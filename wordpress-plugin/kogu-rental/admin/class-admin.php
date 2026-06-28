<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Admin {

    public static function init() {
        add_action( 'admin_menu',                        [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_post_kogu_admin_action',      [ __CLASS__, 'handle_admin_action' ] );
        add_action( 'admin_post_kogu_save_product',      [ __CLASS__, 'handle_save_product' ] );
        add_action( 'admin_post_kogu_save_addon',        [ __CLASS__, 'handle_save_addon' ] );
        add_action( 'admin_post_kogu_return_addon',      [ __CLASS__, 'handle_return_addon' ] );
        add_action( 'admin_post_kogu_run_install',          [ __CLASS__, 'handle_run_install' ] );
        add_action( 'admin_post_kogu_notify_tool_request', [ __CLASS__, 'handle_notify_tool_request' ] );
        add_action( 'admin_post_kogu_toggle_product',    [ __CLASS__, 'handle_toggle_product' ] );
        add_action( 'admin_post_kogu_toggle_addon',      [ __CLASS__, 'handle_toggle_addon' ] );
        add_action( 'admin_post_kogu_delete_unit',       [ __CLASS__, 'handle_delete_unit' ] );
        add_action( 'admin_init',                          [ __CLASS__, 'register_settings' ] );
    }

    // ── 在庫ユニット削除 ──────────────────────────────────────────────────────
    public static function handle_delete_unit() {
        $unit_id    = isset( $_REQUEST['unit_id'] )    ? (int) $_REQUEST['unit_id']    : 0;
        $product_id = isset( $_REQUEST['product_id'] ) ? (int) $_REQUEST['product_id'] : 0;
        check_admin_referer( 'kogu_delete_unit_' . $unit_id );
        if ( ! current_user_can( 'manage_options' ) || ! $unit_id ) wp_die( '権限がありません。' );

        global $wpdb;
        $inv_table = Kogu_Database::inventory_table();
        $unit = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $inv_table WHERE id = %d", $unit_id ) );

        if ( ! $unit ) {
            wp_redirect( admin_url( 'admin.php?page=kogu-inventory&product_id=' . $product_id . '&delete_error=not_found' ) );
            exit;
        }
        if ( $unit->status === 'rented' ) {
            wp_redirect( admin_url( 'admin.php?page=kogu-inventory&product_id=' . $product_id . '&delete_error=rented' ) );
            exit;
        }

        $wpdb->delete( $inv_table, [ 'id' => $unit_id ] );
        wp_redirect( admin_url( 'admin.php?page=kogu-inventory&product_id=' . $product_id . '&deleted=1' ) );
        exit;
    }

    // ── 商品・購入商品のステータス即時切り替え ─────────────────────────────────
    public static function handle_toggle_product() {
        $id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;
        check_admin_referer( 'kogu_toggle_product_' . $id );
        if ( ! current_user_can( 'manage_options' ) || ! $id ) wp_die( '権限がありません。' );
        global $wpdb;
        $table  = Kogu_Database::products_table();
        $cur    = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table WHERE id = %d", $id ) );
        $new    = $cur === 'active' ? 'inactive' : 'active';
        $wpdb->update( $table, [ 'status' => $new ], [ 'id' => $id ] );
        wp_redirect( admin_url( 'admin.php?page=kogu-products&toggled=1' ) );
        exit;
    }

    public static function handle_toggle_addon() {
        $id = isset( $_POST['addon_id'] ) ? (int) $_POST['addon_id'] : 0;
        check_admin_referer( 'kogu_toggle_addon_' . $id );
        if ( ! current_user_can( 'manage_options' ) || ! $id ) wp_die( '権限がありません。' );
        global $wpdb;
        $table  = Kogu_Database::addon_products_table();
        $cur    = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM $table WHERE id = %d", $id ) );
        $new    = $cur === 'active' ? 'inactive' : 'active';
        $wpdb->update( $table, [ 'status' => $new ], [ 'id' => $id ] );
        wp_redirect( admin_url( 'admin.php?page=kogu-addons&toggled=1' ) );
        exit;
    }

    // ── DB初期化（テーブル作成） ──────────────────────────────────────────────
    public static function handle_run_install() {
        check_admin_referer( 'kogu_run_install' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '権限がありません。' );
        Kogu_Database::install();
        wp_redirect( admin_url( 'admin.php?page=kogu-settings&installed=1' ) );
        exit;
    }

    // ── メニュー登録 ──────────────────────────────────────────────────────────
    public static function register_menu() {
        add_menu_page(
            'レンタル管理', 'レンタル管理', 'manage_options',
            'kogu-rentals', [ __CLASS__, 'page_rentals' ],
            'dashicons-hammer', 26
        );
        add_submenu_page( 'kogu-rentals', 'レンタル一覧', 'レンタル一覧',   'manage_options', 'kogu-rentals',    [ __CLASS__, 'page_rentals' ] );
        add_submenu_page( 'kogu-rentals', '延滞一覧',     '⚠️ 延滞一覧',  'manage_options', 'kogu-overdue',    [ __CLASS__, 'page_overdue' ] );
        add_submenu_page( 'kogu-rentals', '在庫管理',     '在庫管理',      'manage_options', 'kogu-inventory',  [ __CLASS__, 'page_inventory' ] );
        add_submenu_page( 'kogu-rentals', '商品管理',     '商品管理',      'manage_options', 'kogu-products',   [ __CLASS__, 'page_products' ] );
        add_submenu_page( 'kogu-rentals', '購入商品管理', '🛒 購入商品管理', 'manage_options', 'kogu-addons',          [ __CLASS__, 'page_addons' ] );
        add_submenu_page( 'kogu-rentals', '購入履歴',     '📦 購入履歴',       'manage_options', 'kogu-addon-history',    [ __CLASS__, 'page_addon_history' ] );
        add_submenu_page( 'kogu-rentals', '工具リクエスト', '💡 工具リクエスト', 'manage_options', 'kogu-tool-requests',   [ __CLASS__, 'page_tool_requests' ] );
        add_submenu_page( 'kogu-rentals', '設定',         '設定',            'manage_options', 'kogu-settings',         [ __CLASS__, 'page_settings' ] );
        add_submenu_page( null, '同梱紙', '同梱紙', 'manage_options', 'kogu-packing-slip', [ __CLASS__, 'page_packing_slip' ] );
    }

    // ── レンタル一覧 ──────────────────────────────────────────────────────────
    public static function page_rentals() {
        global $wpdb;
        $table = Kogu_Database::rentals_table();
        $ptbl  = Kogu_Database::products_table();

        $status_filter = sanitize_text_field( $_GET['status'] ?? '' );
        $where = $status_filter ? $wpdb->prepare( 'WHERE r.status = %s', $status_filter ) : '';

        $rentals = $wpdb->get_results(
            "SELECT r.*, p.name AS product_name
             FROM $table r
             LEFT JOIN $ptbl p ON p.id = r.product_id
             $where
             ORDER BY r.created_at DESC LIMIT 200"
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
            <a href="?page=kogu-rentals" class="button <?php echo ! $status_filter ? 'button-primary' : ''; ?>">すべて</a>
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
                <th>商品</th>
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
                $weeks = (int) $r->rental_weeks ?: 1;
              ?>
                <tr>
                  <td><?php echo (int) $r->id; ?></td>
                  <td>
                    <strong><?php echo esc_html( $name ); ?></strong><br>
                    <small><?php echo esc_html( $email ); ?></small>
                  </td>
                  <td><?php echo esc_html( $r->product_name ?? '—' ); ?></td>
                  <td>
                    <?php echo esc_html( $r->rental_start_date ); ?> 〜<br>
                    <strong style="color:#e85a2b;"><?php echo esc_html( $r->rental_end_date ); ?></strong>
                    <small>(<?php echo $weeks; ?>週間)</small>
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
                <tr><td colspan="9" style="text-align:center;padding:24px;">該当するレンタルはありません。</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php

        if ( isset( $_GET['detail'] ) ) {
            self::render_rental_detail( (int) $_GET['detail'] );
        }
    }

    private static function render_rental_detail( $rental_id ) {
        global $wpdb;
        $table = Kogu_Database::rentals_table();
        $ptbl  = Kogu_Database::products_table();
        $rental = $wpdb->get_row( $wpdb->prepare(
            "SELECT r.*, p.name AS product_name FROM $table r
             LEFT JOIN $ptbl p ON p.id = r.product_id
             WHERE r.id = %d",
            $rental_id
        ) );
        if ( ! $rental ) return;

        $nonce = wp_create_nonce( 'kogu_admin_action' );
        $weeks = (int) $rental->rental_weeks ?: 1;
        ?>
        <div style="margin-top:32px;background:#fff;border:1px solid #ddd;padding:24px;border-radius:8px;max-width:700px;">
          <h2>レンタル #<?php echo (int) $rental->id; ?> 詳細
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=kogu-packing-slip&reservation_number=' . urlencode( $rental->reservation_number ) . '&rental_id=' . $rental_id ) ); ?>"
               target="_blank" class="button button-secondary" style="float:right;font-size:13px;">🖨 同梱紙を印刷</a>
          </h2>

          <table class="form-table">
            <tr><th>ステータス</th><td><?php echo esc_html( $rental->status ); ?></td></tr>
            <tr><th>商品</th><td><?php echo esc_html( $rental->product_name ?? '—' ); ?></td></tr>
            <tr><th>お名前</th><td><?php echo esc_html( $rental->user_id ? get_userdata( $rental->user_id )->display_name : $rental->guest_name ); ?></td></tr>
            <tr><th>メール</th><td><?php echo esc_html( $rental->guest_email ); ?></td></tr>
            <tr><th>電話</th><td><?php echo esc_html( $rental->guest_phone ); ?></td></tr>
            <tr><th>住所</th><td><?php echo esc_html( $rental->guest_postal_code . ' ' . $rental->guest_address ); ?></td></tr>
            <tr><th>期間</th><td><?php echo esc_html( $rental->rental_start_date ); ?> 〜 <?php echo esc_html( $rental->rental_end_date ); ?>（<?php echo $weeks; ?>週間）</td></tr>
            <tr><th>レンタル料金</th><td>¥<?php echo number_format( $rental->rental_fee ); ?></td></tr>
            <tr><th>デポジット</th><td>¥<?php echo number_format( $rental->deposit_amount ); ?></td></tr>
            <tr><th>延滞料金</th><td>¥<?php echo number_format( $rental->late_fee_total ); ?>（<?php echo (int) $rental->late_fee_days; ?>日）</td></tr>
            <?php if ( $rental->damage_fee > 0 ) : ?>
            <tr><th>損害費用</th><td style="color:#c0392b;">¥<?php echo number_format( $rental->damage_fee ); ?></td></tr>
            <?php endif; ?>
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
            <table style="border-collapse:collapse;margin-bottom:10px;">
              <tr>
                <td style="padding:4px 10px 4px 0;font-weight:bold;white-space:nowrap;">往路追跡番号（顧客への発送）</td>
                <td><input type="text" name="tracking_outbound" placeholder="ゆうパック追跡番号" required style="width:280px;padding:6px 10px;" /></td>
              </tr>
              <tr>
                <td style="padding:4px 10px 4px 0;font-weight:bold;white-space:nowrap;">返却用送り状番号 <span style="color:#c0392b;">※必須</span></td>
                <td><input type="text" name="tracking_return_label" placeholder="同梱する返送伝票の追跡番号" required style="width:280px;padding:6px 10px;" /></td>
              </tr>
            </table>
            <button type="submit" class="button button-primary">発送済みにする</button>
          </form>
          <?php endif; ?>

          <!-- 返却手続き登録 -->
          <?php if ( in_array( $rental->status, [ 'shipped_to_customer', 'active', 'overdue' ], true ) ) : ?>
          <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="margin-top:16px;">
            <input type="hidden" name="action"    value="kogu_admin_action">
            <input type="hidden" name="op"        value="submit_return">
            <input type="hidden" name="rental_id" value="<?php echo (int) $rental->id; ?>">
            <input type="hidden" name="_wpnonce"  value="<?php echo esc_attr( $nonce ); ?>">
            <h3>返却手続き</h3>
            <p style="font-size:13px;color:#666;margin-bottom:8px;">顧客が同梱の着払い伝票で発送後、追跡番号を入力して登録してください。登録すると「返却確認中」ステータスに移行します。</p>
            <div style="display:flex;gap:8px;align-items:center;">
              <input type="text" name="tracking_return" placeholder="ゆうパック追跡番号（任意）" style="width:280px;padding:6px 10px;" />
              <button type="submit" class="button button-primary">返却手続きを登録する</button>
            </div>
          </form>
          <?php endif; ?>

          <!-- 返却確認・精算（管理者が延滞・損害費用を入力してカードに請求） -->
          <?php if ( $rental->status === 'return_evidence_submitted' ) : ?>
          <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="margin-top:16px;">
            <input type="hidden" name="action"    value="kogu_admin_action">
            <input type="hidden" name="op"        value="confirm_return">
            <input type="hidden" name="rental_id" value="<?php echo (int) $rental->id; ?>">
            <input type="hidden" name="_wpnonce"  value="<?php echo esc_attr( $nonce ); ?>">
            <h3>返却確認（精算）</h3>
            <label style="display:block;margin-bottom:8px;">
              延滞料金（¥）：
              <input type="number" name="late_fee_override" id="kogu-late-fee-input"
                     value="<?php echo (int) $rental->late_fee_total; ?>" min="0"
                     style="width:120px;padding:6px;margin-left:8px;" />
              <small style="color:#666;">（自動計算: ¥<?php echo number_format( $rental->late_fee_total ); ?>、0にすると免除）</small>
            </label>
            <label style="display:block;margin-bottom:12px;">
              損害費用（¥）：
              <input type="number" name="damage_fee" id="kogu-damage-fee-input"
                     value="0" min="0" style="width:120px;padding:6px;margin-left:8px;" />
            </label>
            <label style="display:block;margin-bottom:12px;">
              損害内容・理由：<br>
              <textarea name="damage_reason" rows="3" style="width:100%;max-width:480px;padding:6px;margin-top:4px;" placeholder="例：本体に打痕あり、バッテリーが充電不可になっていた など（損害費用が0円の場合は空欄でOK）"></textarea>
            </label>
            <p style="font-size:12px;color:#666;">
              合計請求額: ¥<strong id="total-charge-preview"><?php echo number_format( $rental->late_fee_total ); ?></strong>
              （延滞・損害がある場合、登録カードに直接請求されます。0円の場合は請求なし）
            </p>
            <script>
            (function() {
              var lateInput = document.getElementById('kogu-late-fee-input');
              var dmgInput  = document.getElementById('kogu-damage-fee-input');
              function updateTotal() {
                var late = parseInt(lateInput.value) || 0;
                var dmg  = parseInt(dmgInput.value)  || 0;
                document.getElementById('total-charge-preview').textContent = (late + dmg).toLocaleString('ja-JP');
              }
              lateInput.addEventListener('input', updateTotal);
              dmgInput.addEventListener('input', updateTotal);
            })();
            </script>
            <button type="submit" class="button button-primary">返却を確認する（カードに請求）</button>
          </form>
          <?php endif; ?>
        </div>
        <?php
    }

    // ── 延滞一覧 ──────────────────────────────────────────────────────────────
    public static function page_overdue() {
        global $wpdb;
        $table = Kogu_Database::rentals_table();
        $ptbl  = Kogu_Database::products_table();
        $rentals = $wpdb->get_results(
            "SELECT r.*, p.name AS product_name FROM $table r
             LEFT JOIN $ptbl p ON p.id = r.product_id
             WHERE r.status = 'overdue' ORDER BY r.rental_end_date ASC"
        );
        ?>
        <div class="wrap">
          <h1>⚠️ 延滞一覧</h1>
          <table class="wp-list-table widefat fixed striped">
            <thead>
              <tr>
                <th>#</th><th>お客様</th><th>商品</th><th>返却期限</th>
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
                  <td><?php echo esc_html( $r->product_name ?? '—' ); ?></td>
                  <td style="color:#c0392b;font-weight:700;"><?php echo esc_html( $r->rental_end_date ); ?></td>
                  <td><?php echo (int) $r->late_fee_days; ?>日</td>
                  <td style="color:#c0392b;">¥<?php echo number_format( $r->late_fee_total ); ?></td>
                  <td>¥<?php echo number_format( $r->deposit_amount ); ?></td>
                  <td><a href="?page=kogu-rentals&detail=<?php echo (int) $r->id; ?>" class="button button-small">詳細</a></td>
                </tr>
              <?php endforeach; ?>
              <?php if ( empty( $rentals ) ) : ?>
                <tr><td colspan="8" style="text-align:center;padding:24px;">延滞中のレンタルはありません。</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    // ── 在庫管理 ──────────────────────────────────────────────────────────────
    public static function page_inventory() {
        global $wpdb;
        $inv_table = Kogu_Database::inventory_table();
        $products  = Kogu_Database::get_active_products();

        $selected_pid = isset( $_GET['product_id'] ) ? (int) $_GET['product_id'] : ( $products[0]->id ?? 0 );

        // 台数追加
        if ( isset( $_POST['kogu_add_unit'] ) && check_admin_referer( 'kogu_inventory_action' ) ) {
            $max = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT MAX(unit_number) FROM $inv_table WHERE product_id = %d",
                $selected_pid
            ) );
            $new_unit = $max + 1;
            $wpdb->insert( $inv_table, [
                'product_id'  => $selected_pid,
                'unit_number' => $new_unit,
                'status'      => 'available',
                'condition'   => 'excellent',
            ] );
            $new_unit_id = $wpdb->insert_id;
            $product_for_serial = Kogu_Database::get_product( $selected_pid );
            $auto_serial = Kogu_Database::generate_serial(
                'R',
                $product_for_serial->category ?? 'XX',
                $selected_pid,
                $new_unit
            );
            $wpdb->update( $inv_table, [ 'serial_number' => $auto_serial ], [ 'id' => $new_unit_id ] );
        }

        if ( isset( $_GET['deleted'] ) ) {
            echo '<div class="notice notice-success"><p>削除しました。</p></div>';
        }
        if ( isset( $_GET['delete_error'] ) ) {
            $msg = $_GET['delete_error'] === 'rented' ? '貸出中のユニットは削除できません。' : '対象が見つかりませんでした。';
            echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . '</p></div>';
        }

        // シリアル番号・状態の保存
        if ( isset( $_POST['kogu_save_units'] ) && check_admin_referer( 'kogu_inventory_action' ) ) {
            foreach ( $_POST['serial'] as $id => $serial ) {
                $wpdb->update( $inv_table, [
                    'serial_number' => sanitize_text_field( $serial ),
                    'condition'     => sanitize_text_field( $_POST['condition'][ $id ] ?? 'excellent' ),
                    'status'        => sanitize_text_field( $_POST['unit_status'][ $id ] ?? 'available' ),
                    'notes'         => sanitize_textarea_field( $_POST['notes'][ $id ] ?? '' ),
                ], [ 'id' => (int) $id ] );
            }
            echo '<div class="notice notice-success"><p>保存しました。</p></div>';
        }

        $units = Kogu_Inventory::get_all_units( $selected_pid );
        $nonce = wp_create_nonce( 'kogu_inventory_action' );

        $selected_product = null;
        foreach ( $products as $p ) {
            if ( (int) $p->id === $selected_pid ) {
                $selected_product = $p;
                break;
            }
        }
        ?>
        <div class="wrap">
          <h1>在庫管理</h1>

          <!-- 商品タブ -->
          <div style="display:flex;gap:8px;margin-bottom:20px;">
            <?php foreach ( $products as $p ) : ?>
              <a href="?page=kogu-inventory&product_id=<?php echo (int) $p->id; ?>"
                 class="button <?php echo (int) $p->id === $selected_pid ? 'button-primary' : ''; ?>">
                <?php echo esc_html( $p->name ); ?>
              </a>
            <?php endforeach; ?>
          </div>

          <?php if ( $selected_product ) : ?>
          <h2><?php echo esc_html( $selected_product->name ); ?></h2>
          <?php endif; ?>

          <form method="post" style="margin-bottom:24px;">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
            <input type="hidden" name="product_id" value="<?php echo $selected_pid; ?>">
            <table class="wp-list-table widefat fixed striped">
              <thead>
                <tr>
                  <th style="width:60px">#台目</th>
                  <th>シリアル番号</th>
                  <th style="width:120px">状態</th>
                  <th style="width:130px">ステータス</th>
                  <th>メモ</th>
                  <th style="width:70px"></th>
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
                    <td>
                      <?php if ( $u->status !== 'rented' ) :
                        $del_url = wp_nonce_url(
                            admin_url( 'admin-post.php?action=kogu_delete_unit&unit_id=' . (int) $u->id . '&product_id=' . (int) $selected_pid ),
                            'kogu_delete_unit_' . (int) $u->id
                        );
                      ?>
                        <a href="<?php echo esc_url( $del_url ); ?>"
                           class="button button-small"
                           style="color:#c0392b;border-color:#c0392b;"
                           onclick="return confirm('<?php echo esc_js( (int) $u->unit_number . '台目を削除します。この操作は取り消せません。よろしいですか？' ); ?>')">削除</a>
                      <?php else : ?>
                        <span style="color:#aaa;font-size:11px;">貸出中</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div style="margin-top:12px;display:flex;gap:12px;">
              <button type="submit" name="kogu_save_units" class="button button-primary">変更を保存</button>
              <button type="submit" name="kogu_add_unit" class="button">＋ 1台追加</button>
            </div>
          </form>

          <h3>現在の空き状況</h3>
          <?php
          $today     = date( 'Y-m-d' );
          $available = Kogu_Inventory::available_count( $today, $today, $selected_pid );
          $total     = count( $units );
          $rented    = $total - $available;
          $buffer    = (int) get_option( 'kogu_return_buffer_days', 3 );
          ?>
          <p>
            全 <strong><?php echo $total; ?></strong> 台中、
            貸出中 <strong style="color:#e85a2b;"><?php echo $rented; ?></strong> 台 /
            折り返しバッファ中 <strong style="color:#e67e22;"><?php echo max(0,$total-$rented-$available); ?></strong> 台 /
            貸出可 <strong style="color:#27ae60;"><?php echo $available; ?></strong> 台
          </p>
          <p style="font-size:12px;color:#888;">※ 折り返しバッファ: 返却期限後 <?php echo $buffer; ?> 日間は次の貸し出しをブロック（<a href="?page=kogu-settings">設定で変更</a>）</p>

          <!-- 60日間 容量予測モデル -->
          <h3 style="margin-top:32px;">📊 60日間 在庫容量予測</h3>
          <p style="font-size:13px;color:#555;margin-bottom:12px;">
            返却期限＋バッファ（<?php echo $buffer; ?>日）を考慮した、今後60日間の受付可能台数予測です。<br>
            <span style="display:inline-block;width:12px;height:12px;background:#d4f4e2;border:1px solid #ccc;margin-right:4px;"></span>余裕あり
            <span style="display:inline-block;width:12px;height:12px;background:#fff3cd;border:1px solid #ccc;margin:0 4px;"></span>残りわずか
            <span style="display:inline-block;width:12px;height:12px;background:#fde8e8;border:1px solid #ccc;margin:0 4px;"></span>満杯
            <span style="display:inline-block;width:12px;height:12px;background:#ece4d2;border:1px solid #ccc;margin:0 4px;"></span>バッファ中
          </p>
          <?php
          $forecast    = Kogu_Inventory::capacity_forecast( 60 );
          $product_rows = $forecast[ $selected_pid ] ?? [];
          ?>
          <div style="overflow-x:auto;max-height:420px;overflow-y:auto;border:1px solid #ddd;border-radius:4px;">
            <table style="border-collapse:collapse;width:100%;font-size:12px;white-space:nowrap;">
              <thead style="position:sticky;top:0;background:#f6f1e6;z-index:1;">
                <tr>
                  <th style="padding:8px 12px;border-bottom:2px solid #ddd;text-align:left;">日付</th>
                  <th style="padding:8px 12px;border-bottom:2px solid #ddd;text-align:center;">全台数</th>
                  <th style="padding:8px 12px;border-bottom:2px solid #ddd;text-align:center;">貸出中</th>
                  <th style="padding:8px 12px;border-bottom:2px solid #ddd;text-align:center;">バッファ中</th>
                  <th style="padding:8px 12px;border-bottom:2px solid #ddd;text-align:center;">受付可能</th>
                  <th style="padding:8px 12px;border-bottom:2px solid #ddd;text-align:left;">この日が返却期限</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ( $product_rows as $row ) :
                  $avail = $row['available'];
                  $total_r = $row['total'];
                  $ratio = $total_r > 0 ? $avail / $total_r : 0;
                  if ( $avail === 0 && $row['buffer'] === 0 && $row['rented'] === 0 ) {
                    $bg = '#fff'; // 余裕あり（全台空き）
                  } elseif ( $avail === 0 ) {
                    $bg = '#fde8e8'; // 満杯
                  } elseif ( $row['buffer'] > 0 && $avail < $total_r ) {
                    $bg = '#ece4d2'; // バッファ含む
                  } elseif ( $ratio < 0.4 ) {
                    $bg = '#fff3cd'; // 残りわずか
                  } else {
                    $bg = '#d4f4e2'; // 余裕あり
                  }
                  $is_today = $row['date'] === date('Y-m-d');
                  $weekday  = ['日','月','火','水','木','金','土'][(int)date('w', strtotime($row['date']))];
                  $is_sun   = (int)date('w', strtotime($row['date'])) === 0;
                  $is_sat   = (int)date('w', strtotime($row['date'])) === 6;
                ?>
                  <tr style="background:<?php echo $bg; ?>;<?php echo $is_today ? 'font-weight:700;outline:2px solid #e85a2b;outline-offset:-2px;' : ''; ?>">
                    <td style="padding:6px 12px;border-bottom:1px solid #eee;color:<?php echo $is_sun ? '#c0392b' : ($is_sat ? '#2980b9' : 'inherit'); ?>">
                      <?php echo esc_html( $row['date'] ); ?>（<?php echo $weekday; ?>）
                      <?php if ( $is_today ) echo '<span style="background:#e85a2b;color:#fff;font-size:10px;padding:1px 5px;border-radius:3px;margin-left:4px;">今日</span>'; ?>
                    </td>
                    <td style="padding:6px 12px;border-bottom:1px solid #eee;text-align:center;"><?php echo $row['total']; ?>台</td>
                    <td style="padding:6px 12px;border-bottom:1px solid #eee;text-align:center;color:#e85a2b;">
                      <?php echo $row['rented'] > 0 ? $row['rented'] . '台' : '—'; ?>
                    </td>
                    <td style="padding:6px 12px;border-bottom:1px solid #eee;text-align:center;color:#e67e22;">
                      <?php echo $row['buffer'] > 0 ? $row['buffer'] . '台' : '—'; ?>
                    </td>
                    <td style="padding:6px 12px;border-bottom:1px solid #eee;text-align:center;font-weight:700;color:<?php echo $avail > 0 ? '#27ae60' : '#c0392b'; ?>">
                      <?php echo $avail; ?>台
                    </td>
                    <td style="padding:6px 12px;border-bottom:1px solid #eee;color:#888;">
                      <?php echo ! empty( $row['ending_today'] ) ? esc_html( implode( '、', $row['ending_today'] ) ) : '—'; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php
          // サマリー: 今後30日で受け付けられる見込み件数
          $cap30 = array_slice( $product_rows, 0, 30 );
          $total_slots = array_sum( array_column( $cap30, 'available' ) );
          $zero_days   = count( array_filter( $cap30, fn($r) => $r['available'] === 0 ) );
          $avg_avail   = $total_r > 0 ? round( array_sum( array_column( $cap30, 'available' ) ) / 30, 1 ) : 0;
          ?>
          <div style="margin-top:16px;display:flex;gap:16px;flex-wrap:wrap;">
            <div style="background:#f6f1e6;border-radius:8px;padding:16px 20px;text-align:center;min-width:140px;">
              <p style="margin:0 0 4px;font-size:12px;color:#888;">今後30日・平均空き台数</p>
              <p style="margin:0;font-size:28px;font-weight:900;color:#27ae60;"><?php echo $avg_avail; ?>台</p>
            </div>
            <div style="background:#f6f1e6;border-radius:8px;padding:16px 20px;text-align:center;min-width:140px;">
              <p style="margin:0 0 4px;font-size:12px;color:#888;">満杯の日数</p>
              <p style="margin:0;font-size:28px;font-weight:900;color:<?php echo $zero_days > 10 ? '#c0392b' : '#e67e22'; ?>;"><?php echo $zero_days; ?>日</p>
            </div>
          </div>
        </div>
        <?php
    }

    // ── 商品管理 ──────────────────────────────────────────────────────────────
    public static function page_products() {
        global $wpdb;
        $table = Kogu_Database::products_table();

        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success"><p>保存しました。</p></div>';
        }
        if ( isset( $_GET['toggled'] ) ) {
            echo '<div class="notice notice-success"><p>ステータスを切り替えました。</p></div>';
        }

        $edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
        $edit    = $edit_id ? Kogu_Database::get_product( $edit_id ) : null;

        $nonce   = wp_create_nonce( 'kogu_product_action' );
        ?>
        <div class="wrap">
          <h1>商品管理
            <?php if ( ! $edit_id ) : ?>
              <a href="?page=kogu-products&edit=0" class="page-title-action">＋ 新規商品を追加</a>
            <?php endif; ?>
          </h1>

          <?php if ( isset( $_GET['edit'] ) ) : ?>
          <!-- 追加・編集フォーム -->
          <div style="max-width:600px;background:#fff;border:1px solid #ddd;padding:24px;border-radius:8px;margin-bottom:32px;">
            <h2><?php echo $edit ? esc_html( $edit->name ) . ' を編集' : '新規商品を追加'; ?></h2>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
              <input type="hidden" name="action"     value="kogu_save_product">
              <input type="hidden" name="product_id" value="<?php echo $edit_id; ?>">
              <input type="hidden" name="_wpnonce"   value="<?php echo esc_attr( $nonce ); ?>">
              <table class="form-table">
                <tr>
                  <th><label for="pname">商品名 <em>*</em></label></th>
                  <td><input type="text" id="pname" name="name" required class="regular-text"
                             value="<?php echo esc_attr( $edit->name ?? '' ); ?>" /></td>
                </tr>
                <tr>
                  <th><label for="pcat">カテゴリ <em>*</em></label></th>
                  <td>
                    <select id="pcat" name="category">
                      <?php foreach ( Kogu_Database::product_categories() as $code => $label ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>"
                          <?php selected( $edit->category ?? 'XX', $code ); ?>>
                          <?php echo esc_html( "{$code} — {$label}" ); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <p class="description">シリアル番号の先頭カテゴリコードに使われます。</p>
                  </td>
                </tr>
                <tr>
                  <th><label for="pslug">URLスラッグ <em>*</em></label></th>
                  <td>
                    <input type="text" id="pslug" name="slug" required class="regular-text"
                           value="<?php echo esc_attr( $edit->slug ?? '' ); ?>"
                           placeholder="例: impact-driver" pattern="[a-z0-9\-]+" />
                    <p class="description">英小文字・数字・ハイフンのみ。公開URL: <code><?php echo esc_html( home_url( '/products/' ) ); ?><strong>{スラッグ}</strong>/</code></p>
                  </td>
                </tr>
                <tr>
                  <th><label for="pdesc">説明</label></th>
                  <td><textarea id="pdesc" name="description" rows="3" class="large-text"><?php echo esc_textarea( $edit->description ?? '' ); ?></textarea></td>
                </tr>
                <tr>
                  <th><label for="pcontents">レンタルに含まれるもの</label></th>
                  <td>
                    <textarea id="pcontents" name="contents" rows="5" class="large-text"><?php echo esc_textarea( $edit->contents ?? '' ); ?></textarea>
                    <p class="description">1行に1項目。例：<br><code>インパクトドライバー本体<br>充電器<br>バッテリー×2<br>ビットセット</code></p>
                  </td>
                </tr>
                <tr>
                  <th><label for="pspecs">スペック（仕様表）</label></th>
                  <td>
                    <textarea id="pspecs" name="specs" rows="10" class="large-text"><?php echo esc_textarea( $edit->specs ?? '' ); ?></textarea>
                    <p class="description">1行に1項目。<code>項目名|値</code> の形式で入力してください。<br>例：<br><code>電圧|18V<br>最大トルク|177 N·m<br>質量|1.4 kg</code></p>
                  </td>
                </tr>
                <tr>
                  <th><label for="pgallery">画像ギャラリー</label></th>
                  <td>
                    <input type="text" id="pgallery" name="gallery" class="large-text"
                           value="<?php echo esc_attr( $edit->gallery ?? '' ); ?>"
                           placeholder="例: product_impact.jpg,product_bitset.jpg" />
                    <p class="description">プラグインの <code>assets/images/</code> 内のファイル名をカンマ区切りで入力してください。左から順に表示されます。</p>
                  </td>
                </tr>
                <tr>
                  <th><label for="pprice">1週間のレンタル料金（円）<em>*</em></label></th>
                  <td>
                    <input type="number" id="pprice" name="price_per_week" required min="1" class="regular-text"
                           value="<?php echo (int) ( $edit->price_per_week ?? 4900 ); ?>" />
                    <p class="description">
                      2週目以降は自動的に 30%OFF（¥<?php
                        $ppw = (int) ( $edit->price_per_week ?? 4900 );
                        echo number_format( (int) round( $ppw * 0.7 ) );
                      ?>/週）が適用されます。
                    </p>
                  </td>
                </tr>
                <tr>
                  <th><label for="pdeposit">デポジット（円）<em>*</em></label></th>
                  <td><input type="number" id="pdeposit" name="deposit_amount" required min="0" class="regular-text"
                             value="<?php echo (int) ( $edit->deposit_amount ?? 10000 ); ?>" /></td>
                </tr>
                <tr>
                  <th>購入オプション表示</th>
                  <td>
                    <label>
                      <input type="checkbox" name="allows_addons" value="1"
                             <?php checked( (int) ( $edit->allows_addons ?? 1 ), 1 ); ?> />
                      このレンタル商品に購入オプション（消耗品など）を表示する
                    </label>
                  </td>
                </tr>
                <tr>
                  <th><label for="pstatus">ステータス</label></th>
                  <td>
                    <select id="pstatus" name="status">
                      <option value="active"   <?php selected( $edit->status ?? 'active', 'active' ); ?>>公開中</option>
                      <option value="inactive" <?php selected( $edit->status ?? 'active', 'inactive' ); ?>>非公開</option>
                    </select>
                  </td>
                </tr>
              </table>
              <?php submit_button( $edit ? '変更を保存' : '商品を追加' ); ?>
              <a href="?page=kogu-products" class="button" style="margin-left:8px;">キャンセル</a>
            </form>
          </div>
          <?php endif; ?>

          <!-- 商品一覧 -->
          <h2>商品一覧</h2>
          <table class="wp-list-table widefat fixed striped">
            <thead>
              <tr>
                <th style="width:50px">ID</th>
                <th>商品名</th>
                <th>1週間料金</th>
                <th>2週目以降</th>
                <th>デポジット</th>
                <th>ステータス</th>
                <th>在庫台数</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $all_products = $wpdb->get_results( "SELECT * FROM $table ORDER BY id ASC" );
              foreach ( $all_products as $p ) :
                $inv_count = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM " . Kogu_Database::inventory_table() .
                    " WHERE product_id = %d AND status != 'retired'",
                    $p->id
                ) );
                $discounted    = (int) round( $p->price_per_week * 0.7 );
                $toggle_nonce  = wp_create_nonce( 'kogu_toggle_product_' . (int) $p->id );
                $is_active     = $p->status === 'active';
              ?>
                <tr style="<?php echo $is_active ? '' : 'opacity:.55;'; ?>">
                  <td><?php echo (int) $p->id; ?></td>
                  <td><strong><?php echo esc_html( $p->name ); ?></strong><br><small><?php echo esc_html( mb_strimwidth( $p->description, 0, 50, '…' ) ); ?></small></td>
                  <td>¥<?php echo number_format( $p->price_per_week ); ?></td>
                  <td>¥<?php echo number_format( $discounted ); ?>/週 <small style="color:#888;">(30%OFF)</small></td>
                  <td>¥<?php echo number_format( $p->deposit_amount ); ?></td>
                  <td>
                    <?php if ( $is_active ) : ?>
                      <span style="color:#27ae60;font-weight:600;">● 公開中</span>
                    <?php else : ?>
                      <span style="color:#aaa;font-weight:600;">○ 非公開</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo $inv_count; ?>台</td>
                  <td style="white-space:nowrap;">
                    <a href="?page=kogu-products&edit=<?php echo (int) $p->id; ?>" class="button button-small">編集</a>
                    <a href="?page=kogu-inventory&product_id=<?php echo (int) $p->id; ?>" class="button button-small">在庫</a>
                    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display:inline;">
                      <input type="hidden" name="action"     value="kogu_toggle_product">
                      <input type="hidden" name="product_id" value="<?php echo (int) $p->id; ?>">
                      <input type="hidden" name="_wpnonce"   value="<?php echo esc_attr( $toggle_nonce ); ?>">
                      <button type="submit" class="button button-small"
                              style="<?php echo $is_active ? 'color:#c0392b;' : 'color:#27ae60;'; ?>">
                        <?php echo $is_active ? '非公開にする' : '公開する'; ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ( empty( $all_products ) ) : ?>
                <tr><td colspan="8" style="text-align:center;padding:24px;">商品が登録されていません。</td></tr>
              <?php endif; ?>
            </tbody>
          </table>

          <h3 style="margin-top:24px;">料金計算の例</h3>
          <?php foreach ( $all_products as $p ) :
            if ( $p->status !== 'active' ) continue;
            $ppw = (int) $p->price_per_week;
            $disc = (int) round( $ppw * 0.7 );
          ?>
          <p><strong><?php echo esc_html( $p->name ); ?></strong>：
            1週 ¥<?php echo number_format( $ppw ); ?> /
            2週 ¥<?php echo number_format( $ppw + $disc ); ?> /
            3週 ¥<?php echo number_format( $ppw + $disc * 2 ); ?> /
            4週 ¥<?php echo number_format( $ppw + $disc * 3 ); ?>
          </p>
          <?php endforeach; ?>
        </div>
        <?php
    }

    // ── 購入商品管理 ──────────────────────────────────────────────────────────
    public static function page_addons() {
        global $wpdb;
        $table = Kogu_Database::addon_products_table();

        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success"><p>保存しました。</p></div>';
        }
        if ( isset( $_GET['toggled'] ) ) {
            echo '<div class="notice notice-success"><p>ステータスを切り替えました。</p></div>';
        }

        $edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : -1;
        $edit    = $edit_id > 0 ? Kogu_Database::get_addon_product( $edit_id ) : null;
        $nonce   = wp_create_nonce( 'kogu_addon_action' );
        $all     = $wpdb->get_results( "SELECT * FROM $table ORDER BY id ASC" );
        ?>
        <div class="wrap">
          <h1>購入商品管理
            <?php if ( $edit_id < 0 ) : ?>
              <a href="?page=kogu-addons&edit=0" class="page-title-action">＋ 新規商品を追加</a>
            <?php endif; ?>
          </h1>
          <p style="color:#555;">電動工具レンタル時に一緒に購入できる消耗品などを登録します。</p>

          <?php if ( isset( $_GET['edit'] ) ) : ?>
          <div style="max-width:560px;background:#fff;border:1px solid #ddd;padding:24px;border-radius:8px;margin-bottom:32px;">
            <h2><?php echo $edit ? esc_html( $edit->name ) . ' を編集' : '新規購入商品を追加'; ?></h2>
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
              <input type="hidden" name="action"   value="kogu_save_addon">
              <input type="hidden" name="addon_id" value="<?php echo $edit_id > 0 ? $edit_id : 0; ?>">
              <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
              <table class="form-table">
                <?php if ( $edit && ! empty( $edit->serial_number ) ) : ?>
                <tr>
                  <th>シリアル番号</th>
                  <td>
                    <code style="font-size:14px;font-weight:700;letter-spacing:1px;"><?php echo esc_html( $edit->serial_number ); ?></code>
                    <p class="description">自動生成済み。変更不可。</p>
                  </td>
                </tr>
                <?php endif; ?>
                <tr>
                  <th><label for="aname">商品名 <em>*</em></label></th>
                  <td><input type="text" id="aname" name="name" required class="regular-text"
                             value="<?php echo esc_attr( $edit->name ?? '' ); ?>" /></td>
                </tr>
                <tr>
                  <th><label for="acat">カテゴリ <em>*</em></label></th>
                  <td>
                    <select id="acat" name="category">
                      <?php foreach ( Kogu_Database::addon_categories() as $code => $label ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>"
                          <?php selected( $edit->category ?? 'CS', $code ); ?>>
                          <?php echo esc_html( "{$code} — {$label}" ); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <p class="description">シリアル番号の先頭カテゴリコードに使われます（新規登録時のみ適用）。</p>
                  </td>
                </tr>
                <tr>
                  <th><label for="adesc">説明</label></th>
                  <td><input type="text" id="adesc" name="description" class="regular-text"
                             value="<?php echo esc_attr( $edit->description ?? '' ); ?>"
                             placeholder="例：木材用ネジ 40mm" /></td>
                </tr>
                <tr>
                  <th><label for="aprice">単価（円） <em>*</em></label></th>
                  <td><input type="number" id="aprice" name="price" required min="1" class="regular-text"
                             value="<?php echo (int) ( $edit->price ?? 0 ); ?>" /></td>
                </tr>
                <tr>
                  <th><label for="aunit">単位</label></th>
                  <td><input type="text" id="aunit" name="unit" class="small-text"
                             value="<?php echo esc_attr( $edit->unit ?? '個' ); ?>"
                             placeholder="個・袋・箱" /></td>
                </tr>
                <tr>
                  <th><label for="aimage">商品画像URL</label></th>
                  <td>
                    <input type="text" id="aimage" name="image" class="large-text"
                           value="<?php echo esc_attr( $edit->image ?? '' ); ?>"
                           placeholder="例: /wp-content/plugins/kogu-rental/assets/images/addon_screw.jpg" />
                    <p class="description">
                      画像ファイルをサーバーの <code>wp-content/plugins/kogu-rental/assets/images/</code> に置いてパスを入力してください。<br>
                      またはWordPressメディアライブラリのURLをそのまま貼り付けてもOKです。
                    </p>
                    <?php if ( ! empty( $edit->image ) ) : ?>
                      <img src="<?php echo esc_url( $edit->image ); ?>" alt="プレビュー"
                           style="max-width:120px;margin-top:8px;border-radius:6px;border:1px solid #ddd;" />
                    <?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <th><label for="astock">在庫数</label></th>
                  <td>
                    <?php
                    $stock_val = isset( $edit->stock_quantity ) ? $edit->stock_quantity : '';
                    ?>
                    <input type="number" id="astock" name="stock_quantity" min="0" class="small-text"
                           value="<?php echo $stock_val !== '' && $stock_val !== null ? (int) $stock_val : ''; ?>"
                           placeholder="空白=無制限" />
                    <p class="description">空白にすると在庫無制限。数字を入力すると購入時に在庫を減算します。</p>
                  </td>
                </tr>
                <tr>
                  <th><label for="astatus">ステータス</label></th>
                  <td>
                    <select id="astatus" name="status">
                      <option value="active"   <?php selected( $edit->status ?? 'active', 'active' ); ?>>公開中</option>
                      <option value="inactive" <?php selected( $edit->status ?? 'active', 'inactive' ); ?>>非公開</option>
                    </select>
                  </td>
                </tr>
              </table>
              <?php submit_button( $edit ? '変更を保存' : '商品を追加' ); ?>
              <a href="?page=kogu-addons" class="button" style="margin-left:8px;">キャンセル</a>
            </form>
          </div>
          <?php endif; ?>

          <h2>登録済み購入商品</h2>
          <table class="wp-list-table widefat fixed striped">
            <thead>
              <tr>
                <th style="width:50px">ID</th>
                <th>シリアル番号</th>
                <th>商品名</th>
                <th>説明</th>
                <th>単価</th>
                <th>単位</th>
                <th>在庫数</th>
                <th>ステータス</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $all as $a ) :
                $stock_disp   = $a->stock_quantity === null
                    ? '<span style="color:#888;">無制限</span>'
                    : ( (int) $a->stock_quantity === 0
                        ? '<span style="color:#c0392b;font-weight:700;">品切れ</span>'
                        : '<span style="color:#27ae60;font-weight:700;">' . (int) $a->stock_quantity . '</span>' );
                $a_is_active  = $a->status === 'active';
                $a_toggle_nonce = wp_create_nonce( 'kogu_toggle_addon_' . (int) $a->id );
              ?>
                <tr style="<?php echo $a_is_active ? '' : 'opacity:.55;'; ?>">
                  <td><?php echo (int) $a->id; ?></td>
                  <td><code style="font-size:11px;"><?php echo esc_html( $a->serial_number ?? '—' ); ?></code></td>
                  <td><strong><?php echo esc_html( $a->name ); ?></strong></td>
                  <td><?php echo esc_html( $a->description ); ?></td>
                  <td>¥<?php echo number_format( $a->price ); ?></td>
                  <td><?php echo esc_html( $a->unit ); ?></td>
                  <td><?php echo $stock_disp; ?></td>
                  <td>
                    <?php if ( $a_is_active ) : ?>
                      <span style="color:#27ae60;font-weight:600;">● 公開中</span>
                    <?php else : ?>
                      <span style="color:#aaa;font-weight:600;">○ 非公開</span>
                    <?php endif; ?>
                  </td>
                  <td style="white-space:nowrap;">
                    <a href="?page=kogu-addons&edit=<?php echo (int) $a->id; ?>" class="button button-small">編集</a>
                    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display:inline;">
                      <input type="hidden" name="action"   value="kogu_toggle_addon">
                      <input type="hidden" name="addon_id" value="<?php echo (int) $a->id; ?>">
                      <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $a_toggle_nonce ); ?>">
                      <button type="submit" class="button button-small"
                              style="<?php echo $a_is_active ? 'color:#c0392b;' : 'color:#27ae60;'; ?>">
                        <?php echo $a_is_active ? '非公開にする' : '公開する'; ?>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ( empty( $all ) ) : ?>
                <tr><td colspan="8" style="text-align:center;padding:24px;">購入商品が登録されていません。</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    // ── 購入履歴 ──────────────────────────────────────────────────────────────
    public static function page_addon_history() {
        // フィルター
        $filter_addon  = isset( $_GET['addon_id'] )   ? (int) $_GET['addon_id']                        : 0;
        $filter_status = isset( $_GET['status'] )     ? sanitize_text_field( $_GET['status'] )         : '';
        $filter_from   = isset( $_GET['date_from'] )  ? sanitize_text_field( $_GET['date_from'] )      : '';
        $filter_to     = isset( $_GET['date_to'] )    ? sanitize_text_field( $_GET['date_to'] )        : '';

        $filters = array_filter( [
            'addon_product_id' => $filter_addon  ?: null,
            'status'           => $filter_status ?: null,
            'date_from'        => $filter_from   ?: null,
            'date_to'          => $filter_to     ?: null,
        ] );

        if ( isset( $_GET['returned'] ) ) {
            echo '<div class="notice notice-success"><p>返品を登録しました。</p></div>';
        }

        $purchases   = Kogu_Database::get_addon_purchase_history( $filters );
        $summary     = Kogu_Database::get_addon_sales_summary();
        $all_addons  = Kogu_Database::get_active_addon_products();
        $nonce       = wp_create_nonce( 'kogu_return_addon' );

        $status_labels = [
            'active'              => '<span style="color:#27ae60;font-weight:700;">販売中</span>',
            'partially_returned'  => '<span style="color:#e67e22;font-weight:700;">一部返品</span>',
            'returned'            => '<span style="color:#c0392b;font-weight:700;">返品済み</span>',
        ];
        ?>
        <div class="wrap">
          <h1>📦 購入履歴</h1>

          <!-- 商品別サマリー -->
          <h2>商品別集計</h2>
          <table class="wp-list-table widefat fixed striped" style="margin-bottom:32px;">
            <thead>
              <tr>
                <th>商品名</th><th>累計販売数</th><th>累計売上</th><th>返品数</th><th>現在在庫</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $summary as $s ) : ?>
                <tr>
                  <td><strong><?php echo esc_html( $s->name ); ?></strong></td>
                  <td><strong style="color:#e85a2b;"><?php echo number_format( $s->sold_qty ); ?></strong><?php echo esc_html( $s->unit ); ?></td>
                  <td><strong>¥<?php echo number_format( $s->sold_amount ); ?></strong></td>
                  <td><?php echo $s->returned_qty > 0 ? '<span style="color:#c0392b;">' . number_format( $s->returned_qty ) . $s->unit . '</span>' : '—'; ?></td>
                  <td>
                    <?php if ( $s->stock_quantity === null ) : ?>
                      <span style="color:#888;">無制限</span>
                    <?php elseif ( (int) $s->stock_quantity === 0 ) : ?>
                      <span style="color:#c0392b;font-weight:700;">品切れ</span>
                    <?php else : ?>
                      <span style="color:#27ae60;font-weight:700;"><?php echo (int) $s->stock_quantity; ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ( empty( $summary ) ) : ?>
                <tr><td colspan="5" style="text-align:center;padding:24px;">購入履歴はありません。</td></tr>
              <?php endif; ?>
            </tbody>
          </table>

          <!-- フィルター -->
          <h2>購入明細</h2>
          <form method="get" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:20px;background:#f6f1e6;padding:16px;border-radius:8px;">
            <input type="hidden" name="page" value="kogu-addon-history">
            <div>
              <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">商品</label>
              <select name="addon_id" style="padding:6px 10px;">
                <option value="">すべて</option>
                <?php foreach ( $all_addons as $a ) : ?>
                  <option value="<?php echo (int) $a->id; ?>" <?php selected( $filter_addon, (int) $a->id ); ?>><?php echo esc_html( $a->name ); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">ステータス</label>
              <select name="status" style="padding:6px 10px;">
                <option value="">すべて</option>
                <option value="active"             <?php selected( $filter_status, 'active' ); ?>>販売中</option>
                <option value="partially_returned" <?php selected( $filter_status, 'partially_returned' ); ?>>一部返品</option>
                <option value="returned"           <?php selected( $filter_status, 'returned' ); ?>>返品済み</option>
              </select>
            </div>
            <div>
              <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">購入日（From）</label>
              <input type="date" name="date_from" value="<?php echo esc_attr( $filter_from ); ?>" style="padding:6px 10px;" />
            </div>
            <div>
              <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">購入日（To）</label>
              <input type="date" name="date_to" value="<?php echo esc_attr( $filter_to ); ?>" style="padding:6px 10px;" />
            </div>
            <button type="submit" class="button button-primary">絞り込む</button>
            <a href="?page=kogu-addon-history" class="button">リセット</a>
          </form>

          <!-- 件数・合計 -->
          <?php
          $total_qty    = array_sum( array_column( $purchases, 'quantity' ) );
          $total_amount = array_sum( array_map( fn( $p ) => (int) $p->quantity * (int) $p->unit_price, $purchases ) );
          ?>
          <p style="font-size:13px;color:#555;">
            <?php echo count( $purchases ); ?>件
            ／ 合計販売数: <strong><?php echo number_format( $total_qty ); ?></strong>点
            ／ 合計売上: <strong>¥<?php echo number_format( $total_amount ); ?></strong>
          </p>

          <!-- 明細テーブル -->
          <table class="wp-list-table widefat fixed striped">
            <thead>
              <tr>
                <th style="width:150px">購入日時</th>
                <th style="width:110px">予約番号</th>
                <th>お客様</th>
                <th>商品名</th>
                <th style="width:70px">数量</th>
                <th style="width:80px">単価</th>
                <th style="width:80px">小計</th>
                <th style="width:90px">ステータス</th>
                <th style="width:70px">操作</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $purchases as $p ) :
                $subtotal   = (int) $p->quantity * (int) $p->unit_price;
                $status_html = $status_labels[ $p->status ] ?? esc_html( $p->status );
                $can_return  = $p->status !== 'returned';
              ?>
                <tr>
                  <td style="font-size:12px;"><?php echo esc_html( substr( $p->created_at, 0, 16 ) ); ?></td>
                  <td style="font-family:monospace;font-weight:700;"><?php echo esc_html( $p->reservation_number ); ?></td>
                  <td>
                    <strong><?php echo esc_html( $p->guest_name ); ?></strong><br>
                    <small style="color:#888;"><?php echo esc_html( $p->guest_email ); ?></small>
                  </td>
                  <td><?php echo esc_html( $p->addon_name ); ?></td>
                  <td><?php echo (int) $p->quantity; ?><?php echo esc_html( $p->unit ); ?>
                    <?php if ( $p->returned_quantity > 0 ) : ?>
                      <br><small style="color:#c0392b;">返品: <?php echo (int) $p->returned_quantity; ?></small>
                    <?php endif; ?>
                  </td>
                  <td>¥<?php echo number_format( $p->unit_price ); ?></td>
                  <td>¥<?php echo number_format( $subtotal ); ?></td>
                  <td><?php echo $status_html; ?>
                    <?php if ( $p->return_reason ) : ?>
                      <br><small style="color:#888;" title="<?php echo esc_attr( $p->return_reason ); ?>">理由あり</small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ( $can_return ) : ?>
                      <button type="button" class="button button-small"
                              onclick="document.getElementById('return-form-<?php echo (int) $p->id; ?>').style.display='block';this.style.display='none';">
                        返品
                      </button>
                    <?php else : ?>
                      <span style="color:#aaa;font-size:12px;">完了</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php if ( $can_return ) : ?>
                <tr id="return-form-<?php echo (int) $p->id; ?>" style="display:none;background:#fff8f0;">
                  <td colspan="9" style="padding:16px;">
                    <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                      <input type="hidden" name="action"          value="kogu_return_addon">
                      <input type="hidden" name="purchase_id"     value="<?php echo (int) $p->id; ?>">
                      <input type="hidden" name="addon_product_id" value="<?php echo (int) $p->addon_product_id; ?>">
                      <input type="hidden" name="_wpnonce"        value="<?php echo esc_attr( $nonce ); ?>">
                      <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">
                          返品数量（最大: <?php echo (int) $p->quantity - (int) $p->returned_quantity; ?>）
                        </label>
                        <input type="number" name="return_qty" min="1"
                               max="<?php echo (int) $p->quantity - (int) $p->returned_quantity; ?>"
                               value="<?php echo (int) $p->quantity - (int) $p->returned_quantity; ?>"
                               style="width:80px;padding:6px;" required />
                      </div>
                      <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">返品理由</label>
                        <input type="text" name="return_reason" style="width:260px;padding:6px;" placeholder="例: お客様都合、商品不良 など" />
                      </div>
                      <div>
                        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">在庫に戻す</label>
                        <label style="display:flex;align-items:center;gap:6px;padding-top:4px;">
                          <input type="checkbox" name="restore_stock" value="1" checked />
                          在庫数に返品分を加算する
                        </label>
                      </div>
                      <div style="padding-top:20px;">
                        <button type="submit" class="button button-primary" style="background:#c0392b;border-color:#c0392b;">
                          返品を登録する
                        </button>
                      </div>
                    </form>
                  </td>
                </tr>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php if ( empty( $purchases ) ) : ?>
                <tr><td colspan="9" style="text-align:center;padding:24px;">該当する購入履歴はありません。</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    // ── 返品処理ハンドラ ──────────────────────────────────────────────────────
    public static function handle_return_addon() {
        check_admin_referer( 'kogu_return_addon' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '権限がありません。' );

        global $wpdb;
        $purchase_id      = (int) ( $_POST['purchase_id']      ?? 0 );
        $addon_product_id = (int) ( $_POST['addon_product_id'] ?? 0 );
        $return_qty       = max( 1, (int) ( $_POST['return_qty'] ?? 1 ) );
        $return_reason    = sanitize_text_field( $_POST['return_reason'] ?? '' );
        $restore_stock    = ! empty( $_POST['restore_stock'] );

        $table    = Kogu_Database::rental_addons_table();
        $purchase = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $purchase_id ) );
        if ( ! $purchase ) wp_die( '購入データが見つかりません。' );

        $remaining = (int) $purchase->quantity - (int) $purchase->returned_quantity;
        $return_qty = min( $return_qty, $remaining );

        $new_returned = (int) $purchase->returned_quantity + $return_qty;
        $new_status   = $new_returned >= (int) $purchase->quantity ? 'returned' : 'partially_returned';

        $wpdb->update( $table, [
            'status'            => $new_status,
            'returned_quantity' => $new_returned,
            'returned_at'       => current_time( 'mysql' ),
            'return_reason'     => $return_reason,
        ], [ 'id' => $purchase_id ] );

        if ( $restore_stock ) {
            Kogu_Database::restore_addon_stock( $addon_product_id, $return_qty );
        }

        wp_redirect( admin_url( 'admin.php?page=kogu-addon-history&returned=1' ) );
        exit;
    }

    // ── 設定画面 ──────────────────────────────────────────────────────────────
    // ── 同梱紙（印刷専用ページ）────────────────────────────────────────────────
    public static function page_packing_slip() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '不正なアクセスです。' );
        }

        global $wpdb;
        $rtbl = Kogu_Database::rentals_table();
        $ptbl = Kogu_Database::products_table();

        $reservation_number = isset( $_GET['reservation_number'] )
            ? sanitize_text_field( strtoupper( $_GET['reservation_number'] ) )
            : '';
        $rental_id_param = isset( $_GET['rental_id'] ) ? (int) $_GET['rental_id'] : 0;

        $rentals = [];

        if ( $reservation_number ) {
            $rentals = $wpdb->get_results( $wpdb->prepare(
                "SELECT r.*, p.name AS product_name
                 FROM $rtbl r LEFT JOIN $ptbl p ON p.id = r.product_id
                 WHERE r.reservation_number = %s
                 ORDER BY r.id ASC",
                $reservation_number
            ) );
        }

        // reservation_number で見つからない場合は rental_id にフォールバック
        if ( empty( $rentals ) && $rental_id_param ) {
            $single = $wpdb->get_row( $wpdb->prepare(
                "SELECT r.*, p.name AS product_name
                 FROM $rtbl r LEFT JOIN $ptbl p ON p.id = r.product_id
                 WHERE r.id = %d",
                $rental_id_param
            ) );
            if ( $single ) {
                if ( $single->reservation_number ) {
                    // reservation_number が振られていればそれで全件取得
                    $rentals = $wpdb->get_results( $wpdb->prepare(
                        "SELECT r.*, p.name AS product_name
                         FROM $rtbl r LEFT JOIN $ptbl p ON p.id = r.product_id
                         WHERE r.reservation_number = %s
                         ORDER BY r.id ASC",
                        $single->reservation_number
                    ) );
                } elseif ( $single->stripe_payment_intent_id ) {
                    // reservation_number が空の旧データ → 同一 PaymentIntent で同一注文を特定
                    $rentals = $wpdb->get_results( $wpdb->prepare(
                        "SELECT r.*, p.name AS product_name
                         FROM $rtbl r LEFT JOIN $ptbl p ON p.id = r.product_id
                         WHERE r.stripe_payment_intent_id = %s
                         ORDER BY r.id ASC",
                        $single->stripe_payment_intent_id
                    ) );
                } else {
                    $rentals = [ $single ];
                }
            }
        }

        if ( empty( $rentals ) ) {
            wp_die( '予約が見つかりません。' );
        }

        $first          = $rentals[0];
        $name           = esc_html( $first->guest_name ?: ( $first->user_id ? get_userdata( $first->user_id )->display_name : '' ) );

        // GETパラメータにない場合はDBから補完。旧データは rental_id で代替表示
        if ( ! $reservation_number ) {
            $reservation_number = $first->reservation_number ?: ( 'レンタル #' . implode( ', #', array_map( fn( $r ) => (int) $r->id, $rentals ) ) );
        }
        $reservation    = esc_html( $reservation_number );
        $from_email     = esc_html( get_option( 'kogu_from_email', 'support@weekend-diy.com' ) );
        $return_address = get_option( 'kogu_return_address', '' );
        $service_name   = get_option( 'kogu_service_name', 'みんなのレンタル工具' );
        $addr_lines     = $return_address
            ? nl2br( esc_html( $return_address ) ) . '<br><strong>' . esc_html( $service_name ) . ' 行</strong>'
            : '<span style="color:#c0392b;">（管理画面 → 設定 → 返送先住所を設定してください）</span>';

        // 複数商品で最も遅い返却期限を代表値として使用
        $latest_end_date = max( array_map( fn( $r ) => $r->rental_end_date, $rentals ) );
        $multi           = count( $rentals ) > 1;
        ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>同梱紙 <?php echo $reservation; ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "Hiragino Kaku Gothic ProN", Meiryo, sans-serif; font-size: 13px; color: #1f1d1a; background: #f5f5f5; }
  .slip { width: 190mm; min-height: 277mm; margin: 8mm auto; padding: 12mm; background: #fff; border: 1px solid #ccc; }
  .header { background: #1f1d1a; color: #fff; padding: 8px 12px; border-radius: 4px 4px 0 0; display: flex; justify-content: space-between; align-items: center; }
  .header .title { font-size: 16px; font-weight: bold; }
  .header .label { font-size: 11px; background: #e85a2b; padding: 2px 10px; border-radius: 3px; letter-spacing: 1px; }
  .thank-you { border: 1px solid #e0d8c8; border-top: none; border-radius: 0 0 4px 4px; padding: 8px 12px; font-size: 12px; line-height: 1.8; color: #444; }
  .reservation-box { text-align: center; border: 2px solid #e85a2b; border-radius: 6px; padding: 10px; margin: 10px 0; }
  .reservation-box .label { font-size: 11px; color: #888; margin-bottom: 2px; }
  .reservation-box .number { font-size: 30px; font-weight: 900; letter-spacing: 3px; color: #e85a2b; }
  table.info { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 12px; }
  table.info th { width: 30%; padding: 5px 8px; background: #f6f1e6; font-weight: bold; vertical-align: top; border: 1px solid #ddd; }
  table.info td { padding: 5px 8px; border: 1px solid #ddd; vertical-align: top; }
  .item-row td { background: #fff; }
  .item-row:nth-child(even) td { background: #fafafa; }
  .deadline-badge { display: inline-block; background: #e85a2b; color: #fff; font-size: 14px; font-weight: bold; padding: 2px 10px; border-radius: 4px; }
  .section-title { font-size: 13px; font-weight: bold; border-left: 3px solid #e85a2b; padding-left: 8px; margin: 14px 0 6px; }
  ol.steps { padding-left: 20px; font-size: 12px; line-height: 2.3; }
  .address-box { background: #f6f1e6; border-left: 3px solid #e85a2b; padding: 8px 14px; font-size: 14px; line-height: 2; margin: 6px 0; }
  .note { font-size: 10px; color: #888; line-height: 1.8; margin-top: 8px; }
  .contact { margin-top: 12px; font-size: 11px; color: #666; border-top: 1px dashed #ccc; padding-top: 8px; }
  .no-print { text-align: center; padding: 12px; background: #e8f0fe; font-size: 13px; }
  @media print {
    body { background: #fff; }
    .slip { border: none; margin: 0; width: 100%; box-shadow: none; }
    .no-print { display: none; }
  }
</style>
</head>
<body>
<div class="no-print">
  <button onclick="window.print()" style="padding:8px 24px;background:#e85a2b;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;margin-right:8px;">🖨 印刷する</button>
  <a href="javascript:window.close()" style="font-size:13px;color:#666;">閉じる</a>
</div>
<div class="slip">
  <div class="header">
    <span class="title">みんなのレンタル工具</span>
    <span class="label">返却のしおり</span>
  </div>
  <div class="thank-you">
    <?php echo $name; ?> 様、ご利用ありがとうございます。返却期限日までにご返送をお願いいたします。
  </div>

  <div class="reservation-box">
    <div class="label">予約番号</div>
    <div class="number"><?php echo $reservation; ?></div>
  </div>

  <table class="info">
    <tr><th>お名前</th><td><?php echo $name; ?> 様</td></tr>
    <?php if ( $multi ) : ?>
      <tr>
        <th>ご注文内容</th>
        <td>
          <table style="width:100%;border-collapse:collapse;font-size:11px;">
            <thead>
              <tr style="background:#f6f1e6;">
                <th style="padding:3px 6px;text-align:left;border-bottom:1px solid #ddd;">商品</th>
                <th style="padding:3px 6px;text-align:left;border-bottom:1px solid #ddd;">返却期限</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $rentals as $r ) : ?>
              <tr class="item-row">
                <td style="padding:3px 6px;"><?php echo esc_html( $r->product_name ?? '工具' ); ?></td>
                <td style="padding:3px 6px;"><span class="deadline-badge" style="font-size:11px;"><?php echo esc_html( $r->rental_end_date ); ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </td>
      </tr>
    <?php else : ?>
      <tr><th>商品</th><td><?php echo esc_html( $rentals[0]->product_name ?? '工具' ); ?></td></tr>
      <tr>
        <th>返却期限</th>
        <td><span class="deadline-badge"><?php echo esc_html( $latest_end_date ); ?></span> までに発送</td>
      </tr>
    <?php endif; ?>
    <?php if ( $multi ) : ?>
      <tr>
        <th>返却期限（最終）</th>
        <td><span class="deadline-badge"><?php echo esc_html( $latest_end_date ); ?></span> までに全て発送</td>
      </tr>
    <?php endif; ?>
  </table>

  <div class="section-title">返却手順</div>
  <ol class="steps">
    <li>工具と付属品を<strong>同梱の緩衝材で包む</strong></li>
    <li>ダンボール箱に入れ、隙間に緩衝材を詰めてガムテープで封をする</li>
    <li>最寄りの<strong>郵便局・コンビニ（ローソン / ミニストップ）</strong>で<br>
        <strong>「ゆうパック 着払い」</strong>で発送（同梱の伝票をご利用ください）</li>
  </ol>
  <p class="note">
    ※ 期限日までに「発送」していれば到着が翌日以降でも問題ありません。<br>
    ※ 他の手段で返送する際は、記載の返送先にご送付ください（送料はお客様のご負担となります）。<br>
    ※ 商品の破損・紛失があった場合は、ご登録のクレジットカードに実費を請求いたします。<br>
    ※ 期限超過はレンタル料金を日割りした延滞料金が登録カードに発生します。
  </p>

  <div class="section-title">返送先</div>
  <div class="address-box"><?php echo $addr_lines; ?></div>

  <div class="contact">お問い合わせ：<?php echo $from_email; ?></div>
</div>
</body>
</html>
        <?php
        exit;
    }

    public static function register_settings() {
        $fields = [
            'kogu_stripe_public_key'     => 'Stripe 公開鍵（pk_live_...）',
            'kogu_stripe_secret_key'     => 'Stripe 秘密鍵（sk_live_...）',
            'kogu_stripe_webhook_secret' => 'Stripe Webhook シークレット（whsec_...）',
            'kogu_sendgrid_api_key'      => 'SendGrid APIキー',
            'kogu_from_email'            => '送信元メールアドレス',
            'kogu_from_name'             => '送信元名',
            'kogu_noindex_slugs'         => 'noindex にするページスラッグ（カンマ区切り）',
            'kogu_service_name'          => 'サービス名（返送先の「〇〇 行」に使用）',
            'kogu_shipping_fee'          => '送料（円）',
            'kogu_free_shipping_threshold' => '送料無料しきい値（円）',
            'kogu_return_buffer_days'    => '折り返しバッファ日数（返却期限後に在庫をブロックする日数）',
            'kogu_return_address'        => '返送先住所（予約確認メール・同梱紙に表示）',
            'kogu_gsc_verification'      => 'Google Search Console 検証コード',
            'kogu_gtm_container_id'      => 'Google Tag Manager コンテナID',
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
                  <tr>
                    <th><label for="kogu_gsc_verification">Google Search Console 検証コード</label></th>
                    <td>
                      <input type="text" id="kogu_gsc_verification" name="kogu_gsc_verification"
                             value="<?php echo esc_attr( get_option( 'kogu_gsc_verification' ) ); ?>"
                             class="large-text" placeholder="例: google1234abcd5678efgh" />
                      <p class="description">
                        Google Search Console の「HTMLタグ」方式で取得したコンテンツ値を入力してください。<br>
                        例: <code>&lt;meta name="google-site-verification" content="<strong>ここの値</strong>" /&gt;</code>
                      </p>
                    </td>
                  </tr>
                  <tr>
                    <th><label for="kogu_gtm_container_id">Google Tag Manager コンテナID</label></th>
                    <td>
                      <input type="text" id="kogu_gtm_container_id" name="kogu_gtm_container_id"
                             value="<?php echo esc_attr( get_option( 'kogu_gtm_container_id' ) ); ?>"
                             class="regular-text" placeholder="例: GTM-XXXXXXX" />
                      <p class="description">GTMダッシュボードで確認できるコンテナID（GTM-から始まる）を入力してください。</p>
                    </td>
                  </tr>
                  <tr>
                    <th><label for="kogu_shipping_fee">送料（円）</label></th>
                    <td>
                      <input type="number" id="kogu_shipping_fee" name="kogu_shipping_fee"
                             value="<?php echo (int) get_option( 'kogu_shipping_fee', 2500 ); ?>"
                             min="0" class="small-text" /> 円
                      <p class="description">送料無料しきい値未満のとき加算される送料。<strong>0にすると常に送料無料</strong>になります。</p>
                    </td>
                  </tr>
                  <tr>
                    <th><label for="kogu_free_shipping_threshold">送料無料しきい値（円）</label></th>
                    <td>
                      <input type="number" id="kogu_free_shipping_threshold" name="kogu_free_shipping_threshold"
                             value="<?php echo (int) get_option( 'kogu_free_shipping_threshold', 3000 ); ?>"
                             min="0" class="small-text" /> 円以上で送料無料
                      <p class="description"><strong>0にすると常に送料無料</strong>（テスト時に便利）。通常は 3000。</p>
                    </td>
                  </tr>
                  <tr>
                    <th><label for="kogu_return_buffer_days">折り返しバッファ日数</label></th>
                    <td>
                      <input type="number" id="kogu_return_buffer_days" name="kogu_return_buffer_days"
                             value="<?php echo (int) get_option( 'kogu_return_buffer_days', 4 ); ?>"
                             min="0" max="14" class="small-text" />
                      <p class="description">
                        返却期限日から何日間、在庫をブロックするかを設定します。<br>
                        例: <code>4</code> → 返却期限 6/1 の場合、6/5 以降が次の貸し出し可能日になります。<br>
                        <strong>デフォルト: 4日</strong>（郵送3日＋検品1日想定）
                      </p>
                    </td>
                  </tr>
                  <tr>
                    <th><label for="kogu_service_name">サービス名</label></th>
                    <td>
                      <input type="text" id="kogu_service_name" name="kogu_service_name"
                             value="<?php echo esc_attr( get_option( 'kogu_service_name', 'みんなのレンタル工具' ) ); ?>"
                             class="regular-text" />
                      <p class="description">返送先住所の末尾に「〇〇 行」として表示されます。同梱紙・確認メールに反映。</p>
                    </td>
                  </tr>
                  <tr>
                    <th><label for="kogu_return_address">返送先住所</label></th>
                    <td>
                      <textarea id="kogu_return_address" name="kogu_return_address" rows="4" class="large-text"><?php echo esc_textarea( get_option( 'kogu_return_address', '' ) ); ?></textarea>
                      <p class="description">予約確認メール・発送通知・同梱紙に表示される返送先住所を入力してください（例: 〒123-4567 東京都○○市○○1-2-3）。</p>
                    </td>
                  </tr>
                  <tr>
                    <th><label for="kogu_noindex_slugs">検索インデックスさせないページ</label></th>
                    <td>
                      <input type="text" id="kogu_noindex_slugs" name="kogu_noindex_slugs"
                             value="<?php echo esc_attr( get_option( 'kogu_noindex_slugs', 'tokushoho,my-page,privacy,terms' ) ); ?>"
                             class="large-text" placeholder="例: tokushoho,my-page,privacy,terms" />
                      <p class="description">
                        Googleにインデックスさせたくないページの<strong>スラッグ</strong>をカンマ区切りで入力してください。<br>
                        <code>&lt;meta name="robots" content="noindex, nofollow"&gt;</code> が自動で出力されます。<br>
                        <strong>デフォルト:</strong> <code>tokushoho, my-page, privacy, terms</code>
                      </p>
                    </td>
                  </tr>
            </table>
            <p class="description">料金・デポジットは「<a href="?page=kogu-products">商品管理</a>」で商品ごとに設定してください。</p>
            <?php submit_button( '設定を保存' ); ?>
          </form>
          <hr>
          <h2>Webhook URL（Stripeダッシュボードに登録）</h2>
          <code><?php echo esc_html( home_url( '/wp-json/kogu/v1/stripe-webhook' ) ); ?></code>

          <hr>
          <h2>データベース初期化</h2>
          <?php if ( isset( $_GET['installed'] ) ) : ?>
            <div class="notice notice-success"><p>✅ テーブルの作成・更新が完了しました。</p></div>
          <?php endif; ?>
          <p>在庫テーブルなどが存在しない場合はこちらで作成できます。</p>
          <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="kogu_run_install" />
            <?php wp_nonce_field( 'kogu_run_install' ); ?>
            <?php submit_button( 'DBテーブルを作成・更新する', 'secondary' ); ?>
          </form>
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
            $tracking_out    = sanitize_text_field( $_POST['tracking_outbound'] ?? '' );
            $tracking_return = sanitize_text_field( $_POST['tracking_return_label'] ?? '' );
            if ( $tracking_out === '' || $tracking_return === '' ) {
                wp_die( '往路追跡番号と返却用送り状番号の両方を入力してください。', '入力エラー', [ 'back_link' => true ] );
            }
            Kogu_Rental_Manager::mark_shipped_to_customer( $rental_id, $tracking_out, $tracking_return );
        }

        if ( $op === 'submit_return' ) {
            $tracking = sanitize_text_field( $_POST['tracking_return'] ?? '' );
            Kogu_Rental_Manager::submit_return_evidence( $rental_id, $tracking );
        }

        if ( $op === 'confirm_return' ) {
            $damage_fee        = (int) ( $_POST['damage_fee'] ?? 0 );
            $late_fee_override = isset( $_POST['late_fee_override'] ) ? (int) $_POST['late_fee_override'] : null;
            $damage_reason     = sanitize_textarea_field( $_POST['damage_reason'] ?? '' );
            Kogu_Rental_Manager::confirm_return( $rental_id, $damage_fee, $late_fee_override, $damage_reason );
        }

        wp_redirect( admin_url( 'admin.php?page=kogu-rentals&detail=' . $rental_id . '&updated=1' ) );
        exit;
    }

    // ── 購入商品保存ハンドラ ──────────────────────────────────────────────────
    public static function handle_save_addon() {
        check_admin_referer( 'kogu_addon_action' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '権限がありません。' );

        $addon_id     = (int) ( $_POST['addon_id'] ?? 0 );
        $stock_input  = $_POST['stock_quantity'] ?? '';
        $stock_quantity = ( $stock_input === '' || $stock_input === null )
            ? null
            : max( 0, (int) $stock_input );

        $allowed_addon_cats = array_keys( Kogu_Database::addon_categories() );
        $category = in_array( $_POST['category'] ?? '', $allowed_addon_cats, true ) ? $_POST['category'] : 'CS';

        $data = [
            'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
            'category'       => $category,
            'description'    => sanitize_text_field( $_POST['description'] ?? '' ),
            'price'          => max( 0, (int) ( $_POST['price'] ?? 0 ) ),
            'unit'           => sanitize_text_field( $_POST['unit'] ?? '個' ),
            'image'          => sanitize_text_field( $_POST['image'] ?? '' ),
            'stock_quantity' => $stock_quantity,
            'status'         => in_array( $_POST['status'] ?? '', [ 'active', 'inactive' ] ) ? $_POST['status'] : 'active',
        ];

        global $wpdb;
        if ( $addon_id ) {
            $wpdb->update( Kogu_Database::addon_products_table(), $data, [ 'id' => $addon_id ] );
        } else {
            $wpdb->insert( Kogu_Database::addon_products_table(), $data );
            $new_addon_id = $wpdb->insert_id;
            $auto_serial  = Kogu_Database::generate_serial( 'A', $category, $new_addon_id );
            $wpdb->update(
                Kogu_Database::addon_products_table(),
                [ 'serial_number' => $auto_serial ],
                [ 'id' => $new_addon_id ]
            );
        }

        wp_redirect( admin_url( 'admin.php?page=kogu-addons&updated=1' ) );
        exit;
    }

    // ── 商品保存ハンドラ ──────────────────────────────────────────────────────
    public static function handle_save_product() {
        check_admin_referer( 'kogu_product_action' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '権限がありません。' );

        $product_id    = (int) ( $_POST['product_id'] ?? 0 );
        $name          = sanitize_text_field( $_POST['name'] ?? '' );
        $description   = sanitize_textarea_field( $_POST['description'] ?? '' );
        $price_per_week = max( 1, (int) ( $_POST['price_per_week'] ?? 4900 ) );
        $deposit_amount = max( 0, (int) ( $_POST['deposit_amount'] ?? 10000 ) );
        $status        = in_array( $_POST['status'] ?? '', [ 'active', 'inactive' ] )
            ? $_POST['status'] : 'active';

        global $wpdb;
        $slug     = sanitize_title( $_POST['slug'] ?? '' ) ?: sanitize_title( $name );
        $contents = sanitize_textarea_field( $_POST['contents'] ?? '' );
        $specs    = sanitize_textarea_field( $_POST['specs']    ?? '' );
        $gallery  = sanitize_text_field( $_POST['gallery'] ?? '' );
        $allowed_cats = array_keys( Kogu_Database::product_categories() );
        $category = in_array( $_POST['category'] ?? '', $allowed_cats, true ) ? $_POST['category'] : 'XX';
        $data = compact( 'name', 'slug', 'category', 'description', 'contents', 'specs', 'gallery', 'price_per_week', 'deposit_amount', 'status' );
        $data['allows_addons'] = isset( $_POST['allows_addons'] ) ? 1 : 0;

        if ( $product_id ) {
            $wpdb->update( Kogu_Database::products_table(), $data, [ 'id' => $product_id ] );
        } else {
            $wpdb->insert( Kogu_Database::products_table(), $data );
            $product_id = $wpdb->insert_id;
        }

        wp_redirect( admin_url( 'admin.php?page=kogu-products&updated=1' ) );
        exit;
    }

    // ── 工具リクエスト一覧 ────────────────────────────────────────────────────
    public static function page_tool_requests() {
        global $wpdb;
        $tbl = Kogu_Database::tool_requests_table();

        if ( ! empty( $_GET['notified'] ) ) {
            echo '<div class="notice notice-success is-dismissible"><p>通知メールを送信しました。</p></div>';
        }

        $status_filter = sanitize_text_field( $_GET['status'] ?? '' );
        $where = $status_filter ? $wpdb->prepare( 'WHERE status = %s', $status_filter ) : '';
        $rows  = $wpdb->get_results( "SELECT * FROM $tbl $where ORDER BY created_at DESC LIMIT 300" );
        ?>
        <div class="wrap">
          <h1>💡 工具リクエスト一覧</h1>
          <div style="display:flex;gap:8px;margin-bottom:16px;">
            <a href="?page=kogu-tool-requests" class="button <?php echo ! $status_filter ? 'button-primary' : ''; ?>">すべて</a>
            <a href="?page=kogu-tool-requests&status=pending" class="button <?php echo $status_filter === 'pending' ? 'button-primary' : ''; ?>">未通知</a>
            <a href="?page=kogu-tool-requests&status=notified" class="button <?php echo $status_filter === 'notified' ? 'button-primary' : ''; ?>">通知済み</a>
          </div>
          <table class="wp-list-table widefat fixed striped">
            <thead>
              <tr>
                <th style="width:60px">ID</th>
                <th>希望工具</th>
                <th>メールアドレス</th>
                <th style="width:80px">ステータス</th>
                <th>リクエスト日時</th>
                <th style="width:120px">操作</th>
              </tr>
            </thead>
            <tbody>
              <?php if ( empty( $rows ) ) : ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;color:#888;">リクエストはまだありません</td></tr>
              <?php else : ?>
                <?php foreach ( $rows as $row ) : ?>
                  <tr>
                    <td><?php echo (int) $row->id; ?></td>
                    <td><strong><?php echo esc_html( $row->tool_name ); ?></strong></td>
                    <td><?php echo esc_html( $row->email ); ?></td>
                    <td>
                      <?php if ( $row->status === 'notified' ) : ?>
                        <span style="color:#27ae60;font-weight:700;">✓ 通知済</span>
                      <?php else : ?>
                        <span style="color:#e85a2b;">未通知</span>
                      <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $row->created_at ); ?></td>
                    <td>
                      <?php if ( $row->status === 'pending' ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
                          <?php wp_nonce_field( 'kogu_notify_tool_request' ); ?>
                          <input type="hidden" name="action" value="kogu_notify_tool_request" />
                          <input type="hidden" name="request_id" value="<?php echo (int) $row->id; ?>" />
                          <button type="submit" class="button button-primary" style="font-size:12px;"
                            onclick="return confirm('「<?php echo esc_js( $row->tool_name ); ?>」の入荷通知を <?php echo esc_js( $row->email ); ?> に送信しますか？')">
                            入荷通知を送る
                          </button>
                        </form>
                      <?php else : ?>
                        <span style="color:#999;font-size:12px;"><?php echo esc_html( substr( $row->notified_at, 0, 10 ) ); ?> 送信済</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    // ── 入荷通知メール送信 ────────────────────────────────────────────────────
    public static function handle_notify_tool_request() {
        check_admin_referer( 'kogu_notify_tool_request' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( '権限がありません。' );

        global $wpdb;
        $id  = (int) ( $_POST['request_id'] ?? 0 );
        $tbl = Kogu_Database::tool_requests_table();
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $tbl WHERE id = %d", $id ) );

        if ( $row && $row->status === 'pending' ) {
            $site = get_bloginfo( 'name' );
            $url  = home_url( '/rental/' );
            $body = "こんにちは！\n\n"
                . "先日リクエストいただいた「{$row->tool_name}」が入荷しました。\n\n"
                . "ぜひこちらからレンタルをお試しください👇\n"
                . "{$url}\n\n"
                . "引き続き {$site} をよろしくお願いいたします。";

            wp_mail( $row->email, "【{$site}】「{$row->tool_name}」が入荷しました！", $body );

            $wpdb->update(
                $tbl,
                [ 'status' => 'notified', 'notified_at' => current_time( 'mysql' ) ],
                [ 'id' => $id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
        }

        wp_redirect( admin_url( 'admin.php?page=kogu-tool-requests&notified=1' ) );
        exit;
    }
}
