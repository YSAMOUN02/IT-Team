<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;

class InquiryController extends Controller
{
    public function index()
    {
        return view('admin.inquiries.index', ['inquiries' => Inquiry::latest()->paginate(30)]);
    }

    public function show(Inquiry $inquiry)
    {
        if (! $inquiry->read_at) {
            $inquiry->update(['read_at' => now()]);
        }

        return view('admin.inquiries.show', compact('inquiry'));
    }

    public function destroy(Inquiry $inquiry)
    {
        $inquiry->delete();

        return redirect()->route('admin.inquiries.index')->with('status', 'Message deleted.');
    }
}
