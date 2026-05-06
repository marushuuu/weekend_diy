<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Rental_Manager {

    const LATE_FEE_PER_DAY = 500; // 円

    // ── 新規レンタル作成 ─────────────────────────────────────────────────────
    /**
     * @param array $args {
     *   user_id, guest_name, guest_email, guest_phone,
     *   guest_postal_code, guest_address,
     *   rental_start_date, rental_end_date,
     *   rental_fee, deposit_amount,
     *   stripe_payment_intent_id, stripe_customer_id, stripe_payment_method_id,
     *   wc_order_id
     * }
     * @return int|false  rental ID
     */
    public static function create( array $args ) {
        global $wpdb;

        $start = $args['rental_start_date'];
        $end   = $args['rental_end_date'];

        // 空きユニットを確保
        $unit_id = Kogu_Inventory::get_available_unit_id( $start, $end );
        if ( ! $unit_id ) {
            return false; // 在庫なし
        }

        $days = self::calc_days( $start, $end );

        $inserted = $wpdb->insert(
            Kogu_Database::rentals_table(),
            [
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
                'rental_days'              => $days,
                'rental_fee'               => $args['rental_fee'],
                'deposit_amount'           => $args['deposit_amount'],
                'total_charged'            => $args['rental_fee'] + $args['deposit_amount'],
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

        $today         = new DateTime( 'today' );
        $end_date      = new DateTime( $rental->rental_end_date );
        $submitted_date = $today->format( 'Y-m-d' );
        $is_on_time    = $today <= $end_date;

        $wpdb->insert( Kogu_Database::return_evidence_table(), [
            'rental_id'       => $rental_id,
            'tracking_number' => $tracking_number,
            'submitted_date'  => $submitted_date,
            'status'          => $is_on_time ? 'verified_on_time' : 'verified_late',
        ] );

        self::update_status( $rental_id, 'return_evidence_submitted', [
            'tracking_return'  => $tracking_number,
            'actual_return_date' => $submitted_date,
        ] );

        // 延滞の場合：遅延日数を計算して延滞料金レコードを生成
        if ( ! $is_on_time ) {
            self::generate_late_fees( $rental_id );
        }

        Kogu_Email_Handler::send_return_received_notification( $rental_id );
        Kogu_Email_Handler::send_admin_return_submitted( $rental_id );

        return true;
    }

    // ── 管理者が返却を最終確認 → デポジット精算 ─────────────────────────────
    public static function confirm_return( $rental_id, $damage_fee = 0 ) {
        global $wpdb;

        $rental = self::get( $rental_id );
        if ( ! $rental ) return false;

        // 延滞料金の合計を集計
        $late_fee_total = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COALESCE(SUM(amount),0) FROM ' . Kogu_Database::late_fees_table() .
            ' WHERE rental_id = %d',
            $rental_id
        ) );

        $deposit        = (int) $rental->deposit_amount;
        $deduction      = $late_fee_total + $damage_fee;
        $refund_amount  = max( 0, $deposit - $deduction );
        $extra_charge   = max( 0, $deduction - $deposit );

        // Stripeデポジット返金
        if ( $refund_amount > 0 ) {
            Kogu_Stripe_Handler::refund_deposit( $rental->stripe_payment_intent_id, $refund_amount );
        }

        // デポジットを超える延滞・破損費用は追加請求
        if ( $extra_charge > 0 && $rental->stripe_payment_method_id ) {
            Kogu_Stripe_Handler::charge_additional(
                $rental->stripe_customer_id,
                $rental->stripe_payment_method_id,
                $extra_charge,
                "レンタル#{$rental_id} 追加料金（延滞・損害）"
            );
        }

        self::update_status( $rental_id, 'returned', [
            'damage_fee'         => $damage_fee,
            'late_fee_total'     => $late_fee_total,
            'deposit_refunded'   => $refund_amount,
        ] );

        // ユニットを空きに戻す
        Kogu_Inventory::set_unit_status( $rental->inventory_unit_id, 'available' );

        Kogu_Email_Handler::send_return_complete( $rental_id, $refund_amount, $late_fee_total, $damage_fee );

        return true;
    }

    // ── 延滞料金レコードを生成 ────────────────────────────────────────────────
    public static function generate_late_fees( $rental_id ) {
        global $wpdb;

        $rental   = self::get( $rental_id );
        $end_date = new DateTime( $rental->rental_end_date );
        $today    = new DateTime( 'today' );

        // 期限の翌日から今日まで1日500円
        $cursor = clone $end_date;
        $cursor->modify( '+1 day' );

        while ( $cursor <= $today ) {
            $date = $cursor->format( 'Y-m-d' );
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

        // rentalテーブルも更新
        $total_late_days = $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Kogu_Database::late_fees_table() .
            ' WHERE rental_id=%d',
            $rental_id
        ) );
        $wpdb->update( Kogu_Database::rentals_table(), [
            'late_fee_days'  => $total_late_days,
            'late_fee_total' => $total_late_days * self::LATE_FEE_PER_DAY,
            'status'         => 'overdue',
        ], [ 'id' => $rental_id ] );
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

    public static function calc_days( $start, $end ) {
        $s = new DateTime( $start );
        $e = new DateTime( $end );
        return max( 1, (int) $s->diff( $e )->days + 1 );
    }

    /**
     * レンタル料金を計算する
     * 設定: 1日あたり単価（wp option: kogu_price_per_day）
     */
    public static function calc_rental_fee( $start, $end ) {
        $days        = self::calc_days( $start, $end );
        $price_per_day = (int) get_option( 'kogu_price_per_day', 1000 );
        return $days * $price_per_day;
    }
}
