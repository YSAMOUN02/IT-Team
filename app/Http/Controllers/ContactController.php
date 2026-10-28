<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        // Honeypot: real visitors never fill the hidden "website" field.
        if (filled($request->input('website'))) {
            return back()->with('status', __('site.contact_sent'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', 'string', 'max:190'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        Inquiry::create($data + ['ip' => $request->ip()]);

        return back()->with('status', __('site.contact_sent'));
    }
}
