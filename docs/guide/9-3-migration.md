# 9-3 マイグレーションとモデル（リレーションとグローバルスコープ）

📝 **このハンズオンで使う機能**: 外部キーとカスケード削除、Eloquent のリレーション、グローバルスコープ
📝 **前提知識**: 9-1 の設計と 9-2 の環境構築

---

## 🎯 このセクションで学ぶこと

- 9-1 で設計した7つのテーブルのマイグレーションを作り、外部キーと削除の連鎖を設定する
- モデルに1対多のリレーションと `$fillable` / `$casts` を定義する
- **グローバルスコープ**で、よその家族のデータを構造的に取れなくする
- アクセサで「保存しない値」を計算して返す

---

## 導入: 設計図を、動くテーブルとモデルにする

9-1 で、`families` を中心に7つのテーブルを設計しました。ここではそれをマイグレーションとモデルに落とし込みます。

このセクションには、このアプリで**いちばん重要なコード**が出てきます。グローバルスコープです。ここを理解すると、「セキュリティは注意力ではなく仕組みで守る」という考え方が身につきます。

---

## 🧠 先輩エンジニアの思考プロセス

マイグレーションで一番事故りやすいのが、**外部キーの順序**です。`members` が `families` を参照するなら、`families` のマイグレーションが先に走らないと外部キーが張れません。実行順は**ファイル名の日時順**で決まります。

今回は幸い、作る順番どおりに並べれば自然に正しい順になります。ただし `make:migration` を連打すると同じ秒に生成されることがあるので、`ls database/migrations` で並びを目で確認する癖をつけてください。

もうひとつ気にしているのが、**「この列は誰が書き込んでいいか」**です。`$fillable` に何を入れるかは、単なる便利機能ではなくセキュリティの線引きです。`family_id` をうっかり `$fillable` に入れると、フォームに隠しフィールドを足すだけでよその家族に潜り込めてしまいます。

---

## 📌 作業前の確認

- 9-2 で作った `irodori` ディレクトリにいる
- `sail ps` で3つのコンテナが `running`

---

## 🏃 実践: テーブルとモデルを作る

### 🏃 Step 1: モデルとマイグレーションを生成する

**参照される側から先に**作ります。`-m` はマイグレーションを同時に作るオプションです。

```bash
# irodori ディレクトリで実行
sail artisan make:model Family -m
sail artisan make:migration add_family_id_to_users_table
sail artisan make:model Member -m
sail artisan make:model Lesson -m
sail artisan make:model Event -m
sail artisan make:model Task -m
sail artisan make:model Anniversary -m
```

並びを確認します。

```bash
# irodori ディレクトリで実行
ls database/migrations
```

`create_families_table` → `add_family_id_to_users_table` → `create_members_table` → `create_lessons_table` → `create_events_table` → `create_tasks_table` → `create_anniversaries_table` の順になっていれば正解です。

🔑 順番が崩れていたら、ファイル名の日時部分を手で書き換えて並べ替えてください。マイグレーションは**生成した順ではなくファイル名の順**に実行されます。

### 🏃 Step 2: マイグレーションを編集する

ファイル名の日時部分は生成時刻で変わるので、`create_xxx_table` の部分で見分けてください。

**families テーブル。**

```php
<?php
// database/migrations/xxxx_create_families_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->string('name');                        // 「はしもと家」
            $table->string('invite_code', 8)->unique();    // 家族を共有する合言葉
            $table->string('location_name')->nullable();   // 天気予報の地点（9-8で使う）
            $table->decimal('latitude', 8, 5)->nullable();
            $table->decimal('longitude', 8, 5)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
```

**users テーブルに1列足す。**

```php
<?php
// database/migrations/xxxx_add_family_id_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 登録直後はまだ家族がないので nullable
            $table->foreignId('family_id')->nullable()->after('id')
                  ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['family_id']);   // 外部キーを先に外す
            $table->dropColumn('family_id');
        });
    }
};
```

🔑 **`down()` で外部キーを先に外す**のを忘れないでください。列だけ消そうとするとエラーになります。`migrate:rollback` を使うときに効いてきます。

**members テーブル。**

```php
<?php
// database/migrations/xxxx_create_members_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name');                          // 「ゆい」
            $table->string('color', 7)->default('#8FAF86');  // 子どもカラー
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
```

**lessons テーブル（習い事）。**

```php
<?php
// database/migrations/xxxx_create_lessons_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            // お迎え担当。決まっていないことも多いので nullable
            $table->foreignId('pickup_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('title');                          // 「スイミング」
            $table->string('place')->nullable();
            $table->unsignedTinyInteger('day_of_week');       // 0=日 … 6=土
            $table->time('start_time');
            $table->time('end_time');
            $table->date('starts_on');                        // 通いはじめた日
            $table->date('ends_on')->nullable();              // やめたら入れる
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
```

**events テーブル（単発の予定）。**

```php
<?php
// database/migrations/xxxx_create_events_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            // member_id が null のときは「家族ぜんいん」の予定
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pickup_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('place')->nullable();
            $table->date('date');
            $table->time('start_time')->nullable();           // null なら終日
            $table->time('end_time')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['family_id', 'date']);   // 週の絞り込みが速くなる
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
```

**tasks テーブル（提出物）。**

```php
<?php
// database/migrations/xxxx_create_tasks_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');                          // 「給食袋」
            $table->date('due_date')->nullable();
            $table->boolean('is_done')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
```

**anniversaries テーブル（記念日）。**

```php
<?php
// database/migrations/xxxx_create_anniversaries_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 記念日は毎年くりかえすので、日付ではなく「月と日」だけを持つ。
     * 習い事を「曜日」だけで持ったのと同じ考え方。
     */
    public function up(): void
    {
        Schema::create('anniversaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');                          // 「ゆいの誕生日」
            $table->unsignedTinyInteger('month');             // 1〜12
            $table->unsignedTinyInteger('day');               // 1〜31
            $table->unsignedSmallInteger('start_year')->nullable();  // 生まれた年
            $table->timestamps();

            $table->index(['family_id', 'month', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anniversaries');
    }
};
```

### 覚えておくポイント

**`constrained()`**: 列名（`family_id`）から参照先（`families.id`）を自動で推測して外部キー制約を張ります。存在しないIDを入れようとすると、データベースが弾いてくれます。`pickup_user_id` のように列名から推測できない場合は、`constrained('users')` とテーブル名を明示します。

**`cascadeOnDelete()` と `nullOnDelete()`**:

- `cascadeOnDelete()` … 親が消えたら、この行も消す（家族 → 子ども・予定）
- `nullOnDelete()` … 親が消えたら、この列を null にする（家族 → ユーザー、子ども → 予定）

9-1 の表で決めたとおりに設定します。「道連れにしていいか」を1つずつ考えた結果です。

**`index(['family_id', 'date'])`**: 週表示のたびに「この家族の、この期間の予定」を検索します。インデックスがないと全行を1件ずつ調べます。今は数十件なので体感差はありませんが、**どんな検索をするか分かっている列にはインデックスを張る**という習慣をつけてください。

---

### 🏃 Step 3: グローバルスコープのトレイトを作る

**このアプリでいちばん重要なコードです。**

```bash
# irodori ディレクトリで実行
mkdir -p app/Models/Concerns
touch app/Models/Concerns/BelongsToFamily.php
```

#### 何が問題か

```php
Event::find(5);   // ID 5 の予定
```

この5番が、よその家族の予定だったらどうなるでしょうか。URL に `/events/5/edit` と打つだけで、他人の家庭の予定が丸見えになります。

毎回コントローラで `where('family_id', auth()->user()->family_id)` を書けば防げます。でも、**1か所でも書き忘れたら穴が開きます**。画面は今後も増えます。人間は必ず書き忘れます。

#### 解決策

```php
<?php
// app/Models/Concerns/BelongsToFamily.php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * このトレイトを使うと、ログイン中のユーザーの家族のデータしか
 * 取得・作成できなくなる。よその家族の予定が見える事故を構造的に防ぐ。
 */
trait BelongsToFamily
{
    protected static function bootBelongsToFamily(): void
    {
        // すべての検索に自動で where family_id = ? を付ける
        static::addGlobalScope('family', function (Builder $query) {
            if (auth()->check() && auth()->user()->family_id) {
                $query->where(
                    $query->getModel()->getTable() . '.family_id',
                    auth()->user()->family_id
                );
            }
        });

        // 保存時に family_id を自動でセットする
        static::creating(function ($model) {
            if (empty($model->family_id) && auth()->check()) {
                $model->family_id = auth()->user()->family_id;
            }
        });
    }

    public function family()
    {
        return $this->belongsTo(\App\Models\Family::class);
    }
}
```

これを Member / Lesson / Event / Task / Anniversary に1行ずつ書くだけで、`Event::find(5)` はよその家族の予定を**取れなくなります**（null が返る）。書き忘れようがありません。

#### 覚えておくポイント

**`bootXxx` という命名規則**: トレイト名が `BelongsToFamily` なら `bootBelongsToFamily()` という名前のメソッドが、モデル起動時に自動で呼ばれます。`booted()` という汎用の名前にすると、複数のトレイトで取り合いになるので使いません。

**テーブル名を付ける理由**: `$query->getModel()->getTable() . '.family_id'` としているのは、JOIN したときに「どっちのテーブルの `family_id` か」が曖昧になるのを防ぐためです。単に `'family_id'` と書くと、JOIN 時に `ambiguous column` エラーになります。

**`auth()->check()` の判定が必要な理由**: シーダーやコマンドラインからはログインしていません。この判定がないと、シーダーで `Member::create()` したときに `auth()->user()` が null でエラーになります。

**`creating` フック**: 保存の直前に呼ばれます。`family_id` を自動で入れるので、コントローラで意識する必要がなくなります。

---

### 🏃 Step 4: モデルを編集する

📝 **`use HasFactory;` はそのまま残します。**

`make:model` で生成したモデルには、最初から `use HasFactory;` が入っています。これは `Member::factory()` という書き方を可能にするトレイトで、`database/factories/MemberFactory.php` と結びつけるためのものです。

今回はファクトリを使いません。シーダーで入れるのは「はると」「スイミング」「火曜16:30」といった**意味のある固定データ**で、ランダムな値だと週タイムラインの見え方を確認できないからです。

それでも残す理由が3つあります。

1. **`artisan` が生成したとおりだから。** 理由なく削ると、教材や他の人のコードと見比べたときに混乱します
2. **書いてあっても害がないから。** ファクトリのファイルが無くてもエラーにはなりません。`::factory()` を実際に呼んだときだけ探しに行きます
3. **テストを書くときに必ず要るから。** `Member::factory()->create()` はテストで重宝します。あとから足すより、最初から入れておくほうが楽です

トレイトはカンマで並べて書けます。`use HasFactory, BelongsToFamily;` のように書き、ファイル上部の `use Illuminate\Database\Eloquent\Factories\HasFactory;` も忘れずに。

**`app/Models/Family.php` をまるごと書き換えます。**

```php
<?php
// app/Models/Family.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Family extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'invite_code',
        'location_name', 'latitude', 'longitude',   // 天気予報のための地点
    ];

    public function users()   { return $this->hasMany(User::class); }
    public function members() { return $this->hasMany(Member::class); }
    public function lessons() { return $this->hasMany(Lesson::class); }
    public function events()  { return $this->hasMany(Event::class); }
    public function tasks()   { return $this->hasMany(Task::class); }
    public function anniversaries() { return $this->hasMany(Anniversary::class); }

    /** 天気を出せる状態か */
    public function hasLocation(): bool
    {
        return ! is_null($this->latitude) && ! is_null($this->longitude);
    }

    /** かぶらない招待コードを作る（9-4 でくわしく） */
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
```

**`app/Models/Member.php` をまるごと書き換えます。**

```php
<?php
// app/Models/Member.php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory, BelongsToFamily;

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

    public function lessons() { return $this->hasMany(Lesson::class); }
    public function events()  { return $this->hasMany(Event::class); }
    public function tasks()   { return $this->hasMany(Task::class); }
}
```

🔑 **アクセサ（`getXxxAttribute`）**: DBに列を持たず、その場で計算して返す値です。`$member->light_color` で呼べます（スネークケースになる点に注意）。

濃い色と薄い色は必ずペアで決まるので、両方を保存すると「片方だけ変更されて食い違う」状態が作れてしまいます。**片方から計算できるものは保存しない**、が原則です。

**`app/Models/Lesson.php` をまるごと書き換えます。**

```php
<?php
// app/Models/Lesson.php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 習い事 = 毎週おなじ曜日に繰り返す予定。
 * 「毎週火曜 16:30-18:00」を1行だけ持ち、表示のときに各週へ展開する。
 */
class Lesson extends Model
{
    use HasFactory, BelongsToFamily;

    protected $fillable = [
        'member_id', 'pickup_user_id', 'title', 'place',
        'day_of_week', 'start_time', 'end_time', 'starts_on', 'ends_on',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
        'starts_on'  => 'date',
        'ends_on'    => 'date',
    ];

    public const WEEKDAYS = ['日', '月', '火', '水', '木', '金', '土'];

    public function member() { return $this->belongsTo(Member::class); }

    /** お迎え担当（家族のユーザーから選ぶ） */
    public function pickup() { return $this->belongsTo(User::class, 'pickup_user_id'); }

    /** その日にこの習い事があるか（通っている期間内かどうか） */
    public function activeOn(\Carbon\CarbonInterface $date): bool
    {
        if ($date->dayOfWeek !== (int) $this->day_of_week) {
            return false;                          // 曜日が違う
        }
        if ($date->lt($this->starts_on)) {
            return false;                          // まだ通いはじめていない
        }
        if ($this->ends_on && $date->gt($this->ends_on)) {
            return false;                          // もうやめている
        }

        return true;
    }

    public function getWeekdayLabelAttribute(): string
    {
        return self::WEEKDAYS[$this->day_of_week] ?? '';
    }
}
```

🔑 **`activeOn()` をモデルに置く理由**: 同じ判定を週表示でも月表示でも使います。コントローラに書くとコピペになります。**そのデータ自身についての判断はモデルの仕事**です。

**`app/Models/Event.php` をまるごと書き換えます。**

```php
<?php
// app/Models/Event.php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** 単発の予定 */
class Event extends Model
{
    use HasFactory, BelongsToFamily;

    protected $fillable = [
        'member_id', 'pickup_user_id', 'title', 'place', 'date',
        'start_time', 'end_time', 'note', 'created_by',
    ];

    protected $casts = [
        'date'       => 'date',
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
    ];

    public function member()  { return $this->belongsTo(Member::class); }
    public function pickup()  { return $this->belongsTo(User::class, 'pickup_user_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    /** 時刻がなければ終日の予定 */
    public function getIsAllDayAttribute(): bool
    {
        return is_null($this->start_time);
    }
}
```

**`app/Models/Task.php` をまるごと書き換えます。**

```php
<?php
// app/Models/Task.php

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
        'is_done'  => 'boolean',
    ];

    public function member() { return $this->belongsTo(Member::class); }

    /** 期限のラベル（あすまで / 9月18日まで など） */
    public function getDueLabelAttribute(): ?string
    {
        if (! $this->due_date) {
            return null;
        }
        $days = now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false);

        return match (true) {
            $days <  0  => '期限すぎ',
            $days === 0 => 'きょうまで',
            $days === 1 => 'あすまで',
            default     => $this->due_date->format('n月j日') . 'まで',
        };
    }

    public function getIsUrgentAttribute(): bool
    {
        return $this->due_date && $this->due_date->startOfDay()->lte(now()->addDay()->startOfDay());
    }
}
```

📝 **`diffInDays(..., false)` の第2引数**: `false` を渡すと「過去なら負の数」を返します。これがないと絶対値になってしまい、期限切れかどうかが判定できません。

**`app/Models/Anniversary.php` をまるごと書き換えます。**

```php
<?php
// app/Models/Anniversary.php

namespace App\Models;

use App\Models\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** 記念日（毎年おなじ月日にくりかえす） */
class Anniversary extends Model
{
    use HasFactory, BelongsToFamily;

    protected $fillable = ['member_id', 'title', 'month', 'day', 'start_year'];

    public function member() { return $this->belongsTo(Member::class); }

    /** その日が記念日か */
    public function fallsOn(\Carbon\CarbonInterface $date): bool
    {
        // 2月29日の記念日は、平年は2月28日に出す
        if ($this->month === 2 && $this->day === 29 && ! $date->isLeapYear()) {
            return $date->month === 2 && $date->day === 28;
        }

        return $date->month === (int) $this->month && $date->day === (int) $this->day;
    }

    /** その年に何さいか。start_year がなければ null */
    public function ageOn(\Carbon\CarbonInterface $date): ?int
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
```

📝 **2月29日の扱い**: こういう「例外的だが必ず起きること」は、設計時に決めておきます。平年に表示しない選択肢もありますが、4年に1度しか誕生日が祝われないのは不便なので28日に出します。

**`app/Models/User.php` に2つ足します。**

```php
// app/Models/User.php のクラスの中に追加

    /** この人が所属している家族 */
    public function family()
    {
        return $this->belongsTo(\App\Models\Family::class);
    }

    public function hasFamily(): bool
    {
        return ! is_null($this->family_id);
    }
```

⚠️ **`$fillable` に `family_id` を足さないでください。** 新規登録フォームに `<input name="family_id" value="99">` を勝手に足されると、よその家族に潜り込めてしまいます。家族への参加は 9-4 で、コントローラから明示的に行います。

### `$casts` について

```php
protected $casts = [
    'start_time' => 'datetime:H:i',
    'starts_on'  => 'date',
    'is_done'    => 'boolean',
];
```

DBから来るのはただの文字列です。`$casts` を書いておくと、Laravel が自動で Carbon（日付を扱うクラス）や真偽値に変換してくれます。

```php
// $casts なし
$lesson->start_time          // "16:30:00" という文字列

// $casts あり
$lesson->start_time->format('H:i')   // "16:30"
$lesson->starts_on->addWeek()        // 日付計算ができる
```

**日付・時刻・真偽値の列を作ったら必ず `$casts` に書く**と決めておくと事故が減ります。

---

### 🏃 Step 5: マイグレーションを実行する

```bash
# irodori ディレクトリで実行
sail artisan migrate
```

出力に `families`・`members`・`lessons`・`events`・`tasks`・`anniversaries` の作成が並べば成功です。

⚠️ **よくあるエラー**: `SQLSTATE[HY000]: General error: 1215 Cannot add foreign key constraint`

**原因**: 参照される側のテーブルより先に、参照する側のマイグレーションが走っている。

**対処法**: `ls database/migrations` で並びを確認し、`create_families_table` が `create_members_table` より前にあるかを見てください。順が違っていたら、ファイル名の日時部分を書き換えてから `sail artisan migrate:fresh` を実行します。

---

### 🏃 Step 6: Tinker でリレーションを確認する

画面を作る前に、リレーションが正しいかを確認します。

```bash
# irodori ディレクトリで実行
sail artisan tinker
```

```php
// 家族を1つ作る
$f = \App\Models\Family::create(['name' => 'テスト家', 'invite_code' => \App\Models\Family::generateInviteCode()]);

// その家族に子どもを作る（リレーション経由なので family_id が自動で入る）
$m = $f->members()->create(['name' => 'ゆい', 'color' => '#8FB2C9']);

// 両方向からたどれるか
$m->family->name;          // => "テスト家"
$f->members()->count();    // => 1

// 招待コードが8文字でできているか
strlen($f->invite_code);   // => 8
```

たどれれば、1対多のリレーションが両方向から正しく定義できています。`exit` で終了します。

🔑 **`$f->members()->create()` と書く理由**: リレーション経由で作ると `family_id` が自動で入ります。`Member::create(['family_id' => ...])` だと、`$fillable` に `family_id` がないので弾かれます。

---

## ✅ 完成チェックリスト

- 7つのマイグレーションを作り、外部キーと削除の連鎖を設定できた
- `BelongsToFamily` トレイトを作り、5つのモデルで `use` できた
- モデルに1対多のリレーションと `$fillable` / `$casts` を定義できた
- `use HasFactory, BelongsToFamily;` と2つのトレイトを並べて書けた
- アクセサ（`light_color`・`is_all_day`・`due_label`）の役割を説明できる
- `sail artisan migrate` が通り、phpMyAdmin でテーブルを確認できた
- Tinker でリレーションを両方向から確認できた

---

## ✨ まとめ

- 参照される側から順にマイグレーションを作り、外部キーの実行順を保った
- `cascadeOnDelete()` と `nullOnDelete()` を、9-1 で決めた表のとおりに使い分けた
- **グローバルスコープ**で、よその家族のデータを構造的に取れなくした
- くりかえし判定（`activeOn` / `fallsOn`）をモデルに置き、週表示と月表示で共用できるようにした
- 計算できる値（薄い色・終日かどうか・期限ラベル）はDBに持たず、アクセサで返した

次の 9-4 では、Fortify で認証を実装し、招待コードで家族をつなぎます。
