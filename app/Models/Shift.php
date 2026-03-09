<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    /**
     * O Laravel tentará encontrar a tabela 'shifts' automaticamente,
     * mas é boa prática declarar explicitamente.
     */
    protected $table = 'shifts';

    protected $fillable = [
        'user_id',
        'start',
        'end',
        'status',
        'voltage_level',
        'previous_shift_id',
        'observations',
    ];

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'status' => 'string',
        'voltage_level' => 'string',
    ];

    /**
     * Relacionamento com o Utilizador (Operador)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relacionamento com as Ocorrências deste turno
     */
    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'shift_id');
    }

    /**
     * Relacionamento com o turno anterior (Herança)
     */
    public function previousShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'previous_shift_id');
    }
}
