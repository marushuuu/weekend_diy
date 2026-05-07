<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Public {

    public static function init() {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

        // ── ショートコード ──────────────────────────────────────────────────
        add_shortcode( 'kogu_rental_form', [ __CLASS__, 'shortcode_rental_form' ] );
        add_shortcode( 'kogu_mypage',      [ __CLASS__, 'shortcode_mypage' ] );

        // ── AJAX（非ログインユーザー含む） ──────────────────────────────────
        add_action( 'wp_ajax_kogu_availability',         [ __CLASS__, 'ajax_availability' ] );
        add_action( 'wp_ajax_nopriv_kogu_availability',  [ __CLASS__, 'ajax_availability' ] );

        add_action( 'wp_ajax_kogu_create_intent',        [ __CLASS__, 'ajax_create_intent' ] );
        add_action( 'wp_ajax_nopriv_kogu_create_intent', [ __CLASS__, 'ajax_create_intent' ] );

        add_action( 'wp_ajax_kogu_confirm_rental',        [ __CLASS__, 'ajax_confirm_rental' ] );
        add_action( 'wp_ajax_nopriv_kogu_confirm_rental', [ __CLASS__, 'ajax_confirm_rental' ] );

        add_action( 'wp_ajax_kogu_submit_return',        [ __CLASS__, 'ajax_submit_return' ] );
        add_action( 'wp_ajax_nopriv_kogu_submit_return', [ __CLASS__, 'ajax_submit_return' ] );

        // ── Stripe Webhook ───────────────────────────────────────────────────
        add_action( 'rest_api_init', [ __CLASS__, 'register_webhook_route' ] );

        // ── 会員登録完了メール ───────────────────────────────────────────────
        add_action( 'user_register', [ 'Kogu_Email_Handler', 'send_registration_complete' ] );
    }

    public static function enqueue_assets() {
        $v = KOGU_VERSION;
        wp_enqueue_style(  'kogu-rental', KOGU_PLUGIN_URL . 'assets/css/kogu-rental.css', [], $v );
        wp_enqueue_script( 'kogu-rental', KOGU_PLUGIN_URL . 'assets/js/kogu-rental.js',  [ 'jquery' ], $v, true );

        // Stripe.js
        wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', [], null, true );

        // 商品データを JS へ渡す
        $products_raw = Kogu_Database::get_active_products();
        $products_js  = [];
        foreach ( $products_raw as $p ) {
            $products_js[] = [
                'id'             => (int) $p->id,
                'name'           => $p->name,
                'description'    => $p->description,
                'price_per_week' => (int) $p->price_per_week,
                'deposit_amount' => (int) $p->deposit_amount,
            ];
        }

        wp_localize_script( 'kogu-rental', 'KoguData', [
            'ajax_url'           => admin_url( 'admin-ajax.php' ),
            'nonce'              => wp_create_nonce( 'kogu_nonce' ),
            'stripe_public_key'  => get_option( 'kogu_stripe_public_key', '' ),
            'products'           => $products_js,
            'week_discount_rate' => 0.70,
            'late_fee_per_day'   => 500,
        ] );
    }

    // ── ショートコード: レンタル申込フォーム ─────────────────────────────────
    public static function shortcode_rental_form() {
        ob_start();
        include KOGU_PLUGIN_DIR . 'templates/rental-form.php';
        return ob_get_clean();
    }

    // ── ショートコード: マイページ ────────────────────────────────────────────
    public static function shortcode_mypage() {
        ob_start();
        include KOGU_PLUGIN_DIR . 'templates/mypage.php';
        return ob_get_clean();
    }

    // ── AJAX: 在庫マップ（全商品90日分） ─────────────────────────────────────
    public static function ajax_availability() {
        $map = Kogu_Inventory::availability_map( 90 );
        wp_send_json_success( $map );
    }

    // ── AJAX: Stripe PaymentIntent 作成 ──────────────────────────────────────
    public static function ajax_create_intent() {
        check_ajax_referer( 'kogu_nonce', 'nonce' );

        $product_id = (int) ( $_POST['product_id'] ?? 0 );
        $start      = sanitize_text_field( $_POST['start_date'] ?? '' );
        $weeks      = max( 1, (int) ( $_POST['weeks'] ?? 1 ) );
        $email      = sanitize_email( $_POST['email'] ?? '' );
        $name       = sanitize_text_field( $_POST['name'] ?? '' );

        if ( ! $product_id || ! $start || ! $email ) {
            wp_send_json_error( '必須項目が不足しています。' );
        }

        $product = Kogu_Database::get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( '商品が見つかりません。' );
        }

        $end = Kogu_Rental_Manager::calc_end_date( $start, $weeks );

        if ( Kogu_Inventory::available_count( $start, $end, $product_id ) < 1 ) {
            wp_send_json_error( '選択した期間は在庫がありません。' );
        }

        $rental_fee = Kogu_Rental_Manager::calc_rental_fee( $weeks, (int) $product->price_per_week );
        $deposit    = (int) $product->deposit_amount;
        $total      = $rental_fee + $deposit;

        $customer_id = Kogu_Stripe_Handler::get_or_create_customer( $email, $name );
        $result      = Kogu_Stripe_Handler::create_payment_intent( $total, $customer_id, [
            'product_id'    => (string) $product_id,
            'rental_start'  => $start,
            'rental_end'    => $end,
            'rental_weeks'  => (string) $weeks,
            'email'         => $email,
        ] );

        wp_send_json_success( array_merge( $result, [
            'rental_fee'    => $rental_fee,
            'deposit'       => $deposit,
            'total'         => $total,
            'customer_id'   => $customer_id,
        ] ) );
    }

    // ── AJAX: 決済完了後にレンタルを確定 ─────────────────────────────────────
    public static function ajax_confirm_rental() {
        check_ajax_referer( 'kogu_nonce', 'nonce' );

        $product_id  = (int) ( $_POST['product_id']        ?? 0 );
        $pi_id       = sanitize_text_field( $_POST['payment_intent_id'] ?? '' );
        $customer_id = sanitize_text_field( $_POST['customer_id']       ?? '' );
        $start       = sanitize_text_field( $_POST['start_date']        ?? '' );
        $weeks       = max( 1, (int) ( $_POST['weeks'] ?? 1 ) );
        $name        = sanitize_text_field( $_POST['name']              ?? '' );
        $email       = sanitize_email( $_POST['email']                  ?? '' );
        $phone       = sanitize_text_field( $_POST['phone']             ?? '' );
        $postal      = sanitize_text_field( $_POST['postal_code']       ?? '' );
        $address     = sanitize_textarea_field( $_POST['address']       ?? '' );

        if ( ! $pi_id || ! $start || ! $product_id ) {
            wp_send_json_error( 'パラメータ不足。' );
        }

        $product = Kogu_Database::get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( '商品が見つかりません。' );
        }

        $pm_id      = Kogu_Stripe_Handler::get_payment_method_from_intent( $pi_id );
        $rental_fee = Kogu_Rental_Manager::calc_rental_fee( $weeks, (int) $product->price_per_week );
        $deposit    = (int) $product->deposit_amount;

        $rental_id = Kogu_Rental_Manager::create( [
            'product_id'               => $product_id,
            'user_id'                  => get_current_user_id() ?: null,
            'guest_name'               => $name,
            'guest_email'              => $email,
            'guest_phone'              => $phone,
            'guest_postal_code'        => $postal,
            'guest_address'            => $address,
            'rental_start_date'        => $start,
            'rental_weeks'             => $weeks,
            'rental_fee'               => $rental_fee,
            'deposit_amount'           => $deposit,
            'stripe_payment_intent_id' => $pi_id,
            'stripe_customer_id'       => $customer_id,
            'stripe_payment_method_id' => $pm_id,
        ] );

        if ( ! $rental_id ) {
            wp_send_json_error( '在庫がなくなりました。再度ご確認ください。' );
        }

        wp_send_json_success( [ 'rental_id' => $rental_id ] );
    }

    // ── AJAX: 返却証跡（追跡番号）提出 ───────────────────────────────────────
    public static function ajax_submit_return() {
        check_ajax_referer( 'kogu_nonce', 'nonce' );

        $rental_id = (int) ( $_POST['rental_id'] ?? 0 );
        $tracking  = sanitize_text_field( $_POST['tracking'] ?? '' );
        $email     = sanitize_email( $_POST['email']         ?? '' );

        if ( ! $rental_id || ! $tracking ) {
            wp_send_json_error( '必須項目が不足しています。' );
        }

        $rental = Kogu_Rental_Manager::get( $rental_id );
        if ( ! $rental ) {
            wp_send_json_error( 'レンタルが見つかりません。' );
        }

        if ( ! $rental->user_id && $rental->guest_email !== $email ) {
            wp_send_json_error( 'メールアドレスが一致しません。' );
        }

        if ( $rental->user_id && $rental->user_id != get_current_user_id() ) {
            wp_send_json_error( '権限がありません。' );
        }

        $result = Kogu_Rental_Manager::submit_return_evidence( $rental_id, $tracking );

        if ( $result ) {
            wp_send_json_success( '返却証跡を提出しました。' );
        } else {
            wp_send_json_error( '処理に失敗しました。' );
        }
    }

    // ── Stripe Webhook エンドポイント ─────────────────────────────────────────
    public static function register_webhook_route() {
        register_rest_route( 'kogu/v1', '/stripe-webhook', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_stripe_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function handle_stripe_webhook( WP_REST_Request $request ) {
        $payload    = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            $event = Kogu_Stripe_Handler::construct_webhook_event( $payload, $sig_header );
        } catch ( Exception $e ) {
            return new WP_REST_Response( 'Webhook signature verification failed.', 400 );
        }

        if ( $event->type === 'payment_intent.succeeded' ) {
            // 必要に応じてステータス更新処理を追加
        }

        return new WP_REST_Response( 'ok', 200 );
    }
}
