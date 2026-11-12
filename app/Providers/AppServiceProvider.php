<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Course;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\Testimonial;
use App\Support\DeviceLimit;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // A MySQL driver that returns numbers as text would make pages answer 404 on that host only.
        foreach (['mysql' => \Illuminate\Database\MySqlConnection::class, 'mariadb' => \Illuminate\Database\MariaDbConnection::class] as $driver => $connection) {
            \Illuminate\Database\Connection::resolverFor($driver, fn ($pdo, $database, $prefix, $config) => new $connection(
                fn () => tap(value($pdo), \App\Support\NativeNumbersStatement::applyIfNeeded(...)), $database, $prefix, $config,
            ));
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Each sign-in takes one of the student's device places; signing out frees it.
        Event::listen(Login::class, function (Login $event) {
            if ($event->guard === 'web' && $event->user instanceof User && DeviceLimit::applies($event->user)) {
                DeviceLimit::register($event->user);
            }
        });
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                DeviceLimit::forget($event->user, session(DeviceLimit::SESSION_KEY));
            }
        });

        // Busy background requests get their own counters. A plain "throttle:N,1" shares one
        // counter per visitor across all routes, so page-view pings would use up sign-in tries.
        RateLimiter::for('stats', fn (Request $request) => Limit::perMinute(600)->by($request->ip()));
        RateLimiter::for('quiz-check', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('chat-send', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('chat-poll', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // Course links in the public footer.
        View::composer('layouts.site', function ($view) {
            $view->with('footerCourses', Course::published()->take(6)->get(['id', 'title', 'slug']));
        });

        // The red count on the account menu's and the account tabs' Chat link.
        View::composer(['layouts.site', 'account._tabs'], function ($view) {
            $user = auth()->user();
            $view->with('chatUnread', match (true) {
                ! $user => 0,
                $user->isAdmin() => \App\Models\ChatMessage::studentsWaiting(),
                default => $user->chatMessages()->unreadByStudent()->count(),
            });
        });

        // Counts shown next to the admin sidebar links.
        View::composer('admin.layout', function ($view) {
            $articles = Article::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

            $view->with('navCounts', [
                'inquiries' => Inquiry::whereNull('read_at')->count(),
                'chats' => \App\Models\ChatMessage::studentsWaiting(),
                'online' => \App\Models\PageView::where('updated_at', '>=', now()->subMinutes(5))->distinct()->count('visitor_id'),
                'courses' => Course::count(),
                'tutorial' => $articles['tutorial'] ?? 0,
                'news' => $articles['news'] ?? 0,
                'testimonials' => Testimonial::count(),
                'partners' => Partner::count(),
                'subscribers' => Subscriber::active()->count(),
                'orders_paid' => Order::where('status', 'paid')->count(),
                'orders_review' => Order::where('status', 'review')->count(),
                'students' => User::where('role', 'student')->count(),
                'classes' => \App\Models\Classroom::count(),
                'retakes' => \App\Models\RetakeRequest::pending()->count(),
                'to_mark' => \App\Models\QuizAttempt::whereNotNull('submitted_at')->where('needs_marking', true)->whereHas('user', fn ($q) => $q->where('role', 'student'))->count(),
            ]);
        });
    }
}
