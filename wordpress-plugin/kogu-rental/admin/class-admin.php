<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_post_kogu_admin_action', [ __CLASS__, 'handle_admin_action' ] );
        add_action( 'admin_init',            [ __CLASS__, 'register_settings' ] );
    }

    // ── メニュー登録 ──────────────────────────────────────────────────────────
    public static function register_menu() {
        add_menu_page(
            'レンタル管理', 'レンタル管理', 'manage_options',
            'kogu-rentals', [ __CLASS__, 'page_rentals' ],
            'dashicons-hammer', 26
        );
        add_submenu_page( 'kogu-rentals', 'レンタル一覧', 'レンタル一覧', 'manage_options', 'kogu-rentals',          [ __CLASS__, 'page_rentals' ] );
        add_submenu_page( 'kogu-rentals', '延滞一覧',     '⚠️ 延滞一覧', 'manage_options', 'kogu-overdue',          [ __CLASS__, 'page_overdue' ] );
        add_submenu_page( 'kogu-rentals', '在庫管理',     '在庫管理',   'manage_options', 'kogu-inventory',        [ __CLASS__, 'page_inventory' ] );
        add_submenu_page( 'kogu-rentals', '設定',         '設定',       'manage_options', 'kogu-settings',         [ __CLASS__, 'page_settings' ] );
    }

    // ── レンタル一覧 ──────────────────────────────────────────────────────────
    public static function page_rentals() {
        global $wpdb;
        $table = Kogu_Database::rentals_table();

        $status_filter = sanitize_text_field( $_GET['status'] ?? '' );
        $where = $status_filter ? $wpdb->prepare( "WHERE status = %s", $status_filter ) : '';

        $rentals = $wpdb->get_results(
            "SELECT * FROM $table $where ORDER BY created_at DESC LIMIT 200"
        );

        $status_labels = [
            'pending'                    => '確認中',
            'confirmed'                  => '予約確定',
            'shipped_to_customer'        => '発送済み',
            'active'                     => 'レンタル中',
            'return_evidence_submitted'  => '返却証跡あり',
            'returned'                   => '返却完了',
            'overdue'                    => '延滞中',
            'cancelled'                  => 'キャンセル',
        ];
        ?>
        <div class="wrap">
          <h1>レンタル一覧</h1>
          <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
            <a href="?page=kogu-rentals" class="button <?php echo !$status_filter ? 'button-primary' : ''; ?>">すべて</a>
            <?php foreach ( $status_labels as $s => $l ) : ?>
              <a href="?page=kogu-rentals&status=<?php echo esc_attr( $s ); ?>"
                 class="button <?php echo $status_filter === $s ? 'button-primary' : ''; ?>">
                <?php echo esc_html( $l ); ?>
              </a>
            <?php endforeach; ?>
          </div>

          <table class="wp-list-table widefat fixed striped">
            <thead>
              <tr>
                <th style="width:50px">#</th>
                <th>お客様</th>
                <th>期間</th>
                <th>料金</th>
                <th>デポジット</th>
                <th>ステータス</th>
                <th>追跡（往路）</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $rentals as $r ) :
                $label = $status_labels[ $r->status ] ?? $r->status;
                $name  = $r->user_id ? get_userdata( $r->user_id )->display_name : $r->guest_name;
                $email = $r->user_id ? get_userdata( $r->user_id )->user_email   : $r->guest_email;
              ?>
                <tr>
                  <td><?php echo (int) $r->id; ?></td>
                  <td>
                    <strong><?php echo esc_html( $name ); ?></strong><br>
                    <small><?php echo esc_html( $email ); ?></small>
                  </td>
                  <td>
                    <?php echo esc_html( $r->rental_start_date ); ?> 〜<br>
                    <strong style="color:#e85a2b;"><?php echo esc_html( $r->rental_end_date ); ?></strong>
                  </td>
                  <td>¥<?php echo number_format( $r->rental_fee ); ?></td>
                  <td>¥<?php echo number_format( $r->deposit_amount ); ?></td>
                  <td><span class="kogu-status-<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( $label ); ?></span></td>
                  <td><?php echo esc_html( $r->tracking_outbound ?: '—' ); ?></td>
                  <td>
                    <a href="?page=kogu-rentals&detail=<?php echo (int) $r->id; ?>" class="button button-small">詳細</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ( empty( $rentals ) ) : ?>
                <tr><td colspan="8" style="text-align:center;padding:24px;">該当するレンタルはありません。</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php

        // 詳細表示
        if ( isset( $_GET['detail'] ) ) {
            self::render_rental_detail( (int) $_GET['detail'] );
        }
    }

    private static function render_rental_detail( $rental_id ) {
        $rental = Kogu_Rental_Manager::get( $rental_id );
        if ( ! $rental ) return;

        $nonce = wp_create_nonce( 'kogu_admin_action' );
        ?>
        <div style="margin-top:32px;background:#fff;border:1px solid #ddd;padding:24px;border-radius:8px;max-width:700px;">
          <h2>レンタル #<?php echo (int) $rental->id; ?> 詳細</h2>

          <table class="form-table">
            <tr><th>ステータス</th><td><?php echo esc_html( $rental->status ); ?></td></tr>
            <tr><th>お名前</th><td><?php echo esc_html( $rental->user_id ? get_userdata( $rental->user_id )->display_name : $rental->guest_name ); ?></td></tr>
            <tr><th>メール</th><td><?php echo esc_html( $rental->guest_email ); ?></td></tr>
            <tr><th>電話</th><td><?php echo esc_html( $rental->guest_phone ); ?></td></tr>
            <tr><th>住所</th><td><?php echo esc_html( $rental->guest_postal_code . ' ' . $rental->guest_address ); ?></td></tr>
            <tr><th>期間</th><td><?php echo esc_html( $rental->rental_start_date ); ?> 〜 <?php echo esc_html( $rental->rental_end_date ); ?>（<?php echo (int) $rental->rental_days; ?>日）</td></tr>
            <tr><th>レンタル料金</th><td>¥<?php echo number_format( $rental->rental_fee ); ?></td></tr>
            <tr><th>デポジット</th><td>¥<?php echo number_format( $rental->deposit_amount ); ?></td></tr>
            <tr><th>延滞料金</th><td>¥<?php echo number_format( $rental->late_fee_total ); ?>（<?php echo (int) $rental->late_fee_days; ?>日）</td></tr>
            <tr><th>返送追跡番号</th><td><?php echo esc_html( $rental->tracking_return ?: '—' ); ?></td></tr>
          </table>

          <!-- 発送処理 -->
          <?php if ( in_array( $rental->status, [ 'confirmed', 'pending' ], true ) ) : ?>
          <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="margin-top:16px;">
            <input type="hidden" name="action"    value="kogu_admin_action">
            <input type="hidden" name="op"        value="ship">
            <input type="hidden" name="rental_id" value="<?php echo (int) $rental->id; ?>">
            <input type="hidden" name="_wpnonce"  value="<?php echo esc_attr( $nonce ); ?>">
            <h3>発送処理</h3>
            <input type="text" name="tracking_outbound" placeholder="ゆうパック追跡番号" required style="width:280px;padding:6px 10px;margin-right:8px;" />
            <button type="submit" class="button button-primary">発送済みにする</button>
          </form>
          <?php endif; ?>

          <!-- 返却確認・精算 -->
          <?php if ( $rental->status === 'return_evidence_submitted' ) : ?>
          <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="margin-top:16px;">
            <input type="hidden" name="action"    value="kogu_admin_action">
            <input type="hidden" name="op"        value="confirm_return">
            <input type="hidden" name="rental_id" value="<?php echo (int) $rental->id; ?>">
            <input type="hidden" name="_wpnonce"  value="<?php echo esc_attr( $nonce ); ?>">
            <h3>返却確認・精算</h3>
            <label style="display:block;margin-bottom:8px;">
              損害費用（¥）：
              <input type="number" name="damage_fee" value="0" min="0" style="width:120px;padding:6px;margin-left:8px;" />
            </label>
            <p style="font-size:12px;color:#666;">
              延滞料金: ¥<?php echo number_format( $rental->late_fee_total ); ?>
              | デポジット: ¥<?php echo number_format( $rental->deposit_amount ); ?>
            </p>
            <button type="submit" class="button button-primary">返却を確定し精算する</button>
          </form>
          <?php endif; ?>
        </div>
        <?php
    }

    // ── 延滞一覧 ──────────────────────────────────────────────────────────────
    public static function page_overdue() {
        global $wpdb;
        $table   = Kogu_Database::rentals_table();
        $rentals = $wpdb->get_results(
            "SELECT * FROM $table WHERE status = 'overdue' ORDER BY rental_end_date ASC"
        );
        ?>
        <div class="wrap">
          <h1>⚠️ 延滞一覧</h1>
          <table class="wp-list-table widefat fixed striped">
            <thead>
              <tr>
                <th>#</th><th>お客様</th><th>返却期限</th>
                <th>延滞日数</th><th>延滞料金</th><th>デポジット</th><th>操作</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $rentals as $r ) :
                $name = $r->user_id ? get_userdata( $r->user_id )->display_name : $r->guest_name;
              ?>
                <tr style="background:#ffeaea;">
                  <td><?php echo (int) $r->id; ?></td>
                  <td><?php echo esc_html( $name ); ?><br><small><?php echo esc_html( $r->guest_email ); ?></small></td>
                  <td style="color:#c0392b;font-weight:700;"><?php echo esc_html( $r->rental_end_date ); ?></td>
                  <td><?php echo (int) $r->late_fee_days; ?>日</td>
                  <td style="color:#c0392b;">¥<?php echo number_format( $r->late_fee_total ); ?></td>
                  <td>¥<?php echo number_format( $r->deposit_amount ); ?></td>
                  <td><a href="?page=kogu-rentals&detail=<?php echo (int) $r->id; ?>" class="button button-small">詳細</a></td>
                </tr>
              <?php endforeach; ?>
              <?php if ( empty( $rentals ) ) : ?>
                <tr><td colspan="7" style="text-align:center;padding:24px;">延滞中のレンタルはありません。</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    // ── 在庫管理 ──────────────────────────────────────────────────────────────
    public static function page_inventory() {
        global $wpdb;
        $table = Kogu_Database::inventory_table();

        // 台数追加
        if ( isset( $_POST['kogu_add_unit'] ) && check_admin_referer( 'kogu_inventory_action' ) ) {
            $max = (int) $wpdb->get_var( "SELECT MAX(unit_number) FROM $table" );
            $wpdb->insert( $table, [
                'unit_number' => $max + 1,
                'status'      => 'available',
                'condition'   => 'excellent',
            ] );
        }

        // シリアル番号・状態の保存
        if ( isset( $_POST['kogu_save_units'] ) && check_admin_referer( 'kogu_inventory_action' ) ) {
            foreach ( $_POST['serial'] as $id => $serial ) {
                $wpdb->update( $table, [
                    'serial_number' => sanitize_text_field( $serial ),
                    'condition'     => sanitize_text_field( $_POST['condition'][ $id ] ?? 'excellent' ),
                    'status'        => sanitize_text_field( $_POST['unit_status'][ $id ] ?? 'available' ),
                    'notes'         => sanitize_textarea_field( $_POST['notes'][ $id ] ?? '' ),
                ], [ 'id' => (int) $id ] );
            }
            echo '<div class="notice notice-success"><p>保存しました。</p></div>';
        }

        $units = Kogu_Inventory::get_all_units();
        $nonce = wp_create_nonce( 'kogu_inventory_action' );
        ?>
        <div class="wrap">
          <h1>在庫管理（インパクトドライバー）</h1>

          <form method="post" style="margin-bottom:24px;">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
            <table class="wp-list-table widefat fixed striped">
              <thead>
                <tr>
                  <th style="width:60px">#台目</th>
                  <th>シリアル番号</th>
                  <th style="width:120px">状態</th>
                  <th style="width:130px">ステータス</th>
                  <th>メモ</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $units as $u ) :
                  $row_color = $u->status === 'rented' ? '#fff8e1' : ( $u->status === 'maintenance' ? '#fde8e8' : '' );
                ?>
                  <tr style="background:<?php echo esc_attr( $row_color ); ?>">
                    <td style="font-weight:700;"><?php echo (int) $u->unit_number; ?>台目</td>
                    <td>
                      <input type="text" name="serial[<?php echo (int) $u->id; ?>]"
                             value="<?php echo esc_attr( $u->serial_number ); ?>"
                             placeholder="例: SN-00001" style="width:100%;padding:4px 8px;" />
                    </td>
                    <td>
                      <select name="condition[<?php echo (int) $u->id; ?>]" style="width:100%;padding:4px;">
                        <?php foreach ( [ 'excellent' => '良好', 'good' => '普通', 'fair' => '使用感あり', 'damaged' => '破損' ] as $v => $l ) : ?>
                          <option value="<?php echo $v; ?>" <?php selected( $u->condition, $v ); ?>><?php echo esc_html( $l ); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                    <td>
                      <select name="unit_status[<?php echo (int) $u->id; ?>]" style="width:100%;padding:4px;"
                              <?php echo $u->status === 'rented' ? 'disabled' : ''; ?>>
                        <?php foreach ( [ 'available' => '貸出可', 'maintenance' => 'メンテ中', 'retired' => '廃棄' ] as $v => $l ) : ?>
                          <option value="<?php echo $v; ?>" <?php selected( $u->status, $v ); ?>><?php echo esc_html( $l ); ?></option>
                        <?php endforeach; ?>
                        <?php if ( $u->status === 'rented' ) : ?>
                          <option value="rented" selected>貸出中</option>
                        <?php endif; ?>
                      </select>
                    </td>
                    <td>
                      <input type="text" name="notes[<?php echo (int) $u->id; ?>]"
                             value="<?php echo esc_attr( $u->notes ); ?>"
                             placeholder="メモ" style="width:100%;padding:4px 8px;" />
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div style="margin-top:12px;display:flex;gap:12px;">
              <button type="submit" name="kogu_save_units" class="button button-primary">変更を保存</button>
              <button type="submit" name="kogu_add_unit" class="button"
                      onclick="return confirm('台数を1台追加しますか？');">＋ 1台追加</button>
            </div>
          </form>

          <h3>現在の空き状況</h3>
          <?php
          $available = Kogu_Inventory::available_count( date('Y-m-d'), date('Y-m-d') );
          $total     = count( $units );
          $rented    = $total - $available;
          ?>
          <p>
            全 <strong><?php echo $total; ?></strong> 台中、
            貸出中 <strong style="color:#e85a2b;"><?php echo $rented; ?></strong> 台 /
            貸出可 <strong style="color:#27ae60;"><?php echo $available; ?></strong> 台
          </p>
        </div>
        <?php
    }

    // ── 設定画面 ──────────────────────────────────────────────────────────────
    public static function register_settings() {
        $fields = [
            'kogu_stripe_public_key'    => 'Stripe 公開鍵（pk_live_...）',
            'kogu_stripe_secret_key'    => 'Stripe 秘密鍵（sk_live_...）',
            'kogu_stripe_webhook_secret'=> 'Stripe Webhook シークレット（whsec_...）',
            'kogu_sendgrid_api_key'     => 'SendGrid APIキー',
            'kogu_from_email'           => '送信元メールアドレス',
            'kogu_from_name'            => '送信元名',
            'kogu_price_per_day'        => '1日あたりレンタル料金（円）',
            'kogu_deposit_amount'       => 'デポジット額（円）',
        ];
        foreach ( $fields as $key => $label ) {
            register_setting( 'kogu_settings', $key );
        }
    }

    public static function page_settings() {
        ?>
        <div class="wrap">
          <h1>工具レンタル 設定</h1>
          <form method="post" action="options.php">
            <?php settings_fields( 'kogu_settings' ); ?>
            <table class="form-table">
              <?php
              $fields = [
                  'kogu_stripe_public_key'     => 'Stripe 公開鍵（pk_live_...）',
                  'kogu_stripe_secret_key'     => 'Stripe 秘密鍵（sk_live_...）',
                  'kogu_stripe_webhook_secret' => 'Stripe Webhook シークレット（whsec_...）',
                  'kogu_sendgrid_api_key'      => 'SendGrid APIキー',
                  'kogu_from_email'            => '送信元メールアドレス',
                  'kogu_from_name'             => '送信元名',
                  'kogu_price_per_day'         => '1日あたりレンタル料金（円）',
                  'kogu_deposit_amount'        => 'デポジット額（円）',
              ];
              foreach ( $fields as $key => $label ) :
                  $is_secret = strpos( $key, 'key' ) !== false || strpos( $key, 'secret' ) !== false;
              ?>
                  <tr>
                    <th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                    <td>
                      <input
                        type="<?php echo $is_secret ? 'password' : 'text'; ?>"
                        id="<?php echo esc_attr( $key ); ?>"
                        name="<?php echo esc_attr( $key ); ?>"
                        value="<?php echo esc_attr( get_option( $key ) ); ?>"
                        class="regular-text"
                      />
                    </td>
                  </tr>
              <?php endforeach; ?>
            </table>
            <?php submit_button( '設定を保存' ); ?>
          </form>
          <hr>
          <h2>Webhook URL（Stripeダッシュボードに登録）</h2>
          <code><?php echo esc_html( home_url( '/wp-json/kogu/v1/stripe-webhook' ) ); ?></code>
        </div>
        <?php
    }

    // ── 管理者操作ハンドラ ────────────────────────────────────────────────────
    public static function handle_admin_action() {
        check_admin_referer( 'kogu_admin_action' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '権限がありません。' );

        $op        = sanitize_text_field( $_POST['op'] ?? '' );
        $rental_id = (int) ( $_POST['rental_id'] ?? 0 );

        if ( $op === 'ship' ) {
            $tracking = sanitize_text_field( $_POST['tracking_outbound'] ?? '' );
            Kogu_Rental_Manager::mark_shipped_to_customer( $rental_id, $tracking );
        }

        if ( $op === 'confirm_return' ) {
            $damage_fee = (int) ( $_POST['damage_fee'] ?? 0 );
            Kogu_Rental_Manager::confirm_return( $rental_id, $damage_fee );
        }

        wp_redirect( admin_url( 'admin.php?page=kogu-rentals&detail=' . $rental_id . '&updated=1' ) );
        exit;
    }
}
