<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompletedAlbum extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'album_id', 'completed_at'];

    protected $dates = ['completed_at'];

    public function album()
    {
        return $this->belongsTo(Album::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
