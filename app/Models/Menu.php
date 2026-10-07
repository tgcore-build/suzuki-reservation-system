<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = ['name', 'price', 'duration_minutes', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];
}
