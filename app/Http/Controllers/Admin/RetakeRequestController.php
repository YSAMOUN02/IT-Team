<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\RetakeRequest;
use Illuminate\Http\Request;

/** Admin > Re-exam requests: students out of tries ask for another; the teacher approves or declines. */
class RetakeRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $status = $status === 'all' || array_key_exists($status, RetakeRequest::STATUSES) ? $status : 'pending';

        $requests = RetakeRequest::with(['user:id,name,email', 'quiz:id,course_id,title,type,pass_percent,max_attempts', 'quiz.course:id,title', 'decider:id,name'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()->latest('id')
            ->paginate(30)->withQueryString();

        // Each student's tries at the test so far: how many, and the best one.
        $tries = QuizAttempt::whereIn('user_id', $requests->pluck('user_id')->unique())->whereIn('quiz_id', $requests->pluck('quiz_id')->unique())
            ->whereNotNull('submitted_at')->orderByDesc('percent')->orderByDesc('id')->get(['id', 'user_id', 'quiz_id', 'percent', 'passed'])
            ->groupBy(fn ($a) => $a->user_id.'-'.$a->quiz_id);

        return view('admin.retake-requests.index', [
            'requests' => $requests,
            'tries' => $tries,
            'status' => $status,
            'counts' => RetakeRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function decide(Request $request, RetakeRequest $retakeRequest)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,decline'],
            'extra_attempts' => ['nullable', 'integer', 'min:1', 'max:5'],
            'reply' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! $retakeRequest->isPending()) {
            return back()->withErrors(['decision' => 'This request was already answered.']);
        }

        $approve = $data['decision'] === 'approve';
        $retakeRequest->decide($request->user(), $approve, (int) ($data['extra_attempts'] ?? 1), $data['reply'] ?? null)->load('user', 'quiz');

        return back()->with('status', $approve
            ? "Approved: {$retakeRequest->user->name} can take {$retakeRequest->quiz->title} {$retakeRequest->extra_attempts} more ".($retakeRequest->extra_attempts === 1 ? 'time.' : 'times.')
            : "Declined: {$retakeRequest->user->name} was told no for {$retakeRequest->quiz->title}.");
    }

    /** From an attempt's page: give the student more tries without waiting for them to ask. */
    public function give(Request $request, QuizAttempt $attempt)
    {
        abort_if($attempt->user->isAdmin(), 404);
        $data = $request->validate([
            'extra_attempts' => ['nullable', 'integer', 'min:1', 'max:5'],
            'reply' => ['nullable', 'string', 'max:1000'],
        ]);

        // Answers a waiting request if there is one, so it does not stay in the list.
        $retake = RetakeRequest::pending()->where('user_id', $attempt->user_id)->where('quiz_id', $attempt->quiz_id)->first()
            ?? new RetakeRequest(['user_id' => $attempt->user_id, 'quiz_id' => $attempt->quiz_id]);
        $retake->decide($request->user(), true, (int) ($data['extra_attempts'] ?? 1), $data['reply'] ?? null);

        return back()->with('status', "{$attempt->user->name} can take {$attempt->quiz->title} {$retake->extra_attempts} more ".($retake->extra_attempts === 1 ? 'time.' : 'times.'));
    }
}
