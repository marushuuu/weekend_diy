<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Database {

    // ── Table names ───────────────────────────────────────────────────────────
    public static function products_table()        { global $wpdb; return $wpdb->prefix . 'kogu_products'; }
    public static function rentals_table()         { global $wpdb; return $wpdb->prefix . 'kogu_rentals'; }
    public static function inventory_table()       { global $wpdb; return $wpdb->prefix . 'kogu_inventory'; }
    public static function return_evidence_table() { global $wpdb; return $wpdb->prefix . 'kogu_return_evidence'; }
    public static function late_fees_table()       { global $wpdb; return $wpdb->prefix . 'kogu_late_fees'; }
    public static function addon_products_table()  { global $wpdb; return $wpdb->prefix . 'kogu_addon_products'; }
    public static function rental_addons_table()   { global $wpdb; return $wpdb->prefix . 'kogu_rental_addons'; }

    // ── Install / create tables ───────────────────────────────────────────────
    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // ── 商品（レンタル可能な工具の種類）───────────────────────────────────
        dbDelta( "CREATE TABLE " . self::products_table() . " (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name             VARCHAR(200) NOT NULL DEFAULT '',
            description      TEXT DEFAULT '',
            price_per_week   INT UNSIGNED NOT NULL DEFAULT 4900  COMMENT '1週間のレンタル料金（円）',
            deposit_amount   INT UNSIGNED NOT NULL DEFAULT 10000 COMMENT 'デポジット（円）',
            allows_addons    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '購入オプションを表示するか',
            status           ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        // ── 購入オプション商品（消耗品など） ─────────────────────────────────
        dbDelta( "CREATE TABLE " . self::addon_products_table() . " (
            id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name        VARCHAR(200) NOT NULL DEFAULT '',
            description TEXT DEFAULT '',
            price       INT UNSIGNED NOT NULL DEFAULT 0,
            unit        VARCHAR(50) NOT NULL DEFAULT '個',
            image       VARCHAR(500) NOT NULL DEFAULT '',
            status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;" );

        // ── レンタルに紐づく購入オプション ───────────────────────────────────
        dbDelta( "CREATE TABLE " . self::rental_addons_table() . " (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rental_id        BIGINT UNSIGNED NOT NULL,
            addon_product_id BIGINT UNSIGNED NOT NULL,
            quantity         INT UNSIGNED NOT NULL DEFAULT 1,
            unit_price       INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY rental_id (rental_id)
        ) $charset;" );

        // ── 在庫ユニット（物理的な1台ごと）──────────────────────────────────
        dbDelta( "CREATE TABLE " . self::inventory_table() . " (
            id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id    BIGINT UNSIGNED NOT NULL DEFAULT 1,
            unit_number   TINYINT UNSIGNED NOT NULL COMMENT '商品内の通し番号',
            serial_number VARCHAR(100) DEFAULT '',
            `condition`   ENUM('excellent','good','fair','damaged') NOT NULL DEFAULT 'excellent',
            status        ENUM('available','rented','maintenance','retired') NOT NULL DEFAULT 'available',
            notes         TEXT DEFAULT '',
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id)
        ) $charset;" );

        // ── レンタル注文 ──────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::rentals_table() . " (
            id                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id               BIGINT UNSIGNED DEFAULT NULL,
            user_id                  BIGINT UNSIGNED DEFAULT NULL COMMENT 'NULLはゲスト',
            guest_name               VARCHAR(100) DEFAULT '',
            guest_email              VARCHAR(200) DEFAULT '',
            guest_phone              VARCHAR(30)  DEFAULT '',
            guest_postal_code        VARCHAR(10)  DEFAULT '',
            guest_address            TEXT DEFAULT '',
            inventory_unit_id        BIGINT UNSIGNED DEFAULT NULL,
            rental_start_date        DATE NOT NULL,
            rental_end_date          DATE NOT NULL  COMMENT 'rental_start_date + rental_weeks*7 - 1日',
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
            rental_weeks             SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            rental_days              SMALLINT UNSIGNED NOT NULL DEFAULT 7,
            rental_fee               INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '円',
            deposit_amount           INT UNSIGNED NOT NULL DEFAULT 0  COMMENT '円',
            late_fee_days            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            late_fee_total           INT UNSIGNED NOT NULL DEFAULT 0,
            damage_fee               INT UNSIGNED NOT NULL DEFAULT 0,
            damage_reason            VARCHAR(500) DEFAULT '',
            total_charged            INT UNSIGNED NOT NULL DEFAULT 0,
            deposit_refunded         INT UNSIGNED NOT NULL DEFAULT 0,
            tracking_outbound        VARCHAR(30) DEFAULT '' COMMENT 'ゆうパック追跡番号(往路)',
            tracking_return          VARCHAR(30) DEFAULT '' COMMENT 'ゆうパック追跡番号(返送)',
            stripe_payment_intent_id VARCHAR(100) DEFAULT '',
            stripe_customer_id       VARCHAR(100) DEFAULT '',
            stripe_payment_method_id VARCHAR(100) DEFAULT '',
            refund_scheduled_date    DATE DEFAULT NULL        COMMENT '自動返金予定日（NULLは未スケジュール）',
            refund_hold              TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=自動返金を手動停止中',
            reminder_sent            TINYINT(1) NOT NULL DEFAULT 0,
            overdue_notified         TINYINT(1) NOT NULL DEFAULT 0,
            wc_order_id              BIGINT UNSIGNED DEFAULT NULL,
            notes                    TEXT DEFAULT '',
            created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY rental_end_date (rental_end_date),
            KEY inventory_unit_id (inventory_unit_id)
        ) $charset;" );

        // ── 返却証跡 ─────────────────────────────────────────────────────────
        dbDelta( "CREATE TABLE " . self::return_evidence_table() . " (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rental_id       BIGINT UNSIGNED NOT NULL,
            tracking_number VARCHAR(30) NOT NULL,
            submitted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            submitted_date  DATE NOT NULL COMMENT '返却証跡の提出日（延滞判定基準）',
            status          ENUM('pending','verified_on_time','verified_late','rejected') NOT NULL DEFAULT 'pending',
            notes           TEXT DEFAULT '',
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

        self::seed();
        update_option( 'kogu_db_version', KOGU_VERSION );
    }

    private static function seed() {
        global $wpdb;
        $products_table  = self::products_table();
        $inventory_table = self::inventory_table();

        if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM $products_table" ) > 0 ) return;

        $wpdb->insert( $products_table, [
            'name'          => 'インパクトドライバー',
            'description'   => '18V コードレスインパクトドライバー。DIYから本格作業まで対応。',
            'price_per_week' => 4900,
            'deposit_amount' => 10000,
            'allows_addons'  => 1,
            'status'         => 'active',
        ] );
        $product_id = $wpdb->insert_id;

        for ( $i = 1; $i <= 5; $i++ ) {
            $wpdb->insert( $inventory_table, [
                'product_id'  => $product_id,
                'unit_number' => $i,
                'status'      => 'available',
                'condition'   => 'excellent',
            ] );
        }
    }

    // ── Product helpers ───────────────────────────────────────────────────────
    public static function get_active_products(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . self::products_table() . " WHERE status = 'active' ORDER BY id ASC"
        ) ?: [];
    }

    public static function get_product( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::products_table() . " WHERE id = %d",
            $id
        ) ) ?: null;
    }

    // ── Addon product helpers ─────────────────────────────────────────────────
    public static function get_active_addon_products(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM " . self::addon_products_table() . " WHERE status = 'active' ORDER BY id ASC"
        ) ?: [];
    }

    public static function get_addon_product( int $id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::addon_products_table() . " WHERE id = %d",
            $id
        ) ) ?: null;
    }

    public static function save_rental_addons( int $rental_id, array $addons ): void {
        global $wpdb;
        $table = self::rental_addons_table();
        foreach ( $addons as $addon ) {
            $addon_product = self::get_addon_product( (int) $addon['id'] );
            if ( ! $addon_product || (int) $addon['qty'] < 1 ) continue;
            $wpdb->insert( $table, [
                'rental_id'        => $rental_id,
                'addon_product_id' => (int) $addon['id'],
                'quantity'         => (int) $addon['qty'],
                'unit_price'       => (int) $addon_product->price,
            ] );
        }
    }

    public static function get_rental_addons( int $rental_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT ra.*, ap.name, ap.unit
             FROM " . self::rental_addons_table() . " ra
             JOIN " . self::addon_products_table() . " ap ON ap.id = ra.addon_product_id
             WHERE ra.rental_id = %d",
            $rental_id
        ) ) ?: [];
    }
}
