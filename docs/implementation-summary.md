# 工具レンタルサイト 実装まとめ

## プロジェクト概要

WordPress + WooCommerce をベースにした **電動工具レンタルサイト**。  
WooCommerce は会員登録・ログインのみに使用し、チェックアウトは Stripe 直接決済で実装。

---

## ディレクトリ構成

```
weekend_diy/
├── docker/                        # 開発環境 (Docker Compose)
├── wordpress-plugin/
│   └── kogu-rental/
│       ├── kogu-rental.php        # プラグインエントリーポイント
│       ├── includes/
│       │   ├── class-database.php        # テーブル定義・マイグレーション
│       │   ├── class-rental-manager.php  # レンタル業務ロジック
│       │   ├── class-inventory.php       # 在庫管理
│       │   ├── class-stripe-handler.php  # Stripe連携
│       │   ├── class-email-handler.php   # メール送信
│       │   └── class-cron.php            # 定期処理 (WP Cron)
│       ├── admin/
│       │   └── class-admin.php           # 管理画面
│       ├── public/
│       │   └── class-public.php          # フロントエンド (AJAX, ショートコード)
│       ├── templates/
│       │   └── rental-form.php           # 予約フォームテンプレート
│       └── assets/
│           ├── js/kogu-rental.js         # フロントエンドJS (Stripe.js連携)
│           └── css/kogu-rental.css       # スタイル
└── docs/
    └── implementation-summary.md  # 本ファイル
```

---

## データベース設計

### `wp_kogu_products`（商品）
| カラム | 型 | 説明 |
|---|---|---|
| id | BIGINT | 主キー |
| name | VARCHAR(200) | 商品名 |
| description | TEXT | 説明 |
| price_per_week | INT | 1週間あたりのレンタル料金（円） |
| deposit_amount | INT | デポジット金額（円） |
| status | ENUM | `active` / `inactive` |

初期データ: インパクトドライバー（4,900円/週、デポジット10,000円）

### `wp_kogu_inventory`（在庫ユニット）
| カラム | 型 | 説明 |
|---|---|---|
| id | BIGINT | 主キー |
| product_id | BIGINT | 商品ID（外部キー） |
| unit_number | TINYINT | 商品内の通し番号 |
| serial_number | VARCHAR | シリアル番号 |
| condition | ENUM | `excellent` / `good` / `fair` / `damaged` |
| status | ENUM | `available` / `rented` / `maintenance` / `retired` |

初期データ: インパクトドライバー 5台

### `wp_kogu_rentals`（レンタル注文）
| カラム | 型 | 説明 |
|---|---|---|
| product_id | BIGINT | 商品ID |
| user_id | BIGINT | WooCommerceユーザーID（ゲストはNULL） |
| guest_name / email / phone | VARCHAR | ゲスト情報 |
| guest_postal_code / address | VARCHAR / TEXT | 住所 |
| inventory_unit_id | BIGINT | 割り当てられた在庫ユニット |
| rental_start_date | DATE | 開始日 |
| rental_end_date | DATE | 終了日（start + weeks×7 − 1日） |
| rental_weeks | SMALLINT | 予約週数 |
| rental_fee | INT | レンタル料金（円） |
| deposit_amount | INT | デポジット（円） |
| late_fee_days / total | SMALLINT / INT | 延滞日数・延滞料金 |
| damage_fee | INT | 損害費用（円） |
| total_charged | INT | Stripeに請求した合計額 |
| deposit_refunded | INT | 実際に返金した金額 |
| tracking_outbound / return | VARCHAR | ゆうパック追跡番号 |
| stripe_payment_intent_id | VARCHAR | Stripe PaymentIntent ID |
| stripe_customer_id | VARCHAR | Stripe Customer ID |
| stripe_payment_method_id | VARCHAR | Stripe PaymentMethod ID（オフセッション用） |
| **refund_scheduled_date** | DATE | **自動返金予定日（NULLは未スケジュール）** |
| **refund_hold** | TINYINT(1) | **1=自動返金を手動停止中** |
| reminder_sent | TINYINT(1) | 返却期限リマインダー送信済みフラグ |
| overdue_notified | TINYINT(1) | 延滞通知送信済みフラグ |
| status | ENUM | 後述のステータスフロー参照 |
| wc_order_id | BIGINT | WooCommerce注文ID（任意） |

### `wp_kogu_return_evidence`（返却証跡）
| カラム | 型 | 説明 |
|---|---|---|
| rental_id | BIGINT | レンタルID |
| tracking_number | VARCHAR | ゆうパック追跡番号 |
| submitted_date | DATE | 提出日（延滞判定の基準日） |
| status | ENUM | `pending` / `verified_on_time` / `verified_late` / `rejected` |

### `wp_kogu_late_fees`（延滞料金明細）
| カラム | 型 | 説明 |
|---|---|---|
| rental_id | BIGINT | レンタルID |
| fee_date | DATE | 延滞発生日 |
| amount | INT | 金額（500円/日固定） |
| status | ENUM | `pending` / `deducted_from_deposit` / `charged_separately` / `failed` |

---

## レンタルステータスフロー

```
pending
  └─ (決済確定) → confirmed
       └─ (管理者が発送) → shipped_to_customer
            ├─ (顧客が返却証跡を提出) → return_evidence_submitted
            │        └─ (5日後 Cron または管理者確認) → returned
            └─ (期限超過) → overdue
                 └─ (返却証跡を提出) → return_evidence_submitted
                      └─ (処理完了) → returned
```

- `active`: shipped 後の別呼称（現在は shipped_to_customer に統一）
- `cancelled`: キャンセル（Stripe 返金済み）

---

## 料金体系

### レンタル料金
**1週目**: `price_per_week`（フル料金）  
**2週目以降**: `price_per_week × 0.70`（30% OFF）

```
例: price_per_week = 4,900円
  1週: 4,900円
  2週: 4,900 + 3,430 = 8,330円
  3週: 4,900 + 3,430 × 2 = 11,760円
```

PHP実装 (`class-rental-manager.php`):
```php
const WEEK_DISCOUNT_RATE = 0.70;

public static function calc_rental_fee(int $weeks, int $price_per_week): int {
    $discounted_week = (int) round($price_per_week * self::WEEK_DISCOUNT_RATE);
    return $price_per_week + ($weeks - 1) * $discounted_week;
}
```

### 延滞料金
**500円/日**（`LATE_FEE_PER_DAY` 定数）  
返却期限翌日から実際の返却日まで日次で積算。

---

## Stripe 連携

### 設定要件（Stripe ダッシュボード）
- **決済方法**: Card（クレジット/デビット）を有効化
- **Webhook**: `/wp-json/kogu/v1/stripe-webhook` エンドポイントに以下のイベントを設定
  - `payment_intent.succeeded`
  - `payment_intent.payment_failed`
  - `charge.refunded`
- **オフセッション課金**: デポジット超過分の追加請求に使用（`setup_future_usage: off_session`）

### 決済フロー

```
① PaymentIntent 作成
   amount = rental_fee + deposit_amount
   setup_future_usage = 'off_session'  ← カードを保存して後から課金可能に

② 顧客がカード情報入力・決済完了
   → PaymentMethod が Customer にアタッチされる

③ 返却後の精算
   refund = deposit - late_fee - damage_fee
   
   refund > 0  → Refund::create() でデポジット返金
   extra > 0   → PaymentIntent::create() でオフセッション追加課金
```

### 自動返金スケジュール

| トリガー | 返金実行タイミング |
|---|---|
| 管理者が返却確認 | 確認から **3日後** |
| 顧客が返却証跡を提出（未確認） | 提出から **5日後** |

管理者は自動返金を**手動で停止/再開**できる（`refund_hold` フラグ）。

---

## 定期処理（WP Cron）

毎朝9:00に `kogu_daily_tasks` フックで実行。

| 処理 | 内容 |
|---|---|
| `send_return_reminders()` | 返却期限1日前のリマインダーメール送信 |
| `detect_overdue()` | 期限超過を検出してステータスを `overdue` に更新・延滞通知メール送信 |
| `accrue_late_fees()` | 延滞中の注文に延滞料金を日次追加 |
| `process_scheduled_refunds()` | `refund_scheduled_date` が今日以前かつ `refund_hold = 0` の注文を返金処理 |

---

## メール送信

SendGrid API（`KOGU_SENDGRID_API_KEY` 環境変数）を使用し、未設定時は `wp_mail()` にフォールバック。

| メソッド | 送信タイミング | 宛先 |
|---|---|---|
| `send_booking_confirmation()` | 予約確定時 | 顧客 |
| `send_admin_new_booking()` | 予約確定時 | 管理者 |
| `send_shipped_notification()` | 発送時 | 顧客 |
| `send_return_reminder()` | 返却期限1日前 | 顧客 |
| `send_overdue_notification()` | 延滞検出時 | 顧客 |
| `send_return_received_notification()` | 返却証跡提出時 | 顧客 |
| `send_admin_return_submitted()` | 返却証跡提出時 | 管理者 |
| `send_return_confirmed()` | 管理者返却確認時 | 顧客（返金予定日・明細を通知） |
| `send_return_complete()` | 返金実行時 | 顧客（実際の返金額・控除明細） |

---

## フロントエンド（予約フォーム）

`[kogu_rental_form]` ショートコードで表示。3ステップ構成。

**Step 1: 商品・期間選択**
- 商品カードを選択（複数商品対応）
- 開始日を入力（`<input type="date">`）
- 週数を選択（1〜8週、`<select>`）
- リアルタイムで料金計算・在庫確認（AJAX）
- 価格内訳を表示（1週目 ¥X ＋ 2週目以降 ¥Y/週×N週）

**Step 2: 顧客情報入力**
- 氏名・メール・電話・郵便番号・住所

**Step 3: カード情報入力・決済**
- Stripe.js の CardElement でカード情報入力
- PaymentIntent を確定して予約完了

---

## 管理画面

WordPress 管理画面の「工具レンタル」メニュー配下。

| サブメニュー | 機能 |
|---|---|
| **ダッシュボード** | ステータス別件数・最近の予約 |
| **予約一覧** | ステータスフィルター・予約詳細 |
| **予約詳細** | 発送処理・返却確認・損害費用入力・**返金スケジュール表示・停止/再開ボタン** |
| **在庫管理** | 商品フィルター・ユニット状態管理 |
| **商品管理** | 商品CRUD・価格プレビュー（週数別料金一覧） |
| **設定** | Stripe APIキー・SendGrid APIキー・管理者メール |

### 返金スケジュール UI（予約詳細画面）

```
┌─────────────────────────────────────────────────────┐
│ 💳 返金スケジュール                                  │
│ 自動返金予定日: 2026-05-12                           │
│ [⏸ 自動返金を停止する]                              │
└─────────────────────────────────────────────────────┘

↓ 停止後

┌─────────────────────────────────────────────────────┐
│ ⏸ 自動返金 停止中                                   │
│ 管理者によって自動返金が一時停止されています。        │
│ [▶ 自動返金を再開する]                              │
└─────────────────────────────────────────────────────┘
```

---

## 実装コミット履歴

| コミット | 内容 |
|---|---|
| `1eaa23a` | WordPressプラグイン「工具レンタル」を追加 |
| `6f80729` | Astroプロジェクト・ワイヤーフレームを追加 |
| `cbee90f` | 管理者通知メール・在庫管理UI・バリデーションを追加 |
| `e13a956` | Docker開発環境を追加 |
| `3d667e8` | 商品ID対応・週単位料金体系を実装 |
| `5fd2f31` | デポジット自動返金・手動停止/再開機能を実装 |

---

## 今後の検討事項

- [ ] Stripe Webhook の署名検証テスト（本番環境）
- [ ] 複数商品追加時の管理画面UX改善
- [ ] キャンセルポリシーの実装（開始日前のキャンセル返金率）
- [ ] レンタル延長申請フロー
- [ ] 在庫メンテナンスモードの予約ブロック
- [ ] CSVエクスポート機能（売上・在庫レポート）
- [ ] SMS通知（返却期限リマインダー等）
