<?php
defined( 'ABSPATH' ) || exit;

class Kogu_Admin {

    public static function init() {
        add_action( 'admin_menu',                        [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_post_kogu_admin_action',      [ __CLASS__, 'handle_admin_action' ] );
        add_action( 'admin_post_kogu_save_product',      [ __CLASS__, 'handle_save_product' ] );
        add_action( 'admin_post_kogu_save_addon',        [ __CLASS__, 'handle_save_addon' ] );
        add_action( 'admin_post_kogu_run_install',       [ __CLASS__, 'handle_run_install' ] );
        add_action( 'admin_init',                        [ __CLASS__, 'register_settings' ] );
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
        add_submenu_page( 'kogu-rentals', '購入商品管理', '🛒 購入商品管理', 'manage_options', 'kogu-addons',   [ __CLASS__, 'page_addons' ] );
        add_submenu_page( 'kogu-rentals', '設定',         '設定',          'manage_options', 'kogu-settings',   [ __CLASS__, 'page_settings' ] );
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
          <h2>レンタル #<?php echo (int) $rental->id; ?> 詳細</h2>

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
            <input type="text" name="tracking_outbound" placeholder="ゆうパック追跡番号" required style="width:280px;padding:6px 10px;margin-right:8px;" />
            <button type="submit" class="button button-primary">発送済みにする</button>
          </form>
          <?php endif; ?>

          <!-- 管理者による返却手続き（顧客が追跡番号を提出しない場合） -->
          <?php if ( in_array( $rental->status, [ 'shipped_to_customer', 'active', 'overdue' ], true ) ) : ?>
          <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="margin-top:16px;">
            <input type="hidden" name="action"    value="kogu_admin_action">
            <input type="hidden" name="op"        value="submit_return">
            <input type="hidden" name="rental_id" value="<?php echo (int) $rental->id; ?>">
            <input type="hidden" name="_wpnonce"  value="<?php echo esc_attr( $nonce ); ?>">
            <h3>返却手続き（管理者入力）</h3>
            <p style="font-size:13px;color:#666;margin-bottom:8px;">顧客が追跡番号を提出しない場合、管理者がここから入力できます。</p>
            <div style="display:flex;gap:8px;align-items:center;">
              <input type="text" name="tracking_return" placeholder="ゆうパック追跡番号" required style="width:280px;padding:6px 10px;" />
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
            $wpdb->insert( $inv_table, [
                'product_id'  => $selected_pid,
                'unit_number' => $max + 1,
                'status'      => 'available',
                'condition'   => 'excellent',
            ] );
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
              <button type="submit" name="kogu_add_unit" class="button">＋ 1台追加</button>
            </div>
          </form>

          <h3>現在の空き状況</h3>
          <?php
          $today     = date( 'Y-m-d' );
          $available = Kogu_Inventory::available_count( $today, $today, $selected_pid );
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

    // ── 商品管理 ──────────────────────────────────────────────────────────────
    public static function page_products() {
        global $wpdb;
        $table = Kogu_Database::products_table();

        if ( isset( $_GET['updated'] ) ) {
            echo '<div class="notice notice-success"><p>保存しました。</p></div>';
        }

        $edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
        $edit    = $edit_id ? Kogu_Database::get_product( $edit_id ) : null;

        $nonce   = wp_create_nonce( 'kogu_product_action' );
        $products = Kogu_Database::get_active_products();
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
                  <th><label for="pdesc">説明</label></th>
                  <td><textarea id="pdesc" name="description" rows="3" class="large-text"><?php echo esc_textarea( $edit->description ?? '' ); ?></textarea></td>
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
                $discounted = (int) round( $p->price_per_week * 0.7 );
              ?>
                <tr>
                  <td><?php echo (int) $p->id; ?></td>
                  <td><strong><?php echo esc_html( $p->name ); ?></strong><br><small><?php echo esc_html( mb_strimwidth( $p->description, 0, 50, '…' ) ); ?></small></td>
                  <td>¥<?php echo number_format( $p->price_per_week ); ?></td>
                  <td>¥<?php echo number_format( $discounted ); ?>/週 <small style="color:#888;">(30%OFF)</small></td>
                  <td>¥<?php echo number_format( $p->deposit_amount ); ?></td>
                  <td><?php echo $p->status === 'active' ? '<span style="color:#27ae60;">公開中</span>' : '<span style="color:#999;">非公開</span>'; ?></td>
                  <td><?php echo $inv_count; ?>台</td>
                  <td>
                    <a href="?page=kogu-products&edit=<?php echo (int) $p->id; ?>" class="button button-small">編集</a>
                    <a href="?page=kogu-inventory&product_id=<?php echo (int) $p->id; ?>" class="button button-small">在庫管理</a>
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
                <tr>
                  <th><label for="aname">商品名 <em>*</em></label></th>
                  <td><input type="text" id="aname" name="name" required class="regular-text"
                             value="<?php echo esc_attr( $edit->name ?? '' ); ?>" /></td>
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
                <th>商品名</th>
                <th>説明</th>
                <th>単価</th>
                <th>単位</th>
                <th>ステータス</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ( $all as $a ) : ?>
                <tr>
                  <td><?php echo (int) $a->id; ?></td>
                  <td><strong><?php echo esc_html( $a->name ); ?></strong></td>
                  <td><?php echo esc_html( $a->description ); ?></td>
                  <td>¥<?php echo number_format( $a->price ); ?></td>
                  <td><?php echo esc_html( $a->unit ); ?></td>
                  <td><?php echo $a->status === 'active' ? '<span style="color:#27ae60;">公開中</span>' : '<span style="color:#999;">非公開</span>'; ?></td>
                  <td><a href="?page=kogu-addons&edit=<?php echo (int) $a->id; ?>" class="button button-small">編集</a></td>
                </tr>
              <?php endforeach; ?>
              <?php if ( empty( $all ) ) : ?>
                <tr><td colspan="7" style="text-align:center;padding:24px;">購入商品が登録されていません。</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php
    }

    // ── 設定画面 ──────────────────────────────────────────────────────────────
    public static function register_settings() {
        $fields = [
            'kogu_stripe_public_key'     => 'Stripe 公開鍵（pk_live_...）',
            'kogu_stripe_secret_key'     => 'Stripe 秘密鍵（sk_live_...）',
            'kogu_stripe_webhook_secret' => 'Stripe Webhook シークレット（whsec_...）',
            'kogu_sendgrid_api_key'      => 'SendGrid APIキー',
            'kogu_from_email'            => '送信元メールアドレス',
            'kogu_from_name'             => '送信元名',
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
            $tracking = sanitize_text_field( $_POST['tracking_outbound'] ?? '' );
            Kogu_Rental_Manager::mark_shipped_to_customer( $rental_id, $tracking );
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

        $addon_id = (int) ( $_POST['addon_id'] ?? 0 );
        $data = [
            'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
            'description' => sanitize_text_field( $_POST['description'] ?? '' ),
            'price'       => max( 0, (int) ( $_POST['price'] ?? 0 ) ),
            'unit'        => sanitize_text_field( $_POST['unit'] ?? '個' ),
            'image'       => sanitize_text_field( $_POST['image'] ?? '' ),
            'status'      => in_array( $_POST['status'] ?? '', [ 'active', 'inactive' ] ) ? $_POST['status'] : 'active',
        ];

        global $wpdb;
        if ( $addon_id ) {
            $wpdb->update( Kogu_Database::addon_products_table(), $data, [ 'id' => $addon_id ] );
        } else {
            $wpdb->insert( Kogu_Database::addon_products_table(), $data );
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
        $data = compact( 'name', 'description', 'price_per_week', 'deposit_amount', 'status' );

        if ( $product_id ) {
            $wpdb->update( Kogu_Database::products_table(), $data, [ 'id' => $product_id ] );
        } else {
            $wpdb->insert( Kogu_Database::products_table(), $data );
            $product_id = $wpdb->insert_id;
        }

        wp_redirect( admin_url( 'admin.php?page=kogu-products&updated=1' ) );
        exit;
    }
}
