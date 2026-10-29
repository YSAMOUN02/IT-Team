<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\Setting;
use App\Models\User;
use App\Support\CourseProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * More test data for Admin > Students: three extra courses and random students
 * who study one to three courses each. Some access is running, some has ended,
 * a few courses are turned off and one account is turned off.
 *
 * php artisan db:seed --class=RandomStudentsSeeder   (run again for more students)
 *
 * Everything is added to the "demo_records" list, so `php artisan demo:clear`
 * removes it with the rest of the test data. Students sign in with "demo1234".
 */
class RandomStudentsSeeder extends DemoDataSeeder
{
    private const FAMILY = ['Sok', 'Chan', 'Kim', 'Mean', 'Heng', 'Lim', 'Ly', 'Chea', 'Seng', 'Phan', 'Nget', 'Keo', 'Touch', 'Ouk', 'Pich', 'Yim', 'Prak', 'Ros', 'Meas', 'Tep'];

    private const GIVEN = ['Dara', 'Bopha', 'Vanna', 'Sophea', 'Kosal', 'Chanthy', 'Rotha', 'Sreypov', 'Vuthy', 'Monyrath', 'Sovann', 'Leakhena',
        'Piseth', 'Chenda', 'Narith', 'Sothea', 'Malis', 'Ratanak', 'Kunthea', 'Sreymom', 'Davy', 'Panha', 'Rachana', 'Seyha'];

    private const PHONE_PREFIXES = ['010', '011', '012', '015', '016', '017', '069', '070', '077', '078', '085', '086', '089', '092', '093', '096', '097', '098'];

    public function __construct(private int $students = 20) {}

    public function run(): void
    {
        DB::transaction(function () {
            $courses = $this->extraCourses()->merge(Course::published()->with('plans')->get())->unique('id')->values();
            $enrollments = $this->randomStudents($courses);

            // A few courses turned off by an admin, and one whole account.
            foreach ($enrollments->filter->isActive()->shuffle()->take(3) as $enrollment) {
                $enrollment->forceFill(['disabled_at' => now()->subDays(rand(0, 6))])->save();
            }
            $enrollments->pluck('user')->unique('id')->random()->forceFill(['disabled_at' => now()->subDays(rand(1, 10))])->save();
        });

        $records = json_decode((string) setting('demo_records'), true) ?: ['made' => [], 'changed' => []];
        foreach ($this->made as $class => $ids) {
            $records['made'][$class] = array_values(array_merge($records['made'][$class] ?? [], $ids));
        }
        Setting::put(['demo_records' => json_encode($records)]);

        $this->command?->info(count($this->made[User::class] ?? []).' test students added (password: demo1234). Remove all test data with: php artisan demo:clear');
    }

    /** Three test courses (made once; running the seeder again only adds students). */
    private function extraCourses(): Collection
    {
        $images = Course::whereNotNull('image')->pluck('image')->all();
        $made = collect();
        $list = [
            ['Excel Formulas & Functions', 'excel-formulas', '16 hours', [[60, 25, 0], [365, 59, 10]],
                ['Everyday formulas' => ['IF and nested IF', 'COUNTIFS and SUMIFS', 'Text functions'], 'Dates and lookups' => ['Working with dates', 'INDEX and MATCH']]],
            ['Power BI for Beginners', 'power-bi-beginners', '18 hours', [[90, 79, 0], [365, 149, 20]],
                ['Get the data' => ['Install Power BI Desktop', 'Import from Excel'], 'Build a report' => ['Your first visual', 'Slicers and filters', 'Publish and share']]],
            ['Microsoft Word for Office Work', 'word-for-office', '8 hours', [[180, 29, 0], [null, 49, 0]],
                ['Documents' => ['Styles and headings', 'Tables of contents'], 'Letters and forms' => ['Mail merge', 'Fillable forms']]],
        ];

        foreach ($list as $i => [$title, $slug, $duration, $plans, $modules]) {
            if (Course::where('slug', $slug)->exists()) {
                continue;
            }
            $course = $this->keep(Course::create([
                'title' => $title,
                'slug' => $slug,
                'summary' => 'Test data - '.strtolower($title).' in short video lessons with practice files.',
                'duration' => $duration,
                'features' => array_keys($modules),
                'body' => '<p><em>Test data.</em> A sample course so the admin has more students and courses to show.</p>',
                'image' => $images ? $images[$i % count($images)] : null,
                'is_published' => true,
                'certificate_enabled' => true,
                'sort_order' => 90 + $i,
            ]));
            foreach ($plans as $position => [$days, $price, $discount]) {
                $this->keep($course->plans()->create(['days' => $days, 'price' => $price, 'discount_percent' => $discount, 'position' => $position + 1]));
            }
            $m = 0;
            foreach ($modules as $moduleTitle => $lessons) {
                $module = $this->module($course, ++$m, $moduleTitle, 'Test data module.');
                foreach ($lessons as $n => $lessonTitle) {
                    $this->lesson($course, $module, $n + 1, $lessonTitle, rand(5, 14).':00', $m === 1 && $n === 0);
                }
                $this->quiz($course, $module, count($lessons) + 1, 'quiz', $moduleTitle.' quiz', [
                    ['Test question about '.$moduleTitle.'. Pick the first answer.', ['First answer' => true, 'Second answer' => false, 'Third answer' => false]],
                    ['True or false: this course is test data.', ['True' => true, 'False' => false]],
                ]);
            }
            $made->push($course->load('plans'));
        }

        return $made;
    }

    /** @return Collection<int, Enrollment> */
    private function randomStudents(Collection $courses): Collection
    {
        $enrollments = collect();

        for ($i = 0; $i < $this->students; $i++) {
            $given = self::GIVEN[array_rand(self::GIVEN)];
            $family = self::FAMILY[array_rand(self::FAMILY)];
            $base = strtolower($given.'.'.$family);
            $email = "$base@example.com";
            for ($n = 2; User::where('email', $email)->exists(); $n++) {
                $email = "$base$n@example.com";
            }
            $phone = rand(0, 4) ? self::PHONE_PREFIXES[array_rand(self::PHONE_PREFIXES)].' '.rand(100, 999).' '.rand(100, 999) : null;
            $joined = rand(3, 150);
            $user = $this->student("$family $given", $email, $phone, $joined);

            foreach ($courses->random(min(rand(1, 3), $courses->count())) as $course) {
                $enrollment = $this->enrol($user, $course, $joined);
                if ($enrollment) {
                    $shares = [0, 0.1, 0.25, 0.5, 0.5, 0.75, 1.0];
                    $share = $shares[array_rand($shares)];
                    $this->progress($user, $course, $share);
                    if ($share === 1.0) {
                        CourseProgress::for($user, $course)->recordCompletion();
                    }
                    $enrollments->push($enrollment->setRelation('user', $user));
                }
            }
        }

        return $enrollments;
    }

    /**
     * Unlike the demo students, these make mistakes: each gets questions right by their own
     * "skill", tries again after failing (up to 3 tries, a little better each time), and now
     * and then leaves a test unsubmitted.
     */
    protected function takeQuiz(User $user, Quiz $quiz): void
    {
        $skill = rand(45, 100) / 100;
        $start = now()->subDays(rand(2, 25))->setTime(rand(7, 21), rand(0, 59));

        for ($try = 1; $try <= 3; $try++) {
            $answers = $quiz->questions->mapWithKeys(fn ($q) => [$q->id => mt_rand() / mt_getrandmax() < $skill
                ? $q->options->where('is_correct', true)->pluck('id')->all()
                : $q->options->where('is_correct', false)->shuffle()->take(1)->pluck('id')->all()])->all();
            $attempt = $quiz->attempts()->create(['user_id' => $user->id, 'question_order' => collect(array_keys($answers))->shuffle()->all(), 'started_at' => $start]);
            $attempt->grade($answers);
            $took = rand(20, 75) * max(1, count($answers)) * ($quiz->time_limit ? 2 : 1);
            $attempt->forceFill(['submitted_at' => $start->copy()->addSeconds(min($took, ($quiz->time_limit ?: 60) * 60 - 5))])->save();

            if ($attempt->passed || $quiz->isPractice()) {
                break;
            }
            $skill = min(1, $skill + 0.2);
            $start = $start->copy()->addDays(rand(0, 3))->addHours(rand(1, 8));
        }

        if (rand(1, 25) === 1) {
            $quiz->attempts()->create(['user_id' => $user->id, 'question_order' => $quiz->questions->pluck('id')->all(), 'started_at' => now()->subMinutes(rand(3, 4000))]);
        }
    }

    /** Free sign-up, a paid order (running or ended), cash at the office, or an unpaid order (no access). */
    private function enrol(User $user, Course $course, int $joined): ?Enrollment
    {
        if ($course->isFree()) {
            return Enrollment::grant($user, $course, null, 'free');
        }
        $plan = $course->plans->random();
        $roll = rand(1, 100);

        if ($roll <= 10) {
            $this->order($user, $course, $plan, rand(0, 1) ? 'pending' : 'failed', rand(0, min($joined, 20)));

            return null;
        }
        if ($roll <= 22) {
            $enrollment = Enrollment::grant($user, $course, $plan->days, 'manual');
            $start = now()->subDays(rand(0, $joined));

            $enrollment->forceFill(['starts_at' => $start, 'expires_at' => $plan->days ? $start->copy()->addDays($plan->days) : null])->save();

            return $enrollment;
        }
        // Paid: mostly still running; about one in five bought so long ago that it has ended
        // (the student then joined before that purchase).
        if ($plan->days && $roll > 82) {
            $daysAgo = $plan->days + rand(3, 60);
            if ($daysAgo > $user->created_at->diffInDays(now())) {
                $user->forceFill(['created_at' => now()->subDays($daysAgo + rand(1, 10))])->save();
            }
        } else {
            $daysAgo = rand(0, $plan->days ? min($joined, $plan->days - 1) : $joined);
        }
        $this->order($user, $course, $plan, 'paid', $daysAgo);

        return $user->enrollments()->where('course_id', $course->id)->first();
    }
}
