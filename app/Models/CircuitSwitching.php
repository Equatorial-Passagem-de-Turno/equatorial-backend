<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CircuitSwitching extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id', 'user_id', 'feeder', 'equipment', 'affected_clients',
        'responsible_sector', 'reason', 'observations', 'deadline',
        'new_deadline', 'status', 'attachments'
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'new_deadline' => 'datetime',
        'attachments' => 'array',
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
