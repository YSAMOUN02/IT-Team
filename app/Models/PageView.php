<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One page opened on the public site (see App\Support\Visitor and TrackController). */
class PageView extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'seconds' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** "Sok Dara" for a signed-in student, otherwise "Visitor 3F9A2C". */
    public function visitorLabel(): string
    {
        return $this->user?->name ?? 'Visitor '.strtoupper(substr($this->visitor_id, 0, 6));
    }
}
