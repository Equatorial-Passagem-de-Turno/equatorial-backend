<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Occurrence extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'shift_id',
        'supervisor_id',
        'title',
        'category',
        'priority',
        'status',
        'description',
        'location',
        'link_type',
        'link_value',
        'attachments',
        'comments',
        'reminders',
    ];

    protected $casts = [
        'location' => 'array',
        'attachments' => 'array',
        'comments' => 'array',
        'reminders' => 'array',
    ];

    protected function priority(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => match ($value) {
                'critical' => 'crítica',
                'high' => 'alta',
                'medium' => 'média',
                'low' => 'baixa',
                default => $value,
            },
            set: fn (?string $value) => match (strtolower($value)) {
                'crítica', 'critica' => 'critical',
                'alta' => 'high',
                'média', 'media' => 'medium',
                'baixa' => 'low',
                default => strtolower($value),
            }
        );
    }

    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => match ($value) {
                'open' => 'Aberta',
                'in_progress' => 'Em Andamento',
                'resolved' => 'Resolvida',
                'finished' => 'Finalizada',
                default => $value,
            },
            set: fn (?string $value) => match (strtolower($value)) {
                'aberta' => 'open',
                'em andamento' => 'in_progress',
                'resolvida' => 'resolved',
                'finalizada' => 'finished',
                default => strtolower($value),
            }
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }
}
