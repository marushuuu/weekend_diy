#!/bin/bash
# サーバー上で実行するスクリプト
# 場所: /home/xs277376/weekend_diy_deploy/docs/articles/html/
# 実行: bash update-posts.sh

WP_ROOT="/home/xs277376/weekend-diy.com/public_html"
HTML_DIR="$(cd "$(dirname "$0")" && pwd)"

update_post() {
    local slug="$1"
    local html_file="$2"

    local post_id
    post_id=$(wp --path="$WP_ROOT" post list \
        --post_name="$slug" \
        --post_type=post \
        --field=ID \
        --format=ids 2>/dev/null)

    if [ -z "$post_id" ]; then
        echo "  ⚠️  スキップ: $slug が見つかりません"
        return
    fi

    wp --path="$WP_ROOT" post update "$post_id" \
        --post_content="$(cat "$html_file")" \
        --quiet

    echo "  ✅ 更新: $slug (ID: $post_id)"
}

echo ""
echo "=== SEO記事 本文更新 ==="
echo ""

update_post \
    "impact-driver-toha-denki-drill-chigai" \
    "${HTML_DIR}/01_impact-driver-toha-denki-drill-chigai.html"

update_post \
    "impact-driver-rental-vs-purchase" \
    "${HTML_DIR}/02_impact-driver-rental-vs-purchase.html"

update_post \
    "diy-shelf-beginner-complete-guide" \
    "${HTML_DIR}/03_diy-shelf-beginner-complete-guide.html"

echo ""
echo "=== 完了 ==="
