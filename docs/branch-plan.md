# ブランチとコミットの計画

残り5ブランチ。1ブランチ＝1PR＝Squash and merge で main に1コミット。

---

## ブランチ一覧

| ブランチ名 | やること | 対応する手順書 |
|---|---|---|
| `feature/family-auth` | ログインと、招待コードによる家族共有 | 9-4 |
| `feature/schedule-crud` | 子ども・習い事・予定の登録編集 | 9-5 |
| `feature/week-timeline` | 週タイムライン（帯表示・重なりの横並び） | 9-6 |
| `feature/month-calendar` | 月カレンダー・記念日・提出物 | 9-7 |
| `feature/weather-forecast` | 天気予報API | 9-8 |

ブランチ名は**やっていること**が分かる名前にしています。章番号だと、あとから履歴を見たときに何の作業か分かりません。

---

## ⚠️ 進める順番についての注意

手順書は「機能ごと」に書いてあるので、**そのままの順で作ると一時的に動かない箇所**があります。2点だけ先に知っておいてください。

### ① ログイン後の飛び先 `/home` は、9-6 まで仮のものを置く

`/home` は本来、週タイムライン（`WeekController`）です。でもそれを作るのは 9-6。

そこで `feature/family-auth` では、**仮の `/home`** を置きます。家族の名前と招待コードだけを表示する簡単な画面です。9-6 で本物に差し替えます。

### ② 共通レイアウトのナビは、ルートが増えるたびに開放していく

`layouts/irodori.blade.php` のナビには「月」「習い事」「記念日」「提出物」「家族」へのリンクがあります。これらのルートは 9-5 以降で作るので、**いま全部を有効にするとエラーになります**。

```
Route [lessons.index] not defined.
```

なので最初はログアウトだけにしておき、**ルートを作った回で1つずつコメントを外します**。

---

## `feature/family-auth`（9-4）

```bash
git switch main
git pull
git switch -c feature/family-auth
```

### コミット① `chore: Fortify を導入して使う機能を絞る`

```bash
sail composer require laravel/fortify
sail artisan vendor:publish --provider='Laravel\Fortify\FortifyServiceProvider'
```

- `config/app.php` の `providers` に `App\Providers\FortifyServiceProvider::class` を追加
- `config/fortify.php` の `features` を `Features::registration()` だけにする（他はコメントアウト）

```bash
sail artisan migrate        # 2要素認証の列を作るマイグレーションが走る
sail artisan config:clear
```

**この時点の状態**: `/login` を開くと `View [auth.login] not found` になります。**正常です。** 画面は次のコミットで作ります。

```bash
git add .
git commit -m "chore: Fortify を導入して使う機能を絞る

登録機能のみ有効化。パスワード再設定と2要素認証は画面を用意する
義務が生じるため、今回は features から外した。"
```

---

### コミット② `feat: ログイン・新規登録の画面を追加`

- `app/Providers/FortifyServiceProvider.php` の `boot()` に2行

```php
use Laravel\Fortify\Fortify;

Fortify::loginView(fn () => view('auth.login'));
Fortify::registerView(fn () => view('auth.register'));
```

- `resources/views/auth/login.blade.php` と `register.blade.php` を作る（手順書 9-4 Step 5）
- `resources/views/welcome.blade.php` を差し替える（Laravel の初期画面 → いろどりのトップ）
- `routes/web.php` のトップページを書き換える

```php
Route::get('/', fn () => auth()->check()
    ? redirect()->route('home')
    : view('welcome'))->name('top');
```

**この時点の確認**: `http://localhost` でいろどりのトップが出て、「はじめる」から新規登録ができる。

登録が完了すると `/home` へ飛ばされて **404 になります。** これも正常です。次のコミットで作ります。

```bash
git add .
git commit -m "feat: ログイン・新規登録の画面を追加

Fortify は画面を持たないので、自作のデザインで用意した。
Tailwind を使わないため npm のビルドは不要。"
```

---

### コミット③ `feat: 家族の作成と招待コードによる参加を実装`

**1. `app/Models/User.php` に2つ足す**

```php
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

⚠️ `$fillable` に `family_id` は**足さないでください**。

**2. コントローラを作る**

```bash
sail artisan make:controller FamilyController
```

手順書 9-4 Step 8 の `setup` / `store` / `join` / `invite` を書きます。

**3. 画面を作る**

```bash
mkdir -p resources/views/family
touch resources/views/family/setup.blade.php resources/views/family/invite.blade.php
```

手順書 9-4 Step 9 の内容です。

**4. 仮の `/home` を作る**

```bash
touch resources/views/home-placeholder.blade.php
```

```blade
{{-- resources/views/home-placeholder.blade.php --}}
{{-- 9-6 で週タイムラインに差し替える仮の画面 --}}
@extends('layouts.irodori')
@section('title', 'ホーム — いろどり')
@section('wrap-class', 'narrow')

@section('content')
  <div class="card">
    <h2>ログインできました</h2>
    <p class="lead">週タイムラインは 9-6 で作ります。</p>

    <div class="nowset">
      家族：<strong>{{ auth()->user()->family->name }}</strong>
    </div>

    <div class="invite-code">{{ auth()->user()->family->invite_code }}</div>
    <p class="lead">このコードを家族に教えると、同じ画面が見られます。</p>

    <div class="btn-row">
      <a class="btn" href="{{ route('family.invite') }}">招待コードの画面</a>
    </div>
  </div>
@endsection
```

**5. レイアウトのナビを、いま使えるものだけにする**

`resources/views/layouts/irodori.blade.php` の `<nav>` を一旦こうします。

```blade
      <nav>
        {{-- ルートを作った回にコメントを外していく --}}
        {{-- <a href="{{ route('month') }}">月</a> --}}
        {{-- <a href="{{ route('lessons.index') }}">習い事</a> --}}
        {{-- <a href="{{ route('anniversaries.index') }}">記念日</a> --}}
        {{-- <a href="{{ route('tasks.index') }}">提出物</a> --}}
        {{-- <a href="{{ route('members.index') }}">家族</a> --}}
        <form method="post" action="{{ route('logout') }}" style="display:inline;">
          @csrf
          <button type="submit">ログアウト</button>
        </form>
      </nav>
```

**6. ルートを書く**

```php
Route::middleware('auth')->group(function () {

    Route::get('/family/setup', [FamilyController::class, 'setup'])->name('family.setup');
    Route::post('/family',      [FamilyController::class, 'store'])->name('family.store');
    Route::post('/family/join', [FamilyController::class, 'join'])->name('family.join');

    // 9-6 で WeekController に差し替える
    Route::get('/home', fn () => view('home-placeholder'))->name('home');

    Route::get('/family/invite', [FamilyController::class, 'invite'])->name('family.invite');
});
```

**この時点の確認**

- 新規登録 → `/family/setup` で家族を作る → `/home` に家族名と招待コードが出る
- **シークレットウィンドウ**で2人目を登録 → 招待コードを入力 → 同じ家族名が出る

```bash
git add .
git commit -m "feat: 家族の作成と招待コードによる参加を実装

8文字の招待コードで users.family_id を揃えることで共有を成立させる。
コードの文字種は O/0/I/1 を除いた32種（読み間違い防止）。
family_id は \$fillable に入れず forceFill() のみで書き込む。"
```

---

### コミット④ `feat: 家族未所属のユーザーを振り分けるミドルウェアを追加`

**先に、わざと壊れるところを見てください。**

ログアウトして、3人目のアカウントを新規登録します。そして家族を作らずに、URL に直接 `http://localhost/home` と打ちます。

```
Attempt to read property "name" on null
```

家族がないので `auth()->user()->family` が null になり、エラーになります。**これを防ぐのがミドルウェアです。**

```bash
sail artisan make:middleware EnsureHasFamily
```

```php
public function handle(Request $request, Closure $next)
{
    if (! $request->user()?->family_id) {
        return redirect()->route('family.setup');
    }

    return $next($request);
}
```

`app/Http/Kernel.php` の `$middlewareAliases` に1行。

```php
'has.family' => \App\Http\Middleware\EnsureHasFamily::class,
```

そして `routes/web.php` を入れ子にします。

```php
Route::middleware('auth')->group(function () {

    // --- 家族に入っていない人も通れる ---
    Route::get('/family/setup', [FamilyController::class, 'setup'])->name('family.setup');
    Route::post('/family',      [FamilyController::class, 'store'])->name('family.store');
    Route::post('/family/join', [FamilyController::class, 'join'])->name('family.join');

    // --- 家族に入っている人だけ ---
    Route::middleware('has.family')->group(function () {
        Route::get('/home', fn () => view('home-placeholder'))->name('home');
        Route::get('/family/invite', [FamilyController::class, 'invite'])->name('family.invite');
    });
});
```

⚠️ **家族作成の3つを内側に入れないでください。** 入れると無限リダイレクトになります。

**この時点の確認**: さっきエラーになった3人目で `/home` を開くと、家族作成画面へ飛ばされる。

```bash
git add .
git commit -m "feat: 家族未所属のユーザーを振り分けるミドルウェアを追加

family_id が null のユーザーを family.setup へ誘導する。
家族作成の3ルートは auth の直下に置き、無限リダイレクトを避けた。"
```

---

### PR を出してマージ

```bash
git push -u origin feature/family-auth
```

ターミナルに出る URL から PR を作ります。本文は手順書 9-4 の「PR の書き方」のテンプレを使ってください。

**マージ前に「Files changed」タブを必ず開く。** チェックする点。

- [ ] `.env` が混ざっていない
- [ ] `dd()` の消し忘れがない
- [ ] `config/fortify.php` の `features` が絞られている
- [ ] `User` の `$fillable` に `family_id` が入っていない
- [ ] ルートの入れ子が正しい（家族作成が `has.family` の外）

「Squash and merge」でマージ → 「Delete branch」。

```bash
git switch main
git pull
git branch -d feature/family-auth
```

---

## 以降のブランチのコミット区切り（目安）

### `feature/schedule-crud`（9-5）

1. `feat: 子どもの登録と色の選択を実装` ＋ ナビの「家族」を開放
2. `feat: 習い事の登録・編集・削除を実装` ＋ ナビの「習い事」を開放
3. `feat: 予定の登録・編集・削除を実装`
4. `chore: 確認用のデモデータを入れるシーダーを追加`

### `feature/week-timeline`（9-6）

1. `feat: 週タイムラインの枠と時間メモリを表示`
2. `feat: 習い事と予定を帯として配置`（仮の `/home` を `WeekController` に差し替え）
3. `feat: 時間が重なる予定を横に並べる`
4. `feat: スマホ向けに1日表示へ切り替える`
5. `chore: 仮のホーム画面を削除`

### `feature/month-calendar`（9-7）

1. `feat: 月カレンダーを表示` ＋ ナビの「月」を開放
2. `feat: 記念日を毎年くりかえし表示する` ＋ ナビの「記念日」を開放
3. `feat: 提出物の管理を実装` ＋ ナビの「提出物」を開放
4. `feat: 月表示に行事名と子どもの絞り込みを追加`

### `feature/weather-forecast`（9-8）

1. `feat: 天気予報を取得するサービスクラスを追加`
2. `feat: 地名から地点を設定する画面を追加`
3. `feat: 月カレンダーに天気を表示`

---

## コミットの区切り方の考え方

**「この状態で人に見せられるか」** で区切ります。

```
✕ とりあえず保存            ← 何をしたか分からない
✕ 作業中                    ← 同じ
✕ 予定とカレンダーと天気     ← 大きすぎる。1つずつに分ける

○ feat: 習い事の登録・編集・削除を実装      ← 1つの機能が完結している
○ fix: 週をまたぐと日付がずれる問題を修正   ← 1つの問題を直している
```

ブランチ上では細かく刻んで構いません（Squash でまとめられるので）。ただし**1コミット1目的**を守ると、あとから `git log` を読んだときに自分の作業が追えます。
