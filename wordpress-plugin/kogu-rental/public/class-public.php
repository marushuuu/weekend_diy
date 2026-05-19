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

        // ── noindex メタタグ ─────────────────────────────────────────────────
        add_action( 'wp_head', [ __CLASS__, 'output_noindex_meta' ] );
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
                'allows_addons'  => (bool) $p->allows_addons,
            ];
        }

        // 購入オプション商品データを JS へ渡す
        $addons_raw = Kogu_Database::get_active_addon_products();
        $addons_js  = [];
        foreach ( $addons_raw as $a ) {
            $addons_js[] = [
                'id'            => (int) $a->id,
                'name'          => $a->name,
                'description'   => $a->description,
                'price'         => (int) $a->price,
                'unit'          => $a->unit,
                'stock'         => $a->stock_quantity !== null ? (int) $a->stock_quantity : null,
            ];
        }

        wp_localize_script( 'kogu-rental', 'KoguData', [
            'ajax_url'           => admin_url( 'admin-ajax.php' ),
            'nonce'              => wp_create_nonce( 'kogu_nonce' ),
            'stripe_public_key'  => get_option( 'kogu_stripe_public_key', '' ),
            'products'           => $products_js,
            'addon_products'     => $addons_js,
            'week_discount_rate'       => 0.70,
            'late_fee_per_day'         => 500,
            'rental_page_url'          => home_url( '/rental/' ),
            'shipping_fee'             => 2500,
            'free_shipping_threshold'  => 3500,
        ] );
    }

    // ── ショートコード: レンタル申込フォーム ─────────────────────────────────
    public static function shortcode_rental_form( $atts ) {
        $atts = shortcode_atts( [ 'show_step_titles' => 'true', 'mode' => 'full' ], $atts );
        $show_step_titles = $atts['show_step_titles'] !== 'false';
        $rental_page_url  = home_url( '/rental/' );
        $is_top_mode      = $atts['mode'] === 'top';
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

        $start = sanitize_text_field( $_POST['start_date'] ?? '' );
        $weeks = max( 1, (int) ( $_POST['weeks'] ?? 1 ) );
        $email = sanitize_email( $_POST['email'] ?? '' );
        $name  = sanitize_text_field( $_POST['name'] ?? '' );

        // Accept product_ids array or fall back to single product_id
        $pids_raw    = json_decode( stripslashes( $_POST['product_ids'] ?? '[]' ), true ) ?: [];
        if ( empty( $pids_raw ) && ! empty( $_POST['product_id'] ) ) {
            $pids_raw = [ (int) $_POST['product_id'] ];
        }
        $product_ids = array_values( array_filter( array_map( 'intval', $pids_raw ) ) );

        if ( empty( $product_ids ) || ! $start || ! $email ) {
            wp_send_json_error( '必須項目が不足しています。' );
        }

        $end        = Kogu_Rental_Manager::calc_end_date( $start, $weeks );
        $rental_fee = 0;

        foreach ( $product_ids as $pid ) {
            $product = Kogu_Database::get_product( $pid );
            if ( ! $product ) {
                wp_send_json_error( '商品が見つかりません。' );
            }
            if ( Kogu_Inventory::available_count( $start, $end, $pid ) < 1 ) {
                wp_send_json_error( '選択した期間は在庫がありません。' );
            }
            $rental_fee += Kogu_Rental_Manager::calc_rental_fee( $weeks, (int) $product->price_per_week );
        }

        $addon_total = 0;
        $addons_raw  = json_decode( stripslashes( $_POST['addons'] ?? '[]' ), true ) ?: [];
        foreach ( $addons_raw as $a ) {
            $ap  = Kogu_Database::get_addon_product( (int) ( $a['id'] ?? 0 ) );
            $qty = (int) ( $a['qty'] ?? 0 );
            if ( ! $ap || $qty < 1 ) continue;
            if ( $ap->stock_quantity !== null && (int) $ap->stock_quantity < $qty ) {
                wp_send_json_error( esc_html( $ap->name ) . ' の在庫が不足しています（残り' . (int) $ap->stock_quantity . '個）。' );
            }
            $addon_total += (int) $ap->price * $qty;
        }
        $subtotal     = $rental_fee + $addon_total;
        $shipping_fee = $subtotal < 3500 ? 2500 : 0;
        $total        = $subtotal + $shipping_fee;

        $customer_id = Kogu_Stripe_Handler::get_or_create_customer( $email, $name );
        $result      = Kogu_Stripe_Handler::create_payment_intent( $total, $customer_id, [
            'product_ids'  => implode( ',', $product_ids ),
            'rental_start' => $start,
            'rental_end'   => $end,
            'rental_weeks' => (string) $weeks,
            'email'        => $email,
        ] );

        wp_send_json_success( array_merge( $result, [
            'rental_fee'   => $rental_fee,
            'addon_total'  => $addon_total,
            'shipping_fee' => $shipping_fee,
            'deposit'      => 0,
            'total'        => $total,
            'customer_id'  => $customer_id,
        ] ) );
    }

    // ── AJAX: 決済完了後にレンタルを確定 ─────────────────────────────────────
    public static function ajax_confirm_rental() {
        check_ajax_referer( 'kogu_nonce', 'nonce' );

        // Accept product_ids array or fall back to single product_id
        $pids_raw    = json_decode( stripslashes( $_POST['product_ids'] ?? '[]' ), true ) ?: [];
        if ( empty( $pids_raw ) && ! empty( $_POST['product_id'] ) ) {
            $pids_raw = [ (int) $_POST['product_id'] ];
        }
        $product_ids = array_values( array_filter( array_map( 'intval', $pids_raw ) ) );

        $pi_id       = sanitize_text_field( $_POST['payment_intent_id'] ?? '' );
        $customer_id = sanitize_text_field( $_POST['customer_id']       ?? '' );
        $start       = sanitize_text_field( $_POST['start_date']        ?? '' );
        $weeks       = max( 1, (int) ( $_POST['weeks'] ?? 1 ) );
        $name        = sanitize_text_field( $_POST['name']              ?? '' );
        $email       = sanitize_email( $_POST['email']                  ?? '' );
        $phone       = sanitize_text_field( $_POST['phone']             ?? '' );
        $postal      = sanitize_text_field( $_POST['postal_code']       ?? '' );
        $address     = sanitize_textarea_field( $_POST['address']       ?? '' );

        if ( ! $pi_id || ! $start || empty( $product_ids ) ) {
            wp_send_json_error( 'パラメータ不足。' );
        }

        $pm_id  = Kogu_Stripe_Handler::get_payment_method_from_intent( $pi_id );
        $addons = json_decode( stripslashes( $_POST['addons'] ?? '[]' ), true ) ?: [];

        // 全商品の合計レンタル料金を計算
        $total_rental_fee = 0;
        foreach ( $product_ids as $pid ) {
            $product = Kogu_Database::get_product( $pid );
            if ( $product ) {
                $total_rental_fee += Kogu_Rental_Manager::calc_rental_fee( $weeks, (int) $product->price_per_week );
            }
        }
        $addon_total = 0;
        foreach ( $addons as $a ) {
            $ap = Kogu_Database::get_addon_product( (int) ( $a['id'] ?? 0 ) );
            if ( $ap && (int) ( $a['qty'] ?? 0 ) > 0 ) {
                $addon_total += (int) $ap->price * (int) $a['qty'];
            }
        }
        $subtotal     = $total_rental_fee + $addon_total;
        $shipping_fee = $subtotal < 3500 ? 2500 : 0;
        $total        = $subtotal + $shipping_fee;

        $rental_ids = [];
        $is_first   = true;

        foreach ( $product_ids as $pid ) {
            $product = Kogu_Database::get_product( $pid );
            if ( ! $product ) continue;

            $rental_fee = Kogu_Rental_Manager::calc_rental_fee( $weeks, (int) $product->price_per_week );

            $rental_id = Kogu_Rental_Manager::create( [
                'product_id'               => $pid,
                'user_id'                  => get_current_user_id() ?: null,
                'guest_name'               => $name,
                'guest_email'              => $email,
                'guest_phone'              => $phone,
                'guest_postal_code'        => $postal,
                'guest_address'            => $address,
                'rental_start_date'        => $start,
                'rental_weeks'             => $weeks,
                'rental_fee'               => $rental_fee,
                'total_charged'            => $is_first ? $total : 0,
                'stripe_payment_intent_id' => $pi_id,
                'stripe_customer_id'       => $customer_id,
                'stripe_payment_method_id' => $pm_id,
            ] );

            if ( ! $rental_id ) {
                wp_send_json_error( '在庫がなくなりました。再度ご確認ください。' );
            }

            $rental_ids[] = $rental_id;
            $is_first     = false;
        }

        // オプション購入を最初のレンタルに紐付け＆在庫減算
        if ( ! empty( $addons ) && ! empty( $rental_ids ) ) {
            Kogu_Database::save_rental_addons( $rental_ids[0], $addons );
            foreach ( $addons as $a ) {
                $qty = (int) ( $a['qty'] ?? 0 );
                if ( $qty > 0 ) {
                    Kogu_Database::deduct_addon_stock( (int) $a['id'], $qty );
                }
            }
        }

        wp_send_json_success( [ 'rental_id' => $rental_ids[0], 'rental_ids' => $rental_ids ] );
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

    // ── noindex メタタグ出力 ──────────────────────────────────────────────────
    public static function output_noindex_meta() {
        $slugs_option = get_option( 'kogu_noindex_slugs', 'tokushoho,my-page,privacy,terms' );
        $slugs = array_filter( array_map( 'trim', explode( ',', $slugs_option ) ) );

        if ( ! empty( $slugs ) && is_page( $slugs ) ) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
    }
}
