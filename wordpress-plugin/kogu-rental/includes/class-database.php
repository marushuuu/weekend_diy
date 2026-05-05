<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Database {

    // ── Table names ───────────────────────────────────────────────────────────
    public static function rentals_table()         { global $wpdb; return $wpdb->prefix . 'kogu_rentals'; }
    public static function inventory_table()       { global $wpdb; return $wpdb->prefix . 'kogu_inventory'; }
    public static function return_evidence_table() { global $wpdb; return $wpdb->prefix . 'kogu_return_evidence'; }
    public static function late_fees_table()       { global $wpdb; return $wpdb->prefix . 'kogu_late_fees'; }

    // ── Install / create tables ───────────────────────────────────────────────
    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ── 在庫ユニット（物理的な1台ごと） ──────────────────────────────────
        dbDelta( "CREATE TABLE " . self::inventory_table() . " (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            unit_number   TINYINT UNSIGNED NOT NULL COMMENT '1〜5台目',
            serial_number VARCHAR(100) DEFAULT '',
            condition     ENUM('excellent','good','fair','damaged') NOT NULL DEFAULT 'excellent',
            status        ENUM('available','rented','maintenance','retired') NOT NULL DEFAULT 'available',
            notes         TEXT DEFAULT '',
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unit_number (unit_number)
        ) $charset;" );

        // ── レンタル注文 ──────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::rentals_table() . " (
            id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id                  BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULLはゲスト',
            guest_name               VARCHAR(100) DEFAULT '',
            guest_email              VARCHAR(200) DEFAULT '',
            guest_phone              VARCHAR(30)  DEFAULT '',
            guest_postal_code        VARCHAR(10)  DEFAULT '',
            guest_address            TEXT DEFAULT '',
            inventory_unit_id        BIGINT UNSIGNED DEFAULT NULL,
            rental_start_date        DATE NOT NULL,
            rental_end_date          DATE NOT NULL  COMMENT 'この日までに発送すること',
            actual_return_date       DATE DEFAULT NULL,
            status                   ENUM(
                'pending',
                'confirmed',
                'shipped_to_customer',
                'active',
                'return_evidence_submitted',
                'returned',
                'overdue',
                'cancelled'
            ) NOT NULL DEFAULT 'pending',
            rental_days              SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            rental_fee               INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '円',
            deposit_amount           INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '円',
            late_fee_days            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            late_fee_total           INT UNSIGNED NOT NULL DEFAULT 0,
            damage_fee               INT UNSIGNED NOT NULL DEFAULT 0,
            total_charged            INT UNSIGNED NOT NULL DEFAULT 0,
            deposit_refunded         INT UNSIGNED NOT NULL DEFAULT 0,
            tracking_outbound        VARCHAR(30) DEFAULT '' COMMENT 'ゆうパック追跡番号(往路)',
            tracking_return          VARCHAR(30) DEFAULT '' COMMENT 'ゆうパック追跡番号(返送)',
            stripe_payment_intent_id VARCHAR(100) DEFAULT '',
            stripe_customer_id       VARCHAR(100) DEFAULT '',
            stripe_payment_method_id VARCHAR(100) DEFAULT '',
            reminder_sent            TINYINT(1) NOT NULL DEFAULT 0,
            overdue_notified         TINYINT(1) NOT NULL DEFAULT 0,
            wc_order_id              BIGINT UNSIGNED DEFAULT NULL,
            notes                    TEXT DEFAULT '',
            created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY status (status),
            KEY rental_end_date (rental_end_date),
            KEY inventory_unit_id (inventory_unit_id)
        ) $charset;" );

        // ── 返却証跡（ゆうパック追跡番号の提出） ─────────────────────────────
        dbDelta( "CREATE TABLE " . self::return_evidence_table() . " (
            id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rental_id      BIGINT UNSIGNED NOT NULL,
            tracking_number VARCHAR(30) NOT NULL,
            submitted_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            submitted_date DATE NOT NULL COMMENT '返却証跡の提出日（延滞判定基準）',
            status         ENUM('pending','verified_on_time','verified_late','rejected') NOT NULL DEFAULT 'pending',
            notes          TEXT DEFAULT '',
            PRIMARY KEY (id),
            KEY rental_id (rental_id)
        ) $charset;" );

        // ── 延滞料金明細 ─────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::late_fees_table() . " (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rental_id        BIGINT UNSIGNED NOT NULL,
            fee_date         DATE NOT NULL COMMENT '延滞発生日',
            amount           INT UNSIGNED NOT NULL DEFAULT 500,
            stripe_charge_id VARCHAR(100) DEFAULT '',
            status           ENUM('pending','deducted_from_deposit','charged_separately','failed') NOT NULL DEFAULT 'pending',
            charged_at       DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY rental_id (rental_id),
            KEY fee_date (fee_date)
        ) $charset;" );

        // 初期在庫5台を投入
        self::seed_inventory();

        update_option( 'kogu_db_version', KOGU_VERSION );
    }

    private static function seed_inventory() {
        global $wpdb;
        $table = self::inventory_table();
        $existing = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        if ( $existing > 0 ) return;

        for ( $i = 1; $i <= 5; $i++ ) {
            $wpdb->insert( $table, [
                'unit_number' => $i,
                'status'      => 'available',
                'condition'   => 'excellent',
            ] );
        }
    }
}
