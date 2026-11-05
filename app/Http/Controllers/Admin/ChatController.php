<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ChatController as StudentChat;
use App\Http\Controllers\Controller;
use App\Mail\StudentMessage;
use App\Models\ChatMessage;
use App\Models\User;
use App\Support\MailSetup;
use App\Support\ReplySignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Admin > Chat: every student's conversation, answered by chat or by email (both kept in one history). */
class ChatController extends Controller
{
    public function index(Request $request)
    {
        $show = $request->query('show') === 'unread' ? 'unread' : 'all';
        $term = trim((string) $request->query('q', ''));
        $like = '%'.addcslashes($term, '%_\\').'%';

        $latest = ChatMessage::selectRaw('student_id, MAX(id) as last_id')->groupBy('student_id');
        $conversations = User::query()
            ->joinSub($latest, 'latest', 'latest.student_id', '=', 'users.id')
            ->select('users.*', 'latest.last_id')
            ->withCount(['chatMessages as unread_count' => fn ($q) => $q->unreadByAdmins()])
            ->when($show === 'unread', fn ($q) => $q->whereHas('chatMessages', fn ($m) => $m->unreadByAdmins()))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)))
            ->orderByDesc('latest.last_id')
            ->paginate(30)->withQueryString();

        return view('admin.chats.index', [
            'conversations' => $conversations,
            'lastMessages' => ChatMessage::with('sender:id,name')->whereIn('id', $conversations->pluck('last_id'))->get()->keyBy('student_id'),
            'show' => $show,
            'term' => $term,
            'counts' => ['all' => ChatMessage::distinct()->count('student_id'), 'unread' => ChatMessage::studentsWaiting()],
        ]);
    }

    public function show(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 404);

        $messages = $user->chatMessages()->with('sender:id,name')->latest('id')->take(StudentChat::SHOWN)->get()->reverse()->values();
        $user->chatMessages()->unreadByAdmins()->update(['read_at' => now()]);

        return view('admin.chats.show', [
            'student' => $user,
            'messages' => $messages,
            'mode' => in_array('email', [$request->query('via'), $request->old('via')], true) ? 'email' : 'chat',
            'signature' => ReplySignature::get(),
            'mailReady' => MailSetup::ready(),
            'from' => config('mail.from.address'),
            'courses' => $user->enrollments()->with('course:id,title')->get(),
            'classes' => $user->classrooms()->orderBy('name')->get(['classrooms.id', 'name']),
        ]);
    }

    public function store(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 404);

        if ($request->input('via') === 'email') {
            return $this->sendEmail($request, $user);
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], ['body.required' => 'Write a message first.']);
        $user->chatMessages()->create(['sender_id' => $request->user()->id, 'from_student' => false, 'body' => ChatMessage::clean($data['body'])]);
        $user->chatMessages()->unreadByAdmins()->update(['read_at' => now()]); // answering them means having read them

        return $request->wantsJson()
            ? response()->json(ChatMessage::payload($user, $request->integer('after'), 'admin'))
            : redirect()->route('admin.chats.show', $user);
    }

    /** New messages after ?after=ID. The open page asks every few seconds, so seeing them marks them read. */
    public function poll(Request $request, User $user)
    {
        abort_if($user->isAdmin(), 404);

        $payload = ChatMessage::payload($user, $request->integer('after'), 'admin');
        if ($payload['html'] !== '') {
            $user->chatMessages()->unreadByAdmins()->update(['read_at' => now()]);
        }

        return response()->json($payload);
    }

    /**
     * Through the site's email, with the shared signature (a change on the form is kept for next time).
     * Kept and sent together: if the email cannot go, nothing is kept and the text stays on the form.
     */
    private function sendEmail(Request $request, User $user)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'signature' => ['nullable', 'string', 'max:2000'],
        ], ['subject.required' => 'Write a subject first.', 'body.required' => 'Write the email first.']);

        $signature = ChatMessage::clean($data['signature'] ?? '');
        if ($request->has('signature') && $signature !== ReplySignature::get()) {
            ReplySignature::put($signature);
        }

        if (! MailSetup::ready()) {
            return back()->withInput()->with('chat_error', 'Email is not set up on this server yet (MAIL_* in .env), so nothing was sent.');
        }

        try {
            DB::transaction(function () use ($request, $user, $data, $signature) {
                $message = $user->chatMessages()->create([
                    'sender_id' => $request->user()->id,
                    'from_student' => false,
                    'body' => ChatMessage::clean($data['body']),
                    'subject' => ChatMessage::clean($data['subject']),
                    'signature' => $signature !== '' ? $signature : null,
                    'emailed_at' => now(),
                ]);
                Mail::to($user->email, $user->name)->locale(config('app.locale'))->send(new StudentMessage($message));
            });
        } catch (Throwable $e) {
            Log::warning("Email to student {$user->id} ({$user->email}) failed: ".$e->getMessage());

            return back()->withInput()->with('chat_error', 'The email could not be sent: '.MailSetup::reason($e).'. Your text is still here.');
        }

        return redirect()->route('admin.chats.show', $user)->with('status', "Email sent to {$user->email}.");
    }
}
