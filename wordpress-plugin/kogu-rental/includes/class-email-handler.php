<?php
defined( 'ABSPATH' ) || exit;

/**
 * メール送信（SendGrid API経由）
 *
 * APIキーはWordPress管理画面「設定 > 工具レンタル設定」で入力。
 * 送信元メールアドレスも同設定画面で指定。
 */
class Kogu_Email_Handler {

    private static function get_rental_email( $rental ) {
        if ( $rental->user_id ) {
            $user = get_userdata( $rental->user_id );
            return $user ? $user->user_email : '';
        }
        return $rental->guest_email;
    }

    private static function get_rental_name( $rental ) {
        if ( $rental->user_id ) {
            $user = get_userdata( $rental->user_id );
            return $user ? $user->display_name : '';
        }
        return $rental->guest_name;
    }

    // ── 共通送信処理（SendGrid API） ─────────────────────────────────────────
    private static function send( $to_email, $to_name, $subject, $html_body ) {
        $api_key    = get_option( 'kogu_sendgrid_api_key', '' );
        $from_email = get_option( 'kogu_from_email', get_option( 'admin_email' ) );
        $from_name  = get_option( 'kogu_from_name', get_bloginfo( 'name' ) );

        if ( ! $api_key ) {
            // SendGrid未設定時はWordPressのwp_mail()で代替
            wp_mail( $to_email, $subject, wp_strip_all_tags( $html_body ) );
            return;
        }

        $body = wp_json_encode( [
            'personalizations' => [ [
                'to' => [ [ 'email' => $to_email, 'name' => $to_name ] ],
            ] ],
            'from'    => [ 'email' => $from_email, 'name' => $from_name ],
            'subject' => $subject,
            'content' => [ [ 'type' => 'text/html', 'value' => $html_body ] ],
        ] );

        wp_remote_post( 'https://api.sendgrid.com/v3/mail/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 15,
        ] );
    }

    private static function wrap( $content ) {
        $site = get_bloginfo( 'name' );
        return "
        <div style='font-family:\"Hiragino Kaku Gothic ProN\",Meiryo,sans-serif;max-width:600px;margin:0 auto;padding:24px;'>
          <div style='background:#1f1d1a;color:#fff;padding:16px 24px;border-radius:8px 8px 0 0;'>
            <strong style='font-size:18px;'>🔧 {$site}</strong>
          </div>
          <div style='background:#fff;border:1px solid #e0d8c8;border-top:0;padding:28px 24px;border-radius:0 0 8px 8px;line-height:1.8;color:#1f1d1a;'>
            {$content}
          </div>
          <p style='text-align:center;font-size:11px;color:#888;margin-top:16px;'>
            このメールは{$site}から自動送信されています。
          </p>
        </div>";
    }

    // ── 会員登録完了 ──────────────────────────────────────────────────────────
    public static function send_registration_complete( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;
        $content = "
            <h2 style='color:#e85a2b;'>会員登録が完了しました</h2>
            <p>{$user->display_name} 様</p>
            <p>みんなの工具レンタルへのご登録ありがとうございます。<br>
            以下のメールアドレスでご利用いただけます。</p>
            <p><strong>{$user->user_email}</strong></p>
            <p>さっそく工具を探してみましょう！</p>
            <p><a href='" . home_url() . "' style='background:#e85a2b;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;'>工具を探す</a></p>";
        self::send( $user->user_email, $user->display_name, '【工具レンタル】会員登録完了のお知らせ', self::wrap( $content ) );
    }

    // ── 予約確認 ──────────────────────────────────────────────────────────────
    public static function send_booking_confirmation( $rental_id ) {
        $rental = Kogu_Rental_Manager::get( $rental_id );
        if ( ! $rental ) return;

        $email   = self::get_rental_email( $rental );
        $name    = self::get_rental_name( $rental );
        $fee     = number_format( $rental->rental_fee );
        $deposit = number_format( $rental->deposit_amount );
        $total   = number_format( $rental->total_charged );

        $content = "
            <h2 style='color:#e85a2b;'>ご予約を承りました</h2>
            <p>{$name} 様</p>
            <p>インパクトドライバーのレンタルをご予約いただきありがとうございます。</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>レンタル番号</td><td style='padding:8px 12px;'>#{$rental_id}</td></tr>
              <tr><td style='padding:8px 12px;font-weight:bold;'>貸出開始日</td><td style='padding:8px 12px;'>{$rental->rental_start_date}</td></tr>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>返却期限日</td><td style='padding:8px 12px;'><strong style='color:#e85a2b;'>{$rental->rental_end_date}</strong>（この日までに発送してください）</td></tr>
              <tr><td style='padding:8px 12px;font-weight:bold;'>レンタル料金</td><td style='padding:8px 12px;'>¥{$fee}</td></tr>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>デポジット</td><td style='padding:8px 12px;'>¥{$deposit}（返却確認後に返金）</td></tr>
              <tr style='border-top:2px solid #1f1d1a;'><td style='padding:8px 12px;font-weight:bold;'>合計請求額</td><td style='padding:8px 12px;font-size:18px;font-weight:bold;'>¥{$total}</td></tr>
            </table>
            <p>商品は準備が整い次第、ゆうパックにてお届けします。追跡番号が確定しましたら別途ご連絡いたします。</p>
            <p>ご不明な点はお問い合わせください。</p>";
        self::send( $email, $name, '【工具レンタル】ご予約確認 #' . $rental_id, self::wrap( $content ) );
    }

    // ── 発送通知 ──────────────────────────────────────────────────────────────
    public static function send_shipped_notification( $rental_id, $tracking_number ) {
        $rental = Kogu_Rental_Manager::get( $rental_id );
        if ( ! $rental ) return;

        $email = self::get_rental_email( $rental );
        $name  = self::get_rental_name( $rental );

        $content = "
            <h2 style='color:#e85a2b;'>商品を発送しました</h2>
            <p>{$name} 様</p>
            <p>インパクトドライバーを発送しましたのでお知らせします。</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>追跡番号（ゆうパック）</td><td style='padding:8px 12px;font-size:18px;font-weight:bold;'>{$tracking_number}</td></tr>
              <tr><td style='padding:8px 12px;font-weight:bold;'>返却期限日</td><td style='padding:8px 12px;'><strong style='color:#e85a2b;'>{$rental->rental_end_date}</strong></td></tr>
            </table>
            <p><a href='https://www.post.japanpost.jp/cgi-yubin/navi/DispList.do?number={$tracking_number}' style='background:#e85a2b;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;'>ゆうパック追跡を確認する</a></p>
            <h3 style='margin-top:24px;'>返却方法</h3>
            <ol>
              <li><strong>{$rental->rental_end_date}まで</strong>に最寄りの郵便局またはコンビニから着払いで発送してください。</li>
              <li>発送後、マイページから追跡番号を入力して返却証跡を提出してください。</li>
              <li>返却期限を過ぎた場合、1日あたり¥500の延滞料金がデポジットから差し引かれます。</li>
            </ol>";
        self::send( $email, $name, '【工具レンタル】商品発送のお知らせ #' . $rental_id, self::wrap( $content ) );
    }

    // ── 返却期限1日前リマインダー ─────────────────────────────────────────────
    public static function send_return_reminder( $rental_id ) {
        $rental = Kogu_Rental_Manager::get( $rental_id );
        if ( ! $rental ) return;

        $email = self::get_rental_email( $rental );
        $name  = self::get_rental_name( $rental );

        $content = "
            <h2 style='color:#e85a2b;'>⚠️ 返却期限は明日です</h2>
            <p>{$name} 様</p>
            <p>レンタル中のインパクトドライバーの返却期限が<strong>明日（{$rental->rental_end_date}）</strong>に迫っています。</p>
            <p><strong>返却期限日までに発送</strong>してください。<br>
            期限を過ぎると1日あたり¥500の延滞料金がデポジットから差し引かれます。</p>
            <h3>返却手順</h3>
            <ol>
              <li>商品を梱包してください（付属品を必ず同梱）。</li>
              <li>最寄りの郵便局またはコンビニから<strong>着払い</strong>で発送してください。</li>
              <li>マイページから追跡番号を入力して完了です。</li>
            </ol>
            <p><a href='" . home_url( '/my-page' ) . "' style='background:#e85a2b;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;'>マイページで返却手続きをする</a></p>";
        self::send( $email, $name, '【工具レンタル】⚠️ 返却期限前日のお知らせ #' . $rental_id, self::wrap( $content ) );
    }

    // ── 延滞通知 ──────────────────────────────────────────────────────────────
    public static function send_overdue_notification( $rental_id ) {
        $rental = Kogu_Rental_Manager::get( $rental_id );
        if ( ! $rental ) return;

        $email       = self::get_rental_email( $rental );
        $name        = self::get_rental_name( $rental );
        $late_days   = $rental->late_fee_days;
        $late_total  = number_format( $rental->late_fee_total );
        $deposit     = number_format( $rental->deposit_amount );

        $content = "
            <h2 style='color:#c0392b;'>⛔ 返却期限を過ぎています</h2>
            <p>{$name} 様</p>
            <p>レンタル中のインパクトドライバーの返却期限（{$rental->rental_end_date}）を過ぎています。</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
              <tr style='background:#ffeaea;'><td style='padding:8px 12px;font-weight:bold;'>延滞日数</td><td style='padding:8px 12px;color:#c0392b;font-weight:bold;'>{$late_days}日</td></tr>
              <tr><td style='padding:8px 12px;font-weight:bold;'>延滞料金（累計）</td><td style='padding:8px 12px;font-size:18px;font-weight:bold;color:#c0392b;'>¥{$late_total}</td></tr>
              <tr style='background:#ffeaea;'><td style='padding:8px 12px;font-weight:bold;'>デポジット</td><td style='padding:8px 12px;'>¥{$deposit}</td></tr>
            </table>
            <p>延滞料金はデポジットから差し引かれます。デポジットを超える場合は、登録カードに追加請求いたします。</p>
            <p><strong>至急、返送手続きをお願いいたします。</strong></p>
            <p><a href='" . home_url( '/my-page' ) . "' style='background:#c0392b;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;'>今すぐ返却手続きをする</a></p>";
        self::send( $email, $name, '【工具レンタル】⛔ 返却期限超過のお知らせ #' . $rental_id, self::wrap( $content ) );
    }

    // ── 返却受領通知 ──────────────────────────────────────────────────────────
    public static function send_return_received_notification( $rental_id ) {
        $rental = Kogu_Rental_Manager::get( $rental_id );
        if ( ! $rental ) return;
        $email = self::get_rental_email( $rental );
        $name  = self::get_rental_name( $rental );
        $content = "
            <h2 style='color:#27ae60;'>返却証跡を受け付けました</h2>
            <p>{$name} 様</p>
            <p>返却のお手続きありがとうございます。追跡番号を確認しました。</p>
            <p>商品到着後に状態を確認し、デポジットの返金処理を行います（通常3〜5営業日）。</p>
            <p>追跡番号: <strong>{$rental->tracking_return}</strong></p>";
        self::send( $email, $name, '【工具レンタル】返却受付完了 #' . $rental_id, self::wrap( $content ) );
    }

    // ── 管理者：新規予約通知 ──────────────────────────────────────────────────
    public static function send_admin_new_booking( $rental_id ) {
        $rental     = Kogu_Rental_Manager::get( $rental_id );
        if ( ! $rental ) return;

        $admin_email = get_option( 'admin_email' );
        $name        = $rental->user_id ? get_userdata( $rental->user_id )->display_name : $rental->guest_name;
        $fee         = number_format( $rental->rental_fee );
        $deposit     = number_format( $rental->deposit_amount );
        $detail_url  = admin_url( 'admin.php?page=kogu-rentals&detail=' . $rental_id );

        $content = "
            <h2 style='color:#e85a2b;'>新規レンタル予約が入りました</h2>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>レンタル番号</td><td style='padding:8px 12px;'>#{$rental_id}</td></tr>
              <tr><td style='padding:8px 12px;font-weight:bold;'>お客様</td><td style='padding:8px 12px;'>{$name}（{$rental->guest_email}）</td></tr>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>電話</td><td style='padding:8px 12px;'>{$rental->guest_phone}</td></tr>
              <tr><td style='padding:8px 12px;font-weight:bold;'>住所</td><td style='padding:8px 12px;'>{$rental->guest_postal_code} {$rental->guest_address}</td></tr>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>貸出開始</td><td style='padding:8px 12px;'>{$rental->rental_start_date}</td></tr>
              <tr><td style='padding:8px 12px;font-weight:bold;'>返却期限</td><td style='padding:8px 12px;font-weight:bold;color:#e85a2b;'>{$rental->rental_end_date}</td></tr>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>レンタル料金</td><td style='padding:8px 12px;'>¥{$fee}</td></tr>
              <tr><td style='padding:8px 12px;font-weight:bold;'>デポジット</td><td style='padding:8px 12px;'>¥{$deposit}</td></tr>
            </table>
            <p><a href='{$detail_url}' style='background:#e85a2b;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;'>管理画面で確認・発送処理をする</a></p>";

        self::send( $admin_email, get_bloginfo( 'name' ), "【要対応】新規レンタル予約 #{$rental_id}", self::wrap( $content ) );
    }

    // ── 管理者：返却証跡が提出された通知 ─────────────────────────────────────
    public static function send_admin_return_submitted( $rental_id ) {
        $rental     = Kogu_Rental_Manager::get( $rental_id );
        if ( ! $rental ) return;

        $admin_email = get_option( 'admin_email' );
        $name        = $rental->user_id ? get_userdata( $rental->user_id )->display_name : $rental->guest_name;
        $detail_url  = admin_url( 'admin.php?page=kogu-rentals&detail=' . $rental_id );
        $on_time     = $rental->actual_return_date <= $rental->rental_end_date ? '✅ 期限内' : '⚠️ 期限超過';

        $content = "
            <h2 style='color:#e85a2b;'>返却証跡が提出されました</h2>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>レンタル番号</td><td style='padding:8px 12px;'>#{$rental_id}</td></tr>
              <tr><td style='padding:8px 12px;font-weight:bold;'>お客様</td><td style='padding:8px 12px;'>{$name}</td></tr>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>返送追跡番号</td><td style='padding:8px 12px;font-size:16px;font-weight:bold;'>{$rental->tracking_return}</td></tr>
              <tr><td style='padding:8px 12px;font-weight:bold;'>証跡提出日</td><td style='padding:8px 12px;'>{$rental->actual_return_date}</td></tr>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>返却期限</td><td style='padding:8px 12px;'>{$rental->rental_end_date}</td></tr>
              <tr><td style='padding:8px 12px;font-weight:bold;'>期限判定</td><td style='padding:8px 12px;font-weight:bold;'>{$on_time}</td></tr>
            </table>
            <p>商品到着後、状態を確認して返却完了処理を行ってください。</p>
            <p><a href='{$detail_url}' style='background:#e85a2b;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;'>管理画面で返却確認・精算をする</a></p>";

        self::send( $admin_email, get_bloginfo( 'name' ), "【要対応】返却証跡提出 #{$rental_id}", self::wrap( $content ) );
    }

    // ── 返却完了・精算通知 ────────────────────────────────────────────────────
    public static function send_return_complete( $rental_id, $refund, $late_fee, $damage_fee ) {
        $rental = Kogu_Rental_Manager::get( $rental_id );
        if ( ! $rental ) return;
        $email   = self::get_rental_email( $rental );
        $name    = self::get_rental_name( $rental );
        $deposit = number_format( $rental->deposit_amount );
        $r_fmt   = number_format( $refund );
        $l_fmt   = number_format( $late_fee );
        $d_fmt   = number_format( $damage_fee );
        $content = "
            <h2 style='color:#27ae60;'>返却が完了しました</h2>
            <p>{$name} 様</p>
            <p>インパクトドライバーの返却を確認しました。ありがとうございました。</p>
            <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
              <tr style='background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>デポジット</td><td style='padding:8px 12px;'>¥{$deposit}</td></tr>
              <tr><td style='padding:8px 12px;'>延滞料金</td><td style='padding:8px 12px;'>- ¥{$l_fmt}</td></tr>
              <tr><td style='padding:8px 12px;'>損害費用</td><td style='padding:8px 12px;'>- ¥{$d_fmt}</td></tr>
              <tr style='border-top:2px solid #1f1d1a;background:#f6f1e6;'><td style='padding:8px 12px;font-weight:bold;'>返金額</td><td style='padding:8px 12px;font-size:18px;font-weight:bold;color:#27ae60;'>¥{$r_fmt}</td></tr>
            </table>
            <p>返金はStripeを通じてお支払いカードに反映されます（3〜7営業日）。</p>
            <p>またのご利用をお待ちしております。</p>";
        self::send( $email, $name, '【工具レンタル】返却完了・精算のお知らせ #' . $rental_id, self::wrap( $content ) );
    }
}
