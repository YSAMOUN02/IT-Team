<?php

namespace App\Support;

use App\Models\Course;

/**
 * "Is this course ready?" checklist for the admin: each step says whether it
 * is done and links to where it is fixed. Optional steps are recommendations.
 */
class CourseSetup
{
    /** @var list<array{label: string, done: bool, url: string, hint: string, optional: bool}> */
    public array $steps = [];

    public int $percent = 0;

    public function __construct(public Course $course)
    {
        $edit = route('admin.courses.edit', $course);
        $curriculum = route('admin.courses.curriculum', $course);

        $lessons = $course->lessons()->where('is_published', true)->get();
        $quizzes = $course->quizzes()->withCount('questions')->get();
        $empty = $quizzes->where('questions_count', 0);
        $thinLessons = $lessons->filter(fn ($l) => ! $l->hasVideo() && blank(strip_tags((string) $l->body)));
        $final = $quizzes->firstWhere('type', 'final');

        $this->add('Title and short summary', filled($course->summary), $edit.'#f-summary', 'Write one or two sentences for course cards and Google.');
        $this->add('Cover image', filled($course->image), $edit, 'Shown on the course page and when shared.');
        $this->add('What the course covers', ! empty($course->features), $edit.'#f-features', 'The checklist next to the price.');
        $this->add('Price for online sale (or free)', $course->isFree() || $course->plans()->exists(), $edit.'#pricing', 'Add at least one price plan, or switch the course to free.');
        $this->add('Course duration', filled($course->duration), $edit.'#f-duration', 'E.g. “12 hours” - shown on the course card.', optional: true);
        $this->add('Modules', $course->modules()->exists(), $curriculum, 'Split the course into chapters.');
        $this->add('Lessons', $lessons->isNotEmpty(), $curriculum, $lessons->count().' visible lessons.');
        $this->add('Every lesson has a video or notes', $lessons->isNotEmpty() && $thinLessons->isEmpty(), $thinLessons->isNotEmpty() ? route('admin.lessons.edit', $thinLessons->first()) : $curriculum,
            $thinLessons->isNotEmpty() ? $thinLessons->count().' lesson(s) are still empty, e.g. “'.$thinLessons->first()->title.'”.' : 'All lessons have content.');
        $this->add('Practice or quizzes', $quizzes->whereIn('type', ['practice', 'quiz'])->isNotEmpty(), $curriculum, 'Let students check what they learned.');
        $this->add('Every test has questions', $quizzes->isNotEmpty() && $empty->isEmpty(), $empty->isNotEmpty() ? route('admin.quizzes.edit', $empty->first()) : $curriculum,
            $empty->isNotEmpty() ? '“'.$empty->first()->title.'” has no questions yet.' : 'No empty tests.');
        if ($course->certificate_enabled ?? true) { // on by default
            $this->add('Final exam for the certificate', $final && $final->questions_count > 0, $final ? route('admin.quizzes.edit', $final) : route('admin.courses.quizzes.create', [$course, 'type' => 'final']),
                'The certificate needs a passed final exam.');
        }
        $this->add('A free preview lesson', $course->lessons()->where('is_preview', true)->exists(), $curriculum, 'Lets visitors try before buying.', optional: true);
        $this->add('Visible on the website', (bool) $course->is_published, $edit, 'Turn on “Visible on website”.');

        $required = array_filter($this->steps, fn ($s) => ! $s['optional']);
        $this->percent = $required ? (int) round(count(array_filter($required, fn ($s) => $s['done'])) * 100 / count($required)) : 100;
    }

    public static function for(Course $course): self
    {
        return new self($course);
    }

    public function missing(): array
    {
        return array_values(array_filter($this->steps, fn ($s) => ! $s['done']));
    }

    private function add(string $label, bool $done, string $url, string $hint, bool $optional = false): void
    {
        $this->steps[] = compact('label', 'done', 'url', 'hint', 'optional');
    }
}
