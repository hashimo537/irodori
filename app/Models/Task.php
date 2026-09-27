<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** 提出物・持ち物・家族のタスク */
class Task extends Model
{
    use HasFactory, BelongsToFamily;

    protected $fillable = ['member_id', 'title', 'due_date', 'is_done'];

    protected $casts = [
        'due_date' => 'date',
        'is_done' => 'boolean',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /** 期限のラベル（明日まで / 3日後 など） */
    public function getDueLabelAttribute(): ?string
    {
        if (!$this->due_date) {
            return null;
        }
        $days = now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false);

        return match (true) {
            $days < 0 => '期限すぎ',
            $days === 0 => 'きょうまで',
            $days === 1 => 'あすまで',
            default => $this->due_date->format('n月j日') . 'まで',
        };
    }

    public function getIsUrgentAttribute(): bool
    {
        return $this->due_date && $this->due_date->startOfDay()->lte(now()->addDay()->startOfDay());
    }
}
