<?php

namespace Tests\Feature;

use App\Mail\StudentMessage;
use App\Models\ChatMessage;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\User;
use App\Support\MailSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** My account > Chat and Admin > Chat: one conversation per student, answered by chat or by email. */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['name' => 'Teacher Seyha']);
        $this->admin->forceFill(['role' => 'admin'])->save();
        $this->student = User::factory()->create(['name' => 'Sok Dara', 'email' => 'dara@example.com']);
    }

    public function test_a_student_writes_and_admins_see_it_waiting(): void
    {
        $this->actingAs($this->student)->get(route('account.chat'))->assertOk()
            ->assertSee(__('account.chat_title'))->assertSee(__('account.chat_empty'))->assertSee('js/chat.js', false);

        $this->post(route('account.chat.store'), ['body' => "Hello <b>teacher</b>\r\nline two"])->assertRedirect(route('account.chat'));
        $message = ChatMessage::sole();
        $this->assertSame([$this->student->id, $this->student->id, true, "Hello <b>teacher</b>\nline two"], [$message->student_id, $message->sender_id, $message->from_student, $message->body]);
        $this->assertNull($message->read_at);
        $this->assertSame(1, ChatMessage::studentsWaiting());

        $this->post(route('account.chat.store'), ['body' => '   '])->assertSessionHasErrors(['body' => __('account.chat_write_first')]);

        // The admin's sidebar and inbox say someone is waiting.
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee(route('admin.chats.index'), false);
        $inbox = $this->get(route('admin.chats.index'))->assertOk()->assertSee('Sok Dara')->assertSee('chat-count', false)
            ->assertSee(e('Hello <b>teacher</b>'), false)->assertDontSee('Hello <b>teacher</b>', false);
        $this->assertSame([$this->student->id], $inbox->viewData('conversations')->pluck('id')->all());
        $this->get(route('admin.chats.index', ['show' => 'unread']))->assertSee('Sok Dara');
        $this->get(route('admin.chats.index', ['q' => 'nobody']))->assertDontSee('dara@example.com');

        // Opening the conversation reads it: text escaped, nobody waiting any more.
        $this->get(route('admin.chats.show', $this->student))->assertOk()->assertSee(e('Hello <b>teacher</b>'), false)->assertDontSee('Hello <b>teacher</b>', false);
        $this->assertNotNull($message->fresh()->read_at);
        $this->assertSame(0, ChatMessage::studentsWaiting());
        $this->get(route('admin.chats.index', ['show' => 'unread']))->assertDontSee('dara@example.com');
    }

    public function test_an_admin_answers_and_the_student_sees_a_red_count(): void
    {
        $question = $this->student->chatMessages()->create(['sender_id' => $this->student->id, 'from_student' => true, 'body' => 'A question']);

        // Answering also means the question has been read.
        $this->actingAs($this->admin)->post(route('admin.chats.store', $this->student), ['body' => 'The answer'])->assertRedirect(route('admin.chats.show', $this->student));
        $answer = ChatMessage::latest('id')->first();
        $this->assertSame([$this->admin->id, false, 'The answer'], [$answer->sender_id, $answer->from_student, $answer->body]);
        $this->assertNull($answer->emailed_at);
        $this->assertNotNull($question->fresh()->read_at);
        $this->get(route('admin.chats.show', $this->student))->assertSee('You ·', false);

        // The account menu: a Chat link with a red count, also in the account tabs.
        $page = $this->actingAs($this->student)->get(route('account.courses'))->assertOk()
            ->assertSee(route('account.chat'), false)->assertSee(__('account.chat_title'))->assertSee('account-dot', false);
        $this->assertStringContainsString('<b class="account-count">1</b>', $page->getContent());

        // Students see the school, not which admin wrote; opening the chat clears the count.
        $this->get(route('account.chat'))->assertOk()->assertSee('The answer')->assertDontSee('Teacher Seyha')->assertSee(setting('site_name', config('app.name')));
        $this->assertNotNull($answer->fresh()->read_at);
        $this->get(route('account.courses'))->assertDontSee('account-count', false)->assertDontSee('account-dot', false);

        // An admin's own menu on the website goes to the inbox.
        $this->actingAs($this->admin)->get(route('home'))->assertOk()->assertSee(route('admin.chats.index'), false);
        $this->get(route('account.chat'))->assertRedirect(route('admin.chats.index'));
    }

    public function test_messages_are_sent_and_fetched_without_reloading(): void
    {
        $sent = $this->actingAs($this->student)->postJson(route('account.chat.store'), ['body' => 'Hi', 'after' => 0])->assertOk();
        $first = ChatMessage::sole();
        $sent->assertJsonPath('last_id', $first->id);
        $this->assertStringContainsString('data-id="'.$first->id.'"', $sent->json('html'));
        $this->assertStringContainsString('chat-msg-mine', $sent->json('html'));

        // The admin's open page gets it and it counts as read; asking again brings nothing.
        $got = $this->actingAs($this->admin)->getJson(route('admin.chats.poll', [$this->student, 'after' => 0]))->assertOk();
        $this->assertStringContainsString('Hi', $got->json('html'));
        $this->assertStringNotContainsString('chat-msg-mine', $got->json('html'));
        $this->assertNotNull($first->fresh()->read_at);
        $this->getJson(route('admin.chats.poll', [$this->student, 'after' => $first->id]))->assertExactJson(['html' => '', 'last_id' => $first->id]);

        $answer = $this->postJson(route('admin.chats.store', $this->student), ['body' => 'Hello', 'after' => $first->id])->assertOk();
        $second = ChatMessage::latest('id')->first();
        $answer->assertJsonPath('last_id', $second->id);
        $this->assertStringContainsString('chat-msg-mine', $answer->json('html'));
        $this->assertStringNotContainsString('data-id="'.$first->id.'"', $answer->json('html')); // only what is new

        // The student's open page gets the answer, and it counts as read only then.
        $this->assertNull($second->fresh()->read_at);
        $this->actingAs($this->student)->getJson(route('account.chat.poll', ['after' => $first->id]))->assertOk()->assertJsonPath('last_id', $second->id);
        $this->assertNotNull($second->fresh()->read_at);

        $this->postJson(route('account.chat.store'), ['body' => ''])->assertStatus(422);
    }

    public function test_everyone_only_reaches_what_is_theirs(): void
    {
        $other = User::factory()->create(['name' => 'Keo Lina']);
        $other->chatMessages()->create(['sender_id' => $other->id, 'from_student' => true, 'body' => 'Private question']);

        $this->actingAs($this->student)->get(route('account.chat'))->assertDontSee('Private question');
        $this->assertSame('', $this->getJson(route('account.chat.poll', ['after' => 0]))->json('html'));

        // Students cannot use the admin's chat.
        $before = ChatMessage::count();
        foreach ([route('admin.chats.index'), route('admin.chats.show', $other), route('admin.chats.poll', $other)] as $url) {
            $this->get($url)->assertRedirect();
        }
        $this->post(route('admin.chats.store', $other), ['body' => 'Pretending'])->assertRedirect();
        $this->assertSame($before, ChatMessage::count());

        // Admins do not have a conversation with themselves or each other.
        $this->actingAs($this->admin)->get(route('admin.chats.show', $this->admin))->assertNotFound();
        $this->post(route('admin.chats.store', $this->admin), ['body' => 'Hello'])->assertNotFound();
        $this->post(route('account.chat.store'), ['body' => 'From an admin'])->assertRedirect(route('admin.chats.index'));
        $this->assertSame($before, ChatMessage::count());

        // Guests sign in first.
        auth()->logout();
        $this->get(route('account.chat'))->assertRedirect(route('login'));
        $this->post(route('account.chat.store'), ['body' => 'Hello'])->assertRedirect(route('login'));
    }

    public function test_an_email_goes_out_and_stays_in_the_history(): void
    {
        Mail::fake();
        $page = route('admin.chats.show', $this->student);

        $form = $this->actingAs($this->admin)->get(route('admin.chats.show', [$this->student, 'via' => 'email']))->assertOk()->assertSee('name="subject"', false)->assertSee('data-signature', false);
        $this->assertSame('email', $form->viewData('mode'));
        $this->assertSame('chat', $this->get($page)->viewData('mode'));

        // Missing text: nothing goes out, and the email form stays open with what was typed.
        $this->from($page)->post(route('admin.chats.store', $this->student), ['via' => 'email', 'subject' => '', 'body' => 'My text'])
            ->assertRedirect($page)->assertSessionHasErrors(['subject' => 'Write a subject first.'])->assertSessionHasInput('body', 'My text');
        $this->assertSame('email', $this->get($page)->viewData('mode'));
        Mail::assertNothingSent();

        $this->post(route('admin.chats.store', $this->student), ['via' => 'email', 'subject' => 'Your class moved', 'body' => "Hello Dara,\nSee https://teamitcambodia.com/my/courses", 'signature' => "Seyha\nTEAMIT Cambodia"])
            ->assertRedirect($page)->assertSessionHas('status', 'Email sent to dara@example.com.');

        Mail::assertSent(StudentMessage::class, function (StudentMessage $mail) {
            $html = $mail->render();

            return $mail->hasTo('dara@example.com') && $mail->hasSubject('Your class moved')
                && str_contains($html, 'Hello Dara,<br>')
                && str_contains($html, 'href="https://teamitcambodia.com/my/courses"')
                && str_contains($html, 'Seyha<br>')
                && str_contains($html, route('account.chat', [], false));
        });

        $kept = ChatMessage::sole();
        $this->assertSame(['Your class moved', "Seyha\nTEAMIT Cambodia", $this->admin->id, false], [$kept->subject, $kept->signature, $kept->sender_id, $kept->from_student]);
        $this->assertNotNull($kept->emailed_at);
        $this->get($page)->assertSee('Sent by email')->assertSee('Your class moved');

        // The student has it in the chat too.
        $this->actingAs($this->student)->get(route('account.chat'))->assertSee(__('account.chat_by_email'))->assertSee('Your class moved')->assertSee('Hello Dara,');
    }

    public function test_nothing_is_kept_when_the_email_cannot_go(): void
    {
        $page = route('admin.chats.show', $this->student);

        config(['mail.default' => 'log']); // mail not set up
        $this->actingAs($this->admin)->get($page)->assertSee('Email is not set up on this server yet');
        $this->from($page)->post(route('admin.chats.store', $this->student), ['via' => 'email', 'subject' => 'Hello', 'body' => 'My text'])
            ->assertRedirect($page)->assertSessionHas('chat_error')->assertSessionHasInput('body', 'My text');
        $this->assertSame(0, ChatMessage::count());

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        $this->from($page)->post(route('admin.chats.store', $this->student), ['via' => 'email', 'subject' => 'Hello', 'body' => 'My text'])
            ->assertSessionHas('chat_error', fn ($e) => str_contains($e, 'could not be sent: this server could not reach the mail server'));
        $this->assertSame(0, ChatMessage::count());

        // The error shows on the email form, with the text still there.
        $this->from($page)->get($page)->assertSee('could not be sent')->assertSee('My text');
    }

    public function test_a_failed_send_says_why(): void
    {
        $reason = fn (string $error) => MailSetup::reason(new \RuntimeException($error));

        $this->assertStringContainsString('refused the sign-in', $reason('Failed to authenticate on SMTP server with username "a@b.c"'));
        $this->assertStringContainsString('refused the sign-in', $reason('Expected response code "235" but got code "535"'));
        $this->assertStringContainsString('could not reach', $reason('Connection could not be established with host "smtp.gmail.com:587"'));
        $this->assertStringContainsString('could not reach', $reason('Connection to "smtp.gmail.com:587" timed out.'));
        $this->assertStringContainsString('refused or did not answer', $reason('Something else'));
    }

    public function test_class_and_student_pages_offer_chat_and_email(): void
    {
        $course = Course::create(['title' => 'Basic Excel', 'slug' => 'basic-excel', 'is_published' => true]);
        $classroom = Classroom::create(['name' => 'Weekend']);
        $classroom->syncCourses([$course->id]);
        $classroom->students()->attach($this->student->id);
        $chat = route('admin.chats.show', $this->student);
        $email = route('admin.chats.show', [$this->student, 'via' => 'email']);

        $this->actingAs($this->admin)->get(route('admin.classes.show', $classroom))->assertOk()
            ->assertSee('href="'.$chat.'"', false)->assertSee('href="'.$email.'"', false)->assertDontSee('has-unread', false);

        $this->student->chatMessages()->create(['sender_id' => $this->student->id, 'from_student' => true, 'body' => 'Help']);
        $this->get(route('admin.classes.show', $classroom))->assertSee('has-unread', false);

        $this->get(route('admin.students.show', $this->student))->assertOk()->assertSee('href="'.$chat.'"', false)->assertSee('href="'.$email.'"', false);

        // A class with several courses shows them on its Overview too.
        $classroom->syncCourses([$course->id, Course::create(['title' => 'Pivot', 'slug' => 'pivot', 'is_published' => true])->id]);
        $this->get(route('admin.classes.show', $classroom))->assertSee('href="'.$email.'"', false)->assertSee('has-unread', false);
    }

    public function test_too_many_messages_in_a_minute_are_refused(): void
    {
        $this->actingAs($this->student);
        foreach (range(1, 20) as $i) {
            $this->postJson(route('account.chat.store'), ['body' => "Message $i"])->assertOk();
        }
        $this->postJson(route('account.chat.store'), ['body' => 'One more'])->assertStatus(429);
        $this->assertSame(20, ChatMessage::count());

        // Checking for new messages has its own counter.
        $this->getJson(route('account.chat.poll'))->assertOk();
    }

    public function test_deleting_a_student_removes_their_conversation(): void
    {
        $this->student->chatMessages()->create(['sender_id' => $this->admin->id, 'from_student' => false, 'body' => 'Hello']);
        $this->student->delete();
        $this->assertSame(0, ChatMessage::count());
    }

    public function test_an_answer_stays_when_the_admin_who_wrote_it_is_deleted(): void
    {
        $other = User::factory()->create(['name' => 'Old Teacher']);
        $other->forceFill(['role' => 'admin'])->save();
        $this->student->chatMessages()->create(['sender_id' => $other->id, 'from_student' => false, 'body' => 'An old answer']);
        $other->delete();

        $this->assertNull(ChatMessage::sole()->sender_id);
        $this->actingAs($this->admin)->get(route('admin.chats.show', $this->student))->assertOk()->assertSee('An old answer')->assertSee('Admin ·', false);
    }
}
