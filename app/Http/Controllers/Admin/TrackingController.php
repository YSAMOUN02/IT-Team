<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdClick;
use App\Models\AdView;
use App\Models\Article;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\PageView;
use App\Support\Ads;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Tracking: how each post, course and banner did from one date to another, compared
 * with the same number of days just before. Click a row to see that one item on its own.
 */
class TrackingController extends Controller
{
    public const TABS = ['posts' => 'Posts', 'courses' => 'Courses', 'ads' => 'Ads'];

    public function index(Request $request)
    {
        [$from, $to] = $this->period($request);
        $days = (int) $from->diffInDays($to->copy()->startOfDay()) + 1;
        $before = [$from->copy()->subDays($days), $from->copy()->subSecond()];
        $period = [$from, $to];
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'posts';

        return view('admin.tracking.index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'per' => $this->bucket($days),
            'presets' => $this->presets(),
        ] + match ($tab) {
            'courses' => $this->courses($request, $period, $before),
            'ads' => $this->ads($request, $period, $before),
            default => $this->posts($request, $period, $before),
        });
    }

    /** The dates asked for: whole days, oldest first, none in the future. Without dates: the last 30 days. */
    private function period(Request $request): array
    {
        $today = today();
        $date = function (mixed $value): ?Carbon {
            return is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                ? Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])
                : null;
        };

        $from = $date($request->query('from')) ?? $today->copy()->subDays(29);
        $to = $date($request->query('to')) ?? $today->copy();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        // A year typed by mistake (0202…) must not build a chart over centuries.
        $from = $from->max($today->copy()->subYears(10))->min($today);
        $to = $to->min($today)->max($from);

        return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
    }

    /** Quick choices above the table: label => [from, to]. */
    private function presets(): array
    {
        $today = today();
        $lastMonth = $today->copy()->subMonthNoOverflow();

        return collect([
            'Today' => [$today, $today],
            'Last 7 days' => [$today->copy()->subDays(6), $today],
            'Last 30 days' => [$today->copy()->subDays(29), $today],
            'This month' => [$today->copy()->startOfMonth(), $today],
            'Last month' => [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()],
            'This year' => [$today->copy()->startOfYear(), $today],
        ])->map(fn ($range) => [$range[0]->format('Y-m-d'), $range[1]->format('Y-m-d')])->all();
    }

    /** One line per post (tutorials and news), most read first. */
    private function posts(Request $request, array $period, array $before): array
    {
        $type = array_key_exists((string) $request->query('type'), Article::TYPES) ? (string) $request->query('type') : null;
        $articles = Article::when($type, fn ($q) => $q->ofType($type))
            ->orderByDesc('published_at')->orderByDesc('id')
            ->get(['id', 'type', 'title', 'slug', 'image', 'is_published', 'published_at']);
        $selected = $articles->firstWhere('id', $request->integer('item'));

        $views = fn (array $between) => PageView::whereBetween('created_at', $between)->where('kind', 'post')
            ->when($selected, fn ($q) => $q->where('article_id', $selected->id), fn ($q) => $q->whereIn('article_id', $articles->pluck('id')));
        $summary = 'count(*) as views, count(distinct visitor_id) as visitors, avg(case when seconds > 0 then seconds end) as avg_seconds';

        $stats = PageView::whereBetween('created_at', $period)->where('kind', 'post')->whereIn('article_id', $articles->pluck('id'))
            ->selectRaw('article_id, '.$summary)->groupBy('article_id')->get()->keyBy('article_id');

        $rows = $articles
            ->map(fn (Article $article) => (object) [
                'article' => $article,
                'views' => (int) ($stats[$article->id]->views ?? 0),
                'visitors' => (int) ($stats[$article->id]->visitors ?? 0),
                'avg_seconds' => (int) round((float) ($stats[$article->id]->avg_seconds ?? 0)),
            ])
            ->reject(fn ($row) => ! $row->views && ! $row->article->is_published) // drafts nobody can read
            ->sortByDesc('views')->values();

        $now = $views($period)->selectRaw($summary)->first();
        $earlier = $views($before)->selectRaw($summary)->first();

        return [
            'postType' => $type, // not "type": page variables reach the shared field templates
            'postTypes' => Article::TYPES,
            'selected' => $selected,
            'rows' => $rows,
            'totals' => (object) [
                'views' => (int) $now->views,
                'visitors' => (int) $now->visitors,
                'avg_seconds' => (int) round((float) $now->avg_seconds),
                'read' => $rows->where('views', '>', 0)->count(),
                'all' => $rows->count(),
            ],
            'change' => [
                'views' => AnalyticsController::change((int) $now->views, (int) $earlier->views),
                'visitors' => AnalyticsController::change((int) $now->visitors, (int) $earlier->visitors),
            ],
            'chart' => $this->series(fn () => $views($period), 'count(*)', $period),
            'sources' => AnalyticsController::breakdown($views($period), 'source'),
        ];
    }

    /** One line per course: opened → clicked Buy / Enrol → became a student → income. */
    private function courses(Request $request, array $period, array $before): array
    {
        $courses = Course::orderBy('sort_order')->orderBy('id')->get(['id', 'title', 'slug', 'image', 'is_free', 'is_published']);
        $selected = $courses->firstWhere('id', $request->integer('item'));

        $funnel = "sum(case when kind = 'course' then 1 else 0 end) as views,
            count(distinct case when kind = 'course' then visitor_id end) as visitors,
            count(distinct case when kind = 'checkout' then visitor_id end) as clicked_buy,
            count(distinct case when kind in ('learn', 'lesson', 'quiz') then visitor_id end) as studying,
            avg(case when kind = 'course' and seconds > 0 then seconds end) as avg_seconds";
        $pages = fn (array $between) => PageView::whereBetween('created_at', $between)->whereNotNull('course_id')
            ->when($selected, fn ($q) => $q->where('course_id', $selected->id));
        // Given only a certificate (studied in class): not a new student of the course online.
        $students = fn (array $between) => Enrollment::whereBetween('created_at', $between)->where('source', '!=', 'certificate')
            ->when($selected, fn ($q) => $q->where('course_id', $selected->id));
        $sales = fn () => Invoice::whereBetween('issued_at', $period)->where('status', 'paid')
            ->when($selected, fn ($q) => $q->where('course_id', $selected->id));

        $stats = PageView::whereBetween('created_at', $period)->whereNotNull('course_id')
            ->selectRaw('course_id, '.$funnel)->groupBy('course_id')->get()->keyBy('course_id');
        $newStudents = Enrollment::whereBetween('created_at', $period)->where('source', '!=', 'certificate')
            ->selectRaw('course_id, count(*) as total')->groupBy('course_id')->pluck('total', 'course_id');
        $finished = Enrollment::whereBetween('completed_at', $period)
            ->selectRaw('course_id, count(*) as total')->groupBy('course_id')->pluck('total', 'course_id');
        $income = Invoice::whereBetween('issued_at', $period)->where('status', 'paid')
            ->selectRaw('course_id, currency, sum(amount) as total')->groupBy('course_id', 'currency')->get()->groupBy('course_id');

        $rows = $courses
            ->map(fn (Course $course) => (object) [
                'course' => $course,
                'views' => (int) ($stats[$course->id]->views ?? 0),
                'visitors' => (int) ($stats[$course->id]->visitors ?? 0),
                'clicked_buy' => (int) ($stats[$course->id]->clicked_buy ?? 0),
                'studying' => (int) ($stats[$course->id]->studying ?? 0),
                'avg_seconds' => (int) round((float) ($stats[$course->id]->avg_seconds ?? 0)),
                'students' => (int) ($newStudents[$course->id] ?? 0),
                'finished' => (int) ($finished[$course->id] ?? 0),
                'income' => $this->money($income[$course->id] ?? collect()),
            ])
            ->sortByDesc(fn ($row) => [$row->visitors, $row->students])->values();

        $now = $pages($period)->selectRaw($funnel)->first();
        $earlier = $pages($before)->selectRaw($funnel)->first();
        $newNow = $students($period)->count();

        return [
            'selected' => $selected,
            'rows' => $rows,
            'totals' => (object) [
                'views' => (int) $now->views,
                'visitors' => (int) $now->visitors,
                'clicked_buy' => (int) $now->clicked_buy,
                'students' => $newNow,
                'income' => $this->money($sales()->selectRaw('currency, sum(amount) as total')->groupBy('currency')->get()),
                'invoices' => $sales()->where('amount', '>', 0)->count(),
            ],
            'change' => [
                'visitors' => AnalyticsController::change((int) $now->visitors, (int) $earlier->visitors),
                'students' => AnalyticsController::change($newNow, $students($before)->count()),
            ],
            'chart' => $this->series(fn () => $pages($period)->where('kind', 'course'), 'count(distinct visitor_id)', $period),
            'sources' => AnalyticsController::breakdown($pages($period)->where('kind', 'course'), 'source'),
        ];
    }

    /** One line per ad spot: how often its banner was on screen, and how often it was clicked. */
    private function ads(Request $request, array $period, array $before): array
    {
        $placements = config('ads');
        $selected = array_key_exists((string) $request->query('item'), $placements) ? (string) $request->query('item') : null;

        $views = fn (array $between) => AdView::whereBetween('created_at', $between)->when($selected, fn ($q) => $q->where('placement', $selected));
        $clicks = fn (array $between) => AdClick::whereBetween('created_at', $between)->when($selected, fn ($q) => $q->where('placement', $selected));

        $viewStats = AdView::whereBetween('created_at', $period)
            ->selectRaw('placement, count(*) as views, count(distinct visitor_id) as visitors')->groupBy('placement')->get()->keyBy('placement');
        $clickStats = AdClick::whereBetween('created_at', $period)
            ->selectRaw('placement, count(*) as total')->groupBy('placement')->pluck('total', 'placement');

        $rows = collect($placements)
            ->map(function (array $placement, string $key) use ($viewStats, $clickStats) {
                $seen = (int) ($viewStats[$key]->views ?? 0);
                $clicked = (int) ($clickStats[$key] ?? 0);

                return (object) [
                    'key' => $key,
                    'label' => $placement['label'],
                    'where' => $placement['description'],
                    'state' => Ads::state($key),
                    'kind' => Ads::type($key),
                    'banner' => Ads::banner($key),
                    'same_as_header' => Ads::sharesPictureWithHeader($key),
                    'views' => $seen,
                    'visitors' => (int) ($viewStats[$key]->visitors ?? 0),
                    'clicks' => $clicked,
                    'rate' => $this->rate($clicked, $seen),
                ];
            })
            ->sortByDesc(fn ($row) => [$row->views, $row->clicks])->values();

        $seen = $views($period)->count();
        $clicked = $clicks($period)->count();

        return [
            'selected' => $selected,
            'selectedLabel' => $selected ? $placements[$selected]['label'] : null,
            'rows' => $rows,
            'totals' => (object) [
                'views' => $seen,
                'visitors' => $views($period)->distinct()->count('visitor_id'),
                'clicks' => $clicked,
                'rate' => $this->rate($clicked, $seen),
            ],
            'change' => [
                'views' => AnalyticsController::change($seen, $views($before)->count()),
                'clicks' => AnalyticsController::change($clicked, $clicks($before)->count()),
            ],
            'chart' => $this->series(fn () => $views($period), 'count(*)', $period),
            'chartClicks' => $this->series(fn () => $clicks($period), 'count(*)', $period),
        ];
    }

    /** Clicks per 100 views. Clicks were counted before views were, so more clicks than views says nothing. */
    private function rate(int $clicks, int $views): ?float
    {
        return $views > 0 && $clicks <= $views ? round($clicks * 100 / $views, 1) : null;
    }

    /** "$45.00", or "$45.00 + 100,000 ៛" when courses sell in both currencies. Empty: "—". */
    private function money($totals): string
    {
        $lines = collect($totals)->filter(fn ($row) => (float) $row->total > 0)
            ->map(fn ($row) => money_format_simple((float) $row->total, $row->currency));

        return $lines->isEmpty() ? '—' : $lines->implode(' + ');
    }

    private function bucket(int $days): string
    {
        return $days === 1 ? 'hour' : ($days <= 62 ? 'day' : 'month');
    }

    /**
     * Chart points for a period: per hour for one day, per day up to two months, per month beyond,
     * with empty hours, days and months included.
     *
     * @param  callable  $query  makes a fresh query for the period
     */
    private function series(callable $query, string $aggregate, array $period): array
    {
        [$from, $to] = $period;
        $days = (int) $from->diffInDays($to->copy()->startOfDay()) + 1;
        $sqlite = DB::connection()->getDriverName() === 'sqlite';
        $count = fn (string $bucketSql) => $query()->selectRaw("$bucketSql as bucket, $aggregate as total")->groupBy('bucket')->pluck('total', 'bucket');

        if ($days === 1) {
            $counts = $count($sqlite ? "CAST(strftime('%H', created_at) AS INTEGER)" : 'HOUR(created_at)');

            return collect(range(0, $to->isToday() ? now()->hour : 23))->map(fn ($h) => [
                'label' => sprintf('%02d:00–%02d:59', $h, $h),
                'tick' => $h % 3 === 0 ? sprintf('%02d', $h) : null,
                'value' => (int) ($counts[$h] ?? 0),
            ])->all();
        }

        if ($days <= 62) {
            $counts = $count('DATE(created_at)');
            $every = $days <= 7 ? 1 : ($days <= 31 ? 7 : 14);

            return collect(range(0, $days - 1))->map(function ($i) use ($from, $counts, $days, $every) {
                $day = $from->copy()->addDays($i);

                return [
                    'label' => $day->format('D j M'),
                    'tick' => ($days - 1 - $i) % $every === 0 ? $day->format($days <= 7 ? 'D' : 'j M') : null,
                    'value' => (int) ($counts[$day->format('Y-m-d')] ?? 0),
                ];
            })->all();
        }

        $counts = $count($sqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')");
        $months = [];
        for ($month = $from->copy()->startOfMonth(); $month->lte($to); $month->addMonthNoOverflow()) {
            $months[] = $month->copy();
        }
        $every = max(1, (int) ceil(count($months) / 8));

        return collect($months)->values()->map(fn ($month, $i) => [
            'label' => $month->format('F Y'),
            'tick' => (count($months) - 1 - $i) % $every === 0 ? $month->format(count($months) <= 12 ? 'M' : 'M y') : null,
            'value' => (int) ($counts[$month->format('Y-m')] ?? 0),
        ])->all();
    }
}
