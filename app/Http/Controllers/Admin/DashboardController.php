<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Course;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $monthStart = now()->startOfMonth();

        return view('admin.dashboard', [
            'kpis' => [
                [
                    'label' => 'Sales this month', 'icon' => 'courses', 'url' => route('admin.orders.index', ['status' => 'paid']),
                    'value' => (float) Order::where('status', 'paid')->where('paid_at', '>=', $monthStart)->sum('amount'),
                    'money' => true,
                    'note' => Order::where('status', 'paid')->where('paid_at', '>=', $monthStart)->count().' paid orders · '.User::where('role', 'student')->count().' students',
                ],
                [
                    'label' => 'Tutorials', 'icon' => 'tutorials', 'url' => route('admin.articles.index', ['type' => 'tutorial']),
                    'value' => Article::ofType('tutorial')->count(),
                    'delta' => Article::ofType('tutorial')->where('published_at', '>=', $monthStart)->count(),
                ],
                [
                    'label' => 'News', 'icon' => 'news', 'url' => route('admin.articles.index', ['type' => 'news']),
                    'value' => Article::ofType('news')->count(),
                    'delta' => Article::ofType('news')->where('published_at', '>=', $monthStart)->count(),
                ],
                [
                    'label' => 'Unread messages', 'icon' => 'inbox', 'url' => route('admin.inquiries.index'),
                    'value' => $unread = Inquiry::whereNull('read_at')->count(),
                    'note' => Inquiry::count().' received in total',
                    'alert' => $unread > 0,
                ],
            ],
            'messagesChart' => $this->dailyMessages(30),
            'publishedChart' => $this->monthlyPublished(12),
            'recent' => $this->recentlyEdited(6),
            'inquiries' => Inquiry::latest()->take(5)->get(),
        ]);
    }

    /** Contact-form messages per day, oldest first. */
    private function dailyMessages(int $days): array
    {
        $counts = Inquiry::where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->get(['created_at'])
            ->countBy(fn ($i) => $i->created_at->format('Y-m-d'));

        return collect(range($days - 1, 0))->map(function ($ago) use ($counts) {
            $day = now()->subDays($ago);

            return [
                'label' => $day->format('D j M'),
                'tick' => $ago % 7 === 0 ? $day->format('j M') : null,
                'value' => $counts[$day->format('Y-m-d')] ?? 0,
            ];
        })->all();
    }

    /** Tutorials, news and posts published per month, oldest first. */
    private function monthlyPublished(int $months): array
    {
        $counts = Article::whereBetween('published_at', [now()->startOfMonth()->subMonths($months - 1), now()])
            ->get(['published_at'])
            ->countBy(fn ($a) => $a->published_at->format('Y-m'));

        return collect(range($months - 1, 0))->map(function ($ago) use ($counts) {
            $month = now()->startOfMonth()->subMonths($ago);

            return [
                'label' => $month->format('F Y'),
                'tick' => $month->format('M'),
                'value' => $counts[$month->format('Y-m')] ?? 0,
            ];
        })->all();
    }

    /** Latest edited courses and articles, newest first. */
    private function recentlyEdited(int $limit)
    {
        $courses = Course::latest('updated_at')->take($limit)->get()->map(fn (Course $c) => [
            'title' => $c->title,
            'type' => 'Course',
            'image' => $c->mediaUrl() ?? $c->mediaUrl('icon'),
            'url' => route('admin.courses.edit', $c),
            'updated_at' => $c->updated_at,
            'published' => $c->is_published,
        ]);
        $articles = Article::latest('updated_at')->take($limit)->get()->map(fn (Article $a) => [
            'title' => $a->title,
            'type' => ucfirst(Article::SINGULAR[$a->type]),
            'image' => $a->mediaUrl(),
            'url' => route('admin.articles.edit', $a),
            'updated_at' => $a->updated_at,
            'published' => $a->is_published,
        ]);

        return $courses->concat($articles)->sortByDesc('updated_at')->take($limit)->values();
    }
}
