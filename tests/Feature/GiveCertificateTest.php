<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\CourseProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Admin > Students: giving a certificate by hand (e.g. a class at the office) and taking it back. */
class GiveCertificateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function course(string $title = 'Pivot Table'): Course
    {
        $course = Course::create(['title' => $title, 'slug' => str($title)->slug(), 'is_published' => true, 'certificate_enabled' => true]);
        $course->plans()->create(['days' => 90, 'price' => 30]);
        $course->lessons()->create(['title' => 'Lesson one', 'slug' => 'lesson-one', 'body' => '<p>Paid text</p>', 'is_published' => true]);

        return $course;
    }

    public function test_admin_gives_a_certificate_to_a_student_who_is_studying_the_course(): void
    {
        $course = $this->course();
        $student = User::factory()->create(['name' => 'Sok Dara']);
        Enrollment::grant($student, $course, 90);

        $this->actingAs($this->admin())->from("/admin/students/{$student->id}")
            ->post("/admin/students/{$student->id}/certificates", ['course_id' => $course->id])
            ->assertRedirect("/admin/students/{$student->id}")->assertSessionHasNoErrors();

        $enrollment = $student->enrollments()->first();
        $this->assertMatchesRegularExpression('/^TIC-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $enrollment->certificate_code);
        $this->assertTrue($enrollment->completed_at->isToday());
        $this->assertTrue($student->canAccessCourse($course), 'access is unchanged');
        $this->get("/admin/students/{$student->id}")->assertSee($enrollment->certificate_code)->assertSee('Take back');

        // Anyone can check it, and the student sees it in "My courses".
        $this->get("/certificates/{$enrollment->certificate_code}")->assertOk()->assertSee('Sok Dara')->assertSee('Pivot Table');
        $this->actingAs($student)->get('/my/courses')->assertSee(route('learn.certificate', 'pivot-table'));
        $this->get('/learn/pivot-table/certificate')->assertOk()->assertSee($enrollment->certificate_code);
    }

    public function test_a_student_without_the_course_gets_the_certificate_but_not_the_course(): void
    {
        $course = $this->course();
        $student = User::factory()->create(['name' => 'Kim Visal']);

        $this->actingAs($this->admin())->post("/admin/students/{$student->id}/certificates", ['course_id' => $course->id, 'issued_on' => '2026-09-15'])
            ->assertSessionHasNoErrors();

        $enrollment = $student->enrollments()->firstOrFail();
        $this->assertSame(['certificate', '2026-09-15'], [$enrollment->source, $enrollment->completed_at->toDateString()]);
        $this->assertFalse($student->fresh()->canAccessCourse($course));
        $this->get("/certificates/{$enrollment->certificate_code}")->assertOk()->assertSee('15/09/2026');
        $this->get('/admin/students')->assertSee('title="Certificate only">Pivot Table<', false);

        $this->actingAs($student)->get('/learn/pivot-table/certificate')->assertOk()->assertSee('Kim Visal');
        $this->get('/learn/pivot-table/lessons/lesson-one')->assertRedirect('/courses/pivot-table');
    }

    public function test_one_certificate_per_course_no_future_dates_and_not_for_admins(): void
    {
        $course = $this->course();
        $student = User::factory()->create();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/admin/students/{$student->id}/certificates", ['course_id' => $course->id]);
        $code = $student->enrollments()->value('certificate_code');

        $this->post("/admin/students/{$student->id}/certificates", ['course_id' => $course->id])->assertSessionHasErrors('course_id');
        $this->assertSame($code, $student->enrollments()->value('certificate_code'));

        $other = $this->course('Basic Excel');
        $this->post("/admin/students/{$student->id}/certificates", ['course_id' => $other->id, 'issued_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('issued_on');
        $this->post("/admin/students/{$admin->id}/certificates", ['course_id' => $other->id])->assertNotFound();
    }

    public function test_taking_back_a_certificate(): void
    {
        $course = $this->course();
        $admin = $this->admin();

        // Given by hand to someone who had not finished: certificate and "completed" both go.
        $dara = User::factory()->create(['name' => 'Sok Dara']);
        Enrollment::grant($dara, $course, 90);
        $this->actingAs($admin)->post("/admin/students/{$dara->id}/certificates", ['course_id' => $course->id]);
        $enrollment = $dara->enrollments()->first();
        $code = $enrollment->certificate_code;

        $this->delete("/admin/enrollments/{$enrollment->id}/certificate")->assertSessionHas('status', "Certificate $code taken back from Sok Dara.");
        $this->assertSame([null, null], [$enrollment->fresh()->certificate_code, $enrollment->fresh()->completed_at]);
        $this->get("/certificates/$code")->assertNotFound();
        $this->assertTrue($dara->canAccessCourse($course));

        // Really finished every lesson: stays completed, and is not handed a new certificate by itself.
        $srey = User::factory()->create();
        Enrollment::grant($srey, $course, 90);
        DB::table('lesson_progress')->insert(['user_id' => $srey->id, 'course_lesson_id' => $course->lessons()->value('id'), 'completed_at' => now()]);
        $finished = CourseProgress::for($srey, $course)->recordCompletion();
        $this->delete("/admin/enrollments/{$finished->id}/certificate");
        $this->assertNull($finished->fresh()->certificate_code);
        $this->assertNotNull($finished->fresh()->completed_at);
        CourseProgress::for($srey, $course)->recordCompletion();
        $this->assertNull($finished->fresh()->certificate_code);

        // Certificate only: the record goes with it.
        $visal = User::factory()->create();
        $this->post("/admin/students/{$visal->id}/certificates", ['course_id' => $course->id]);
        $this->delete('/admin/enrollments/'.$visal->enrollments()->value('id').'/certificate');
        $this->assertSame(0, $visal->enrollments()->count());
    }

    public function test_certificates_can_be_given_from_the_course_students_page(): void
    {
        $course = $this->course();
        $student = User::factory()->create(['name' => 'Chan Sreyneang']);
        Enrollment::grant($student, $course, 90);
        $page = "/admin/courses/{$course->id}/students";

        $this->actingAs($this->admin())->get($page)->assertSee('Give certificate');
        $this->from($page)->post("/admin/students/{$student->id}/certificates", ['course_id' => $course->id])->assertRedirect($page);
        $this->get($page)->assertSee($student->enrollments()->value('certificate_code'))->assertSee('Take back');
    }
}
