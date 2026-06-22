<?php
/**
 * ブログ記事（wordpress-content/blog/）を下書きとして投入するスクリプト
 * 実行方法:
 *   wp --path=/home/xs277376/weekend-diy.com/public_html eval-file /home/xs277376/weekend_diy_deploy/scripts/import-blog-articles.php
 */

$blog_dir   = __DIR__ . '/../wordpress-content/blog/';
$plugin_url = plugins_url( 'kogu-rental/' );

$articles = [
    [
        'title'    => '工具レンタルの返し方｜梱包・ゆうパック着払いの手順を写真で解説',
        'slug'     => 'return-method',
        'category' => 'レンタル活用術',
        'file'     => $blog_dir . 'return-method.html',
    ],
    [
        'title'    => '電動ノコギリのレンタル方法｜ジグソー・丸ノコ・レシプロソーの違いと選び方',
        'slug'     => 'denki-nokogiri-rental',
        'category' => 'レンタル活用術',
        'file'     => $blog_dir . 'denki-nokogiri-rental.html',
    ],
    [
        'title'    => 'ホームセンターの工具レンタルとオンラインレンタルを比較｜費用・手間・品質の違い',
        'slug'     => 'homecenter-kogu-rental',
        'category' => 'レンタル活用術',
        'file'     => $blog_dir . 'homecenter-kogu-rental.html',
    ],
    [
        'title'    => 'ハンマードリルのレンタル方法｜コンクリート穴あけ・振動ドリルとの違いを解説',
        'slug'     => 'hammer-drill-rental',
        'category' => 'レンタル活用術',
        'file'     => $blog_dir . 'hammer-drill-rental.html',
    ],
    [
        'title'    => '電動ドリルのレンタル方法｜種類の違い・選び方・インパクトドライバーとの比較',
        'slug'     => 'denki-drill-rental',
        'category' => 'レンタル活用術',
        'file'     => $blog_dir . 'denki-drill-rental.html',
    ],
    [
        'title'    => '丸ノコのレンタル方法｜木材カット・安全な使い方・初心者向けガイド',
        'slug'     => 'maruko-rental',
        'category' => 'レンタル活用術',
        'file'     => $blog_dir . 'maruko-rental.html',
    ],
    [
        'title'    => '電動サンダーのレンタル方法｜種類・塗装前の研磨作業を効率化するコツ',
        'slug'     => 'sander-rental',
        'category' => 'レンタル活用術',
        'file'     => $blog_dir . 'sander-rental.html',
    ],
    [
        'title'    => '木工トリマーのレンタル方法｜溝切り・面取り・装飾加工の用途と使い方',
        'slug'     => 'trimmer-rental',
        'category' => 'レンタル活用術',
        'file'     => $blog_dir . 'trimmer-rental.html',
    ],
    [
        'title'    => '電動カンナのレンタル方法｜手カンナとの違い・木材の面出しに使う手順を解説',
        'slug'     => 'denki-kanna-rental',
        'category' => 'レンタル活用術',
        'file'     => $blog_dir . 'denki-kanna-rental.html',
    ],
    [
        'title'    => 'ジグソーのレンタル方法｜曲線カットの用途・ブレード選び・初心者向けガイド',
        'slug'     => 'jigsaw-rental',
        'category' => 'レンタル活用術',
        'file'     => $blog_dir . 'jigsaw-rental.html',
    ],
    [
        'title'    => '工具の貸し出しとは？レンタルとの違い・利用方法・おすすめサービスを解説',
        'slug'     => 'kogu-kashidashi',
        'category' => 'レンタル活用術',
        'file'     => $blog_dir . 'kogu-kashidashi.html',
    ],
];

// カテゴリを作成または取得
function blog_get_or_create_category( $name ) {
    $existing = get_term_by( 'name', $name, 'category' );
    if ( $existing ) return $existing->term_id;
    $result = wp_insert_term( $name, 'category' );
    return is_wp_error( $result ) ? 1 : $result['term_id'];
}

foreach ( $articles as $article ) {
    if ( ! file_exists( $article['file'] ) ) {
        WP_CLI::warning( "File not found: {$article['file']}" );
        continue;
    }

    $content = file_get_contents( $article['file'] );
    $content = str_replace( '##PLUGIN_URL##', $plugin_url, $content );
    $cat_id  = blog_get_or_create_category( $article['category'] );

    // 既存投稿を確認（更新 or 新規）
    $existing = get_page_by_path( $article['slug'], OBJECT, 'post' );

    $post_data = [
        'post_title'    => $article['title'],
        'post_name'     => $article['slug'],
        'post_content'  => $content,
        'post_status'   => 'draft',
        'post_type'     => 'post',
        'post_category' => [ $cat_id ],
    ];

    if ( $existing ) {
        $post_data['ID'] = $existing->ID;
        $post_id = wp_update_post( $post_data, true );
        $action  = '更新';
    } else {
        $post_id = wp_insert_post( $post_data, true );
        $action  = '新規作成';
    }

    if ( is_wp_error( $post_id ) ) {
        WP_CLI::error( "失敗: {$article['title']} — " . $post_id->get_error_message() );
    } else {
        WP_CLI::success( "{$action} (draft) ID={$post_id}: {$article['title']}" );
    }
}

WP_CLI::line( '完了。管理画面 → 投稿 → 下書き で確認してください。' );
