<?php
defined( 'ABSPATH' ) || exit;

/**
 * Stripe連携
 *
 * 必要なライブラリ:  composer require stripe/stripe-php
 * APIキーはWordPress管理画面「設定 > 工具レンタル設定」で入力。
 */
class Kogu_Stripe_Handler {

    private static function init() {
        if ( ! class_exists( '\Stripe\Stripe' ) ) {
            $autoload = KOGU_PLUGIN_DIR . 'vendor/autoload.php';
            if ( ! file_exists( $autoload ) ) {
                throw new \RuntimeException( 'Stripe ライブラリが見つかりません。プラグインディレクトリで composer install を実行してください。' );
            }
            require_once $autoload;
        }
        $secret_key = get_option( 'kogu_stripe_secret_key', '' );
        if ( empty( $secret_key ) ) {
            throw new \RuntimeException( 'Stripe 秘密鍵が設定されていません。管理画面の「工具レンタル設定」で入力してください。' );
        }
        \Stripe\Stripe::setApiKey( $secret_key );
    }

    // ── PaymentIntent 作成（レンタル料金 + デポジット） ──────────────────────
    /**
     * @param int    $amount_jpy  合計金額（レンタル料金 + デポジット）
     * @param string $customer_id Stripe Customer ID（既存顧客の場合）
     * @param array  $metadata
     * @return array { client_secret, payment_intent_id }
     */
    public static function create_payment_intent( $amount_jpy, $customer_id = '', $metadata = [] ) {
        self::init();

        $params = [
            'amount'               => $amount_jpy,
            'currency'             => 'jpy',
            'payment_method_types' => [ 'card' ],
            'metadata'             => array_merge( $metadata, [ 'site' => get_bloginfo( 'url' ) ] ),
            // setup_future_usage: 後から延滞料金等を請求できるようにPMを保存
            'setup_future_usage'   => 'off_session',
        ];

        if ( $customer_id ) {
            $params['customer'] = $customer_id;
        }

        $intent = \Stripe\PaymentIntent::create( $params );

        return [
            'client_secret'      => $intent->client_secret,
            'payment_intent_id'  => $intent->id,
        ];
    }

    // ── Stripeカスタマーを作成または取得 ─────────────────────────────────────
    public static function get_or_create_customer( $email, $name ) {
        self::init();

        $existing = \Stripe\Customer::search( [
            'query' => "email:\"$email\"",
            'limit' => 1,
        ] );

        if ( ! empty( $existing->data ) ) {
            return $existing->data[0]->id;
        }

        $customer = \Stripe\Customer::create( [
            'email' => $email,
            'name'  => $name,
        ] );

        return $customer->id;
    }

    // ── PaymentIntentからPaymentMethodを取得して保存 ──────────────────────────
    public static function get_payment_method_from_intent( $payment_intent_id ) {
        self::init();
        $intent = \Stripe\PaymentIntent::retrieve( $payment_intent_id );
        return $intent->payment_method ?? '';
    }

    // ── デポジット（一部）返金 ────────────────────────────────────────────────
    /**
     * @param string $payment_intent_id
     * @param int    $refund_amount_jpy
     */
    public static function refund_deposit( $payment_intent_id, $refund_amount_jpy ) {
        self::init();

        if ( $refund_amount_jpy <= 0 ) return;

        try {
            \Stripe\Refund::create( [
                'payment_intent' => $payment_intent_id,
                'amount'         => $refund_amount_jpy,
                'reason'         => 'requested_by_customer',
                'metadata'       => [ 'note' => 'デポジット返金（延滞・損害控除後）' ],
            ] );
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            error_log( 'Kogu Stripe refund error: ' . $e->getMessage() );
        }
    }

    // ── デポジット超過分の追加請求 ────────────────────────────────────────────
    /**
     * @param string $customer_id        Stripe Customer ID
     * @param string $payment_method_id  保存済みカード
     * @param int    $amount_jpy
     * @param string $description
     */
    public static function charge_additional( $customer_id, $payment_method_id, $amount_jpy, $description ) {
        self::init();

        if ( $amount_jpy <= 0 ) return;

        try {
            $intent = \Stripe\PaymentIntent::create( [
                'amount'               => $amount_jpy,
                'currency'             => 'jpy',
                'customer'             => $customer_id,
                'payment_method'       => $payment_method_id,
                'confirm'              => true,
                'off_session'          => true,
                'description'          => $description,
                'payment_method_types' => [ 'card' ],
            ] );
            return $intent->id;
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            error_log( 'Kogu Stripe additional charge error: ' . $e->getMessage() );
            return false;
        }
    }

    // ── Webhookの検証とイベント取得 ───────────────────────────────────────────
    public static function construct_webhook_event( $payload, $sig_header ) {
        self::init();
        $secret = get_option( 'kogu_stripe_webhook_secret', '' );
        return \Stripe\Webhook::constructEvent( $payload, $sig_header, $secret );
    }
}
