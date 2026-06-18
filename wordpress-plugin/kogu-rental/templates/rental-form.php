<?php defined( 'ABSPATH' ) || exit; ?>

<div id="kogu-rental-app" class="kogu-wrap">

  <!-- ── STEP 1: 商品選択 + 期間選択 ────────────────────────────────────── -->
  <div class="kogu-step" id="step-dates">
    <h2 class="kogu-step-title">STEP 1 &nbsp;商品と期間を選択</h2>

    <!-- 商品カード -->
    <div id="kogu-products" class="kogu-products">
      <?php
        $img_base = KOGU_PLUGIN_URL . 'assets/images/';
        foreach ( Kogu_Database::get_active_products() as $p ) :
          if ( mb_strpos( (string) $p->name, 'インパクト' ) !== false ) {
            $img = $img_base . 'product_impact.jpg';
          } elseif ( mb_strpos( (string) $p->name, 'ビット' ) !== false ) {
            $img = $img_base . 'product_bitset.jpg';
          } else {
            $img = '';
          }
      ?>
        <div class="kogu-product-card" data-product-id="<?php echo (int) $p->id; ?>">
          <?php if ( $img ) : ?>
            <div class="kogu-product-img">
              <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $p->name ); ?>" loading="lazy" />
            </div>
          <?php endif; ?>
          <div class="kogu-product-name"><?php echo esc_html( $p->name ); ?></div>
          <div class="kogu-product-desc"><?php echo esc_html( $p->description ); ?></div>
          <div class="kogu-product-price">
            <span class="kogu-price-label">1週間</span>
            <span class="kogu-price-amount">¥<?php echo number_format( $p->price_per_week ); ?></span>
          </div>
          <div class="kogu-product-price-discount">
            2週目以降 ¥<?php echo number_format( (int) round( $p->price_per_week * 0.7 ) ); ?>/週
            <span class="kogu-badge-discount">30%OFF</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- 期間選択 -->
    <div id="kogu-period-wrap" style="display:none;" class="kogu-period-wrap">

      <div class="kogu-in-stock-banner">
        <span id="kogu-stock-badge" class="kogu-badge kogu-badge-checking">確認中...</span>
        <span id="kogu-stock-product-name" class="kogu-stock-label">商品を選択してください</span>
      </div>

      <div class="kogu-form-row">
        <label for="start-date">貸出開始日 <em>*</em></label>
        <input type="date" id="start-date"
               min="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+2 days' ) ) ); ?>"
               max="<?php echo esc_attr( date( 'Y-m-d', strtotime( '+88 days' ) ) ); ?>" />
      </div>

      <div class="kogu-form-row">
        <label for="rental-weeks">レンタル週数 <em>*</em></label>
        <select id="rental-weeks">
          <option value="">-- 週数を選択 --</option>
          <?php for ( $w = 1; $w <= 8; $w++ ) : ?>
            <option value="<?php echo $w; ?>"><?php echo $w; ?>週間（<?php echo $w * 7; ?>日間）</option>
          <?php endfor; ?>
        </select>
      </div>

      <!-- 料金サマリー -->
      <div class="kogu-date-summary" id="kogu-date-summary" style="display:none;">
        <div class="kogu-date-row">
          <span>貸出開始</span><strong id="disp-start">—</strong>
        </div>
        <div class="kogu-date-row">
          <span>返却期限</span><strong id="disp-end" style="color:#e85a2b;">—</strong>
        </div>
        <div class="kogu-date-row">
          <span>レンタル期間</span><strong id="disp-weeks">—</strong>
        </div>
        <div id="kogu-price-breakdown" class="kogu-price-breakdown" style="display:none;"></div>
        <div class="kogu-divider"></div>
        <div class="kogu-date-row kogu-total">
          <span>レンタル料金</span><strong id="disp-rental-fee">—</strong>
        </div>
      </div>

      <div id="kogu-unavailable-msg" class="kogu-error" style="display:none;">
        選択した期間は在庫がありません。別の日程をお選びください。
      </div>

    </div><!-- /kogu-period-wrap -->

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
        <input type="text" name="postal_code" required placeholder="1500001" maxlength="8" />
        <small style="color:var(--kogu-muted);font-size:11px;margin-top:3px;">7桁入力で住所を自動入力します</small>
      </div>
      <div class="kogu-form-row">
        <label>住所（都道府県〜番地） <em>*</em></label>
        <input type="text" name="address1" required placeholder="例：東京都渋谷区道玄坂1-2-3" />
      </div>
      <div class="kogu-form-row">
        <label>建物名・部屋番号</label>
        <input type="text" name="address2" placeholder="例：○○マンション101号室（任意）" />
      </div>

      <div style="display:flex;gap:12px;margin-top:8px;">
        <button type="button" class="kogu-btn kogu-btn-secondary" id="btn-back-dates">← 日程に戻る</button>
        <button type="submit" class="kogu-btn kogu-btn-primary">次へ：お支払い情報を入力 →</button>
      </div>
    </form>
  </div>

  <!-- ── STEP 3: Stripe 決済 ────────────────────────────────────────────── -->
  <div class="kogu-step" id="step-payment" style="display:none;">
    <h2 class="kogu-step-title">STEP 3 &nbsp;お支払い</h2>

    <div class="kogu-payment-summary">
      <p>レンタル料金のみご請求します。延滞・損傷があった場合のみ、登録カードに別途請求いたします。</p>
      <div class="kogu-date-row kogu-total">
        <span>請求額</span><strong id="disp-total-payment">—</strong>
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

    <div style="display:flex;gap:12px;margin-top:16px;">
      <button type="button" class="kogu-btn kogu-btn-secondary" id="btn-back-info">← お客様情報に戻る</button>
      <button type="button" class="kogu-btn kogu-btn-primary" id="btn-pay" disabled>
        <span id="btn-pay-text">予約を確定して支払う</span>
        <span id="btn-pay-loading" style="display:none;">処理中...</span>
      </button>
    </div>
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
