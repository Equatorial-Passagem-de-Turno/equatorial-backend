<?php

namespace App\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

class EloquentShiftModel extends Model
{
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'shifts';

    /**
     * The attributes that are mass assignable.
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'start',
        'end',
        'status',
        'voltage_level',
        'previous_shift_id',
        'observations',
    ];

    /**
     * The attributes that should be cast.
     * @var array<string, string>
     */
    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
    ];
}
