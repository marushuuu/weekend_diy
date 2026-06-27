<?php
/**
 * インパクトドライバー商品のスペックを更新するスクリプト
 * 実行方法:
 *   wp --path=/home/xs277376/weekend-diy.com/public_html eval-file /home/xs277376/weekend_diy_deploy/scripts/update-impact-product.php
 */

global $wpdb;
$table = $wpdb->prefix . 'kogu_products';

$product = $wpdb->get_row(
    "SELECT * FROM $table WHERE name LIKE '%インパクト%' LIMIT 1"
);

if ( ! $product ) {
    WP_CLI::error( '対象商品が見つかりませんでした。管理画面で商品名を確認してください。' );
    exit;
}

WP_CLI::line( "対象商品: ID={$product->id} name={$product->name}" );

$new_specs = implode( "\n", [
    '製品名/形名|コードレスインパクトドライバ FWH 18DGL',
    '締付能力（小ねじ）|4〜8mm',
    '締付能力（普通ボルト）|M6〜M14',
    '締付能力（高力ボルト）|M6〜M12',
    '最大トルク|150N·m（1,530kgf·cm）',
    '六角軸二面幅|6.35mm',
    '回転数|0〜2,400min⁻¹（回/分）',
    '打撃数|0〜3,200min⁻¹（打撃/分）',
    '機体寸法|全長166×高さ221×センタハイト28.5mm（BSL 1815 装着時）',
    '質量|1.4kg（BSL 1815 装着時）',
    '蓄電池|形名 BSL 1815、電圧 18V、容量 1.5Ah（充電時間約40分）',
    '充電器|形名 UC 18YKSL',
    '標準付属品|充電器・予備電池・ケース・No.2 プラスビット・電池カバー×2個',
] );

$result = $wpdb->update(
    $table,
    [ 'specs' => $new_specs ],
    [ 'id'    => $product->id ]
);

if ( $result === false ) {
    WP_CLI::error( 'DB更新失敗: ' . $wpdb->last_error );
} else {
    WP_CLI::success( "ID={$product->id}（{$product->name}）のスペックを更新しました。" );
}
