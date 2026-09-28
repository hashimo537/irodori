<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use BelongsToFamily,HasFactory;

    protected $fillable = ['name', 'color', 'sort_order'];

    /**
     * 選べる6色。キー = 濃い色（線）、値 = 薄い色（帯の背景）
     */
    public const PALETTE = [
        '#8FAF86' => ['light' => '#E4EDE1', 'label' => 'セージ'],
        '#D79372' => ['light' => '#F7E6DC', 'label' => 'テラコッタ'],
        '#8FB2C9' => ['light' => '#E2ECF2', 'label' => 'スカイ'],
        '#DFB877' => ['light' => '#F8EEDC', 'label' => 'アプリコット'],
        '#A79BC0' => ['light' => '#EAE6F1', 'label' => 'ラベンダー'],
        '#CE9599' => ['light' => '#F5E5E6', 'label' => 'ダスティローズ'],
    ];

    /** 帯の背景に使う薄い色 */
    public function getLightColorAttribute(): string
    {
        return self::PALETTE[$this->color]['light'] ?? '#EFEBE3';
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
}
