# いろどり — Git / GitHub の運用手順

ブランチを切って PR を出し、マージして main を育てていく流れ。

---

## 0. 前提の確認

プロジェクトの場所はここです。**内側**の `irodori` が Laravel プロジェクトです。

```
~/coachtech/laravel/irodori/irodori/   ← composer.json があるのはここ
```

Git はこの階層で初期化します。`cd` を間違えると、空のフォルダをリポジトリにしてしまうので注意してください。

📝 **二重フォルダを直したい場合**（任意）: 動作には影響しませんが、気になるなら Finder で内側の中身を1つ上へ移してください。`sail` を止めてから行い、移したあとに `sail up -d` で起動し直します。**Git を始める前にやる**のがおすすめです（あとからだとパスが変わって面倒）。

---

## 1. リポジトリを作って main を用意する

### Step 1-1: git を初期化する

```bash
cd ~/coachtech/laravel/irodori/irodori

git init
git branch -M main          # 既定のブランチ名を main にする
```

📝 `git branch -M main` は「今いるブランチを main に改名する」コマンドです。Git のバージョンによって初期ブランチ名が `master` になることがあるので、明示的に揃えます。

### Step 1-2: 何がコミットされるか確認する

**いきなり `git add .` を打たないでください。** まず中身を見ます。

```bash
git status --short | head -40
git status --short | wc -l        # 何ファイルあるか
```

そして**ここがいちばん大事**です。

```bash
git check-ignore -v .env vendor node_modules
```

3つとも「`.gitignore` の何行目で無視されているか」が表示されればOKです。何も出なかったら `.gitignore` が効いていません。

⚠️ **`.env` を絶対にコミットしないでください。** データベースのパスワードや `APP_KEY` が入っています。GitHub に上げると、Public なら世界中から読めます。Private でも、後から Public にした瞬間に履歴ごと公開されます。**一度 push した秘密情報は、消しても履歴に残る**と考えてください。

Laravel の `.gitignore` は最初から `.env` と `vendor` を無視するようになっているので、通常は問題ありません。確認する習慣をつける、という意味でのチェックです。

### Step 1-3: 最初のコミット

```bash
git add .
git commit -m "chore: Laravel 10 + Sail の初期構成とテーブル・モデルを作成

- Sail + MySQL + phpMyAdmin の環境
- families / members / lessons / events / tasks / anniversaries のマイグレーション
- 各モデルのリレーション、BelongsToFamily トレイト
- 共通レイアウトと CSS"
```

📝 **コミットメッセージの1行目と本文**: 1行目は要約（50文字くらい）、1行空けて本文に詳細を書きます。GitHub では1行目がタイトルとして表示されます。

### Step 1-4: GitHub にリポジトリを作る

**ブラウザで作る場合**

1. https://github.com/new を開く
2. Repository name に `irodori`
3. **Private** を選ぶ（学習中は Private が無難。あとから Public にできます）
4. **「Add a README file」「Add .gitignore」「Choose a license」はすべてチェックしない**
5. 「Create repository」

⚠️ **4 が重要です。** ここでファイルを作らせると、手元の履歴と GitHub の履歴が枝分かれして、最初の push が拒否されます。空のリポジトリを作るのが正解です。

**GitHub CLI（`gh`）が入っている場合**

```bash
gh repo create irodori --private --source=. --remote=origin --push
```

これ1行で、リポジトリ作成・リモート登録・push まで終わります。

### Step 1-5: リモートを登録して push

ブラウザで作った場合は、こちらを実行します。表示された URL を使ってください。

```bash
git remote add origin git@github.com:あなたのユーザー名/irodori.git
git remote -v                        # 登録されたか確認
git push -u origin main
```

📝 **`git@github.com:...`（SSH）と `https://github.com/...`（HTTPS）の違い**: BookShelf で使っていたのと同じ方式を選んでください。SSH なら鍵の設定が済んでいるので、パスワードを聞かれません。分からなければ `cd ~/coachtech/laravel/bookshelf && git remote -v` で確認できます。

📝 **`-u` の意味**: 「このブランチは origin/main と対応している」と覚えさせるオプションです。次回以降は `git push` だけで済みます。

---

## 2. BookShelf のリモートを付け替えたい場合

「同じフォルダのリモート先を変えたい」場合はこちらですが、**今回は使いません**（別プロジェクトなので）。参考として。

```bash
git remote set-url origin git@github.com:ユーザー名/別のリポジトリ.git
git remote -v
```

`add` は新規登録、`set-url` は付け替えです。間違えて `add` すると「もう origin がある」と言われます。

---

## 3. これからの運用サイクル

残っているのは 9-4 から 9-8 です。**セクションごとに1ブランチ・1PR**にします。

| ブランチ名 | 内容 |
|---|---|
| `feature/9-4-auth` | Fortify と招待コード |
| `feature/9-5-crud` | 子ども・習い事・予定の CRUD |
| `feature/9-6-week` | 週タイムライン |
| `feature/9-7-month` | 月カレンダー・記念日・提出物 |
| `feature/9-8-weather` | 天気予報API |

### 1周のながれ

```bash
# ① main を最新にしてから枝を切る
git switch main
git pull
git switch -c feature/9-4-auth

# ② 作業する（Fortify を入れる、コントローラを書く…）
#    途中で何度でもコミットしていい
git add .
git commit -m "feat: Fortify を導入して登録・ログインを実装"

git add .
git commit -m "feat: 招待コードで家族に参加できるようにする"

# ③ GitHub に上げる
git push -u origin feature/9-4-auth

# ④ ブラウザで Pull Request を作る（push すると GitHub にリンクが出ます）

# ⑤ 自分でレビューしてマージ（下のチェックリスト参照）

# ⑥ 手元を片づける
git switch main
git pull
git branch -d feature/9-4-auth
```

このあと `feature/9-5-crud` で同じことを繰り返します。

📝 **`git switch` と `git checkout`**: `switch` はブランチ移動専用の新しいコマンドです。`checkout` でも同じことができますが、`checkout` はファイルの復元も兼ねていて紛らわしいので、ブランチ移動には `switch` を使うのがおすすめです。

- `git switch main` … main に移動
- `git switch -c 新しい名前` … 新しいブランチを作って移動（`checkout -b` と同じ）

### 途中でコミットを刻んでいい理由

⑤ でマージするとき **「Squash and merge」** を選びます。これはブランチ上の複数のコミットを**1つにまとめて** main に載せる方式です。

だから、ブランチ上では「とりあえず保存」くらいの粒度でコミットして構いません。main の履歴は「機能ごとに1コミット」というきれいな形に保たれます。

```
main の履歴（Squash and merge を使った場合）

* feat: 天気予報APIを組み込む          ← 9-8 のPRが1コミットに
* feat: 月カレンダーと記念日を追加      ← 9-7
* feat: 週タイムラインを実装            ← 9-6
* feat: 子ども・習い事・予定のCRUD      ← 9-5
* feat: Fortifyと招待コードで家族共有    ← 9-4
* chore: Laravel 10 + Sail の初期構成   ← 最初のコミット
```

面接やポートフォリオで履歴を見せるとき、これはかなり効きます。

---

## 4. コミットメッセージの書き方

先頭に種類を付ける書き方（Conventional Commits）に揃えると、あとから探しやすくなります。

| 接頭辞 | 使いどころ | 例 |
|---|---|---|
| `feat:` | 機能を追加した | `feat: 週タイムラインに重なりの横並びを実装` |
| `fix:` | バグを直した | `fix: 帯の位置が1時間ずれる問題を修正` |
| `refactor:` | 動きは変えず整理した | `refactor: 重なり判定をレイアウト用メソッドに切り出す` |
| `style:` | 見た目・インデントだけ | `style: 終日の枠を囲み線にする` |
| `docs:` | ドキュメントだけ | `docs: Git運用手順を追加` |
| `chore:` | 設定・雑用 | `chore: Fortify を導入` |
| `test:` | テスト | `test: 家族の分離を確認するテストを追加` |

**日本語で書いて構いません。** 大事なのは「何をしたか」ではなく「**なぜそうしたか**」が分かることです。

```
✕ fix: バグ修正
✕ feat: 修正しました
✕ feat: WeekController.php を変更

○ fix: 週をまたぐと日付がずれる問題を修正（copy() の付け忘れ）
○ feat: 習い事を曜日だけで持ち、表示時に各週へ展開する
```

「ファイル名」はコミットを見れば分かります。書くべきは**意図**です。

---

## 5. Pull Request の書き方

1人開発でも本文を書く価値があります。**自分の考えを言語化すると、設計の穴に気づく**からです。

### テンプレート

```markdown
## やったこと
Fortify を導入し、招待コードで家族を共有できるようにした。

## 変更の中身
- Fortify を導入（使う機能は registration のみに絞った）
- `Family::generateInviteCode()` で8文字の招待コードを生成
- `EnsureHasFamily` ミドルウェアで、家族未所属のユーザーを setup 画面へ
- ログイン・登録画面を自作のデザインで作成

## 設計の判断
- 招待メールではなく招待コードにした。メール送信の設定が不要で、口頭でも伝えられるため
- 文字種から `O/0/I/1` を除外（32種8文字＝約1.1兆通り）
- `users.$fillable` に `family_id` を入れず、`forceFill()` のみで書き込む
  （登録フォームから他人の家族に潜り込まれるのを防ぐ）

## 確認したこと
- [x] 2つのアカウントで同じ予定が見える（シークレットウィンドウで確認）
- [x] **別家族のデータが URL 直打ちで見えない（404 になる）**
- [x] 家族未所属のユーザーが setup 画面へ飛ぶ
- [x] `.env` がコミットに含まれていない

## 残していること
- 招待コード入力のレート制限は未実装（実務では必要）
```

「設計の判断」と「残していること」の欄は特におすすめです。**割り切った理由を書き残す**と、後から読んだときに「ミスか意図か」で悩みません。

### PR を作る手順

`git push` すると、ターミナルに GitHub のリンクが出ます。それを開くと PR 作成画面になります。

`gh` が入っていれば、こちらでも作れます。

```bash
gh pr create --base main --title "feat: Fortifyと招待コードで家族共有を実装" --body-file .github/pr-body.md
```

---

## 6. マージする前のセルフレビュー

PR 画面の **「Files changed」タブを必ず開いてください。** これがPRを出す最大の実利です。差分として並べて見ると、コードを書いているときには見えなかったものが見えます。

チェックする観点。

- [ ] **`.env` や `vendor/` が混ざっていないか**（いちばん大事）
- [ ] 消し忘れた `dd()` や `var_dump()` がないか
- [ ] コメントアウトした死んだコードが残っていないか
- [ ] 意図せず触ったファイルがないか（見覚えのないファイルが並んでいたら要注意）
- [ ] 変数名・メソッド名が、他のファイルと揃っているか
- [ ] 新しいモデルに `BelongsToFamily` を付け忘れていないか

問題が見つかったら、**そのブランチで直して push し直す**だけです。PR は自動で更新されます。

### マージのしかた

1. 「Merge pull request」の横の **▼** を押す
2. **「Squash and merge」** を選ぶ
3. コミットメッセージを整える（PR タイトルが入っているので、必要なら直す）
4. 「Confirm squash and merge」
5. 「Delete branch」（GitHub 側のブランチを消す）

---

## 7. やってはいけないこと

| | なぜ |
|---|---|
| **`.env` をコミットする** | パスワードと APP_KEY が流出する。履歴からは消しにくい |
| **`vendor/` をコミットする** | 何万ファイルになる。`composer install` で再現できるものは管理しない |
| **main に直接コミットする** | PR の練習にならない。差分を見ずにマージすることになる |
| **`git push --force` を main にする** | 履歴が壊れる。自分のブランチ以外では絶対に使わない |
| **1つのPRに複数の機能を詰める** | レビューできなくなる。「このPRは何をするものか」を1文で言えない大きさは分けすぎのサイン |

---

## 8. 詰まったときのコマンド

```bash
# いまどこにいるか
git status
git branch

# コミットする前に、変更の中身を見る
git diff                    # まだ add していない変更
git diff --staged           # add した変更

# 直前のコミットメッセージを直したい（push する前だけ）
git commit --amend

# ファイルの変更を捨てて元に戻したい
git restore ファイル名

# add を取り消したい（変更は残る）
git restore --staged ファイル名

# 直前のコミットを取り消したい（変更は残る。push 前だけ）
git reset --soft HEAD^

# ブランチを間違えて作業してしまった（まだコミットしていない）
git stash                   # 変更を一時退避
git switch 正しいブランチ
git stash pop               # 戻す

# リモートの状態を見たい
git remote -v
git log --oneline --graph --all -15
```

---

## 9. 今回やること（まとめ）

```bash
# ① 今の状態を main に載せる
cd ~/coachtech/laravel/irodori/irodori
git init
git branch -M main
git check-ignore -v .env vendor          # ← 確認してから
git add .
git commit -m "chore: Laravel 10 + Sail の初期構成とテーブル・モデルを作成"

# ② GitHub に空のリポジトリ irodori を作る（README なし・Private）

# ③ つないで push
git remote add origin git@github.com:ユーザー名/irodori.git
git push -u origin main

# ④ 9-4 用のブランチを切って、続きから作業
git switch -c feature/9-4-auth
```

以降は「3. これからの運用サイクル」を5回くり返せば、9-8 まで到達します。
