<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Occurrence extends Model
{
    protected $table = 'occurrences';

    protected $fillable = [
        'shift_id',
        'title',
        'description',
        'type',
        'status',
        'supervisor_id'
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
