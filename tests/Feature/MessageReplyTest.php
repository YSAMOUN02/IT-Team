<?php

namespace Tests\Feature;

use App\Mail\MessageReply;
use App\Models\Inquiry;
use App\Models\InquiryReply;
use App\Models\Setting;
use App\Models\User;
use App\Support\ReplySignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Admin > Messages: answer a contact-form message by email, with one shared, editable signature. */
class MessageReplyTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $admin = User::factory()->create(['name' => 'Mang Seyha']);
        $admin->forceFill(['role' => 'admin'])->save();

        return $admin;
    }

    private function message(): Inquiry
    {
        return Inquiry::create(['name' => 'Heng Rithy', 'email' => 'rithy@example.com', 'phone' => '012 345 678',
            'subject' => 'Certificate', 'message' => "Hello,\nwhen do I get my certificate?"]);
    }

    public function test_the_reply_box_is_ready_with_subject_and_signature(): void
    {
        Setting::put(['site_name' => 'TEAMIT Cambodia', 'phone' => '(+855) 096-80-54-558', 'email' => 'teamitcambodia@gmail.com']);
        $inquiry = $this->message();

        $this->actingAs($this->adminUser())->get(route('admin.inquiries.show', $inquiry))->assertOk()
            ->assertSee('value="Re: Certificate"', false)
            ->assertSee('rithy@example.com')
            ->assertSee("TEAMIT Cambodia\nTel: (+855) 096-80-54-558\nteamitcambodia@gmail.com", false)
            ->assertSee('Not answered yet');
    }

    public function test_a_reply_is_emailed_kept_and_marks_the_message_replied(): void
    {
        Mail::fake();
        Setting::put(['email' => 'teamitcambodia@gmail.com']);
        $admin = $this->adminUser();
        $inquiry = $this->message();
        $page = route('admin.inquiries.show', $inquiry);

        $this->actingAs($admin)->from($page)->post(route('admin.inquiries.reply', $inquiry), [
            'subject' => 'Re: Certificate', 'body' => "Hi Rithy,\nIt is ready: https://teamitcambodia.com/my/courses", 'signature' => "Seyha\nTEAMIT Cambodia",
        ])->assertRedirect($page)->assertSessionHas('status', 'Reply sent to rithy@example.com.');

        Mail::assertSent(MessageReply::class, function (MessageReply $mail) {
            $html = $mail->render();

            return $mail->hasTo('rithy@example.com') && $mail->hasReplyTo('teamitcambodia@gmail.com')
                && $mail->envelope()->subject === 'Re: Certificate'
                && str_contains($html, 'Hi Rithy,<br>')
                && str_contains($html, '<a href="https://teamitcambodia.com/my/courses"')   // links made clickable
                && str_contains($html, 'Seyha<br>')                                        // signature
                && str_contains($html, 'when do I get my certificate?');                   // their message quoted
        });

        $reply = InquiryReply::sole();
        $this->assertSame([$admin->id, 'rithy@example.com', "Seyha\nTEAMIT Cambodia"], [$reply->user_id, $reply->to_email, $reply->signature]);
        $this->assertNotNull($inquiry->fresh()->replied_at);

        // The conversation and the list show it.
        $this->get($page)->assertSee('1 reply sent')->assertSee('It is ready');
        $this->get(route('admin.inquiries.index'))->assertSee('Replied');
    }

    public function test_one_signature_changed_on_the_form_is_kept_for_the_next_reply(): void
    {
        Mail::fake();
        $inquiry = $this->message();
        $this->actingAs($this->adminUser())->post(route('admin.inquiries.reply', $inquiry), [
            'subject' => 'Re: Certificate', 'body' => 'Thanks!', 'signature' => "Seyha\nIT Deputy Manager",
        ]);

        $this->assertSame("Seyha\nIT Deputy Manager", ReplySignature::get());
        $this->get(route('admin.inquiries.show', $this->message()))->assertSee("Seyha\nIT Deputy Manager", false);

        // Also changeable on its own (Settings, or the signature form).
        $this->put(route('admin.inquiries.signature'), ['signature' => 'TEAMIT team'])->assertSessionHas('status', 'Signature saved.');
        $this->assertSame('TEAMIT team', ReplySignature::get());
    }

    public function test_nothing_is_kept_when_the_email_cannot_go(): void
    {
        config(['mail.default' => 'log']); // mail not set up
        $inquiry = $this->message();
        $page = route('admin.inquiries.show', $inquiry);

        $this->actingAs($this->adminUser())->from($page)->post(route('admin.inquiries.reply', $inquiry), ['subject' => 'Re: Certificate', 'body' => 'Thanks!'])
            ->assertRedirect($page)->assertSessionHas('reply_error')->assertSessionHasInput('body', 'Thanks!');
        $this->assertSame(0, InquiryReply::count());
        $this->assertNull($inquiry->fresh()->replied_at);

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        $this->from($page)->post(route('admin.inquiries.reply', $inquiry), ['subject' => 'Re: Certificate', 'body' => 'Thanks!'])
            ->assertSessionHas('reply_error', fn ($e) => str_contains($e, 'could not be sent'));
        $this->assertSame(0, InquiryReply::count());

        $this->post(route('admin.inquiries.reply', $inquiry), ['subject' => 'Re: Certificate', 'body' => ''])->assertSessionHasErrors(['body' => 'Write the reply first.']);
    }

    public function test_broken_characters_are_cleaned_instead_of_breaking_the_send(): void
    {
        Mail::fake();
        $inquiry = $this->message();

        $this->actingAs($this->adminUser())->post(route('admin.inquiries.reply', $inquiry), ['subject' => 'Re: Certificate', 'body' => "Admin \x9B Messages សួស្តី"])
            ->assertSessionHasNoErrors()->assertSessionMissing('reply_error');

        $this->assertSame('Admin ? Messages សួស្តី', InquiryReply::sole()->body); // a broken byte becomes "?"
        Mail::assertSent(MessageReply::class, 1);
    }

    public function test_the_email_text_is_escaped_and_addresses_become_links(): void
    {
        $this->assertSame(
            'See <a href="https://a.com/x?y=1&amp;z=2" style="color:#5f59f7">https://a.com/x?y=1&amp;z=2</a>.<br>'."\n"
                .'&lt;script&gt;alert(1)&lt;/script&gt; javascript:alert(1)<br>'."\n"
                .'&quot;<a href="https://b.com" style="color:#5f59f7">https://b.com</a>&quot; or <a href="mailto:me@b.com" style="color:#5f59f7">me@b.com</a>',
            MessageReply::textToHtml("See https://a.com/x?y=1&z=2.\n<script>alert(1)</script> javascript:alert(1)\n\"https://b.com\" or me@b.com")
        );
    }

    public function test_a_signature_saved_in_settings_is_the_one_on_the_form(): void
    {
        Mail::fake();
        $admin = $this->adminUser();
        // A browser's textarea sends Windows line ends.
        $this->actingAs($admin)->put('/admin/settings', ['settings' => ['reply_signature' => "Seyha\r\nTEAMIT Cambodia"]])->assertSessionHasNoErrors();
        $this->assertSame("Seyha\nTEAMIT Cambodia", ReplySignature::get());

        $inquiry = $this->message();
        $this->get(route('admin.inquiries.show', $inquiry))->assertSee("Seyha\nTEAMIT Cambodia", false);
        $this->post(route('admin.inquiries.reply', $inquiry), ['subject' => 'Re: Certificate', 'body' => 'Thanks!', 'signature' => "Seyha\r\nTEAMIT Cambodia"]);
        $this->assertSame("Seyha\nTEAMIT Cambodia", InquiryReply::sole()->signature);

        // Later edits do not change what was already sent; emptying it brings back the default.
        $this->put(route('admin.inquiries.signature'), ['signature' => '']);
        $this->assertSame(ReplySignature::default(), ReplySignature::get());
        $this->assertSame("Seyha\nTEAMIT Cambodia", InquiryReply::sole()->signature);
    }

    public function test_deleting_a_message_also_removes_its_replies(): void
    {
        Mail::fake();
        $inquiry = $this->message();
        $this->actingAs($this->adminUser())->post(route('admin.inquiries.reply', $inquiry), ['subject' => 'Re: Certificate', 'body' => 'Thanks!']);
        $this->assertSame(1, InquiryReply::count());

        $this->delete(route('admin.inquiries.destroy', $inquiry))->assertRedirect(route('admin.inquiries.index'));
        $this->assertSame(0, InquiryReply::count());
    }

    public function test_only_admins_can_reply(): void
    {
        Mail::fake();
        $inquiry = $this->message();

        $this->actingAs(User::factory()->create())->post(route('admin.inquiries.reply', $inquiry), ['subject' => 'x', 'body' => 'y']);
        Mail::assertNothingSent();
        $this->assertSame(0, InquiryReply::count());
    }
}
