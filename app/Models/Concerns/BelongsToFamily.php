<?php

namespace App\Models\Concerns;

use App\Models\Family;
use Illuminate\Database\Eloquent\Builder;

/**
 * このトレイトを使うと、ログイン中のユーザーの家族のデータしか
 * 取得・作成できなくなる。よその家族の予定が見える事故を構造的に防ぐ。
 */
trait BelongsToFamily
{
    protected static function bootBelongsToFamily(): void
    {
        // 全クエリに自動で where family_id = ? を付ける
        static::addGlobalScope('family', function (Builder $query) {
            if (auth()->check() && auth()->user()->family_id) {
                $query->where($query->getModel()->getTable().'.family_id', auth()->user()->family_id);
            }
        });

        // 保存時に family_id を自動でセット
        static::creating(function ($model) {
            if (empty($model->family_id) && auth()->check()) {
                $model->family_id = auth()->user()->family_id;
            }
        });
    }

    public function family()
    {
        return $this->belongsTo(Family::class);
    }
}
