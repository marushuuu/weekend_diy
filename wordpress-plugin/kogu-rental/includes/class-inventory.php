<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Inventory {

    /** 設定された折り返しバッファ日数（返却期限 + N日 まで在庫をブロック）。郵送3日＋検品1日＝4日想定 */
    private static function get_buffer(): int {
        return max( 0, (int) get_option( 'kogu_return_buffer_days', 4 ) );
    }

    /**
     * 指定商品・期間で利用可能なユニットIDを1件返す。
     * 在庫なしの場合は null を返す。
     * 既存レンタルの返却期限に折り返しバッファを加算して判定する。
     */
    public static function get_available_unit_id( string $start, string $end, int $product_id ): ?int {
        global $wpdb;
        $inv    = Kogu_Database::inventory_table();
        $ren    = Kogu_Database::rentals_table();
        $buffer = self::get_buffer();

        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT i.id FROM $inv i
             WHERE i.product_id = %d
               AND i.status = 'available'
               AND i.id NOT IN (
                   SELECT r.inventory_unit_id FROM $ren r
                   WHERE r.status NOT IN ('returned','cancelled')
                     AND r.rental_start_date <= %s
                     AND DATE_ADD(r.rental_end_date, INTERVAL %d DAY) >= %s
                     AND r.inventory_unit_id IS NOT NULL
               )
             ORDER BY i.unit_number ASC
             LIMIT 1",
            $product_id, $end, $buffer, $start
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
     * 既存レンタルの返却期限にバッファを加算して判定する。
     */
    public static function available_count( string $start, string $end, int $product_id = null ): int {
        global $wpdb;
        $inv    = Kogu_Database::inventory_table();
        $ren    = Kogu_Database::rentals_table();
        $buffer = self::get_buffer();

        $product_where = $product_id
            ? $wpdb->prepare( 'AND i.product_id = %d', $product_id )
            : '';

        $start_sql = esc_sql( $start );
        $end_sql   = esc_sql( $end );
        $buf_int   = (int) $buffer;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $inv i
             WHERE i.status = 'available'
             $product_where
             AND i.id NOT IN (
                 SELECT r.inventory_unit_id FROM $ren r
                 WHERE r.status NOT IN ('returned','cancelled')
                   AND r.rental_start_date <= '$end_sql'
                   AND DATE_ADD(r.rental_end_date, INTERVAL $buf_int DAY) >= '$start_sql'
                   AND r.inventory_unit_id IS NOT NULL
             )"
        );
    }

    /**
     * 今後 $days 日分の在庫マップを返す（バッファ込み）。
     * 返値: [ product_id => [ 'Y-m-d' => available_count, ... ], ... ]
     */
    public static function availability_map( int $days = 90 ): array {
        global $wpdb;
        $inv    = Kogu_Database::inventory_table();
        $ren    = Kogu_Database::rentals_table();
        $buffer = self::get_buffer();

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

            $rentals = $wpdb->get_results( $wpdb->prepare(
                "SELECT r.rental_start_date, r.rental_end_date
                 FROM $ren r
                 JOIN $inv i ON i.id = r.inventory_unit_id
                 WHERE i.product_id = %d
                   AND r.status NOT IN ('returned','cancelled')
                   AND DATE_ADD(r.rental_end_date, INTERVAL %d DAY) >= %s
                   AND r.rental_start_date <= %s",
                $pid, $buffer, $today_str, $end_range
            ) ) ?: [];

            $map = [];
            for ( $i = 0; $i < $days; $i++ ) {
                $d       = ( clone $today )->modify( "+{$i} days" )->format( 'Y-m-d' );
                $map[$d] = $total_units;
            }

            foreach ( $rentals as $r ) {
                $cursor     = new DateTime( max( $r->rental_start_date, $today_str ) );
                // バッファ込みの実質ブロック終了日
                $block_end  = date( 'Y-m-d', strtotime( $r->rental_end_date . " +{$buffer} days" ) );
                $end_dt     = new DateTime( min( $block_end, $end_range ) );
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

    /**
     * 60日間の容量予測モデル。
     * 返値: [ product_id => [ [ date, total, rented, buffer_only, available, rentals_ending[] ], ... ] ]
     */
    public static function capacity_forecast( int $days = 60 ): array {
        global $wpdb;
        $inv    = Kogu_Database::inventory_table();
        $ren    = Kogu_Database::rentals_table();
        $buffer = self::get_buffer();

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

            $rentals = $wpdb->get_results( $wpdb->prepare(
                "SELECT r.id, r.rental_start_date, r.rental_end_date,
                        r.guest_name, r.reservation_number, r.status
                 FROM $ren r
                 JOIN $inv i ON i.id = r.inventory_unit_id
                 WHERE i.product_id = %d
                   AND r.status NOT IN ('returned','cancelled')
                   AND DATE_ADD(r.rental_end_date, INTERVAL %d DAY) >= %s
                   AND r.rental_start_date <= %s",
                $pid, $buffer, $today_str, $end_range
            ) ) ?: [];

            // 日別マップ構築
            $rows = [];
            for ( $i = 0; $i < $days; $i++ ) {
                $d          = ( clone $today )->modify( "+{$i} days" )->format( 'Y-m-d' );
                $rows[$d]   = [
                    'date'          => $d,
                    'total'         => $total_units,
                    'rented'        => 0,    // rental_end_date 以前の実稼働
                    'buffer'        => 0,    // バッファ期間中（返却期限過ぎ・まだ使えない）
                    'available'     => 0,
                    'ending_today'  => [],   // この日が rental_end_date の予約
                ];
            }

            foreach ( $rentals as $r ) {
                // 実レンタル期間カウント
                $rent_start = max( $r->rental_start_date, $today_str );
                $rent_end   = min( $r->rental_end_date,   $end_range );
                $cursor = new DateTime( $rent_start );
                $end_dt = new DateTime( $rent_end );
                while ( $cursor <= $end_dt ) {
                    $d = $cursor->format( 'Y-m-d' );
                    if ( isset( $rows[$d] ) ) { $rows[$d]['rented']++; }
                    $cursor->modify( '+1 day' );
                }

                // 返却期限日を記録
                if ( isset( $rows[ $r->rental_end_date ] ) ) {
                    $rows[ $r->rental_end_date ]['ending_today'][] = $r->reservation_number;
                }

                // バッファ期間（rental_end_date+1 〜 rental_end_date+buffer）
                if ( $buffer > 0 ) {
                    $buf_start_dt = ( new DateTime( $r->rental_end_date ) )->modify( '+1 day' );
                    $buf_end_str  = date( 'Y-m-d', strtotime( $r->rental_end_date . " +{$buffer} days" ) );
                    $buf_end_dt   = new DateTime( min( $buf_end_str, $end_range ) );
                    $bc = clone $buf_start_dt;
                    while ( $bc <= $buf_end_dt ) {
                        $d = $bc->format( 'Y-m-d' );
                        if ( isset( $rows[$d] ) && $rows[$d]['rented'] < $total_units ) {
                            $rows[$d]['buffer']++;
                        }
                        $bc->modify( '+1 day' );
                    }
                }
            }

            // available 計算
            foreach ( $rows as &$row ) {
                $row['available'] = max( 0, $row['total'] - $row['rented'] - $row['buffer'] );
            }

            $result[$pid] = array_values( $rows );
        }

        return $result;
    }

    /**
     * 特定ユニットが指定期間に空いているか確認する（延長可否判定用）。
     * exclude_rental_id: 自身のレンタルIDを除外する。
     */
    public static function is_unit_free_in_period( int $unit_id, string $start, string $end, int $exclude_rental_id ): bool {
        global $wpdb;
        $ren    = Kogu_Database::rentals_table();
        $buffer = self::get_buffer();

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $ren
             WHERE inventory_unit_id = %d
               AND id != %d
               AND status NOT IN ('returned','cancelled')
               AND rental_start_date <= %s
               AND DATE_ADD(rental_end_date, INTERVAL %d DAY) >= %s",
            $unit_id, $exclude_rental_id, $end, $buffer, $start
        ) );

        return $count === 0;
    }
}
