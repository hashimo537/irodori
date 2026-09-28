<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** 記念日（毎年おなじ月日にくりかえす） */
class Anniversary extends Model
{
    use BelongsToFamily, HasFactory;

    protected $fillable = ['member_id', 'title', 'month', 'day', 'start_year'];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /** その日が記念日か */
    public function fallsOn(CarbonInterface $date): bool
    {
        // 2月29日の記念日は、平年は2月28日に出す
        if ($this->month === 2 && $this->day === 29 && ! $date->isLeapYear()) {
            return $date->month === 2 && $date->day === 28;
        }

        return $date->month === (int) $this->month && $date->day === (int) $this->day;
    }

    /** その年に何回目（何さい）か。start_year がなければ null */
    public function ageOn(CarbonInterface $date): ?int
    {
        if (! $this->start_year) {
            return null;
        }

        return $date->year - (int) $this->start_year;
    }

    public function getDateLabelAttribute(): string
    {
        return "{$this->month}月{$this->day}日";
    }
}
