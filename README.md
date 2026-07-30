# 織光舎 Website — Journal 記事追加手順

このリポジトリでは、ジャーナル記事を `journal/` フォルダ内に HTML ファイルとして追加し、トップページの `#journal` セクションにカードを置いてリンクする運用を推奨します。

## 1. 新しい記事ファイルを作る

1. `journal/template.html` をコピーして、ファイル名を `YYYY-MM-DD-your-slug.html` の形式にします（例: `2026-07-16-sample-post.html`）。
2. `<title>` と `<meta name="description">`、`<link rel="canonical">` を編集してください。
3. 記事本文と画像パス（画像は `images/` に置く）を編集します。

例: ファイル名

```
journal/2026-07-16-your-title.html
```

## 2. トップページにカードを追加

`index.html` の `.journal-grid` 内に記事カードを追加します。例:

```html
<article>
	<a href="journal/2026-07-16-your-title.html">
		<div class="ph"><img src="images/your-thumbnail.webp" alt="サムネイル説明"></div>
		<time>2026.07.16</time>
		<h3>記事の短いタイトル</h3>
	</a>
</article>
```

追加後はローカルサーバ（例: `http://localhost:8000/`）でリンクと見た目を確認してください。

## 3. 画像の扱い

- 画像は `images/` に入れ、`webp` を推奨します。ファイル名は英数字とハイフンで分かりやすく。例: `Journal_post_01.webp`。
- 必ず `alt` 属性を設定してください。

## 4. コミットとデプロイ

変更をコミットしてプッシュします:

```bash
git add journal/ images/ index.html
git commit -m "Add journal post: 2026-07-16 your-title"
git push
```

## 5. 補足
- 現在 `journal.html` は `index.html#journal` にリダイレクトする簡易ページです。将来的にジャーナル専用一覧ページを作る場合は `journal/index.html` を作成して `journal.html` を差し替えてください。
- スラグは小文字・ハイフン推奨、公開日で重複を避けてください。

## 6. トラブルシューティング
- カードが表示されない場合: `index.html` のパス（`href`）と `journal/` 内のファイル名を確認してください。
- 画像が表示されない場合: `images/` に配置されているか、ファイル名が一致しているか確認してください。

必要なら私が新しい記事の追加やトップページカードの追加を代行します。

## トップページのリンク更新ワークフロー

## 7. PHP / reCAPTCHA セットアップ
このサイトでは `reserve.html` から `reserve.php` へのご予約フォーム送信時に Google reCAPTCHA を検証します。本番運用前に以下の設定を必ず行ってください。

1. `config.example.js` を `config.js` にコピーし、`recaptchaSiteKey` に実運用の Site Key を設定します。
   - `config.js` は本番で HTML から読み込まれ、`reserve.html` の reCAPTCHA ウィジェットに自動で反映されます。
   - `config.example.js` はリポジトリに含め、実運用キーは `config.js` にのみ保持してください。
2. `config.example.php` を `config.php` にコピーして、`recaptcha_secret` に実運用の Secret Key を設定します。
   - `config.php` は `.gitignore` に含まれているため、ソース管理へコミットされません。
3. PHP の `php.ini` で以下の設定を有効にしておくと安定します。
   - `extension=mbstring`
   - `extension=openssl`
   - `date.timezone = "Asia/Tokyo"`
4. 開発環境で確認する場合は、以下のコマンドでローカルサーバを起動します。

```powershell
cd C:\Users\bigbe\Documents\Github\orikohsha
php -S localhost:8000
```

ブラウザで `http://localhost:8000/reserve.html` を開き、フォーム送信後に `reserve.html?sent=1` へリダイレクトされることを確認してください。

### 本番運用時の注意
- Google reCAPTCHA のテストキーは本番では使わず、必ず実運用サイトキーとシークレットキーに差し替えてください。
- メール送信を行う場合、`php.ini` の `SMTP` / `sendmail_path` などの設定も確認してください。

### いつ更新するか
- 新しい記事を公開する時。
- 既存記事を削除・移動したとき（リンク切れを防ぐため）。

### 手順（短く）
1. `journal/` に記事ファイルを追加（`journal/template.html` をコピー）。
2. `index.html` の `.journal-grid` にカードを追加または削除する。新着はリスト上部に置くと分かりやすいです。
3. カード内のリンクは相対パスで `journal/your-slug.html` を使う。
4. `<time>` を更新し、画像は `images/` に配置して `alt` を書く。サムネイルは横長ではなく正方形（CSSが正方形を想定）を推奨します。
5. ローカルサーバで確認（`http://localhost:8000/`）: カードをクリック → 個別ページへ遷移するか。モバイル（ハンバーガーメニュー）でも動作するか確認。
6. 変更をコミット／プッシュ:

```bash
git add journal/ index.html images/
git commit -m "Add journal post: YYYY-MM-DD your-slug"
git push
```

### 削除・移動の注意
- 記事ファイルを削除する前に、`index.html` の該当カードを先に削除してください（ユーザーに404を踏ませないため）。

### 自動化案（任意）
- 記事が増える場合は、カード生成の小さなスクリプト（Node/Python等）を作り、テンプレートから `index.html` の該当部分を自動更新することを検討してください。

不明点があれば、このワークフローに合わせた `git` のブランチ・PR 手順や自動化スクリプトを作成します。

