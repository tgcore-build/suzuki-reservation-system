# 予約・顧客管理システム

予約制サービス業（個人サロン）向けの、予約・顧客管理システムです。techmeets Week22〜24 の総合演習として、個人で開発しています。

紙や LINE に分かれていた「予約」「顧客情報」「施術の記録」「来店前の連絡」を、1つの管理画面にまとめることを目指しました。お客様は、ログインなしで空き状況を見て、そのまま予約できます。

## 現在の状況（Week24）

| 区分 | 内容 | 状態 |
|---|---|---|
| 管理者 | ログイン、ダッシュボード | 完了 |
| 管理者 | 顧客管理（登録・検索・編集）、顧客のCSV取り込み | 完了 |
| 管理者 | 予約管理（一覧・登録・編集・キャンセル）、予約表（1週間／2週間） | 完了 |
| 管理者 | 対応記録（提供内容・使用資材・時間・メモ・前後の写真） | 完了 |
| 管理者 | メニュー管理、営業時間の設定 | 完了 |
| 自動処理 | 前日18:00にリマインドメールを自動送信 | 完了 |
| お客様 | 空き状況表（1週間／2週間）から予約、確認メール | 完了 |
| 公開 | 本番環境へのデプロイ | これから |
| フェーズ2 | AI提案機能：API選定（#12）、チャットとAI連携（#13）、会話の保持（#14）、提案結果の保存・一覧（#15） | 完了（試験運用中） |
| フェーズ2 | 顧客向け利用履歴ページ・簡易ログイン（#11） | 完了 |

## 主な機能

### 管理者向け

- **ダッシュボード**：明日・今後7日間の予約件数、顧客数、本日の予約、リマインドの送信状況（送信済み／未送信／メール未登録）。
- **顧客管理**：氏名・電話・メールでの検索。詳細画面に、対応記録と予約履歴をまとめて表示。
- **CSV取り込み**：既存ソフトから出力した顧客CSVを、プレビューで確認してから登録。文字コード（UTF-8／Shift_JIS）を自動判別、電話番号・生年月日を整形、重複（同じ電話番号）と不備のある行は取り込まず理由を表示。
- **予約管理・予約表**：メニューの所要時間を使った重複チェック。予約表は週・2週間の切り替えができ、予約を時間の長さに合わせて表示。
- **対応記録**：施術内容・カウンセリングシート（お悩みのチェック項目と自由記入。お客様にも表示）・使用資材・時間・メモ（スタッフ向け）・施術前後の写真。保存先は設定で切り替え可能（ローカル／S3）。

### お客様向け：オンライン予約（`/book`）

- メニューを選ぶと、営業時間・既存の予約・直前の受付制限（60分前まで）・予約可能期間（60日先まで）から、予約できる枠を計算して表（○／×）で表示。
- 表の日時を選んで予約すると確定。確認メールを送信。
- **登録済みのお客様**は、かんたんログイン（電話番号＋生年月日）をしてから予約する。名前などの入力は不要。
- **はじめてのお客様**は、名前・電話番号・メール（必須）と生年月日（任意。月日がカルテ番号になる）を入力して登録。登録済みの電話番号なら、ログインを案内する（他人の名前での予約や、登録の書き換えを防ぐ）。
- 予約のあと、はじめてのお客様には**事前カウンセリング**（お悩みのチェックと自由記入）を案内。入力内容は予約一覧・顧客詳細に表示され、対応記録を作るときに最初から入る。

### お客様向け：かんたんログインと利用履歴（`/my`）

- **かんたんログイン**（電話番号＋生年月日）：登録済みのお客様が予約するときに使う。生年月日が未登録の方は、登録メールに届くコードで本人と確認してから登録できる（`/my/birthday`）。
- **利用履歴**（さらに、登録メールに届く6桁の確認コード）：これからの予約、来店の記録（日付・メニュー・カウンセリング・施術前後の写真）、過去の予約、AI相談の内容を表示。スタッフ向けのメモと使用資材は表示しない。
- 確認コードは10分で無効、5回まちがえると無効、1回使うと無効。履歴を見られるのは確認から30分まで。
- 確認コードは、お店で登録した1つ目のメールアドレスにだけ送る（ログイン中の予約で入力されたアドレスは2つ目に入れ、コードの送り先にしない）。

### AI提案機能（お客様向け、ログイン不要）

- お客様が髪や頭皮の悩みを書くと、AIが質問を重ねながら絞り込み、登録済みメニューから1つを提案。「このメニューで予約に進む」で、そのメニューを選んだ状態の予約画面へ移る。
- 管理画面「AI提案結果」で、提案メニュー・予約の有無・会話内容を確認できる。AI相談のあと同じブラウザで予約すると、その相談が顧客に紐づき、顧客詳細にも表示される。
- 会話は `ai_sessions` / `ai_messages` に保存。1回目は必ず質問、最大4回で必ず提案して終える（`AI_MAX_TURNS`）。
- AIに渡す知識は、メニュー管理の「説明」と、管理画面「AI設定」の「お店の判断基準」。一覧にないメニューは作らず、頭皮の症状は断定せず専門医をすすめる。
- AI API は Gemini（`gemini-3.1-flash-lite`）。複数モデルを実測し、速さ（約1〜2秒）と答えの質で選定。呼び出しは `app/Services/Ai/MenuAdvisor.php` に集約し、他のAPIへ差し替えられる構成。混雑・時間切れは1回だけ再試行（待ち時間は1回8秒まで、`AI_TIMEOUT`）。1日の利用回数に、全体（`AI_DAILY_LIMIT`）とIPアドレスごと（`AI_IP_DAILY_LIMIT`）の上限。
- 相談画面に「外部のAIサービス（Google Gemini）に送信される」ことを表示。
- 点検用コマンド `php artisan ai:eval` で、よくある相談12件（`database/ai-eval-cases.txt`）への提案を一覧にし、判断基準を直すたびに比較できる。

## 技術構成

PHP 8.4 / Laravel 13 / MySQL 8 / Docker、Blade・Tailwind CSS・Alpine.js（Laravel Breeze）、PHPUnit。選定理由は [docs/08_tech_selection.html](docs/08_tech_selection.html) を参照。

## 動かし方（ローカル）

```sh
git clone https://github.com/tgcore-build/suzuki-reservation-system.git
cd suzuki-reservation-system
cp .env.example .env        # DB を MySQL に切り替える（下の「.env の主な設定」を参照）
docker compose up -d --build
composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan db:seed --class=DemoSeeder   # デモ用データ（任意）
npm install && npm run dev  # 別のターミナルで起動したままにする
```

ブラウザで `http://localhost:8000` を開きます。管理画面のログインは `/login`、お客様向けの予約画面は `/book` です。`php artisan migrate --seed` で作られるテスト用ユーザー（`test@example.com`）は、ローカル開発専用です。

### .env の主な設定

| 項目 | 例 | 説明 |
|---|---|---|
| `APP_NAME` | `"サロン名"` | 画面とメールに出る名前 |
| `DB_CONNECTION` ほか | `mysql` / `db` / `suzuki_reservation_db` / `root` / `password` | `docker-compose.yml` の MySQL に接続 |
| `MAIL_MAILER` | `log` | ローカルでは、メールを送らずログに書き出す。本番は SMTP 等に切り替え |
| `PHOTO_DISK` | `public` | 対応記録の写真の保存先。本番は `s3`（要 `league/flysystem-aws-s3-v3`） |
| `GEMINI_API_KEY` | （各自で取得） | AI提案機能の APIキー。Google AI Studio で発行 |
| `GEMINI_MODEL` | `gemini-3.1-flash-lite` | AI提案機能で使うモデル |

### リマインドメール

```sh
docker compose exec app php artisan reminders:send
```

「明日の確定済みの予約で、メールアドレスがあり、まだ送っていないもの」に送信し、送信日時を記録します（二重送信しません）。毎日18:00に自動実行する設定が `routes/console.php` にあります。本番では、スケジューラ（`php artisan schedule:run` を毎分、または `schedule:work`）を動かす必要があります。

## テスト

```sh
docker compose exec app php artisan test
```

認証・アクセス制限、顧客、予約、予約表、対応記録（写真付き）、リマインド、CSV取り込み、オンライン予約（二重予約の拒否を含む）、ダッシュボード、AI提案（AIの応答は偽物に置き換えて確認）を、自動テストで確認しています。

## ドキュメント（docs/）

| No | ファイル | 内容 |
|---|---|---|
| 01 | [requirements_reservation.html](docs/01_requirements_reservation.html) | 要件定義書：予約・顧客管理システム |
| 02 | [requirements_ai.html](docs/02_requirements_ai.html) | 要件定義書：AI提案機能 |
| 03 | [design_reservation.html](docs/03_design_reservation.html) | 設計書：予約・顧客管理システム（画面設計・DB設計・API設計） |
| 04 | [design_ai.html](docs/04_design_ai.html) | 設計書：AI提案機能（画面設計・DB設計・API設計） |
| 05 | [agile_reservation.html](docs/05_agile_reservation.html) | 開発計画：予約・顧客管理システム（スプリント計画・タスク割当・KPI） |
| 06 | [agile_ai.html](docs/06_agile_ai.html) | 開発計画：AI提案機能（スプリント計画・タスク割当・KPI） |
| 07 | [quotation.html](docs/07_quotation.html) | 見積書（練習課題） |
| 08 | [tech_selection.html](docs/08_tech_selection.html) | 技術選定資料 |
| 09 | [task_breakdown.html](docs/09_task_breakdown.html) | タスク分解（GitHub Issues登録用の下書き） |

Figma のワイヤーフレームは [こちら](https://www.figma.com/design/2JolEvuYmxQiFynxv2JfTo/%E7%84%A1%E9%A1%8C?node-id=0-1&t=sqtwBgtZyZ9xfN6Q-1)（「予約システム」8画面／「AI提案機能」5画面）。

## 開発の進め方

タスクは [Issues](../../issues)、進行管理は [Projects](../../projects) のカンバンボードで行っています。

| 週 | 内容 |
|---|---|
| Week22 | 環境構築・管理者ログイン・顧客管理・予約管理の基本CRUD |
| Week23 | 対応記録・自動リマインド・既存ソフト連携（CSV取り込み） |
| Week24 | 顧客向けオンライン予約（空き状況表）・バグ修正・デプロイ |
| フェーズ2（Week24以降） | AI提案機能（着手済み）・顧客向け利用履歴ページ |
