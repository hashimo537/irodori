# 9-2 環境構築と共通レイアウト

📝 **このハンズオンで使う機能**: Laravel Sail（2-1）、Laravel 10 の構成（2-2）
📝 **前提知識**: 9-1 の設計が固まっていること

---

## 🎯 このセクションで学ぶこと

- Sail で Laravel 10 のプロジェクトを作り、phpMyAdmin つきで起動する
- Tailwind を使わない素の CSS でアプリを組む方針を決め、その置き場所を理解する
- 全画面で使う共通レイアウトを2つ（ログイン後／ログイン前）作る

---

## 導入: 見た目の土台を先に置く

9-1 で設計が固まりました。ここからは実装です。

このセクションでやるのは、**プロジェクトの生成**と**見た目の土台の配置**です。CSS とレイアウトを先に置いておくと、9-3 以降で作る画面がいきなり「それらしく」見えます。真っ白な HTML を見ながら進めるより、ずっとモチベーションが保てます。

---

## 🧠 先輩エンジニアの思考プロセス

CSS フレームワークを使うかどうかは、最初に決めます。今回は **Tailwind を使いません**。

理由は2つあります。ひとつは、このアプリの見た目の核心が「時間の帯を絶対配置する」ことにあり、そこは結局どのフレームワークでも自分で書くことになるから。もうひとつは、**`npm run build` という工程が丸ごと消える**からです。動く部品が減ると、詰まる場所も減ります。

素の CSS を `public/css/` に置くと、ビルド不要でそのままブラウザが読みます。学習中は「保存 → リロードで即反映」が効くこの形が扱いやすいです。

---

## 📌 作業前の確認

- Docker が起動している
- プロジェクトを置きたいディレクトリを決めてある
- `sail` エイリアスが使える（未設定なら `./vendor/bin/sail` に読み替え）

---

## 🏃 実践: プロジェクトを立ち上げる

### 🏃 Step 1: Laravel 10 プロジェクトを作成する

手元に PHP がなくても作れるよう、PHP と Composer の入った Docker イメージを一時的に借ります。

```bash
# プロジェクトを置きたいディレクトリで実行
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer create-project laravel/laravel:^10.0 irodori
```

```bash
cd irodori
```

🔑 バージョンを `^10.0` と明示しています。`laravel.build` を使う方法もありますが、そちらは常に最新版を取ってくるので、教材と食い違います。

### 🏃 Step 2: Laravel Sail を導入する

まだ `vendor/bin/sail` が使えないので、この2つも Docker イメージ越しに実行します。

```bash
# irodori ディレクトリで実行
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
    laravelsail/php82-composer:latest \
    composer require laravel/sail --dev
```

```bash
# irodori ディレクトリで実行
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    php artisan sail:install --with=mysql
```

### 🏃 Step 3: phpMyAdmin を追加する

`compose.yaml` の `services:` の下、`mysql` サービスのブロックの後ろに追記します。

```yaml
# compose.yaml （mysql サービスの後ろに追記）
    phpmyadmin:
        image: 'phpmyadmin:latest'
        ports:
            - '${FORWARD_PHPMYADMIN_PORT:-8080}:80'
        environment:
            PMA_HOST: mysql
            PMA_USER: '${DB_USERNAME}'
            PMA_PASSWORD: '${DB_PASSWORD}'
        networks:
            - sail
        depends_on:
            - mysql
```

`.env` のデータベース接続情報を確認します（`sail:install` が設定しているはずです）。

```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```

🔑 `DB_HOST` は `localhost` ではなく、コンテナ名の `mysql` です。アプリのコンテナから見ると、データベースは `mysql` という名前のコンテナとして存在します。

### 🏃 Step 4: 起動して初期化する

```bash
# irodori ディレクトリで実行
sail up -d
sail artisan key:generate
sail artisan migrate
```

⚠️ `sail up -d` の直後に `migrate` を打つと、MySQL の初期化が終わっておらず `SQLSTATE[HY000] [2002] Connection refused` が出ることがあります。`sail ps` で `mysql` が `running` になってから実行し直してください。

### 🏃 Step 5: 日本語ロケールを設定する

`config/app.php` の `locale` を変更します。

```php
// config/app.php
'locale' => 'ja',
```

`app/Providers/AppServiceProvider.php` の `boot()` に1行足します。日付が日本語で出るようになります。

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    \Carbon\Carbon::setLocale('ja');
}
```

言語ファイルを置きます。

```bash
# irodori ディレクトリで実行
mkdir -p lang/ja
touch lang/ja/validation.php lang/ja/auth.php
```

`lang/ja/validation.php` を次の内容にします。

```php
<?php
// lang/ja/validation.php

// ここに無いキーは lang/en の英語メッセージに自動で切り替わります。
return [
    'required'         => ':attributeを入力してください。',
    'required_without' => ':attributeを入力してください。',
    'string'           => ':attributeは文字列で入力してください。',
    'integer'          => ':attributeは数値で入力してください。',
    'boolean'          => ':attributeの指定が正しくありません。',
    'email'            => ':attributeはメールアドレスの形式で入力してください。',
    'confirmed'        => ':attributeが確認用と一致しません。',
    'unique'           => 'その:attributeはすでに使われています。',
    'exists'           => '選択された:attributeが正しくありません。',
    'in'               => '選択された:attributeが正しくありません。',
    'date'             => ':attributeは日付の形式で入力してください。',
    'date_format'      => ':attributeは:format の形式で入力してください。',
    'after'            => ':attributeは:dateより後にしてください。',
    'numeric'          => ':attributeは数値で入力してください。',
    'between'          => [
        'numeric' => ':attributeは:minから:maxの間で指定してください。',
    ],
    'size'             => [
        'string' => ':attributeは:size文字で入力してください。',
    ],
    'max'              => [
        'string'  => ':attributeは:max文字以内で入力してください。',
        'numeric' => ':attributeは:max以下にしてください。',
    ],
    'min'              => [
        'string'  => ':attributeは:min文字以上で入力してください。',
        'numeric' => ':attributeは:min以上にしてください。',
    ],

    'attributes' => [
        'name'                  => 'おなまえ',
        'email'                 => 'メールアドレス',
        'password'              => 'パスワード',
        'password_confirmation' => 'パスワード（確認）',
        'title'                 => 'タイトル',
        'place'                 => '場所',
        'date'                  => '日付',
        'start_time'            => 'はじまる時間',
        'end_time'              => 'おわる時間',
        'starts_on'             => '通いはじめた日',
        'ends_on'               => 'おわる日',
        'day_of_week'           => '曜日',
        'member_id'             => 'だれの',
        'pickup_user_id'        => 'お迎え担当',
        'due_date'              => '期限',
        'invite_code'           => '招待コード',
        'color'                 => '色',
        'note'                  => 'メモ',
    ],
];
```

`lang/ja/auth.php` を次の内容にします。

```php
<?php
// lang/ja/auth.php

return [
    'failed'   => 'メールアドレスかパスワードが正しくありません。',
    'password' => 'パスワードが正しくありません。',
    'throttle' => '試行回数が多すぎます。:seconds秒後にもう一度お試しください。',
];
```

📝 **`lang/ja/validation.php` に無いキーはどうなるか**: 英語のメッセージに自動で切り替わります。Laravel は「今のロケール → フォールバックのロケール」の順に探すので、日本語ファイルが不完全でもエラーにはなりません。安心して必要なものだけ書けます。

---

## 🏃 実践: 見た目の土台を置く

### 🏃 Step 6: CSS を配置する

```bash
# irodori ディレクトリで実行
mkdir -p public/css
touch public/css/irodori.css
```

`public/css/irodori.css` に、アプリ全体のスタイルを書きます。全文は長いので、**設計の考え方**を押さえてください。

```css
/* public/css/irodori.css（先頭部分。全文は既存プロジェクトから流用してください） */
:root{
  --bg:#FAF8F3;          /* 生成りの背景 */
  --surface:#FFFFFF;
  --ink:#3E3A34;         /* 墨っぽい茶 */
  --sub:#8A8175;         /* 補助の文字 */
  --line:#E8E2D8;        /* 罫線 */
  --line-strong:#DAD2C4;
  --accent:#6E8B6A;      /* 深いセージ */
}
*{box-sizing:border-box;}
html,body{margin:0;padding:0;}
body{
  background:var(--bg);
  color:var(--ink);
  font-family:"Hiragino Sans","Noto Sans JP","Yu Gothic",sans-serif;
  font-size:16px;
  line-height:1.6;
}
.wrap{max-width:1040px;margin:0 auto;padding:0 16px 120px;}
.wrap.narrow{max-width:560px;}
```

🔑 **色を CSS 変数（`--bg` など）にまとめる理由**: 色を変えたくなったとき、1か所を直せば全体に効きます。値を直接あちこちに書くと、あとで全ファイルを検索する羽目になります。

**全世代に効くルール**として、次を守ります。これは「おしゃれかどうか」ではなく、祖父母世代が迷わないための実務的な判断です。

1. 本文 `16px` 以上
2. タップできるものは**高さ 48px 以上**
3. 色**だけ**で意味を伝えない（帯には必ず子どもの名前を書く）
4. 1画面の主要ボタンは1つだけ
5. アイコン単体を使わない。必ず文字ラベルを添える
6. 影を使わず、罫線と余白で区切る

### 🏃 Step 7: 共通レイアウトを2つ作る

```bash
# irodori ディレクトリで実行
mkdir -p resources/views/layouts
touch resources/views/layouts/irodori.blade.php
touch resources/views/layouts/irodori-guest.blade.php
```

⚠️ **ファイル名に注意**: `layouts/app.blade.php` や `layouts/guest.blade.php` という名前は**使わないでください**。Fortify や Breeze が同名のファイルを作ることがあり、上書きするとログイン画面が壊れます。`irodori` という独自の名前にしておけば衝突しません。

`resources/views/layouts/irodori.blade.php`（ログイン後の全画面で使う）。

```blade
{{-- resources/views/layouts/irodori.blade.php --}}
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>@yield('title', 'いろどり')</title>
  <link rel="stylesheet" href="{{ asset('css/irodori.css') }}">
</head>
<body>

<header class="app">
  <div class="header-inner">
    <div class="brand">
      <h1><a href="{{ route('home') }}">
        <span class="logo-dots" aria-hidden="true">
          <i style="background:#D79372"></i><i style="background:#8FB2C9"></i><i style="background:#8FAF86"></i><i style="background:#DFB877"></i>
        </span>いろどり</a></h1>
      <span class="family">{{ auth()->user()->family->name ?? '' }}</span>
      <span class="spacer"></span>
      <nav>
        <a href="{{ route('month') }}">月</a>
        <a href="{{ route('lessons.index') }}">習い事</a>
        <a href="{{ route('anniversaries.index') }}">記念日</a>
        <a href="{{ route('tasks.index') }}">提出物</a>
        <a href="{{ route('members.index') }}">家族</a>
        <form method="post" action="{{ route('logout') }}" style="display:inline;">
          @csrf
          <button type="submit">ログアウト</button>
        </form>
      </nav>
    </div>
    @yield('header')
  </div>
</header>

<div class="wrap @yield('wrap-class')">

  @if (session('status'))
    <div class="flash">{{ session('status') }}</div>
  @endif

  @if ($errors->any())
    <div class="errors">
      入力を見なおしてください。
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @yield('content')
</div>

@yield('fab')
@stack('scripts')
</body>
</html>
```

`resources/views/layouts/irodori-guest.blade.php`（ログイン前の画面で使う）。

```blade
{{-- resources/views/layouts/irodori-guest.blade.php --}}
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>@yield('title', 'いろどり')</title>
  <link rel="stylesheet" href="{{ asset('css/irodori.css') }}">
</head>
<body>
<header class="app">
  <div class="header-inner">
    <div class="brand"><h1>
      <span class="logo-dots" aria-hidden="true">
        <i style="background:#D79372"></i><i style="background:#8FB2C9"></i><i style="background:#8FAF86"></i><i style="background:#DFB877"></i>
      </span>いろどり</h1></div>
  </div>
</header>
<div class="wrap narrow">
  @if ($errors->any())
    <div class="errors">
      入力を見なおしてください。
      <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif
  @yield('content')
</div>
</body>
</html>
```

### 覚えておくポイント

**`@yield` と `@section`**: レイアウト側が `@yield('content')` で穴を空け、各画面が `@section('content') ... @endsection` で埋めます。共通部分（ヘッダー・エラー表示）を1か所にまとめるための仕組みです。

**`@stack('scripts')` と `@push('scripts')`**: 各画面から JavaScript を差し込むための仕組みです。`@yield` と違って**複数の場所から積み重ねられる**ので、フォーム部品ごとにスクリプトを持たせられます。

**`asset('css/irodori.css')`**: `public/` からの相対パスで URL を作ります。`public/css/irodori.css` に置いたファイルは `http://localhost/css/irodori.css` で読めます。**ビルドは不要**です。

**エラー表示をレイアウトに置く理由**: バリデーションエラーは全画面で起こります。各画面に書くとコピペになり、1か所だけ書き忘れると「保存したのに何も起きない」という最悪の状態になります。

---

## ✅ 完成チェックリスト

- `sail ps` で `laravel.test`・`mysql`・`phpmyadmin` の3つが `running` である
- `http://localhost` で Laravel のウェルカムページが表示される
- `http://localhost:8080` で phpMyAdmin が開き、`laravel` データベースが見える
- `sail artisan tinker` で `app()->getLocale()` が `"ja"` を返す
- `public/css/irodori.css` と `resources/views/layouts/` の2ファイルが置けた

---

## ✨ まとめ

- `composer create-project laravel/laravel:^10.0` でバージョンを固定してプロジェクトを作った
- Tailwind を使わず素の CSS にすることで、`npm run build` の工程を丸ごと省いた
- レイアウトのファイル名を `irodori` にして、Fortify が作るファイルとの衝突を避けた
- エラー表示とフラッシュメッセージをレイアウトに集約した

次の 9-3 では、テーブルとモデルを作り、家族を守るグローバルスコープを実装します。
