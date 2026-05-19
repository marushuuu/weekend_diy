<?php defined( 'ABSPATH' ) || exit;
$img_base = KOGU_PLUGIN_URL . 'assets/images/';
$date_min = date( 'Y-m-d', strtotime( '+2 days' ) );
$date_max = date( 'Y-m-d', strtotime( '+88 days' ) );
function kogu_product_img( $name, $base ) {
    if ( mb_strpos( $name, 'インパクト' ) !== false ) return $base . 'product_impact.jpg';
    if ( mb_strpos( $name, 'ビット' ) !== false || mb_strpos( $name, 'セット' ) !== false ) return $base . 'product_bitset.jpg';
    return '';
}
?>

<div id="kogu-rental-app" class="kogu-wrap">

<?php if ( $is_top_mode ) : ?>
  <!-- ══ TOP MODE: 商品ごとに日付・週数入力 ══════════════════════════════════ -->
  <div class="kogu-top-grid">
  <?php foreach ( Kogu_Database::get_active_products() as $p ) :
    $img = kogu_product_img( (string) $p->name, $img_base );
  ?>
    <div class="kogu-top-block" data-product-id="<?php echo (int) $p->id; ?>">

      <!-- 商品カード -->
      <div class="kogu-product-card kogu-product-card-static">
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

      <!-- 日付・週数入力 -->
      <div class="kogu-product-period">
        <div class="kogu-form-row">
          <label>貸出開始日 <em>*</em></label>
          <input type="date" class="kogu-top-start-date"
                 min="<?php echo esc_attr( $date_min ); ?>"
                 max="<?php echo esc_attr( $date_max ); ?>" />
        </div>
        <div class="kogu-form-row">
          <label>レンタル週数 <em>*</em></label>
          <select class="kogu-top-rental-weeks">
            <option value="">-- 週数を選択 --</option>
            <?php for ( $w = 1; $w <= 8; $w++ ) : ?>
              <option value="<?php echo $w; ?>"><?php echo $w; ?>週間（<?php echo $w * 7; ?>日間）</option>
            <?php endfor; ?>
          </select>
        </div>

        <!-- 料金サマリー -->
        <div class="kogu-date-summary kogu-top-summary" style="display:none;">
          <div class="kogu-date-row">
            <span>貸出開始</span><strong class="kogu-top-disp-start">—</strong>
          </div>
          <div class="kogu-date-row">
            <span>返却期限</span><strong class="kogu-top-disp-end" style="color:#e85a2b;">—</strong>
          </div>
          <div class="kogu-divider"></div>
          <div class="kogu-date-row kogu-total">
            <span>レンタル料金</span><strong class="kogu-top-disp-fee">—</strong>
          </div>
          <div class="kogu-date-row">
            <span>送料</span><strong class="kogu-top-disp-shipping">—</strong>
          </div>
        </div>

        <div class="kogu-error kogu-top-unavail" style="display:none;">
          選択した期間は在庫がありません。別の日程をお選びください。
        </div>

        <a class="kogu-btn kogu-btn-primary kogu-top-go-btn" style="display:none;" href="#">レンタルに進む →</a>
      </div>

    </div><!-- /kogu-top-block -->
  <?php endforeach; ?>
  </div><!-- /kogu-top-grid -->

<?php else : ?>
  <!-- ══ FULL MODE: STEP 1〜4 ═══════════════════════════════════════════════ -->

  <!-- ── STEP 1: 商品選択 + 期間選択 ──────────────────────────────────────── -->
  <div class="kogu-step" id="step-dates">
    <?php if ( $show_step_titles ) : ?><h2 class="kogu-step-title">STEP 1 &nbsp;商品と期間を選択</h2><?php endif; ?>

    <div class="kogu-step1-layout">

      <!-- 左: 商品カード一覧 -->
      <div id="kogu-products" class="kogu-products">
      <?php foreach ( Kogu_Database::get_active_products() as $p ) :
        $img = kogu_product_img( (string) $p->name, $img_base );
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
      </div><!-- /kogu-products -->

      <!-- 右: 日付・週数・サマリー -->
      <div class="kogu-step1-right">

        <!-- 商品未選択時のヒント -->
        <div id="kogu-select-hint" class="kogu-select-hint">
          借りたい工具を選んでください<br><small>複数選択もできます</small>
        </div>

        <!-- 期間選択（商品選択後に有効化） -->
        <div id="kogu-period-wrap" class="kogu-period-wrap">

          <div class="kogu-in-stock-banner" id="kogu-stock-banner" style="display:none;">
            <span id="kogu-stock-badge" class="kogu-badge kogu-badge-checking">確認中...</span>
            <span id="kogu-stock-product-name" class="kogu-stock-label"></span>
          </div>

          <div class="kogu-form-row">
            <label for="start-date">貸出開始日 <em>*</em></label>
            <input type="date" id="start-date"
                   min="<?php echo esc_attr( $date_min ); ?>"
                   max="<?php echo esc_attr( $date_max ); ?>" />
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
            <div class="kogu-date-row" id="kogu-shipping-row" style="display:none;">
              <span>送料</span><strong id="disp-shipping" style="color:#c0392b;">—</strong>
            </div>
            <div class="kogu-date-row" id="kogu-free-shipping-row" style="display:none;">
              <span>送料</span><strong style="color:#27ae60;">無料</strong>
            </div>
          </div>

          <div id="kogu-unavailable-msg" class="kogu-error" style="display:none;">
            選択した期間は在庫がありません。別の日程をお選びください。
          </div>

        </div><!-- /kogu-period-wrap -->

      </div><!-- /kogu-step1-right -->

    </div><!-- /kogu-step1-layout -->

    <!-- 購入オプション（在庫確定後に全幅で表示） -->
    <?php $addon_products = Kogu_Database::get_active_addon_products(); ?>
    <?php if ( ! empty( $addon_products ) ) : ?>
    <div id="kogu-addons-wrap" style="display:none;" class="kogu-addons-wrap">
      <h3 class="kogu-addons-title">オプション購入（任意）</h3>
      <p class="kogu-addons-desc">レンタル工具に合わせてご購入いただけます。消耗品はそのままお使いください。</p>
      <div class="kogu-addon-cards">
        <?php foreach ( $addon_products as $a ) :
          $stock = $a->stock_quantity !== null ? (int) $a->stock_quantity : null;
          $soldout = $stock === 0;
        ?>
        <div class="kogu-addon-card<?php echo $soldout ? ' kogu-addon-card-soldout' : ''; ?>"
             data-addon-id="<?php echo (int) $a->id; ?>">

          <?php if ( $a->image ) : ?>
          <div class="kogu-addon-card-img">
            <img src="<?php echo esc_url( $a->image ); ?>" alt="<?php echo esc_attr( $a->name ); ?>" loading="lazy" />
          </div>
          <?php else : ?>
          <div class="kogu-addon-card-img kogu-addon-card-img-placeholder">
            <span>No Image</span>
          </div>
          <?php endif; ?>

          <div class="kogu-addon-card-body">
            <div class="kogu-addon-name"><?php echo esc_html( $a->name ); ?></div>
            <?php if ( $a->description ) : ?>
              <div class="kogu-addon-desc-text"><?php echo esc_html( $a->description ); ?></div>
            <?php endif; ?>
            <div class="kogu-addon-price">¥<?php echo number_format( $a->price ); ?> <span class="kogu-addon-unit">/ <?php echo esc_html( $a->unit ); ?></span></div>
          </div>

          <div class="kogu-addon-card-footer">
            <?php if ( $soldout ) : ?>
              <span class="kogu-addon-soldout">品切れ</span>
            <?php else : ?>
              <div class="kogu-addon-qty">
                <button type="button" class="kogu-qty-btn kogu-qty-minus" data-addon-id="<?php echo (int) $a->id; ?>">－</button>
                <input type="number" class="kogu-qty-input"
                       id="addon-qty-<?php echo (int) $a->id; ?>"
                       data-addon-id="<?php echo (int) $a->id; ?>"
                       data-addon-price="<?php echo (int) $a->price; ?>"
                       data-addon-stock="<?php echo $stock !== null ? $stock : ''; ?>"
                       value="0" min="0" max="<?php echo $stock !== null ? $stock : 99; ?>" readonly />
                <button type="button" class="kogu-qty-btn kogu-qty-plus" data-addon-id="<?php echo (int) $a->id; ?>">＋</button>
              </div>
              <?php if ( $stock !== null ) : ?>
                <small class="kogu-addon-stock-label">残り<?php echo $stock; ?>個</small>
              <?php endif; ?>
            <?php endif; ?>
          </div>

        </div><!-- /kogu-addon-card -->
        <?php endforeach; ?>
      </div><!-- /kogu-addon-cards -->

      <div class="kogu-addon-total-row" id="kogu-addon-total-row" style="display:none;">
        <span>オプション合計</span><strong id="disp-addon-total">¥0</strong>
      </div>
    </div><!-- /kogu-addons-wrap -->
    <?php endif; ?>

    <button class="kogu-btn kogu-btn-primary kogu-btn-next" id="btn-to-info" disabled style="display:none;">次へ：お客様情報を入力 →</button>
  </div>

  <!-- ── STEP 2: お客様情報 ─────────────────────────────────────────────── -->
  <div class="kogu-step" id="step-info" style="display:none;">
    <h2 class="kogu-step-title">STEP 2 &nbsp;お客様情報</h2>

    <form id="kogu-info-form" class="kogu-form">
      <div class="kogu-form-row">
        <label>お名前 <em>*</em></label>
        <input type="text" name="name" required placeholder="山田 太郎" />
      </div>
      <div class="kogu-form-row">
        <label>メールアドレス <em>*</em></label>
        <input type="email" name="email" required placeholder="example@email.com" />
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

<?php endif; ?>

</div>
