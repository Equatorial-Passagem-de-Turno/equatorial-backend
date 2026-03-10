<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Roles extends Model
{
    protected $fillable = [
        'name', 
        'description', 
        'is_active'
    ];

    public function shifts()
    {
        return $this->hasMany(Shift::class, 'role', 'name');
    }
}


