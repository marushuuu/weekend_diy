<?php defined( 'ABSPATH' ) || exit; ?>

<div id="kogu-rental-app" class="kogu-wrap">

  <!-- ── STEP 1: 日程選択 + 在庫確認 ───────────────────────────────────── -->
  <div class="kogu-step" id="step-dates">
    <h2 class="kogu-step-title">STEP 1 &nbsp;レンタル期間を選択</h2>

    <!-- 在庫バッジ -->
    <div class="kogu-availability-banner">
      <span id="kogu-stock-badge" class="kogu-badge">確認中...</span>
      <span class="kogu-stock-label">インパクトドライバー 在庫状況</span>
    </div>

    <!-- カレンダー -->
    <div id="kogu-calendar" class="kogu-calendar"></div>

    <div class="kogu-date-summary" id="kogu-date-summary" style="display:none;">
      <div class="kogu-date-row">
        <span>貸出開始</span><strong id="disp-start">—</strong>
      </div>
      <div class="kogu-date-row">
        <span>返却期限</span><strong id="disp-end">—</strong>
      </div>
      <div class="kogu-date-row">
        <span>レンタル日数</span><strong id="disp-days">—</strong>日
      </div>
      <div class="kogu-divider"></div>
      <div class="kogu-date-row">
        <span>レンタル料金</span><strong id="disp-rental-fee">—</strong>
      </div>
      <div class="kogu-date-row">
        <span>デポジット（返却後返金）</span><strong id="disp-deposit">—</strong>
      </div>
      <div class="kogu-date-row kogu-total">
        <span>合計請求額</span><strong id="disp-total">—</strong>
      </div>
    </div>

    <button class="kogu-btn kogu-btn-primary" id="btn-to-info" disabled>次へ：お客様情報を入力 →</button>
  </div>

  <!-- ── STEP 2: お客様情報 ─────────────────────────────────────────────── -->
  <div class="kogu-step" id="step-info" style="display:none;">
    <h2 class="kogu-step-title">STEP 2 &nbsp;お客様情報</h2>

    <?php if ( is_user_logged_in() ) : ?>
      <div class="kogu-notice">ログイン中のアカウント情報を使用します。</div>
    <?php else : ?>
      <div class="kogu-auth-links">
        <a href="<?php echo wp_login_url( get_permalink() ); ?>">ログインして申込む</a>
        <span> / </span>
        <a href="<?php echo wp_registration_url(); ?>">会員登録（任意）</a>
      </div>
    <?php endif; ?>

    <form id="kogu-info-form" class="kogu-form">
      <div class="kogu-form-row">
        <label>お名前 <em>*</em></label>
        <input type="text" name="name" required placeholder="山田 太郎"
               value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>" />
      </div>
      <div class="kogu-form-row">
        <label>メールアドレス <em>*</em></label>
        <input type="email" name="email" required placeholder="example@email.com"
               value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" />
      </div>
      <div class="kogu-form-row">
        <label>電話番号 <em>*</em></label>
        <input type="tel" name="phone" required placeholder="090-0000-0000" />
      </div>
      <div class="kogu-form-row">
        <label>郵便番号 <em>*</em></label>
        <input type="text" name="postal_code" required placeholder="000-0000" maxlength="8" />
      </div>
      <div class="kogu-form-row">
        <label>お届け先住所 <em>*</em></label>
        <textarea name="address" required rows="3" placeholder="都道府県・市区町村・番地・建物名"></textarea>
      </div>

      <button type="button" class="kogu-btn kogu-btn-secondary" id="btn-back-dates">← 日程に戻る</button>
      <button type="submit" class="kogu-btn kogu-btn-primary">次へ：お支払い情報を入力 →</button>
    </form>
  </div>

  <!-- ── STEP 3: Stripe 決済 ────────────────────────────────────────────── -->
  <div class="kogu-step" id="step-payment" style="display:none;">
    <h2 class="kogu-step-title">STEP 3 &nbsp;お支払い</h2>

    <div class="kogu-payment-summary">
      <p>レンタル料金 + デポジット（返却後返金）を合計してご請求します。</p>
      <div class="kogu-date-row kogu-total">
        <span>合計請求額</span><strong id="disp-total-payment">—</strong>
      </div>
    </div>

    <div id="stripe-payment-element" class="kogu-stripe-element"></div>
    <div id="stripe-error" class="kogu-error" style="display:none;"></div>

    <div class="kogu-terms-check">
      <label>
        <input type="checkbox" id="agree-terms" />
        <a href="<?php echo esc_url( home_url( '/terms' ) ); ?>" target="_blank">利用規約</a>および
        <a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>" target="_blank">プライバシーポリシー</a>に同意する
      </label>
    </div>

    <button type="button" class="kogu-btn kogu-btn-secondary" id="btn-back-info">← お客様情報に戻る</button>
    <button type="button" class="kogu-btn kogu-btn-primary" id="btn-pay">
      <span id="btn-pay-text">予約を確定して支払う</span>
      <span id="btn-pay-loading" style="display:none;">処理中...</span>
    </button>
  </div>

  <!-- ── STEP 4: 完了 ───────────────────────────────────────────────────── -->
  <div class="kogu-step" id="step-complete" style="display:none;">
    <div class="kogu-complete-icon">✅</div>
    <h2>ご予約が完了しました！</h2>
    <p>確認メールをお送りしました。商品の発送準備が整い次第、追跡番号をお知らせします。</p>
    <p>レンタル番号: <strong id="disp-rental-id">—</strong></p>
    <a href="<?php echo esc_url( home_url( '/my-page' ) ); ?>" class="kogu-btn kogu-btn-primary">マイページへ</a>
  </div>

</div>
