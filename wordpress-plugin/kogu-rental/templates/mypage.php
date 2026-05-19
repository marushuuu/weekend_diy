<?php
defined( 'ABSPATH' ) || exit;

$is_logged_in = is_user_logged_in();
$user_id      = get_current_user_id();

// ゲスト：メールアドレスで検索
$guest_email = sanitize_email( $_POST['guest_email'] ?? '' );

$rentals = [];
if ( $is_logged_in ) {
    $rentals = Kogu_Rental_Manager::get_by_user( $user_id );
} elseif ( $guest_email ) {
    $rentals = Kogu_Rental_Manager::get_by_email( $guest_email );
}

$status_labels = [
    'pending'                    => '確認中',
    'confirmed'                  => '予約確定',
    'shipped_to_customer'        => '発送済み',
    'active'                     => 'レンタル中',
    'return_evidence_submitted'  => '返却手続き済み',
    'returned'                   => '返却完了',
    'overdue'                    => '⚠️ 延滞中',
    'cancelled'                  => 'キャンセル',
];
?>

<div class="kogu-wrap kogu-mypage">
  <h2>マイページ</h2>

  <?php if ( $is_logged_in ) : ?>
    <p>ようこそ、<strong><?php echo esc_html( wp_get_current_user()->display_name ); ?></strong> さん。</p>
  <?php else : ?>
    <!-- ゲスト用：メールアドレスで履歴検索 -->
    <div class="kogu-guest-lookup">
      <p>ゲストでご利用の方は、ご注文時のメールアドレスで履歴を確認できます。</p>
      <form method="post">
        <?php wp_nonce_field( 'kogu_guest_lookup' ); ?>
        <div class="kogu-form-row">
          <label>メールアドレス</label>
          <input type="email" name="guest_email" required placeholder="example@email.com"
                 value="<?php echo esc_attr( $guest_email ); ?>" />
        </div>
        <button type="submit" class="kogu-btn kogu-btn-primary">履歴を確認する</button>
      </form>
    </div>
    <hr class="kogu-divider" />
    <p>
      <a href="<?php echo wp_login_url( get_permalink() ); ?>">ログイン</a> /
      <a href="<?php echo wp_registration_url(); ?>">会員登録</a>
    </p>
  <?php endif; ?>

  <!-- ── レンタル一覧 ────────────────────────────────────────────────── -->
  <?php if ( ! empty( $rentals ) ) : ?>
    <h3>レンタル履歴</h3>
    <div class="kogu-rental-list">
      <?php foreach ( $rentals as $r ) :
        $label  = $status_labels[ $r->status ] ?? $r->status;
        $is_active = in_array( $r->status, [ 'shipped_to_customer', 'active', 'overdue' ], true );
        $is_overdue = $r->status === 'overdue';
      ?>
        <div class="kogu-rental-card <?php echo $is_overdue ? 'kogu-overdue' : ''; ?>">
          <div class="kogu-rental-card-header">
            <span class="kogu-rental-id">レンタル #<?php echo esc_html( $r->id ); ?></span>
            <span class="kogu-status-badge kogu-status-<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( $label ); ?></span>
          </div>
          <div class="kogu-rental-card-body">
            <div class="kogu-info-row"><span>商品</span><strong>インパクトドライバー</strong></div>
            <div class="kogu-info-row"><span>貸出開始</span><strong><?php echo esc_html( $r->rental_start_date ); ?></strong></div>
            <div class="kogu-info-row"><span>返却期限</span><strong><?php echo esc_html( $r->rental_end_date ); ?></strong></div>
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
                  <a href="https://www.post.japanpost.jp/cgi-yubin/navi/DispList.do?number=<?php echo esc_attr( $r->tracking_outbound ); ?>" target="_blank">
                    <?php echo esc_html( $r->tracking_outbound ); ?>
                  </a>
                </strong>
              </div>
            <?php endif; ?>
          </div>

          <?php if ( $is_active ) : ?>
          <!-- 返却手続きフォーム -->
          <div class="kogu-return-section">
            <h4>返却手続き</h4>
            <p>ゆうパック（着払い）で発送後、追跡番号を入力してください。</p>
            <form class="kogu-return-form" data-rental-id="<?php echo (int) $r->id; ?>">
              <?php if ( ! $is_logged_in ) : ?>
                <input type="email" name="email" required placeholder="ご注文時のメールアドレス"
                       value="<?php echo esc_attr( $guest_email ); ?>" />
              <?php endif; ?>
              <div class="kogu-tracking-row">
                <input type="text" name="tracking" required placeholder="ゆうパック追跡番号（例: 1234567890123）" maxlength="30" />
                <button type="submit" class="kogu-btn kogu-btn-primary">提出する</button>
              </div>
              <div class="kogu-return-msg" style="display:none;"></div>
            </form>
          </div>
          <?php endif; ?>

          <?php if ( $r->status === 'returned' ) : ?>
          <div class="kogu-return-complete">
            <p>✅ 返却完了。</p>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php elseif ( $guest_email || $is_logged_in ) : ?>
    <p class="kogu-empty">レンタル履歴はありません。</p>
  <?php endif; ?>
</div>
