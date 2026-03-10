<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationDesk extends Model
{
    protected $fillable = [
        'code', 
        'name', 
        'location', 
        'is_active'
    ];

    public function shifts()
    {
        return $this->hasMany(Shift::class);
    }
}
