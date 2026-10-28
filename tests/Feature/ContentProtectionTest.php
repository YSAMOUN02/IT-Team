<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Payments\PayWay;
use App\Support\DeviceLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Paid course content stays with students who paid. */
class ContentProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function course(array $attributes = []): Course
    {
        $course = Course::create($attributes + ['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $course->plans()->create(['days' => 90, 'price' => 30]);

        return $course;
    }

    private function lesson(Course $course, array $attributes = []): CourseLesson
    {
        return $course->lessons()->create($attributes + ['title' => 'Lesson', 'slug' => 'lesson-'.uniqid(), 'body' => '<p>Paid text</p>', 'is_published' => true]);
    }

    private function signInOnNewDevice(User $user): ?string
    {
        Auth::forgetGuards();
        $this->flushSession();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        return session(DeviceLimit::SESSION_KEY);
    }

    public function test_someone_who_has_not_paid_gets_nothing_of_the_course(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('lessons/book.xlsx', 'x');
        $course = $this->course();
        $lesson = $this->lesson($course, ['attachment_path' => 'lessons/book.xlsx', 'attachment_name' => 'book.xlsx', 'solution_path' => 'lessons/book.xlsx']);
        $quiz = $course->quizzes()->create(['type' => 'quiz', 'title' => 'Quiz', 'slug' => 'quiz', 'is_published' => true]);
        $base = "/learn/{$course->slug}";

        foreach ([null, User::factory()->create()] as $visitor) {
            $client = $visitor ? $this->actingAs($visitor) : $this;
            $client->get("$base/lessons/{$lesson->slug}")->assertRedirect("/courses/{$course->slug}");
            $client->get("$base/lessons/{$lesson->slug}/files/practice")->assertForbidden();
            $client->get("$base/lessons/{$lesson->slug}/files/solution")->assertForbidden();
            $visitor
                ? $client->get("$base/quizzes/{$quiz->slug}")->assertRedirect("/courses/{$course->slug}")
                : $client->get("$base/quizzes/{$quiz->slug}")->assertRedirect('/login');
            $visitor
                ? $client->get($base)->assertRedirect("/courses/{$course->slug}")
                : $client->get($base)->assertRedirect('/login');
        }

        // A pending (unpaid) order is not enough either.
        $student = User::factory()->create();
        Order::create(['user_id' => $student->id, 'course_id' => $course->id, 'number' => Order::newNumber(), 'status' => 'pending', 'amount' => 30, 'currency' => 'USD', 'access_days' => 90, 'course_title' => 'Pivot Table', 'buyer_name' => 'S', 'buyer_email' => $student->email]);
        $this->actingAs($student)->get("$base/lessons/{$lesson->slug}")->assertRedirect("/courses/{$course->slug}");
        $this->actingAs($student)->get('/courses/pivot-table')->assertDontSee('Paid text');
    }

    public function test_paid_pages_are_never_kept_in_the_browser_cache(): void
    {
        $course = $this->course();
        $lesson = $this->lesson($course);
        $student = User::factory()->create();
        Enrollment::grant($student, $course, 90);

        $response = $this->actingAs($student)->get("/learn/{$course->slug}/lessons/{$lesson->slug}")->assertOk()->assertSee('Paid text', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $this->actingAs($student)->get('/my/courses')->headers->get('Cache-Control'));
    }

    public function test_hidden_lessons_and_unpublished_courses_do_not_leak_through_previews(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('lessons/a.xlsx', 'x');
        $course = $this->course();
        $hidden = $this->lesson($course, ['is_published' => false, 'is_preview' => true, 'attachment_path' => 'lessons/a.xlsx']);
        $student = User::factory()->create();
        Enrollment::grant($student, $course, 90);

        $this->actingAs($student)->get("/learn/{$course->slug}/lessons/{$hidden->slug}/files/practice")->assertNotFound();

        $draft = $this->course(['slug' => 'draft', 'is_published' => false]);
        $preview = $this->lesson($draft, ['is_preview' => true]);
        $this->get("/learn/draft/lessons/{$preview->slug}")->assertRedirect('/courses/draft');
    }

    public function test_a_shared_account_signs_out_the_oldest_device(): void
    {
        $course = $this->course();
        $student = User::factory()->create();
        Enrollment::grant($student, $course, 90);

        $first = $this->signInOnNewDevice($student);
        $second = $this->signInOnNewDevice($student);
        $third = $this->signInOnNewDevice($student); // default limit is 2 devices

        $this->assertCount(2, $student->fresh()->device_sessions);

        Auth::forgetGuards();
        $this->actingAs($student->fresh())->withSession([DeviceLimit::SESSION_KEY => $first])
            ->get("/learn/{$course->slug}")->assertRedirect('/login')->assertSessionHas('status', __('account.signed_out_elsewhere'));
        $this->assertGuest();

        foreach ([$second, $third] as $token) {
            Auth::forgetGuards();
            $this->actingAs($student->fresh())->withSession([DeviceLimit::SESSION_KEY => $token])->get("/learn/{$course->slug}")->assertOk();
        }
    }

    public function test_device_limit_can_be_switched_off_and_never_applies_to_admins(): void
    {
        $student = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        Setting::put(['student_device_limit' => '0']);
        $old = $this->signInOnNewDevice($student);
        $this->assertNull($old);
        $this->signInOnNewDevice($student);
        $this->assertSame([], $student->fresh()->device_sessions ?? []);

        Setting::put(['student_device_limit' => '1']);
        $this->assertFalse(DeviceLimit::applies($admin));
        $this->actingAs($admin)->withSession([DeviceLimit::SESSION_KEY => 'stale'])->get('/admin')->assertOk();
    }

    public function test_refund_takes_back_the_access_it_paid_for(): void
    {
        $course = $this->course();
        $student = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $make = fn (int $days, $paidAt) => tap(Order::create([
            'user_id' => $student->id, 'course_id' => $course->id, 'number' => Order::newNumber(), 'status' => 'paid',
            'amount' => 30, 'currency' => 'USD', 'access_days' => $days, 'paid_at' => $paidAt,
            'course_title' => 'Pivot Table', 'buyer_name' => 'S', 'buyer_email' => $student->email,
        ]), fn ($order) => Enrollment::grant($student, $course, $days, 'payment', $order));

        $first = $make(30, now());
        $renewal = $make(90, now());
        $this->assertEqualsWithDelta(now()->addDays(120)->timestamp, Enrollment::first()->expires_at->timestamp, 10);

        // Refunding the renewal keeps the first 30 days.
        $this->actingAs($admin)->post("/admin/orders/{$renewal->number}/refund")->assertRedirect();
        $this->assertSame('refunded', $renewal->fresh()->status);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, Enrollment::first()->expires_at->timestamp, 10);
        $this->assertTrue($student->fresh()->canAccessCourse($course));

        // Refunding the last paid order ends access.
        $this->actingAs($admin)->post("/admin/orders/{$first->number}/refund")->assertRedirect();
        $this->assertFalse($student->fresh()->canAccessCourse($course));
    }

    public function test_test_payment_page_never_runs_on_a_real_domain(): void
    {
        config(['payway.merchant_id' => null, 'payway.api_key' => null, 'payway.test_gateway' => null]);

        $openedAt = fn (string $url) => $this->app->instance('request', Request::create($url));

        config(['app.url' => 'http://127.0.0.1:8124']);
        $openedAt('http://127.0.0.1:8124/courses');
        $this->assertTrue(app(PayWay::class)->usesTestGateway());
        $openedAt('http://192.168.9.12:8001/courses'); // the team on the office network
        $this->assertTrue(app(PayWay::class)->usesTestGateway());
        $openedAt('https://teamitcambodia.com/courses'); // reached from the internet
        $this->assertFalse(app(PayWay::class)->usesTestGateway());

        // Live server with APP_ENV still "local" and no PayWay keys yet.
        config(['app.url' => 'https://teamitcambodia.com']);
        $this->assertFalse(app(PayWay::class)->usesTestGateway());
        config(['payway.test_gateway' => true]);
        $this->assertFalse(app(PayWay::class)->usesTestGateway());
    }

    public function test_lesson_next_link_stays_on_this_site(): void
    {
        $course = $this->course();
        $lesson = $this->lesson($course);
        $student = User::factory()->create();
        Enrollment::grant($student, $course, 90);

        foreach (['https://evil.example/phish', '//evil.example', 'https://evil.example'] as $next) {
            $location = $this->actingAs($student)
                ->post("/learn/{$course->slug}/lessons/{$lesson->slug}/complete", ['next' => $next])->headers->get('Location');
            $this->assertStringNotContainsString('evil.example', (string) $location);
        }
        $this->actingAs($student)->post("/learn/{$course->slug}/lessons/{$lesson->slug}/complete", ['next' => "/learn/{$course->slug}"])
            ->assertRedirect("/learn/{$course->slug}");
    }
}
