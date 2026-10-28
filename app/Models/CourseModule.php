<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A chapter of a course ("Module 1: ..."), holding lessons and quizzes. */
class CourseModule extends Model
{
    protected $guarded = ['id'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class)->orderBy('position')->orderBy('id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class)->orderBy('position')->orderBy('id');
    }
}
