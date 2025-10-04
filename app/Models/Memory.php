<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Memory extends Model
{
    protected $fillable = ['challenge_id', 'type', 'file_path', 'note'];
}
