<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RandomStudentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Admin > Students: seeing each student's courses, turning one course or a whole account off and on again. */
class DisableStudentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['email' => 'office@teamit.test']);
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function course(string $title = 'Pivot Table', array $attributes = []): Course
    {
        $course = Course::create($attributes + ['title' => $title, 'slug' => str($title)->slug(), 'is_published' => true]);
        $course->plans()->create(['days' => 90, 'price' => 30]);

        return $course;
    }

    private function lesson(Course $course): CourseLesson
    {
        return $course->lessons()->create(['title' => 'Lesson one', 'slug' => 'lesson-one', 'body' => '<p>Paid text</p>', 'is_published' => true]);
    }

    public function test_admin_turns_one_course_off_and_on_and_the_student_keeps_progress_and_dates(): void
    {
        $course = $this->course();
        $lesson = $this->lesson($course);
        $student = User::factory()->create(['name' => 'Sok Dara']);
        $enrollment = Enrollment::grant($student, $course, 90);
        $until = $enrollment->expires_at->toDateTimeString();
        DB::table('lesson_progress')->insert(['user_id' => $student->id, 'course_lesson_id' => $lesson->id, 'completed_at' => now()]);
        $lessonUrl = "/learn/pivot-table/lessons/{$lesson->slug}";

        $this->actingAs($student)->get($lessonUrl)->assertOk()->assertSee('Paid text');

        $this->actingAs($this->admin())->from("/admin/students/{$student->id}")
            ->patch("/admin/enrollments/{$enrollment->id}", ['disabled' => '1'])
            ->assertRedirect("/admin/students/{$student->id}")->assertSessionHas('status', 'Pivot Table is turned off for Sok Dara.');
        $this->get("/admin/students/{$student->id}")->assertSee('Turned off')->assertSee('Turn on');

        $this->actingAs($student)->get($lessonUrl)->assertRedirect('/courses/pivot-table')->assertSessionHas('checkout_notice', __('account.access_off'));
        $this->get('/learn/pivot-table')->assertRedirect('/courses/pivot-table');
        $this->get('/my/courses')->assertOk()->assertSee(__('account.turned_off'));

        $this->actingAs(User::where('role', 'admin')->first())->patch("/admin/enrollments/{$enrollment->id}", ['disabled' => '0'])
            ->assertSessionHas('status', 'Pivot Table is on again for Sok Dara.');
        $enrollment->refresh();
        $this->assertNull($enrollment->disabled_at);
        $this->assertSame($until, $enrollment->expires_at->toDateTimeString());
        $this->assertSame(1, DB::table('lesson_progress')->where('user_id', $student->id)->count());
        $this->actingAs($student)->get($lessonUrl)->assertOk()->assertSee('Paid text');
    }

    public function test_a_turned_off_course_cannot_be_reopened_by_the_student_but_an_admin_can_give_it_again(): void
    {
        $free = $this->course('Quick Start', ['is_free' => true]);
        $paid = $this->course('Advanced Excel');
        $student = User::factory()->create();
        foreach ([$free, $paid] as $course) {
            Enrollment::grant($student, $course, $course->is_free ? null : 90, $course->is_free ? 'free' : 'payment')->forceFill(['disabled_at' => now()])->save();
        }

        // "Enrol free" again, or buying again, does not undo it.
        $this->actingAs($student)->get('/courses/quick-start/enroll')->assertRedirect('/courses/quick-start')->assertSessionHas('checkout_notice', __('account.access_off'));
        $this->get('/courses/advanced-excel/checkout')->assertRedirect('/courses/advanced-excel')->assertSessionHas('checkout_notice', __('account.access_off'));
        $this->post('/courses/advanced-excel/checkout', ['plan_id' => $paid->plans->first()->id])->assertRedirect('/courses/advanced-excel');
        $this->assertSame(0, $student->orders()->count());
        // A payment that still comes through (an order started earlier) extends the dates but leaves it off.
        Enrollment::grant($student, $paid, 30, 'payment');
        $this->assertFalse($student->fresh()->canAccessCourse($paid));

        // "Give access" by an admin turns it back on.
        $this->actingAs($this->admin())->post("/admin/students/{$student->id}/enrollments", ['course_id' => $paid->id, 'days' => 30]);
        $this->assertTrue($student->fresh()->canAccessCourse($paid));
        $this->assertFalse($student->fresh()->canAccessCourse($free));
    }

    public function test_renewing_a_turned_off_course_keeps_the_days_that_were_left(): void
    {
        $course = $this->course();
        $student = User::factory()->create();
        $enrollment = Enrollment::grant($student, $course, 90);
        $end = $enrollment->expires_at->copy();
        $enrollment->forceFill(['disabled_at' => now()])->save();

        $renewed = Enrollment::grant($student, $course, 30, 'manual');

        $this->assertSame($end->addDays(30)->toDateTimeString(), $renewed->expires_at->toDateTimeString());
        $this->assertNull($renewed->disabled_at);
    }

    public function test_a_turned_off_account_is_signed_out_and_cannot_sign_in_until_turned_on(): void
    {
        $course = $this->course();
        $student = User::factory()->create(['name' => 'Kim Visal', 'email' => 'visal@example.com']);
        Enrollment::grant($student, $course, 90);

        $this->post('/login', ['email' => 'visal@example.com', 'password' => 'password'])->assertRedirect('/my/courses');
        $this->get('/my/courses')->assertOk();

        $admin = $this->admin();
        Auth::forgetGuards();
        $this->actingAs($admin)->patch("/admin/students/{$student->id}", ['disabled' => '1'])
            ->assertSessionHas('status', "Kim Visal's account is turned off. They are signed out and cannot sign in.");
        $this->get("/admin/students/{$student->id}")->assertSee('This account is turned off')->assertSee('Turn account on');
        $this->assertFalse($student->fresh()->canAccessCourse($course));

        // Already signed in: signed out on the next page.
        Auth::forgetGuards();
        $this->actingAs($student->fresh())->get('/my/courses')->assertRedirect('/login')->assertSessionHasErrors(['email' => __('account.account_disabled')]);
        $this->assertGuest();

        // Signing in again: told why only when the password is right.
        $this->post('/login', ['email' => 'visal@example.com', 'password' => 'wrong-one'])->assertSessionHasErrors(['email' => __('account.login_failed')]);
        $this->post('/login', ['email' => 'visal@example.com', 'password' => 'password'])->assertSessionHasErrors(['email' => __('account.account_disabled')]);
        $this->assertGuest();

        $this->actingAs($admin)->patch("/admin/students/{$student->id}", ['disabled' => '0'])->assertSessionHas('status', "Kim Visal's account is on again.");
        Auth::forgetGuards();
        $this->flushSession();
        $this->post('/login', ['email' => 'visal@example.com', 'password' => 'password'])->assertRedirect('/my/courses');
        $this->assertTrue($student->fresh()->canAccessCourse($course));
    }

    public function test_admin_accounts_cannot_be_turned_off(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();
        $other->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin)->patch("/admin/students/{$other->id}", ['disabled' => '1'])->assertNotFound();
        $this->assertNull($other->fresh()->disabled_at);
    }

    public function test_students_list_shows_each_students_courses_and_filters_current_and_turned_off(): void
    {
        $basic = $this->course('Basic Excel');
        $pivot = $this->course('Pivot Table');
        $word = $this->course('Word for Office');

        $dara = User::factory()->create(['name' => 'Sok Dara']);
        Enrollment::grant($dara, $basic, 90);
        Enrollment::grant($dara, $pivot, 90)->forceFill(['disabled_at' => now()])->save();
        $visal = User::factory()->create(['name' => 'Kim Visal']);
        Enrollment::grant($visal, $word, 30)->forceFill(['starts_at' => now()->subDays(60), 'expires_at' => now()->subDays(30)])->save();
        $sokha = User::factory()->create(['name' => 'Mean Sokha']);
        Enrollment::grant($sokha, $basic, null);
        $sokha->forceFill(['disabled_at' => now()])->save();

        $html = $this->actingAs($this->admin())->get('/admin/students')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#status-on" title="Until [^"]+">Basic Excel<#', $html);
        $this->assertMatchesRegularExpression('#status-blocked" title="Turned off [^"]+">Pivot Table<#', $html);
        $this->assertMatchesRegularExpression('#status-off" title="Ended [^"]+">Word for Office<#', $html);
        $this->assertMatchesRegularExpression('#Mean Sokha</a>\s*<span class="status status-blocked">Account off#', $html);

        $this->get('/admin/students?show=current')->assertSee('Sok Dara')->assertDontSee('Kim Visal')->assertDontSee('Mean Sokha');
        $this->get('/admin/students?show=off')->assertSee('Sok Dara')->assertSee('Mean Sokha')->assertDontSee('Kim Visal');

        $this->get("/admin/courses/{$pivot->id}/students")->assertOk()->assertSee('1 turned off')->assertSee('Turn on');
    }

    public function test_random_test_students_and_courses_are_added_and_removed_with_demo_clear(): void
    {
        $this->course('Basic Excel');
        (new RandomStudentsSeeder(12))->run();

        $students = User::where('role', 'student')->get();
        $this->assertCount(12, $students);
        $this->assertSame(4, Course::count());
        $this->assertGreaterThan(0, Enrollment::count());
        $this->assertSame(1, $students->whereNotNull('disabled_at')->count());
        $this->assertNotNull(setting('demo_records'));

        $this->actingAs($this->admin())->get('/admin/students')->assertOk()->assertSee('12 students');

        $this->artisan('demo:clear', ['--force' => true])->assertSuccessful();
        $this->assertSame(0, User::where('role', 'student')->count());
        $this->assertSame(['Basic Excel'], Course::pluck('title')->all());
        $this->assertSame(0, Enrollment::count());
        $this->assertNull(Setting::where('key', 'demo_records')->value('value'));
    }
}
