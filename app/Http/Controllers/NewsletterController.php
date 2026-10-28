<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function store(Request $request)
    {
        // Honeypot: real visitors never fill the hidden "website" field.
        if (filled($request->input('website'))) {
            return $this->back(__('site.newsletter_thanks'));
        }

        $data = $request->validateWithBag('newsletter', [
            'email' => ['required', 'email', 'max:190'],
        ]);

        $subscriber = Subscriber::firstOrNew(['email' => mb_strtolower(trim($data['email']))]);
        $already = $subscriber->exists && ! $subscriber->unsubscribed_at;
        $subscriber->fill(['unsubscribed_at' => null, 'ip' => $request->ip()])->save();

        return $this->back(__($already ? 'site.newsletter_already' : 'site.newsletter_thanks'));
    }

    public function unsubscribe(string $token)
    {
        $subscriber = Subscriber::where('token', $token)->firstOrFail();
        $subscriber->update(['unsubscribed_at' => $subscriber->unsubscribed_at ?? now()]);

        return view('newsletter-unsubscribed', ['email' => $subscriber->email]);
    }

    private function back(string $message)
    {
        return redirect()->to(url()->previous().'#newsletter')->with('newsletter_status', $message);
    }
}
