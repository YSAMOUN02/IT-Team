<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Course;
use App\Models\CoursePlan;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlansAndFreeCoursesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payway.merchant_id' => 'ec000002', 'payway.api_key' => 'k', 'payway.test_gateway' => false]);
        Mail::fake();
    }

    private function course(array $attributes = []): Course
    {
        return Course::create($attributes + ['title' => 'Advanced Excel', 'slug' => 'advanced-excel', 'is_published' => true, 'currency' => 'USD']);
    }

    public function test_discount_only_applies_inside_its_dates(): void
    {
        $course = $this->course();
        $always = $course->plans()->create(['days' => 30, 'price' => 20, 'discount_percent' => 25]);
        $running = $course->plans()->create(['days' => 90, 'price' => 50, 'discount_percent' => 10, 'discount_starts_at' => now()->subDay(), 'discount_ends_at' => now()->addDay()]);
        $future = $course->plans()->create(['days' => 180, 'price' => 80, 'discount_percent' => 50, 'discount_starts_at' => now()->addWeek()]);
        $ended = $course->plans()->create(['days' => 365, 'price' => 100, 'discount_percent' => 50, 'discount_ends_at' => now()->subDay()]);

        $this->assertSame(15.0, $always->currentPrice());
        $this->assertSame(45.0, $running->currentPrice());
        $this->assertSame(80.0, $future->currentPrice());
        $this->assertSame(100.0, $ended->currentPrice());
        $this->assertSame(25, $course->fresh()->bestDiscount());
        $this->assertSame('$15.00', $course->fresh()->cheapestPlan()->formattedPrice());
    }

    public function test_checkout_charges_the_chosen_plans_discounted_price(): void
    {
        $course = $this->course();
        $course->plans()->create(['days' => 30, 'price' => 20]);
        $year = $course->plans()->create(['days' => 365, 'price' => 120, 'discount_percent' => 30]);

        $this->actingAs(User::factory()->create())->post('/courses/advanced-excel/checkout', [
            'plan_id' => $year->id, 'name' => 'Sok', 'email' => 's@example.com', 'terms' => '1',
            'amount' => '1', // ignored: the price comes from the plan
        ])->assertOk();

        $order = Order::firstOrFail();
        $this->assertSame('84.00', $order->amount);
        $this->assertSame('120.00', $order->original_amount);
        $this->assertSame(30, $order->discount_percent);
        $this->assertSame(365, $order->access_days);
        $this->assertSame($year->id, $order->plan_id);
    }

    public function test_plan_from_another_course_is_rejected(): void
    {
        $course = $this->course();
        $course->plans()->create(['days' => 30, 'price' => 20]);
        $other = Course::create(['title' => 'Other', 'slug' => 'other', 'is_published' => true]);
        $foreign = $other->plans()->create(['days' => 30, 'price' => 1]);

        $this->actingAs(User::factory()->create())
            ->post('/courses/advanced-excel/checkout', ['plan_id' => $foreign->id, 'name' => 'S', 'email' => 's@example.com', 'terms' => '1'])
            ->assertSessionHasErrors('plan_id');
        $this->assertSame(0, Order::count());
    }

    public function test_fully_discounted_plan_gives_access_without_payway(): void
    {
        $course = $this->course();
        $plan = $course->plans()->create(['days' => 30, 'price' => 20, 'discount_percent' => 100]);
        $student = User::factory()->create();

        $this->actingAs($student)->post('/courses/advanced-excel/checkout', [
            'plan_id' => $plan->id, 'name' => 'Sok', 'email' => 's@example.com', 'terms' => '1',
        ])->assertRedirect();

        $this->assertSame('paid', Order::firstOrFail()->status);
        $this->assertTrue($student->canAccessCourse($course));
    }

    public function test_free_course_only_needs_an_account(): void
    {
        $course = $this->course(['is_free' => true, 'slug' => 'excel-starter', 'title' => 'Excel starter']);
        $student = User::factory()->create();

        $this->get('/courses/excel-starter/enroll')->assertOk()->assertSee(route('register.store'), false); // "create account or sign in" first
        $this->get('/courses/excel-starter')->assertOk()->assertSee('/courses/excel-starter/enroll', false);

        $this->actingAs($student)->get('/courses/excel-starter/enroll')->assertRedirect('/learn/excel-starter');
        $enrollment = Enrollment::firstOrFail();
        $this->assertSame('free', $enrollment->source);
        $this->assertNull($enrollment->expires_at);
        $this->assertSame(0, Order::count()); // no payment at all

        // Checkout of a free course goes to the free enrolment instead.
        $this->actingAs($student)->get('/courses/excel-starter/checkout')->assertRedirect('/courses/excel-starter/enroll');
    }

    public function test_course_page_shows_periods_and_prices_even_before_online_payment_is_set_up(): void
    {
        config(['payway.merchant_id' => null, 'payway.api_key' => null, 'payway.test_gateway' => false]);
        $course = $this->course();
        $course->plans()->create(['days' => 90, 'price' => 19]);

        $this->get('/courses/advanced-excel')->assertOk()
            ->assertSee('$19.00')->assertSee('name="plan"', false)->assertSee('/courses/advanced-excel/checkout', false);
    }

    public function test_paid_course_cannot_be_joined_through_the_free_link(): void
    {
        $course = $this->course();
        $course->plans()->create(['days' => 30, 'price' => 20]);
        $student = User::factory()->create();

        $this->actingAs($student)->get('/courses/advanced-excel/enroll')->assertRedirect('/courses/advanced-excel/checkout');
        $this->assertFalse($student->canAccessCourse($course));
    }

    public function test_blog_links_go_to_news(): void
    {
        Article::create(['type' => 'news', 'title' => 'Hello', 'slug' => 'hello', 'published_at' => now()]);

        $this->get('/blog')->assertRedirect('/news')->assertStatus(301);
        $this->get('/blog/hello')->assertRedirect('/news/hello')->assertStatus(301);
        $this->get('/news/hello')->assertOk();
    }
}
