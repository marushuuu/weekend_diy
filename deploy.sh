#!/bin/bash
set -e

# ──────────────────────────────────────────────────────────────────────────────
# デプロイ設定 — ここだけ書き換えてください
# ──────────────────────────────────────────────────────────────────────────────
SSH_USER="your_user"                          # XServerのアカウント名
SSH_HOST="sv****.xserver.jp"                  # XServerのホスト名
SSH_PORT=10022                                # XServerのSSHポート（通常10022）
WP_ROOT="/home/${SSH_USER}/ドメイン名/public_html"   # WordPress設置パス
SSH_KEY="~/.ssh/id_rsa"                       # 秘密鍵パス（不要なら削除）
# ──────────────────────────────────────────────────────────────────────────────

SSH_OPTS="-p ${SSH_PORT} -i ${SSH_KEY} -o StrictHostKeyChecking=no"
SSH_CMD="ssh ${SSH_OPTS} ${SSH_USER}@${SSH_HOST}"
RSYNC_OPTS="-avz --delete -e \"ssh ${SSH_OPTS}\""

PLUGIN_SRC="wordpress-plugin/kogu-rental/"
PLUGIN_DST="${WP_ROOT}/wp-content/plugins/kogu-rental/"
THEME_SRC="wordpress-theme/minna-kogu/"
THEME_DST="${WP_ROOT}/wp-content/themes/minna-kogu/"

echo ""
echo "======================================="
echo "  みんなの工具レンタル デプロイ開始"
echo "  対象: ${SSH_USER}@${SSH_HOST}"
echo "======================================="

# ─── 1. プラグインを転送 ──────────────────────────────────────────────────────
if [ "${1}" != "--theme-only" ]; then
    echo ""
    echo "▶ プラグインを転送中..."
    rsync -avz --delete \
        -e "ssh ${SSH_OPTS}" \
        --exclude="vendor/" \
        --exclude="*.phar" \
        --exclude="import-articles.php" \
        "${PLUGIN_SRC}" \
        "${SSH_USER}@${SSH_HOST}:${PLUGIN_DST}"
    echo "  ✅ プラグイン転送完了"
fi

# ─── 2. テーマを転送 ──────────────────────────────────────────────────────────
echo ""
echo "▶ テーマを転送中..."
rsync -avz --delete \
    -e "ssh ${SSH_OPTS}" \
    "${THEME_SRC}" \
    "${SSH_USER}@${SSH_HOST}:${THEME_DST}"
echo "  ✅ テーマ転送完了"

# ─── 3. SEO記事をインポート（初回のみ）──────────────────────────────────────
if [ "${1}" = "--import" ] || [ "${2}" = "--import" ]; then
    echo ""
    echo "▶ SEO記事をインポート中..."
    ${SSH_CMD} bash <<REMOTE
cd "${WP_ROOT}"

# 記事1: インパクトドライバーとは？
wp post list --post_name=impact-driver-toha-denki-drill-chigai --post_type=post --format=count 2>/dev/null | grep -q "^0$" && \
wp post create \
    --post_title="インパクトドライバーとは？電気ドリルとの違いをわかりやすく解説" \
    --post_name="impact-driver-toha-denki-drill-chigai" \
    --post_status=publish \
    --post_date="2026-05-01 09:00:00" \
    --post_category=\$(wp term create category "工具の基礎知識" --porcelain 2>/dev/null || wp term get category "工具の基礎知識" --by=name --field=term_id 2>/dev/null) \
    --post_content="<p>記事本文はWordPress管理画面で貼り付けてください</p>" \
    --comment_status=closed \
    && echo "  ✅ 記事1 作成" || echo "  ⏭  記事1 スキップ（既存）"

# 記事2: レンタルvs購入
wp post list --post_name=impact-driver-rental-vs-purchase --post_type=post --format=count 2>/dev/null | grep -q "^0$" && \
wp post create \
    --post_title="インパクトドライバーはレンタルと購入どちらがお得？使用頻度別に徹底比較" \
    --post_name="impact-driver-rental-vs-purchase" \
    --post_status=publish \
    --post_date="2026-05-08 09:00:00" \
    --post_category=\$(wp term create category "レンタル活用術" --porcelain 2>/dev/null || wp term get category "レンタル活用術" --by=name --field=term_id 2>/dev/null) \
    --post_content="<p>記事本文はWordPress管理画面で貼り付けてください</p>" \
    --comment_status=closed \
    && echo "  ✅ 記事2 作成" || echo "  ⏭  記事2 スキップ（既存）"

# 記事3: 棚の作り方
wp post list --post_name=diy-shelf-beginner-complete-guide --post_type=post --format=count 2>/dev/null | grep -q "^0$" && \
wp post create \
    --post_title="DIY初心者でも作れる！棚の作り方【完全手順ガイド】" \
    --post_name="diy-shelf-beginner-complete-guide" \
    --post_status=publish \
    --post_date="2026-05-15 09:00:00" \
    --post_category=\$(wp term create category "DIY ハウツー" --porcelain 2>/dev/null || wp term get category "DIY ハウツー" --by=name --field=term_id 2>/dev/null) \
    --post_content="<p>記事本文はWordPress管理画面で貼り付けてください</p>" \
    --comment_status=closed \
    && echo "  ✅ 記事3 作成" || echo "  ⏭  記事3 スキップ（既存）"
REMOTE
    echo "  ✅ 記事インポート完了"
fi

# ─── 4. テーマを有効化（初回のみ）──────────────────────────────────────────
if [ "${1}" = "--activate-theme" ] || [ "${2}" = "--activate-theme" ]; then
    echo ""
    echo "▶ テーマを有効化中..."
    ${SSH_CMD} "cd '${WP_ROOT}' && wp theme activate minna-kogu"
    echo "  ✅ テーマ有効化完了"
fi

echo ""
echo "======================================="
echo "  ✅ デプロイ完了"
echo ""
echo "  使用例:"
echo "  ./deploy.sh                    # プラグイン＋テーマのみ"
echo "  ./deploy.sh --import           # ＋記事インポート（初回のみ）"
echo "  ./deploy.sh --activate-theme   # ＋テーマ有効化"
echo "  ./deploy.sh --theme-only       # テーマのみ"
echo "======================================="
