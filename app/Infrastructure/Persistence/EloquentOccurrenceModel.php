<?php

namespace App\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

class EloquentOccurrenceModel extends Model
{
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'occurrences';

    /**
     * The attributes that are mass assignable.
     * @var array<int, string>
     */
    protected $fillable = [
        'shift_id',
        'title',
        'description',
        'type',
        'status',
        'supervisor_id',
    ];

    /**
     * The attributes that should be cast.
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
