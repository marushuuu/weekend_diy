<?php
/**
 * ビットセット商品のデータを更新するスクリプト
 * 実行方法:
 *   wp --path=/home/xs277376/weekend-diy.com/public_html eval-file /home/xs277376/weekend_diy_deploy/scripts/update-bitset-product.php
 */

global $wpdb;
$table = $wpdb->prefix . 'kogu_products';

// 「工具セット」または「ビットセット」という名前の商品を検索
$product = $wpdb->get_row(
    "SELECT * FROM $table WHERE name LIKE '%セット%' AND name NOT LIKE '%インパクト%' LIMIT 1"
);

if ( ! $product ) {
    WP_CLI::error( '対象商品が見つかりませんでした。管理画面で商品名を確認してください。' );
    exit;
}

WP_CLI::line( "対象商品: ID={$product->id} name={$product->name}" );

$new_data = [
    'name'        => 'E-Value ビットセット BS-4（29点組）',
    'slug'        => 'bit-set',
    'description' => 'イーバリュー（E-Value）の充電ドライバー・インパクトドライバー用ビット＆ソケットセット29点組。ドライバービット・六角軸鉄工ドリル・ソケット・イージーチャックがプラスチックケースにまとまった使いやすいセット。',
    'specs'       => implode( "\n", [
        'ブランド|イーバリュー（E-Value）',
        '型番|BS-4',
        'セット内容|29点組',
        '六角軸鉄工ドリル（ハイス製）|2 / 2.5 / 3 mm 各1本',
        'ドライバービット材質|特殊合金工具鋼（S-2）',
        'ソケット材質|クロームバナジウム',
        '両頭ビット（−）|6mm×65mm 1本',
        '両頭ビット（＋）|#1×65mm 1本、#2×45mm 2本、#2×65mm 3本、#1×110mm 1本、#2×110mm 2本',
        '段付ビット|#1×100mm 1本、#2×100mm 1本',
        '六角ビット 50mm長|2 / 2.5 / 3 / 4 / 5 / 6 mm 各1本',
        '1/4″ソケット|6 / 7 / 8 / 10 / 12 / 13 mm 各1個',
        '1/4″ソケットアダプター|1本',
        'EZ（イージー）チャック|1個',
        'ケース|プラスチックケース付き',
    ] ),
    'contents'    => implode( "\n", [
        '六角軸鉄工ドリル（ハイス製）2・2.5・3mm 各1本',
        '両頭ビット（−6mm×65mm）1本',
        '両頭ビット（+#1×65mm）1本、（+#2×45mm）2本、（+#2×65mm）3本、（+#1×110mm）1本、（+#2×110mm）2本',
        '段付ビット（+#1×100mm）1本、（+#2×100mm）1本',
        '1/4″ソケットアダプター 1本',
        '六角ビット50mm長 2・2.5・3・4・5・6mm 各1本',
        '1/4″ソケット（クロームバナジウム）6・7・8・10・12・13mm 各1個',
        'EZ（イージー）チャック 1個',
        'プラスチックケース',
    ] ),
    'gallery'     => 'product_bitset.jpg',
];

$result = $wpdb->update( $table, $new_data, [ 'id' => $product->id ] );

if ( $result === false ) {
    WP_CLI::error( 'DB更新失敗: ' . $wpdb->last_error );
} else {
    WP_CLI::success( "ID={$product->id} を更新しました → 名前: E-Value ビットセット BS-4（29点組）" );
}
