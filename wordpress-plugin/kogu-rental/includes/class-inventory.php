<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Inventory {

    /**
     * 指定商品・期間で利用可能なユニットIDを1件返す。
     * 在庫なしの場合は null を返す。
     */
    public static function get_available_unit_id( string $start, string $end, int $product_id ): ?int {
        global $wpdb;
        $inv = Kogu_Database::inventory_table();
        $ren = Kogu_Database::rentals_table();

        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT i.id FROM $inv i
             WHERE i.product_id = %d
               AND i.status = 'available'
               AND i.id NOT IN (
                   SELECT r.inventory_unit_id FROM $ren r
                   WHERE r.status NOT IN ('returned','cancelled')
                     AND r.rental_start_date <= %s
                     AND r.rental_end_date   >= %s
                     AND r.inventory_unit_id IS NOT NULL
               )
             ORDER BY i.unit_number ASC
             LIMIT 1",
            $product_id, $end, $start
        ) );

        return $id ? (int) $id : null;
    }

    /** ユニットのステータスを更新する */
    public static function set_unit_status( int $unit_id, string $status ): void {
        global $wpdb;
        $wpdb->update( Kogu_Database::inventory_table(), [ 'status' => $status ], [ 'id' => $unit_id ] );
    }

    /** 在庫ユニット一覧を返す（product_id 指定で絞り込み可） */
    public static function get_all_units( int $product_id = null ): array {
        global $wpdb;
        $table = Kogu_Database::inventory_table();
        if ( $product_id ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $table WHERE product_id = %d ORDER BY unit_number ASC",
                $product_id
            ) ) ?: [];
        }
        return $wpdb->get_results(
            "SELECT * FROM $table ORDER BY product_id ASC, unit_number ASC"
        ) ?: [];
    }

    /**
     * 指定期間に利用可能な台数を返す。
     * product_id = null のときは全商品合算。
     */
    public static function available_count( string $start, string $end, int $product_id = null ): int {
        global $wpdb;
        $inv = Kogu_Database::inventory_table();
        $ren = Kogu_Database::rentals_table();

        $product_where = $product_id
            ? $wpdb->prepare( 'AND i.product_id = %d', $product_id )
            : '';

        $start_sql = esc_sql( $start );
        $end_sql   = esc_sql( $end );

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $inv i
             WHERE i.status = 'available'
             $product_where
             AND i.id NOT IN (
                 SELECT r.inventory_unit_id FROM $ren r
                 WHERE r.status NOT IN ('returned','cancelled')
                   AND r.rental_start_date <= '$end_sql'
                   AND r.rental_end_date   >= '$start_sql'
                   AND r.inventory_unit_id IS NOT NULL
             )"
        );
    }

    /**
     * 今後 $days 日分の在庫マップを返す。
     * 返値: [ product_id => [ 'Y-m-d' => available_count, ... ], ... ]
     *
     * JS の在庫チェックで使用（在庫ゼロの日をブロックする）。
     */
    public static function availability_map( int $days = 90 ): array {
        global $wpdb;
        $inv = Kogu_Database::inventory_table();
        $ren = Kogu_Database::rentals_table();

        $today     = new DateTime( 'today' );
        $today_str = $today->format( 'Y-m-d' );
        $end_range = ( clone $today )->modify( "+{$days} days" )->format( 'Y-m-d' );

        $products = Kogu_Database::get_active_products();
        $result   = [];

        foreach ( $products as $product ) {
            $pid = (int) $product->id;

            $total_units = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $inv WHERE product_id = %d AND status = 'available'",
                $pid
            ) );

            // 期間内の有効レンタルを1クエリで取得（N*90クエリを回避）
            $rentals = $wpdb->get_results( $wpdb->prepare(
                "SELECT r.rental_start_date, r.rental_end_date
                 FROM $ren r
                 JOIN $inv i ON i.id = r.inventory_unit_id
                 WHERE i.product_id = %d
                   AND r.status NOT IN ('returned','cancelled')
                   AND r.rental_end_date   >= %s
                   AND r.rental_start_date <= %s",
                $pid, $today_str, $end_range
            ) ) ?: [];

            // 全日付を在庫数で初期化
            $map = [];
            for ( $i = 0; $i < $days; $i++ ) {
                $d       = ( clone $today )->modify( "+{$i} days" )->format( 'Y-m-d' );
                $map[$d] = $total_units;
            }

            // レンタル期間中の日付ごとに台数を1引く
            foreach ( $rentals as $r ) {
                $cursor = new DateTime( max( $r->rental_start_date, $today_str ) );
                $end_dt = new DateTime( min( $r->rental_end_date, $end_range ) );
                while ( $cursor <= $end_dt ) {
                    $d = $cursor->format( 'Y-m-d' );
                    if ( isset( $map[$d] ) ) {
                        $map[$d] = max( 0, $map[$d] - 1 );
                    }
                    $cursor->modify( '+1 day' );
                }
            }

            $result[$pid] = $map;
        }

        return $result;
    }
}
