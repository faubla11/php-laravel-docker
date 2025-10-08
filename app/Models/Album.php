<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Album extends Model
{
    protected $fillable = ['user_id', 'title', 'description', 'category', 'code', 'bg_image', 'allow_collaborators'];

    public function challenges()
    {
        return $this->hasMany(\App\Models\Challenge::class);
    }

    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
