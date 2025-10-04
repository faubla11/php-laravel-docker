<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Album extends Model
{
    protected $fillable = ['user_id', 'title', 'description', 'category', 'code', 'bg_image'];

    public function challenges()
    {
        return $this->hasMany(\App\Models\Challenge::class);
    }
}
