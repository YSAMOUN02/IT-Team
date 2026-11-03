<?php

namespace App\Http\Controllers;

use App\Models\AdView;
use App\Models\Article;
use App\Models\Course;
use App\Models\PageView;
use App\Support\Ads;
use App\Support\Visitor;
use Illuminate\Http\Request;

/**
 * Website statistics. The page tells us it was opened (view) and, when it is
 * closed or hidden, how long it was on screen (leave). Search-engine bots and
 * admins are not counted; no IP address is stored.
 */
class TrackController extends Controller
{
    public const VISITOR_COOKIE = 'tic_vid';

    public const VISIT_COOKIE = 'tic_sid';

    public const MAX_SECONDS = 1800; // longer is a tab left open, not reading

    public function view(Request $request)
    {
        if (Visitor::isBot($request->userAgent()) || $request->user()?->isAdmin()) {
            return response()->noContent();
        }

        $data = $request->validate([
            'path' => ['required', 'string', 'max:1000'],
            'title' => ['nullable', 'string', 'max:500'],
            'referrer' => ['nullable', 'string', 'max:1000'],
            'utm' => ['nullable', 'string', 'max:80'],
        ]);

        $path = '/'.ltrim(rawurldecode((string) parse_url($data['path'], PHP_URL_PATH)), '/');
        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return response()->noContent();
        }

        $agent = (string) $request->userAgent();
        $visitor = $this->cookieId($request, self::VISITOR_COOKIE);
        $existingVisit = $this->validId($request->cookie(self::VISIT_COOKIE));
        $visit = $existingVisit ?? bin2hex(random_bytes(16));

        $ownHosts = array_filter([$request->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)]);
        [$source, $host] = Visitor::source($data['referrer'] ?? null, $data['utm'] ?? null, $ownHosts);
        if ($source === 'Direct' && $existingVisit) {
            // Pages after the first one in a visit keep the visit's source (Facebook, Google…).
            $source = PageView::where('session_id', $existingVisit)->orderBy('id')->value('source') ?? $source;
        }
        [$kind, $courseId, $articleId] = $this->classify($path);

        $view = PageView::create([
            'visitor_id' => $visitor,
            'session_id' => $visit,
            'user_id' => $request->user()?->id,
            'path' => mb_substr($path, 0, 255),
            'title' => isset($data['title']) ? mb_substr(trim($data['title']), 0, 255) : null,
            'course_id' => $courseId,
            'article_id' => $articleId,
            'kind' => $kind,
            'source' => $source,
            'referrer_host' => $host ? mb_substr($host, 0, 120) : null,
            'device' => Visitor::device($agent),
            'browser' => Visitor::browser($agent),
            'os' => Visitor::os($agent),
        ]);

        return response()->json(['id' => $view->id])
            ->withCookie(cookie(self::VISITOR_COOKIE, $visitor, 60 * 24 * 365))
            ->withCookie(cookie(self::VISIT_COOKIE, $visit, 30)); // a visit ends after 30 quiet minutes
    }

    /** Time on page: only ever grows, capped, and only for the visitor who opened it. */
    public function leave(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ]);
        $visitor = $this->validId($request->cookie(self::VISITOR_COOKIE));

        $view = $visitor ? PageView::whereKey($data['id'])->where('visitor_id', $visitor)
            ->where('created_at', '>=', now()->subHours(12))->first() : null;
        if ($view) {
            $view->seconds = max($view->seconds, min((int) $data['seconds'], self::MAX_SECONDS));
            $view->save();
            $view->touch();
        }

        return response()->noContent();
    }

    /**
     * A banner came on screen (the page says so once per banner, per page opened). These are the
     * "views" next to its clicks in Admin > Tracking. Bots and admins are not counted.
     */
    public function spot(Request $request)
    {
        if (Visitor::isBot($request->userAgent()) || $request->user()?->isAdmin()) {
            return response()->noContent();
        }

        $data = $request->validate(['spot' => ['required', 'string', 'max:40']]);
        if (Ads::state($data['spot']) === 'live') {
            AdView::create(['placement' => $data['spot'], 'visitor_id' => $this->validId($request->cookie(self::VISITOR_COOKIE))]);
        }

        return response()->noContent();
    }

    /** What kind of page this is, and which course or post it belongs to. */
    private function classify(string $path): array
    {
        $patterns = [
            'checkout' => '#^/courses/([^/]+)/(checkout|enroll)$#u',
            'course' => '#^/courses/([^/]+)$#u',
            'lesson' => '#^/learn/([^/]+)/lessons/#u',
            'quiz' => '#^/learn/([^/]+)/quizzes/#u',
            'learn' => '#^/learn/([^/]+)/?$#u',
        ];
        foreach ($patterns as $kind => $pattern) {
            if (preg_match($pattern, $path, $m)) {
                return [$kind, Course::where('slug', $m[1])->value('id'), null];
            }
        }

        foreach (Article::SECTIONS as $type => $section) {
            if (preg_match('#^/'.$section.'/([^/]+)/?$#u', $path, $m)) {
                $articleId = Article::ofType($type)->where('slug', $m[1])->value('id');

                return $articleId ? ['post', null, $articleId] : ['page', null, null];
            }
        }

        return ['page', null, null];
    }

    private function cookieId(Request $request, string $name): string
    {
        return $this->validId($request->cookie($name)) ?? bin2hex(random_bytes(16));
    }

    private function validId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[a-f0-9]{32}$/', $value) ? $value : null;
    }
}
