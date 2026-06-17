<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        'id',
        'name',
        'location',
        'price',
        'description',
        'facilities'
    ];

    protected $casts = [
        'facilities' => 'array',
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function addons()
    {
        return $this->belongsToMany(Addon::class, 'room_addon');
    }
}
