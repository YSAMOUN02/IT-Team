<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CoursePlan;
use App\Models\Enrollment;
use App\Models\Inquiry;
use App\Models\Order;
use App\Models\Quiz;
use App\Models\Subscriber;
use App\Models\User;
use App\Support\CourseProgress;
use App\Support\Slug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Test data so every part of the site can be seen working: a free course,
 * price plans with discounts, sample lessons and tests, news, demo students
 * with orders and progress, messages and newsletter subscribers.
 *
 * Everything created (or changed) is recorded in the "demo_records" setting,
 * and `php artisan demo:clear` removes it again before going live.
 * Demo students sign in with password "demo1234".
 */
class DemoDataSeeder extends Seeder
{
    protected array $made = [];

    private array $changed = [];

    public function run(): void
    {
        if (setting('demo_records')) {
            $this->command?->warn('Test data is already there. Run "php artisan demo:clear" first to make it again.');

            return;
        }

        DB::transaction(function () {
            $this->plansAndDurations();
            $free = $this->freeCourse();
            $this->moreLessons();
            $this->news();
            $this->students($free);
            $this->inbox();
        });

        \App\Models\Setting::put(['demo_records' => json_encode(['made' => $this->made, 'changed' => $this->changed])]);
        $this->command?->info('Test data added. Demo students: dara@example.com, sreyneang@example.com, visal@example.com, sokha@example.com (password: demo1234).');
        $this->command?->info('Remove it all with: php artisan demo:clear');
    }

    /** Several periods with discounts, and a duration, on the real courses. */
    private function plansAndDurations(): void
    {
        $setup = [
            'basic-excel' => ['12 hours', [[90, 19, 0, null], [180, 34, 10, null]]],
            'advanced-excel' => ['20 hours', [[30, 15, 0, null], [180, 69, 15, null], [365, 129, 25, now()->endOfMonth()]]],
            'excel-expert' => ['30 hours', [[90, 499, 20, now()->addDays(14)]]],
        ];

        foreach ($setup as $slug => [$duration, $plans]) {
            $course = Course::where('slug', $slug)->first();
            if (! $course) {
                continue;
            }
            if (blank($course->duration)) {
                $this->changed['course_duration'][$course->id] = $course->duration;
                $course->update(['duration' => $duration]);
            }
            $position = (int) $course->plans()->max('position');
            foreach ($plans as [$days, $price, $discount, $ends]) {
                if ($course->plans()->where('days', $days)->exists()) {
                    continue;
                }
                $this->keep($course->plans()->create([
                    'days' => $days, 'price' => $price, 'discount_percent' => $discount,
                    'discount_ends_at' => $ends, 'position' => ++$position,
                ]));
            }
        }
    }

    private function freeCourse(): Course
    {
        $image = Course::whereNotNull('image')->value('image');
        $course = $this->keep(Course::create([
            'title' => 'Excel Quick Start (Free)',
            'slug' => Slug::unique('excel-quick-start', Course::query()),
            'summary' => 'Test data - a short free course: open Excel, enter data and write your first formulas.',
            'duration' => '2 hours',
            'features' => ['The Excel window', 'Entering data', 'Your first SUM formula'],
            'body' => '<p><em>Test data.</em> A free course to show how free enrolment works: students only need an account.</p>',
            'image' => $image,
            'is_free' => true,
            'is_published' => true,
            'certificate_enabled' => true,
            'sort_order' => 99,
        ]));

        $m1 = $this->module($course, 1, 'Start here', 'Find your way around Excel.');
        $this->lesson($course, $m1, 1, 'Welcome to Excel', '4:00', true);
        $this->lesson($course, $m1, 2, 'Typing and editing data', '6:00');
        $this->quiz($course, $m1, 3, 'practice', 'Practice: the basics', [
            ['Which key confirms an entry and moves down?', ['Enter' => true, 'Esc' => false, 'Delete' => false]],
            ['What is the address of column B, row 4?', ['B4' => true, '4B' => false, 'BB4' => false]],
        ]);
        $m2 = $this->module($course, 2, 'First formulas', 'Let Excel do the maths.');
        $this->lesson($course, $m2, 1, 'Adding with SUM', '7:00');
        $this->quiz($course, $m2, 2, 'quiz', 'Quick Start quiz', [
            ['Every formula starts with…', ['=' => true, '#' => false, '?' => false]],
            ['=SUM(A1:A3) adds…', ['A1, A2 and A3' => true, 'only A1 and A3' => false, 'column A to C' => false]],
            ['Which function finds the largest value?', ['MAX' => true, 'MIN' => false, 'COUNT' => false]],
        ]);

        return $course;
    }

    /** A short sample curriculum for the paid courses that have none yet. */
    private function moreLessons(): void
    {
        $topics = [
            'advanced-excel' => [['Lookups', ['VLOOKUP step by step', 'XLOOKUP, the modern way']], ['PivotTables', ['Your first PivotTable', 'Slicers and timelines']]],
            'excel-expert' => [['Power Query', ['Import and clean data', 'Combine files from a folder']], ['Dashboards', ['Plan a dashboard', 'Interactive charts']]],
        ];
        foreach ($topics as $slug => $modules) {
            $course = Course::where('slug', $slug)->first();
            if (! $course || $course->modules()->exists()) {
                continue;
            }
            foreach ($modules as $i => [$title, $lessons]) {
                $module = $this->module($course, $i + 1, $title, 'Test data module.');
                foreach ($lessons as $n => $lessonTitle) {
                    $this->lesson($course, $module, $n + 1, $lessonTitle, (8 + $n * 3).':00', $i === 0 && $n === 0);
                }
                $this->quiz($course, $module, count($lessons) + 1, 'quiz', $title.' quiz', [
                    ['This is a test question about '.$title.'. Pick the first answer.', ['First answer' => true, 'Second answer' => false, 'Third answer' => false]],
                    ['True or false: this course has sample content.', ['True' => true, 'False' => false]],
                ]);
            }
        }
    }

    private function news(): void
    {
        $images = Article::ofType('tutorial')->whereNotNull('image')->pluck('image')->all();
        $items = [
            ['វគ្គសិក្សា Excel ថ្មីសម្រាប់ឆមាសនេះ', 'Test data - announcement of a new course intake.', 2],
            ['គន្លឹះ Excel ៥ ដែលជួយសន្សំពេលវេលា', 'Test data - five quick tips to work faster in Excel.', 9],
            ['TEAMIT Cambodia hosts a free Excel workshop', 'Test data - a sample news item in English.', 16],
            ['ការបញ្ចុះតម្លៃពិសេសសម្រាប់វគ្គ Advanced Excel', 'Test data - a sample promotion post.', 23],
        ];
        foreach ($items as $i => [$title, $excerpt, $daysAgo]) {
            $this->keep(Article::create([
                'type' => 'news',
                'title' => $title,
                'slug' => Slug::unique($title, Article::ofType('news')),
                'excerpt' => $excerpt,
                'body' => '<p><em>Test data.</em> '.e($excerpt).'</p><p>This text is a placeholder so you can see how news looks on the website. Delete it or run <code>php artisan demo:clear</code>.</p>',
                'image' => $images[$i % max(count($images), 1)] ?? null,
                'published_at' => now()->subDays($daysAgo),
                'is_published' => true,
            ]));
        }
    }

    /** Demo students with orders, progress, test results and a certificate. */
    private function students(Course $free): void
    {
        $basic = Course::where('slug', 'basic-excel')->first();
        $advanced = Course::where('slug', 'advanced-excel')->first();
        $expert = Course::where('slug', 'excel-expert')->first();

        $dara = $this->student('Sok Dara', 'dara@example.com', '012 345 678', 21);
        $srey = $this->student('Chan Sreyneang', 'sreyneang@example.com', '015 222 333', 14);
        $visal = $this->student('Kim Visal', 'visal@example.com', null, 6);
        $sokha = $this->student('Mean Sokha', 'sokha@example.com', '097 111 222', 2);

        if ($basic) {
            $this->order($dara, $basic, $basic->plans->first(), 'paid', 20);
            $this->progress($dara, $basic, 0.6);
        }
        if ($advanced) {
            $plan = $advanced->plans->firstWhere('days', 180) ?? $advanced->plans->first();
            $this->order($srey, $advanced, $plan, 'paid', 12);
            $this->progress($srey, $advanced, 0.4);
        }
        // Visal finished the free course: all lessons, quizzes passed, certificate.
        Enrollment::grant($visal, $free, null, 'free');
        $this->progress($visal, $free, 1.0);
        CourseProgress::for($visal, $free)->recordCompletion();

        if ($expert) {
            $this->order($sokha, $expert, $expert->plans->first(), 'pending', 1);
            $this->order($sokha, $expert, $expert->plans->first(), 'failed', 3);
        }
        if ($basic) {
            $this->order($visal, $basic, $basic->plans->first(), 'paid', 4);
        }
    }

    private function inbox(): void
    {
        $messages = [
            ['Sok Dara', 'dara@example.com', 'Question about the Basic Excel course', 'Test data - Hello, can I study on my phone?', 5, true],
            ['Lim Pisey', 'pisey@example.com', 'Training for our company', 'Test data - We are 12 staff and would like on-site training.', 2, false],
            ['Heng Rithy', 'rithy@example.com', 'Certificate', 'Test data - Is the certificate recognised by employers?', 0, false],
        ];
        foreach ($messages as [$name, $email, $subject, $message, $daysAgo, $read]) {
            $inquiry = $this->keep(Inquiry::create(compact('name', 'email', 'subject', 'message') + ['read_at' => $read ? now() : null]));
            $inquiry->forceFill(['created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo)])->save();
        }

        foreach (['news.reader1@example.com', 'news.reader2@example.com', 'excel.fan@example.com', 'office.team@example.com', 'student.kh@example.com'] as $i => $email) {
            $subscriber = $this->keep(Subscriber::create(['email' => $email]));
            $subscriber->forceFill(['created_at' => now()->subDays($i * 4)])->save();
        }
    }

    // ------------------------------------------------------------- helpers

    protected function keep($model)
    {
        $this->made[$model::class][] = $model->id;

        return $model;
    }

    protected function module(Course $course, int $position, string $title, string $description): CourseModule
    {
        return $this->keep($course->modules()->create(compact('title', 'description', 'position')));
    }

    protected function lesson(Course $course, CourseModule $module, int $position, string $title, string $duration, bool $preview = false): CourseLesson
    {
        return $this->keep($course->lessons()->create([
            'course_module_id' => $module->id,
            'title' => $title,
            'slug' => Slug::unique($title, CourseLesson::where('course_id', $course->id)),
            'position' => $position,
            'duration' => $duration,
            'body' => '<p><em>Test data.</em> Lesson notes for “'.e($title).'” go here: steps, screenshots and formulas.</p>',
            'is_preview' => $preview,
            'is_published' => true,
        ]));
    }

    protected function quiz(Course $course, CourseModule $module, int $position, string $type, string $title, array $questions): Quiz
    {
        $quiz = $this->keep($course->quizzes()->create([
            'course_module_id' => $module->id, 'type' => $type, 'title' => $title,
            'slug' => Slug::unique($title, Quiz::where('course_id', $course->id)),
            'description' => 'Test data quiz.', 'position' => $position, 'pass_percent' => 60, 'show_answers' => true, 'is_published' => true,
        ]));
        foreach ($questions as $i => [$prompt, $answers]) {
            $question = $quiz->questions()->create(['type' => 'single', 'prompt' => $prompt, 'points' => 1, 'position' => $i + 1]);
            $n = 0;
            foreach ($answers as $text => $correct) {
                $question->options()->create(['text' => (string) $text, 'is_correct' => $correct, 'position' => ++$n]);
            }
        }

        return $quiz;
    }

    protected function student(string $name, string $email, ?string $phone, int $daysAgo): User
    {
        $user = new User(['name' => $name, 'email' => $email, 'phone' => $phone, 'password' => 'demo1234']);
        $user->role = 'student';
        $user->save();
        $user->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        return $this->keep($user);
    }

    protected function order(User $user, Course $course, ?CoursePlan $plan, string $status, int $daysAgo): Order
    {
        $when = now()->subDays($daysAgo)->setTime(rand(8, 20), rand(0, 59));
        if ($when->isFuture()) { // "today" at an hour that has not come yet
            $when = now()->subMinutes(rand(5, 90));
        }
        $order = $this->keep(Order::create([
            'number' => Order::newNumber(),
            'user_id' => $user->id, 'course_id' => $course->id, 'plan_id' => $plan?->id, 'plan_label' => $plan?->periodLabel(),
            'course_title' => $course->title,
            'amount' => $plan?->currentPrice() ?? 0, 'original_amount' => $plan?->price, 'discount_percent' => $plan?->discountActive() ? $plan->discount_percent : 0,
            'currency' => $course->currency ?: 'USD', 'access_days' => $plan?->days,
            'payment_option' => $status === 'failed' ? 'cards' : 'abapay_khqr', 'gateway' => 'test',
            'status' => $status, 'gateway_status' => ['paid' => 'APPROVED', 'pending' => 'PENDING', 'failed' => 'DECLINED'][$status] ?? null,
            'apv' => $status === 'paid' ? 'TEST'.rand(1000, 9999) : null,
            'buyer_name' => $user->name, 'buyer_email' => $user->email, 'buyer_phone' => $user->phone,
            'paid_at' => $status === 'paid' ? $when : null,
        ]));
        $order->forceFill(['created_at' => $when, 'updated_at' => $when])->save();

        if ($status === 'paid') {
            $enrollment = Enrollment::grant($user, $course, $plan?->days, 'payment', $order);
            $enrollment->forceFill(['starts_at' => $when, 'expires_at' => $plan?->days ? $when->copy()->addDays($plan->days) : null])->save();
        }

        return $order;
    }

    /** Mark a share of the lessons done and pass the quizzes when finished. */
    protected function progress(User $user, Course $course, float $share): void
    {
        $lessons = $course->lessons()->where('is_published', true)->get();
        foreach ($lessons->take((int) ceil($lessons->count() * $share)) as $lesson) {
            DB::table('lesson_progress')->insertOrIgnore(['user_id' => $user->id, 'course_lesson_id' => $lesson->id, 'completed_at' => now()->subDays(rand(0, 10))]);
        }
        foreach ($course->quizzes()->with('questions.options')->get() as $quiz) {
            if ($share < 1.0 && $quiz->type !== 'practice' && rand(0, 1)) {
                continue;
            }
            $this->takeQuiz($user, $quiz);
        }
    }

    /** One submitted attempt with every answer right. */
    protected function takeQuiz(User $user, Quiz $quiz): void
    {
        $answers = $quiz->questions->mapWithKeys(fn ($q) => [$q->id => $q->options->where('is_correct', true)->pluck('id')->all()])->all();
        $attempt = $quiz->attempts()->create(['user_id' => $user->id, 'question_order' => array_keys($answers), 'started_at' => now()->subMinutes(rand(8, 25))]);
        $attempt->grade($answers);
    }
}
