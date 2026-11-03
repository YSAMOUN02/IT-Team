<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inquiry extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'read_at' => 'datetime',
        'replied_at' => 'datetime',
    ];

    /** Email replies sent from the admin, oldest first (a conversation). */
    public function replies(): HasMany
    {
        return $this->hasMany(InquiryReply::class)->oldest()->oldest('id');
    }
}
