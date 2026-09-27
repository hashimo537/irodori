# 9-5 CRUD（子ども・習い事・予定）

📝 **このハンズオンで使う機能**: `Route::resource()`、バリデーション、フォームの共通化
📝 **前提知識**: 9-4 の認証と家族の共有

---

## 🎯 このセクションで学ぶこと

- `Route::resource()` で7つのルートをまとめて定義し、不要なものを外す
- 登録フォームと編集フォームを `_form.blade.php` で共通化する
- バリデーションを private メソッドに切り出し、`store` と `update` で共用する
- シーダーで確認用データを入れる

---

## 導入: 表示を作る前に、データを入れる

9-6 で作る週タイムラインが、このアプリの主役です。でもいきなりそこへ行くと困ります。**表示するデータがないから**です。

かといって、先に登録画面を完璧に作ってから表示に進むと、「登録できたのか確かめられない」まま何時間も進むことになります。

そこで、このセクションでは **CRUD とシーダーの両方**を用意します。シーダーでデータを入れておけば、9-6 で表示に集中できます。

---

## 🧠 先輩エンジニアの思考プロセス

CRUD を書くときに必ずやるのが、**バリデーションの切り出し**と**フォームの共通化**です。

`store` と `update` はバリデーションのルールがほぼ同じです。コピペすると、後で片方だけ直して食い違います。これは実際によくある事故で、「新規登録はできるのに編集だと弾かれる」という不可解な現象になります。

`create.blade.php` と `edit.blade.php` も同じです。項目を1つ足すときに、2ファイル直す必要がある構造にはしません。

---

## 📌 作業前の確認

- 9-4 でログイン・家族の作成／参加ができている
- ログイン後に `/home` へ行こうとすると、`WeekController` がないのでエラーになる状態（正常です。9-6 で作ります）

---

## 🏃 実践: CRUD を作る

### 🏃 Step 1: ルートを足す

`routes/web.php` の `has.family` グループの中に足します。

```php
// routes/web.php の has.family グループ内

use App\Http\Controllers\EventController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MemberController;

        Route::resource('members', MemberController::class)->except(['show', 'create']);
        Route::resource('events',  EventController::class)->except(['show', 'index']);
        Route::resource('lessons', LessonController::class)->except(['show']);
```

### `Route::resource()` が作る7つのルート

| メソッド | URL | コントローラのメソッド | 用途 |
|---|---|---|---|
| GET | /events | index | 一覧 |
| GET | /events/create | create | 登録フォーム |
| POST | /events | store | 登録実行 |
| GET | /events/1 | show | 詳細 |
| GET | /events/1/edit | edit | 編集フォーム |
| PUT/PATCH | /events/1 | update | 更新実行 |
| DELETE | /events/1 | destroy | 削除実行 |

**外しているもの**:

- `show` … 詳細画面は作りません。編集画面が詳細を兼ねます。画面を1つ減らせます
- `events` の `index` … 予定の一覧は週タイムラインが兼ねます
- `members` の `create` … 子どもの登録フォームは一覧画面に埋め込むので、単独の画面は要りません

🔑 **確認のしかた**: `sail artisan route:list` で意図どおりか確認する癖をつけてください。`route()` のタイプミスは実行するまで分からないので、先に一覧で見ておくと安心です。

---

### 🏃 Step 2: MemberController を作る

```bash
# irodori ディレクトリで実行
sail artisan make:controller MemberController
```

```php
<?php
// app/Http/Controllers/MemberController.php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index()
    {
        return view('members.index', [
            'members' => Member::orderBy('sort_order')->orderBy('id')->get(),
            'palette' => Member::PALETTE,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = (Member::max('sort_order') ?? 0) + 1;

        Member::create($data);

        return back()->with('status', "{$data['name']}さんを登録しました。");
    }

    public function edit(Member $member)
    {
        return view('members.edit', [
            'member'  => $member,
            'palette' => Member::PALETTE,
        ]);
    }

    public function update(Request $request, Member $member)
    {
        $member->update($this->validated($request));

        return redirect()->route('members.index')->with('status', '変更を保存しました。');
    }

    public function destroy(Member $member)
    {
        $name = $member->name;
        $member->delete();

        return redirect()->route('members.index')
            ->with('status', "{$name}さんと、その予定を削除しました。");
    }

    /** store と update で同じルールを使う */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'  => ['required', 'string', 'max:20'],
            // 用意した6色のいずれか。自由入力にすると変な色が入る
            'color' => ['required', Rule::in(array_keys(Member::PALETTE))],
        ], [], [
            'name'  => 'なまえ',
            'color' => '色',
        ]);
    }
}
```

### 覚えておくポイント

**`Member $member` という引数（ルートモデルバインディング）**: URL の `/members/3/edit` の `3` を見て、Laravel が自動で `Member::find(3)` してくれます。見つからなければ自動で 404 になります。

そして重要なのが、**この検索にもグローバルスコープが効く**ことです。よその家族の子どものIDを URL に打っても、404 になります。9-3 で仕込んだ安全装置が、ここでも自動的に働いています。

**`Rule::in(array_keys(Member::PALETTE))`**: 色は6色のうちどれか、という検証です。`<input type="color">` のような自由入力にすると、真っ黒や蛍光色を選ばれて画面が破綻します。**選択肢を絞ることも設計**です。

**`back()` と `redirect()->route()` の使い分け**: 登録（`store`）は同じ画面に留まってほしいので `back()`。更新（`update`）は一覧に戻したいので `redirect()->route()`。ユーザーの気持ちで選びます。

---

### 🏃 Step 3: LessonController を作る

```bash
# irodori ディレクトリで実行
sail artisan make:controller LessonController
```

```php
<?php
// app/Http/Controllers/LessonController.php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    public function index()
    {
        return view('lessons.index', [
            'lessons' => Lesson::with('member')
                ->orderBy('day_of_week')->orderBy('start_time')->get(),
        ]);
    }

    public function create()
    {
        return view('lessons.create', [
            'members' => Member::orderBy('sort_order')->get(),
            'parents' => User::where('family_id', auth()->user()->family_id)->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Lesson::create($this->validated($request));

        return redirect()->route('lessons.index')->with('status', '習い事を登録しました。');
    }

    public function edit(Lesson $lesson)
    {
        return view('lessons.edit', [
            'lesson'  => $lesson,
            'members' => Member::orderBy('sort_order')->get(),
            'parents' => User::where('family_id', auth()->user()->family_id)->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $lesson->update($this->validated($request));

        return redirect()->route('lessons.index')->with('status', '変更を保存しました。');
    }

    public function destroy(Lesson $lesson)
    {
        $lesson->delete();

        return redirect()->route('lessons.index')->with('status', '習い事を削除しました。');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'member_id'   => ['required', 'exists:members,id'],
            // お迎え担当は「同じ家族のユーザー」だけ選べるようにする
            'pickup_user_id' => ['nullable', Rule::in(
                User::where('family_id', auth()->user()->family_id)->pluck('id')->all()
            )],
            'title'       => ['required', 'string', 'max:40'],
            'place'       => ['nullable', 'string', 'max:40'],
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time'  => ['required', 'date_format:H:i'],
            'end_time'    => ['required', 'date_format:H:i', 'after:start_time'],
            'starts_on'   => ['required', 'date'],
            'ends_on'     => ['nullable', 'date', 'after:starts_on'],
        ], [], [
            'member_id'      => 'だれの',
            'title'          => '習い事の名前',
            'day_of_week'    => '曜日',
            'start_time'     => 'はじまる時間',
            'end_time'       => 'おわる時間',
            'starts_on'      => '通いはじめた日',
            'ends_on'        => 'おわる日',
            'pickup_user_id' => 'お迎え担当',
        ]);
    }
}
```

### 覚えておくポイント

**`with('member')` ＝ N+1 問題の対策**

これを書かないと、Blade で `$lesson->member->name` を呼ぶたびに SQL が1本ずつ飛びます。習い事が20件なら「一覧を取る SQL 1本 ＋ 持ち主を取る SQL 20本」で21本。これが **N+1問題** です。

`with('member')` を書くと、Laravel が先に持ち主をまとめて取ってくるので2本で済みます。

**見つけ方**: `AppServiceProvider` の `boot()` に次を書くと、実行された SQL がログに出ます。

```php
\DB::listen(fn ($q) => logger($q->sql));
```

`storage/logs/laravel.log` を見て、一覧画面で SQL が何十本も出ていたら N+1 です。

**`Rule::in(...)` でお迎え担当を絞る理由**

`exists:users,id` だと、**世の中の全ユーザー**が対象になります。他人のユーザーIDを送られたら、その人が「お迎え担当」として表示されてしまいます。

`User` モデルには家族のグローバルスコープを付けていない（ログインに使うテーブルなので付けられない）ので、ここは自分で絞る必要があります。**スコープが効かない場所を意識する**のが大事です。

**`after:start_time`**: 終了が開始より前、という矛盾を防ぎます。日付の `after:starts_on` も同じです。

---

### 🏃 Step 4: EventController を作る

```bash
# irodori ディレクトリで実行
sail artisan make:controller EventController
```

```php
<?php
// app/Http/Controllers/EventController.php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function create(Request $request)
    {
        return view('events.create', [
            'members' => Member::orderBy('sort_order')->get(),
            'parents' => User::where('family_id', auth()->user()->family_id)->orderBy('id')->get(),
            // カレンダーで開いていた日を初期値にする
            'date'    => $request->input('date', now()->toDateString()),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        Event::create($data);

        return redirect()->route('home', ['date' => $data['date']])
            ->with('status', '予定を追加しました。');
    }

    public function edit(Event $event)
    {
        return view('events.edit', [
            'event'   => $event,
            'members' => Member::orderBy('sort_order')->get(),
            'parents' => User::where('family_id', auth()->user()->family_id)->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, Event $event)
    {
        $event->update($this->validated($request));

        return redirect()->route('home', ['date' => $event->date->toDateString()])
            ->with('status', '予定を変更しました。');
    }

    public function destroy(Event $event)
    {
        $date = $event->date->toDateString();
        $event->delete();

        return redirect()->route('home', ['date' => $date])
            ->with('status', '予定を削除しました。');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'member_id'  => ['nullable', 'exists:members,id'],
            'pickup_user_id' => ['nullable', Rule::in(
                User::where('family_id', auth()->user()->family_id)->pluck('id')->all()
            )],
            'title'      => ['required', 'string', 'max:40'],
            'place'      => ['nullable', 'string', 'max:40'],
            'date'       => ['required', 'date'],
            'all_day'    => ['nullable', 'boolean'],
            // 終日でなければ時刻は必須
            'start_time' => ['nullable', 'required_without:all_day', 'date_format:H:i'],
            'end_time'   => ['nullable', 'date_format:H:i', 'after:start_time'],
            'note'       => ['nullable', 'string', 'max:500'],
        ], [], [
            'title'      => 'タイトル',
            'date'       => '日付',
            'start_time' => 'はじまる時間',
            'end_time'   => 'おわる時間',
        ]);

        // 終日にチェックが入っていたら時刻を消す
        if ($request->boolean('all_day')) {
            $data['start_time'] = null;
            $data['end_time']   = null;
        }
        unset($data['all_day']);   // DBにない列なので取り除く

        return $data;
    }
}
```

🔑 **`unset($data['all_day'])` を忘れないでください。** `all_day` は画面のチェックボックス用で、`events` テーブルには存在しない列です。残したまま `create()` すると `Column not found` でエラーになります。

「画面の入力項目」と「DBの列」は一致しないことがある、という例です。

🔑 **`required_without:all_day`**: 「`all_day` がなければ必須」という意味です。終日にチェックを入れたときだけ、時刻を省けます。**バリデーションのルール同士を関連づけられる**ことを覚えておくと、条件分岐を減らせます。

---

### 🏃 Step 5: フォームを共通化する

`create` と `edit` は中身がほぼ同じです。`_form.blade.php` に切り出して、両方から呼びます。

```bash
# irodori ディレクトリで実行
mkdir -p resources/views/lessons resources/views/events resources/views/members
touch resources/views/lessons/_form.blade.php
touch resources/views/lessons/create.blade.php
touch resources/views/lessons/edit.blade.php
touch resources/views/lessons/index.blade.php
```

`resources/views/lessons/create.blade.php`。

```blade
{{-- resources/views/lessons/create.blade.php --}}
@extends('layouts.irodori')
@section('title', '習い事を登録 — いろどり')
@section('wrap-class', 'narrow')

@section('content')
  <div class="card">
    <h2>習い事を登録</h2>
    <p class="lead">一度だけ登録すれば、毎週おなじ曜日に自動で表示されます。</p>

    @if ($members->isEmpty())
      <p class="empty">
        さきに お子さんを登録してください。<br>
        <a href="{{ route('members.index') }}">家族の登録へ</a>
      </p>
    @else
      <form method="post" action="{{ route('lessons.store') }}">
        @csrf
        @include('lessons._form')
        <div class="btn-row">
          <button type="submit" class="btn primary">登録する</button>
          <a class="btn" href="{{ route('lessons.index') }}">やめる</a>
        </div>
      </form>
    @endif
  </div>
@endsection
```

`resources/views/lessons/edit.blade.php`。

```blade
{{-- resources/views/lessons/edit.blade.php --}}
@extends('layouts.irodori')
@section('title', '習い事を編集 — いろどり')
@section('wrap-class', 'narrow')

@section('content')
  <div class="card">
    <h2>習い事を編集</h2>

    <form method="post" action="{{ route('lessons.update', $lesson) }}">
      @csrf @method('put')
      @include('lessons._form')
      <div class="btn-row">
        <button type="submit" class="btn primary">保存する</button>
        <a class="btn" href="{{ route('lessons.index') }}">やめる</a>
      </div>
    </form>
  </div>

  <form method="post" action="{{ route('lessons.destroy', $lesson) }}"
        onsubmit="return confirm('この習い事を削除します。よろしいですか？');">
    @csrf @method('delete')
    <div class="btn-row">
      <button type="submit" class="btn danger">この習い事を削除する</button>
    </div>
  </form>
@endsection
```

`resources/views/lessons/_form.blade.php`（抜粋。全文は既存プロジェクトから流用してください）。

```blade
{{-- resources/views/lessons/_form.blade.php --}}
<div class="field">
  <label for="title">習い事の名前<span class="req">必須</span></label>
  <input type="text" id="title" name="title"
         value="{{ old('title', $lesson->title ?? '') }}" placeholder="例）スイミング" required>
</div>

<div class="field">
  <label>なん曜日<span class="req">必須</span></label>
  <div class="weekdays">
    @foreach (['日','月','火','水','木','金','土'] as $i => $label)
      <label>
        <input type="radio" name="day_of_week" value="{{ $i }}"
               {{ (string) old('day_of_week', $lesson->day_of_week ?? '') === (string) $i ? 'checked' : '' }}>
        <span>{{ $label }}</span>
      </label>
    @endforeach
  </div>
</div>

<div class="field">
  <label for="pickup_user_id">お迎え<span class="opt">任意</span></label>
  <select id="pickup_user_id" name="pickup_user_id">
    <option value="">きめていない</option>
    @foreach ($parents as $parent)
      <option value="{{ $parent->id }}"
        {{ (string) old('pickup_user_id', $lesson->pickup_user_id ?? '') === (string) $parent->id ? 'selected' : '' }}>
        {{ $parent->name }}
      </option>
    @endforeach
  </select>
  <p class="prefill">家族として登録している人から選べます。招待コードで参加すると増えます。</p>
</div>
```

### 覚えておくポイント

**`isset($lesson)` で新規と編集を見分ける**

```blade
value="{{ old('title', $lesson->title ?? '') }}"
```

`$lesson` があれば編集、なければ新規。`??` を使うと、変数が無いときにエラーにならず空文字になります。

**`old()` の意味**

バリデーションで弾かれて戻ってきたとき、入力した内容を復元します。第2引数はその値がないときの初期値です。**これを書かないと、エラーのたびに全部打ち直しになります。**

**`@method('put')` が必要な理由**

HTML のフォームは GET と POST しか送れません。`@method('put')` は隠しフィールド `<input type="hidden" name="_method" value="PUT">` を出力して、Laravel に「PUT として扱え」と伝える仕組みです。DELETE も同じです。

**`@csrf` を忘れると** 419 エラーになります。他サイトから勝手にフォーム送信されるのを防ぐ仕組みです。

**`(string)` でキャストして比較する理由**

```blade
{{ (string) old('day_of_week', ...) === (string) $i ? 'checked' : '' }}
```

`old()` が返すのは文字列の `"1"`、`$i` は数値の `1`。`===`（厳密比較）だと型が違って false になります。両方を文字列に揃えてから比べます。`==`（緩い比較）を使う手もありますが、`"0" == false` が true になるなど落とし穴が多いので、**揃えてから `===`** が安全です。

**初期値を入れすぎない**

時刻に `16:00` のような初期値を入れると、親切のようで実は「入力済み」に見えます。直し忘れると間違った時間で登録されます。**空にして、例示は `placeholder` で示す**のが親切です。

日付だけは例外で、毎回打たせると面倒なので初期値を入れ、欄の下に「きょうの日付を入れてあります」と書き添えます。

---

### 🏃 Step 6: シーダーで確認用データを入れる

```bash
# irodori ディレクトリで実行
sail artisan make:seeder DemoSeeder
```

```php
<?php
// database/seeders/DemoSeeder.php

namespace Database\Seeders;

use App\Models\Family;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * 動作確認用のデモデータ。
 *   sail artisan db:seed --class=DemoSeeder
 *
 * ログイン：  demo@example.com  /  password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $family = Family::create([
            'name'        => 'はしもと家',
            'invite_code' => Family::generateInviteCode(),
        ]);

        $user = User::updateOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name'      => 'まゆ',
                'password'  => 'password',   // User の hashed キャストが自動でハッシュ化する
                'family_id' => $family->id,
            ]
        );

        $haruto = $family->members()->create(['name' => 'はると', 'color' => '#D79372', 'sort_order' => 1]);
        $yui    = $family->members()->create(['name' => 'ゆい',   'color' => '#8FB2C9', 'sort_order' => 2]);
        $aoi    = $family->members()->create(['name' => 'あおい', 'color' => '#8FAF86', 'sort_order' => 3]);

        $from = Carbon::today()->subMonths(3)->toDateString();

        // 習い事（毎週くりかえし）
        $lessons = [
            [$haruto, 'そろばん',   'なかまち教室', 1, '16:00', '17:30'],
            [$yui,    'えいご',     null,           1, '15:30', '16:20'],
            [$yui,    'スイミング', '市民プール',   2, '16:30', '18:00'],
            [$aoi,    'たいそう',   null,           2, '15:30', '16:30'],
            [$haruto, 'えいご',     null,           3, '17:00', '18:00'],
            [$haruto, 'ピアノ',     'みどり音楽',   4, '16:30', '18:00'],
            [$yui,    'リトミック', null,           4, '15:00', '16:00'],
            [$aoi,    'ダンス',     '公民館',       5, '16:00', '17:00'],
            [$haruto, 'そろばん',   'なかまち教室', 5, '17:30', '19:00'],
            // ↓ この2つは土曜の午前に重なる（9-6 の横並びを確認するため）
            [$haruto, 'サッカー',   '東小 校庭',    6, '09:00', '11:00'],
            [$yui,    '絵画きょうしつ', null,       6, '10:00', '11:30'],
        ];

        foreach ($lessons as [$member, $title, $place, $dow, $start, $end]) {
            $family->lessons()->create([
                'member_id'      => $member->id,
                'pickup_user_id' => $start >= '16:00' ? $user->id : null,
                'title'          => $title,
                'place'          => $place,
                'day_of_week'    => $dow,
                'start_time'     => $start,
                'end_time'       => $end,
                'starts_on'      => $from,
            ]);
        }

        // 単発の予定
        $saturday = Carbon::today()->startOfWeek(Carbon::MONDAY)->addDays(5);

        $family->events()->create([
            'member_id'  => null,              // 家族ぜんいん
            'title'      => 'じいじの家',
            'date'       => $saturday->toDateString(),
            'start_time' => '14:00',
            'end_time'   => '16:30',
            'note'       => "おみやげを持っていく。\n帰りにスーパーへ寄る。",
            'created_by' => $user->id,
        ]);

        $family->events()->create([
            'member_id'  => $aoi->id,
            'title'      => 'たいそう発表会',
            'place'      => '総合体育館',
            'date'       => $saturday->copy()->addDay()->toDateString(),
            'start_time' => null,              // 終日
            'note'       => '8:30 集合。体操服とお茶を忘れずに。',
            'created_by' => $user->id,
        ]);

        $this->command->info("デモデータを作りました。 招待コード: {$family->invite_code}");
        $this->command->info('ログイン: demo@example.com / password');
    }
}
```

```bash
# irodori ディレクトリで実行
sail artisan db:seed --class=DemoSeeder
```

### 覚えておくポイント

**`$family->members()->create()` と書く理由**: リレーション経由で作ると `family_id` が自動で入ります。`Member::create(['family_id' => ...])` だと `$fillable` に `family_id` がないので弾かれます。

**パスワードを平文で渡す**: Laravel 10 の `User` モデルには `'password' => 'hashed'` というキャストが最初から入っていて、保存時に自動でハッシュ化されます。`Hash::make()` を通すと環境によって二重ハッシュになり、ログインできなくなります。**キャストに任せる**のが正解です。

**土曜に時間が重なる習い事を入れている**のは意図的です。9-6 で作る「横並び表示」を確認するためのデータです。**先に確認用のデータを仕込んでおく**と、実装後すぐに動作を確かめられます。

---

## ⚠️ よくあるエラー

| エラー | 原因 |
|---|---|
| `Column not found: 'all_day'` | `unset($data['all_day'])` 忘れ |
| `Add [family_id] to fillable property` | `Member::create()` を直接呼んでいる。`$family->members()->create()` に |
| `419 Page Expired` | `@csrf` 忘れ |
| 編集画面で保存しても何も起きない | `@method('put')` 忘れ（POST として `store` に飛んでいる） |
| ログインできない（シーダーのユーザー） | `Hash::make()` を通して二重ハッシュになっている |
| 曜日のラジオが全部選ばれていない状態で保存できない | 正常です。`required` が効いています |

---

## ✅ 完成チェックリスト

- `Route::resource()` で必要なルートだけを定義できた（`route:list` で確認）
- バリデーションを private メソッドに切り出し、`store` と `update` で共用できた
- `_form.blade.php` でフォームを共通化できた
- ルートモデルバインディングにグローバルスコープが効くことを説明できる
- お迎え担当を `Rule::in()` で家族内に絞る理由を説明できる
- `unset($data['all_day'])` が必要な理由を説明できる
- シーダーでデモデータを入れ、子ども3人と習い事11件が登録できた

---

## ✨ まとめ

- `Route::resource()` は7つ作る。使わないものは `except()` / `only()` で外す
- バリデーションとフォームは共通化する。コピペは「片方だけ直す」事故を生む
- 画面の入力項目とDBの列は一致しないことがある（`all_day`）
- グローバルスコープが効かない場所（`User`）は、自分で `Rule::in()` で絞る
- 表示を作る前にシーダーでデータを入れておくと、次のセクションで表示に集中できる

次の 9-6 では、このアプリの心臓である週タイムラインを作ります。
