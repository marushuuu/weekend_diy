<?php
/**
 * Stripe Webhook Standalone Handler
 * Deploy to: public_html/stripe-webhook.php
 * Stripe webhook URL: https://weekend-diy.com/stripe-webhook.php
 */

// WordPress本体を読み込む（プラグインも自動でロードされる）
require_once __DIR__ . '/../../../wp-load.php';

$payload    = file_get_contents( 'php://input' );
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ( ! $payload || ! $sig_header ) {
    http_response_code( 400 );
    exit( 'Bad Request' );
}

try {
    $event = Kogu_Stripe_Handler::construct_webhook_event( $payload, $sig_header );
} catch ( Exception $e ) {
    http_response_code( 400 );
    exit( 'Webhook signature verification failed.' );
}

if ( $event->type === 'payment_intent.succeeded' ) {
    // 現在はAJAXで確定処理済みのため追加処理なし
}

http_response_code( 200 );
echo 'ok';
