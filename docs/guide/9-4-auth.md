# 9-4 認証と家族の共有（招待コード）

📝 **このハンズオンで使う機能**: Laravel Fortify、ミドルウェア、マスアサインメント対策
📝 **前提知識**: 9-3 のモデルとグローバルスコープ

---

## 🎯 このセクションで学ぶこと

- Fortify で登録・ログイン・ログアウトを実装する
- **招待コードで複数のユーザーを1つの家族に結びつける**仕組みを設計・実装する
- 「家族に入っていない人」をミドルウェアで振り分ける
- 招待コードの安全性（当てられにくさ）を数字で評価する

---

## 導入: ログインできても、まだ何も見えない

Fortify を入れると、登録・ログイン・ログアウトはすぐ動きます。でもそれだけでは、このアプリは成立しません。

このアプリの肝は「**家族で同じ予定を見る**」ことです。ママが登録した予定を、パパも祖父母も見られなければ意味がありません。ところが、ユーザーは別々にアカウントを作ります。**別々に登録した人たちを、どうやって同じグループに結びつけるか**。これがこのセクションの主題です。

---

## 🧠 先輩エンジニアの思考プロセス

グループ共有の実装方法はいくつかあります。実務でよく見るのは次の3つです。

| 方式 | 仕組み | 向いている場面 |
|---|---|---|
| **招待コード** | 短い合言葉を手渡し、入力してもらう | 家族・友人など、口頭で伝えられる関係 |
| 招待メール | URL付きメールを送る | 会社・学校など、相手のメールを知っている |
| 管理者が追加 | 管理画面でユーザーを選んで追加 | 社内システム |

今回は**招待コード**を選びます。理由は「LINE で送れる」からです。祖父母に「このコードを入れて」と電話口で伝えることもできます。招待メール方式にすると、メール送信の設定（SMTP・SendGrid など）が必要になり、1日では終わりません。

コードを設計するときに気にするのは2点です。**読み間違えないこと**と、**当てられないこと**。この2つは相反するので、バランスを取ります。

---

## 📌 作業前の確認

- 9-3 のマイグレーションが通っている
- `sail ps` で3つのコンテナが `running`

---

## 🏃 実践: Fortify を導入する

### 🏃 Step 1: Fortify をインストールする

```bash
# irodori ディレクトリで実行
sail composer require laravel/fortify
sail artisan vendor:publish --provider='Laravel\Fortify\FortifyServiceProvider'
```

⚠️ `--provider` は**シングルクォート**で囲んでください。ダブルクォートだと、シェルがバックスラッシュを解釈することがあります。

これで次のファイルが生成されます。

- `config/fortify.php` … 設定
- `app/Providers/FortifyServiceProvider.php` … 使うビューなどの指定
- `app/Actions/Fortify/` … 登録・更新の処理本体
- `database/migrations/..._add_two_factor_columns_to_users_table.php` … 2要素認証の列

### 🏃 Step 2: プロバイダを登録する

`config/app.php` の `providers` 配列に1行足します。

```php
// config/app.php
        App\Providers\FortifyServiceProvider::class,   // ← 追加
        App\Providers\RouteServiceProvider::class,
```

### 🏃 Step 3: 使う機能を絞る

`config/fortify.php` の `features` を編集します。

```php
// config/fortify.php
'features' => [
    Features::registration(),
    // 今回は使わない機能。必要になったらコメントを外す
    // Features::resetPasswords(),
    // Features::emailVerification(),
    // Features::updateProfileInformation(),
    // Features::updatePasswords(),
    // Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
],
```

🔑 **使わない機能を切る理由**: 機能を有効にすると、その画面（パスワード再設定フォームなど）を用意する義務が生じます。用意しないと、リンクを踏んだ瞬間に「View [auth.forgot-password] not found」でエラーになります。**使わない機能は最初から切る**のが安全です。

パスワード再設定はメール送信の設定も必要なので、学習の段階では切っておくのが賢明です。

### 🏃 Step 4: 使うビューを教える

`app/Providers/FortifyServiceProvider.php` の `boot()` の先頭に2行足します。

```php
// app/Providers/FortifyServiceProvider.php

use Laravel\Fortify\Fortify;

public function boot(): void
{
    // Fortify は画面を持たないので、どのビューを使うか教える
    Fortify::loginView(fn () => view('auth.login'));
    Fortify::registerView(fn () => view('auth.register'));

    Fortify::createUsersUsing(CreateNewUser::class);
    // …（以下、生成されたままでOK）
}
```

🔑 **Fortify が画面を持たない理由**: Fortify は「認証の裏側だけ」を提供するパッケージです。Breeze と違って画面を押しつけてこないので、**自分のデザインで画面を作れます**。今回のように独自の CSS で統一したいときは、こちらが向いています。

### 🏃 Step 5: ログイン・登録の画面を作る

```bash
# irodori ディレクトリで実行
mkdir -p resources/views/auth
touch resources/views/auth/login.blade.php
touch resources/views/auth/register.blade.php
```

`resources/views/auth/login.blade.php`。

```blade
{{-- resources/views/auth/login.blade.php --}}
@extends('layouts.irodori-guest')
@section('title', 'ログイン — いろどり')

@section('content')
  <div class="card">
    <h2>ログイン</h2>

    @if (session('status'))
      <div class="flash">{{ session('status') }}</div>
    @endif

    <form method="post" action="{{ route('login') }}">
      @csrf

      <div class="field">
        <label for="email">メールアドレス</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}"
               autocomplete="email" required autofocus>
      </div>

      <div class="field">
        <label for="password">パスワード</label>
        <input type="password" id="password" name="password"
               autocomplete="current-password" required>
      </div>

      <div class="field">
        <label class="check">
          <input type="checkbox" name="remember" value="1">
          ログインしたままにする
        </label>
      </div>

      <button type="submit" class="btn primary block">ログイン</button>
    </form>

    <p style="margin-top:18px;font-size:14px;text-align:center;">
      はじめての方は <a href="{{ route('register') }}">新規登録</a>
    </p>
  </div>
@endsection
```

`resources/views/auth/register.blade.php`。

```blade
{{-- resources/views/auth/register.blade.php --}}
@extends('layouts.irodori-guest')
@section('title', '新規登録 — いろどり')

@section('content')
  <div class="card">
    <h2>新規登録</h2>
    <p class="lead">登録がすんだら、家族をつくるか、招待コードで参加します。</p>

    <form method="post" action="{{ route('register') }}">
      @csrf

      <div class="field">
        <label for="name">おなまえ</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}"
               autocomplete="name" required autofocus>
      </div>

      <div class="field">
        <label for="email">メールアドレス</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}"
               autocomplete="email" required>
      </div>

      <div class="field">
        <label for="password">パスワード <span class="hint">（8文字以上）</span></label>
        <input type="password" id="password" name="password"
               autocomplete="new-password" required>
      </div>

      <div class="field">
        <label for="password_confirmation">パスワード（確認）</label>
        <input type="password" id="password_confirmation" name="password_confirmation"
               autocomplete="new-password" required>
      </div>

      <button type="submit" class="btn primary block">登録する</button>
    </form>

    <p style="margin-top:18px;font-size:14px;text-align:center;">
      すでにお持ちの方は <a href="{{ route('login') }}">ログイン</a>
    </p>
  </div>
@endsection
```

📝 **`autocomplete` 属性**: ブラウザのパスワード管理機能が正しく動くために必要です。`current-password`（ログイン）と `new-password`（登録）を書き分けると、登録時に「保存しますか」が正しく出ます。地味ですが、ユーザー体験に効きます。

📝 **`old('email')` はあるのに `old('password')` がない理由**: パスワードを画面に戻すのは危険なので、Laravel は自動的にパスワード系のフィールドを `old()` から除外しています。

---

## 🏃 実践: 招待コードで家族をつなぐ

ここからが本題です。

### 招待コードの仕組み

```
【ママ】                              【パパ】
1. 新規登録                           1. 新規登録
   ↓                                    ↓
2. 家族をつくる                        2. 招待コードを入力
   families に1行できる                   ↓
   invite_code: "A3F9K2QP"             3. そのコードの家族を探す
   ↓                                    ↓
3. users.family_id に               4. 自分の users.family_id に
   その家族のIDが入る                    同じ家族のIDを入れる
   ↓                                    ↓
        ────────── 2人とも family_id が同じになる ──────────
                              ↓
        グローバルスコープが where family_id = ? を付けるので
        2人は自動的に同じ予定を見ることになる
```

🔑 **ここが設計の美しいところです。** 「共有」のために特別な仕組みは何も要りません。`family_id` を揃えるだけで、9-3 で作ったグローバルスコープが勝手に共有を成立させます。

もし 9-3 でグローバルスコープを作らず、コントローラごとに条件を書いていたら、ここで「共有ってどう実装するんだろう」と悩むことになったはずです。**データの持ち方を正しく決めると、機能が自然に手に入ります。**

### 招待コードの設計

#### ① 文字種を選ぶ

```php
'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'
```

アルファベット大文字26種＋数字10種＝36種から、**紛らわしい4文字を抜いて32種**にしています。

| 抜いた文字 | 理由 |
|---|---|
| `O`（オー） | `0`（ゼロ）と見分けがつかない |
| `0`（ゼロ） | 同上 |
| `I`（アイ） | `1`（イチ）と見分けがつかない |
| `1`（イチ） | 同上 |

電話で「オーだよ、ゼロじゃなくて」というやりとりが発生しないようにします。**祖父母に電話で伝える**という使い方を想定した判断です。

小文字も混ぜません。「大文字か小文字か」を気にさせないためです（入力時に `strtoupper()` で揃えます）。

#### ② 長さを決める

8文字にします。当てられにくさを計算してみます。

```
32種類 の 8文字 = 32^8 = 1,099,511,627,776 通り（約1.1兆）
```

仮に1秒に100回試せたとしても、全部試すのに約350年かかります。**十分です。**

逆に4文字だと `32^4 = 約100万通り` で、これは自動プログラムなら数時間で全部試せてしまいます。短くしすぎないのが大事です。

📝 **とはいえ**: 本番運用するなら、招待コードの入力にレート制限（`throttle` ミドルウェア）を付けるべきです。「1分に5回まで」と制限すれば、総当たり攻撃は現実的に不可能になります。今回は学習用なので付けていませんが、**実務では必ず入れる**と覚えておいてください。

#### ③ かぶらないようにする

```php
public static function generateInviteCode(): string
{
    do {
        $code = collect(str_split('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'))
            ->shuffle()->take(8)->implode('');
    } while (static::where('invite_code', $code)->exists());

    return $code;
}
```

`do { } while (...)` で、**すでに存在するコードが出たら作り直します**。

1.1兆通りあるのでめったにかぶりませんが、「めったにない」は「起きない」ではありません。DB側にも `unique` 制約を張ってあるので、万一すり抜けても保存時に弾かれます。**アプリのチェックとDBの制約、二段構えで守ります。**

⚠️ `shuffle()->take(8)` は**同じ文字が2回出ない**方式です（32文字をシャッフルして先頭8文字を取る）。同じ文字を許したい場合は `Str::random()` を使いますが、その場合は除外文字の処理を自分で書く必要があります。今回は読みやすさを優先しています。

---

### 🏃 Step 6: ミドルウェアを作る

登録直後のユーザーは、まだどの家族にも入っていません（`family_id` が null）。その状態でカレンダーを開かれると困るので、家族作成画面へ送り返します。

```bash
# irodori ディレクトリで実行
sail artisan make:middleware EnsureHasFamily
```

```php
<?php
// app/Http/Middleware/EnsureHasFamily.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** まだ家族に入っていない人を、家族づくりの画面へ送る */
class EnsureHasFamily
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->family_id) {
            return redirect()->route('family.setup');
        }

        return $next($request);
    }
}
```

`app/Http/Kernel.php` の `$middlewareAliases` に別名を登録します。

```php
// app/Http/Kernel.php
protected $middlewareAliases = [
    'has.family' => \App\Http\Middleware\EnsureHasFamily::class,   // ← 追加
    'auth' => \App\Http\Middleware\Authenticate::class,
    // …
];
```

📝 Laravel 11 以降は `Kernel.php` がなく、`bootstrap/app.php` の `->withMiddleware()` の中に書きます。Laravel 10 では `Kernel.php` です。

📝 **`?->` について**: `$request->user()` が null（未ログイン）でもエラーにならず null を返す書き方です。PHP 8 からの機能で、「ヌル安全演算子」といいます。

---

### 🏃 Step 7: ルートを書く

`routes/web.php` を**まるごと書き換えます**。

```php
<?php
// routes/web.php

use App\Http\Controllers\FamilyController;
use App\Http\Controllers\WeekController;
use Illuminate\Support\Facades\Route;

/*
 | ログイン・新規登録・ログアウトのルートは Fortify が自動で用意します。
 | （/login, /register, /logout）ここには書きません。
 */

Route::get('/', fn () => auth()->check()
    ? redirect()->route('home')
    : view('welcome'))->name('top');

Route::middleware('auth')->group(function () {

    // --- まだ家族に入っていない人だけが通る道 ---
    Route::get('/family/setup', [FamilyController::class, 'setup'])->name('family.setup');
    Route::post('/family',      [FamilyController::class, 'store'])->name('family.store');
    Route::post('/family/join', [FamilyController::class, 'join'])->name('family.join');

    // --- ここから先は家族に入っている人だけ ---
    Route::middleware('has.family')->group(function () {

        // ログイン後の飛び先。RouteServiceProvider::HOME = '/home' と一致させている
        Route::get('/home', [WeekController::class, 'index'])->name('home');

        Route::get('/family/invite', [FamilyController::class, 'invite'])->name('family.invite');

        // 9-5 以降でここに足していきます
    });
});
```

### ⚠️ ここが最重要: ルートの入れ子

```
auth（ログイン必須）
 ├── family/setup・family・family/join   ← 家族がなくても通れる
 └── has.family（家族に入っている人だけ）
      ├── /home
      └── その他すべて
```

**家族作成の3つを内側（`has.family` の中）に入れてはいけません。**

もし入れてしまうと、こうなります。

```
1. 登録直後のユーザーが /family/setup にアクセス
2. has.family が「家族がない」と判定
3. /family/setup へリダイレクト
4. → 2に戻る（無限ループ）
```

ブラウザには「リダイレクトが繰り返されました」と出ます。**設計の時点で気づけるバグ**なので、9-1 の画面設計で入れ子の図を描いておいたわけです。

🔑 **`/home` にした理由**: Laravel の `app/Providers/RouteServiceProvider.php` にある `HOME` 定数が最初から `/home` です。Fortify はログイン成功後ここへ飛ばすので、名前を合わせておくと設定変更が要りません。

---

### 🏃 Step 8: FamilyController を作る

```bash
# irodori ディレクトリで実行
sail artisan make:controller FamilyController
```

```php
<?php
// app/Http/Controllers/FamilyController.php

namespace App\Http\Controllers;

use App\Models\Family;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    /** 家族をつくる or 招待コードで参加する画面 */
    public function setup(Request $request)
    {
        // すでに家族に入っている人がここへ来たら、カレンダーへ返す
        if ($request->user()->family_id) {
            return redirect()->route('home');
        }

        return view('family.setup');
    }

    /** 新しく家族をつくる */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
        ], [], ['name' => '家族の名前']);

        $family = Family::create([
            'name'        => $data['name'],
            'invite_code' => Family::generateInviteCode(),
        ]);

        // ★ ここがポイント（下で詳しく説明）
        $request->user()->forceFill(['family_id' => $family->id])->save();

        return redirect()->route('members.index')
            ->with('status', '家族をつくりました。つづけてお子さんを登録しましょう。');
    }

    /** 招待コードで既存の家族に参加する */
    public function join(Request $request)
    {
        $data = $request->validate([
            'invite_code' => ['required', 'string', 'size:8'],
        ], [], ['invite_code' => '招待コード']);

        // 小文字で入力されても大丈夫なように、大文字に揃えてから探す
        $family = Family::where('invite_code', strtoupper($data['invite_code']))->first();

        if (! $family) {
            return back()
                ->withErrors(['invite_code' => '招待コードが見つかりませんでした。'])
                ->withInput();
        }

        $request->user()->forceFill(['family_id' => $family->id])->save();

        return redirect()->route('home')
            ->with('status', "「{$family->name}」に参加しました。");
    }

    /** 招待コードを見せる画面 */
    public function invite(Request $request)
    {
        return view('family.invite', [
            'family' => $request->user()->family,
        ]);
    }
}
```

### 覚えておくポイント

#### `forceFill()` を使う理由

```php
$request->user()->forceFill(['family_id' => $family->id])->save();
```

9-3 で、`User` の `$fillable` に `family_id` を**入れませんでした**。だから普通の `update(['family_id' => ...])` では書き込めません。

`forceFill()` は `$fillable` を無視して書き込むメソッドです。つまりこう読めます。

> 「`family_id` は基本的に書き込み禁止。ただしこの2か所だけは、意図的に例外とする」

これは単なるテクニックではなく、**設計の意思表示**です。もし `$fillable` に `family_id` を入れていたら、新規登録フォームに `<input type="hidden" name="family_id" value="1">` を仕込むだけで、他人の家族の予定を全部見られてしまいます。

⚠️ この攻撃は**マスアサインメント脆弱性**といい、実際のサービスでも起きている典型的な事故です。`$fillable` は「便利機能」ではなく「防御壁」だと考えてください。

#### `strtoupper()` で揃える理由

ユーザーは小文字で入力するかもしれません。コピペで前後に空白が入るかもしれません。**入力を正規化してから検索する**のが親切です。

さらに丁寧にするなら、こう書きます。

```php
$code = strtoupper(trim($data['invite_code']));
```

#### `withErrors()` と `withInput()`

```php
return back()
    ->withErrors(['invite_code' => '招待コードが見つかりませんでした。'])
    ->withInput();
```

- `withErrors()` … エラーメッセージを持って前の画面へ戻る（レイアウトの `$errors->any()` が拾う）
- `withInput()` … 入力した内容を保持する（`old()` で復元できる）

`withInput()` を忘れると、コードを打ち直させることになります。**エラーで戻すときは必ずセット**で書いてください。

#### なぜ `exists:families,invite_code` を使わないのか

バリデーションルールで存在確認する方法もあります。

```php
'invite_code' => ['required', 'size:8', 'exists:families,invite_code'],
```

これでも動きますが、エラーメッセージが「選択された招待コードが正しくありません」という機械的な文になります。今回は自分で探して、**人間が読んで分かる文言**を返しています。どちらが正解ということはなく、メッセージの自然さを取るか、記述量を取るかの判断です。

---

### 🏃 Step 9: 家族の画面を作る

```bash
# irodori ディレクトリで実行
mkdir -p resources/views/family
touch resources/views/family/setup.blade.php
touch resources/views/family/invite.blade.php
```

`resources/views/family/setup.blade.php`。

```blade
{{-- resources/views/family/setup.blade.php --}}
@extends('layouts.irodori-guest')
@section('title', '家族をつくる — いろどり')

@section('content')

  <div class="card">
    <h2>家族をつくる</h2>
    <p class="lead">はじめての方はこちら。あとで家族を招待できます。</p>
    <form method="post" action="{{ route('family.store') }}">
      @csrf
      <div class="field">
        <label for="name">家族の名前</label>
        <input type="text" id="name" name="name" value="{{ old('name') }}"
               placeholder="例）はしもと家" required>
      </div>
      <button type="submit" class="btn primary block">つくる</button>
    </form>
  </div>

  <div class="card">
    <h2>招待コードで参加する</h2>
    <p class="lead">家族から8文字のコードを受けとった方はこちら。</p>
    <form method="post" action="{{ route('family.join') }}">
      @csrf
      <div class="field">
        <label for="invite_code">招待コード</label>
        <input type="text" id="invite_code" name="invite_code" value="{{ old('invite_code') }}"
               placeholder="ABCD2345" maxlength="8"
               style="text-transform:uppercase;letter-spacing:.2em;" required>
      </div>
      <button type="submit" class="btn block">参加する</button>
    </form>
  </div>

@endsection
```

📝 **`text-transform:uppercase`**: 入力中の見た目を大文字にする CSS です。**送信される値は変わりません**（だからサーバー側の `strtoupper()` が必要）。見た目だけの親切です。

`resources/views/family/invite.blade.php`。

```blade
{{-- resources/views/family/invite.blade.php --}}
@extends('layouts.irodori')
@section('title', '家族をまねく — いろどり')
@section('wrap-class', 'narrow')

@section('content')
  <div class="card">
    <h2>家族をまねく</h2>
    <p class="lead">
      このコードを パパや おじいちゃん・おばあちゃんに教えてください。<br>
      新規登録のあと「招待コードで参加する」に入力すると、同じ予定が見られます。
    </p>

    <div class="invite-code">{{ $family->invite_code }}</div>

    <p class="lead">まちがえやすい 0（ゼロ）と O、1 と I は使っていません。</p>

    <div class="btn-row">
      <a class="btn" href="{{ route('home') }}">もどる</a>
    </div>
  </div>
@endsection
```

---

### 🏃 Step 10: 動作を確認する

このセクションの確認は、**ブラウザを2つ使う**のがポイントです。

1. 通常のウィンドウで新規登録 → 家族をつくる → 招待コードをメモ
2. **シークレットウィンドウ**（別のログインセッション）で新規登録
3. 招待コード入力画面に、1でメモしたコードを入れる
4. 両方のウィンドウで `/home` を開く

このとき、**2人が同じ画面を見ていれば成功**です。

🔑 **シークレットウィンドウを使う理由**: 同じブラウザの通常ウィンドウでは、セッション（ログイン状態）が共有されます。2人目としてログインすると1人目がログアウトしてしまうので、別のセッションが必要です。

さらに Tinker で、`family_id` が揃っていることを確かめます。

```bash
sail artisan tinker
```

```php
\App\Models\User::pluck('family_id', 'email');
// => 2人とも同じ数字が出ていれば成功
```

---

### 🏃 Step 11: 分離できているかを確かめる（重要）

ここまでで「共有」は確認できました。次は逆に「**分離**」を確かめます。セキュリティは「動いたからOK」ではなく、「**破れないことを確かめて**」はじめてOKです。

1. シークレットウィンドウで、もう1つ別のアカウントを作る
2. そちらは**別の家族**を作る（招待コードは使わない）
3. 1つ目の家族で予定を1件登録し、そのIDを確認する（URL に出ます）
4. 2つ目の家族でログインしたまま、`/events/1/edit` のように**直接URLを打つ**

**404 になれば成功です。** グローバルスコープが効いています。

もし予定が見えてしまったら、`BelongsToFamily` トレイトを `use` し忘れているモデルがあります。

---

## ⚠️ よくあるエラー

| エラー | 原因 |
|---|---|
| `Target class [has.family] does not exist` | `Kernel.php` への別名登録忘れ |
| `View [auth.login] not found` | `FortifyServiceProvider` の `loginView()` 忘れ、またはビューの置き場所違い |
| 「リダイレクトが繰り返されました」 | `family/setup` を `has.family` の内側に入れてしまっている |
| ログイン後に 404 | `RouteServiceProvider::HOME` の値と、`home` ルートのパスが一致していない |
| 招待コードを入れても参加できない | 大文字小文字。`strtoupper()` を通しているか確認 |
| `Add [family_id] to fillable property` | `update()` を使っている。`forceFill()->save()` に直す |

---

## ✅ 完成チェックリスト

- Fortify を入れ、`config/app.php` にプロバイダを登録できた
- `features` を今回使う分だけに絞り、その理由を説明できる
- 自作のデザインでログイン・登録画面を作れた
- 招待コードの文字種と長さの理由（読み間違え防止・当てにくさ）を説明できる
- `generateInviteCode()` が重複を避ける仕組みを説明できる
- ルートの入れ子（`auth` の内側に `has.family`）が必要な理由を説明できる
- `forceFill()` を使う理由（マスアサインメント対策）を説明できる
- ブラウザ2つで、2人が同じ予定を見られることを確認できた
- **別の家族のデータが URL 直打ちで見えないことを確認できた**

---

## ✨ まとめ

- Fortify は「認証の裏側」だけを提供する。画面は自分で作るので、デザインを統一できる
- 使わない機能は `features` から外す。有効にした機能には画面を用意する義務が生じる
- **招待コードは `family_id` を揃えるための道具**。共有そのものは、9-3 のグローバルスコープが自動的に成立させる
- コードの文字種は「読み間違えないこと」、長さは「当てられないこと」で決める。32種8文字で約1.1兆通り
- `$fillable` は便利機能ではなく防御壁。例外は `forceFill()` で明示的に開ける
- セキュリティは、共有できることと**分離できていること**の両方を確かめて初めて完成

次の 9-5 では、子ども・習い事・予定の CRUD を実装します。
