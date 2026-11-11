<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The public site's shared pieces: course cards, the course page's phone bar, the logo, the 404. */
class SiteDesignTest extends TestCase
{
    use RefreshDatabase;

    private function course(array $attributes = []): Course
    {
        return Course::create($attributes + ['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
    }

    public function test_a_course_card_shows_cover_facts_and_price(): void
    {
        $paid = $this->course(['image' => 'courses/basic.jpg', 'badge' => '150', 'duration' => '12 hours', 'summary' => 'Start here.']);
        $paid->plans()->create(['days' => 90, 'price' => 30, 'discount_percent' => 10, 'position' => 1]);
        $paid->plans()->create(['days' => 365, 'price' => 49, 'position' => 2]);
        $paid->lessons()->create(['title' => 'One', 'slug' => 'one', 'position' => 1, 'is_published' => true]);
        $paid->lessons()->create(['title' => 'Two', 'slug' => 'two', 'position' => 2, 'is_published' => true]);
        $paid->lessons()->create(['title' => 'Draft', 'slug' => 'draft', 'position' => 3, 'is_published' => false]);
        $free = $this->course(['title' => 'Quick Start', 'slug' => 'quick-start', 'is_free' => true]);

        foreach (['/', route('courses.index')] as $url) {
            $page = $this->get($url)->assertOk();
            $html = $page->getContent();

            // Paid: its cover, the best discount, learners, published lessons only, the lowest price.
            $page->assertSee('class="course-cover"', false)->assertSee('courses/basic.jpg')
                ->assertSee('<span class="course-flag is-sale">−10%</span>', false)
                ->assertSee('data-count="150"', false)
                ->assertSee(trans_choice('account.lessons_count', 2, ['count' => 2]))
                ->assertSee(__('account.from_price', ['price' => '$27.00']));
            // The whole card is one link, the title's: "View course" is only a label.
            $page->assertSee('<h3 class="card-title"><a href="'.$paid->url().'">Basic Excel</a></h3>', false)
                ->assertSee('<span class="card-link" aria-hidden="true">', false);
            $this->assertSame(0, substr_count($html, '<a class="card-link" href="'.$paid->url().'"'), $url);

            // Free: says so, and has a drawn cover while no picture is uploaded.
            $page->assertSee('<span class="course-flag is-free">'.__('account.free').'</span>', false)
                ->assertSee('class="course-cover-empty"', false);
        }
    }

    public function test_the_phone_bar_on_a_course_page_is_about_that_course(): void
    {
        $course = $this->course(['image' => 'courses/basic.jpg']);
        $course->plans()->create(['days' => 90, 'price' => 19, 'position' => 1]);
        // Listed lower on the page under "Our courses", in a loop that reuses the same variable name.
        $this->course(['title' => 'Quick Start', 'slug' => 'quick-start', 'is_free' => true, 'sort_order' => 9]);

        $page = $this->get($course->url())->assertOk()->assertSee('class="price-cover"', false)->assertDontSee('featured-image', false);
        $bar = $this->phoneBar($page->getContent());
        $this->assertStringContainsString('<small>Basic Excel</small>', $bar);
        $this->assertStringContainsString('$19.00', $bar);
        $this->assertStringContainsString('href="#buy"', $bar);
        $this->assertStringNotContainsString('Quick Start', $bar);

        // A student who already has the course goes straight in.
        $student = User::factory()->create();
        Enrollment::grant($student, $course, null, 'manual');
        $bar = $this->phoneBar($this->actingAs($student)->get($course->url())->getContent());
        $this->assertStringContainsString('href="'.route('learn.course', $course->slug).'"', $bar);
        $this->assertStringContainsString(__('account.go_to_course'), $bar);

        // A free course offers the free enrolment.
        $free = Course::where('slug', 'quick-start')->sole();
        $bar = $this->phoneBar($this->get($free->url())->getContent());
        $this->assertStringContainsString('href="'.route('courses.enroll', $free->slug).'"', $bar);
        $this->assertStringContainsString('<small>Quick Start</small>', $bar);
    }

    public function test_the_logo_is_the_emblem_until_a_logo_picture_is_uploaded(): void
    {
        $this->get('/')->assertOk()->assertSee('class="logo-mark"', false)->assertDontSee('logo-emblem', false);

        Setting::put(['favicon' => 'brand/emblem.png']);
        $this->get('/')->assertSee('<img class="logo-emblem" src="'.media_url('brand/emblem.png').'"', false)
            ->assertSee('class="logo-text"', false)->assertDontSee('class="logo-mark"', false);

        Setting::put(['logo' => 'brand/logo.png']);
        $this->get('/')->assertSee('<img src="'.media_url('brand/logo.png').'"', false)
            ->assertDontSee('logo-emblem', false)->assertDontSee('class="logo-text"', false);
    }

    public function test_the_404_page_shows_the_address_safely(): void
    {
        $this->get('/no/such/page')->assertNotFound()->assertSee('=PAGE("no/such/page")', false)->assertSee('#REF!');
        $this->get('/%3Cb%3Ebold')->assertNotFound()->assertDontSee('<b>bold', false);
    }

    /** The bar's own HTML, from the page. */
    private function phoneBar(string $html): string
    {
        $this->assertSame(1, substr_count($html, 'data-buy-bar'));
        $start = strpos($html, '<div class="buy-bar"');
        $end = strpos($html, '<section', $start) ?: strlen($html); // the bar sits between the hero and the next section

        return substr($html, $start, $end - $start);
    }
}
