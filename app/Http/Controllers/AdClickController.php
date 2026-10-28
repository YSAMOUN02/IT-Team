<?php

namespace App\Http\Controllers;

use App\Models\AdClick;
use App\Support\Ads;
use App\Support\Visitor;
use Illuminate\Http\Request;

/**
 * A banner ad links here first: the click is counted, then the visitor goes on
 * to the banner's link. Bots and admins are not counted.
 */
class AdClickController extends Controller
{
    public function __invoke(Request $request, string $placement)
    {
        $banner = Ads::banner($placement);
        abort_unless($banner && $banner['link'], 404);

        if (! Visitor::isBot($request->userAgent()) && ! $request->user()?->isAdmin()) {
            $visitor = $request->cookie(TrackController::VISITOR_COOKIE);
            AdClick::create([
                'placement' => $placement,
                'visitor_id' => is_string($visitor) && preg_match('/^[a-f0-9]{32}$/', $visitor) ? $visitor : null,
            ]);
        }

        return redirect()->to($banner['link']);
    }
}
