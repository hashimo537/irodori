# 9-8 天気予報API

📝 **このハンズオンで使う機能**: HTTP クライアント（`Http` ファサード）、キャッシュ、サービスクラス、依存性注入
📝 **前提知識**: 9-7 の月カレンダー

---

## 🎯 このセクションで学ぶこと

- 外部APIを使うときに、自分のアプリの中と何が違うかを理解する
- **サービスクラス**に切り出し、コントローラから依存性注入で使う
- **キャッシュ**で通信の回数を減らす
- **APIが落ちてもアプリが壊れない**書き方を身につける
- 地名から緯度経度を調べる（ジオコーディング）

---

## 導入: 自分で持っていないデータを使う

ここまで扱ってきたデータは、すべて自分のデータベースの中にありました。天気予報は違います。**よそのサーバーが持っているデータ**です。

この違いが、実装の難しさを生みます。

| 自分のDB | 外部API |
|---|---|
| ほぼ必ず応答する | 落ちていることがある |
| 数ミリ秒で返る | 数百ミリ秒〜数秒かかる |
| 何回呼んでもタダ | 回数制限があることが多い |
| 形が変わらない | 相手の都合で仕様が変わる |

つまり、**「失敗する前提」「遅い前提」「回数を減らす前提」**で書く必要があります。このセクションの設計は、ほぼ全部この3つから来ています。

---

## 🧠 先輩エンジニアの思考プロセス

外部APIを組み込むとき、最初に自問するのは「**これが動かなくても、アプリは使えるか**」です。

天気は「あったら嬉しい」機能です。天気が出ないせいでカレンダーが真っ白になるのは論外。だから **try/catch で囲んで、失敗したら空を返す**と最初に決めます。これを「グレースフル・デグラデーション（品位ある劣化）」といいます。

次に考えるのが**キャッシュ**。天気予報は1日に何度も変わるものではありません。3時間くらい前の予報でも実用上困らない。逆に、ユーザーが月を行き来するたびに通信していたら、遅いし相手のサーバーにも迷惑です。

最後に**置き場所**。コントローラに `Http::get(...)` を直接書くと、月表示でも週表示でも同じコードをコピペすることになります。サービスクラスに切り出します。

---

## APIを選ぶ

### なぜ Open-Meteo か

| | Open-Meteo | OpenWeatherMap | 気象庁の非公式JSON |
|---|---|---|---|
| APIキー | **不要** | 必要（登録） | 不要 |
| 料金 | 非商用は無料 | 無料枠あり | — |
| 日別予報 | **16日先まで** | 無料枠は5日 | 週間のみ |
| 仕様の安定性 | ドキュメントあり | ドキュメントあり | **非公式（予告なく変わる）** |

学習用としては **Open-Meteo** が最適です。**APIキーが不要**なのが大きく、`.env` にキーを書いて管理する手順をまるごと省けます。

📝 **APIキーが必要なAPIを使う場合**: キーは必ず `.env` に書き、`config/services.php` 経由で読みます。**コードに直接書かない**でください。GitHub に push した瞬間に流出します。

### ⚠️ 16日より先は出せない

これは重要な制約なので、はっきり書いておきます。

**日別の天気予報は、どのサービスでも2週間程度が限界です。** APIの制限ではなく、気象学的にそれ以上先の日別予報は精度がほぼランダムになるため、どこも提供していません。

つまり「1ヶ月ぶんの天気を出す」という要件は**技術的に実現できません**。できるのは「16日ぶん出して、それより先は空欄」です。

🔑 **できないことは、できないと早めに言う。** これはエンジニアの仕事のうちで、かなり大事な部分です。「なんとかします」と言って後から無理だと分かるより、設計の段階で伝えるほうが、全員にとって良い結果になります。代わりに「16日ぶんなら出せます」と提示できれば、話は前に進みます。

---

## 🏃 実践: 天気予報を組み込む

### 🏃 Step 1: 地点を持つ列を用意する

天気予報には緯度と経度が必要です。9-3 の `families` テーブルに、すでに3列用意してあります。

```php
$table->string('location_name')->nullable();   // 「横浜市」
$table->decimal('latitude', 8, 5)->nullable(); // 35.44780
$table->decimal('longitude', 8, 5)->nullable();
```

📝 **`decimal(8, 5)` の意味**: 全体で8桁、うち小数点以下5桁。緯度は `-90.00000` 〜 `90.00000`、経度は `-180.00000` 〜 `180.00000` なので足ります。

**なぜ `float` ではなく `decimal` か**: `float` は誤差が出ます。位置情報は比較や保存の正確さが求められるので `decimal` を使います。小数第5位で約1mの精度です。

**なぜ家族ごとに持つか**: 家族は同じ場所に住んでいるので、ユーザーごとに持つ必要はありません。**どの単位で持つかも設計判断**です。

### 🏃 Step 2: サービスクラスを作る

```bash
# irodori ディレクトリで実行
mkdir -p app/Services
touch app/Services/WeatherService.php
```

```php
<?php
// app/Services/WeatherService.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 天気予報。Open-Meteo（無料・APIキー不要）を使う。
 *
 * ・予報が出せるのは今日から16日先まで。それより先は空になる。
 * ・外部APIは落ちることがあるので、失敗しても画面は壊さない（空配列を返す）。
 * ・同じ地点を何度も問い合わせないよう、3時間キャッシュする。
 */
class WeatherService
{
    private const FORECAST_URL  = 'https://api.open-meteo.com/v1/forecast';
    private const GEOCODING_URL = 'https://geocoding-api.open-meteo.com/v1/search';

    /** 予報が出せる最大日数（Open-Meteo の上限） */
    public const MAX_DAYS = 16;

    /**
     * 天気コード → 絵文字。
     * コードの意味は WMO（世界気象機関）の世界共通の番号。
     */
    private const ICONS = [
        0  => '☀️',  1  => '🌤',  2  => '⛅️', 3  => '☁️',
        45 => '🌫',  48 => '🌫',
        51 => '🌦',  53 => '🌦',  55 => '🌦',  56 => '🌦', 57 => '🌦',
        61 => '☂️',  63 => '☂️',  65 => '☔️', 66 => '🌧', 67 => '🌧',
        71 => '❄️',  73 => '❄️',  75 => '⛄️', 77 => '❄️',
        80 => '🌦',  81 => '🌧',  82 => '🌧',
        85 => '🌨',  86 => '🌨',
        95 => '⛈',  96 => '⛈',  99 => '⛈',
    ];

    private const LABELS = [
        0 => 'はれ', 1 => 'はれ', 2 => 'くもり時々はれ', 3 => 'くもり',
        45 => 'きり', 48 => 'きり',
        51 => '小雨', 53 => '小雨', 55 => '小雨', 56 => 'こおる雨', 57 => 'こおる雨',
        61 => '雨', 63 => '雨', 65 => '強い雨', 66 => 'こおる雨', 67 => 'こおる雨',
        71 => '雪', 73 => '雪', 75 => '大雪', 77 => '雪',
        80 => 'にわか雨', 81 => 'にわか雨', 82 => '強いにわか雨',
        85 => 'にわか雪', 86 => 'にわか雪',
        95 => 'かみなり', 96 => 'かみなり', 99 => 'かみなり',
    ];

    /**
     * 日付ごとの天気を返す。
     *
     * @return array<string, array{icon:string,label:string,max:?float,min:?float}>
     *         キーは 'Y-m-d'。取れなかったときは空配列。
     */
    public function daily(?float $lat, ?float $lon): array
    {
        if (is_null($lat) || is_null($lon)) {
            return [];                      // 地点が未設定なら通信もしない
        }

        // 小数2桁に丸めてキャッシュキーにする（数百mの差で別扱いにしないため）
        $key = sprintf('weather:%.2f:%.2f', $lat, $lon);

        return Cache::remember($key, now()->addHours(3), function () use ($lat, $lon) {
            try {
                $response = Http::timeout(5)->get(self::FORECAST_URL, [
                    'latitude'      => $lat,
                    'longitude'     => $lon,
                    'daily'         => 'weather_code,temperature_2m_max,temperature_2m_min',
                    'timezone'      => 'Asia/Tokyo',
                    'forecast_days' => self::MAX_DAYS,
                ]);

                if (! $response->successful()) {
                    return [];
                }

                $daily = $response->json('daily');
                $out   = [];

                foreach ($daily['time'] ?? [] as $i => $date) {
                    $code = $daily['weather_code'][$i] ?? null;

                    $out[$date] = [
                        'icon'  => self::ICONS[$code]  ?? '・',
                        'label' => self::LABELS[$code] ?? '',
                        'max'   => $daily['temperature_2m_max'][$i] ?? null,
                        'min'   => $daily['temperature_2m_min'][$i] ?? null,
                    ];
                }

                return $out;
            } catch (\Throwable $e) {
                // 天気が出ないだけで、カレンダーは使えるべき
                Log::warning('天気予報の取得に失敗しました: ' . $e->getMessage());

                return [];
            }
        });
    }

    /**
     * 地名から緯度経度を調べる（ジオコーディング）。
     *
     * @return array<int, array{name:string,admin:string,latitude:float,longitude:float}>
     */
    public function search(string $name): array
    {
        try {
            $response = Http::timeout(5)->get(self::GEOCODING_URL, [
                'name'     => $name,
                'count'    => 5,
                'language' => 'ja',
                'format'   => 'json',
            ]);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('results') ?? [])
                ->map(fn ($r) => [
                    'name'      => $r['name'] ?? '',
                    'admin'     => $r['admin1'] ?? '',
                    'latitude'  => (float) $r['latitude'],
                    'longitude' => (float) $r['longitude'],
                ])
                ->all();
        } catch (\Throwable $e) {
            Log::warning('地名の検索に失敗しました: ' . $e->getMessage());

            return [];
        }
    }
}
```

---

## コードを1つずつ読む

### なぜサービスクラスに切り出すのか

コントローラに `Http::get(...)` を直接書くこともできます。でも、こうなります。

- 月表示でも週表示でも使いたくなったとき、コピペになる
- コントローラが「画面のための処理」と「外部との通信」の2つの仕事を持つ
- テストを書くとき、コントローラを動かさないと天気の処理を試せない

`app/Services/` に置くのは Laravel の決まりではなく**慣習**です。「1つの責任を持つクラスを、適切な名前のフォルダに置く」というだけのことです。

### `Http::timeout(5)`

**タイムアウトは必ず設定してください。** 設定しないと、相手のサーバーが応答しないときに、こちらのリクエストが延々と待ち続けます。

待っている間、PHP のプロセスは1つ占有されたままです。アクセスが集中すると、**天気APIが遅いせいでアプリ全体が止まる**という事態になります。

5秒は「待てる上限」として決めた値です。天気は多少遅れても構わないので、これ以上待つ価値はありません。

### `$response->successful()`

HTTP のステータスコードが 200 番台かを判定します。

⚠️ **`Http::get()` は、404 や 500 が返ってきても例外を投げません。** 「通信はできた。中身がエラーだった」というだけなので、正常終了として扱われます。だから自分で確認する必要があります。

例外を投げてほしい場合は `$response->throw()` を使いますが、今回は「失敗したら空を返す」方針なので、`successful()` で判定しています。

### `Cache::remember($key, $ttl, $callback)`

```php
Cache::remember($key, now()->addHours(3), function () { ... });
```

このメソッドは次のように動きます。

```
① $key でキャッシュを探す
   → あれば、その値を返して終わり（通信しない）
   → なければ、②へ
② $callback を実行する
③ 結果を $key で3時間ぶん保存する
④ 結果を返す
```

たった1行で「あればそれを使い、なければ取ってきて保存する」が書けます。

**キャッシュの保存先**は `.env` の `CACHE_DRIVER` で決まります。既定は `file` で、`storage/framework/cache/` にファイルとして保存されます。

### キャッシュキーの設計

```php
$key = sprintf('weather:%.2f:%.2f', $lat, $lon);
// → "weather:35.45:139.64"
```

キーの設計には2つの工夫があります。

**① 接頭辞を付ける**: `weather:` を付けることで、他の用途のキャッシュと衝突しません。`user:5` と `weather:5` が混ざる事故を防げます。

**② 小数2桁に丸める**: 緯度経度をそのまま使うと、`35.447801` と `35.447802` が別のキーになってしまいます。数百メートルの差で天気は変わらないので、丸めて**キャッシュが効きやすく**します。

小数2桁は約1kmの精度です。同じ市内ならだいたい同じキーになります。

### なぜ3時間か

| TTL | メリット | デメリット |
|---|---|---|
| 5分 | 常に最新 | 通信が多い。相手に迷惑 |
| **3時間** | バランスが良い | 3時間前の予報が出ることがある |
| 24時間 | 通信が最小 | 今日の天気が古すぎる |

天気予報は1日に数回しか更新されません。3時間前の予報でも実用上困らないので、この値にしています。**「どれくらい古くても許されるか」から決める**のがコツです。

### `try { } catch (\Throwable $e)`

`\Throwable` は、PHP のすべてのエラーと例外の親です。`\Exception` だけを catch すると、`TypeError` などの「エラー」系が漏れます。

**外部との通信では何が起きるか分からない**ので、ここでは広く受け止めます。

```php
} catch (\Throwable $e) {
    Log::warning('天気予報の取得に失敗しました: ' . $e->getMessage());
    return [];
}
```

**握りつぶさず、ログには残す**のが大事です。黙って空を返すだけだと、天気が出ない原因が永遠に分かりません。`storage/logs/laravel.log` を見れば理由が分かるようにしておきます。

`Log::error()` ではなく `Log::warning()` にしているのは、**アプリは壊れていないから**です。ログのレベルは「どれくらい緊急か」で選びます。

### 空配列を返す設計

失敗時に `null` ではなく **空の配列** を返しています。

```php
$forecast = $weather->daily($lat, $lon);
$cell['weather'] = $forecast[$key] ?? null;   // ← 空配列でもエラーにならない
```

`null` を返すと、呼ぶ側で `if (is_null(...))` の判定が必要になります。空配列なら「該当なし」として自然に扱えます。**呼ぶ側が楽になる形で返す**のが、良いメソッドの条件です。

### WMO の天気コード

```php
0 => '☀️', 3 => '☁️', 61 => '☂️', 71 => '❄️', 95 => '⛈',
```

天気を数字で表す世界共通の規格です。Open-Meteo はこのコードを返してくるので、自分で絵文字や日本語に変換します。

コードは100種類近くありますが、**実際に日本でよく返るものだけ**を書いてあります。表にないコードは `'・'` になるようにしてあるので、知らないコードが来ても画面は壊れません。

```php
'icon' => self::ICONS[$code] ?? '・',
```

🔑 **外部から来る値には、必ず「想定外だったとき」を用意する。** これは天気に限らず、外部APIを扱うときの鉄則です。

### `$daily['time'] ?? []`

レスポンスの形が想定と違っても、`foreach` でエラーにならないようにしています。**相手の仕様は予告なく変わる**ので、こういう防御を入れておきます。

---

### 🏃 Step 3: コントローラから使う

`MonthController` に引数を1つ足すだけです。

```php
// app/Http/Controllers/MonthController.php

use App\Services\WeatherService;

    public function index(Request $request, WeatherService $weather)
    {
        // …（9-7 の処理）

        // ---- 天気予報（今日から16日先まで） -----------------------
        $family   = $request->user()->family;
        $forecast = $weather->daily($family->latitude, $family->longitude);

        foreach ($days as $day) {
            $key = $day->toDateString();
            // …
            $cells[$key] = [
                'colors'  => array_values(array_unique($colors)),
                'chips'   => $chips,
                'weather' => $forecast[$key] ?? null,   // ← 16日より先は null
            ];
        }

        return view('month.index', [
            // …
            'hasWeather' => ! empty($forecast),
            'family'     => $family,
        ]);
    }
```

🔑 **`WeatherService $weather` と書くだけで使える理由（依存性注入）**

`new WeatherService()` と書いていないことに注目してください。Laravel は、コントローラのメソッドの引数に書かれた**型を見て、自動でインスタンスを作って渡してくれます**。これを「依存性注入（DI）」といいます。

うれしいのは、テストのときに**偽物の WeatherService に差し替えられる**ことです。

```php
// テストでの差し替え例
$this->mock(WeatherService::class, function ($mock) {
    $mock->shouldReceive('daily')->andReturn([
        '2026-09-20' => ['icon' => '☀️', 'label' => 'はれ', 'max' => 28, 'min' => 20],
    ]);
});
```

こう書けば、**実際に通信せずに**天気表示のテストができます。`new` で書いていたら差し替えられません。

### 🏃 Step 4: 月表示に天気を出す

```blade
{{-- resources/views/month/index.blade.php --}}
<span class="top">
  <span class="d">{{ $day->day }}</span>

  {{-- 天気（今日から16日先まで。それより先は何も出ない） --}}
  @if ($cell['weather'])
    <span class="mw" title="{{ $cell['weather']['label'] }}">
      {{ $cell['weather']['icon'] }}
      @if (! is_null($cell['weather']['max']))
        <small>{{ round($cell['weather']['max']) }}/{{ round($cell['weather']['min']) }}</small>
      @endif
    </span>
  @endif
</span>
```

```css
/* public/css/irodori.css に追記 */
.mcell .top{display:flex;align-items:center;justify-content:space-between;gap:4px;}
.mw{font-size:14px;line-height:1;flex:none;}
.mw small{display:block;font-size:9px;color:var(--sub);text-align:right;margin-top:2px;}

@media (max-width:767px){
  .mw{font-size:12px;}
  .mw small{display:none;}   /* スマホでは気温を省く */
}
```

📝 **`title` 属性**: マウスを乗せると「はれ」と出ます。絵文字だけでは意味が伝わらない人への配慮です。9-2 で決めた「アイコン単体を使わない」の実践でもあります。

---

### 🏃 Step 5: 地域を設定する画面を作る

緯度経度を直接入力させるわけにはいきません。**地名で検索して選ぶ**形にします。

`FamilyController` に3つメソッドを足します。

```php
// app/Http/Controllers/FamilyController.php

use App\Services\WeatherService;

    /**
     * 地域の設定（天気予報のため）。
     * q が入っていれば地名を検索して候補を出す。
     */
    public function settings(Request $request, WeatherService $weather)
    {
        $candidates = [];

        if ($request->filled('q')) {
            $candidates = $weather->search($request->input('q'));
        }

        return view('family.settings', [
            'family'     => $request->user()->family,
            'q'          => $request->input('q', ''),
            'candidates' => $candidates,
        ]);
    }

    /** 選んだ地点を保存する */
    public function saveLocation(Request $request)
    {
        $data = $request->validate([
            'location_name' => ['required', 'string', 'max:60'],
            'latitude'      => ['required', 'numeric', 'between:-90,90'],
            'longitude'     => ['required', 'numeric', 'between:-180,180'],
        ], [], ['location_name' => '地域']);

        $request->user()->family->update($data);

        return redirect()->route('month')
            ->with('status', "天気予報の地点を「{$data['location_name']}」にしました。");
    }

    /** 地点の設定を消す（天気を出さなくする） */
    public function clearLocation(Request $request)
    {
        $request->user()->family->update([
            'location_name' => null, 'latitude' => null, 'longitude' => null,
        ]);

        return back()->with('status', '天気予報を出さない設定にしました。');
    }
```

ルートを足します。

```php
// routes/web.php の has.family グループ内
        Route::get('/family/settings',    [FamilyController::class, 'settings'])->name('family.settings');
        Route::post('/family/location',   [FamilyController::class, 'saveLocation'])->name('family.location');
        Route::delete('/family/location', [FamilyController::class, 'clearLocation'])->name('family.location.clear');
```

画面を作ります。

```bash
# irodori ディレクトリで実行
touch resources/views/family/settings.blade.php
```

```blade
{{-- resources/views/family/settings.blade.php --}}
@extends('layouts.irodori')
@section('title', '地域の設定 — いろどり')
@section('wrap-class', 'narrow')

@section('content')

  <div class="card">
    <h2>天気予報の地域</h2>
    <p class="lead">
      住んでいる市区町村を入れて検索してください。
      設定すると、月カレンダーに16日先までの天気が出ます。
    </p>

    @if ($family->hasLocation())
      <div class="nowset">
        いまの設定：<strong>{{ $family->location_name }}</strong>
      </div>
    @endif

    {{-- 検索は GET。結果をブックマークできるし、リロードしても再送信にならない --}}
    <form method="get" action="{{ route('family.settings') }}">
      <div class="field" style="margin-top:18px;">
        <label for="q">地名<span class="req">必須</span></label>
        <input type="text" id="q" name="q" value="{{ $q }}"
               placeholder="例）横浜市" required autofocus>
      </div>
      <button type="submit" class="btn primary block">さがす</button>
    </form>

    @if ($q !== '')
      @if ($candidates)
        <h3 class="section-title">みつかった地名</h3>
        <div class="cand">
          @foreach ($candidates as $c)
            {{-- 保存は POST。データを変えるので --}}
            <form method="post" action="{{ route('family.location') }}">
              @csrf
              <input type="hidden" name="location_name" value="{{ $c['name'] }}">
              <input type="hidden" name="latitude"  value="{{ $c['latitude'] }}">
              <input type="hidden" name="longitude" value="{{ $c['longitude'] }}">
              <button type="submit">
                {{ $c['name'] }}
                <small>{{ $c['admin'] }}（{{ number_format($c['latitude'], 2) }}, {{ number_format($c['longitude'], 2) }}）</small>
              </button>
            </form>
          @endforeach
        </div>
      @else
        <p class="empty">
          みつかりませんでした。<br>
          市区町村の名前（「横浜市」「藤沢」など）で試してみてください。
        </p>
      @endif
    @endif
  </div>

@endsection
```

### 覚えておくポイント

**検索は GET、保存は POST**

- **GET** … 何も変えない（検索・表示）。URLに残るのでブックマークでき、リロードしても安全
- **POST** … データを変える（保存・削除）。リロードすると「再送信しますか」と聞かれる

この使い分けは HTTP の基本です。検索を POST にすると、結果を共有できなくなります。

**候補をボタンにして hidden で持つ**

緯度経度をユーザーに見せて選ばせるのではなく、**検索結果をそのまま hidden フィールドに入れて送り返す**形にしています。ユーザーは「横浜市」を押すだけ。

⚠️ ただし hidden の値は改ざんできるので、`saveLocation()` で `between:-90,90` などの検証をしています。**画面から送られてくる値は、hidden であっても信用しない**が原則です。

---

### 🏃 Step 6: 動作を確認する

1. 月表示 → 下の「地域を設定する」
2. 「横浜市」などで検索
3. 候補から選ぶ
4. 月表示に戻り、今日から16日ぶんのマスに天気の絵文字が出る

**確認したいポイント**:

- 17日目以降のマスには**何も出ない**（これが正しい動作）
- 月を行き来しても、2回目以降は速い（キャッシュが効いている）

### キャッシュが効いているかを確かめる

`AppServiceProvider` に一時的に次を書くと、SQL とは別に通信のログが見られます。もっと簡単なのは Tinker です。

```bash
sail artisan tinker
```

```php
$w = app(\App\Services\WeatherService::class);

// 1回目（通信する。少し待つ）
$t = microtime(true); $w->daily(35.45, 139.64); round((microtime(true)-$t)*1000) . 'ms';

// 2回目（キャッシュから。速い）
$t = microtime(true); $w->daily(35.45, 139.64); round((microtime(true)-$t)*1000) . 'ms';
```

1回目が数百ms、2回目が数msなら、キャッシュが効いています。

キャッシュを消したいときは次のコマンドです。

```bash
sail artisan cache:clear
```

### 通信できない状態を試す

**わざと壊してみる**のは、良い学習です。`WeatherService` の URL を一時的に存在しないものに変えてみてください。

```php
private const FORECAST_URL = 'https://api.open-meteo.invalid/v1/forecast';
```

このとき、**カレンダーは普通に表示され、天気だけが出ない**はずです。そして `storage/logs/laravel.log` に警告が残っているはずです。

これが確認できたら、`try/catch` が意図どおり働いています。確認したら URL を戻してください。

---

## ⚠️ よくあるエラー

| 症状 | 原因 |
|---|---|
| `Class "GuzzleHttp\Client" not found` | `sail composer require guzzlehttp/guzzle`（Laravel 10 には標準で入っています） |
| 天気が出ない・ログに `cURL error 6` | コンテナから外部に出られない。ネットワークを確認 |
| 天気が出ない・ログに `cURL error 28` | タイムアウト。相手が遅い。時間をおいて再試行 |
| 設定を変えても天気が変わらない | キャッシュ。`sail artisan cache:clear` |
| 17日目以降にも出したい | **できません**。気象学的な限界です |
| 地名が見つからない | 「県」より「市区町村」のほうが当たりやすい |
| 絵文字が `・` になる | 表にないコードが返ってきている。`ICONS` に追加する |

---

## 📝 外部APIを使うときのマナー

学習用でも意識しておきたいことがあります。

**① 相手のサーバーに負荷をかけない。** キャッシュは自分のためだけでなく、相手のためでもあります。ループの中で毎回呼ぶようなコードは書かないでください。

**② 利用規約を読む。** Open-Meteo は非商用なら無料ですが、商用利用には条件があります。「無料で使える」と「何をしてもいい」は違います。

**③ 個人情報を送らない。** 今回送っているのは緯度経度だけです。ユーザー名やメールアドレスを外部APIに送るときは、それが必要か・許されるかを必ず考えてください。

**④ 出典を示す。** サービスとして公開するなら、「天気データ: Open-Meteo」のような表記を入れるのが礼儀です。

---

## ✅ 完成チェックリスト

- 外部APIが自分のDBと何が違うか（失敗する・遅い・回数制限）を説明できる
- サービスクラスに切り出す理由を説明できる
- `Http::timeout()` が必要な理由を説明できる
- `Cache::remember()` の動きと、キーの設計（接頭辞・丸め）を説明できる
- `try/catch` で失敗しても画面が壊れないことを、**実際に壊して**確認できた
- 依存性注入でサービスを受け取り、テストで差し替えられる形になっている
- 地名検索から地点を保存し、月表示に天気が出た
- **16日より先が空欄であること**を確認し、その理由を説明できる

---

## ✨ まとめ

- 外部APIは「失敗する前提」「遅い前提」「回数を減らす前提」で書く
- 通信の処理はサービスクラスに切り出し、コントローラからは依存性注入で受け取る
- タイムアウトは必ず設定する。設定しないとアプリ全体が止まりうる
- キャッシュは自分のためであり、相手のためでもある
- 失敗しても画面は壊さない（グレースフル・デグラデーション）。ただしログには残す
- **できないことは、設計の段階ではっきり伝える**。16日より先の日別予報は存在しない

---

## 🎉 完成

ここまでで、「いろどり」は動く状態になりました。

- 家族で共有できるスケジュール
- 子どもごとの色分け
- 毎週くりかえす習い事
- 時間が重なる予定の横並び
- 終日の予定・記念日・提出物
- 月カレンダーと天気予報

### この設計から持ち帰ってほしい3つのこと

**割り切りを言語化する。** 「くりかえしは毎週のみ」「1人1家族」と決めて、理由をコメントに残しました。要件を全部満たそうとすると終わりません。何を捨てたかを記録しておけば、後で足せます。

**矛盾が作れない形にする。** 終日フラグを持たず「時刻が null なら終日」としたのも、色を片方だけ持つのも、記念日を月日で持つのも同じ発想です。データの持ち方で防げるバグは、コードで防ぐより確実です。

**忘れても事故らない仕組みにする。** グローバルスコープがその例です。「毎回 where を書く」という運用は必ず破綻します。仕組みで縛れば、書き忘れようがありません。

この3つは、どんなアプリを作るときにも効きます。
