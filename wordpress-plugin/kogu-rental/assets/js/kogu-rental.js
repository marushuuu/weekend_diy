/* global KoguData, Stripe, jQuery */
(function ($) {
  'use strict';

  // ── State ──────────────────────────────────────────────────────────────────
  var state = {
    product_id:       null,
    product:          null,
    start_date:       '',
    weeks:            0,
    availability:     {},
    rental_fee:       0,
    addon_total:      0,
    addons:           {},   // { addon_id: qty }
    deposit:          0,
    customer_id:      '',
    payment_intent_id: '',
    stripe:           null,
    stripe_elements:  null,
    customer_info:    {},
  };

  // ── Utility ────────────────────────────────────────────────────────────────
  function fmt(n) { return '¥' + Number(n).toLocaleString('ja-JP'); }

  function addDays(dateStr, days) {
    var d = new Date(dateStr + 'T00:00:00');
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
  }

  // 週単位の料金計算（PHP の calc_rental_fee と同じロジック）
  function calcFee(weeks, pricePerWeek) {
    if (weeks <= 0) return 0;
    var discounted = Math.round(pricePerWeek * KoguData.week_discount_rate);
    return pricePerWeek + (weeks - 1) * discounted;
  }

  // 選択期間中に在庫ゼロの日がないか確認
  function isAvailable(start, weeks, availability) {
    if (!start || weeks <= 0) return false;
    var cursor = new Date(start + 'T00:00:00');
    var endDt  = new Date(addDays(start, weeks * 7 - 1) + 'T00:00:00');
    while (cursor <= endDt) {
      var d = cursor.toISOString().slice(0, 10);
      if (d in availability && availability[d] < 1) return false;
      cursor.setDate(cursor.getDate() + 1);
    }
    return true;
  }

  // ── Init ───────────────────────────────────────────────────────────────────
  $(document).ready(function () {
    if ($('#kogu-rental-app').length) {
      initRentalForm();
    }
    if ($('.kogu-mypage').length) {
      initMyPage();
    }
  });

  // ── Rental Form ────────────────────────────────────────────────────────────
  function initRentalForm() {

    // 商品カードクリック
    $(document).on('click', '.kogu-product-card', function () {
      var pid = parseInt($(this).data('product-id'), 10);
      if (state.product_id === pid) return;

      state.product_id   = pid;
      state.product      = null;
      state.availability = {};
      state.addons       = {};
      state.addon_total  = 0;

      $.each(KoguData.products, function (_, p) {
        if (p.id === pid) { state.product = p; return false; }
      });

      $('.kogu-product-card').removeClass('selected');
      $(this).addClass('selected');

      $('#kogu-stock-badge').text('確認中...').attr('class', 'kogu-badge kogu-badge-checking');
      $('#kogu-stock-product-name').text(state.product ? state.product.name : '');
      $('#kogu-period-wrap').show();
      $('#kogu-date-summary').hide();
      $('#kogu-unavailable-msg').hide();
      $('#btn-to-info').prop('disabled', true);

      // 購入オプション表示切替
      if (state.product && state.product.allows_addons && KoguData.addon_products.length > 0) {
        $('.kogu-qty-input').val(0);
        $('#kogu-addon-total-row').hide();
        $('#kogu-addons-wrap').show();
      } else {
        $('#kogu-addons-wrap').hide();
      }

      // 在庫マップを取得
      $.post(KoguData.ajax_url, { action: 'kogu_availability', nonce: KoguData.nonce }, function (res) {
        if (res.success) {
          state.availability = res.data[pid] || {};
          updateSummary();
          // 日付未選択の場合、「確認中...」のままにならないよう案内表示に切り替え
          if (!$('#start-date').val() || !parseInt($('#rental-weeks').val(), 10)) {
            $('#kogu-stock-badge').text('日付を選んでください').attr('class', 'kogu-badge');
          }
        }
      });
    });

    // 日付・週数変更
    $('#start-date, #rental-weeks').on('change', updateSummary);

    // アドオン数量ボタン
    $(document).on('click', '.kogu-qty-plus', function () {
      var id = $(this).data('addon-id');
      var $input = $('#addon-qty-' + id);
      var val = parseInt($input.val(), 10) || 0;
      $input.val(val + 1);
      updateAddons();
    });
    $(document).on('click', '.kogu-qty-minus', function () {
      var id = $(this).data('addon-id');
      var $input = $('#addon-qty-' + id);
      var val = parseInt($input.val(), 10) || 0;
      if (val > 0) { $input.val(val - 1); updateAddons(); }
    });

    // STEP 移動
    $('#btn-to-info').on('click', function () { showStep('step-info'); });
    $('#btn-back-dates').on('click', function () { showStep('step-dates'); });
    $('#btn-back-info').on('click', function () { showStep('step-info'); });

    // お客様情報フォーム
    $('#kogu-info-form').on('submit', function (e) {
      e.preventDefault();
      state.customer_info = {
        name:        $(this).find('[name=name]').val().trim(),
        email:       $(this).find('[name=email]').val().trim(),
        phone:       $(this).find('[name=phone]').val().trim(),
        postal_code: $(this).find('[name=postal_code]').val().trim(),
        address:     [$(this).find('[name=address1]').val().trim(), $(this).find('[name=address2]').val().trim()].filter(Boolean).join(' '),
      };
      showStep('step-payment');
      createPaymentIntent();
    });

    // 同意チェック
    $('#agree-terms').on('change', function () {
      $('#btn-pay').prop('disabled', !this.checked || !state.stripe_elements);
    });

    // 支払いボタン
    $('#btn-pay').on('click', handlePayment);
  }

  // ── アドオン集計 ──────────────────────────────────────────────────────────
  function updateAddons() {
    state.addons      = {};
    state.addon_total = 0;
    $('.kogu-qty-input').each(function () {
      var qty = parseInt($(this).val(), 10) || 0;
      if (qty > 0) {
        var id    = parseInt($(this).data('addon-id'), 10);
        var price = parseInt($(this).data('addon-price'), 10) || 0;
        state.addons[id]   = qty;
        state.addon_total += price * qty;
      }
    });
    if (state.addon_total > 0) {
      $('#disp-addon-total').text(fmt(state.addon_total));
      $('#kogu-addon-total-row').show();
    } else {
      $('#kogu-addon-total-row').hide();
    }
  }

  // ── 料金サマリー更新 ──────────────────────────────────────────────────────
  function updateSummary() {
    var start = $('#start-date').val();
    var weeks = parseInt($('#rental-weeks').val(), 10) || 0;

    if (!state.product || !start || !weeks) {
      $('#kogu-date-summary').hide();
      $('#btn-to-info').prop('disabled', true);
      return;
    }

    state.start_date = start;
    state.weeks      = weeks;

    var ppw   = state.product.price_per_week;
    var disc  = Math.round(ppw * KoguData.week_discount_rate);
    var avail = isAvailable(start, weeks, state.availability);

    state.rental_fee = calcFee(weeks, ppw);
    state.deposit    = 0;

    $('#disp-start').text(start);
    $('#disp-end').text(addDays(start, weeks * 7 - 1));
    $('#disp-weeks').text(weeks + '週間（' + (weeks * 7) + '日間）');
    $('#disp-rental-fee').text(fmt(state.rental_fee));

    if (weeks >= 2) {
      $('#kogu-price-breakdown')
        .html('内訳：1週目 ' + fmt(ppw) + ' ＋ 2週目以降 ' + fmt(disc) + '/週×' + (weeks - 1) + '週')
        .show();
    } else {
      $('#kogu-price-breakdown').hide();
    }

    $('#kogu-date-summary').show();

    if (avail) {
      $('#kogu-stock-badge').text('在庫あり').attr('class', 'kogu-badge kogu-badge-ok');
      $('#kogu-unavailable-msg').hide();
      $('#btn-to-info').prop('disabled', false);
    } else {
      $('#kogu-stock-badge').text('在庫なし').attr('class', 'kogu-badge kogu-badge-empty');
      $('#kogu-unavailable-msg').show();
      $('#btn-to-info').prop('disabled', true);
    }
  }

  // ── PaymentIntent 作成 ────────────────────────────────────────────────────
  function buildAddonsPayload() {
    var list = [];
    $.each(state.addons, function (id, qty) {
      list.push({ id: id, qty: qty });
    });
    return JSON.stringify(list);
  }

  function createPaymentIntent() {
    $.post(KoguData.ajax_url, {
      action:     'kogu_create_intent',
      nonce:      KoguData.nonce,
      product_id: state.product_id,
      start_date: state.start_date,
      weeks:      state.weeks,
      name:       state.customer_info.name,
      email:      state.customer_info.email,
      addons:     buildAddonsPayload(),
    }, function (res) {
      if (!res.success) {
        showError(res.data);
        showStep('step-info');
        return;
      }

      state.customer_id       = res.data.customer_id;
      state.payment_intent_id = res.data.payment_intent_id;

      $('#disp-total-payment').text(fmt(res.data.total));

      var stripe   = Stripe(KoguData.stripe_public_key);
      state.stripe = stripe;
      var elements = stripe.elements({ clientSecret: res.data.client_secret, locale: 'ja' });
      state.stripe_elements = elements;

      var paymentElement = elements.create('payment');
      paymentElement.mount('#stripe-payment-element');
    });
  }

  // ── 支払い処理 ────────────────────────────────────────────────────────────
  function handlePayment() {
    if (!$('#agree-terms').is(':checked')) {
      showError('利用規約への同意が必要です。');
      return;
    }
    if (!state.stripe || !state.stripe_elements) return;

    setPayBtnLoading(true);

    state.stripe.confirmPayment({
      elements: state.stripe_elements,
      redirect: 'if_required',
      confirmParams: {
        payment_method_data: {
          billing_details: {
            name:  state.customer_info.name,
            email: state.customer_info.email,
            phone: state.customer_info.phone,
          },
        },
      },
    }).then(function (result) {
      if (result.error) {
        showError(result.error.message);
        setPayBtnLoading(false);
        return;
      }
      confirmRental(result.paymentIntent.id);
    });
  }

  // ── レンタル確定（決済成功後） ────────────────────────────────────────────
  function confirmRental(piId) {
    $.post(KoguData.ajax_url, {
      action:             'kogu_confirm_rental',
      nonce:              KoguData.nonce,
      product_id:         state.product_id,
      payment_intent_id:  piId,
      customer_id:        state.customer_id,
      start_date:         state.start_date,
      weeks:              state.weeks,
      name:               state.customer_info.name,
      email:              state.customer_info.email,
      phone:              state.customer_info.phone,
      postal_code:        state.customer_info.postal_code,
      address:            state.customer_info.address,
      addons:             buildAddonsPayload(),
    }, function (res) {
      setPayBtnLoading(false);
      if (res.success) {
        $('#disp-rental-id').text('#' + res.data.rental_id);
        showStep('step-complete');
      } else {
        showError(res.data);
      }
    });
  }

  // ── My Page: 返却証跡フォーム ──────────────────────────────────────────────
  function initMyPage() {
    $(document).on('submit', '.kogu-return-form', function (e) {
      e.preventDefault();

      var $form    = $(this);
      var rentalId = $form.data('rental-id');
      var tracking = $form.find('[name=tracking]').val().trim().replace(/\s/g, '');
      var email    = $form.find('[name=email]').val().trim();
      var $msg     = $form.find('.kogu-return-msg');

      if (!tracking) { $msg.text('追跡番号を入力してください。').show(); return; }
      if (!/^\d{12,13}$/.test(tracking)) {
        $msg.text('ゆうパックの追跡番号は12〜13桁の数字です。再確認してください。').css('color','red').show();
        return;
      }

      $.post(KoguData.ajax_url, {
        action:    'kogu_submit_return',
        nonce:     KoguData.nonce,
        rental_id: rentalId,
        tracking:  tracking,
        email:     email,
      }, function (res) {
        if (res.success) {
          $msg.text('✅ 返却証跡を提出しました。').css('color','green').show();
          $form.find('input,button').prop('disabled', true);
        } else {
          $msg.text('⚠️ ' + res.data).css('color','red').show();
        }
      });
    });
  }

  // ── 郵便番号 → 住所自動入力 ────────────────────────────────────────────────
  $(document).on('input change', 'input[name="postal_code"]', function () {
    var raw = $(this).val().replace(/[^0-9]/g, '');
    if (raw.length !== 7) return;
    fetch('https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + raw)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.results && data.results[0]) {
          var r = data.results[0];
          $('input[name="address1"]').val(r.address1 + r.address2 + r.address3);
        }
      })
      .catch(function () {});
  });

  // ── Helpers ────────────────────────────────────────────────────────────────
  function showStep(id) {
    $('.kogu-step').hide();
    $('#' + id).show();
    $('html, body').animate({ scrollTop: Math.max(0, ($('#kogu-rental-app').offset().top || 0) - 80) }, 300);
  }

  function showError(msg) {
    $('#stripe-error').text(msg).show();
  }

  function setPayBtnLoading(on) {
    $('#btn-pay-text').toggle(!on);
    $('#btn-pay-loading').toggle(on);
    $('#btn-pay').prop('disabled', on);
  }

})(jQuery);
