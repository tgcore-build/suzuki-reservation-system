# セキュリティ対策

このシステムのセキュリティ対策と、本番運用で必要な作業をまとめます。

## 1. アプリでの対策（実装済み）

| 項目 | 対策 | 場所 |
| --- | --- | --- |
| CSRF | すべての POST/PUT/DELETE フォームに `@csrf`（Laravel 標準のチェック） | 各 Blade |
| XSS | 画面の出力はすべて `{{ }}` でエスケープ。`{!! !!}` は使っていない | `resources/views` |
| SQLインジェクション | Eloquent / クエリビルダーを使用。生SQLは1か所だけで、値はバインド（`?`）で渡している | `BookingController` |
| パスワード | bcrypt でハッシュ化して保存（平文では保存しない） | `User` モデル |
| 入力チェック | すべてのフォームでバリデーション。エラーメッセージは日本語 | 各コントローラ、`lang/ja` |
| 認証・権限 | 管理画面はすべてログイン必須。新規登録画面はない。テスト用ユーザー（`DatabaseSeeder`）は本番では作らない | `routes/web.php`、`routes/auth.php` |
| ログイン試行の制限 | 5回失敗すると一定時間ログインできない | `LoginRequest` |
| パスワード再設定 | メールで再設定用リンクを送る（本番ではメール送信の設定が必要） | `routes/auth.php` |
| 送信回数の制限 | オンライン予約は1分に10回、AI相談は1分に5回まで。AI相談は1日の上限も、全体とIPアドレスごとに設ける | `routes/web.php` |
| ファイルアップロード | 写真は jpg / png / webp、5MB まで。CSV は csv / txt、2MB まで。ファイル名はランダムに付け直す | `RecordController`、`CustomerImportController` |
| セッション・Cookie | HttpOnly、SameSite=Lax。本番では Secure（HTTPS だけで送信） | `config/session.php` |
| HTTPS | 本番では URL をすべて https にし、HSTS ヘッダーを付ける | `AppServiceProvider`、`SecurityHeaders` |
| 接続元IP | 信頼するプロキシは、プライベートIP（Render の内部）だけ。お客様が `X-Forwarded-For` に書いたIPでは、回数制限をすり抜けられない | `bootstrap/app.php`（`TRUSTED_PROXIES`） |
| セキュリティヘッダー | X-Frame-Options、X-Content-Type-Options、Referrer-Policy、Permissions-Policy | `app/Http/Middleware/SecurityHeaders.php` |
| 二重予約・なりすまし | 同じ日の予約は1件ずつ処理。登録済みの電話番号は、かんたんログインしないと予約できない | `BookingController` |
| ログ | ログイン成功・失敗・ロック・ログアウト・パスワード再設定を記録（パスワードは記録しない） | `app/Listeners/LogAuthActivity.php` |

### アクセス制御について

管理画面にログインできるのはお店のスタッフ（管理者）だけで、全員がすべての顧客データを扱う前提です。
お客様向けの画面（`/book`、`/ai`）では、自分の予約・自分の会話以外は表示されません（会話はセッションに保存した ID でだけ読み込む）。

お客様の利用履歴（`/my`）は、2段階で本人を確認します（`app/Services/CustomerLogin.php`）。

| 段階 | 方法 | できること |
| --- | --- | --- |
| 1 | 電話番号＋生年月日 | 予約する、事前カウンセリングを入力する（表示するのは名前と、一部を隠したメールアドレスだけ） |
| 2 | 登録メールに届く6桁のコード | 利用履歴を見る |

- 見られるのは、セッションに保存した自分の顧客IDのデータだけ（URLに顧客IDを含めない）。
- 1段階目は、電話番号ごとに10分で5回まで。失敗したときは、電話番号が登録されているかどうかが分からない同じメッセージを出す。
- コードはハッシュだけを保存し、10分で無効、5回まちがえると無効、1回使うと無効。送信は10分で3回まで。
- コードは、お店で登録した1つ目のメールアドレスにだけ送る。ログイン中の予約で入力されたアドレスは2つ目に入れ、送り先にしない（他人がアドレスを登録して、コードを受け取るのを防ぐ）。
- 生年月日が未登録の方は、登録メールのコードで確認してから登録する。生年月日が登録済みの人・登録のない電話番号でも同じ画面を出し、登録の有無が分からないようにする。
- 予約画面で、登録済みの電話番号には「ログインしてください」と表示するため、電話番号が登録済みかどうかは分かる（予約の送信は1分に10回までに制限）。
- ログインとコードの確認のときに、セッションIDを作り直す。2段階目の確認は30分で切れる。
- スタッフ向けのメモと使用資材は、お客様に表示しない。
- LINE 連携は、2段階目まで確認できた本人だけができる。LINE ログインは state（なりすまし対策）と nonce を確認し、ユーザーIDは LINE の確認APIで受け取る。1つの LINE アカウントは1人のお客様にだけ連携できる。
スタッフごとに見られる範囲を分ける必要が出た場合は、Policy で権限を追加します。

## 2. 本番で必ず設定すること（Render の環境変数）

| 変数 | 値 |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false`（エラー画面に内部情報を出さない） |
| `APP_KEY` | `php artisan key:generate --show` で作った値 |
| `APP_URL` | `https://〜`（Render のURL） |
| `APP_LOCALE` / `APP_TIMEZONE` | `ja` / `Asia/Tokyo` |
| `LOG_LEVEL` | `warning` 以上（`debug` にしない） |
| `SESSION_SECURE_COOKIE` | `true`（未設定でも production なら true） |
| `DB_*` | Aiven の接続情報。SSL 接続を使う |
| `GEMINI_API_KEY` | Render の画面でだけ設定する（render.yaml には値を書かない） |
| `LINE_LOGIN_CHANNEL_SECRET`・`LINE_MESSAGING_ACCESS_TOKEN` | 同じく Render の画面でだけ設定する。漏れたら LINE Developers で再発行する |
| `TRUSTED_PROXIES` | 通常は設定しない（プライベートIPだけを信頼）。デプロイ後に下の「接続元IPの確認」をする |

### 接続元IPの確認（デプロイ後に1回）

1. 本番の管理画面にログインする。
2. Render の Logs で「ログイン成功」の行の `ip` が、自分のIPアドレス（「IP確認」などで検索して調べる）と同じか確かめる。
3. 違う場合（Render のプロキシのIPなどになっている場合）は、IPごとの回数制限が全員まとめて数えられてしまうので、`TRUSTED_PROXIES` を見直す。

## 3. 秘密情報の管理

- `.env` は `.gitignore` に入っていて、Git に載せていない（過去のコミットにも含まれていないことを確認済み）。
- APIキー・DBパスワードは、Render の環境変数にだけ登録する。
- 漏れたと思ったときの手順：
  1. Gemini の APIキー：Google AI Studio でキーを削除して作り直し、Render の `GEMINI_API_KEY` を差し替える。
  2. DBパスワード：Aiven の画面でパスワードを変更し、Render の `DB_PASSWORD` を差し替える。
  3. `APP_KEY`：作り直すと全員ログアウトされる。差し替えたあと、管理者が再ログインする。
  4. 管理者パスワード：ログインしてプロフィール画面から変更する。

## 4. サーバー・DB

- Render：外に開くのは Web（HTTPS）だけ。HTTPS は Render が自動で用意する。
- Aiven：接続には SSL とパスワードが必要。可能なら接続元の IP 制限をかける。
- 手元の Docker：アプリ（8000）と MySQL（3306）は自分のPC（127.0.0.1）からだけ接続できる。
- 写真：ローカルは `storage/app/private`（`PHOTO_DISK=local`）に置き、ログインした管理者と、確認コードまで済ませた本人のお客様だけが通れるURLで表示する。本番は S3 の非公開のバケットに置き、30分だけ有効な一時URLで表示する（`PHOTO_DISK=s3`）。`public` にはしない。

## 5. バックアップと復旧

- Aiven の管理画面で、自動バックアップの有無と保存期間を確認する（プランによって違う）。
- 手動バックアップ（月1回など、定期的に行う）：

  ```sh
  mysqldump -h <ホスト> -P <ポート> -u <ユーザー> -p --ssl-mode=REQUIRED --single-transaction <DB名> > backup-YYYYMMDD.sql
  ```

- 復旧：

  ```sh
  mysql -h <ホスト> -P <ポート> -u <ユーザー> -p --ssl-mode=REQUIRED <DB名> < backup-YYYYMMDD.sql
  ```

- バックアップファイルには個人情報が入るので、Git に入れず、安全な場所に保存する。

## 6. 個人情報

- お客様から受け取るのは、予約に必要な名前・電話番号・メールアドレスだけ。
- 顧客は管理画面から削除できる（対応記録も一緒に削除される）。
- AI相談の会話記録と、削除した顧客の写真ファイルは、今は自動では削除しない（必要になったら追加する）。
- AI相談の内容（頭皮の症状など健康に関わる情報を含むことがある）は Google Gemini に送られる。
  - 無料枠では、入力内容が Google のサービス改善に使われることがある。本番運用では有料枠に切り替える予定。
  - それまでは、相談画面に「外部のAIサービスに送信される」「個人情報は書かない」ことを表示している。

## 7. 依存パッケージ

定期的に（月1回など）次を実行し、既知の脆弱性がないか確認する。

```sh
composer audit     # PHP のパッケージ
composer outdated --direct
npm audit          # 画面の部品（ビルド時だけ使う）
```

- 2026-10-22 時点：`composer audit`・`npm audit` とも問題なし。Tailwind CSS を 4 に更新済み（v3 の依存にあった指摘は解消）。
