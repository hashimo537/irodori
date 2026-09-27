<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** 単発の予定 */
class Event extends Model
{
    use HasFactory, BelongsToFamily;

    protected $fillable = [
        'member_id',
        'pickup_user_id',
        'title',
        'place',
        'date',
        'start_time',
        'end_time',
        'note',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /** お迎え担当（家族のユーザーから選ぶ） */
    public function pickup()
    {
        return $this->belongsTo(User::class, 'pickup_user_id');
    }
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getIsAllDayAttribute(): bool
    {
        return is_null($this->start_time);
    }
}
