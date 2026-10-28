<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Support\Slug;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Lessons of a course. Videos, practice workbooks and solutions are kept on
 * the private disk and streamed only to students who have access.
 */
class LessonController extends Controller
{
    private const FILES = [
        'video' => ['video_path', null],
        'practice' => ['attachment_path', 'attachment_name'],
        'solution' => ['solution_path', 'solution_name'],
    ];

    public function create(Request $request, Course $course)
    {
        return view('admin.lessons.form', [
            'course' => $course,
            'lesson' => new CourseLesson([
                'course_module_id' => $request->integer('module') ?: null,
                'is_published' => true,
            ]),
        ]);
    }

    public function store(Request $request, Course $course)
    {
        $lesson = new CourseLesson(['course_id' => $course->id]);

        return $this->save($request, $course, $lesson);
    }

    public function edit(CourseLesson $lesson)
    {
        return view('admin.lessons.form', ['course' => $lesson->course, 'lesson' => $lesson]);
    }

    public function update(Request $request, CourseLesson $lesson)
    {
        return $this->save($request, $lesson->course, $lesson);
    }

    public function destroy(CourseLesson $lesson)
    {
        foreach (self::FILES as [$pathColumn]) {
            $this->deleteFile($lesson->{$pathColumn});
        }
        $course = $lesson->course;
        $lesson->delete();

        return redirect()->route('admin.courses.curriculum', $course)->with('status', 'Lesson deleted.');
    }

    private function save(Request $request, Course $course, CourseLesson $lesson)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190'],
            'course_module_id' => ['nullable', Rule::exists('course_modules', 'id')->where('course_id', $course->id)],
            'duration' => ['nullable', 'string', 'max:20'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'body' => ['nullable', 'string'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:1048576'],
            'practice' => ['nullable', 'file', 'mimes:xlsx,xls,xlsm,csv,pdf,docx,pptx,zip', 'max:51200'],
            'solution' => ['nullable', 'file', 'mimes:xlsx,xls,xlsm,csv,pdf,docx,pptx,zip', 'max:51200'],
        ]);

        $moduleChanged = $lesson->exists && $lesson->course_module_id != ($data['course_module_id'] ?? null);
        $lesson->fill([
            'title' => $data['title'],
            'course_module_id' => $data['course_module_id'] ?? null,
            'duration' => $data['duration'] ?? null,
            'video_url' => $data['video_url'] ?? null,
            'body' => $data['body'] ?? null,
            'is_preview' => $request->boolean('is_preview'),
            'is_published' => $request->boolean('is_published'),
        ]);
        $lesson->slug = Slug::unique(($data['slug'] ?? null) ?: $data['title'], CourseLesson::where('course_id', $course->id), $lesson->id);
        if (! $lesson->exists || $moduleChanged) {
            $lesson->position = $this->nextPosition($course, $lesson->course_module_id);
        }

        foreach (self::FILES as $input => [$pathColumn, $nameColumn]) {
            if ($request->hasFile($input)) {
                $this->deleteFile($lesson->getOriginal($pathColumn));
                $lesson->{$pathColumn} = $this->storeFile($request->file($input), $course);
                if ($nameColumn) {
                    $lesson->{$nameColumn} = $request->file($input)->getClientOriginalName();
                }
            } elseif ($request->boolean('remove_'.$input)) {
                $this->deleteFile($lesson->getOriginal($pathColumn));
                $lesson->{$pathColumn} = null;
                if ($nameColumn) {
                    $lesson->{$nameColumn} = null;
                }
            }
        }

        $lesson->save();

        return redirect()->route('admin.lessons.edit', $lesson)->with('status', 'Lesson saved.');
    }

    private function nextPosition(Course $course, ?int $moduleId): int
    {
        $max = max(
            (int) $course->lessons()->where('course_module_id', $moduleId)->max('position'),
            (int) $course->quizzes()->where('course_module_id', $moduleId)->max('position'),
        );

        return $max + 1;
    }

    private function storeFile(UploadedFile $file, Course $course): string
    {
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'file';

        return $file->storeAs('lessons/'.$course->id, $name.'-'.Str::lower(Str::random(6)).'.'.strtolower($file->getClientOriginalExtension()), 'local');
    }

    private function deleteFile(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('local')->delete($path);
        }
    }
}
