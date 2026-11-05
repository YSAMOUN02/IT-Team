<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ResultController;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\CourseProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MidtermExamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mid_term_must_be_passed_to_complete_the_course(): void
    {
        $course = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $lesson = $course->lessons()->create(['title' => 'Lesson', 'slug' => 'lesson', 'position' => 1, 'is_published' => true]);
        $midterm = $course->quizzes()->create(['type' => 'midterm', 'title' => 'Mid-term', 'slug' => 'mid-term', 'position' => 2, 'pass_percent' => 50, 'is_published' => true]);
        $question = $midterm->questions()->create(['type' => 'single', 'prompt' => 'Pick one', 'points' => 1, 'position' => 1]);
        $right = $question->options()->create(['text' => 'Right', 'is_correct' => true]);
        $wrong = $question->options()->create(['text' => 'Wrong', 'is_correct' => false]);
        $student = User::factory()->create();
        Enrollment::grant($student, $course, null, 'manual');
        DB::table('lesson_progress')->insert(['user_id' => $student->id, 'course_lesson_id' => $lesson->id, 'completed_at' => now()]);

        $this->assertFalse(CourseProgress::for($student, $course)->complete); // every lesson done, mid-term not passed

        $this->actingAs($student)->get(route('learn.quiz', [$course->slug, $midterm->slug]))->assertOk()
            ->assertSee('tag-midterm', false)->assertSee(__('account.quiz_type_midterm'))->assertSee(__('account.note_midterm'));

        $take = function ($option) use ($course, $midterm, $question) {
            $this->post("/learn/{$course->slug}/quizzes/{$midterm->slug}/start")->assertRedirect();
            $attempt = $midterm->attempts()->latest('id')->firstOrFail();
            $this->post("/learn/{$course->slug}/quizzes/{$midterm->slug}/attempts/{$attempt->id}", ['answers' => [$question->id => [$option->id]]])->assertRedirect();
        };

        // Failed: the teacher sees a retake, labelled "Mid-term" in the gradebook.
        $take($wrong);
        $book = ResultController::gradebook($course);
        $this->assertSame(['Mid-term'], array_values($book['labels']));
        $this->assertSame('retake', $book['rows']->sole()['status']);
        $this->assertFalse(CourseProgress::for($student, $course)->complete);

        // Passed: the course is complete.
        $take($right);
        $this->assertTrue(CourseProgress::for($student, $course)->complete);
        $this->assertNotNull(Enrollment::where('user_id', $student->id)->value('completed_at'));
    }

    public function test_admins_add_a_mid_term_with_exam_settings(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $course = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $module = $course->modules()->create(['title' => 'Module 1', 'position' => 1]);

        $this->actingAs($admin)->get(route('admin.courses.curriculum', $course))->assertOk()
            ->assertSee('Mid-term exam')->assertSee(e(route('admin.courses.quizzes.create', [$course, 'type' => 'midterm', 'module' => $module->id])), false);

        $form = $this->get(route('admin.courses.quizzes.create', [$course, 'type' => 'midterm']))->assertOk()->assertSee('New mid-term exam');
        $quiz = $form->viewData('quiz');
        $this->assertSame([45, 3, 70, true], [$quiz->time_limit, $quiz->max_attempts, $quiz->pass_percent, $quiz->shuffle_questions]);
    }
}
