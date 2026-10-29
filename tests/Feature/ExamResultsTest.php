<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin > Exam results: every test students took, and each attempt answer by answer. */
class ExamResultsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Quiz $final;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Office Admin']);
        $this->admin->forceFill(['role' => 'admin'])->save();

        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $this->final = $this->quiz($course, 'final', 'Final exam', 45);
    }

    /** Two questions: "2 + 2" (B is right) and "Pick the fruits" (A and C are right). */
    private function quiz(Course $course, string $type, string $title, ?int $timeLimit = null): Quiz
    {
        $quiz = $course->quizzes()->create(['type' => $type, 'title' => $title, 'slug' => str($title)->slug(), 'pass_percent' => 60, 'time_limit' => $timeLimit, 'max_attempts' => 3, 'is_published' => true]);
        $q1 = $quiz->questions()->create(['type' => 'single', 'prompt' => 'What is 2 + 2?', 'points' => 1, 'position' => 1, 'explanation' => 'Two and two make four.']);
        foreach (['3' => false, '4' => true, '5' => false] as $text => $correct) {
            $q1->options()->create(['text' => (string) $text, 'is_correct' => $correct]);
        }
        $q2 = $quiz->questions()->create(['type' => 'multiple', 'prompt' => 'Pick the fruits', 'points' => 1, 'position' => 2]);
        foreach (['Apple' => true, 'Chair' => false, 'Mango' => true] as $text => $correct) {
            $q2->options()->create(['text' => $text, 'is_correct' => $correct]);
        }

        return $quiz->load('questions.options');
    }

    /** $right = which questions to answer correctly (1, 2), the rest wrong; null = not submitted. */
    private function attempt(User $user, Quiz $quiz, ?array $right, int $minutesAgo = 30): QuizAttempt
    {
        $attempt = $quiz->attempts()->create(['user_id' => $user->id, 'question_order' => $quiz->questions->pluck('id')->all(), 'started_at' => now()->subMinutes($minutesAgo)]);
        if ($right === null) {
            return $attempt;
        }
        [$q1, $q2] = $quiz->questions->values();
        $attempt->grade([
            $q1->id => [$q1->options->firstWhere('is_correct', in_array(1, $right))->id],
            $q2->id => in_array(2, $right) ? $q2->options->where('is_correct', true)->pluck('id')->all() : [$q2->options->firstWhere('text', 'Chair')->id],
        ]);
        $attempt->forceFill(['submitted_at' => $attempt->started_at->copy()->addMinutes(12)->addSeconds(5)])->save();

        return $attempt;
    }

    public function test_results_list_shows_each_students_score_result_and_time(): void
    {
        $dara = User::factory()->create(['name' => 'Sok Dara', 'email' => 'dara@example.com']);
        $visal = User::factory()->create(['name' => 'Kim Visal', 'email' => 'visal@example.com']);
        $this->attempt($dara, $this->final, [1], 120);     // 50%: not passed
        $this->attempt($dara, $this->final, [1, 2], 60);   // 100%: passed, second try
        $this->attempt($visal, $this->final, null, 5);     // still taking it
        $this->attempt($this->admin, $this->final, [1, 2]); // admins trying the test are not listed

        $html = $this->actingAs($this->admin)->get('/admin/results')->assertOk()
            ->assertSee('Sok Dara')->assertSee('Kim Visal')->assertDontSee('Office Admin</a>', false)
            ->assertSee('Final exam')->assertSee('2/2 · pass 60%')->assertSee('12:05')
            ->assertSee('Passed')->assertSee('Not passed')->assertSee('In progress')
            ->getContent();
        // Tests submitted 2 · average 75% · pass rate 50% · not submitted 1
        $this->assertMatchesRegularExpression('#Tests submitted</div><div class="kpi-value">2<.*Average score</div><div class="kpi-value">75%<.*Pass rate</div><div class="kpi-value">50%<.*Not submitted</div><div class="kpi-value">1<#s', $html);
        $this->assertSame(3, substr_count($html, 'href="'.url('/admin/results').'/'));
        // Newest first: Visal's open attempt, then Dara's second try, then her first.
        $this->assertMatchesRegularExpression('#Kim Visal.*?In progress.*?Sok Dara.*?100%.*?Passed.*?try 2 of 2.*?Sok Dara.*?50%.*?Not passed.*?try 1 of 2#s', $html);
        $this->assertStringNotContainsString('try 1 of 1', $html);
    }

    public function test_time_taken_is_not_made_up_for_attempts_whose_start_time_was_lost(): void
    {
        $attempt = $this->attempt(User::factory()->create(['name' => 'Sok Dara']), $this->final, [1, 2]);
        $attempt->forceFill(['started_at' => $attempt->submitted_at])->save(); // as MariaDB left them before the fix

        $this->actingAs($this->admin)->get('/admin/results')->assertDontSee('0:00');
        $this->get("/admin/results/{$attempt->id}")->assertSee('Time taken</div><div class="kpi-value">—</div>', false);
    }

    public function test_results_can_be_filtered(): void
    {
        $other = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $practice = $this->quiz($other, 'practice', 'Practice: formulas');
        $dara = User::factory()->create(['name' => 'Sok Dara']);
        $visal = User::factory()->create(['name' => 'Kim Visal']);
        $this->attempt($dara, $this->final, [1, 2]);
        $this->attempt($visal, $practice, [1]);
        $this->attempt($visal, $this->final, null, 200); // timer ran out long ago

        $this->actingAs($this->admin);
        $this->get('/admin/results?q=dara')->assertSee('Sok Dara')->assertDontSee('Kim Visal');
        $this->get("/admin/results?course={$other->id}")->assertSee('Practice: formulas')->assertDontSee('Sok Dara');
        $this->get('/admin/results?type=final&result=passed')->assertSee('Sok Dara')->assertDontSee('Kim Visal');
        $this->get('/admin/results?result=failed')->assertSee('Kim Visal')->assertSee('Practice: formulas')->assertDontSee('Sok Dara');
        $this->get('/admin/results?result=unfinished')->assertSee('Kim Visal')->assertSee('Time ran out')->assertDontSee('Sok Dara');
        $this->get("/admin/results?quiz={$practice->id}")->assertSee('Practice: formulas · Basic Excel')->assertSee('1 attempts');
    }

    public function test_one_attempt_shows_every_answer_next_to_the_right_one(): void
    {
        $dara = User::factory()->create(['name' => 'Sok Dara']);
        $first = $this->attempt($dara, $this->final, [2], 90);
        $second = $this->attempt($dara, $this->final, [1, 2], 30);

        $html = $this->actingAs($this->admin)->get("/admin/results/{$first->id}")->assertOk()
            ->assertSee('Sok Dara · Final exam · Pivot Table')
            ->assertSee('What is 2 + 2?')->assertSee('Pick the fruits')->assertSee('Two and two make four.')
            ->assertSee("Sok Dara's tries", false)->assertSee('2 of 3 allowed')->assertSee('Try 1 (this one)')
            ->assertSee(route('admin.results.show', $second))
            ->getContent();

        // Question 1: picked "3" (wrong), "4" is the right answer. Question 2: both fruits picked.
        $this->assertMatchesRegularExpression('#class="is-picked-wrong">\s*<span class="q-mark">A</span>\s*<span>3</span>\s*<small>Student\'s answer</small>#', $html);
        $this->assertMatchesRegularExpression('#class="is-correct">\s*<span class="q-mark">B</span>\s*<span>4</span>\s*<small>Right answer</small>#', $html);
        $this->assertSame(2, substr_count($html, "Student's answer · correct"));
        $this->assertMatchesRegularExpression('#Correct</div><div class="kpi-value">1<#', $html);
    }

    public function test_an_unsubmitted_attempt_says_so(): void
    {
        $attempt = $this->attempt(User::factory()->create(), $this->final, null, 10);

        $this->actingAs($this->admin)->get("/admin/results/{$attempt->id}")->assertOk()
            ->assertSee('Not submitted.')->assertSee('time limit 45 min')->assertSee('In progress')
            ->assertSee('The answers show here once the test is submitted.');
    }

    public function test_student_page_and_test_page_link_to_the_results(): void
    {
        $dara = User::factory()->create(['name' => 'Sok Dara']);
        $attempt = $this->attempt($dara, $this->final, [1, 2]);

        $this->actingAs($this->admin)->get("/admin/students/{$dara->id}")->assertOk()
            ->assertSee('Tests & exams', false)->assertSee(route('admin.results.show', $attempt))->assertSee('100%');
        $this->get("/admin/quizzes/{$this->final->id}/edit")->assertOk()->assertSee(route('admin.results.index', ['quiz' => $this->final->id]), false);
    }

    public function test_students_cannot_see_the_results_pages(): void
    {
        $dara = User::factory()->create();
        $attempt = $this->attempt($dara, $this->final, [1, 2]);

        $this->actingAs($dara)->get('/admin/results')->assertRedirect('/my/courses');
        $this->get("/admin/results/{$attempt->id}")->assertRedirect('/my/courses');
    }
}
