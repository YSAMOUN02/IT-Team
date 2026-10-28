<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLesson extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_preview' => 'boolean',
        'is_published' => 'boolean',
        'position' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function url(): string
    {
        return route('learn.lesson', ['course' => $this->course->slug, 'lesson' => $this->slug]);
    }

    /** Embed URL for a YouTube or Vimeo link, null for anything else. */
    public function embedUrl(): ?string
    {
        $url = (string) $this->video_url;

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1].'?rel=0&modestbranding=1';
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        return null;
    }

    public function hasVideo(): bool
    {
        return filled($this->video_path) || $this->embedUrl() !== null;
    }
}
