<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    protected $fillable = ['album_id', 'question', 'answer_type', 'answer'];

    public function memories()
    {
        return $this->hasMany(\App\Models\Memory::class);
    }
}
