# みんなの工具レンタル — Claude Code セッション引継ぎ

## プロジェクト概要

- **サービス名**: みんなの工具レンタル
- **URL**: https://weekend-diy.com
- **サーバー**: Xserver（SSH: xs277376@weekend-diy.com）
- **WordPressパス**: `/home/xs277376/weekend-diy.com/public_html`
- **デプロイリポジトリ**: `/home/xs277376/weekend_diy_deploy/`
- **WP-CLI**: `wp --path=/home/xs277376/weekend-diy.com/public_html`

## ブランチ運用ルール（必須）

- **開発・プッシュ先**: `work/task-6fSYE` ブランチのみ
- `claude/*` ブランチには絶対にプッシュしない

## デプロイ手順

```bash
# サーバーSSH後
cd ~/weekend_diy_deploy && git pull origin work/task-6fSYE

# テーマ
rsync -a wordpress-theme/minna-kogu/ ~/weekend-diy.com/public_html/wp-content/themes/minna-kogu/

# プラグイン
rsync -a --exclude='assets/images/' wordpress-plugin/kogu-rental/ ~/weekend-diy.com/public_html/wp-content/plugins/kogu-rental/
```

## 構成ファイル

| ファイル | 役割 |
|---------|------|
| `wordpress-theme/minna-kogu/functions.php` | SEO・canonical・OGP・メンテナンスモード |
| `wordpress-theme/minna-kogu/style.css` | テーマCSS（Version番号でキャッシュバスト） |
| `wordpress-theme/minna-kogu/front-page.php` | トップページ |
| `wordpress-theme/minna-kogu/single.php` | ブログ個別記事 |
| `wordpress-plugin/kogu-rental/admin/class-admin.php` | 管理画面・設定 |
| `wordpress-plugin/kogu-rental/public/class-public.php` | フロント処理・noindex |
| `wordpress-plugin/kogu-rental/includes/class-email-handler.php` | メール送信 |
| `wordpress-plugin/kogu-rental/legal/terms.html` | 利用規約（WP固定ページへ貼り付け） |
| `wordpress-plugin/kogu-rental/legal/privacy.html` | プライバシーポリシー |
| `wordpress-plugin/kogu-rental/legal/tokushoho.html` | 特定商取引法に基づく表記 |

## WordPressオプション（重要）

| オプション名 | 内容 |
|-------------|------|
| `kogu_maintenance_mode` | 1=メンテナンス中(503) / 0=公開 |
| `kogu_noindex_slugs` | noindex対象スラッグ（カンマ区切り）デフォルト: `tokushoho,my-page,privacy,terms,contact` |
| `kogu_gsc_verification` | Google Search Console 確認タグ |
| `kogu_gtm_container_id` | Google Tag Manager コンテナID |
| `kogu_service_name` | サービス名（返送先ラベル用）= みんなの工具レンタル |

## インストール済みプラグイン

| プラグイン | 状態 | 備考 |
|-----------|------|------|
| kogu-rental（自作） | 有効 | 決済・在庫・メール管理 |
| WooCommerce | 有効 | ホームページ=ショップページとして設定 |
| CloudSecure WP Security | 有効 | ログイン通知はOFFを推奨 |
| WP Mail SMTP | 有効 | Resend経由で全メール送信 |
| Jetpack プロテクト | 有効 | |
| WP 2FA | 有効 | |
| WPForms Lite | 有効 | |
| VK All in One Expansion Unit | **無効化済み** | canonicalタグ二重出力のため無効化 |

## SEO構造（functions.php）

`minna_kogu_seo_head()` がcanonical・OGP・descriptionを出力。
条件分岐は以下の順で評価：

1. `is_front_page()` → canonical = `/`
2. `is_page('rental')` → canonical = `/rental/`
3. `is_page()` → canonical = `get_permalink()`
4. `is_singular()` → canonical = `get_permalink()` ← **ブログ記事用（重要）**
5. `is_home() || is_archive()` → canonical = `get_pagenum_link()`
6. else → canonical = `get_pagenum_link()`

**VK ExUnit を無効化したことで canonical の二重出力問題は解消済み。**

## Google Search Console 状況（2026-07-26時点）

- インデックス済み: 4ページ
- 未インデックス: 32ページ
- 主な原因: **全ブログ記事のcanonicalがホームページURLになっていた**（修正済み）
- プロパティ種別: URLプレフィックス（`https://weekend-diy.com/`） → ドメインプロパティへの移行を推奨

## 残タスク

### 高優先度
- [ ] **34記事を一括公開**（サーバーで実行）
  ```bash
  wp --path=/home/xs277376/weekend-diy.com/public_html post update \
    $(wp --path=/home/xs277376/weekend-diy.com/public_html post list \
      --post_status=draft --post_type=post --format=ids) \
    --post_status=publish
  ```
- [ ] **Search Consoleにサイトマップ送信**
  - URL: `https://weekend-diy.com/wp-sitemap.xml`
- [ ] **CloudSecure WP Security のログイン通知をOFF**
  - 管理画面 → CloudSecure → ログイン通知 → 無効化

### 中優先度
- [ ] **Search ConsoleをDomainプロパティに移行**
  - Xserver DNSにTXTレコードを追加して確認
- [ ] **ファビコン設定**
  - 外観 → カスタマイズ → サイトアイコン で設定
- [ ] **ブログ記事の内容精査**
  - 存在しない商品（アングルインパクト・チェーンソー等）の記事を修正
  - 誤った価格表記（¥700〜）の修正

### 低優先度
- [ ] `billing_note` カラムの存在確認（本番DB）
  ```bash
  wp --path=/home/xs277376/weekend-diy.com/public_html eval 'Kogu_Database::install();'
  ```

## 料金体系（正確な情報）

| 項目 | 金額 |
|------|------|
| インパクトドライバー 1週間 | ¥4,900 |
| 2週目以降 | 30%OFF |
| 送料（¥3,000以上） | 無料（北海道・沖縄・離島除く） |
| 送料（¥3,000未満） | ¥2,500 |
| 延滞料金 | 週単価 ÷ 7（小数点切り捨て）/日 |
| 損害上限 | ¥30,000 |

## CSS変更時の注意

テーマCSS変更後は `style.css` の `Version:` を上げる（例: 1.0.8 → 1.0.9）。
WooCommerceの `.woocommerce img { height: auto }` が高い詳細度を持つため、
画像サイズ指定には `!important` が必要な場合がある。

## メール設定

- 送信: WP Mail SMTP → Resend API経由
- 管理者宛メール（CloudSecure通知等）もResend経由になっている
- CloudSecureのログイン通知を無効化することで大半は解消
