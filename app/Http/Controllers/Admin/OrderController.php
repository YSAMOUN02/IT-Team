<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Order;
use App\Payments\OrderProcessor;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = array_key_exists((string) $request->query('status'), Order::STATUSES) ? $request->query('status') : null;

        return view('admin.orders.index', [
            'status' => $status,
            'counts' => Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'revenue' => Order::where('status', 'paid')->sum('amount'),
            'orders' => Order::query()
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                    ->where('number', 'like', "%$term%")->orWhere('buyer_name', 'like', "%$term%")
                    ->orWhere('buyer_email', 'like', "%$term%")->orWhere('course_title', 'like', "%$term%")))
                ->latest()->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Order $order)
    {
        return view('admin.orders.show', ['order' => $order->load(['user', 'course', 'enrollment'])]);
    }

    /** Ask PayWay again (pending orders, or ones flagged for review). */
    public function recheck(Order $order, OrderProcessor $processor)
    {
        $order = $processor->refresh($order);

        return back()->with('status', 'Checked with PayWay: '.Order::STATUSES[$order->status].($order->gateway_status ? ' ('.$order->gateway_status.')' : '').'.');
    }

    /** Money given back (in the PayWay portal, or cash): the course access it bought goes too. */
    public function refund(Order $order)
    {
        abort_unless($order->isPaid(), 422);

        $order->update(['status' => 'refunded']);
        $removed = Enrollment::revokeOrder($order);

        return back()->with('status', 'Order marked as refunded.'.($removed
            ? ' The course access it paid for has been removed.'
            : ' Access given by hand (if any) was not changed. Remove it from the student page if needed.'));
    }
}
