<?php

namespace App\Models\Concerns;

use App\Support\Media;

trait HasMediaUrls
{
    /** Public URL for an image column (stored as a path on the public disk). */
    public function mediaUrl(string $column = 'image'): ?string
    {
        return Media::url($this->{$column});
    }
}
