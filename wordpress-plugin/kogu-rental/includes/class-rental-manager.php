<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Rental_Manager {

    const LATE_FEE_PER_DAY   = 500;  // 円/日
    const WEEK_DISCOUNT_RATE = 0.70; // 2週目以降 30%OFF

    // ── 新規レンタル作成 ─────────────────────────────────────────────────────
    public static function create( array $args ) {
        global $wpdb;

        $product_id = (int) ( $args['product_id'] ?? 1 );
        $weeks      = max( 1, (int) ( $args['rental_weeks'] ?? 1 ) );
        $start      = $args['rental_start_date'];
        $end        = self::calc_end_date( $start, $weeks );

        $unit_id = Kogu_Inventory::get_available_unit_id( $start, $end, $product_id );
        if ( ! $unit_id ) return false;

        $rental_fee = (int) $args['rental_fee'];

        $inserted = $wpdb->insert(
            Kogu_Database::rentals_table(),
            [
                'product_id'               => $product_id,
                'user_id'                  => $args['user_id']                  ?? null,
                'guest_name'               => $args['guest_name']               ?? '',
                'guest_email'              => $args['guest_email']               ?? '',
                'guest_phone'              => $args['guest_phone']               ?? '',
                'guest_postal_code'        => $args['guest_postal_code']         ?? '',
                'guest_address'            => $args['guest_address']             ?? '',
                'inventory_unit_id'        => $unit_id,
                'rental_start_date'        => $start,
                'rental_end_date'          => $end,
                'status'                   => 'confirmed',
                'rental_weeks'             => $weeks,
                'rental_days'              => $weeks * 7,
                'rental_fee'               => $rental_fee,
                'deposit_amount'           => 0,
                'total_charged'            => $args['total_charged'] ?? $rental_fee,
                'stripe_payment_intent_id' => $args['stripe_payment_intent_id'] ?? '',
                'stripe_customer_id'       => $args['stripe_customer_id']       ?? '',
                'stripe_payment_method_id' => $args['stripe_payment_method_id'] ?? '',
                'wc_order_id'              => $args['wc_order_id']              ?? null,
            ]
        );

        if ( ! $inserted ) return false;

        $rental_id = $wpdb->insert_id;

        Kogu_Inventory::set_unit_status( $unit_id, 'rented' );
        Kogu_Email_Handler::send_booking_confirmation( $rental_id );
        Kogu_Email_Handler::send_admin_new_booking( $rental_id );

        return $rental_id;
    }

    // ── ステータス変更 ────────────────────────────────────────────────────────
    public static function update_status( $rental_id, $status, $extra = [] ) {
        global $wpdb;
        $data = array_merge( [ 'status' => $status ], $extra );
        $wpdb->update( Kogu_Database::rentals_table(), $data, [ 'id' => $rental_id ] );
    }

    // ── 発送済み（管理者操作） ────────────────────────────────────────────────
    public static function mark_shipped_to_customer( $rental_id, $tracking_number ) {
        self::update_status( $rental_id, 'shipped_to_customer', [
            'tracking_outbound' => $tracking_number,
        ] );
        Kogu_Email_Handler::send_shipped_notification( $rental_id, $tracking_number );
    }

    // ── 返却証跡の提出（ユーザー操作） ───────────────────────────────────────
    public static function submit_return_evidence( $rental_id, $tracking_number ) {
        global $wpdb;

        $rental = self::get( $rental_id );
        if ( ! $rental ) return false;

        $today          = new DateTime( 'today' );
        $end_date       = new DateTime( $rental->rental_end_date );
        $submitted_date = $today->format( 'Y-m-d' );
        $is_on_time     = $today <= $end_date;

        $wpdb->insert( Kogu_Database::return_evidence_table(), [
            'rental_id'       => $rental_id,
            'tracking_number' => $tracking_number,
            'submitted_date'  => $submitted_date,
            'status'          => $is_on_time ? 'verified_on_time' : 'verified_late',
        ] );

        if ( ! $is_on_time ) {
            self::generate_late_fees( $rental_id );
        }

        self::update_status( $rental_id, 'return_evidence_submitted', [
            'tracking_return'    => $tracking_number,
            'actual_return_date' => $submitted_date,
        ] );

        Kogu_Email_Handler::send_return_received_notification( $rental_id );
        Kogu_Email_Handler::send_admin_return_submitted( $rental_id );

        return true;
    }

    // ── 管理者が返却を確認 → 延滞・損害を登録カードに直接請求 ──────────────
    public static function confirm_return( $rental_id, $damage_fee = 0, $late_fee_override = null, $damage_reason = '' ) {
        global $wpdb;

        $rental = self::get( $rental_id );
        if ( ! $rental ) return false;

        $late_fee_total = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(amount),0) FROM ' . Kogu_Database::late_fees_table() .
            ' WHERE rental_id = %d',
            $rental_id
        ) );

        // Allow manual override (e.g. to waive or reduce late fees)
        if ( $late_fee_override !== null ) {
            $late_fee_total = max( 0, (int) $late_fee_override );
        }

        $total_charge = $late_fee_total + (int) $damage_fee;

        // 延滞・損害がある場合は登録カードに直接請求
        if ( $total_charge > 0 && $rental->stripe_customer_id && $rental->stripe_payment_method_id ) {
            Kogu_Stripe_Handler::charge_additional(
                $rental->stripe_customer_id,
                $rental->stripe_payment_method_id,
                $total_charge,
                "レンタル#{$rental_id} 延滞料金・損害費用"
            );
        }

        // 在庫を即時解放
        Kogu_Inventory::set_unit_status( $rental->inventory_unit_id, 'available' );

        self::update_status( $rental_id, 'returned', [
            'damage_fee'     => $damage_fee,
            'damage_reason'  => sanitize_text_field( $damage_reason ),
            'late_fee_total' => $late_fee_total,
        ] );

        Kogu_Email_Handler::send_return_complete( $rental_id, $total_charge, $late_fee_total, $damage_fee, $damage_reason );

        return true;
    }

    // ── 延滞料金レコードを生成 ────────────────────────────────────────────────
    public static function generate_late_fees( $rental_id ) {
        global $wpdb;

        $rental   = self::get( $rental_id );
        $end_date = new DateTime( $rental->rental_end_date );
        $today    = new DateTime( 'today' );

        $cursor = clone $end_date;
        $cursor->modify( '+1 day' );

        while ( $cursor <= $today ) {
            $date   = $cursor->format( 'Y-m-d' );
            $exists = $wpdb->get_var( $wpdb->prepare(
                'SELECT id FROM ' . Kogu_Database::late_fees_table() .
                ' WHERE rental_id=%d AND fee_date=%s',
                $rental_id, $date
            ) );
            if ( ! $exists ) {
                $wpdb->insert( Kogu_Database::late_fees_table(), [
                    'rental_id' => $rental_id,
                    'fee_date'  => $date,
                    'amount'    => self::LATE_FEE_PER_DAY,
                    'status'    => 'pending',
                ] );
            }
            $cursor->modify( '+1 day' );
        }

        $total_late_days = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Kogu_Database::late_fees_table() . ' WHERE rental_id=%d',
            $rental_id
        ) );
        $wpdb->update( Kogu_Database::rentals_table(), [
            'late_fee_days'  => $total_late_days,
            'late_fee_total' => $total_late_days * self::LATE_FEE_PER_DAY,
            'status'         => 'overdue',
        ], [ 'id' => $rental_id ] );
    }

    // ── 料金計算 ──────────────────────────────────────────────────────────────

    /**
     * 週単位の料金計算。
     * 1週目: price_per_week
     * 2週目以降: round(price_per_week * 0.70) / 週 (30%OFF)
     */
    public static function calc_rental_fee( int $weeks, int $price_per_week ): int {
        if ( $weeks <= 0 ) return 0;
        $discounted_week = (int) round( $price_per_week * self::WEEK_DISCOUNT_RATE );
        return $price_per_week + ( $weeks - 1 ) * $discounted_week;
    }

    /**
     * 開始日から週数で終了日を計算（rental_end_date = start + weeks*7 - 1日）
     */
    public static function calc_end_date( string $start, int $weeks ): string {
        return date( 'Y-m-d', strtotime( $start . ' +' . ( $weeks * 7 - 1 ) . ' days' ) );
    }

    // ── ヘルパー ──────────────────────────────────────────────────────────────
    public static function get( $rental_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . Kogu_Database::rentals_table() . ' WHERE id=%d',
            $rental_id
        ) );
    }

    public static function get_by_user( $user_id, $status = null ) {
        global $wpdb;
        $where = $wpdb->prepare( 'WHERE user_id=%d', $user_id );
        if ( $status ) {
            $where .= $wpdb->prepare( ' AND status=%s', $status );
        }
        return $wpdb->get_results(
            'SELECT * FROM ' . Kogu_Database::rentals_table() . " $where ORDER BY created_at DESC"
        );
    }

    public static function get_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . Kogu_Database::rentals_table() .
            ' WHERE guest_email=%s ORDER BY created_at DESC',
            $email
        ) );
    }

    /** 後方互換のため残す */
    public static function calc_days( $start, $end ) {
        $s = new DateTime( $start );
        $e = new DateTime( $end );
        return max( 1, (int) $s->diff( $e )->days + 1 );
    }
}
