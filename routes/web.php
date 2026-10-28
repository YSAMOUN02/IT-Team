<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\StudentAuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\TrackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
*/
Route::get('/', HomeController::class)->name('home');

Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course:slug}', [CourseController::class, 'show'])->name('courses.show');
// The pricing page was removed (prices are on each course page); old links go to the course list.
Route::redirect('/pricing', '/courses', 301);

// Buy / enrol: visitors who are not signed in get "create account or sign in" first, then come back here.
Route::middleware('no-store')->group(function () {
    Route::get('/courses/{course:slug}/enroll', [CourseController::class, 'enroll'])->name('courses.enroll');
    Route::get('/courses/{course:slug}/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
});

Route::get('/tutorials', [ArticleController::class, 'tutorials'])->name('tutorials.index');
Route::get('/tutorials/{slug}', [ArticleController::class, 'tutorial'])->name('tutorials.show');
Route::get('/news', [ArticleController::class, 'newsIndex'])->name('news.index');
Route::get('/news/{slug}', [ArticleController::class, 'newsItem'])->name('news.show');
// News and blog are one section now.
Route::get('/blog', fn () => redirect()->route('news.index', [], 301));
Route::get('/blog/{slug}', fn (string $slug) => redirect()->route('news.show', $slug, 301));

Route::get('/about', [PageController::class, 'show'])->defaults('slug', 'about')->name('about');
Route::get('/contact', [PageController::class, 'show'])->defaults('slug', 'contact')->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::get('/coming-soon', [PageController::class, 'show'])->defaults('slug', 'coming-soon')->name('coming-soon');

Route::get('/search', SearchController::class)->name('search');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots']);

// AdSense asks for /ads.txt; served from the publisher ID in Admin > Google Ads.
Route::get('/ads.txt', function () {
    $txt = \App\Support\Ads::adsTxt() ?? abort(404);

    return response($txt, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
});

Route::post('/newsletter', [NewsletterController::class, 'store'])->middleware('throttle:6,1')->name('newsletter.store');

// Your own banner ads link here: counts the click, then goes on. ("ads" is left
// out of the address on purpose: ad blockers hide anything under /ads/.)
Route::get('/go/{placement}', \App\Http\Controllers\AdClickController::class)->where('placement', '[a-z_]+')->name('promo.go');

// Website statistics (page opened / time on page), see Admin > Visitors.
Route::post('/stats/view', [TrackController::class, 'view'])->middleware('throttle:stats')->name('stats.view');
Route::post('/stats/leave', [TrackController::class, 'leave'])->middleware('throttle:stats')->name('stats.leave');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

Route::get('/certificates/{code}', [LearnController::class, 'verifyCertificate'])->name('certificates.verify');

/*
|--------------------------------------------------------------------------
| Student accounts
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [StudentAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [StudentAuthController::class, 'login'])->middleware('throttle:10,1')->name('login.store');
    Route::get('/register', [StudentAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [StudentAuthController::class, 'register'])->middleware('throttle:6,1')->name('register.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:4,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');

    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth', 'no-store'])->group(function () {
    Route::post('/logout', [StudentAuthController::class, 'logout'])->name('logout');

    Route::get('/my/courses', [AccountController::class, 'courses'])->name('account.courses');
    Route::get('/my/orders', [AccountController::class, 'orders'])->name('account.orders');
    Route::get('/my/profile', [AccountController::class, 'profile'])->name('account.profile');
    Route::put('/my/profile', [AccountController::class, 'updateProfile'])->name('account.profile.update');

    // Checkout (ABA PayWay)
    Route::post('/courses/{course:slug}/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/checkout/{order}/return', [CheckoutController::class, 'return'])->name('checkout.return');
    Route::get('/checkout/{order}/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
    Route::get('/checkout/{order}/test', [CheckoutController::class, 'testGateway'])->name('checkout.test');
    Route::post('/checkout/{order}/test', [CheckoutController::class, 'testGatewayResult'])->name('checkout.test.result');
    Route::get('/orders/{order}', [CheckoutController::class, 'receipt'])->name('orders.show');
});

Route::post('/payway/pushback', [CheckoutController::class, 'pushback'])->middleware('throttle:60,1')->name('payway.pushback');

/*
|--------------------------------------------------------------------------
| Course player (lessons, quizzes, mock exams, certificate)
|--------------------------------------------------------------------------
*/
Route::prefix('learn/{course:slug}')->name('learn.')->middleware('no-store')->scopeBindings()->group(function () {
    // Free-preview lessons and their files are open to everyone; the rest check access in the controller.
    Route::get('lessons/{lesson:slug}', [LearnController::class, 'lesson'])->name('lesson');
    Route::get('lessons/{lesson:slug}/files/{kind}', [LearnController::class, 'file'])->name('file');

    Route::middleware('auth')->group(function () {
        Route::get('/', [LearnController::class, 'course'])->name('course');
        Route::post('lessons/{lesson:slug}/complete', [LearnController::class, 'complete'])->name('complete');
        Route::get('certificate', [LearnController::class, 'certificate'])->name('certificate');

        Route::get('quizzes/{quiz:slug}', [QuizController::class, 'show'])->name('quiz');
        Route::post('quizzes/{quiz:slug}/start', [QuizController::class, 'start'])->name('quiz.start');
        Route::get('quizzes/{quiz:slug}/attempts/{attempt}', [QuizController::class, 'attempt'])->name('attempt');
        Route::post('quizzes/{quiz:slug}/attempts/{attempt}', [QuizController::class, 'submit'])->name('submit');
        Route::get('quizzes/{quiz:slug}/attempts/{attempt}/result', [QuizController::class, 'result'])->name('result');
        Route::post('quizzes/{quiz:slug}/questions/{question}/check', [QuizController::class, 'check'])->middleware('throttle:quiz-check')->name('check');
    });
});

// Old WordPress URLs -> new URLs (301), so existing links and search results keep working.
Route::get('/tdcpt_service/{slug}', [LegacyRedirectController::class, 'course']);
Route::get('/tdcpt_location/{slug}', [LegacyRedirectController::class, 'tutorial']);
Route::get('/tdcpt_testimonial/{slug}', fn () => redirect()->to(route('home').'#testimonials', 301));
Route::get('/{year}/{month}/{day}/{slug}', [LegacyRedirectController::class, 'post'])
    ->where(['year' => '\d{4}', 'month' => '\d{2}', 'day' => '\d{2}']);
Route::get('/wp-admin', fn () => redirect()->route('admin.dashboard', [], 301));
Route::get('/wp-login.php', fn () => redirect()->route('admin.login', [], 301));

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'create'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'store'])->middleware('throttle:10,1')->name('login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::post('logout', [Admin\AuthController::class, 'destroy'])->name('logout');

        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::get('visitors', [Admin\AnalyticsController::class, 'index'])->name('analytics');
        Route::get('search', Admin\SearchController::class)->name('search');

        // Courses and their curriculum (modules, lessons, quizzes, questions)
        Route::resource('courses', Admin\CourseController::class)->except('show');
        Route::get('courses/{course}/curriculum', [Admin\CurriculumController::class, 'show'])->name('courses.curriculum');
        Route::post('courses/{course}/curriculum/reorder', [Admin\CurriculumController::class, 'reorder'])->name('courses.curriculum.reorder');
        Route::post('courses/{course}/curriculum/quick-add', [Admin\CurriculumController::class, 'quickAdd'])->name('courses.curriculum.quick-add');
        Route::post('curriculum/toggle', [Admin\CurriculumController::class, 'toggle'])->name('curriculum.toggle');
        Route::post('courses/{course}/duplicate', [Admin\CourseController::class, 'duplicate'])->name('courses.duplicate');
        Route::get('courses/{course}/students', [Admin\CourseController::class, 'students'])->name('courses.students');
        Route::post('courses/{course}/students', [Admin\CourseController::class, 'grantByEmail'])->name('courses.students.grant');
        Route::post('courses/{course}/modules', [Admin\CurriculumController::class, 'storeModule'])->name('modules.store');
        Route::put('modules/{module}', [Admin\CurriculumController::class, 'updateModule'])->name('modules.update');
        Route::delete('modules/{module}', [Admin\CurriculumController::class, 'destroyModule'])->name('modules.destroy');
        Route::post('curriculum/move', [Admin\CurriculumController::class, 'move'])->name('curriculum.move');
        Route::resource('courses.lessons', Admin\LessonController::class)->except(['index', 'show'])->shallow();
        Route::resource('courses.quizzes', Admin\QuizController::class)->except(['index', 'show'])->shallow();
        Route::resource('quizzes.questions', Admin\QuestionController::class)->except(['index', 'show'])->shallow();

        // Sales
        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/recheck', [Admin\OrderController::class, 'recheck'])->name('orders.recheck');
        Route::post('orders/{order}/refund', [Admin\OrderController::class, 'refund'])->name('orders.refund');
        Route::get('students', [Admin\StudentController::class, 'index'])->name('students.index');
        Route::get('students/{user}', [Admin\StudentController::class, 'show'])->name('students.show');
        Route::post('students/{user}/enrollments', [Admin\StudentController::class, 'grant'])->name('students.grant');
        Route::delete('enrollments/{enrollment}', [Admin\StudentController::class, 'revoke'])->name('enrollments.destroy');
        Route::post('students/{user}/sign-out', [Admin\StudentController::class, 'signOut'])->name('students.sign-out');

        Route::resource('articles', Admin\ArticleController::class)->except('show');
        Route::resource('testimonials', Admin\TestimonialController::class)->except('show');
        Route::resource('partners', Admin\PartnerController::class)->except('show');
        Route::resource('pages', Admin\PageController::class)->only(['index', 'edit', 'update']);
        Route::resource('inquiries', Admin\InquiryController::class)->only(['index', 'show', 'destroy']);
        Route::get('subscribers/export', [Admin\SubscriberController::class, 'export'])->name('subscribers.export');
        Route::resource('subscribers', Admin\SubscriberController::class)->only(['index', 'destroy']);
        Route::resource('users', Admin\UserController::class)->except('show');

        Route::get('ads', [Admin\AdController::class, 'edit'])->name('ads.edit');
        Route::put('ads', [Admin\AdController::class, 'update'])->name('ads.update');

        Route::get('settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [Admin\SettingController::class, 'update'])->name('settings.update');

        Route::post('uploads', Admin\UploadController::class)->name('uploads.store');
        Route::post('uploads/word', [Admin\UploadController::class, 'word'])->name('uploads.word');
    });
});
