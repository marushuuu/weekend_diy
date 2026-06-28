<?php
defined( 'ABSPATH' ) || exit;

$reservation_number = '';
$rentals            = [];
$lookup_done        = false;
$error_msg          = '';

if ( ! empty( $_POST['reservation_number'] ) && check_admin_referer( 'kogu_mypage_lookup' ) ) {
    $reservation_number = strtoupper( sanitize_text_field( $_POST['reservation_number'] ) );
    $reservation_number = preg_replace( '/[\s　-]+/', '-', $reservation_number );
    $rentals            = Kogu_Database::get_rentals_by_reservation_number( $reservation_number );
    $lookup_done        = true;
    if ( empty( $rentals ) ) {
        $error_msg = 'この予約番号は見つかりませんでした。番号をご確認ください。';
    }
}

$status_labels = [
    'pending'                    => '確認中',
    'confirmed'                  => '予約確定',
    'shipped_to_customer'        => '発送済み',
    'active'                     => 'レンタル中',
    'return_evidence_submitted'  => '返却確認中',
    'returned'                   => '返却完了',
    'overdue'                    => '⚠️ 延滞中',
    'cancelled'                  => 'キャンセル',
];

$status_priority = [
    'overdue'                   => 7,
    'return_evidence_submitted' => 6,
    'active'                    => 5,
    'shipped_to_customer'       => 4,
    'confirmed'                 => 3,
    'pending'                   => 2,
    'returned'                  => 1,
    'cancelled'                 => 0,
];
?>

<div class="kogu-wrap kogu-mypage">
  <h2>マイページ</h2>
  <p class="kogu-mypage-intro">予約確認メールに記載の<strong>予約番号</strong>を入力すると、予約状況の確認ができます。</p>

  <!-- 予約番号入力フォーム -->
  <div class="kogu-rn-lookup">
    <form method="post" class="kogu-rn-form">
      <?php wp_nonce_field( 'kogu_mypage_lookup' ); ?>
      <div class="kogu-rn-input-row">
        <input type="text" name="reservation_number"
               class="kogu-rn-input"
               placeholder="例: KR-AB2CDE"
               value="<?php echo esc_attr( $reservation_number ); ?>"
               required maxlength="20"
               autocomplete="off" />
        <button type="submit" class="kogu-btn kogu-btn-primary">予約を確認する</button>
      </div>
      <?php if ( $error_msg ) : ?>
        <p class="kogu-error" style="margin-top:10px;"><?php echo esc_html( $error_msg ); ?></p>
      <?php endif; ?>
    </form>
  </div>

  <!-- 検索結果 -->
  <?php if ( $lookup_done && ! empty( $rentals ) ) :
    // ── 予約単位で集計 ──────────────────────────────────────────────────────
    $first          = $rentals[0];
    $latest_end     = max( array_map( fn( $r ) => $r->rental_end_date, $rentals ) );
    $total_fee      = array_sum( array_map( fn( $r ) => (int) $r->rental_fee, $rentals ) );
    $total_late_fee = array_sum( array_map( fn( $r ) => (int) $r->late_fee_total, $rentals ) );
    $tracking_out   = '';
    foreach ( $rentals as $r ) {
        if ( $r->tracking_outbound ) { $tracking_out = $r->tracking_outbound; break; }
    }
    $weeks = (int) $first->rental_weeks;

    // 商品名一覧
    $product_names = [];
    foreach ( $rentals as $r ) {
        $p = Kogu_Database::get_product( (int) $r->product_id );
        if ( $p ) $product_names[] = $p->name;
    }

    // 最も重要なステータスを代表値とする
    $worst_status = $rentals[0]->status;
    foreach ( $rentals as $r ) {
        if ( ( $status_priority[ $r->status ] ?? 0 ) > ( $status_priority[ $worst_status ] ?? 0 ) ) {
            $worst_status = $r->status;
        }
    }
    $label      = $status_labels[ $worst_status ] ?? $worst_status;
    $is_overdue = $worst_status === 'overdue';

    // 返却期限まで残り日数
    $today     = new DateTime( 'today' );
    $end_dt    = new DateTime( $latest_end );
    $days_left = (int) $today->diff( $end_dt )->days * ( $today <= $end_dt ? 1 : -1 );

    // 延長可否：全商品が延長可能なときのみ表示
    $can_extend_all = ! empty( $rentals );
    $total_ext_fee  = 0;
    foreach ( $rentals as $r ) {
        if ( ! Kogu_Rental_Manager::can_extend( (int) $r->id ) ) {
            $can_extend_all = false;
            break;
        }
        $prd = Kogu_Database::get_product( (int) $r->product_id );
        $total_ext_fee += $prd ? (int) round( (int) $prd->price_per_week * 0.70 ) : 0;
    }
    $new_end_preview = date( 'Y-m-d', strtotime( $latest_end . ' +7 days' ) );
  ?>

    <div class="kogu-rn-result-header">
      <span class="kogu-rn-result-label">予約番号</span>
      <strong class="kogu-rn-result-number"><?php echo esc_html( $reservation_number ); ?></strong>
    </div>

    <div class="kogu-rental-list">
      <div class="kogu-rental-card <?php echo $is_overdue ? 'kogu-overdue' : ''; ?>">
        <div class="kogu-rental-card-header">
          <span class="kogu-rental-id">予約 <?php echo esc_html( $reservation_number ); ?></span>
          <span class="kogu-status-badge kogu-status-<?php echo esc_attr( $worst_status ); ?>"><?php echo esc_html( $label ); ?></span>
        </div>
        <div class="kogu-rental-card-body">
          <!-- 商品 -->
          <?php if ( count( $product_names ) === 1 ) : ?>
            <div class="kogu-info-row"><span>商品</span><strong><?php echo esc_html( $product_names[0] ); ?></strong></div>
          <?php else : ?>
            <div class="kogu-info-row" style="align-items:flex-start;">
              <span>商品</span>
              <strong>
                <?php foreach ( $product_names as $i => $pn ) : ?>
                  <?php echo esc_html( $pn ); ?><?php if ( $i < count( $product_names ) - 1 ) echo '<br>'; ?>
                <?php endforeach; ?>
              </strong>
            </div>
          <?php endif; ?>

          <div class="kogu-info-row"><span>貸出開始</span><strong><?php echo esc_html( $first->rental_start_date ); ?></strong></div>

          <div class="kogu-info-row <?php echo $is_overdue ? 'kogu-warn' : ''; ?>">
            <span>返却期限</span>
            <strong>
              <?php echo esc_html( $latest_end ); ?>
              <?php if ( in_array( $worst_status, [ 'confirmed', 'shipped_to_customer', 'active', 'overdue' ], true ) ) : ?>
                <?php if ( $is_overdue ) : ?>
                  <span class="kogu-days-badge kogu-days-overdue">⚠️ <?php echo abs( $days_left ); ?>日超過</span>
                <?php elseif ( $days_left === 0 ) : ?>
                  <span class="kogu-days-badge kogu-days-today">今日が期限です</span>
                <?php elseif ( $days_left <= 3 ) : ?>
                  <span class="kogu-days-badge kogu-days-soon">残り<?php echo $days_left; ?>日</span>
                <?php else : ?>
                  <span class="kogu-days-badge">残り<?php echo $days_left; ?>日</span>
                <?php endif; ?>
              <?php endif; ?>
            </strong>
          </div>

          <div class="kogu-info-row"><span>レンタル期間</span><strong><?php echo $weeks; ?>週間</strong></div>

          <?php if ( $total_fee ) : ?>
            <div class="kogu-info-row"><span>レンタル料金<?php echo count( $product_names ) > 1 ? '合計' : ''; ?></span><strong>¥<?php echo number_format( $total_fee ); ?></strong></div>
          <?php endif; ?>

          <?php if ( $total_late_fee ) : ?>
            <div class="kogu-info-row kogu-warn">
              <span>延滞料金</span>
              <strong>¥<?php echo number_format( $total_late_fee ); ?></strong>
            </div>
          <?php endif; ?>

          <?php if ( $tracking_out ) : ?>
            <div class="kogu-info-row">
              <span>追跡番号（往路）</span>
              <strong>
                <a href="https://www.post.japanpost.jp/cgi-yubin/navi/DispList.do?number=<?php echo esc_attr( $tracking_out ); ?>" target="_blank" rel="noopener">
                  <?php echo esc_html( $tracking_out ); ?>
                </a>
              </strong>
            </div>
          <?php endif; ?>
        </div>

        <?php if ( $can_extend_all ) : ?>
        <div class="kogu-extend-section" id="kogu-extend-res">
          <div class="kogu-extend-info">
            <p class="kogu-extend-title">📅 レンタルを1週間延長する</p>
            <p class="kogu-extend-desc">
              延長後の返却期限: <strong><?php echo esc_html( $new_end_preview ); ?></strong>
              ／ 延長料金: <strong>¥<?php echo number_format( $total_ext_fee ); ?></strong>（30%OFF）
            </p>
            <?php if ( count( $rentals ) > 1 ) : ?>
              <p class="kogu-extend-desc" style="font-size:12px;color:#888;">
                <?php foreach ( $rentals as $idx => $r ) :
                  $prd = Kogu_Database::get_product( (int) $r->product_id );
                  $ef  = $prd ? (int) round( (int) $prd->price_per_week * 0.70 ) : 0;
                ?>
                  <?php echo esc_html( $prd->name ?? '—' ); ?>: ¥<?php echo number_format( $ef ); ?><?php echo $idx < count( $rentals ) - 1 ? ' ／ ' : ''; ?>
                <?php endforeach; ?>
              </p>
            <?php endif; ?>
            <p class="kogu-extend-note">ご登録のクレジットカードに即時請求されます。</p>
          </div>
          <button class="kogu-btn kogu-btn-extend"
                  data-reservation-number="<?php echo esc_attr( $reservation_number ); ?>"
                  data-ext-fee="<?php echo (int) $total_ext_fee; ?>"
                  data-new-end="<?php echo esc_attr( $new_end_preview ); ?>">
            1週間延長する（¥<?php echo number_format( $total_ext_fee ); ?>）
          </button>
          <div class="kogu-extend-msg" style="display:none;"></div>
        </div>
        <?php endif; ?>

        <?php if ( $worst_status === 'returned' ) : ?>
        <div class="kogu-return-complete">
          <p>✅ 返却完了。ありがとうございました。</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
