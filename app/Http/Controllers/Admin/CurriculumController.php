<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Quiz;
use App\Support\Slug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Course builder: modules and the order of lessons and quizzes inside them. */
class CurriculumController extends Controller
{
    public function show(Course $course)
    {
        return view('admin.curriculum.show', [
            'course' => $course,
            'outline' => $course->curriculum(publishedOnly: false),
            'stats' => [
                'modules' => $course->modules()->count(),
                'lessons' => $course->lessons()->count(),
                'quizzes' => $course->quizzes()->count(),
                'students' => $course->enrollments()->count(),
            ],
        ]);
    }

    public function storeModule(Request $request, Course $course)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $course->modules()->create($data + ['position' => (int) $course->modules()->max('position') + 1]);

        return back()->with('status', 'Module added.');
    }

    public function updateModule(Request $request, CourseModule $module)
    {
        $module->update($request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('status', 'Module saved.');
    }

    public function destroyModule(CourseModule $module)
    {
        // Lessons and quizzes stay in the course, just without a module.
        $module->delete();

        return back()->with('status', 'Module deleted. Its lessons and quizzes are now under "Not in a module".');
    }

    /**
     * Save a drag-and-drop arrangement. Body:
     *   modules: [moduleId, …] in order
     *   items:   {moduleId|"none": ["lesson:12", "quiz:3", …]} in order
     * Anything that does not belong to this course is ignored.
     */
    public function reorder(Request $request, Course $course)
    {
        $data = $request->validate([
            'modules' => ['array'],
            'modules.*' => ['integer'],
            'items' => ['array'],
            'items.*' => ['array'],
            'items.*.*' => ['string', 'regex:/^(lesson|quiz):\d+$/'],
        ]);

        $moduleIds = $course->modules()->pluck('id')->all();
        $lessonIds = $course->lessons()->pluck('id')->all();
        $quizIds = $course->quizzes()->pluck('id')->all();

        DB::transaction(function () use ($data, $moduleIds, $lessonIds, $quizIds) {
            foreach (array_values(array_intersect($data['modules'] ?? [], $moduleIds)) as $position => $id) {
                CourseModule::whereKey($id)->update(['position' => $position + 1]);
            }
            foreach ($data['items'] ?? [] as $module => $keys) {
                $moduleId = $module === 'none' ? null : (int) $module;
                if ($moduleId !== null && ! in_array($moduleId, $moduleIds, true)) {
                    continue;
                }
                foreach (array_values($keys) as $position => $key) {
                    [$type, $id] = explode(':', $key);
                    $id = (int) $id;
                    $values = ['course_module_id' => $moduleId, 'position' => $position + 1];
                    if ($type === 'lesson' && in_array($id, $lessonIds, true)) {
                        CourseLesson::whereKey($id)->update($values);
                    } elseif ($type === 'quiz' && in_array($id, $quizIds, true)) {
                        Quiz::whereKey($id)->update($values);
                    }
                }
            }
        });

        return response()->json(['saved' => true]);
    }

    /** Add lessons by title (one per line) to a module; they start hidden until filled in. */
    public function quickAdd(Request $request, Course $course)
    {
        $data = $request->validate([
            'titles' => ['required', 'string', 'max:5000'],
            'module' => ['nullable', Rule::exists('course_modules', 'id')->where('course_id', $course->id)],
        ]);

        $titles = array_values(array_filter(array_map('trim', preg_split('/\R/u', $data['titles']))));
        $moduleId = $data['module'] ?? null;
        $position = max(
            (int) $course->lessons()->where('course_module_id', $moduleId)->max('position'),
            (int) $course->quizzes()->where('course_module_id', $moduleId)->max('position'),
        );

        foreach ($titles as $title) {
            $course->lessons()->create([
                'course_module_id' => $moduleId,
                'title' => mb_substr($title, 0, 190),
                'slug' => Slug::unique($title, CourseLesson::where('course_id', $course->id)),
                'position' => ++$position,
                'is_published' => false,
            ]);
        }

        return back()->with('status', count($titles).' lesson(s) added as hidden drafts. Open each one to add the video and notes, then make it visible.');
    }

    /** Show / hide a lesson or quiz without opening it. */
    public function toggle(Request $request)
    {
        $data = $request->validate(['type' => ['required', Rule::in(['lesson', 'quiz'])], 'id' => ['required', 'integer']]);
        $item = $data['type'] === 'lesson' ? CourseLesson::findOrFail($data['id']) : Quiz::findOrFail($data['id']);
        $item->update(['is_published' => ! $item->is_published]);

        return back()->with('status', '“'.$item->title.'” is now '.($item->is_published ? 'visible to students.' : 'hidden.'));
    }

    /** Move a module, lesson or quiz one place up or down. */
    public function move(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['module', 'lesson', 'quiz'])],
            'id' => ['required', 'integer'],
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        if ($data['type'] === 'module') {
            $module = CourseModule::findOrFail($data['id']);
            $siblings = $module->course->modules()->get()->values();
            $this->swap($siblings, $siblings->search(fn ($m) => $m->id === $module->id), $data['direction']);
        } else {
            $item = $data['type'] === 'lesson' ? CourseLesson::findOrFail($data['id']) : Quiz::findOrFail($data['id']);
            $group = $item->course->curriculum(publishedOnly: false)
                ->first(fn ($section) => ($section['module']?->id) === $item->course_module_id)['items'] ?? collect();
            $this->swap($group, $group->search(fn ($i) => $i::class === $item::class && $i->id === $item->id), $data['direction']);
        }

        return back();
    }

    /** Swap the item with its neighbour, then renumber the group 1..n. */
    private function swap($items, int|false $index, string $direction): void
    {
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($index === false || $target < 0 || $target >= $items->count()) {
            return;
        }

        $list = $items->all();
        [$list[$index], $list[$target]] = [$list[$target], $list[$index]];
        foreach (array_values($list) as $position => $model) {
            $model->forceFill(['position' => $position + 1])->saveQuietly();
        }
    }
}
