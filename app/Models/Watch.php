<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Watch extends Model
{
    protected $fillable = ['address', 'chat_id', 'label', 'last_seen_ms', 'was_blacklisted'];

    protected $casts = ['was_blacklisted' => 'boolean'];
}
