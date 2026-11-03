<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A banner that came on screen for a visitor (at most once per page opened). See AdClick for its clicks. */
class AdView extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['placement', 'visitor_id'];
}
