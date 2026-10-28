<?php

namespace App\Support;

use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Copies a course with its whole curriculum as a hidden draft. Students and orders are not copied. */
class CourseDuplicator
{
    public function duplicate(Course $source): Course
    {
        return DB::transaction(function () use ($source) {
            $copy = $source->replicate(['wp_id']);
            $copy->title = $source->title.' (copy)';
            $copy->slug = Slug::unique($copy->title, Course::query());
            $copy->is_published = false;
            $copy->image = $this->copyPublic($source->image);
            $copy->icon = $this->copyPublic($source->icon);
            $copy->save();

            $moduleMap = [];
            foreach ($source->modules as $module) {
                $new = $module->replicate();
                $new->course_id = $copy->id;
                $new->save();
                $moduleMap[$module->id] = $new->id;
            }

            foreach ($source->lessons as $lesson) {
                $new = $lesson->replicate();
                $new->course_id = $copy->id;
                $new->course_module_id = $moduleMap[$lesson->course_module_id] ?? null;
                foreach (['video_path', 'attachment_path', 'solution_path'] as $column) {
                    $new->{$column} = $this->copyPrivate($lesson->{$column}, $copy->id);
                }
                $new->save();
            }

            foreach ($source->quizzes()->with('questions.options')->get() as $quiz) {
                $newQuiz = $quiz->replicate(['questions_count']);
                $newQuiz->course_id = $copy->id;
                $newQuiz->course_module_id = $moduleMap[$quiz->course_module_id] ?? null;
                $newQuiz->save();

                foreach ($quiz->questions as $question) {
                    $newQuestion = $question->replicate();
                    $newQuestion->quiz_id = $newQuiz->id;
                    $newQuestion->image = $this->copyPublic($question->image);
                    $newQuestion->save();
                    foreach ($question->options as $option) {
                        $newQuestion->options()->create($option->only('text', 'is_correct', 'position'));
                    }
                }
            }

            return $copy;
        });
    }

    /** Give the copy its own file so deleting one course never breaks the other. */
    private function copyPublic(?string $path): ?string
    {
        return $this->copy('public', $path, dirname((string) $path));
    }

    private function copyPrivate(?string $path, int $courseId): ?string
    {
        return $this->copy('local', $path, 'lessons/'.$courseId);
    }

    private function copy(string $disk, ?string $path, string $folder): ?string
    {
        if (blank($path) || Str::startsWith($path, ['http://', 'https://', '/']) || ! Storage::disk($disk)->exists($path)) {
            return $path;
        }

        $target = trim($folder, '/').'/'.pathinfo($path, PATHINFO_FILENAME).'-'.Str::lower(Str::random(5)).'.'.pathinfo($path, PATHINFO_EXTENSION);
        Storage::disk($disk)->copy($path, $target);

        return $target;
    }
}
