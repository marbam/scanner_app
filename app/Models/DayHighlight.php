<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The highlight of one day, a short free-text note from Your Day.
 *
 * @property CarbonInterface $date
 * @property string $body
 */
class DayHighlight extends Model
{
    protected $fillable = [
        'date',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
