<?php

namespace App\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

class EloquentOcorrenciaModel extends Model
{
    protected $table = 'ocorrencias';
    protected $guarded = [];
}