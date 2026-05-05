<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Inventory {

    const TOTAL_UNITS = 5;

    /**
     * 指定期間に貸出可能な台数を返す
     */
    public static function available_count( $start_date, $end_date ) {
        global $wpdb;

        $rentals_table   = Kogu_Database::rentals_table();
        $inventory_table = Kogu_Database::inventory_table();

        // 期間が重複している有効なレンタルに割り当て済みのユニット数
        $booked = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $rentals_table
             WHERE status NOT IN ('cancelled','returned')
               AND rental_start_date <= %s
               AND rental_end_date   >= %s
               AND inventory_unit_id IS NOT NULL",
            $end_date,
            $start_date
        ) );

        $total_active = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $inventory_table
             WHERE status IN ('available','rented')"
        );

        return max( 0, $total_active - $booked );
    }

    /**
     * 指定期間に空きのある在庫ユニットIDを1つ返す（予約割り当て用）
     */
    public static function get_available_unit_id( $start_date, $end_date ) {
        global $wpdb;

        $rentals_table   = Kogu_Database::rentals_table();
        $inventory_table = Kogu_Database::inventory_table();

        $booked_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT inventory_unit_id FROM $rentals_table
             WHERE status NOT IN ('cancelled','returned')
               AND rental_start_date <= %s
               AND rental_end_date   >= %s
               AND inventory_unit_id IS NOT NULL",
            $end_date,
            $start_date
        ) );

        $exclude = empty( $booked_ids ) ? '0' : implode( ',', array_map( 'intval', $booked_ids ) );

        return $wpdb->get_var(
            "SELECT id FROM $inventory_table
             WHERE status IN ('available','rented')
               AND id NOT IN ($exclude)
             ORDER BY unit_number ASC
             LIMIT 1"
        );
    }

    /**
     * カレンダー用：今日から60日間の日付ごとの空き台数
     */
    public static function availability_map( $days = 60 ) {
        $map   = [];
        $today = new DateTime( 'today' );

        for ( $i = 0; $i < $days; $i++ ) {
            $date = ( clone $today )->modify( "+$i days" )->format( 'Y-m-d' );
            $map[ $date ] = self::available_count( $date, $date );
        }

        return $map;
    }

    /**
     * ユニットのステータスを更新
     */
    public static function set_unit_status( $unit_id, $status ) {
        global $wpdb;
        $wpdb->update(
            Kogu_Database::inventory_table(),
            [ 'status' => $status ],
            [ 'id'     => $unit_id ]
        );
    }

    /**
     * 全ユニット一覧
     */
    public static function get_all_units() {
        global $wpdb;
        return $wpdb->get_results(
            'SELECT * FROM ' . Kogu_Database::inventory_table() . ' ORDER BY unit_number ASC'
        );
    }
}
