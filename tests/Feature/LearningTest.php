<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_locked_lessons_need_access_and_previews_are_open(): void
    {
        [$course, $locked, $preview] = $this->courseWithLessons();
        $student = User::factory()->create();

        $this->get("/learn/{$course->slug}/lessons/{$preview->slug}")->assertOk();
        $this->get("/learn/{$course->slug}/lessons/{$locked->slug}")->assertRedirect("/courses/{$course->slug}");
        $this->actingAs($student)->get("/learn/{$course->slug}/lessons/{$locked->slug}")->assertRedirect("/courses/{$course->slug}");

        Enrollment::grant($student, $course, 30, 'manual');
        $this->actingAs($student)->get("/learn/{$course->slug}/lessons/{$locked->slug}")->assertOk();
    }

    public function test_expired_access_locks_the_course_again(): void
    {
        [$course, $locked] = $this->courseWithLessons();
        $student = User::factory()->create();
        Enrollment::create(['user_id' => $student->id, 'course_id' => $course->id, 'starts_at' => now()->subDays(40), 'expires_at' => now()->subDays(10)]);

        $this->actingAs($student)->get("/learn/{$course->slug}/lessons/{$locked->slug}")->assertRedirect("/courses/{$course->slug}");
    }

    public function test_renewing_adds_time_after_the_current_end_date(): void
    {
        [$course] = $this->courseWithLessons();
        $student = User::factory()->create();

        Enrollment::grant($student, $course, 30);
        $first = Enrollment::first()->expires_at;
        Enrollment::grant($student, $course, 30);

        $this->assertEqualsWithDelta($first->copy()->addDays(30)->timestamp, Enrollment::first()->expires_at->timestamp, 5);
    }

    public function test_quiz_is_graded_on_the_server_all_or_nothing(): void
    {
        [$course] = $this->courseWithLessons();
        $student = User::factory()->create();
        Enrollment::grant($student, $course, null, 'manual');

        $quiz = $course->quizzes()->create(['type' => 'quiz', 'title' => 'Module quiz', 'slug' => 'module-quiz', 'pass_percent' => 50]);
        $q1 = $quiz->questions()->create(['type' => 'multiple', 'prompt' => 'Pick both', 'points' => 1, 'position' => 1]);
        $a = $q1->options()->create(['text' => 'A', 'is_correct' => true]);
        $b = $q1->options()->create(['text' => 'B', 'is_correct' => true]);
        $q1->options()->create(['text' => 'C', 'is_correct' => false]);
        $q2 = $quiz->questions()->create(['type' => 'single', 'prompt' => 'Pick one', 'points' => 1, 'position' => 2]);
        $right = $q2->options()->create(['text' => 'Right', 'is_correct' => true]);
        $q2->options()->create(['text' => 'Wrong', 'is_correct' => false]);

        $this->actingAs($student)->post("/learn/{$course->slug}/quizzes/{$quiz->slug}/start")->assertRedirect();
        $attempt = $quiz->attempts()->firstOrFail();

        // Only one of the two right answers on Q1 = wrong; Q2 right -> 50% -> passes at 50.
        $this->actingAs($student)->post("/learn/{$course->slug}/quizzes/{$quiz->slug}/attempts/{$attempt->id}", [
            'answers' => [$q1->id => [$a->id], $q2->id => [$right->id]],
        ])->assertRedirect();

        $attempt->refresh();
        $this->assertSame(1, $attempt->score);
        $this->assertSame(2, $attempt->max_score);
        $this->assertTrue($attempt->passed);
    }

    public function test_answers_after_the_time_limit_do_not_count(): void
    {
        [$course] = $this->courseWithLessons();
        $student = User::factory()->create();
        Enrollment::grant($student, $course, null, 'manual');
        $quiz = $course->quizzes()->create(['type' => 'final', 'title' => 'Final', 'slug' => 'final', 'time_limit' => 10]);
        $q = $quiz->questions()->create(['type' => 'single', 'prompt' => 'Q', 'points' => 1]);
        $right = $q->options()->create(['text' => 'Right', 'is_correct' => true]);
        $q->options()->create(['text' => 'Wrong', 'is_correct' => false]);

        $this->actingAs($student)->post("/learn/{$course->slug}/quizzes/{$quiz->slug}/start");
        $attempt = $quiz->attempts()->firstOrFail();
        $attempt->update(['started_at' => now()->subMinutes(15)]);

        $this->actingAs($student)->post("/learn/{$course->slug}/quizzes/{$quiz->slug}/attempts/{$attempt->id}", ['answers' => [$q->id => [$right->id]]]);

        $this->assertSame(0, $attempt->fresh()->score);
    }

    public function test_students_cannot_open_the_admin(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertRedirect('/my/courses');
    }

    private function courseWithLessons(): array
    {
        $course = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $locked = $course->lessons()->create(['title' => 'Locked', 'slug' => 'locked', 'position' => 2]);
        $preview = $course->lessons()->create(['title' => 'Preview', 'slug' => 'preview', 'position' => 1, 'is_preview' => true]);

        return [$course, $locked, $preview];
    }
}
