<?php
defined( 'ABSPATH' ) || exit;

// 予約番号で検索
$reservation_number = '';
$rentals            = [];
$lookup_done        = false;
$error_msg          = '';

if ( ! empty( $_POST['reservation_number'] ) && check_admin_referer( 'kogu_mypage_lookup' ) ) {
    $reservation_number = strtoupper( sanitize_text_field( $_POST['reservation_number'] ) );
    // スペース・ハイフンを正規化
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
  <?php if ( $lookup_done && ! empty( $rentals ) ) : ?>
    <div class="kogu-rn-result-header">
      <span class="kogu-rn-result-label">予約番号</span>
      <strong class="kogu-rn-result-number"><?php echo esc_html( $reservation_number ); ?></strong>
    </div>

    <div class="kogu-rental-list">
      <?php foreach ( $rentals as $r ) :
        $label      = $status_labels[ $r->status ] ?? $r->status;
        $is_active  = in_array( $r->status, [ 'shipped_to_customer', 'active', 'overdue' ], true );
        $is_overdue = $r->status === 'overdue';
        $product    = Kogu_Database::get_product( (int) $r->product_id );

        // 返却期限まで残り日数
        $today        = new DateTime( 'today' );
        $end_dt       = new DateTime( $r->rental_end_date );
        $days_left    = (int) $today->diff( $end_dt )->days * ( $today <= $end_dt ? 1 : -1 );
        $can_extend   = Kogu_Rental_Manager::can_extend( (int) $r->id );
      ?>
        <div class="kogu-rental-card <?php echo $is_overdue ? 'kogu-overdue' : ''; ?>">
          <div class="kogu-rental-card-header">
            <span class="kogu-rental-id">レンタル #<?php echo (int) $r->id; ?></span>
            <span class="kogu-status-badge kogu-status-<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( $label ); ?></span>
          </div>
          <div class="kogu-rental-card-body">
            <div class="kogu-info-row"><span>商品</span><strong><?php echo esc_html( $product->name ?? '—' ); ?></strong></div>
            <div class="kogu-info-row"><span>貸出開始</span><strong><?php echo esc_html( $r->rental_start_date ); ?></strong></div>
            <div class="kogu-info-row <?php echo $is_overdue ? 'kogu-warn' : ''; ?>">
              <span>返却期限</span>
              <strong>
                <?php echo esc_html( $r->rental_end_date ); ?>
                <?php if ( in_array( $r->status, [ 'confirmed', 'shipped_to_customer', 'active', 'overdue' ], true ) ) : ?>
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
            <div class="kogu-info-row"><span>レンタル期間</span><strong><?php echo (int) $r->rental_weeks; ?>週間</strong></div>
            <?php if ( $r->rental_fee ) : ?>
              <div class="kogu-info-row"><span>レンタル料金</span><strong>¥<?php echo number_format( $r->rental_fee ); ?></strong></div>
            <?php endif; ?>
            <?php if ( $r->late_fee_total ) : ?>
              <div class="kogu-info-row kogu-warn"><span>延滞料金</span><strong>¥<?php echo number_format( $r->late_fee_total ); ?>（<?php echo (int) $r->late_fee_days; ?>日分）</strong></div>
            <?php endif; ?>
            <?php if ( $r->tracking_outbound ) : ?>
              <div class="kogu-info-row">
                <span>追跡番号（往路）</span>
                <strong>
                  <a href="https://www.post.japanpost.jp/cgi-yubin/navi/DispList.do?number=<?php echo esc_attr( $r->tracking_outbound ); ?>" target="_blank" rel="noopener">
                    <?php echo esc_html( $r->tracking_outbound ); ?>
                  </a>
                </strong>
              </div>
            <?php endif; ?>
          </div>

          <?php if ( $can_extend ) :
            $product_for_ext = Kogu_Database::get_product( (int) $r->product_id );
            $ext_fee = $product_for_ext
                ? (int) round( (int) $product_for_ext->price_per_week * 0.70 )
                : 0;
            $new_end_preview = date( 'Y-m-d', strtotime( $r->rental_end_date . ' +7 days' ) );
          ?>
          <div class="kogu-extend-section" id="kogu-extend-<?php echo (int) $r->id; ?>">
            <div class="kogu-extend-info">
              <p class="kogu-extend-title">📅 レンタルを1週間延長する</p>
              <p class="kogu-extend-desc">
                延長後の返却期限: <strong><?php echo esc_html( $new_end_preview ); ?></strong>
                ／ 延長料金: <strong>¥<?php echo number_format( $ext_fee ); ?></strong>（30%OFF）
              </p>
              <p class="kogu-extend-note">ご登録のクレジットカードに即時請求されます。</p>
            </div>
            <button class="kogu-btn kogu-btn-extend"
                    data-rental-id="<?php echo (int) $r->id; ?>"
                    data-reservation-number="<?php echo esc_attr( $reservation_number ); ?>"
                    data-ext-fee="<?php echo (int) $ext_fee; ?>"
                    data-new-end="<?php echo esc_attr( $new_end_preview ); ?>">
              1週間延長する（¥<?php echo number_format( $ext_fee ); ?>）
            </button>
            <div class="kogu-extend-msg" style="display:none;"></div>
          </div>
          <?php endif; ?>

          <?php if ( $r->tracking_return ) : ?>
          <div class="kogu-return-complete">
            <p>📦 返送追跡番号: <strong><?php echo esc_html( $r->tracking_return ); ?></strong></p>
            <p>
              <a href="https://www.post.japanpost.jp/cgi-yubin/navi/DispList.do?number=<?php echo esc_attr( $r->tracking_return ); ?>" target="_blank" rel="noopener">
                ゆうパックで追跡する →
              </a>
            </p>
          </div>
          <?php endif; ?>

          <?php if ( $r->status === 'returned' ) : ?>
          <div class="kogu-return-complete">
            <p>✅ 返却完了。ありがとうございました。</p>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
