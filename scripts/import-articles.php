<?php
/**
 * WP-CLI経由で実行するブログ記事インポートスクリプト
 * 実行方法:
 *   wp --path=/home/xs277376/weekend-diy.com/public_html eval-file /tmp/import-articles.php
 */

// カテゴリを作成または取得
function get_or_create_category( $name ) {
    $existing = get_term_by( 'name', $name, 'category' );
    if ( $existing ) return $existing->term_id;
    $result = wp_insert_term( $name, 'category' );
    return is_wp_error( $result ) ? 1 : $result['term_id'];
}

// Markdown → HTML 変換（基本要素のみ）
function md2html( $md ) {
    // フロントマター除去
    $md = preg_replace( '/^\*\*公開日\*\*:.*$/m', '', $md );
    $md = preg_replace( '/^\*\*カテゴリ\*\*:.*$/m', '', $md );
    $md = preg_replace( '/^\*\*スラッグ\*\*:.*$/m', '', $md );

    // 水平線
    $md = preg_replace( '/^---$/m', '<hr>', $md );

    // 見出し
    $md = preg_replace( '/^### (.+)$/m', '<h3>$1</h3>', $md );
    $md = preg_replace( '/^## (.+)$/m',  '<h2>$1</h2>', $md );
    $md = preg_replace( '/^# (.+)$/m',   '',             $md ); // h1はタイトルなので除去

    // テーブル変換
    $md = preg_replace_callback(
        '/(\|.+\|\n)+/',
        function( $matches ) {
            $rows = array_filter( explode( "\n", trim( $matches[0] ) ) );
            $html = '<table><tbody>';
            $first = true;
            foreach ( $rows as $row ) {
                if ( preg_match( '/^\|[-| ]+\|$/', trim( $row ) ) ) continue; // 区切り行スキップ
                $cells = array_map( 'trim', explode( '|', trim( $row, '|' ) ) );
                $tag   = $first ? 'th' : 'td';
                $html .= '<tr>' . implode( '', array_map( fn($c) => "<{$tag}>{$c}</{$tag}>", $cells ) ) . '</tr>';
                $first = false;
            }
            $html .= '</tbody></table>';
            return $html;
        },
        $md
    );

    // チェックリスト
    $md = preg_replace( '/^- \[ \] (.+)$/m', '<li>☐ $1</li>', $md );
    $md = preg_replace( '/^- \[x\] (.+)$/m', '<li>☑ $1</li>', $md );

    // リスト
    $md = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $md );
    $md = preg_replace( '/^(\d+)\. (.+)$/m', '<li>$2</li>', $md );

    // <li>のグループをulで囲む
    $md = preg_replace( '/(<li>(?:.|\n)*?<\/li>)(?!\n?<li>)/s', '<ul>$1</ul>', $md );

    // 引用
    $md = preg_replace( '/^> (.+)$/m', '<blockquote><p>$1</p></blockquote>', $md );

    // Bold・リンク
    $md = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $md );
    $md = preg_replace( '/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $md );

    // バッククォート
    $md = preg_replace( '/`(.+?)`/', '<code>$1</code>', $md );

    // 段落
    $blocks = preg_split( '/\n{2,}/', $md );
    $html   = '';
    foreach ( $blocks as $block ) {
        $block = trim( $block );
        if ( empty( $block ) ) continue;
        if ( preg_match( '/^<(h[1-6]|ul|ol|table|blockquote|hr)/', $block ) ) {
            $html .= $block . "\n";
        } else {
            $html .= '<p>' . nl2br( $block ) . "</p>\n";
        }
    }
    return $html;
}

$articles = [
    [
        'title'    => 'インパクトドライバーとは？電気ドリルとの違いをわかりやすく解説',
        'slug'     => 'impact-driver-toha-denki-drill-chigai',
        'category' => '工具の基礎知識',
        'file'     => __DIR__ . '/../docs/articles/01_impact-driver-toha-denki-drill-chigai.md',
    ],
    [
        'title'    => 'インパクトドライバーはレンタルと購入どちらがお得？使用頻度別に徹底比較',
        'slug'     => 'impact-driver-rental-vs-purchase',
        'category' => 'レンタル活用術',
        'file'     => __DIR__ . '/../docs/articles/02_impact-driver-rental-vs-purchase.md',
    ],
    [
        'title'    => 'DIY初心者でも作れる！棚の作り方【完全手順ガイド】',
        'slug'     => 'diy-shelf-beginner-complete-guide',
        'category' => 'DIYハウツー',
        'file'     => __DIR__ . '/../docs/articles/03_diy-shelf-beginner-complete-guide.md',
    ],
];

foreach ( $articles as $article ) {
    // ファイル読み込み
    if ( ! file_exists( $article['file'] ) ) {
        WP_CLI::error( "File not found: {$article['file']}" );
        continue;
    }
    $md      = file_get_contents( $article['file'] );
    $content = md2html( $md );
    $cat_id  = get_or_create_category( $article['category'] );

    // 既存チェック（slug重複防止）
    $existing = get_page_by_path( $article['slug'], OBJECT, 'post' );
    if ( $existing ) {
        WP_CLI::warning( "Skip (already exists): {$article['slug']}" );
        continue;
    }

    $post_id = wp_insert_post( [
        'post_title'    => $article['title'],
        'post_name'     => $article['slug'],
        'post_content'  => $content,
        'post_status'   => 'draft',   // まず下書きで確認
        'post_type'     => 'post',
        'post_category' => [ $cat_id ],
    ], true );

    if ( is_wp_error( $post_id ) ) {
        WP_CLI::error( "Failed: {$article['title']} — " . $post_id->get_error_message() );
    } else {
        WP_CLI::success( "Created (draft) ID={$post_id}: {$article['title']}" );
    }
}

WP_CLI::line( '完了。管理画面 → 投稿 → 下書き で確認してから公開してください。' );
