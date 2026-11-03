<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A kept copy of one sale or course access. Made when an order is paid (Order::booted) or when an
 * admin gives access by hand; the student downloads it from My account > Invoices.
 */
class Invoice extends Model
{
    public const METHODS = [
        'cash' => 'Cash',
        'transfer' => 'ABA / bank transfer',
        'free' => 'Free / scholarship',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'access_until' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Invoice $invoice) {
            $invoice->forceFill(['number' => 'INV-'.$invoice->issued_at->format('Y').'-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** The invoice for a paid order; made once, whatever path marked the order paid. */
    public static function forOrder(Order $order): self
    {
        $enrollment = $order->enrollment ?? Enrollment::where('order_id', $order->id)->first();

        return static::firstOrCreate(['order_id' => $order->id], [
            'user_id' => $order->user_id,
            'course_id' => $order->course_id,
            'course_title' => $order->course_title,
            'student_name' => $order->buyer_name,
            'student_email' => $order->buyer_email,
            'student_phone' => $order->buyer_phone,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'access_days' => $order->access_days,
            'access_until' => $enrollment?->expires_at,
            'payment_method' => $order->gateway ?: 'payway', // unset on a fresh model = the column default
            'reference' => $order->apv ?: $order->number,
            'status' => 'paid',
            'issued_at' => $order->paid_at ?? now(),
        ]);
    }

    /** The invoice for access an admin gave by hand (cash at the office, a transfer, a scholarship). */
    public static function forManualAccess(Enrollment $enrollment, ?int $days, float $amount = 0, ?string $method = null, ?string $note = null): self
    {
        $enrollment->loadMissing('user', 'course');

        return static::create([
            'user_id' => $enrollment->user_id,
            'course_id' => $enrollment->course_id,
            'course_title' => $enrollment->course->title,
            'student_name' => $enrollment->user->name,
            'student_email' => $enrollment->user->email,
            'student_phone' => $enrollment->user->phone,
            'amount' => round(max(0, $amount), 2),
            'currency' => 'USD',
            'access_days' => $days,
            'access_until' => $enrollment->expires_at,
            'payment_method' => $amount > 0 ? ($method && $method !== 'free' ? $method : 'cash') : 'free',
            'note' => $note ?: null,
            'status' => 'paid',
            'issued_at' => now(),
        ]);
    }

    public function formattedAmount(): string
    {
        return money_format_simple((float) $this->amount, $this->currency);
    }

    public function methodLabel(): string
    {
        return __('account.invoice_method_'.$this->payment_method) !== 'account.invoice_method_'.$this->payment_method
            ? __('account.invoice_method_'.$this->payment_method)
            : ucfirst($this->payment_method);
    }
}
