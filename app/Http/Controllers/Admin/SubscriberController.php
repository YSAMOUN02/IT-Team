<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['active', 'unsubscribed'], true) ? $request->query('status') : 'all';

        return view('admin.subscribers.index', [
            'status' => $status,
            'totals' => [
                'all' => Subscriber::count(),
                'active' => Subscriber::active()->count(),
                'unsubscribed' => Subscriber::whereNotNull('unsubscribed_at')->count(),
            ],
            'subscribers' => Subscriber::query()
                ->when($request->query('q'), fn ($q, $term) => $q->where('email', 'like', '%'.$term.'%'))
                ->when($status === 'active', fn ($q) => $q->whereNull('unsubscribed_at'))
                ->when($status === 'unsubscribed', fn ($q) => $q->whereNotNull('unsubscribed_at'))
                ->latest()->paginate(30)->withQueryString(),
        ]);
    }

    /** Active subscribers as CSV, for importing into an email tool. */
    public function export()
    {
        $rows = Subscriber::active()->orderBy('created_at')->get(['email', 'created_at']);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly
            fputcsv($out, ['email', 'subscribed_at']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->email, $row->created_at->format('Y-m-d H:i')]);
            }
            fclose($out);
        }, 'subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(Subscriber $subscriber)
    {
        $subscriber->delete();

        return redirect()->route('admin.subscribers.index')->with('status', 'Subscriber removed.');
    }
}
