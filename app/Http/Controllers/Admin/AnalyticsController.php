<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Order;
use App\Models\PageView;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Admin > Visitors: who looks at the website, which pages and courses, when and for how long. */
class AnalyticsController extends Controller
{
    public const RANGES = ['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days'];

    public function index(Request $request)
    {
        $range = array_key_exists((string) $request->query('range'), self::RANGES) ? $request->query('range') : '7d';
        $days = ['today' => 1, '7d' => 7, '30d' => 30, '90d' => 90][$range];
        $from = today()->subDays($days - 1);
        $previousFrom = $from->copy()->subDays($days);
        $visitor = preg_match('/^[a-f0-9]{32}$/', (string) $request->query('visitor')) ? $request->query('visitor') : null;

        $inRange = fn () => PageView::where('created_at', '>=', $from);
        $totals = $this->totals($inRange());
        $previous = $this->totals(PageView::whereBetween('created_at', [$previousFrom, $from]));

        return view('admin.analytics.index', [
            'range' => $range,
            'ranges' => self::RANGES,
            'totals' => $totals,
            'change' => [
                'visitors' => $this->change($totals->visitors, $previous->visitors),
                'views' => $this->change($totals->views, $previous->views),
            ],
            'online' => PageView::where('updated_at', '>=', now()->subMinutes(5))->distinct()->count('visitor_id'),
            'trend' => $range === 'today' ? $this->byHourToday() : $this->byDay($from, $days),
            'hours' => $this->byHourOfDay($inRange()),
            'pages' => $inRange()->selectRaw('path, max(title) as title, count(*) as views, count(distinct visitor_id) as visitors, avg(case when seconds > 0 then seconds end) as avg_seconds')
                ->groupBy('path')->orderByDesc('views')->limit(15)->get(),
            'courses' => $this->courses($inRange(), $from),
            'sources' => $this->breakdown($inRange(), 'source'),
            'devices' => $this->breakdown($inRange(), 'device'),
            'browsers' => $this->breakdown($inRange(), 'browser'),
            'systems' => $this->breakdown($inRange(), 'os'),
            'visitor' => $visitor,
            'activity' => PageView::with(['user:id,name', 'course:id,title'])
                ->when($visitor, fn ($q) => $q->where('visitor_id', $visitor), fn ($q) => $q->where('created_at', '>=', $from))
                ->latest('id')->limit($visitor ? 300 : 60)->get(),
        ]);
    }

    private function totals($query): object
    {
        $row = $query->selectRaw('count(*) as views, count(distinct visitor_id) as visitors, count(distinct session_id) as visits, avg(case when seconds > 0 then seconds end) as avg_seconds')->first();

        return (object) [
            'views' => (int) $row->views,
            'visitors' => (int) $row->visitors,
            'visits' => (int) $row->visits,
            'avg_seconds' => (int) round((float) $row->avg_seconds),
            'pages_per_visit' => $row->visits ? round($row->views / $row->visits, 1) : 0,
        ];
    }

    /** Percent change against the previous period of the same length (null = nothing to compare with). */
    private function change(int $now, int $before): ?int
    {
        return $before > 0 ? (int) round(($now - $before) * 100 / $before) : null;
    }

    /** Visitors per day, oldest first, for the column chart. */
    private function byDay(Carbon $from, int $days): array
    {
        $counts = PageView::where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as day, count(distinct visitor_id) as visitors')
            ->groupBy('day')->pluck('visitors', 'day');

        return collect(range(0, $days - 1))->map(function ($i) use ($from, $counts, $days) {
            $day = $from->copy()->addDays($i);

            return [
                'label' => $day->format('D j M'),
                'tick' => $days <= 7 || ($days - 1 - $i) % ($days > 30 ? 14 : 7) === 0 ? $day->format($days <= 7 ? 'D' : 'j M') : null,
                'value' => (int) ($counts[$day->format('Y-m-d')] ?? 0),
            ];
        })->all();
    }

    /** Today: visitors per hour so far. */
    private function byHourToday(): array
    {
        $counts = PageView::where('created_at', '>=', today())
            ->selectRaw($this->hourSql().' as h, count(distinct visitor_id) as visitors')->groupBy('h')->pluck('visitors', 'h');

        return collect(range(0, now()->hour))->map(fn ($h) => [
            'label' => sprintf('%02d:00–%02d:59', $h, $h),
            'tick' => $h % 3 === 0 ? sprintf('%02d', $h) : null,
            'value' => (int) ($counts[$h] ?? 0),
        ])->all();
    }

    /** Which hours of the day people visit (all days in the period together). */
    private function byHourOfDay($query): array
    {
        $counts = $query->selectRaw($this->hourSql().' as h, count(*) as views')->groupBy('h')->pluck('views', 'h');

        return collect(range(0, 23))->map(fn ($h) => [
            'label' => sprintf('%02d:00–%02d:59', $h, $h),
            'tick' => $h % 3 === 0 ? sprintf('%02d', $h) : null,
            'value' => (int) ($counts[$h] ?? 0),
        ])->all();
    }

    private function hourSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "CAST(strftime('%H', created_at) AS INTEGER)" : 'HOUR(created_at)';
    }

    /** Per course: opened the course page → clicked Buy / Enrol → paid → studied. */
    private function courses($query, Carbon $from)
    {
        $stats = $query->whereNotNull('course_id')->selectRaw("course_id,
                sum(case when kind = 'course' then 1 else 0 end) as views,
                count(distinct case when kind = 'course' then visitor_id end) as visitors,
                count(distinct case when kind = 'checkout' then visitor_id end) as clicked_buy,
                count(distinct case when kind in ('learn', 'lesson', 'quiz') then visitor_id end) as studying,
                avg(case when kind = 'course' and seconds > 0 then seconds end) as avg_seconds")
            ->groupBy('course_id')->get()->keyBy('course_id');
        $paid = Order::where('status', 'paid')->where('paid_at', '>=', $from)
            ->selectRaw('course_id, count(*) as orders')->groupBy('course_id')->pluck('orders', 'course_id');

        return Course::orderBy('sort_order')->orderBy('id')->get(['id', 'title', 'slug', 'is_free'])
            ->map(fn (Course $course) => (object) [
                'course' => $course,
                'views' => (int) ($stats[$course->id]->views ?? 0),
                'visitors' => (int) ($stats[$course->id]->visitors ?? 0),
                'clicked_buy' => (int) ($stats[$course->id]->clicked_buy ?? 0),
                'paid' => (int) ($paid[$course->id] ?? 0),
                'studying' => (int) ($stats[$course->id]->studying ?? 0),
                'avg_seconds' => (int) round((float) ($stats[$course->id]->avg_seconds ?? 0)),
            ])
            ->sortByDesc('visitors')->values();
    }

    private function breakdown($query, string $column)
    {
        $rows = $query->selectRaw("$column as name, count(distinct visitor_id) as visitors")
            ->groupBy($column)->orderByDesc('visitors')->limit(8)->get();
        $total = max(1, $rows->sum('visitors'));

        return $rows->map(fn ($row) => (object) ['name' => $row->name, 'visitors' => (int) $row->visitors, 'share' => round($row->visitors * 100 / $total)]);
    }
}
