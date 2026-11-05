<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Admin > Classes: a group of students in one course, followed on one page. */
class ClassroomTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Course $course;

    private CourseLesson $lesson;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 10:00:00');
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();

        $this->course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $module = $this->course->modules()->create(['title' => 'Module 1', 'position' => 1]);
        $this->lesson = $this->course->lessons()->create(['course_module_id' => $module->id, 'title' => 'Lesson', 'slug' => 'lesson', 'position' => 1, 'is_published' => true]);
        $this->quiz = $this->course->quizzes()->create(['course_module_id' => $module->id, 'type' => 'quiz', 'title' => 'Module 1 quiz', 'slug' => 'module-1-quiz', 'position' => 2, 'pass_percent' => 60, 'is_published' => true]);
    }

    private function student(string $name, ?string $enrolledAt = '2026-10-01 09:00:00', array $enrollment = []): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => str($name)->slug('.').'@example.com']);
        if ($enrolledAt) {
            Enrollment::grant($user, $this->course, 90)->forceFill(['starts_at' => $enrolledAt] + $enrollment)->save();
        }

        return $user;
    }

    private function finished(User $user, string $when): void
    {
        DB::table('lesson_progress')->insert(['user_id' => $user->id, 'course_lesson_id' => $this->lesson->id, 'completed_at' => $when]);
    }

    private function took(User $user, int $percent, bool $passed, string $when): void
    {
        $this->quiz->attempts()->create(['user_id' => $user->id, 'started_at' => $when, 'submitted_at' => $when,
            'score' => $percent, 'max_score' => 100, 'percent' => $percent, 'passed' => $passed]);
    }

    private function classroom(array $extra = []): Classroom
    {
        $classroom = Classroom::create($extra + ['name' => 'Saturday morning']);
        $classroom->syncCourses([$this->course->id]);

        return $classroom;
    }

    public function test_an_admin_makes_a_class_and_lands_on_it(): void
    {
        $this->actingAs($this->admin)->get(route('admin.classes.index'))->assertOk()->assertSee('No classes yet');
        $this->get(route('admin.classes.create', ['course' => $this->course->id]))->assertOk()
            ->assertSee('name="courses[]" value="'.$this->course->id.'" checked', false);

        $this->from(route('admin.classes.create'))->post(route('admin.classes.store'), [
            'name' => 'Pivot Table, Saturday morning', 'courses' => [$this->course->id], 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-01',
        ])->assertSessionHasErrors(['ends_on' => 'The last day cannot be before the first day.']);

        $this->post(route('admin.classes.store'), [
            'name' => 'Pivot Table, Saturday morning', 'courses' => [$this->course->id], 'teacher' => 'Mang Seyha',
            'schedule' => 'Sat 8:00–11:00, room 2', 'starts_on' => '2026-11-07', 'ends_on' => '2026-12-19',
        ])->assertRedirect(route('admin.classes.show', $classroom = Classroom::sole()));

        $this->get(route('admin.classes.show', $classroom))->assertOk()
            ->assertSee('Mang Seyha')->assertSee('7 Nov – 19 Dec 2026')->assertSee('Starts soon')->assertSee('Add students');
        $this->get(route('admin.classes.index'))->assertSee('Pivot Table, Saturday morning')->assertSee('Starts soon');
        $this->assertSame(['upcoming', 'running', 'finished'], [
            $classroom->status(),
            $classroom->forceFill(['starts_on' => '2026-11-01'])->status(),
            $classroom->forceFill(['ends_on' => '2026-11-04'])->status(),
        ]);
    }

    public function test_adding_students_only_adds_students_once(): void
    {
        $classroom = $this->classroom();
        $dara = $this->student('Sok Dara');
        $lina = $this->student('Keo Lina', null); // no course yet
        $page = route('admin.classes.show', $classroom);

        $this->actingAs($this->admin)->get($page)->assertSee('Has the course')->assertSee('No access yet');
        $this->from($page)->post(route('admin.classes.students.store', $classroom), ['students' => []])->assertSessionHasErrors(['students' => 'Tick at least one student.']);

        $this->post(route('admin.classes.students.store', $classroom), ['students' => [$dara->id, $lina->id, $this->admin->id]])
            ->assertRedirect($page)
            ->assertSessionHas('status', '2 students added. 1 cannot open the course yet: give them access on the course’s Students page.');
        $this->post(route('admin.classes.students.store', $classroom), ['students' => [$dara->id]]);

        $this->assertSame(['Keo Lina', 'Sok Dara'], $classroom->students()->pluck('name')->all());
        $this->get($page)->assertDontSee('value="'.$dara->id.'"', false); // no longer offered
    }

    public function test_the_class_page_shows_who_needs_help_first(): void
    {
        $classroom = $this->classroom();
        $done = $this->student('Chan Done', '2026-10-01 09:00:00', ['completed_at' => '2026-10-20 09:00:00']);
        $failed = $this->student('Kim Failed');
        $this->took($failed, 40, false, '2026-11-04 09:00:00');
        $active = $this->student('Mean Active');
        $this->finished($active, '2026-11-03 09:00:00');
        $quiet = $this->student('Lim Quiet');
        $this->finished($quiet, '2026-10-10 09:00:00');
        $never = $this->student('Sok Never');
        $newbie = $this->student('Heng New', '2026-11-03 09:00:00'); // joined 2 days ago: given time
        $outside = $this->student('Y Outside', null);
        $classroom->students()->attach([$done->id, $failed->id, $active->id, $quiet->id, $never->id, $newbie->id, $outside->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.classes.show', $classroom))->assertOk();
        $rows = $response->viewData('rows')->keyBy(fn ($row) => $row['student']->name);

        $this->assertSame('Did not pass: Module 1 quiz', $rows['Kim Failed']['reason']);
        $this->assertStringStartsWith('No activity for 26 days', $rows['Lim Quiet']['reason']);
        $this->assertSame('Has not started', $rows['Sok Never']['reason']);
        $this->assertSame(['Cannot open the course', 'No access'], [$rows['Y Outside']['reason'], $rows['Y Outside']['label']]);
        foreach (['Chan Done', 'Mean Active', 'Heng New'] as $fine) {
            $this->assertNull($rows[$fine]['reason'], $fine);
        }
        $this->assertSame('2026-11-03 09:00:00', $rows['Mean Active']['last_active']->format('Y-m-d H:i:s'));
        $this->assertSame(['completed'], $rows['Chan Done']['groups']);

        // Needs help first.
        $this->assertSame(['Kim Failed', 'Lim Quiet', 'Sok Never', 'Y Outside'], $response->viewData('rows')->take(4)->pluck('student.name')->sort()->values()->all());
        $this->assertTrue(in_array('help', $response->viewData('rows')->first()['groups'], true));
        $this->assertSame(['help' => 4, 'learning' => 3, 'completed' => 1, 'not_started' => 2, 'no_access' => 1], $response->viewData('counts')->all());
        $response->assertSee('Did not pass: Module 1 quiz')->assertSee('40%')->assertSee('Give access →');

        $help = $this->get(route('admin.classes.show', [$classroom, 'show' => 'help']));
        $this->assertSame(4, $help->viewData('shown')->count());

        $csv = $this->get(route('admin.classes.export', $classroom))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"Module 1 (best %)"', $csv);
        $this->assertStringContainsString('"Kim Failed",kim.failed@example.com', $csv);
    }

    public function test_leaving_or_deleting_a_class_does_not_touch_course_access(): void
    {
        $classroom = $this->classroom();
        $dara = $this->student('Sok Dara');
        $classroom->students()->attach($dara->id);

        $this->actingAs($this->admin)->get(route('admin.students.show', $dara))->assertSee('Saturday morning');

        $this->delete(route('admin.classes.students.destroy', [$classroom, $dara]))->assertSessionHas('status', 'Sok Dara left the class. Their course access is unchanged.');
        $this->assertSame(0, $classroom->students()->count());
        $this->assertTrue($dara->fresh()->canAccessCourse($this->course));

        $classroom->students()->attach($dara->id);
        $this->delete(route('admin.classes.destroy', $classroom))->assertRedirect(route('admin.classes.index'));
        $this->assertSame(0, Classroom::count());
        $this->assertSame(0, DB::table('classroom_student')->count());
        $this->assertTrue($dara->fresh()->canAccessCourse($this->course));
    }

    public function test_editing_and_filtering_classes(): void
    {
        $running = $this->classroom(['name' => 'Running class', 'starts_on' => '2026-10-01']);
        $this->classroom(['name' => 'Old class', 'starts_on' => '2026-01-01', 'ends_on' => '2026-03-01']);
        $other = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        Classroom::create(['name' => 'Basic class'])->syncCourses([$other->id]);

        $this->actingAs($this->admin)->get(route('admin.classes.index', ['show' => 'finished']))->assertSee('Old class')->assertDontSee('Running class');
        $this->get(route('admin.classes.index', ['course' => $other->id]))->assertSee('Basic class')->assertDontSee('Old class');

        $this->get(route('admin.classes.edit', $running))->assertOk()->assertSee('value="2026-10-01"', false);
        $this->put(route('admin.classes.update', $running), ['name' => 'Renamed', 'courses' => [$this->course->id]])->assertRedirect(route('admin.classes.show', $running));
        $this->assertSame(['Renamed', null], [$running->fresh()->name, $running->fresh()->starts_on]);
    }

    public function test_only_admins_can_open_classes(): void
    {
        $classroom = $this->classroom();
        $this->get(route('admin.classes.show', $classroom))->assertRedirect(route('admin.login'));
        $this->actingAs($this->student('Sok Dara'))->get(route('admin.classes.index'))->assertRedirect();
        $this->post(route('admin.classes.students.store', $classroom), ['students' => [1]])->assertRedirect();
        $this->assertSame(0, DB::table('classroom_student')->count());
    }
}
