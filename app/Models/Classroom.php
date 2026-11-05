<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A class: students taking one or more courses together (Admin > Classes), with a teacher, dates
 * and a schedule. It only groups them for following up; course access is given as usual.
 */
class Classroom extends Model
{
    public const STATUSES = ['upcoming' => 'Starts soon', 'running' => 'Running', 'finished' => 'Finished'];

    protected $guarded = ['id'];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** The class's courses, in the order it takes them. */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'classroom_course')->withPivot('position')
            ->orderBy('classroom_course.position')->orderBy('courses.id');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'classroom_student')->withTimestamps()->orderBy('name');
    }

    /**
     * For lists of students: user id => the classes each is in (optionally only classes of one
     * course), so pages can show "Class: Saturday morning" next to a name.
     *
     * @return \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<int, self>>
     */
    public static function forStudents(iterable $userIds, ?int $courseId = null): \Illuminate\Support\Collection
    {
        $links = \Illuminate\Support\Facades\DB::table('classroom_student')->whereIn('user_id', collect($userIds)->unique()->values())->get(['classroom_id', 'user_id']);
        $classes = self::whereIn('id', $links->pluck('classroom_id')->unique())
            ->when($courseId, fn ($q) => $q->whereHas('courses', fn ($c) => $c->whereKey($courseId)))
            ->orderBy('name')->get(['id', 'name', 'starts_on', 'ends_on'])->keyBy('id');

        return $links->filter(fn ($link) => isset($classes[$link->classroom_id]))
            ->groupBy('user_id')
            ->map(fn ($group) => $group->map(fn ($link) => $classes[$link->classroom_id])->sortBy('name')->values());
    }

    /** Ids of the students in a class, for filtering other pages by class. */
    public static function studentIds(int $classroomId): array
    {
        return \Illuminate\Support\Facades\DB::table('classroom_student')->where('classroom_id', $classroomId)->pluck('user_id')->all();
    }

    /** Set the courses, keeping the order given. */
    public function syncCourses(array $courseIds): void
    {
        $this->courses()->sync(collect(array_values(array_unique(array_map('intval', $courseIds))))
            ->mapWithKeys(fn ($id, $i) => [$id => ['position' => $i]])->all());
    }

    /** Starts soon / running / finished, from its dates (no dates: running). */
    public function status(): string
    {
        return match (true) {
            $this->starts_on && $this->starts_on->isAfter(today()) => 'upcoming',
            $this->ends_on && $this->ends_on->isBefore(today()) => 'finished',
            default => 'running',
        };
    }

    /** "3 Nov – 15 Dec 2026", "From 3 Nov 2026", or null. */
    public function dates(): ?string
    {
        return match (true) {
            $this->starts_on && $this->ends_on => $this->starts_on->format($this->starts_on->year === $this->ends_on->year ? 'j M' : 'j M Y').' – '.$this->ends_on->format('j M Y'),
            (bool) $this->starts_on => 'From '.$this->starts_on->format('j M Y'),
            (bool) $this->ends_on => 'Until '.$this->ends_on->format('j M Y'),
            default => null,
        };
    }
}
