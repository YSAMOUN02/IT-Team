<?php

namespace App\Payments;

use App\Mail\OrderPaid;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Brings an order up to date with PayWay. Safe to call many times (browser
 * return, PayWay pushback, the "check again" button): access is granted once.
 */
class OrderProcessor
{
    public function __construct(private PayWay $payway)
    {
    }

    /** Ask PayWay and apply the answer. Returns the fresh order. */
    public function refresh(Order $order): Order
    {
        if (! in_array($order->status, ['pending', 'review'], true)) {
            return $order;
        }

        try {
            $result = $this->payway->checkTransaction($order);
        } catch (Throwable $e) {
            Log::warning('PayWay check failed for order '.$order->number.': '.$e->getMessage());
            $order->forceFill(['checked_at' => now()])->save();

            return $order;
        }

        return $this->apply($order, $result);
    }

    public function apply(Order $order, array $result): Order
    {
        $justPaid = false;

        $order = DB::transaction(function () use ($order, $result, &$justPaid) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->fill([
                'checked_at' => now(),
                'gateway_status' => $result['gateway_status'],
                'gateway_payload' => array_merge((array) $order->gateway_payload, ['last_check' => $result['payload']]),
            ]);

            if ($result['status'] === 'paid' && ! $order->isPaid()) {
                $amountMatches = $result['amount'] === null || abs($result['amount'] - (float) $order->amount) < 0.01;
                $currencyMatches = blank($result['currency']) || strtoupper($result['currency']) === strtoupper($order->currency);

                if (! $amountMatches || ! $currencyMatches) {
                    // Paid, but not what we asked for: an admin has to look at it.
                    $order->status = 'review';
                } else {
                    $order->fill(['status' => 'paid', 'paid_at' => now(), 'apv' => $result['apv']]);
                    if ($order->course) {
                        Enrollment::grant($order->user, $order->course, $order->access_days, 'payment', $order);
                    }
                    $justPaid = true;
                }
            } elseif (in_array($result['status'], ['failed', 'cancelled'], true) && $order->isPending()) {
                $order->status = $result['status'];
            } elseif ($result['status'] === 'refunded' && $order->status !== 'refunded') {
                $wasPaid = $order->isPaid();
                $order->status = 'refunded';
                if ($wasPaid) {
                    Enrollment::revokeOrder($order); // money back = access back
                }
            }

            $order->save();

            return $order;
        });

        if ($justPaid) {
            try {
                Mail::to($order->buyer_email)->send(new OrderPaid($order));
            } catch (Throwable $e) {
                Log::warning('Receipt email failed for order '.$order->number.': '.$e->getMessage());
            }
        }

        return $order;
    }
}
