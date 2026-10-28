<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** My courses, course home, taking a test and its result page. */
class StudentAreaTest extends TestCase
{
    use RefreshDatabase;

    private function setUpCourse(): array
    {
        $course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $module = $course->modules()->create(['title' => 'Basics', 'position' => 1]);
        $first = $course->lessons()->create(['course_module_id' => $module->id, 'title' => 'What is a PivotTable', 'slug' => 'what', 'position' => 1, 'is_published' => true]);
        $second = $course->lessons()->create(['course_module_id' => $module->id, 'title' => 'Create one', 'slug' => 'create', 'position' => 2, 'is_published' => true]);
        $quiz = $course->quizzes()->create(['course_module_id' => $module->id, 'type' => 'quiz', 'title' => 'Basics quiz', 'slug' => 'basics-quiz', 'position' => 3, 'pass_percent' => 50, 'show_answers' => true, 'is_published' => true]);
        foreach (['Values' => true, 'Rows' => false] as $text => $correct) {
            $question ??= $quiz->questions()->create(['type' => 'single', 'prompt' => 'Numbers go in…', 'points' => 1, 'position' => 1]);
            $question->options()->create(['text' => $text, 'is_correct' => $correct]);
        }
        $student = User::factory()->create(['name' => 'Sok Dara']);
        Enrollment::grant($student, $course, 90);

        return [$course, $first, $second, $quiz, $student];
    }

    public function test_my_courses_greets_and_points_to_the_next_lesson(): void
    {
        [, $first, , , $student] = $this->setUpCourse();
        $this->actingAs($student)->post('/learn/pivot-table/lessons/what/complete');

        $this->actingAs($student)->get('/my/courses')->assertOk()
            ->assertSee('Sok')
            ->assertSee('class="learn-stats"', false)
            ->assertSee('class="card continue-card"', false)
            ->assertSee('Create one') // next up
            ->assertSee('/learn/pivot-table/lessons/create', false)
            ->assertSee('33%');
    }

    public function test_finishing_a_lesson_animates_progress_and_says_so(): void
    {
        [, , , , $student] = $this->setUpCourse();

        $this->actingAs($student)
            ->post('/learn/pivot-table/lessons/what/complete', ['next' => '/learn/pivot-table/lessons/create'])
            ->assertRedirect('/learn/pivot-table/lessons/create')
            ->assertSessionHas('progress_was', 0)
            ->assertSessionHas('lesson_done', 'What is a PivotTable');

        $this->actingAs($student)->followingRedirects()
            ->post('/learn/pivot-table/lessons/what/complete', ['next' => '/learn/pivot-table/lessons/create'])
            ->assertSee('class="learn-toast"', false)
            ->assertSee('--from: 33%', false);
    }

    public function test_course_home_shows_module_progress_and_next_up(): void
    {
        [, , , , $student] = $this->setUpCourse();
        $this->actingAs($student)->post('/learn/pivot-table/lessons/what/complete');

        $this->actingAs($student)->get('/learn/pivot-table')->assertOk()
            ->assertSee('class="next-up"', false)->assertSee('Create one')
            ->assertSee('class="cur-bar"', false)->assertSee('1/3');
    }

    public function test_taking_a_test_shows_question_chips_progress_and_the_submit_box(): void
    {
        [, , , $quiz, $student] = $this->setUpCourse();
        $this->actingAs($student)->post('/learn/pivot-table/quizzes/basics-quiz/start');
        $attempt = $quiz->attempts()->firstOrFail();

        $this->actingAs($student)->get("/learn/pivot-table/quizzes/basics-quiz/attempts/{$attempt->id}")->assertOk()
            ->assertSee('data-progress-bar', false)
            ->assertSee('data-nav="', false)
            ->assertSee('data-submit-dialog', false);
    }

    public function test_result_page_counts_right_wrong_and_offers_the_next_step(): void
    {
        [, , , $quiz, $student] = $this->setUpCourse();
        $this->actingAs($student)->post('/learn/pivot-table/quizzes/basics-quiz/start');
        $attempt = $quiz->attempts()->firstOrFail();
        $right = $quiz->questions()->first()->options()->where('is_correct', true)->value('id');

        $this->actingAs($student)->post("/learn/pivot-table/quizzes/basics-quiz/attempts/{$attempt->id}", ['answers' => [$quiz->questions()->value('id') => [$right]]])
            ->assertRedirect();

        $this->actingAs($student)->get("/learn/pivot-table/quizzes/basics-quiz/attempts/{$attempt->id}/result")->assertOk()
            ->assertSee('data-confetti', false)
            ->assertSee('class="result-stats"', false)
            ->assertSee('data-review-filter', false)
            ->assertSee('data-count="100"', false);
    }
}
