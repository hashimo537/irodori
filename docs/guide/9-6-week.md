# 9-6 週タイムラインと重なりの横並び

📝 **このハンズオンで使う機能**: Carbon による日付操作、CSS の絶対配置、配列の整形
📝 **前提知識**: 9-5 の CRUD とシーダー

---

## 🎯 このセクションで学ぶこと

- 「1分 = 1px」という単位を決めて、時刻を画面上の位置に変換する
- 習い事（毎週くりかえし）を、表示のたびに各週へ展開する
- 時間が重なる予定を検出し、**横に並べる**アルゴリズムを実装する
- N+1 問題を避けながら、1週間ぶんのデータを組み立てる

---

## 導入: このアプリの心臓

ここが山場です。そして、このアプリの存在意義そのものでもあります。

TimeTree のようなマス目式のカレンダーは、「火曜に3件予定がある」ことは分かっても、「16:30から18:00まで拘束される」ことが見えません。**時間の長さを、帯の長さで見せる**のがこのアプリの価値です。

難しそうに見えますが、核心は驚くほど単純な計算です。

---

## 🧠 先輩エンジニアの思考プロセス

こういう「画面に図形を配置する」系の実装で最初にやるのは、**単位を決めること**です。

今回は「1分 = 1px」と決めます。すると 6:00〜22:00 で 960px。スクロールでちょうど扱える高さです。この1行を決めた瞬間に、あとの計算は全部ただの引き算になります。

もうひとつ意識するのが、**計算をどこでやるか**。ブラウザ（JavaScript）でもできますが、データを持っているのはサーバー（PHP）です。PHP で位置まで計算して、完成した HTML を返すほうが素直です。JavaScript は「最初のスクロール位置」だけに使います。

重なりの横並びは、見た目は複雑ですが**区間スケジューリング**という定番の問題で、解き方が決まっています。自力で思いつこうとせず、「塊に分ける → 列を割り当てる」という型を覚えてしまうのが早いです。

---

## 📌 作業前の確認

- 9-5 のシーダーでデモデータが入っている
- 土曜に時間が重なる習い事（サッカー 9:00-11:00 と 絵画 10:00-11:30）が入っている

---

## 位置の計算

```
1分 = 1px、タイムラインは 6:00 から始まる（= 360分）

スイミングは 16:30〜18:00
  16:30 = 16×60 + 30 = 990分
  18:00 = 18×60 +  0 = 1080分

  上からの距離 = 990 − 360 = 630px
  帯の高さ     = 1080 − 990 = 90px
```

これだけです。あとは HTML にこう書けば、帯がその場所に置かれます。

```html
<div class="slot" style="top:630px; height:90px;">スイミング</div>
```

CSS 側は2行が本質です。

```css
.col  { position: relative; }   /* 基準になる箱 */
.slot { position: absolute; }   /* その中で自由な位置に置く */
```

⚠️ **`position: absolute` は「いちばん近い `position: relative` の親」を基準に配置されます。** `.col` に `relative` を付け忘れると、帯が画面全体の左上を基準に飛んでいきます。**ここは9割の人が1回はハマります。**

---

## 重なりを横に並べるアルゴリズム

土曜の午前に、こういう予定があるとします。

```
9:00 ─────── 11:00   サッカー
       10:00 ─────── 11:30   絵画
                        13:00 ─── 14:00   買い物
```

サッカーと絵画は重なっています。買い物は重なっていません。求めたいのは「サッカーと絵画だけを2列に分け、買い物は全幅を使う」という配置です。

### ① 塊（クラスタ）に分ける

時間がつながっているグループに切り分けます。

```
開始時刻の順に並べる
 ↓
1件ずつ見ていき、「いま作っている塊の終わり」より後に始まるなら、塊を切る

サッカー(9:00-11:00) … 塊1に入れる。塊の終わり = 11:00
絵画(10:00-11:30)   … 10:00 < 11:00 なので塊1に入れる。塊の終わり = 11:30
買い物(13:00-14:00) … 13:00 >= 11:30 なので塊を切って、塊2にする

→ 塊1 = [サッカー, 絵画]、塊2 = [買い物]
```

🔑 **「塊の終わり」は、その塊で**いちばん遅い終了時刻**です。** 直前の1件ではありません。ここを間違えると、A(9-12) B(10-11) C(11:30-13) のようなケースで C が別の塊になってしまいます。

### ② 塊の中で列を割り当てる

塊の中を、左から順に「空いている列」へ入れていきます。

```
各列について「その列に最後に入れた予定の終了時刻」を覚えておく

サッカー … 列がまだ無い → 列0を作る。列0の終わり = 11:00
絵画     … 列0は 11:00 まで埋まっている。10:00 < 11:00 なので入れない
           → 列1を作る。列1の終わり = 11:30

→ 塊1の列数 = 2
```

### ③ 幅を決める

```
塊1（列数2）… 幅 = 100 ÷ 2 = 50%
  サッカー … 列0 → 左端 = 0%、幅 50%
  絵画     … 列1 → 左端 = 50%、幅 50%

塊2（列数1）… 幅 = 100%
  買い物   … 列0 → 左端 = 0%、幅 100%
```

**重なっていない予定は全幅のまま**です。全部を一律に分割しないのがポイントで、これをやると予定が1件しかない日まで細くなってしまいます。

---

## 🏃 実践: 週タイムラインを作る

### 🏃 Step 1: WeekController を作る

```bash
# irodori ディレクトリで実行
sail artisan make:controller WeekController
```

```php
<?php
// app/Http/Controllers/WeekController.php

namespace App\Http\Controllers;

use App\Models\Anniversary;
use App\Models\Event;
use App\Models\Lesson;
use App\Models\Member;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * ホーム画面（週タイムライン）。
 * ここが このアプリの心臓。
 */
class WeekController extends Controller
{
    /** タイムラインの表示範囲：6:00 〜 22:00、1分 = 1px */
    public const START_MIN = 6 * 60;
    public const END_MIN   = 22 * 60;

    public function index(Request $request)
    {
        // ---- 1. 表示する週を決める ----------------------------------
        $base  = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();
        $start = $base->copy()->startOfWeek(Carbon::MONDAY);
        $end   = $start->copy()->addDays(6);

        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));

        // ---- 2. データをまとめて取る（N+1を避ける） -------------------
        $members = Member::orderBy('sort_order')->orderBy('id')->get();
        $lessons = Lesson::with('member', 'pickup')->get();

        $events = Event::with('member', 'pickup')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $anniversaries = Anniversary::with('member')->get();

        // ---- 3. 1日ぶんの「帯」を組み立てる ---------------------------
        $slotsByDate  = [];
        $allDayByDate = [];   // 終日の予定＋記念日

        foreach ($days as $day) {
            $key    = $day->toDateString();
            $slots  = [];
            $allDay = [];

            // 習い事（毎週くりかえし）をこの日に展開する
            foreach ($lessons as $lesson) {
                if (! $lesson->activeOn($day)) {
                    continue;
                }
                $slots[] = $this->makeSlot(
                    type:   'lesson',
                    id:     $lesson->id,
                    title:  $lesson->title,
                    place:  $lesson->place,
                    member: $lesson->member,
                    start:  $lesson->start_time->format('H:i'),
                    end:    $lesson->end_time->format('H:i'),
                    pickup: $lesson->pickup?->name,
                );
            }

            // 単発の予定
            foreach ($events as $event) {
                if (! $event->date->isSameDay($day)) {
                    continue;
                }

                if ($event->is_all_day) {
                    $allDay[] = [
                        'kind'   => 'event',
                        'id'     => $event->id,
                        'title'  => $event->title,
                        'place'  => $event->place,
                        'name'   => $event->member->name ?? '家族ぜんいん',
                        'color'  => $event->member->color ?? '#A79BC0',
                        'light'  => $event->member->light_color ?? '#EAE6F1',
                        'note'   => filled($event->note),
                        'pickup' => $event->pickup?->name,
                    ];
                    continue;
                }

                $slots[] = $this->makeSlot(
                    type:   'event',
                    id:     $event->id,
                    title:  $event->title,
                    place:  $event->place,
                    member: $event->member,
                    start:  $event->start_time->format('H:i'),
                    end:    optional($event->end_time)->format('H:i')
                            ?? $event->start_time->copy()->addHour()->format('H:i'),
                    note:   filled($event->note),
                    pickup: $event->pickup?->name,
                );
            }

            // 記念日（毎年おなじ月日）
            foreach ($anniversaries as $anniv) {
                if (! $anniv->fallsOn($day)) {
                    continue;
                }
                $age = $anniv->ageOn($day);
                $allDay[] = [
                    'kind'   => 'anniv',
                    'id'     => $anniv->id,
                    'title'  => $anniv->title . ($age !== null ? "（{$age}さい）" : ''),
                    'place'  => null,
                    'name'   => $anniv->member->name ?? '家族',
                    'color'  => $anniv->member->color ?? '#C9A227',
                    'light'  => $anniv->member->light_color ?? '#FBF2D8',
                    'note'   => false,
                    'pickup' => null,
                ];
            }

            // 時間が重なっている予定を、横に並べる
            $slotsByDate[$key]  = $this->layout($slots);
            $allDayByDate[$key] = $allDay;
        }

        // ---- 4. 今週の提出物（画面いちばん上のバー） -----------------
        $todos = Task::with('member')
            ->where('is_done', false)
            ->where(function ($q) use ($end) {
                $q->whereNull('due_date')->orWhere('due_date', '<=', $end->toDateString());
            })
            ->orderByRaw('due_date IS NULL, due_date')
            ->limit(5)
            ->get();

        return view('week.index', [
            'start'        => $start,
            'days'         => $days,
            'members'      => $members,
            'slotsByDate'  => $slotsByDate,
            'allDayByDate' => $allDayByDate,
            'todos'        => $todos,
            'startMin'     => self::START_MIN,
            'endMin'       => self::END_MIN,
        ]);
    }

    /**
     * 重なっている帯を横に並べるための計算。
     *
     * ① 時間がつながっている塊（クラスタ）に分ける
     * ② 塊の中で、予定を左から順に「空いている列」へ入れる
     * ③ 塊の列数が決まるので、1本あたりの幅が決まる
     *
     * 結果として col（何列目か）と cols（その塊の列数）が付く。
     */
    private function layout(array $slots): array
    {
        usort($slots, fn ($a, $b) =>
            [$a['start_min'], $a['end_min']] <=> [$b['start_min'], $b['end_min']]);

        // ① 塊に分ける
        $clusters   = [];
        $current    = [];
        $clusterEnd = 0;

        foreach ($slots as $slot) {
            // いま作っている塊のどれとも重ならない → 塊を切る
            if ($current && $slot['start_min'] >= $clusterEnd) {
                $clusters[] = $current;
                $current    = [];
                $clusterEnd = 0;
            }
            $current[]  = $slot;
            $clusterEnd = max($clusterEnd, $slot['end_min']);
        }
        if ($current) {
            $clusters[] = $current;
        }

        // ② 塊ごとに列を割りあてる
        $out = [];
        foreach ($clusters as $cluster) {
            $columnEnds = [];   // 各列の「最後に入れた予定の終わり時刻」
            $assigned   = [];

            foreach ($cluster as $i => $slot) {
                $placed = null;
                foreach ($columnEnds as $col => $endMin) {
                    if ($slot['start_min'] >= $endMin) {   // その列は空いている
                        $placed = $col;
                        break;
                    }
                }
                if ($placed === null) {
                    $placed = count($columnEnds);          // 新しい列を作る
                }
                $columnEnds[$placed] = $slot['end_min'];
                $assigned[$i]        = $placed;
            }

            // ③ 幅を決める
            $total = count($columnEnds);
            foreach ($cluster as $i => $slot) {
                $slot['col']  = $assigned[$i];
                $slot['cols'] = $total;
                $out[]        = $slot;
            }
        }

        return $out;
    }

    /**
     * 帯1本ぶんのデータ。
     * start_min / end_min がそのまま CSS の top / height (px) になる。
     */
    private function makeSlot(string $type, int $id, string $title, ?string $place,
                              ?Member $member, string $start, string $end,
                              bool $note = false, ?string $pickup = null): array
    {
        return [
            'type'      => $type,
            'id'        => $id,
            'title'     => $title,
            'place'     => $place,
            'note'      => $note,
            'pickup'    => $pickup,
            'name'      => $member->name ?? '家族ぜんいん',
            'color'     => $member->color ?? '#A79BC0',
            'light'     => $member->light_color ?? '#EAE6F1',
            'start'     => $start,
            'end'       => $end,
            'start_min' => $this->toMin($start),
            'end_min'   => $this->toMin($end),
        ];
    }

    private function toMin(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }
}
```

### 覚えておくポイント

**`copy()` を忘れない**

Carbon のメソッドは**元の値を書き換えます**。

```php
$start->addDays(3);          // $start 自体が3日進んでしまう！
$start->copy()->addDays(3);  // 正しい
```

これは本当によくバグります。ループの中で `$start` を使い回すと、週がどんどんずれていきます。**日付計算のときは反射的に `copy()` を打つ**癖をつけてください。

**`startOfWeek(Carbon::MONDAY)` と明示する**

曜日の始まりはロケール設定に左右されます。引数で明示すれば、設定に関係なく月曜始まりになります。

**`with('member', 'pickup')` ＝ N+1 対策**

習い事20件それぞれで `$lesson->member->name` を呼ぶと、SQL が20本飛びます。`with()` を書けば2本で済みます。

**なぜモデルではなく配列を返すのか**

`makeSlot()` は Eloquent のモデルではなく、**ただの配列**を返しています。理由は2つあります。

1. 習い事と予定という**違うモデル**を、同じ形にして混ぜたいから
2. Blade 側で `$slot['start_min']` と書けば、それが px であることが明確だから

「モデルをそのまま渡す」が常に正解ではありません。**画面が必要とする形に整えてから渡す**という選択肢を持っておいてください。

**名前付き引数（`type:` `id:` …）**

PHP 8 の機能です。引数が8個もあると、順番で渡すと何がなんだか分かりません。名前を書けば読めますし、順番を間違えません。

**`$lesson->pickup?->name`**

お迎え担当が設定されていなければ `pickup` は null です。`?->` を使うと、null でもエラーにならず null を返します。

**`optional($event->end_time)->format('H:i') ?? ...`**

終了時刻が未入力なら、開始の1時間後を仮の終わりにしています。高さ0の帯を作らないための処理です。

---

### 🏃 Step 2: 週タイムラインのビューを作る

```bash
# irodori ディレクトリで実行
mkdir -p resources/views/week
touch resources/views/week/index.blade.php
```

核心部分だけを載せます（全文は既存プロジェクトから流用してください）。

```blade
{{-- resources/views/week/index.blade.php の核心部分 --}}

{{-- 時間のタイムライン本体 --}}
<div class="scroller" id="scroller">
  <div class="grid" style="height:{{ $endMin - $startMin + 20 }}px;">

    {{-- 左はしの時間メモリ --}}
    <div class="hours" style="height:{{ $endMin - $startMin + 20 }}px;">
      @for ($h = intdiv($startMin, 60); $h <= intdiv($endMin, 60); $h++)
        <div class="h" style="top:{{ $h * 60 - $startMin }}px;">{{ $h }}:00</div>
      @endfor
    </div>

    {{-- 曜日ごとの列 --}}
    @foreach ($days as $day)
      @php $slots = $slotsByDate[$day->toDateString()]; @endphp
      <div class="col {{ $day->isWeekend() ? 'weekend' : '' }}">

        {{-- 1時間ごとの罫線 --}}
        @for ($h = intdiv($startMin, 60); $h <= intdiv($endMin, 60); $h++)
          <div class="rule" style="top:{{ $h * 60 - $startMin }}px;"></div>
          @if ($h * 60 < $endMin)
            <div class="rule half" style="top:{{ $h * 60 - $startMin + 30 }}px;"></div>
          @endif
        @endfor

        {{-- 予定の帯。ここが このアプリの心臓 --}}
        @foreach ($slots as $slot)
          @php
            $top   = $slot['start_min'] - $startMin;                   // 上からの距離(px)
            $bandH = max($slot['end_min'] - $slot['start_min'], 34);   // 高さ(px)

            // 重なっている本数ぶんだけ横に割る
            $cols  = $slot['cols'];
            $width = 100 / $cols;
            $left  = $slot['col'] * $width;

            $route = $slot['type'] === 'lesson'
                     ? route('lessons.edit', $slot['id'])
                     : route('events.edit', $slot['id']);
          @endphp
          <a class="slot {{ $bandH < 52 ? 'narrow' : '' }} {{ $cols > 1 ? 'side' : '' }}"
             href="{{ $route }}"
             style="top:{{ $top }}px; height:{{ $bandH }}px;
                    left:calc({{ $left }}% + 3px);
                    width:calc({{ $width }}% - 6px);
                    background:{{ $slot['light'] }};
                    border-left:4px solid {{ $slot['color'] }};">
            <span class="t">{{ $slot['title'] }}@if ($slot['note'])<span class="stamp">メモ</span>@endif</span>
            <span class="m">{{ $slot['name'] }}@if ($slot['pickup'])<span class="pickup">迎 {{ $slot['pickup'] }}</span>@endif</span>
            <span class="time">{{ $slot['start'] }}–{{ $slot['end'] }}@if ($slot['place']) ・{{ $slot['place'] }}@endif</span>
          </a>
        @endforeach

      </div>
    @endforeach

  </div>
</div>

@push('scripts')
<script>
  // 夕方あたりが最初に見えるようにスクロールしておく
  const sc = document.getElementById('scroller');
  if (sc) sc.scrollTop = {{ 14 * 60 - $startMin }} - 60;
</script>
@endpush
```

CSS 側はこうなります。

```css
/* public/css/irodori.css に追記 */
.grid{display:grid;grid-template-columns:52px repeat(7,1fr);position:relative;}
.col{position:relative;border-left:1px solid var(--line);}   /* ← 基準になる箱 */
.slot{
  position:absolute;             /* ← .col を基準に配置される */
  right:auto;                    /* ← 横並びのため右端固定をやめる */
  border-radius:8px;padding:5px 7px 5px 9px;
  overflow:hidden;font-size:12px;line-height:1.35;
  text-decoration:none;color:var(--ink);display:block;
}
.slot.side .time{display:none;}  /* 横に割ったら時刻は省く */
.rule{position:absolute;left:0;right:0;height:1px;background:var(--line);}
```

### 覚えておくポイント

**`max($..., 34)` の意図**: 10分の予定だと高さ10pxになって文字が読めません。最低34pxを確保しています。**データ上の正しさと、読めることは別**です。

**`calc({{ $left }}% + 3px)`**: 左端の位置はパーセント、すき間は px。単位の違う値を混ぜられるのが `calc()` です。すき間を % にすると、列が増えたときに詰まりすぎます。

**`right:auto` が必要な理由**: 横並びをしない設計のときは `left:3px; right:3px` で幅を作れました。横並びでは `left` と `width` を指定するので、`right` が残っていると衝突します。CSS を後から拡張するときの典型的なつまずきです。

**`.slot.side .time{display:none}`**: 2列に割ると幅が半分になり、「9:00–11:00 ・東小 校庭」が入りません。**狭いときは情報を減らす**という判断です。何を残して何を削るかは、優先順位の設計です。

---

### 🏃 Step 3: 動作を確認する

```bash
sail up -d
```

ブラウザで `http://localhost` → ログイン（`demo@example.com` / `password`）。

確認するポイント。

1. **火曜の夕方**: ゆいのスイミング（16:30-18:00）とあおいの体操（15:30-16:30）が、時間差で縦に並んでいる
2. **土曜の午前**: サッカー（9:00-11:00）と絵画（10:00-11:30）が**左右に分かれている**
3. **土曜の午後**: じいじの家（14:00-16:30）は重なっていないので**全幅**を使っている
4. 帯をクリックすると編集画面が開く

3 が確認できれば、`layout()` が正しく動いています。「全部が半分の幅になっていないか」を必ず見てください。

---

## ⚠️ よくあるエラー

| 症状 | 原因 |
|---|---|
| 帯が画面の左上に固まっている | `.col` に `position: relative` がない |
| 帯の位置がずれる | `$casts` の `datetime:H:i` が効いていない。モデルを確認 |
| 週がどんどんずれていく | `copy()` の付け忘れ |
| 重なっていない予定まで細くなる | `layout()` の塊分けで、`$clusterEnd` を `max()` で更新していない |
| 全部が1列に重なる | `$slot['cols']` が Blade に渡っていない。`layout()` を通しているか確認 |
| 一覧が異常に遅い | N+1 問題。`with()` を確認 |
| `Undefined array key "cols"` | `$slotsByDate[$key] = $slots;` のまま。`$this->layout($slots)` を通す |

---

## ✅ 完成チェックリスト

- 「1分 = 1px」という単位を決め、`top` と `height` の計算式を説明できる
- `position: relative` / `absolute` の親子関係を説明できる
- 習い事が展開されて各週に表示される仕組みを説明できる
- 重なりの横並びが「塊に分ける → 列を割り当てる」の2段階であることを説明できる
- **重なっていない予定が全幅を保っている**ことを画面で確認できた
- `with()` による N+1 対策を入れられた

---

## ✨ まとめ

- 単位（1分 = 1px）を先に決めると、位置の計算はただの引き算になる
- 位置の計算はサーバー（PHP）で行い、完成した HTML を返す
- くりかえしの展開は保存せず、表示のたびに組み立てる
- 重なりの横並びは**区間スケジューリング**の定番の型。塊に分けてから列を割り当てる
- 狭い場所では情報を減らす。データの正しさと読みやすさは別の問題

次の 9-7 では、月カレンダー・記念日・提出物を作ります。
