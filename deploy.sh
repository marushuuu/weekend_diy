#!/bin/bash
set -e

# ──────────────────────────────────────────────────────────────────────────────
# Xserver デプロイスクリプト — weekend-diy.com
# ──────────────────────────────────────────────────────────────────────────────
SSH_KEY="~/.ssh/xserver_key"
SSH_USER="xs277376"
SSH_HOST="sv17063.xserver.jp"
SSH_PORT=10022
BRANCH="claude/epic-ritchie-8nKV5"

DEPLOY_DIR="/home/xs277376/weekend_diy_deploy"
WP_ROOT="/home/xs277376/weekend-diy.com/public_html"
PLUGIN_DST="${WP_ROOT}/wp-content/plugins/kogu-rental"
THEME_DST="${WP_ROOT}/wp-content/themes/minna-kogu"

SSH_CMD="ssh -i ${SSH_KEY} -p ${SSH_PORT} ${SSH_USER}@${SSH_HOST}"

echo ""
echo "======================================="
echo "  weekend-diy.com デプロイ"
echo "  branch: ${BRANCH}"
echo "======================================="

# ─── git pull → cp（プラグイン＋テーマ）────────────────────────────────────
echo ""
echo "▶ サーバー上で git pull & コピー中..."
${SSH_CMD} bash <<REMOTE
set -e
cd "${DEPLOY_DIR}"
git fetch origin ${BRANCH}
git checkout ${BRANCH}
git pull origin ${BRANCH}

# プラグインをコピー
cp -r wordpress-plugin/kogu-rental/* "${PLUGIN_DST}/"
echo "  ✅ プラグインコピー完了"

# テーマをコピー（ディレクトリがなければ作成）
mkdir -p "${THEME_DST}"
cp -r wordpress-theme/minna-kogu/* "${THEME_DST}/"
echo "  ✅ テーマコピー完了"

# バージョン確認
echo ""
echo "  プラグインバージョン:"
grep "KOGU_VERSION" "${PLUGIN_DST}/kogu-rental.php"
REMOTE

# ─── SEO記事インポート（--import オプション時のみ）──────────────────────────
if [[ " $* " == *" --import "* ]] || [[ "$1" == "--import" ]]; then
    echo ""
    echo "▶ SEO記事をインポート中..."
    ${SSH_CMD} bash <<'REMOTE'
WP_ROOT="/home/xs277376/weekend-diy.com/public_html"
cd "${WP_ROOT}"

import_article() {
    local slug="$1" title="$2" cat="$3" date="$4"
    local count
    count=$(wp post list --post_name="${slug}" --post_type=post --format=count 2>/dev/null)
    if [ "${count}" = "0" ]; then
        local cat_id
        cat_id=$(wp term get category "${cat}" --by=name --field=term_id 2>/dev/null \
                 || wp term create category "${cat}" --porcelain 2>/dev/null)
        wp post create \
            --post_title="${title}" \
            --post_name="${slug}" \
            --post_status=publish \
            --post_date="${date}" \
            --post_category="${cat_id}" \
            --post_content="<p>※ 記事本文は管理画面で貼り付けてください</p>" \
            --comment_status=closed \
            --porcelain
        echo "  ✅ 作成: ${title}"
    else
        echo "  ⏭  スキップ（既存）: ${slug}"
    fi
}

import_article \
    "impact-driver-toha-denki-drill-chigai" \
    "インパクトドライバーとは？電気ドリルとの違いをわかりやすく解説" \
    "工具の基礎知識" \
    "2026-05-01 09:00:00"

import_article \
    "impact-driver-rental-vs-purchase" \
    "インパクトドライバーはレンタルと購入どちらがお得？使用頻度別に徹底比較" \
    "レンタル活用術" \
    "2026-05-08 09:00:00"

import_article \
    "diy-shelf-beginner-complete-guide" \
    "DIY初心者でも作れる！棚の作り方【完全手順ガイド】" \
    "DIY ハウツー" \
    "2026-05-15 09:00:00"
REMOTE
    echo "  ✅ 記事インポート完了"
    echo ""
    echo "  次のステップ:"
    echo "  管理画面 → 投稿 → 各記事を開いて本文を貼り付ける"
    echo "  docs/articles/ の .md ファイルをHTML変換して貼り付けてください"
fi

# ─── テーマ有効化（--activate-theme オプション時のみ）──────────────────────
if [[ " $* " == *" --activate-theme "* ]] || [[ "$1" == "--activate-theme" ]]; then
    echo ""
    echo "▶ テーマを有効化中..."
    ${SSH_CMD} "cd '${WP_ROOT}' && wp theme activate minna-kogu"
    echo "  ✅ テーマ有効化完了"
fi

echo ""
echo "======================================="
echo "  ✅ デプロイ完了"
echo "  https://weekend-diy.com"
echo ""
echo "  使い方:"
echo "  ./deploy.sh                    # プラグイン＋テーマのみ（通常）"
echo "  ./deploy.sh --import           # ＋SEO記事の初回インポート"
echo "  ./deploy.sh --activate-theme   # ＋テーマ有効化（初回のみ）"
echo "  ./deploy.sh --import --activate-theme  # 全部"
echo "======================================="
