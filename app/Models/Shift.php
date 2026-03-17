<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $table = 'shifts';

    protected $fillable = [
        'user_id',
        'operation_desk_id',
        'role',
        'start',
        'end',
        'status',
        'previous_shift_id',
        'briefing',
        'handover_acknowledged',
        'next_operator_id'
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'handover_acknowledged' => 'boolean',
        'status' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'shift_id');
    }

    public function previousShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'previous_shift_id');
    }

    public function desk(): BelongsTo
    {
        return $this->belongsTo(OperationDesk::class, 'operation_desk_id');
    }

    public function roleDetails(): BelongsTo
    {
        return $this->belongsTo(Roles::class, 'role', 'name');
    }
}
