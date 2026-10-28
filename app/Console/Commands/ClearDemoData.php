<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Removes everything DemoDataSeeder made and puts back what it changed. */
class ClearDemoData extends Command
{
    protected $signature = 'demo:clear {--force : Do not ask for confirmation}';

    protected $description = 'Remove the test data added by DemoDataSeeder';

    public function handle(): int
    {
        $records = json_decode((string) setting('demo_records'), true);
        if (! $records) {
            $this->info('No test data to remove.');

            return self::SUCCESS;
        }

        $count = array_sum(array_map('count', $records['made'] ?? []));
        if (! $this->option('force') && ! $this->confirm("Remove {$count} test records (demo students, orders, news, sample lessons, free course…)?", true)) {
            return self::FAILURE;
        }

        DB::transaction(function () use ($records) {
            // Children first; students take their orders, enrollments and progress with them.
            $order = [
                \App\Models\Order::class, \App\Models\User::class, \App\Models\Inquiry::class, \App\Models\Subscriber::class,
                \App\Models\Article::class, \App\Models\Quiz::class, CourseLesson::class, \App\Models\CourseModule::class,
                \App\Models\CoursePlan::class, Course::class,
            ];
            foreach ($order as $class) {
                $ids = $records['made'][$class] ?? [];
                if (! $ids) {
                    continue;
                }
                if ($class === CourseLesson::class) {
                    foreach (CourseLesson::whereKey($ids)->get() as $lesson) {
                        Storage::disk('local')->delete(array_filter([$lesson->video_path, $lesson->attachment_path, $lesson->solution_path]));
                    }
                }
                $class::whereKey($ids)->delete();
            }

            foreach ($records['changed']['course_duration'] ?? [] as $id => $previous) {
                Course::whereKey($id)->update(['duration' => $previous]);
            }

            Setting::put(['demo_records' => null]);
        });

        $this->info('Test data removed.');

        return self::SUCCESS;
    }
}
