<?php

namespace App\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

class EloquentTurnoModel extends Model
{
    protected $table = 'turnos';

    protected $fillable = [
        'usuario_id',
        'inicio',
        'fim',
        'status',
        'observacoes',
    ];

    protected $casts = [
        'inicio' => 'datetime',
        'fim' => 'datetime',
    ];
}