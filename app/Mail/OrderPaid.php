<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Receipt sent to the buyer once PayWay confirms the payment. */
class OrderPaid extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('account.mail_paid_subject', ['course' => $this->order->course_title]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order-paid');
    }
}
