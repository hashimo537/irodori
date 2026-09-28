<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 習い事 = 毎週おなじ曜日に繰り返す予定。
 * 「毎週火曜 16:30-18:00」を1行だけ持ち、表示のときに各週へ展開する。
 */
class Lesson extends Model
{
    use BelongsToFamily, HasFactory;

    protected $fillable = [
        'member_id',
        'pickup_user_id',
        'title',
        'place',
        'day_of_week',
        'start_time',
        'end_time',
        'starts_on',
        'ends_on',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /** お迎え担当（家族のユーザーから選ぶ） */
    public function pickup()
    {
        return $this->belongsTo(User::class, 'pickup_user_id');
    }

    /** その日にこの習い事があるか（通っている期間内かどうか） */
    public function activeOn(CarbonInterface $date): bool
    {
        if ($date->dayOfWeek !== (int) $this->day_of_week) {
            return false;
        }
        if ($date->lt($this->starts_on)) {
            return false;
        }
        if ($this->ends_on && $date->gt($this->ends_on)) {
            return false;
        }

        return true;
    }

    public function getWeekdayLabelAttribute(): string
    {
        return self::WEEKDAYS[$this->day_of_week] ?? '';
    }
}
