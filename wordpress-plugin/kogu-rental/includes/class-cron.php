<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Cron {

    public static function init() {
        add_action( 'kogu_daily_tasks', [ __CLASS__, 'run_daily_tasks' ] );

        if ( ! wp_next_scheduled( 'kogu_daily_tasks' ) ) {
            wp_schedule_event( strtotime( 'today 09:00' ), 'daily', 'kogu_daily_tasks' );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'kogu_daily_tasks' );
    }

    public static function run_daily_tasks() {
        self::send_return_reminders();
        self::detect_overdue();
        self::send_overdue_warnings();
        self::accrue_late_fees();
    }

    // ── 返却期限1日前リマインダー ─────────────────────────────────────────────
    private static function send_return_reminders() {
        global $wpdb;

        $tomorrow = date( 'Y-m-d', strtotime( '+1 day' ) );

        $rentals = $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . Kogu_Database::rentals_table() .
            " WHERE rental_end_date = %s
               AND status IN ('shipped_to_customer','active')
               AND reminder_sent = 0",
            $tomorrow
        ) );

        foreach ( $rentals as $rental ) {
            Kogu_Email_Handler::send_return_reminder( $rental->id );
            $wpdb->update(
                Kogu_Database::rentals_table(),
                [ 'reminder_sent' => 1 ],
                [ 'id' => $rental->id ]
            );
        }
    }

    // ── 延滞検出・ステータス更新のみ（通知・料金計算はしない）────────────────
    private static function detect_overdue() {
        global $wpdb;

        $today = date( 'Y-m-d' );

        $wpdb->query( $wpdb->prepare(
            'UPDATE ' . Kogu_Database::rentals_table() .
            " SET status = 'overdue'
              WHERE rental_end_date < %s
                AND status IN ('shipped_to_customer','active','confirmed')",
            $today
        ) );
    }

    // ── 期限超過4日目：延滞料金が発生した旨を通知（1回のみ）────────────────
    private static function send_overdue_warnings() {
        global $wpdb;

        // 期限から4日後（料金発生初日）に1回だけ送信
        $four_days_ago = date( 'Y-m-d', strtotime( '-4 days' ) );

        $rentals = $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . Kogu_Database::rentals_table() .
            " WHERE rental_end_date = %s
               AND status = 'overdue'
               AND overdue_notified = 0",
            $four_days_ago
        ) );

        foreach ( $rentals as $rental ) {
            Kogu_Email_Handler::send_overdue_warning( $rental->id );
            $wpdb->update(
                Kogu_Database::rentals_table(),
                [ 'overdue_notified' => 1 ],
                [ 'id' => $rental->id ]
            );
        }
    }

    // ── 猶予期間後（4日目以降）の延滞料金を積算 ─────────────────────────────
    private static function accrue_late_fees() {
        global $wpdb;

        $overdue_rentals = $wpdb->get_results(
            'SELECT * FROM ' . Kogu_Database::rentals_table() .
            " WHERE status = 'overdue'"
        );

        foreach ( $overdue_rentals as $rental ) {
            Kogu_Rental_Manager::generate_late_fees( $rental->id );
        }
    }
}
