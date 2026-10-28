<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Support\CourseProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** The student course player: course home, lessons, files and certificate. */
class LearnController extends Controller
{
    public function course(Request $request, Course $course)
    {
        $this->requireAccess($request, $course);
        $progress = CourseProgress::for($request->user(), $course);

        return view('learn.course', [
            'course' => $course,
            'outline' => $course->curriculum(),
            'progress' => $progress,
            'enrollment' => $request->user()->enrollments()->where('course_id', $course->id)->first(),
            'next' => $progress->nextItem(),
        ]);
    }

    public function lesson(Request $request, Course $course, CourseLesson $lesson)
    {
        abort_unless($lesson->is_published || $request->user()?->isAdmin(), 404);
        $hasAccess = $request->user()?->canAccessCourse($course) ?? false;
        if (! $hasAccess && ! ($lesson->is_preview && $course->is_published)) {
            return $this->locked($request, $course);
        }

        $progress = $request->user() && $hasAccess ? CourseProgress::for($request->user(), $course) : null;
        [$previous, $next] = $this->neighbours($course, $lesson);

        return view('learn.lesson', compact('course', 'lesson', 'progress', 'previous', 'next', 'hasAccess') + [
            'outline' => $course->curriculum(),
        ]);
    }

    /** Tick a lesson as done (or undo it), then optionally go to the next item. */
    public function complete(Request $request, Course $course, CourseLesson $lesson)
    {
        $this->requireAccess($request, $course);
        $user = $request->user();
        $before = CourseProgress::for($user, $course)->percent;

        $done = DB::table('lesson_progress')->where('user_id', $user->id)->where('course_lesson_id', $lesson->id);
        if ($request->boolean('undo')) {
            $done->delete();
        } elseif (! $done->exists()) {
            DB::table('lesson_progress')->insert(['user_id' => $user->id, 'course_lesson_id' => $lesson->id, 'completed_at' => now()]);
        }

        $progress = CourseProgress::for($user, $course);
        $justCompleted = $progress->complete && ! Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->whereNotNull('completed_at')->exists();
        $progress->recordCompletion();

        // The next page animates the progress bar from the old value and says "Lesson complete".
        if (! $request->boolean('undo')) {
            $request->session()->flash('progress_was', $before);
            $request->session()->flash('lesson_done', $lesson->title);
        }

        if ($justCompleted) {
            return redirect()->route('learn.course', $course->slug)->with('status', __('account.course_completed'));
        }
        $next = (string) $request->input('next');
        if ($next !== '' && (str_starts_with($next, url('/').'/') || (str_starts_with($next, '/') && ! str_starts_with($next, '//')))) {
            return redirect()->to($next); // only pages on this site
        }

        return back();
    }

    /** Uploaded lesson video, practice workbook or solution, streamed only to students with access. */
    public function file(Request $request, Course $course, CourseLesson $lesson, string $kind)
    {
        [$path, $name] = match ($kind) {
            'video' => [$lesson->video_path, null],
            'practice' => [$lesson->attachment_path, $lesson->attachment_name],
            'solution' => [$lesson->solution_path, $lesson->solution_name],
            default => abort(404),
        };
        abort_if(blank($path) || ! Storage::disk('local')->exists($path), 404);
        abort_unless($lesson->is_published || $request->user()?->isAdmin(), 404);

        $allowed = ($request->user()?->canAccessCourse($course) ?? false)
            || ($lesson->is_preview && $course->is_published && $kind !== 'solution');
        abort_unless($allowed, 403);

        return $kind === 'video'
            ? response()->file(Storage::disk('local')->path($path)) // supports seeking (Range requests)
            : Storage::disk('local')->download($path, $name ?: basename($path));
    }

    public function certificate(Request $request, Course $course)
    {
        $enrollment = $request->user()->enrollments()->where('course_id', $course->id)->whereNotNull('certificate_code')->first();
        abort_unless($enrollment, 404);

        return view('learn.certificate', ['course' => $course, 'enrollment' => $enrollment, 'user' => $request->user()]);
    }

    /** Public check that a certificate code is real. */
    public function verifyCertificate(string $code)
    {
        $enrollment = Enrollment::with(['user', 'course'])->where('certificate_code', $code)->firstOrFail();

        return view('learn.certificate', ['course' => $enrollment->course, 'enrollment' => $enrollment, 'user' => $enrollment->user, 'verifying' => true]);
    }

    private function requireAccess(Request $request, Course $course): void
    {
        if (! $request->user()->canAccessCourse($course)) {
            abort(redirect()->route('courses.show', $course->slug)->with('checkout_notice', __('account.need_access')));
        }
    }

    private function locked(Request $request, Course $course)
    {
        return redirect()->route('courses.show', $course->slug)->with('checkout_notice', __('account.need_access'));
    }

    /** Lessons and quizzes in course order. */
    private function flatItems(Course $course)
    {
        return $course->curriculum()->flatMap(fn ($section) => $section['items'])->values();
    }

    private function neighbours(Course $course, CourseLesson|Quiz $current): array
    {
        $items = $this->flatItems($course);
        $index = $items->search(fn ($item) => $item::class === $current::class && $item->id === $current->id);

        return [
            $index > 0 ? $items[$index - 1] : null,
            $index !== false && $index < $items->count() - 1 ? $items[$index + 1] : null,
        ];
    }
}
