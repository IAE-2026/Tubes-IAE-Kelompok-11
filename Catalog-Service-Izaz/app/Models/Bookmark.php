<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bookmark extends Model
{
    protected $fillable = ['room_id'];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }
}
