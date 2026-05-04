<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnavailableEquipment extends Model
{
    use HasFactory;

    protected $table = 'unavailable_equipments';

    protected $fillable = [
        'shift_id', 'user_id', 'equipment_number', 'equipment_type',
        'feeder', 'contract_account', 'note_number', 'responsible_sector',
        'observations', 'deadline', 'status', 'attachments'
    ];

    protected $casts = [
        'deadline' => 'datetime',
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
