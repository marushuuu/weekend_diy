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

    // ── 延滞検出・ステータス更新・初回通知 ────────────────────────────────────
    private static function detect_overdue() {
        global $wpdb;

        $today = date( 'Y-m-d' );

        $rentals = $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . Kogu_Database::rentals_table() .
            " WHERE rental_end_date < %s
               AND status IN ('shipped_to_customer','active','confirmed')",
            $today
        ) );

        foreach ( $rentals as $rental ) {
            $wpdb->update(
                Kogu_Database::rentals_table(),
                [ 'status' => 'overdue' ],
                [ 'id' => $rental->id ]
            );

            Kogu_Rental_Manager::generate_late_fees( $rental->id );

            if ( ! $rental->overdue_notified ) {
                Kogu_Email_Handler::send_overdue_notification( $rental->id );
                $wpdb->update(
                    Kogu_Database::rentals_table(),
                    [ 'overdue_notified' => 1 ],
                    [ 'id' => $rental->id ]
                );
            }
        }
    }

    // ── すでに延滞中の注文：毎日延滞料金を追加 ───────────────────────────────
    private static function accrue_late_fees() {
        global $wpdb;

        $overdue_rentals = $wpdb->get_results(
            'SELECT * FROM ' . Kogu_Database::rentals_table() .
            " WHERE status = 'overdue'"
        );

        foreach ( $overdue_rentals as $rental ) {
            Kogu_Rental_Manager::generate_late_fees( $rental->id );
            Kogu_Email_Handler::send_overdue_daily_update( $rental->id );
        }
    }
}
