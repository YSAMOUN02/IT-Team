<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Admin > Courses > a course > Results: the whole class on one page, with each test's best score. */
class CourseResultsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Course $course;

    private CourseLesson $lesson;

    private Quiz $practice;

    private Quiz $quiz;

    private Quiz $final;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();

        $this->course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $module = $this->course->modules()->create(['title' => 'Module 1', 'position' => 1]);
        $this->lesson = $this->course->lessons()->create(['course_module_id' => $module->id, 'title' => 'Lesson', 'slug' => 'lesson', 'position' => 1, 'is_published' => true]);
        $this->practice = $this->test('practice', 'Practice: basics', 2, $module->id);
        $this->quiz = $this->test('quiz', 'Module 1 quiz', 3, $module->id);
        $this->final = $this->test('final', 'Final exam', 4);
    }

    private function test(string $type, string $title, int $position, ?int $moduleId = null): Quiz
    {
        $quiz = $this->course->quizzes()->create(['course_module_id' => $moduleId, 'type' => $type, 'title' => $title, 'slug' => str($title)->slug(), 'position' => $position, 'pass_percent' => 60, 'is_published' => true]);
        $question = $quiz->questions()->create(['type' => 'single', 'prompt' => 'Q', 'points' => 1, 'position' => 1]);
        $question->options()->create(['text' => 'Right', 'is_correct' => true]);
        $question->options()->create(['text' => 'Wrong', 'is_correct' => false]);

        return $quiz;
    }

    private function student(string $name, array $enrollment = []): User
    {
        $user = User::factory()->create(['name' => $name, 'email' => str($name)->slug('.').'@example.com']);
        Enrollment::grant($user, $this->course, 90)->forceFill($enrollment)->save();

        return $user;
    }

    private function took(User $user, Quiz $quiz, int $percent, bool $passed): void
    {
        $quiz->attempts()->create(['user_id' => $user->id, 'started_at' => now()->subMinutes(20), 'submitted_at' => now()->subMinutes(5),
            'score' => $percent, 'max_score' => 100, 'percent' => $percent, 'passed' => $passed]);
    }

    private function finishedLesson(User $user): void
    {
        DB::table('lesson_progress')->insert(['user_id' => $user->id, 'course_lesson_id' => $this->lesson->id, 'completed_at' => now()]);
    }

    public function test_the_whole_class_on_one_page_with_each_tests_best_score(): void
    {
        $dara = $this->student('Sok Dara', ['completed_at' => now(), 'certificate_code' => 'TIC-AAAA-BBBB']);
        $this->finishedLesson($dara);
        $this->took($dara, $this->quiz, 100, true);
        $this->took($dara, $this->final, 40, false);
        $this->took($dara, $this->final, 85, true);
        $this->took($dara, $this->practice, 10, false);

        $visal = $this->student('Kim Visal');
        $this->finishedLesson($visal);
        $this->took($visal, $this->quiz, 50, false);

        $sokha = $this->student('Mean Sokha');
        $this->finishedLesson($sokha);
        $this->student('Chan Sreyneang');
        $this->student('Heng Rithy', ['starts_at' => now()->subDays(100), 'expires_at' => now()->subDays(10)]);

        $html = $this->actingAs($this->admin)->get("/admin/courses/{$this->course->id}/results")->assertOk()
            ->assertSee('>Module 1</a>', false)->assertSee('>Final</a>', false)->assertSee('2 tests · practice tests left out')
            ->assertDontSee('Practice: basics')
            ->getContent();

        // Best try counts (85%, not 40%), with the number of tries; a test not taken shows a dash.
        $this->assertMatchesRegularExpression('#Sok Dara.*?grade grade-pass[^>]*>100%<.*?grade grade-pass[^>]*>85%</a>\s*<small>2 tries</small>.*?Completed.*?TIC-AAAA-BBBB#s', $html);
        $this->assertMatchesRegularExpression('#Kim Visal.*?grade grade-fail[^>]*>50%<.*?<span class="muted">—</span>.*?Needs to retake</span>\s*<span class="row-sub"[^>]*>Module 1</span>#s', $html);
        $this->assertMatchesRegularExpression('#Mean Sokha.*?status-sched">Learning<#s', $html);
        $this->assertMatchesRegularExpression('#Chan Sreyneang.*?Not started<#s', $html);
        $this->assertMatchesRegularExpression('#Heng Rithy.*?Access ended<#s', $html);
        // Students 5 · completed 1 · need to retake 1 · passed the final 1 of 1
        $this->assertMatchesRegularExpression('#Students</div><div class="kpi-value">5<.*?Completed</div><div class="kpi-value">1<.*?Need to retake a test</div><div class="kpi-value">1<.*?Passed the final exam</div><div class="kpi-value">1<span[^>]*> / 1</span>#s', $html);
        $best = QuizAttempt::where('user_id', $dara->id)->where('quiz_id', $this->final->id)->orderByDesc('percent')->first();
        $this->assertStringContainsString(route('admin.results.show', $best), $html);
    }

    public function test_one_click_shows_who_is_learning_who_completed_and_who_must_retake(): void
    {
        $this->student('Sok Dara', ['completed_at' => now()]);
        $visal = $this->student('Kim Visal');
        $this->took($visal, $this->final, 30, false);
        $sokha = $this->student('Mean Sokha');
        $this->finishedLesson($sokha);

        $this->actingAs($this->admin);
        $page = "/admin/courses/{$this->course->id}/results";
        $this->get($page)->assertSee('Completed <span class="muted mono">1</span>', false)->assertSee('Needs to retake <span class="muted mono">1</span>', false);
        $this->get("$page?show=completed")->assertSee('Sok Dara')->assertDontSee('Kim Visal')->assertDontSee('Mean Sokha');
        $this->get("$page?show=retake")->assertSee('Kim Visal')->assertDontSee('Sok Dara');
        $this->get("$page?show=learning")->assertSee('Mean Sokha')->assertDontSee('Kim Visal');
        $this->get("$page?show=nonsense")->assertSee('Sok Dara')->assertSee('Kim Visal');
    }

    public function test_the_results_download_opens_in_excel_with_khmer_names(): void
    {
        $student = $this->student('ម៉ាង សីហា');
        $this->took($student, $this->quiz, 75, true);

        $response = $this->actingAs($this->admin)->get("/admin/courses/{$this->course->id}/results.csv")->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
        $this->assertSame(['Student', 'Email', 'Phone', 'Progress %', 'Lessons and tests done', 'Module 1 quiz (best %)', 'Final exam (best %)', 'Result', 'Needs to retake', 'Certificate', 'Access'], $lines[0]);
        $this->assertSame(['ម៉ាង សីហា', '75', '', 'Learning'], [$lines[1][0], $lines[1][5], $lines[1][6], $lines[1][7]]);
    }

    public function test_admins_who_tried_the_course_are_not_in_the_class(): void
    {
        Enrollment::grant($this->admin, $this->course, null, 'manual');
        $this->student('Sok Dara');

        $this->actingAs($this->admin)->get("/admin/courses/{$this->course->id}/results")
            ->assertSee('Sok Dara')->assertSee('Students</div><div class="kpi-value">1<', false);
        $this->get("/admin/courses/{$this->course->id}/students")->assertSee(route('admin.courses.results', $this->course));
    }
}
