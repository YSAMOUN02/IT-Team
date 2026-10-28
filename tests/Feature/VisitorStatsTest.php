<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Website statistics: counting page views and the Admin > Visitors page. */
class VisitorStatsTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36';

    private const FACEBOOK_APP = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 [FBAN/FBIOS;FBAV/470.0]';

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    public function test_a_page_view_is_counted_with_device_browser_and_source(): void
    {
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);

        $response = $this->withHeader('User-Agent', self::FACEBOOK_APP)
            ->post('/stats/view', ['path' => '/courses/pivot-table', 'title' => 'Pivot Table | TEAMIT', 'referrer' => 'https://m.facebook.com/'])
            ->assertOk()->assertJsonStructure(['id']);

        $view = PageView::firstOrFail();
        $this->assertSame(['course', $course->id, 'Facebook', 'Mobile', 'Facebook app', 'iOS'], [$view->kind, $view->course_id, $view->source, $view->device, $view->browser, $view->os]);
        $this->assertSame('/courses/pivot-table', $view->path);
        $response->assertCookie('tic_vid', $view->visitor_id)->assertCookie('tic_sid', $view->session_id);
    }

    public function test_later_pages_in_the_same_visit_keep_where_the_visit_came_from(): void
    {
        Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $first = $this->withHeader('User-Agent', self::PHONE)->post('/stats/view', ['path' => '/', 'referrer' => 'https://www.google.com/']);
        $visitor = $first->getCookie('tic_vid')->getValue();
        $visit = $first->getCookie('tic_sid')->getValue();

        $this->withHeader('User-Agent', self::PHONE)->withCookie('tic_vid', $visitor)->withCookie('tic_sid', $visit)
            ->post('/stats/view', ['path' => '/courses/pivot-table/checkout', 'referrer' => url('/courses/pivot-table')])->assertOk();

        $second = PageView::latest('id')->first();
        $this->assertSame(['Google', 'checkout', $visitor, $visit], [$second->source, $second->kind, $second->visitor_id, $second->session_id]);
    }

    public function test_bots_admins_and_admin_pages_are_not_counted(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->post('/stats/view', ['path' => '/'])->assertNoContent();
        $this->withHeader('User-Agent', self::PHONE)->post('/stats/view', ['path' => '/admin/courses'])->assertNoContent();
        $this->actingAs($this->admin())->withHeader('User-Agent', self::PHONE)->post('/stats/view', ['path' => '/'])->assertNoContent();

        $this->assertSame(0, PageView::count());
    }

    public function test_time_on_page_only_grows_is_capped_and_belongs_to_the_visitor(): void
    {
        $response = $this->withHeader('User-Agent', self::PHONE)->post('/stats/view', ['path' => '/news']);
        $id = $response->json('id');
        $visitor = $response->getCookie('tic_vid')->getValue();

        $this->withCookie('tic_vid', $visitor)->post('/stats/leave', ['id' => $id, 'seconds' => 42])->assertNoContent();
        $this->withCookie('tic_vid', $visitor)->post('/stats/leave', ['id' => $id, 'seconds' => 10]);
        $this->assertSame(42, PageView::find($id)->seconds);

        $this->withCookie('tic_vid', $visitor)->post('/stats/leave', ['id' => $id, 'seconds' => 5000]); // tab left open
        $this->assertSame(1800, PageView::find($id)->seconds);

        $this->withCookie('tic_vid', str_repeat('a', 32))->post('/stats/leave', ['id' => $id, 'seconds' => 5]);
        $this->assertSame(1800, PageView::find($id)->seconds); // someone else's cookie changes nothing
    }

    public function test_browsing_many_pages_does_not_use_up_sign_in_tries(): void
    {
        foreach (range(1, 15) as $page) {
            $id = $this->withHeader('User-Agent', self::PHONE)->post('/stats/view', ['path' => "/news/{$page}"])->assertOk()->json('id');
            $this->post('/stats/leave', ['id' => $id, 'seconds' => 3])->assertNoContent();
        }

        $this->post('/login', ['login' => 'nobody@example.com', 'password' => 'wrong'])->assertStatus(302);
    }

    public function test_admin_sees_visitors_pages_courses_and_activity(): void
    {
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $student = User::factory()->create(['name' => 'Sok Dara']);
        $base = ['source' => 'Facebook', 'device' => 'Mobile', 'browser' => 'Chrome', 'os' => 'Android'];
        foreach ([['a', 'course'], ['a', 'checkout'], ['b', 'course'], ['c', 'page']] as [$who, $kind]) {
            PageView::create($base + [
                'visitor_id' => str_repeat($who, 32), 'session_id' => str_repeat($who, 32), 'user_id' => $who === 'a' ? $student->id : null,
                'path' => $kind === 'page' ? '/about' : '/courses/pivot-table'.($kind === 'checkout' ? '/checkout' : ''),
                'title' => 'Pivot Table', 'course_id' => $kind === 'page' ? null : $course->id, 'kind' => $kind, 'seconds' => 30,
            ]);
        }

        $page = $this->actingAs($this->admin())->get('/admin/visitors?range=7d')->assertOk();
        $page->assertSee('Courses: what people click on')->assertSee('Pivot Table')->assertSee('Sok Dara')
            ->assertSee('50% of opened', false)   // 2 opened the course page, 1 clicked Buy
            ->assertSee('Clicked Buy');
        $this->assertSame(3, $page->viewData('totals')->visitors);
        $this->assertSame(4, $page->viewData('totals')->views);

        $journey = $this->actingAs($this->admin())->get('/admin/visitors?visitor='.str_repeat('a', 32))->assertOk();
        $this->assertCount(2, $journey->viewData('activity'));
    }

    public function test_students_cannot_open_the_visitors_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/visitors')->assertRedirect();
    }
}
