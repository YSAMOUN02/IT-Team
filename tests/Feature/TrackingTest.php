<?php

namespace Tests\Feature;

use App\Models\AdClick;
use App\Models\AdView;
use App\Models\Article;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\PageView;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Admin > Tracking: posts, courses and ads, from one date to another. */
class TrackingTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Carbon::setTestNow('2026-11-03 15:00:00');
    }

    private function adminUser(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function article(string $slug = 'hello', string $type = 'tutorial'): Article
    {
        return Article::create(['type' => $type, 'title' => ucfirst($slug), 'slug' => $slug, 'body' => '<p>Text</p>', 'is_published' => true, 'published_at' => '2026-01-01 08:00:00']);
    }

    private function pageView(string $when, array $attributes = []): PageView
    {
        return PageView::create($attributes + [
            'visitor_id' => str_repeat('a', 32), 'session_id' => str_repeat('b', 32), 'path' => '/x', 'kind' => 'page',
            'device' => 'Mobile', 'browser' => 'Chrome', 'os' => 'Android', 'created_at' => $when, 'updated_at' => $when,
        ]);
    }

    private function tracking(array $query = [])
    {
        return $this->actingAs($this->adminUser())->get(route('admin.tracking', $query))->assertOk();
    }

    private function banner(string $spot, string $path = 'promos/2026/11/sale.gif', string $picture = 'a picture'): void
    {
        Storage::disk('public')->put($path, $picture);
        Setting::put(["ad_{$spot}_enabled" => '1', "ad_{$spot}_type" => 'image', "ad_{$spot}_image" => $path, "ad_{$spot}_link" => '/courses']);
    }

    public function test_a_view_of_a_post_is_linked_to_that_post(): void
    {
        $tutorial = $this->article('hello');
        $news = $this->article('hello', 'news');
        $khmer = $this->article('មេរៀន-ទី១');

        $this->withHeader('User-Agent', self::PHONE)->post('/stats/view', ['path' => '/tutorials/hello'])->assertOk();
        $this->post('/stats/view', ['path' => '/news/hello'])->assertOk();
        $this->post('/stats/view', ['path' => '/tutorials/'.rawurlencode('មេរៀន-ទី១')])->assertOk();
        $this->post('/stats/view', ['path' => '/tutorials'])->assertOk();
        $this->post('/stats/view', ['path' => '/tutorials/no-such-post'])->assertOk();

        $this->assertSame([$tutorial->id, 'post'], [PageView::where('path', '/tutorials/hello')->value('article_id'), PageView::where('path', '/tutorials/hello')->value('kind')]);
        $this->assertSame($news->id, PageView::where('path', '/news/hello')->value('article_id'));
        $this->assertSame($khmer->id, PageView::where('path', '/tutorials/មេរៀន-ទី១')->value('article_id'));
        foreach (['/tutorials', '/tutorials/no-such-post'] as $path) {
            $this->assertSame([null, 'page'], [PageView::where('path', $path)->value('article_id'), PageView::where('path', $path)->value('kind')]);
        }
    }

    public function test_posts_count_only_inside_the_dates_with_the_last_day_included(): void
    {
        $post = $this->article();
        $quiet = $this->article('quiet');
        $seen = ['kind' => 'post', 'article_id' => $post->id];

        $this->pageView('2026-10-09 23:59:59', $seen);                                           // the day before
        $this->pageView('2026-10-10 00:00:00', $seen + ['seconds' => 30]);                       // first moment
        $this->pageView('2026-10-20 23:59:59', $seen + ['seconds' => 90, 'visitor_id' => str_repeat('c', 32)]); // last moment
        $this->pageView('2026-10-21 00:00:00', $seen);                                           // the day after

        $response = $this->tracking(['from' => '2026-10-10', 'to' => '2026-10-20']);
        $row = $response->viewData('rows')->first();

        $this->assertTrue($row->article->is($post)); // most read first
        $this->assertSame([2, 2, 60], [$row->views, $row->visitors, $row->avg_seconds]);
        $this->assertSame([0, 0], [$response->viewData('rows')->firstWhere('article.id', $quiet->id)->views, $response->viewData('rows')->firstWhere('article.id', $quiet->id)->visitors]);
        $this->assertSame([2, 2, 60, 1, 2], [$response->viewData('totals')->views, $response->viewData('totals')->visitors, $response->viewData('totals')->avg_seconds, $response->viewData('totals')->read, $response->viewData('totals')->all]);
        $response->assertSee('10 Oct 2026 – 20 Oct 2026')->assertSee('11 days');

        // Compared with the same number of days just before (1 view on 9 Oct, in 30 Sep – 9 Oct... 11 days).
        $this->assertSame(100, $response->viewData('change')['views']);
    }

    public function test_one_post_on_its_own_and_only_one_kind_of_post(): void
    {
        $first = $this->article('first');
        $second = $this->article('second', 'news');
        $this->pageView('2026-10-15 10:00:00', ['kind' => 'post', 'article_id' => $first->id, 'source' => 'Facebook']);
        $this->pageView('2026-10-15 11:00:00', ['kind' => 'post', 'article_id' => $second->id, 'source' => 'Google']);
        $dates = ['from' => '2026-10-01', 'to' => '2026-10-31'];

        $all = $this->tracking($dates);
        $this->assertSame(2, $all->viewData('totals')->views);

        $one = $this->get(route('admin.tracking', $dates + ['item' => $first->id]))->assertOk()->assertSee('Show all posts');
        $this->assertSame(1, $one->viewData('totals')->views);
        $this->assertSame(['Facebook'], $one->viewData('sources')->pluck('name')->all());
        $this->assertTrue($one->viewData('selected')->is($first));
        $this->assertSame(2, $one->viewData('rows')->count()); // the list stays, to pick another

        $news = $this->get(route('admin.tracking', $dates + ['type' => 'news']))->assertOk();
        $this->assertSame(1, $news->viewData('totals')->views);
        $this->assertSame([$second->id], $news->viewData('rows')->pluck('article.id')->all());
    }

    public function test_dates_that_make_no_sense_are_handled(): void
    {
        $admin = $this->adminUser();
        $dates = fn (array $query) => array_map(
            fn ($date) => $date->format('Y-m-d H:i:s'),
            [($page = $this->actingAs($admin)->get(route('admin.tracking', $query))->assertOk())->viewData('from'), $page->viewData('to')]
        );

        // Nothing or nonsense: the last 30 days.
        $this->assertSame(['2026-10-05 00:00:00', '2026-11-03 23:59:59'], $dates([]));
        $this->assertSame(['2026-10-05 00:00:00', '2026-11-03 23:59:59'], $dates(['from' => '2026-13-45', 'to' => 'soon']));
        $this->assertSame(['2026-10-05 00:00:00', '2026-11-03 23:59:59'], $dates(['from' => '2026-02-31', 'to' => "'; drop table--"]));
        // The wrong way round: swapped.
        $this->assertSame(['2026-10-01 00:00:00', '2026-10-20 23:59:59'], $dates(['from' => '2026-10-20', 'to' => '2026-10-01']));
        // The future: up to today.
        $this->assertSame(['2026-11-01 00:00:00', '2026-11-03 23:59:59'], $dates(['from' => '2026-11-01', 'to' => '2027-01-01']));
        $this->assertSame(['2026-11-03 00:00:00', '2026-11-03 23:59:59'], $dates(['from' => '2027-01-01', 'to' => '2027-02-01']));
        // A mistyped year does not reach back for centuries.
        $this->assertSame('2016-11-03 00:00:00', $dates(['from' => '0202-01-01', 'to' => '2026-11-03'])[0]);
    }

    public function test_the_chart_steps_by_hour_day_or_month(): void
    {
        $post = $this->article();
        $this->pageView('2026-10-15 09:30:00', ['kind' => 'post', 'article_id' => $post->id]);
        $this->pageView('2026-10-15 09:40:00', ['kind' => 'post', 'article_id' => $post->id]);
        $admin = $this->adminUser();
        $open = fn (array $query) => $this->actingAs($admin)->get(route('admin.tracking', $query))->assertOk();

        $day = $open(['from' => '2026-10-15', 'to' => '2026-10-15']);
        $this->assertSame('hour', $day->viewData('per'));
        $this->assertCount(24, $day->viewData('chart'));
        $this->assertSame(2, $day->viewData('chart')[9]['value']);

        // Today: only the hours so far (it is 15:00).
        $this->assertCount(16, $open(['from' => '2026-11-03', 'to' => '2026-11-03'])->viewData('chart'));

        $month = $open(['from' => '2026-10-01', 'to' => '2026-10-31']);
        $this->assertSame('day', $month->viewData('per'));
        $this->assertCount(31, $month->viewData('chart'));
        $this->assertSame(['Thu 15 Oct', 2], [$month->viewData('chart')[14]['label'], $month->viewData('chart')[14]['value']]);

        $year = $open(['from' => '2026-01-15', 'to' => '2026-11-03']);
        $this->assertSame('month', $year->viewData('per'));
        $this->assertCount(11, $year->viewData('chart'));
        $this->assertSame(['October 2026', 2], [$year->viewData('chart')[9]['label'], $year->viewData('chart')[9]['value']]);
    }

    public function test_courses_show_opened_clicked_students_and_income_in_the_dates(): void
    {
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $other = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $courseView = fn (string $when, string $kind, string $who) => $this->pageView($when, ['course_id' => $course->id, 'kind' => $kind, 'visitor_id' => str_repeat($who, 32)]);

        $courseView('2026-10-15 09:00:00', 'course', 'a');
        $courseView('2026-10-15 10:00:00', 'course', 'a');
        $courseView('2026-10-16 09:00:00', 'course', 'b');
        $courseView('2026-10-16 09:05:00', 'checkout', 'b');
        $courseView('2026-10-17 09:00:00', 'lesson', 'b');
        $courseView('2026-09-01 09:00:00', 'course', 'c'); // outside

        $enrol = function (string $when, string $source = 'manual', ?string $done = null) use ($course) {
            $student = User::factory()->create();
            Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'starts_at' => $when, 'source' => $source, 'completed_at' => $done, 'created_at' => $when, 'updated_at' => $when]);

            return $student;
        };
        $inside = $enrol('2026-10-20 09:00:00', 'manual', '2026-10-25 09:00:00');
        $enrol('2026-09-20 09:00:00');                     // outside
        $enrol('2026-10-21 09:00:00', 'certificate');      // a certificate only: not a new student

        $invoice = fn (string $when, float $amount, string $status = 'paid', string $currency = 'USD') => Invoice::create([
            'user_id' => $inside->id, 'course_id' => $course->id, 'course_title' => $course->title, 'student_name' => 'Sok', 'student_email' => 's@example.com',
            'amount' => $amount, 'currency' => $currency, 'payment_method' => 'cash', 'status' => $status, 'issued_at' => $when,
        ]);
        $invoice('2026-10-20 09:00:00', 29);
        $invoice('2026-10-21 09:00:00', 16.5);
        $invoice('2026-10-22 09:00:00', 100, 'refunded'); // refunds are left out
        $invoice('2026-09-20 09:00:00', 45);              // outside

        $response = $this->tracking(['tab' => 'courses', 'from' => '2026-10-01', 'to' => '2026-10-31']);
        $row = $response->viewData('rows')->firstWhere('course.id', $course->id);

        $this->assertSame([3, 2, 1, 1, 1, 1, '$45.50'], [$row->views, $row->visitors, $row->clicked_buy, $row->studying, $row->students, $row->finished, $row->income]);
        $this->assertSame([0, 0, '—'], [$response->viewData('rows')->firstWhere('course.id', $other->id)->visitors, $response->viewData('rows')->firstWhere('course.id', $other->id)->students, $response->viewData('rows')->firstWhere('course.id', $other->id)->income]);
        $this->assertSame([2, 1, 1, '$45.50', 2], [$response->viewData('totals')->visitors, $response->viewData('totals')->clicked_buy, $response->viewData('totals')->students, $response->viewData('totals')->income, $response->viewData('totals')->invoices]);
        $response->assertSee('Pivot Table')->assertSee('$45.50')->assertSee('50% of opened');

        // One course on its own.
        $alone = $this->get(route('admin.tracking', ['tab' => 'courses', 'from' => '2026-10-01', 'to' => '2026-10-31', 'item' => $other->id]))->assertOk();
        $this->assertSame([0, 0, '—'], [$alone->viewData('totals')->visitors, $alone->viewData('totals')->students, $alone->viewData('totals')->income]);
    }

    public function test_a_banner_on_screen_counts_as_a_view_for_visitors_only(): void
    {
        $this->banner('header');
        Setting::put(['ad_footer_enabled' => '1', 'ad_footer_type' => 'image']); // on, but nothing to show

        // Live banners tell the page to report them; an empty spot does not.
        $page = $this->withHeader('User-Agent', self::PHONE)->get('/courses')->assertOk();
        $page->assertSee('data-spot="header"', false)->assertDontSee('data-spot="footer"', false)->assertSee('data-track-spot', false);

        $this->post('/stats/spot', ['spot' => 'header'])->assertNoContent();
        $this->post('/stats/spot', ['spot' => 'footer'])->assertNoContent();   // nothing to show: not counted
        $this->post('/stats/spot', ['spot' => 'nowhere'])->assertNoContent();  // no such spot
        $this->assertSame(['header'], AdView::pluck('placement')->all());
        $this->assertNull(AdView::first()->visitor_id);

        // With the visitor's cookie, people can be told apart.
        $visitor = str_repeat('d', 32);
        $this->withCookie('tic_vid', $visitor)->post('/stats/spot', ['spot' => 'header'])->assertNoContent();
        $this->assertSame($visitor, AdView::latest('id')->first()->visitor_id);

        // Bots and admins are not counted, and an admin's page does not report at all.
        $this->withHeader('User-Agent', 'Googlebot/2.1')->post('/stats/spot', ['spot' => 'header'])->assertNoContent();
        $this->assertSame(2, AdView::count());
        $admin = $this->adminUser();
        $this->actingAs($admin)->withHeader('User-Agent', self::PHONE)->post('/stats/spot', ['spot' => 'header'])->assertNoContent();
        $this->assertSame(2, AdView::count());
        $this->get('/courses')->assertDontSee('data-track-spot', false);
    }

    public function test_ads_show_views_clicks_and_click_rate_in_the_dates(): void
    {
        $this->banner('header');
        foreach (['2026-10-10 09:00:00', '2026-10-11 09:00:00', '2026-10-12 09:00:00', '2026-10-13 09:00:00'] as $i => $when) {
            AdView::forceCreate(['placement' => 'header', 'visitor_id' => $i < 3 ? str_repeat('a', 32) : str_repeat('b', 32), 'created_at' => $when]);
        }
        AdView::forceCreate(['placement' => 'header', 'visitor_id' => null, 'created_at' => '2026-09-01 09:00:00']);   // outside
        AdClick::forceCreate(['placement' => 'header', 'created_at' => '2026-10-12 09:30:00']);
        AdClick::forceCreate(['placement' => 'header', 'created_at' => '2026-09-01 09:30:00']);                        // outside
        // Clicks from before views were counted: no rate rather than 300%.
        foreach (range(1, 3) as $i) {
            AdClick::forceCreate(['placement' => 'footer', 'created_at' => '2026-10-12 09:30:00']);
        }
        AdView::forceCreate(['placement' => 'footer', 'created_at' => '2026-10-12 09:00:00']);

        $response = $this->tracking(['tab' => 'ads', 'from' => '2026-10-01', 'to' => '2026-10-31']);
        $header = $response->viewData('rows')->firstWhere('key', 'header');
        $this->assertSame([4, 2, 1, 25.0], [$header->views, $header->visitors, $header->clicks, $header->rate]);
        $this->assertNull($response->viewData('rows')->firstWhere('key', 'footer')->rate);
        $this->assertSame(['live', 'Below the header'], [$header->state, $header->label]);
        $response->assertSee('Your banner')->assertSee('25%');

        // One spot on its own, with both charts.
        $alone = $this->get(route('admin.tracking', ['tab' => 'ads', 'from' => '2026-10-01', 'to' => '2026-10-31', 'item' => 'header']))->assertOk();
        $this->assertSame([4, 2, 1], [$alone->viewData('totals')->views, $alone->viewData('totals')->visitors, $alone->viewData('totals')->clicks]);
        $this->assertSame(1, collect($alone->viewData('chartClicks'))->sum('value'));
        $this->assertSame('Below the header', $alone->viewData('selectedLabel'));
    }

    public function test_a_spot_hidden_as_the_same_picture_says_so(): void
    {
        $this->banner('header', 'promos/2026/11/a.gif');
        $this->banner('listing', 'promos/2026/11/b.gif');

        $this->tracking(['tab' => 'ads'])->assertSee('Same picture as header');

        $this->banner('listing', 'promos/2026/11/c.gif', 'another picture');
        $this->tracking(['tab' => 'ads'])->assertDontSee('Same picture as header');
    }

    public function test_every_tab_opens_with_nothing_recorded_for_any_dates(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin)->get('/admin')->assertSee(route('admin.tracking'));

        foreach (array_keys(\App\Http\Controllers\Admin\TrackingController::TABS) as $tab) {
            foreach ([[], ['from' => '2026-11-03', 'to' => '2026-11-03'], ['from' => '2025-01-01', 'to' => '2026-11-03']] as $dates) {
                $this->actingAs($admin)->get(route('admin.tracking', ['tab' => $tab] + $dates))->assertOk()->assertSee('Tracking');
            }
        }
        $this->get(route('admin.tracking', ['tab' => 'nonsense']))->assertOk()->assertViewHas('tab', 'posts');
        $this->get(route('admin.tracking', ['item' => 'nonsense']))->assertOk()->assertViewHas('selected', null);
    }

    public function test_only_admins_can_open_it(): void
    {
        $this->get(route('admin.tracking'))->assertRedirect(route('admin.login'));
        $this->actingAs(User::factory()->create())->get(route('admin.tracking'))->assertRedirect(); // students are sent away, as everywhere in the admin
    }
}
