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
    // YAMLフロントマター除去（--- ... --- ブロック）
    $md = preg_replace( '/^---[\s\S]*?---\n/m', '', $md );
    // 旧形式フロントマター除去
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
    [ 'title' => 'インパクトドライバーとは？電動ドリルとの違い・初心者の選び方を解説',                                                   'slug' => 'impact-driver-toha-denki-drill-chigai',       'category' => '工具の使い方',   'file' => __DIR__ . '/../docs/articles/01_impact-driver-toha-denki-drill-chigai.md' ],
    [ 'title' => 'インパクトドライバーはレンタルと購入どちらがお得？使用頻度で判断する完全ガイド',                                         'slug' => 'impact-driver-rental-vs-purchase',            'category' => 'レンタル活用術', 'file' => __DIR__ . '/../docs/articles/02_impact-driver-rental-vs-purchase.md' ],
    [ 'title' => 'DIY初心者が棚を作る全手順｜木材選び・カット・組み立て・塗装まで工具リスト付き',                                          'slug' => 'diy-shelf-beginner-complete-guide',           'category' => 'DIYガイド',      'file' => __DIR__ . '/../docs/articles/03_diy-shelf-beginner-complete-guide.md' ],
    [ 'title' => '木工トリマーとは？使い方・ビットの種類・初心者がやりがちな失敗を徹底解説',                                               'slug' => 'wood-trimmer-how-to-use',                     'category' => '工具の使い方',   'file' => __DIR__ . '/../docs/articles/04_wood-trimmer-how-to-use.md' ],
    [ 'title' => '高圧洗浄機レンタルの完全ガイド｜料金・使い方・失敗しない場所の選び方',                                                   'slug' => 'pressure-washer-rental-guide',                'category' => 'レンタル活用術', 'file' => __DIR__ . '/../docs/articles/05_pressure-washer-rental-guide.md' ],
    [ 'title' => 'コーナン・コメリ・ナフコの工具レンタルを比較｜料金・種類・オンラインとの違いまで解説',                                    'slug' => 'homecenter-tool-rental-compare',              'category' => 'レンタル活用術', 'file' => __DIR__ . '/../docs/articles/06_homecenter-tool-rental-compare.md' ],
    [ 'title' => '電動サンダーとは？種類・選び方・使い方を初心者向けに解説｜塗装前の下地処理に',                                           'slug' => 'electric-sander-how-to-use',                  'category' => '工具の使い方',   'file' => __DIR__ . '/../docs/articles/07_electric-sander-how-to-use.md' ],
    [ 'title' => 'ジグソーとは？曲線カットの使い方・ブレードの選び方を初心者向けに解説',                                                   'slug' => 'jigsaw-how-to-use-curve-cut',                 'category' => '工具の使い方',   'file' => __DIR__ . '/../docs/articles/08_jigsaw-how-to-use-curve-cut.md' ],
    [ 'title' => 'タッカーとは？使い方・針の選び方・椅子の張替えから壁紙留めまで初心者向けに解説',                                         'slug' => 'tacker-how-to-use-beginner',                  'category' => '工具の使い方',   'file' => __DIR__ . '/../docs/articles/09_tacker-how-to-use-beginner.md' ],
    [ 'title' => '電気カンナとは？使い方・削り量の調整・手カンナとの違いをDIY初心者向けに解説',                                           'slug' => 'electric-planer-how-to-use',                  'category' => '工具の使い方',   'file' => __DIR__ . '/../docs/articles/10_electric-planer-how-to-use.md' ],
    [ 'title' => 'レーザー墨出し器の使い方｜棚・カーテンレール・壁掛けを水平に決める手順を解説',                                           'slug' => 'laser-level-how-to-use-diy',                  'category' => '工具の使い方',   'file' => __DIR__ . '/../docs/articles/11_laser-level-how-to-use-diy.md' ],
    [ 'title' => 'ハンマードリルとは？振動ドリルとの違い・使い方・コンクリート穴あけの手順を解説',                                         'slug' => 'hammer-drill-how-to-use',                     'category' => '工具の使い方',   'file' => __DIR__ . '/../docs/articles/12_hammer-drill-how-to-use.md' ],
    [ 'title' => 'コメリの工具レンタルを徹底解説｜料金・借り方・オンラインレンタルとの比較',                                               'slug' => 'komeri-tool-rental-guide',                    'category' => 'レンタル活用術', 'file' => __DIR__ . '/../docs/articles/13_komeri-tool-rental-guide.md' ],
    [ 'title' => 'ナフコ・DCM・ケーヨーD2の工具レンタルを比較｜料金・対応エリア・借り方を解説',                                           'slug' => 'nafco-dcm-keyod2-tool-rental-compare',        'category' => 'レンタル活用術', 'file' => __DIR__ . '/../docs/articles/14_nafco-dcm-keyod2-tool-rental-compare.md' ],
    [ 'title' => '木工トリマーはレンタルと購入どちらがお得？使用頻度で判断する完全ガイド',                                                 'slug' => 'trimmer-rental-vs-purchase',                  'category' => 'レンタル活用術', 'file' => __DIR__ . '/../docs/articles/15_trimmer-rental-vs-purchase.md' ],
    [ 'title' => '電動ドライバーをレンタルする方法｜料金・手順・どこで借りるか徹底解説',                                                   'slug' => 'electric-driver-rental',                      'category' => 'レンタル活用術', 'file' => __DIR__ . '/../docs/articles/16_electric-driver-rental.md' ],
    [ 'title' => 'インパクトドライバーをレンタルする方法｜料金・どこで借りる・何週間借りるかを解説',                                       'slug' => 'impact-driver-rental-how-to',                 'category' => 'レンタル活用術', 'file' => __DIR__ . '/../docs/articles/17_impact-driver-rental-how-to.md' ],
    [ 'title' => '電動ドリルをレンタルする方法｜種類の選び方・料金・どこで借りるかを解説',                                                 'slug' => 'electric-drill-rental',                       'category' => 'レンタル活用術', 'file' => __DIR__ . '/../docs/articles/18_electric-drill-rental.md' ],
    [ 'title' => 'インパクトレンチをレンタルする方法｜タイヤ交換・ボルト締めに使う料金と手順を解説',                                       'slug' => 'impact-wrench-rental',                        'category' => 'レンタル活用術', 'file' => __DIR__ . '/../docs/articles/19_impact-wrench-rental.md' ],
    [ 'title' => 'コアドリル・コンクリートドリルをレンタルする方法｜エアコンスリーブ・配管穴あけの料金と手順',                              'slug' => 'core-drill-rental',                           'category' => 'レンタル活用術', 'file' => __DIR__ . '/../docs/articles/20_core-drill-rental.md' ],
    [ 'title' => 'アングルインパクト・アングルドライバーとは？使い方・マキタ対応機種・レンタル方法を解説',                                  'slug' => 'angle-impact-driver-how-to',                  'category' => '工具の使い方',   'file' => __DIR__ . '/../docs/articles/21_angle-impact-driver.md' ],
    [ 'title' => 'HiKOKI（ハイコーキ）の電動工具とは？ビス打ち機・釘打ち機・マルチボルトの特徴とレンタル方法を解説',                       'slug' => 'hikoki-tool-rental-guide',                    'category' => '工具の使い方',   'file' => __DIR__ . '/../docs/articles/22_hikoki-tool-rental-guide.md' ],
    [ 'title' => '工具レンタルの返し方｜梱包・ゆうパック着払いの手順を写真で解説',                                                         'slug' => 'return-method',                               'category' => 'レンタル活用術', 'file' => __DIR__ . '/../wordpress-content/blog/return-method.html' ],
];

foreach ( $articles as $article ) {
    // ファイル読み込み
    if ( ! file_exists( $article['file'] ) ) {
        WP_CLI::error( "File not found: {$article['file']}" );
        continue;
    }
    $raw     = file_get_contents( $article['file'] );
    if ( str_ends_with( $article['file'], '.html' ) ) {
        $plugin_url = plugins_url( 'kogu-rental/' );
        $content    = str_replace( '##PLUGIN_URL##', $plugin_url, $raw );
    } else {
        $content = md2html( $raw );
    }
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
