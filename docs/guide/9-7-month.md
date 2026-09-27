# 9-7 月カレンダー・記念日・提出物

📝 **このハンズオンで使う機能**: Carbon による月のグリッド生成、クエリパラメータによる表示の切り替え、`when()` による条件つき検索
📝 **前提知識**: 9-6 の週タイムライン

---

## 🎯 このセクションで学ぶこと

- 月をまたぐカレンダーのマス目を作る
- 表示の切り替え（行事名の ON/OFF・子どもの絞り込み）を**URLのパラメータ**で持ち回る
- `when()` で「条件があるときだけ」検索条件を足す
- 記念日と提出物の CRUD を作る

---

## 導入: 見せる情報を、画面ごとに変える

週タイムラインは「今週をどう回すか」の画面でした。月カレンダーは「先の予定を見わたす」画面です。

役割が違うので、**見せる情報も変えます**。1マスが小さいので、予定名まで入れると読めません。「誰かの予定がある」ことだけ色まるで示し、詳しくは週表示へ飛べばいい。**あえて情報を削る**のが月表示の設計です。

---

## 🧠 先輩エンジニアの思考プロセス

表示の切り替え（フィルタ）を実装するとき、状態をどこに持つかを最初に決めます。

| 持ち方 | 特徴 |
|---|---|
| **URLのクエリパラメータ** | リンクで共有できる。ブラウザの戻るが効く。サーバー側で処理できる |
| セッション | URLが汚れない。ただし共有できず、戻るも効かない |
| JavaScript（画面上だけ） | 通信なしで速い。ただしリロードで消える |

今回は**クエリパラメータ**にします。「9月の、ゆいだけ」の画面をそのまま共有できるからです。加えて、絞り込みを**DBの検索条件として渡せる**ので、画面側で弾くより速くなります。

---

## 📌 作業前の確認

- 9-6 の週タイムラインが動いている
- 記念日と提出物のテーブルは 9-3 で作成済み

---

## 月のマス目をどう作るか

9月1日が火曜だとすると、カレンダーの1マス目（月曜）は8月31日です。7列にきれいに収めるには、**前後の月まで表示範囲に入れる**必要があります。

```php
$month     = $base->copy()->startOfMonth();                        // 9月1日
$gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);          // 8月31日（月）
$gridEnd   = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);  // 10月4日（日）
```

前後の月のマスは `$day->month !== $month->month` で判定し、薄い色にします。

---

## 🏃 実践: 月カレンダーを作る

### 🏃 Step 1: MonthController を作る

```bash
# irodori ディレクトリで実行
sail artisan make:controller MonthController
```

```php
<?php
// app/Http/Controllers/MonthController.php

namespace App\Http\Controllers;

use App\Models\Anniversary;
use App\Models\Event;
use App\Models\Lesson;
use App\Models\Member;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * 月カレンダー表示。
 *
 * 表示の切り替えを2つ持つ（どちらも URL のパラメータで持ち回る）。
 *   ?names=0   行事名をかくして、色まるだけにする
 *   ?member=3  その子の予定だけにしぼる
 */
class MonthController extends Controller
{
    public function index(Request $request)
    {
        // ---- 1. 表示する月と、表示の切り替え ------------------------
        $base  = $request->filled('month')
            ? Carbon::parse($request->month . '-01')
            : Carbon::today()->startOfMonth();

        $month = $base->copy()->startOfMonth();

        $showNames  = $request->input('names', '1') !== '0';
        $onlyMember = $request->filled('member') ? (int) $request->member : null;

        // カレンダーのマス目は、月をまたいで月曜はじまり〜日曜おわりで埋める
        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd   = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $days = collect();
        for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) {
            $days->push($d->copy());
        }

        // ---- 2. データをまとめて取る --------------------------------
        $members = Member::orderBy('sort_order')->orderBy('id')->get();

        // しぼり込みは、DBの検索条件として渡す（画面側で弾くより速い）
        $lessons = Lesson::with('member')
            ->when($onlyMember, fn ($q) => $q->where('member_id', $onlyMember))
            ->get();

        $events = Event::with('member')
            ->whereBetween('date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->when($onlyMember, fn ($q) => $q->where('member_id', $onlyMember))
            ->get();

        $anniversaries = Anniversary::with('member')
            ->when($onlyMember, fn ($q) => $q->where('member_id', $onlyMember))
            ->get();

        $dues = Task::with('member')
            ->where('is_done', false)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->when($onlyMember, fn ($q) => $q->where('member_id', $onlyMember))
            ->get();

        // ---- 3. 1日ぶんの中身を組み立てる ---------------------------
        $cells = [];

        foreach ($days as $day) {
            $key    = $day->toDateString();
            $colors = [];      // その日に予定がある子の色（重複なし）
            $chips  = [];      // 終日の予定・記念日・しめきり

            foreach ($lessons as $lesson) {
                if ($lesson->activeOn($day)) {
                    $colors[] = $lesson->member->color;
                }
            }

            foreach ($events as $event) {
                if (! $event->date->isSameDay($day)) {
                    continue;
                }
                $colors[] = $event->member->color ?? '#A79BC0';

                if ($event->is_all_day) {
                    $chips[] = [
                        'kind'  => 'event',
                        'title' => $event->title,
                        'color' => $event->member->color ?? '#A79BC0',
                        'light' => $event->member->light_color ?? '#EAE6F1',
                    ];
                }
            }

            foreach ($anniversaries as $anniv) {
                if (! $anniv->fallsOn($day)) {
                    continue;
                }
                $age      = $anniv->ageOn($day);
                $colors[] = $anniv->member->color ?? '#C9A227';
                $chips[]  = [
                    'kind'  => 'anniv',
                    'title' => $anniv->title . ($age !== null ? "（{$age}）" : ''),
                    'color' => $anniv->member->color ?? '#C9A227',
                    'light' => '#FBF2D8',
                ];
            }

            foreach ($dues as $due) {
                if ($due->due_date->isSameDay($day)) {
                    $chips[] = [
                        'kind'  => 'due',
                        'title' => $due->title,
                        'color' => $due->member->color ?? '#A79BC0',
                        'light' => '#FFF6EC',
                    ];
                }
            }

            $cells[$key] = [
                'colors' => array_values(array_unique($colors)),
                'chips'  => $chips,
            ];
        }

        return view('month.index', [
            'month'      => $month,
            'days'       => $days,
            'cells'      => $cells,
            'members'    => $members,
            'showNames'  => $showNames,
            'onlyMember' => $onlyMember,
            'prev'       => $month->copy()->subMonth()->format('Y-m'),
            'next'       => $month->copy()->addMonth()->format('Y-m'),
        ]);
    }
}
```

### 覚えておくポイント

**`when()` の使い方**

```php
->when($onlyMember, fn ($q) => $q->where('member_id', $onlyMember))
```

第1引数が「真」のときだけ、第2引数のクロージャを実行します。これがないと、こう書くことになります。

```php
$query = Lesson::with('member');
if ($onlyMember) {
    $query->where('member_id', $onlyMember);
}
$lessons = $query->get();
```

`when()` を使うと**メソッドチェーンを切らずに**書けます。条件が増えても読みやすさが保てます。

**絞り込みをDB側でやる理由**

取ってきてから PHP で弾くこともできますが、無駄なデータをメモリに載せることになります。**DBに任せられるものはDBに任せる**のが原則です。

**`array_unique()` のあとに `array_values()`**

`array_unique()` はキーを保持するので、`[0 => 'a', 2 => 'c']` のような飛び飛びの配列になります。Blade で `@foreach` する分には問題ありませんが、`json_encode` すると配列ではなくオブジェクトになって混乱の元です。`array_values()` で詰め直しておきます。

**`$request->input('names', '1') !== '0'`**

デフォルトは「出す」。`?names=0` のときだけ「かくす」。**既定値をどちらにするかは設計判断**で、今回は「初めて見た人が情報を見られる」ほうを既定にしました。

---

### 🏃 Step 2: 月カレンダーのビューを作る

```bash
# irodori ディレクトリで実行
mkdir -p resources/views/month
touch resources/views/month/index.blade.php
```

切り替えボタンの部分が肝です。

```blade
{{-- resources/views/month/index.blade.php の切り替え部分 --}}
@php
  // いまの切り替え状態を保ったままリンクを作るための材料
  $base = ['month' => $month->format('Y-m')];
@endphp

<div class="mtoggle">
  <span class="lbl">行事名</span>
  <a href="{{ route('month', $base + ['names' => '1', 'member' => $onlyMember]) }}"
     class="{{ $showNames ? 'on' : '' }}">出す</a>
  <a href="{{ route('month', $base + ['names' => '0', 'member' => $onlyMember]) }}"
     class="{{ $showNames ? '' : 'on' }}">かくす</a>

  <span class="sep"></span>

  <span class="lbl">だれの</span>
  <a href="{{ route('month', $base + ['names' => $showNames ? null : '0']) }}"
     class="{{ $onlyMember ? '' : 'on' }}">ぜんいん</a>
  @foreach ($members as $member)
    <a href="{{ route('month', $base + ['names' => $showNames ? null : '0', 'member' => $member->id]) }}"
       class="{{ $onlyMember === $member->id ? 'on' : '' }}">
      <i style="background:{{ $member->color }}"></i>{{ $member->name }}
    </a>
  @endforeach
</div>
```

マス目の部分。

```blade
<div class="mgrid">
  @foreach ($days as $day)
    @php
      $key   = $day->toDateString();
      $cell  = $cells[$key];
      $other = $day->month !== $month->month;   // 前後の月のマス
    @endphp

    <a class="mcell {{ $other ? 'other' : '' }} {{ $day->isWeekend() ? 'weekend' : '' }} {{ $day->isToday() ? 'today' : '' }}"
       href="{{ route('home', ['date' => $key, 'd' => $key]) }}">

      <span class="d">{{ $day->day }}</span>

      {{-- 予定がある子の色まる --}}
      @if ($cell['colors'])
        <span class="mdots">
          @foreach ($cell['colors'] as $color)
            <i style="background:{{ $color }}"></i>
          @endforeach
        </span>
      @endif

      {{-- 「かくす」を選んでいるときは出さない --}}
      @if ($showNames)
        @foreach (array_slice($cell['chips'], 0, 3) as $chip)
          @if ($chip['kind'] === 'due')
            <span class="mchip due">{{ $chip['title'] }}</span>
          @elseif ($chip['kind'] === 'anniv')
            <span class="mchip anniv">{{ $chip['title'] }}</span>
          @else
            <span class="mchip"
                  style="background:{{ $chip['light'] }};border-color:{{ $chip['color'] }};">
              {{ $chip['title'] }}
            </span>
          @endif
        @endforeach
      @endif
    </a>
  @endforeach
</div>
```

### 覚えておくポイント

**`route('month', $base + ['names' => null])`**

配列の `+` は**キーが重複しない部分だけを足す**演算子です（`array_merge` と違い、先に書いたほうが優先されます）。

そして `null` の値は、URL を作るときに**自動的に省かれます**。だから `names=1` のときはパラメータが付かず、URL がきれいに保たれます。

**`array_slice($cell['chips'], 0, 3)`**

1マスに出すのは最大3件まで。それ以上あると縦に伸びて、カレンダーのマス目が崩れます。**表示の上限を決めておく**のは、崩れないUIを作るコツです。

**スマホでは `mchip` を隠す**

```css
@media (max-width:767px){
  .mchip{display:none;}   /* スマホでは色まるだけにする */
}
```

幅が狭いと文字が読めません。**画面幅ごとに見せる情報を変える**のは、9-6 の「狭いときは情報を減らす」と同じ考え方です。

---

## 🏃 実践: 記念日と提出物

### 🏃 Step 3: AnniversaryController を作る

```bash
# irodori ディレクトリで実行
sail artisan make:controller AnniversaryController
```

```php
<?php
// app/Http/Controllers/AnniversaryController.php

namespace App\Http\Controllers;

use App\Models\Anniversary;
use App\Models\Member;
use Illuminate\Http\Request;

class AnniversaryController extends Controller
{
    public function index()
    {
        return view('anniversaries.index', [
            'anniversaries' => Anniversary::with('member')
                ->orderBy('month')->orderBy('day')->get(),
            'members' => Member::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Anniversary::create($this->validated($request));

        return back()->with('status', '記念日を登録しました。');
    }

    public function edit(Anniversary $anniversary)
    {
        return view('anniversaries.edit', [
            'anniversary' => $anniversary,
            'members'     => Member::orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Anniversary $anniversary)
    {
        $anniversary->update($this->validated($request));

        return redirect()->route('anniversaries.index')->with('status', '変更を保存しました。');
    }

    public function destroy(Anniversary $anniversary)
    {
        $anniversary->delete();

        return redirect()->route('anniversaries.index')->with('status', '削除しました。');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'member_id'  => ['nullable', 'exists:members,id'],
            'title'      => ['required', 'string', 'max:40'],
            'month'      => ['required', 'integer', 'between:1,12'],
            'day'        => ['required', 'integer', 'between:1,31'],
            'start_year' => ['nullable', 'integer', 'between:1900,2100'],
        ], [], [
            'title'      => '記念日の名前',
            'month'      => '月',
            'day'        => '日',
            'start_year' => '始まった年',
        ]);
    }
}
```

📝 **`day` を `between:1,31` にしている理由**: 「2月31日」を弾こうとすると、月ごとの日数を見た複雑な検証が必要になります。実害が小さい（`fallsOn()` が false を返して表示されないだけ）ので、ここは割り切っています。**どこまで厳密にやるかも設計判断**です。

### 🏃 Step 4: TaskController を作る

```bash
# irodori ディレクトリで実行
sail artisan make:controller TaskController
```

```php
<?php
// app/Http/Controllers/TaskController.php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index()
    {
        return view('tasks.index', [
            'todo'    => Task::with('member')->where('is_done', false)
                            ->orderByRaw('due_date IS NULL, due_date')->get(),
            'done'    => Task::with('member')->where('is_done', true)
                            ->latest('updated_at')->limit(20)->get(),
            'members' => Member::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Task::create($request->validate([
            'member_id' => ['nullable', 'exists:members,id'],
            'title'     => ['required', 'string', 'max:40'],
            'due_date'  => ['nullable', 'date'],
        ], [], [
            'title'    => 'やること',
            'due_date' => '期限',
        ]));

        return back()->with('status', '追加しました。');
    }

    /** チェックのオン・オフ */
    public function update(Request $request, Task $task)
    {
        $task->update(['is_done' => $request->boolean('is_done')]);

        return back();
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return back()->with('status', '削除しました。');
    }
}
```

🔑 **`orderByRaw('due_date IS NULL, due_date')`**

「期限なし」を最後に回しつつ、期限のあるものは近い順に並べます。

MySQL では `NULL` は普通にソートすると先頭に来ます。`due_date IS NULL` は真なら1・偽なら0を返すので、これで並べると「期限あり（0）が先、なし（1）が後」になります。そのあと `due_date` で並べます。

**`orderByRaw()` は SQL をそのまま書く**ので、ユーザーの入力を混ぜてはいけません（SQLインジェクションになります）。固定の文字列のときだけ使います。

### 🏃 Step 5: ルートを足す

```php
// routes/web.php の has.family グループ内

use App\Http\Controllers\AnniversaryController;
use App\Http\Controllers\MonthController;
use App\Http\Controllers\TaskController;

        Route::get('/month', [MonthController::class, 'index'])->name('month');
        Route::resource('tasks', TaskController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('anniversaries', AnniversaryController::class)->except(['show', 'create']);
```

### 🏃 Step 6: 記念日を登録して確認する

画面から記念日を1件登録し、月カレンダーに出ることを確認します。

**確認したいポイント**: 登録した記念日が、**来年の同じ日にも出る**こと。月を1年ぶん進めて確かめてください。日付ではなく「月と日」を持った設計の効果が、ここで確認できます。

---

## ⚠️ よくあるエラー

| 症状 | 原因 |
|---|---|
| 月のマスが7列に揃わない | `startOfWeek` / `endOfWeek` の曜日指定がない |
| 切り替えボタンを押すと月が今月に戻る | リンクに `month` パラメータを含めていない |
| 絞り込みが効かない | `when()` の第1引数が `0` や `''` になっている（`0` は偽と判定される） |
| 記念日が来年に出ない | `date` 型で持ってしまっている。月と日で持つ設計になっているか確認 |
| 期限なしのタスクが先頭に来る | `orderByRaw('due_date IS NULL, due_date')` を使う |

---

## ✅ 完成チェックリスト

- 月をまたぐマス目を作り、前後の月を薄く表示できた
- 表示の切り替えを URL のパラメータで持ち回れた（月を移動しても設定が残る）
- `when()` で条件つきの検索を書けた
- 記念日が翌年にも表示されることを確認できた
- 提出物の並び順（期限なしを最後に）を実装できた
- 月表示であえて情報を削っている理由を説明できる

---

## ✨ まとめ

- 画面の役割が違えば、見せる情報も変える。月表示は「見わたす」ためにあえて削る
- 表示の切り替えは URL のパラメータで持つと、共有できて戻るも効く
- 絞り込みは DB の検索条件として渡す。`when()` でチェーンを切らずに書ける
- 「月と日だけ」で持った記念日が、翌年にも自動で出ることを確認する
- 厳密さと手間のバランスも設計判断（2月31日をどこまで弾くか）

次の 9-8 では、外部の天気予報APIを組み込みます。
