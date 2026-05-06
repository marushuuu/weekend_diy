#!/bin/bash
set -e

echo "=== 工具レンタルサイト ローカル環境セットアップ ==="

# 1. コンテナ起動
echo ""
echo "▶ Dockerコンテナを起動します..."
docker compose up -d

# WordPressの起動を待つ
echo "▶ WordPressの起動を待っています..."
sleep 15

# 2. WordPress初期設定
echo ""
echo "▶ WordPressを初期設定します..."
docker compose run --rm wpcli wp core install \
  --url="http://localhost:8080" \
  --title="みんなの工具レンタル" \
  --admin_user="admin" \
  --admin_password="admin1234" \
  --admin_email="admin@example.com" \
  --skip-email

# 3. WooCommerceインストール
echo ""
echo "▶ WooCommerceをインストールします..."
docker compose run --rm wpcli wp plugin install woocommerce --activate

# 4. WooCommerce初期設定
docker compose run --rm wpcli wp option update woocommerce_store_address "東京都渋谷区"
docker compose run --rm wpcli wp option update woocommerce_default_country "JP"
docker compose run --rm wpcli wp option update woocommerce_currency "JPY"

# ゲストチェックアウト・会員登録を許可
docker compose run --rm wpcli wp option update woocommerce_enable_guest_checkout "yes"
docker compose run --rm wpcli wp option update woocommerce_enable_myaccount_registration "yes"

# 5. composerでStripeライブラリをインストール
echo ""
echo "▶ Stripeライブラリをインストールします..."
docker compose exec wordpress bash -c "
  cd /var/www/html/wp-content/plugins/kogu-rental && \
  curl -sS https://getcomposer.org/installer | php && \
  php composer.phar install --no-dev 2>&1 | tail -5
"

# 6. 工具レンタルプラグインを有効化
echo ""
echo "▶ 工具レンタルプラグインを有効化します..."
docker compose run --rm wpcli wp plugin activate kogu-rental

# 7. 必要なページを自動作成
echo ""
echo "▶ 必要なページを作成します..."

docker compose run --rm wpcli wp post create \
  --post_type=page \
  --post_status=publish \
  --post_title="レンタル申込" \
  --post_name="rental" \
  --post_content="[kogu_rental_form]"

docker compose run --rm wpcli wp post create \
  --post_type=page \
  --post_status=publish \
  --post_title="マイページ" \
  --post_name="my-page" \
  --post_content="[kogu_mypage]"

docker compose run --rm wpcli wp post create \
  --post_type=page \
  --post_status=publish \
  --post_title="利用規約" \
  --post_name="terms" \
  --post_content="$(cat wordpress-plugin/kogu-rental/legal/terms-of-service.md)"

docker compose run --rm wpcli wp post create \
  --post_type=page \
  --post_status=publish \
  --post_title="特定商取引法に基づく表示" \
  --post_name="tokushoho" \
  --post_content="$(cat wordpress-plugin/kogu-rental/legal/tokushoho.md)"

# 8. プラグイン設定（ダミー値）
echo ""
echo "▶ プラグイン設定を初期化します..."
docker compose run --rm wpcli wp option update kogu_price_per_day "1000"
docker compose run --rm wpcli wp option update kogu_deposit_amount "10000"
docker compose run --rm wpcli wp option update kogu_from_email "admin@example.com"
docker compose run --rm wpcli wp option update kogu_from_name "みんなの工具レンタル"

echo ""
echo "======================================"
echo "✅ セットアップ完了！"
echo ""
echo "  WordPress管理画面: http://localhost:8080/wp-admin"
echo "  ユーザー名: admin"
echo "  パスワード: admin1234"
echo ""
echo "  レンタル申込ページ: http://localhost:8080/rental"
echo "  マイページ:         http://localhost:8080/my-page"
echo "  phpMyAdmin:        http://localhost:8081"
echo ""
echo "  次のステップ:"
echo "  1. http://localhost:8080/wp-admin/admin.php?page=kogu-settings"
echo "     でStripe・SendGridのAPIキーを設定"
echo "======================================"
