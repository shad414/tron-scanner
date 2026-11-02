<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scan extends Model
{
    protected $fillable = ['address', 'risk_level', 'usdt_balance', 'taint_percent', 'exchanges', 'result', 'ip'];

    protected $casts = ['result' => 'array'];
}
