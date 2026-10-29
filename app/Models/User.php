<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = [
        'admin' => 'Admin',
        'student' => 'Student',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'device_sessions',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'device_sessions' => 'array',
            'disabled_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** A student account an admin turned off: no signing in, no courses. Admin accounts are never disabled. */
    public function isDisabled(): bool
    {
        return $this->disabled_at !== null && ! $this->isAdmin();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function completedLessons(): BelongsToMany
    {
        return $this->belongsToMany(CourseLesson::class, 'lesson_progress')->withPivot('completed_at');
    }

    /** Admins can open every course; students need an active enrollment and an account that is not turned off. */
    public function canAccessCourse(Course $course): bool
    {
        return $this->isAdmin() || (! $this->isDisabled() && $this->activeEnrollment($course) !== null);
    }

    /** An admin turned off this student's access to the course (paying or enrolling again does not undo it). */
    public function courseAccessOff(Course $course): bool
    {
        return $this->enrollments()->where('course_id', $course->id)->whereNotNull('disabled_at')->exists();
    }

    public function activeEnrollment(Course $course): ?Enrollment
    {
        return $this->enrollments()->where('course_id', $course->id)->active()->first();
    }
}
