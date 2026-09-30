


Readme · MD
予約・顧客管理システム＋AI提案機能
予約制サービス業向けの予約・顧客管理システムに、AIによるメニュー提案機能を組み合わせた総合プロジェクト。techmeetsカリキュラム Week18〜21（Month5：PM的スキル習得）の成果物であり、Week22〜24で実際に開発する。

プロジェクト概要
対応記録（提供内容・使用資材・対応時間・メモ・前後の写真）が一元管理できていない、来店リマインドが手動・未実施、といった現状の課題を解決するための予約・顧客管理システムを開発する。
追加機能として、お客様がAIチャットに悩みや要望を伝えると、おすすめのサービスメニューを提案してくれるAI提案機能を実装する。
開発体制：開発者本人による個人開発（techmeets Week22〜24 総合演習）
開発期間：3週間（Week22〜24）
技術選定
PHP / Laravel / MySQL / Docker（バックエンド）、Blade / Tailwind CSS / Alpine.js（フロントエンド）、外部AI API（AI提案機能）、Render または Railway（デプロイ先）。選定理由の詳細は docs/08_tech_selection.html を参照。

Figmaワイヤーフレーム
Figmaデザインを見る

「予約システム」ページ：予約・顧客管理システムの8画面
「AI提案機能」ページ：AI提案機能の5画面
ドキュメント一覧（docs/）
No	ファイル	内容
01	requirements_reservation.html	要件定義書：予約・顧客管理システム
02	requirements_ai.html	要件定義書：AI提案機能
03	design_reservation.html	設計書：予約・顧客管理システム（画面設計・DB設計・API設計）
04	design_ai.html	設計書：AI提案機能（画面設計・DB設計・API設計）
05	agile_reservation.html	開発計画：予約・顧客管理システム（スプリント計画・タスク割当・KPI）
06	agile_ai.html	開発計画：AI提案機能（スプリント計画・タスク割当・KPI）
07	quotation.html	見積書（練習課題）
08	tech_selection.html	技術選定資料
09	task_breakdown.html	タスク分解（GitHub Issues登録用の下書き）
タスク管理
開発タスクは Issues に登録し、進行管理は Projects のカンバンボードで行う。タスクの内訳は docs/09_task_breakdown.html を参照。

開発スケジュール
週	内容
Week22	環境構築・管理者ログイン・顧客管理・予約管理の基本CRUD
Week23	対応記録・自動リマインド・既存ソフト連携
Week24	顧客向けオンライン予約（空き状況カレンダー優先）・AI提案機能・デプロイ

