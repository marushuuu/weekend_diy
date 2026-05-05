# 工具レンタルプラグイン セットアップ手順

## 1. 前提条件

- WordPress 6.0以上
- WooCommerce 8.0以上
- PHP 8.1以上
- SSL証明書（Stripe決済に必須）

## 2. プラグインのインストール

1. `kogu-rental/` フォルダを `wp-content/plugins/` にアップロード
2. Stripeライブラリをインストール:
   ```bash
   cd wp-content/plugins/kogu-rental/
   composer require stripe/stripe-php
   ```
3. WordPress管理画面「プラグイン」→「工具レンタル」を有効化
4. 有効化時に自動でデータベーステーブルと初期在庫5台が作成されます

## 3. 設定（WordPress管理画面 → レンタル管理 → 設定）

| 設定項目 | 内容 | 取得先 |
|---|---|---|
| Stripe 公開鍵 | `pk_live_...` | Stripe Dashboard → API Keys |
| Stripe 秘密鍵 | `sk_live_...` | Stripe Dashboard → API Keys |
| Stripe Webhook シークレット | `whsec_...` | 下記参照 |
| SendGrid APIキー | `SG.xxx` | SendGrid → Settings → API Keys |
| 送信元メールアドレス | 例: rental@your-domain.com | SendGrid認証済みアドレス |
| 送信元名 | 例: みんなの工具レンタル | 任意 |
| 1日あたりレンタル料金 | 例: 1000（円） | 任意 |
| デポジット額 | 例: 10000（円） | 任意 |

## 4. Stripe Webhook の設定

1. Stripe Dashboard → Developers → Webhooks → 「Add endpoint」
2. URL: `https://your-domain.com/wp-json/kogu/v1/stripe-webhook`
3. Events: `payment_intent.succeeded` を選択
4. 作成後に表示される `whsec_...` をプラグイン設定に入力

## 5. WordPress ページの作成

以下のページを作成し、ショートコードを貼り付けます：

| ページ名 | スラッグ | ショートコード |
|---|---|---|
| レンタル申込 | `/rental` | `[kogu_rental_form]` |
| マイページ | `/my-page` | `[kogu_mypage]` |

## 6. 法的ページの作成

`legal/` フォルダ内のMarkdownをWordPressページとして作成してください：

| ファイル | ページスラッグ |
|---|---|
| terms-of-service.md | `/terms` |
| tokushoho.md | `/tokushoho` |

プライバシーポリシーは WordPress管理画面「設定 → プライバシー」で作成できます。

## 7. WooCommerce の設定

WooCommerceのチェックアウトは本プラグインでは使用しません（独自フォーム）。
ただし会員機能（ログイン・登録）のためWooCommerceを有効化してください。

`WooCommerce → 設定 → アカウントとプライバシー` で以下を有効化：
- ✅ ゲストのチェックアウトを許可する
- ✅ アカウントの作成を許可する

## 8. メール送信テスト

設定完了後、管理画面から以下を確認：
1. テストユーザーで会員登録 → 登録完了メール受信確認
2. テスト予約 → 予約確認メール受信確認
3. Stripe テストモードでの決済動作確認

## 9. 本番運用前チェックリスト

- [ ] Stripe を本番モード（`pk_live_` / `sk_live_`）に切り替え
- [ ] SendGrid の送信ドメイン認証（SPF/DKIM）を設定
- [ ] SSL証明書の有効期限を確認
- [ ] 利用規約・プライバシーポリシー・特定商取引法ページを公開
- [ ] ゆうパック着払い用の伝票を準備
- [ ] 管理者への注文通知メールを WooCommerce → メール で設定

## データベーステーブル

| テーブル名 | 内容 |
|---|---|
| `wp_kogu_rentals` | レンタル注文（メインテーブル） |
| `wp_kogu_inventory` | 在庫ユニット（1〜5台） |
| `wp_kogu_return_evidence` | 返却証跡（ゆうパック追跡番号） |
| `wp_kogu_late_fees` | 延滞料金明細 |

## 定期実行タスク

WordPress Cron（毎朝9:00）が以下を自動実行します：
- 返却期限1日前のリマインダーメール
- 延滞検出・ステータス更新
- 毎日の延滞料金積算
