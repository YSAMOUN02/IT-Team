<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\CourseDuplicator;
use App\Support\CourseProgress;
use App\Support\Media;
use App\Support\Slug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    use HandlesImageUploads;

    public function index()
    {
        return view('admin.courses.index', [
            'courses' => Course::with('plans')->withCount(['modules', 'lessons', 'quizzes', 'enrollments'])->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.courses.form', ['course' => new Course(['is_published' => true, 'currency' => 'USD', 'certificate_enabled' => true])]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new Course);
    }

    public function edit(Course $course)
    {
        return view('admin.courses.form', compact('course'));
    }

    public function update(Request $request, Course $course)
    {
        return $this->save($request, $course);
    }

    /** Copy the course and its curriculum as a hidden draft. */
    public function duplicate(Course $course, CourseDuplicator $duplicator)
    {
        $copy = $duplicator->duplicate($course);

        return redirect()->route('admin.courses.edit', $copy)->with('status', 'Course copied as a hidden draft. Rename it and adjust what you need.');
    }

    /** Students of one course, with progress; access can be given by email here too. */
    public function students(Course $course)
    {
        return view('admin.courses.students', [
            'course' => $course,
            'rows' => $course->enrollments()->with('user')->latest()->get()
                ->filter(fn ($e) => $e->user)
                ->map(fn ($e) => ['enrollment' => $e, 'progress' => CourseProgress::for($e->user, $course)]),
        ]);
    }

    public function grantByEmail(Request $request, Course $course)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $user = User::whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->first();
        if (! $user) {
            return back()->withErrors(['email' => 'No account uses that email. Ask the student to register on the website first.'])->withInput();
        }
        if ($user->isAdmin()) {
            return back()->withErrors(['email' => 'That email belongs to an admin account. Admins can already open every course.'])->withInput();
        }

        Enrollment::grant($user, $course, $data['days'] ?? $course->plans->first()?->days, 'manual');

        return back()->with('status', "{$user->name} can now open {$course->title}.");
    }

    public function destroy(Course $course)
    {
        Media::delete($course->image);
        Media::delete($course->icon);
        // Lesson videos and practice files live on the private disk.
        foreach ($course->lessons as $lesson) {
            Storage::disk('local')->delete(array_filter([$lesson->video_path, $lesson->attachment_path, $lesson->solution_path]));
        }
        $course->delete();

        return redirect()->route('admin.courses.index')->with('status', 'Course deleted.');
    }

    private function save(Request $request, Course $course)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190'],
            'badge' => ['nullable', 'string', 'max:60'],
            'price_prefix' => ['nullable', 'string', 'max:60'],
            'price' => ['nullable', 'string', 'max:60'],
            'price_suffix' => ['nullable', 'string', 'max:60'],
            'currency' => ['required', 'in:USD,KHR'],
            'duration' => ['nullable', 'string', 'max:60'],
            'plans' => ['array', 'max:12'],
            'plans.*.id' => ['nullable', 'integer'],
            'plans.*.label' => ['nullable', 'string', 'max:60'],
            'plans.*.days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'plans.*.price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'plans.*.discount_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'plans.*.discount_starts_at' => ['nullable', 'date'],
            'plans.*.discount_ends_at' => ['nullable', 'date', 'after_or_equal:plans.*.discount_starts_at'],
            'features' => ['nullable', 'string', 'max:5000'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'body' => ['nullable', 'string'],
            'cta_label' => ['nullable', 'string', 'max:100'],
            'cta_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'image' => $this->imageRule(),
            'icon' => $this->imageRule(),
        ], $this->imageMessages(['image' => 'Cover image', 'icon' => 'Card icon']));
        $plans = $data['plans'] ?? [];
        unset($data['image'], $data['icon'], $data['plans']);
        $data['features'] = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($data['features'] ?? '')))));

        $course->fill($data);
        $course->slug = Slug::unique(($data['slug'] ?? null) ?: $data['title'], Course::query(), $course->id);
        $course->sort_order = (int) ($data['sort_order'] ?? 0);
        $course->is_published = $request->boolean('is_published');
        $course->is_free = $request->boolean('is_free');
        $course->certificate_enabled = $request->boolean('certificate_enabled');
        $this->syncImage($request, $course, 'image', 'courses');
        $this->syncImage($request, $course, 'icon', 'courses');
        $course->save();
        $this->syncPlans($course, $plans);

        return redirect()->route($request->boolean('to_curriculum') ? 'admin.courses.curriculum' : 'admin.courses.edit', $course)->with('status', 'Course saved.');
    }

    /** Keep, add and remove price plans to match the form (rows without a price are dropped). */
    private function syncPlans(Course $course, array $rows): void
    {
        $keep = [];
        $position = 0;

        foreach ($rows as $row) {
            if (! isset($row['price']) || $row['price'] === '' || $row['price'] === null) {
                continue;
            }
            $values = [
                'label' => $row['label'] ?? null,
                'days' => $row['days'] ?? null,
                'price' => $row['price'],
                'discount_percent' => (int) ($row['discount_percent'] ?? 0),
                'discount_starts_at' => $row['discount_starts_at'] ?? null,
                'discount_ends_at' => $row['discount_ends_at'] ?? null,
                'position' => ++$position,
            ];

            $plan = isset($row['id']) ? $course->plans()->find($row['id']) : null;
            $plan ? $plan->update($values) : $plan = $course->plans()->create($values);
            $keep[] = $plan->id;
        }

        // Orders keep their own copy of the price, so removed plans do not change history.
        $course->plans()->whereNotIn('id', $keep)->delete();
    }
}
