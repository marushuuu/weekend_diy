<?php
/**
 * 在庫テーブル作成スクリプト
 * 使用方法: wp eval-file path/to/this/file.php
 */
global $wpdb;

$charset = $wpdb->get_charset_collate();
$table   = $wpdb->prefix . 'kogu_inventory';

$sql = "CREATE TABLE IF NOT EXISTS `$table` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id`    BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `unit_number`   TINYINT UNSIGNED NOT NULL,
    `serial_number` VARCHAR(100) DEFAULT '',
    `condition`     ENUM('excellent','good','fair','damaged') NOT NULL DEFAULT 'excellent',
    `status`        ENUM('available','rented','maintenance','retired') NOT NULL DEFAULT 'available',
    `notes`         TEXT,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `product_id` (`product_id`)
) $charset;";

$result = $wpdb->query( $sql );

if ( $result === false ) {
    echo "ERROR: " . $wpdb->last_error . "\n";
} else {
    echo "OK: テーブル作成完了 ($table)\n";
}

// 確認
$exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
echo $exists ? "確認: $table が存在します\n" : "警告: テーブルが見つかりません\n";
