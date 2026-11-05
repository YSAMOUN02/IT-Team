<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use Illuminate\Http\Request;

/** My account > Chat: a student's conversation with the school. Admins answer in Admin > Chat. */
class ChatController extends Controller
{
    /** Older messages stay in the database; the page opens on the latest ones. */
    public const SHOWN = 200;

    public function show(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return redirect()->route('admin.chats.index');
        }

        $messages = $user->chatMessages()->with('sender:id,name')->latest('id')->take(self::SHOWN)->get()->reverse()->values();
        $user->chatMessages()->unreadByStudent()->update(['read_at' => now()]);

        return view('account.chat', compact('messages'));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return redirect()->route('admin.chats.index');
        }

        $data = $request->validate(['body' => ['required', 'string', 'max:2000']], ['body.required' => __('account.chat_write_first')]);
        $user->chatMessages()->create(['sender_id' => $user->id, 'from_student' => true, 'body' => ChatMessage::clean($data['body'])]);

        return $request->wantsJson()
            ? response()->json(ChatMessage::payload($user, $request->integer('after'), 'student'))
            : redirect()->route('account.chat');
    }

    /** New messages after ?after=ID. The open page asks every few seconds, so seeing them marks them read. */
    public function poll(Request $request)
    {
        $user = $request->user();
        $payload = ChatMessage::payload($user, $request->integer('after'), 'student');
        if ($payload['html'] !== '') {
            $user->chatMessages()->unreadByStudent()->update(['read_at' => now()]);
        }

        return response()->json($payload);
    }
}
