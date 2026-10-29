<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\RetakeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A student out of tries asks for a re-exam; the teacher (an admin) approves with more tries or declines. */
class RetakeRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    private Course $course;

    private Quiz $final;

    private string $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Teacher Seyha']);
        $this->admin->forceFill(['role' => 'admin'])->save();
        $this->student = User::factory()->create(['name' => 'Kim Visal', 'email' => 'visal@example.com']);

        $this->course = Course::create(['title' => 'Pivot Table', 'slug' => 'pivot-table', 'is_published' => true]);
        $this->final = $this->course->quizzes()->create(['type' => 'final', 'title' => 'Final exam', 'slug' => 'final-exam', 'pass_percent' => 75, 'max_attempts' => 2, 'is_published' => true]);
        $question = $this->final->questions()->create(['type' => 'single', 'prompt' => 'Q', 'points' => 1, 'position' => 1]);
        $question->options()->create(['text' => 'Right', 'is_correct' => true]);
        Enrollment::grant($this->student, $this->course, 90);
        $this->page = '/learn/pivot-table/quizzes/final-exam';
    }

    private function failedTry(?User $user = null): void
    {
        $this->final->attempts()->create(['user_id' => ($user ?? $this->student)->id, 'started_at' => now()->subMinutes(30), 'submitted_at' => now()->subMinutes(10),
            'score' => 0, 'max_score' => 1, 'percent' => 40, 'passed' => false]);
    }

    public function test_a_student_out_of_tries_asks_for_another_and_waits(): void
    {
        $this->failedTry();
        $this->failedTry();

        $this->actingAs($this->student)->get($this->page)->assertOk()
            ->assertSee(__('account.no_attempts_left'))->assertSee(__('account.retake_ask'))->assertSee('name="message"', false);
        $this->get("{$this->page}/attempts/".$this->final->attempts()->latest('id')->value('id').'/result')->assertSee($this->page.'#retake', false);

        $this->post("{$this->page}/retake-request", ['message' => 'I was sick on the day.'])
            ->assertRedirect($this->page)->assertSessionHas('status', __('account.retake_sent'));

        $request = RetakeRequest::sole();
        $this->assertSame(['pending', 'I was sick on the day.'], [$request->status, $request->message]);
        $this->get($this->page)->assertSee(__('account.retake_pending', ['date' => now()->format('d/m/Y')]))->assertDontSee('name="message"', false);

        // Asking twice, or starting anyway, does nothing.
        $this->post("{$this->page}/retake-request")->assertSessionHas('status', __('account.retake_not_allowed'));
        $this->post("{$this->page}/start")->assertSessionHas('status', __('account.no_attempts_left'));
        $this->assertSame(1, RetakeRequest::count());
    }

    public function test_only_students_who_are_out_of_tries_and_did_not_pass_can_ask(): void
    {
        $this->failedTry();
        $this->actingAs($this->student)->get($this->page)->assertDontSee(__('account.retake_ask'));
        $this->post("{$this->page}/retake-request")->assertSessionHas('status', __('account.retake_not_allowed'));

        $this->final->attempts()->create(['user_id' => $this->student->id, 'started_at' => now()->subMinutes(9), 'submitted_at' => now(), 'score' => 1, 'max_score' => 1, 'percent' => 100, 'passed' => true]);
        $this->post("{$this->page}/retake-request")->assertSessionHas('status', __('account.retake_not_allowed'));
        $this->assertSame(0, RetakeRequest::count());
    }

    public function test_the_teacher_approves_with_more_tries_and_the_student_can_take_it_again(): void
    {
        $this->failedTry();
        $this->failedTry();
        $request = $this->final->retakeRequests()->create(['user_id' => $this->student->id, 'message' => 'Please, one more chance.']);

        $this->actingAs($this->admin)->get('/admin/retake-requests')->assertOk()
            ->assertSee('Kim Visal')->assertSee('Final exam')->assertSee('2 tries used')->assertSee('best')->assertSee('40%')
            ->assertSee('Please, one more chance.')->assertSee('Approve');
        $this->get('/admin')->assertSee(route('admin.retake-requests.index'));

        $this->from('/admin/retake-requests')->patch("/admin/retake-requests/{$request->id}", ['decision' => 'approve', 'extra_attempts' => 2, 'reply' => 'Read module 3 again first.'])
            ->assertRedirect('/admin/retake-requests')->assertSessionHas('status', 'Approved: Kim Visal can take Final exam 2 more times.');
        $request->refresh();
        $this->assertSame(['approved', 2, $this->admin->id], [$request->status, $request->extra_attempts, $request->decided_by]);
        $this->assertSame(2, $this->final->attemptsLeft($this->student));

        $this->actingAs($this->student)->get($this->page)
            ->assertSee(trans_choice('account.retake_approved', 2, ['count' => 2]))->assertSee('Read module 3 again first.')
            ->assertSee(route('learn.quiz.start', ['pivot-table', 'final-exam']), false);
        $this->post("{$this->page}/start")->assertRedirect();
        $this->assertSame(1, $this->final->attempts()->whereNull('submitted_at')->count());

        // Answered once only.
        $this->actingAs($this->admin)->patch("/admin/retake-requests/{$request->id}", ['decision' => 'decline'])->assertSessionHasErrors('decision');
    }

    public function test_the_teacher_declines_with_a_note_and_the_student_may_ask_again_later(): void
    {
        $this->failedTry();
        $this->failedTry();
        $request = $this->final->retakeRequests()->create(['user_id' => $this->student->id]);

        $this->actingAs($this->admin)->patch("/admin/retake-requests/{$request->id}", ['decision' => 'decline', 'reply' => 'Finish the lessons first.'])
            ->assertSessionHas('status', 'Declined: Kim Visal was told no for Final exam.');
        $this->assertSame(0, $this->final->attemptsLeft($this->student));
        $this->get('/admin/retake-requests?status=declined')->assertSee('Declined')->assertSee('Finish the lessons first.')->assertSee('Teacher Seyha');

        $this->actingAs($this->student)->get($this->page)
            ->assertSee(__('account.retake_declined'))->assertSee('Finish the lessons first.')->assertSee(__('account.retake_ask'));
    }

    public function test_the_teacher_can_give_more_tries_from_the_attempt_page_without_a_request(): void
    {
        $this->failedTry();
        $this->failedTry();
        $attempt = $this->final->attempts()->latest('id')->first();

        $this->actingAs($this->admin)->get("/admin/results/{$attempt->id}")->assertSee('No tries left and not passed.')->assertSee('Give more tries');
        $this->from("/admin/results/{$attempt->id}")->post("/admin/results/{$attempt->id}/extra-try", ['extra_attempts' => 1])
            ->assertSessionHas('status', 'Kim Visal can take Final exam 1 more time.');

        $this->assertSame(1, $this->final->attemptsLeft($this->student));
        $this->get("/admin/results/{$attempt->id}")->assertSee('2 of 3 allowed (2 + 1 given by a teacher)')->assertDontSee('Give more tries');

        // A waiting request is answered by it rather than left in the list.
        $this->failedTry();
        $this->final->retakeRequests()->create(['user_id' => $this->student->id, 'message' => 'Again please']);
        $this->get("/admin/results/{$attempt->id}")->assertSee('Asked for a re-exam')->assertSee('Again please');
        $this->post("/admin/results/{$attempt->id}/extra-try", ['extra_attempts' => 1]);
        $this->assertSame(0, RetakeRequest::pending()->count());
        $this->assertSame(1, $this->final->attemptsLeft($this->student));
    }

    public function test_course_results_show_who_asked_and_students_cannot_answer_requests(): void
    {
        $this->failedTry();
        $this->failedTry();
        $request = $this->final->retakeRequests()->create(['user_id' => $this->student->id]);

        $this->actingAs($this->admin)->get("/admin/courses/{$this->course->id}/results")->assertSee('Asked for a re-exam');

        $this->actingAs($this->student)->get('/admin/retake-requests')->assertRedirect('/my/courses');
        $this->patch("/admin/retake-requests/{$request->id}", ['decision' => 'approve'])->assertRedirect('/my/courses');
        $this->assertTrue($request->fresh()->isPending());
    }
}
