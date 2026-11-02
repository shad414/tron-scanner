<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlaggedAddress extends Model
{
    protected $fillable = ['address', 'source', 'note', 'flagged_at'];

    protected $casts = ['flagged_at' => 'datetime'];

    public const SOURCES = [
        'tether' => 'بلاک‌لیستی Tether (USDT فریز کراوە)',
        'ofac' => 'سزای OFAC (ئەمریکا)',
        'manual' => 'لیستی خۆت',
    ];

    /**
     * address => [sources…] for the given addresses, in one query.
     */
    public static function lookup(array $addresses): array
    {
        $out = [];
        foreach (array_chunk(array_values(array_unique($addresses)), 500) as $chunk) {
            foreach (static::whereIn('address', $chunk)->get(['address', 'source']) as $row) {
                $out[$row->address][] = $row->source;
            }
        }

        return $out;
    }
}
