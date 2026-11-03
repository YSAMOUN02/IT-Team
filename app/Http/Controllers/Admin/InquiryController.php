<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\MessageReply;
use App\Models\Inquiry;
use App\Support\MailSetup;
use App\Support\ReplySignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class InquiryController extends Controller
{
    public function index()
    {
        return view('admin.inquiries.index', ['inquiries' => Inquiry::withCount('replies')->latest()->paginate(30)]);
    }

    public function show(Inquiry $inquiry)
    {
        if (! $inquiry->read_at) {
            $inquiry->update(['read_at' => now()]);
        }

        return view('admin.inquiries.show', [
            'inquiry' => $inquiry->load('replies.user'),
            'signature' => ReplySignature::get(),
            'mailReady' => MailSetup::ready(),
            'from' => config('mail.from.address'),
        ]);
    }

    /**
     * Send a reply through the site's email. The signature is the shared one: if it was changed
     * on the form, the new wording is kept for the next replies too. Only sent replies are kept.
     */
    public function reply(Request $request, Inquiry $inquiry)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'signature' => ['nullable', 'string', 'max:2000'],
        ], [
            'body.required' => 'Write the reply first.',
        ]);

        // Clean text: Windows line ends, and any bytes that are not UTF-8 (they would break the save).
        $clean = fn (?string $text) => trim(mb_convert_encoding(str_replace("\r\n", "\n", (string) $text), 'UTF-8', 'UTF-8'));
        $signature = $clean($data['signature'] ?? '');
        if ($request->has('signature') && $signature !== ReplySignature::get()) {
            ReplySignature::put($signature);
        }

        if (! MailSetup::ready()) {
            return back()->withInput()->with('reply_error', 'Email is not set up on this server yet (MAIL_* in .env), so nothing was sent.');
        }

        // Kept and sent together: if the email cannot go, nothing is kept; if it cannot be kept, nothing is sent.
        try {
            DB::transaction(function () use ($request, $inquiry, $data, $clean, $signature) {
                $reply = $inquiry->replies()->create([
                    'user_id' => $request->user()->id,
                    'to_email' => $inquiry->email,
                    'subject' => $clean($data['subject']),
                    'body' => $clean($data['body']),
                    'signature' => $signature !== '' ? $signature : null,
                ]);
                $reply->setRelation('inquiry', $inquiry);
                Mail::to($inquiry->email, $inquiry->name)->locale(config('app.locale'))->send(new MessageReply($reply));
            });
        } catch (Throwable $e) {
            Log::warning("Reply to message {$inquiry->id} ({$inquiry->email}) failed: ".$e->getMessage());

            return back()->withInput()->with('reply_error', 'The email could not be sent: the mail server refused or did not answer. Your text is still here; try again in a moment.');
        }

        $inquiry->update(['replied_at' => now(), 'read_at' => $inquiry->read_at ?? now()]);

        return redirect()->route('admin.inquiries.show', $inquiry)->with('status', "Reply sent to {$inquiry->email}.");
    }

    /** Change the shared signature without sending anything. */
    public function signature(Request $request)
    {
        $data = $request->validate(['signature' => ['nullable', 'string', 'max:2000']]);
        ReplySignature::put($data['signature'] ?? '');

        return back()->with('status', 'Signature saved.');
    }

    public function destroy(Inquiry $inquiry)
    {
        $inquiry->delete();

        return redirect()->route('admin.inquiries.index')->with('status', 'Message deleted.');
    }
}
