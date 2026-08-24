<?php

namespace App\Models;

use Database\Factories\PlanningApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanningApplication extends Model
{
    /** @use HasFactory<PlanningApplicationFactory> */
    use HasFactory;

    protected $fillable = [
        'reference',
        'address',
        'proposal',
        'status',
        'decision',
        'decision_date',
        'viewed',
    ];

    protected function casts(): array
    {
        return [
            'decision_date' => 'datetime',
            'viewed' => 'boolean',
        ];
    }
}
