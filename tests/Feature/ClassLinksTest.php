<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\RetakeRequest;
use App\Models\User;
use App\Support\CourseProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** A class with several courses, and how the rest of the admin links to classes. */
class ClassLinksTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Course $pivot;

    private Course $basic;

    private Quiz $quiz;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 10:00:00');
        Mail::fake();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();

        $this->pivot = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $this->basic = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $module = $this->pivot->modules()->create(['title' => 'Module 1', 'position' => 1]);
        $this->pivot->lessons()->create(['course_module_id' => $module->id, 'title' => 'Lesson', 'slug' => 'lesson', 'position' => 1, 'is_published' => true]);
        $this->basic->lessons()->create(['title' => 'Basics', 'slug' => 'basics', 'position' => 1, 'is_published' => true]);
        $this->quiz = $this->pivot->quizzes()->create(['course_module_id' => $module->id, 'type' => 'quiz', 'title' => 'Module 1 quiz', 'slug' => 'module-1-quiz', 'position' => 2, 'pass_percent' => 60, 'is_published' => true]);

        $this->classroom = Classroom::create(['name' => 'Excel weekend', 'teacher' => 'Mang Seyha']);
        $this->classroom->syncCourses([$this->basic->id, $this->pivot->id]); // the order the class takes them
    }

    private function student(string $name, array $courses = []): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => str($name)->slug('.').'@example.com']);
        foreach ($courses as $course) {
            Enrollment::grant($user, $course, 90)->forceFill(['starts_at' => '2026-11-01 09:00:00'])->save();
        }

        return $user;
    }

    public function test_a_class_can_take_several_courses_and_the_overview_shows_each(): void
    {
        $this->actingAs($this->admin)->post(route('admin.classes.store'), ['name' => 'Two courses', 'courses' => [$this->pivot->id, $this->basic->id]])->assertSessionHasNoErrors();
        $this->assertSame(['Pivot Table', 'Basic Excel'], Classroom::where('name', 'Two courses')->sole()->courses->pluck('title')->all());
        $this->post(route('admin.classes.store'), ['name' => 'None', 'courses' => []])->assertSessionHasErrors(['courses' => 'Tick at least one course.']);

        $both = $this->student('Sok Both', [$this->pivot, $this->basic]);
        DB::table('lesson_progress')->insert(['user_id' => $both->id, 'course_lesson_id' => $this->basic->lessons()->value('id'), 'completed_at' => '2026-11-04 09:00:00']);
        CourseProgress::for($both, $this->basic)->recordCompletion(); // as the lesson page does
        $one = $this->student('Keo One', [$this->basic]);
        $this->classroom->students()->attach([$both->id, $one->id]);

        $overview = $this->get(route('admin.classes.show', $this->classroom))->assertOk()
            ->assertSee('Overview')->assertSee('Basic Excel · Pivot Table')
            ->assertSee(route('admin.classes.show', [$this->classroom, 'course' => $this->pivot->id]), false);
        $rows = $overview->viewData('rows')->keyBy(fn ($row) => $row['student']->name);
        $this->assertNull($overview->viewData('course'));
        $this->assertSame([100, 'Not started'], [$rows['Sok Both']['cells'][$this->basic->id]['progress']->percent, $rows['Sok Both']['cells'][$this->pivot->id]['label']]);
        $this->assertSame(['Pivot Table: Cannot open the course'], $rows['Keo One']['reasons']);
        $this->assertSame([1, 4], [$overview->viewData('completions'), $overview->viewData('pairs')]);
        // "Give access →" opens the course's box with the student picked and the class chosen.
        $overview->assertSee(e(route('admin.courses.students', [$this->pivot, 'email' => 'keo.one@example.com', 'class' => $this->classroom->id]).'#give-access'), false);

        $tab = $this->get(route('admin.classes.show', [$this->classroom, 'course' => $this->pivot->id]))->assertOk()->assertSee('Module 1');
        $this->assertTrue($tab->viewData('course')->is($this->pivot));

        $csv = $this->get(route('admin.classes.export', $this->classroom))->streamedContent();
        $this->assertStringContainsString('"Basic Excel (progress %)","Basic Excel (status)","Pivot Table (progress %)"', $csv);
        $this->assertStringContainsString('"Pivot Table: Cannot open the course"', $csv);
    }

    public function test_a_turned_off_account_is_flagged_once(): void
    {
        $off = $this->student('Lim Off', [$this->pivot, $this->basic]);
        $off->forceFill(['disabled_at' => now()])->save();
        $this->classroom->students()->attach($off->id);

        $overview = $this->actingAs($this->admin)->get(route('admin.classes.show', $this->classroom))->assertOk()
            ->assertSee('<span class="status status-blocked">Account off</span>', false);
        $row = $overview->viewData('rows')->sole();
        $this->assertSame(['Account turned off'], $row['reasons']); // once, not once per course
        $this->assertSame(['help', 'not_started', 'no_access'], $row['groups']);

        $tab = $this->get(route('admin.classes.show', [$this->classroom, 'course' => $this->pivot->id]))->viewData('rows')->sole();
        $this->assertSame(['Account turned off', ['not_started', 'no_access', 'help']], [$tab['reason'], $tab['groups']]);

        // Finished the course before the account was turned off: nothing to do.
        $done = Classroom::create(['name' => 'Done']);
        $done->syncCourses([$this->basic->id]);
        $done->students()->attach($off->id);
        DB::table('lesson_progress')->insert(['user_id' => $off->id, 'course_lesson_id' => $this->basic->lessons()->value('id'), 'completed_at' => '2026-11-04 09:00:00']);
        CourseProgress::for($off, $this->basic)->recordCompletion();
        $finished = $this->get(route('admin.classes.show', $done))->viewData('rows')->sole();
        $this->assertSame([null, ['completed']], [$finished['reason'], $finished['groups']]);
    }

    public function test_course_pages_link_to_their_classes(): void
    {
        $dara = $this->student('Sok Dara', [$this->pivot]);
        $this->student('Keo Lina', [$this->pivot]); // same course, not in the class
        $this->classroom->students()->attach($dara->id);

        // The course's tab, and the class shown next to its students.
        $page = $this->actingAs($this->admin)->get(route('admin.courses.students', $this->pivot))->assertOk()
            ->assertSee(route('admin.classes.index', ['course' => $this->pivot->id]), false)
            ->assertSee('class="class-chip" href="'.route('admin.classes.show', $this->classroom).'"', false);
        $this->assertCount(2, $page->viewData('rows'));

        // Only one class.
        $only = $this->get(route('admin.courses.students', [$this->pivot, 'class' => $this->classroom->id]))->assertSee('Open the class');
        $this->assertSame([$dara->id], $only->viewData('rows')->pluck('enrollment.user_id')->all());

        // Results: only the class, and the download follows.
        $results = $this->get(route('admin.courses.results', [$this->pivot, 'class' => $this->classroom->id]))->assertOk();
        $this->assertSame(['Sok Dara'], $results->viewData('rows')->pluck('enrollment.user.name')->all());
        $csv = $this->get(route('admin.courses.results.export', [$this->pivot, 'class' => $this->classroom->id]))->streamedContent();
        $this->assertStringContainsString('Sok Dara', $csv);
        $this->assertStringNotContainsString('Keo Lina', $csv);
    }

    public function test_give_access_can_put_the_student_in_a_class(): void
    {
        $dara = $this->student('Sok Dara');
        $this->classroom->students()->attach($dara->id);
        $page = route('admin.courses.students', $this->pivot);

        // Coming from the class page: the student and class are filled in, and it goes back there.
        $this->actingAs($this->admin)->get(route('admin.courses.students', [$this->pivot, 'email' => $dara->email, 'class' => $this->classroom->id]))
            ->assertSee('name="email" value="'.$dara->email.'"', false)
            ->assertSee('<option value="'.$this->classroom->id.'" selected>', false)
            ->assertSee('name="back_to_class"', false);
        $this->from($page)->post(route('admin.courses.students.grant', $this->pivot), ['email' => $dara->email, 'classroom_id' => $this->classroom->id, 'back_to_class' => '1'])
            ->assertRedirect(route('admin.classes.show', $this->classroom))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Sok Dara can now open Pivot Table. In class “Excel weekend”.'));
        $this->assertTrue($dara->fresh()->canAccessCourse($this->pivot));

        // A new student straight into the class.
        $this->from($page)->post(route('admin.courses.students.grant', $this->pivot), ['email' => 'new@example.com', 'name' => 'Chan New', 'classroom_id' => $this->classroom->id])
            ->assertRedirect($page);
        $this->assertTrue($this->classroom->students()->where('email', 'new@example.com')->exists());

        // A class of another course is ignored.
        $other = Classroom::create(['name' => 'Other']);
        $other->syncCourses([$this->basic->id]);
        $lina = $this->student('Keo Lina');
        $this->post(route('admin.courses.students.grant', $this->pivot), ['email' => $lina->email, 'classroom_id' => $other->id]);
        $this->assertSame(0, $other->students()->count());
    }

    public function test_students_pages_show_and_filter_by_class(): void
    {
        $dara = $this->student('Sok Dara', [$this->pivot]);
        $lina = $this->student('Keo Lina', [$this->pivot]);
        $this->classroom->students()->attach($dara->id);

        $this->actingAs($this->admin)->get(route('admin.students.index'))->assertSee('Excel weekend')->assertSee('Class: Excel weekend');
        $filtered = $this->get(route('admin.students.index', ['class' => $this->classroom->id]));
        $this->assertSame([$dara->id], $filtered->viewData('students')->pluck('id')->all());

        // The student page: the class, and "Add to class" for the others.
        $later = Classroom::create(['name' => 'Evening class']);
        $later->syncCourses([$this->pivot->id]);
        $this->get(route('admin.students.show', $lina))->assertSee('Not in a class yet.')->assertSee('Evening class');
        $this->from(route('admin.students.show', $lina))->post(route('admin.students.classes.store', $lina), ['classroom_id' => $later->id])
            ->assertRedirect(route('admin.students.show', $lina))
            ->assertSessionHas('status', 'Added to the class “Evening class”.');
        $this->assertTrue($later->students()->whereKey($lina->id)->exists());
        $this->post(route('admin.students.classes.store', $this->admin), ['classroom_id' => $later->id])->assertNotFound();

        // Add student, straight into a class.
        $this->get(route('admin.students.create', ['class' => $this->classroom->id]))->assertSee('<option value="'.$this->classroom->id.'" selected>', false);
        $this->post(route('admin.students.store'), ['name' => 'Heng New', 'email' => 'heng@example.com', 'classroom_id' => $this->classroom->id])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'In class “Excel weekend”.'));
        $this->assertTrue($this->classroom->students()->where('email', 'heng@example.com')->exists());
    }

    public function test_exam_results_re_exams_and_search_link_to_classes(): void
    {
        $dara = $this->student('Sok Dara', [$this->pivot]);
        $lina = $this->student('Keo Lina', [$this->pivot]);
        $this->classroom->students()->attach($dara->id);
        foreach ([$dara, $lina] as $who) {
            $this->quiz->attempts()->create(['user_id' => $who->id, 'started_at' => now(), 'submitted_at' => now(), 'score' => 40, 'max_score' => 100, 'percent' => 40, 'passed' => false]);
        }
        RetakeRequest::create(['user_id' => $dara->id, 'quiz_id' => $this->quiz->id, 'status' => 'pending', 'message' => 'Please']);

        $all = $this->actingAs($this->admin)->get(route('admin.results.index'))->assertOk()->assertSee('Class: Excel weekend');
        $this->assertSame(2, $all->viewData('attempts')->total());
        $class = $this->get(route('admin.results.index', ['class' => $this->classroom->id]));
        $this->assertSame([$dara->id], $class->viewData('attempts')->pluck('user_id')->all());

        $this->get(route('admin.retake-requests.index'))->assertOk()->assertSee('class="class-chip"', false)->assertSee('Excel weekend');

        $this->get(route('admin.search', ['q' => 'weekend']))->assertSee('Excel weekend')->assertSee(route('admin.classes.show', $this->classroom), false);
        $this->get(route('admin.search', ['q' => 'Lina']))->assertSee('Keo Lina')->assertSee(route('admin.students.show', $lina), false);
        $this->get(route('admin.search', ['q' => 'Seyha']))->assertSee('Excel weekend'); // by teacher
    }
}
