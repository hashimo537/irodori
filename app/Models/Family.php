<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'invite_code',
        'location_name',
        'latitude',
        'longitude',   // 天気予報のための地点
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function anniversaries()
    {
        return $this->hasMany(Anniversary::class);
    }

    /** 天気を出せる状態か */
    public function hasLocation(): bool
    {
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }

    /** かぶらない招待コードを作る */
    public static function generateInviteCode(): string
    {
        do {
            // まぎらわしい文字（0/O、1/I）を除いた8文字
            $code = collect(str_split('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'))
                ->shuffle()->take(8)->implode('');
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }
}
