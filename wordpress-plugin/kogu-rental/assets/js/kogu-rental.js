/* global KoguData, Stripe, jQuery */
(function ($) {
  'use strict';

  // ── Utility ────────────────────────────────────────────────────────────────
  function fmt(n) { return '¥' + Number(n).toLocaleString('ja-JP'); }

  function addDays(dateStr, days) {
    var d = new Date(dateStr + 'T00:00:00');
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
  }

  function calcFee(weeks, pricePerWeek) {
    if (weeks <= 0) return 0;
    var discounted = Math.round(pricePerWeek * KoguData.week_discount_rate);
    return pricePerWeek + (weeks - 1) * discounted;
  }

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
    // DOM が確実に揃ってから判定
    var isTopMode = !!$('.kogu-top-block').length;

    if ($('#kogu-rental-app').length) {
      if (isTopMode) {
        initTopMode();
      } else {
        initRentalForm();
        applyUrlParams();
      }
    }
    if ($('.kogu-mypage').length) {
      initMyPage();
    }
  });

  // ── TOP MODE: 商品ごとに独立した日付・週数入力 ──────────────────────────────
  function initTopMode() {
    var topAvail = {};

    $.post(KoguData.ajax_url, { action: 'kogu_availability', nonce: KoguData.nonce }, function (res) {
      if (res.success) { topAvail = res.data; }
    });

    $(document).on('change', '.kogu-top-start-date, .kogu-top-rental-weeks', function () {
      var $block = $(this).closest('.kogu-top-block');
      updateTopBlock($block, topAvail);
    });
  }

  function updateTopBlock($block, topAvail) {
    var pid   = parseInt($block.data('product-id'), 10);
    var start = $block.find('.kogu-top-start-date').val();
    var weeks = parseInt($block.find('.kogu-top-rental-weeks').val(), 10) || 0;

    var product = null;
    $.each(KoguData.products, function (_, p) { if (p.id === pid) { product = p; return false; } });

    var $summary = $block.find('.kogu-top-summary');
    var $unavail = $block.find('.kogu-top-unavail');
    var $goBtn   = $block.find('.kogu-top-go-btn');

    if (!product || !start || !weeks) {
      $summary.hide(); $goBtn.hide(); $unavail.hide();
      return;
    }

    var avail    = isAvailable(start, weeks, topAvail[pid] || {});
    var fee      = calcFee(weeks, product.price_per_week);
    var shipping = fee < KoguData.free_shipping_threshold ? KoguData.shipping_fee : 0;

    $block.find('.kogu-top-disp-start').text(start);
    $block.find('.kogu-top-disp-end').text(addDays(start, weeks * 7 - 1));
    $block.find('.kogu-top-disp-fee').text(fmt(fee));
    if (shipping > 0) {
      $block.find('.kogu-top-disp-shipping').text(fmt(shipping)).css('color', '#c0392b');
    } else {
      $block.find('.kogu-top-disp-shipping').text('無料').css('color', '#27ae60');
    }
    $summary.show();

    if (avail) {
      var url = KoguData.rental_page_url + '?product_id=' + pid +
                '&start_date=' + start + '&weeks=' + weeks;
      $goBtn.attr('href', url).show();
      $unavail.hide();
    } else {
      $goBtn.hide();
      $unavail.show();
    }
  }

  // ── URLパラメータから自動入力（/rental/ ページ用） ──────────────────────────
  function applyUrlParams() {
    var params = new URLSearchParams(window.location.search);
    var pid    = parseInt(params.get('product_id'), 10);
    var date   = params.get('start_date');
    var weeks  = params.get('weeks');
    if (!pid) return;
    var $card = $('.kogu-product-card[data-product-id="' + pid + '"]');
    if ($card.length) {
      $card.trigger('click');
      if (date)  { setTimeout(function(){ $('#start-date').val(date).trigger('change'); }, 300); }
      if (weeks) { setTimeout(function(){ $('#rental-weeks').val(weeks).trigger('change'); }, 400); }
    }
  }

  // ── Full Mode: Rental Form (STEP 1〜4) ─────────────────────────────────────
  var state = {
    product_ids: [], products: [],
    start_date: '', weeks: 0,
    availability: {}, addons: {},
    rental_fee: 0, addon_total: 0, deposit: 0,
    customer_id: '', payment_intent_id: '',
    stripe: null, stripe_elements: null, customer_info: {},
  };

  // 在庫データを一度だけ取得してキャッシュ
  var availabilityLoaded = false;

  function ensureAvailability(callback) {
    if (availabilityLoaded) {
      callback();
      return;
    }
    $.post(KoguData.ajax_url, { action: 'kogu_availability', nonce: KoguData.nonce }, function (res) {
      if (res.success) {
        state.availability = res.data;
        availabilityLoaded = true;
      }
      callback();
    });
  }

  function initRentalForm() {
    // 商品カードクリック — #kogu-products 内のカードに直接バインド
    $('#kogu-products').on('click', '.kogu-product-card', function () {
      $(this).toggleClass('selected');

      // 選択中のカードから state を再構築
      state.product_ids = [];
      state.products    = [];
      $('.kogu-product-card.selected').each(function () {
        var pid = parseInt($(this).data('product-id'), 10);
        state.product_ids.push(pid);
        $.each(KoguData.products, function (_, p) {
          if (p.id === pid) { state.products.push(p); return false; }
        });
      });

      var hasSelected = state.product_ids.length > 0;

      if (hasSelected) {
        $('#kogu-select-hint').hide();
        $('#start-date, #rental-weeks').prop('disabled', false);

        var names = state.products.map(function (p) { return p.name; }).join('・');
        $('#kogu-stock-product-name').text(names);
        $('#kogu-stock-banner').show();
        $('#btn-to-info').show();

        ensureAvailability(function () {
          updateSummary();
          if (!$('#start-date').val() || !parseInt($('#rental-weeks').val(), 10)) {
            $('#kogu-stock-badge').text('日付と週数を選んでください').attr('class', 'kogu-badge');
          }
        });
      } else {
        $('#kogu-select-hint').show();
        $('#start-date, #rental-weeks').prop('disabled', true);
        $('#kogu-stock-banner').hide();
        $('#kogu-date-summary').hide();
        $('#kogu-unavailable-msg').hide();
        $('#kogu-addons-wrap').hide();
        $('#btn-to-info').hide();
      }
    });

    // 初期状態：商品未選択なら入力欄を無効化
    $('#start-date, #rental-weeks').prop('disabled', true);

    $('#start-date, #rental-weeks').on('change', updateSummary);

    $(document).on('click', '.kogu-qty-plus', function () {
      var id     = $(this).data('addon-id');
      var $input = $('#addon-qty-' + id);
      var cur    = parseInt($input.val(), 10) || 0;
      var stock  = parseInt($input.data('addon-stock'), 10);
      var maxQty = isNaN(stock) ? 99 : stock;
      if (cur < maxQty) { $input.val(cur + 1); updateAddons(); }
    });
    $(document).on('click', '.kogu-qty-minus', function () {
      var id = $(this).data('addon-id');
      var $input = $('#addon-qty-' + id);
      var val = parseInt($input.val(), 10) || 0;
      if (val > 0) { $input.val(val - 1); updateAddons(); }
    });

    $('#btn-to-info').on('click', function () { showStep('step-info'); });
    $('#btn-back-dates').on('click', function () { showStep('step-dates'); });
    $('#btn-back-info').on('click', function () { showStep('step-info'); });

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

    $('#agree-terms').on('change', function () {
      $('#btn-pay').prop('disabled', !this.checked || !state.stripe_elements);
    });

    $('#btn-pay').on('click', handlePayment);
  }

  function updateAddons() {
    state.addons = {}; state.addon_total = 0;
    $('.kogu-qty-input').each(function () {
      var qty = parseInt($(this).val(), 10) || 0;
      var id  = parseInt($(this).data('addon-id'), 10);
      // カードの selected クラスを数量に連動
      $('.kogu-addon-card[data-addon-id="' + id + '"]').toggleClass('selected', qty > 0);
      if (qty > 0) {
        state.addons[id]   = qty;
        state.addon_total += (parseInt($(this).data('addon-price'), 10) || 0) * qty;
      }
    });
    if (state.addon_total > 0) {
      $('#disp-addon-total').text(fmt(state.addon_total));
      $('#kogu-addon-total-row').show();
    } else {
      $('#kogu-addon-total-row').hide();
    }
    updateShippingDisplay();
  }

  function updateShippingDisplay() {
    if (!state.rental_fee) return;
    var subtotal = state.rental_fee + state.addon_total;
    if (subtotal < KoguData.free_shipping_threshold) {
      $('#disp-shipping').text(fmt(KoguData.shipping_fee));
      $('#kogu-shipping-row').show();
      $('#kogu-free-shipping-row').hide();
    } else {
      $('#kogu-shipping-row').hide();
      $('#kogu-free-shipping-row').show();
    }
  }

  function updateSummary() {
    var start = $('#start-date').val();
    var weeks = parseInt($('#rental-weeks').val(), 10) || 0;

    if (!state.product_ids.length || !start || !weeks) {
      $('#kogu-date-summary').hide();
      $('#btn-to-info').prop('disabled', true);
      return;
    }

    state.start_date = start;
    state.weeks      = weeks;

    // 全選択商品の合計料金と在庫チェック
    state.rental_fee = 0;
    var allAvailable = true;
    $.each(state.products, function (_, product) {
      state.rental_fee += calcFee(weeks, product.price_per_week);
      if (!isAvailable(start, weeks, state.availability[product.id] || {})) {
        allAvailable = false;
      }
    });
    state.deposit = 0;

    $('#disp-start').text(start);
    $('#disp-end').text(addDays(start, weeks * 7 - 1));
    $('#disp-weeks').text(weeks + '週間（' + (weeks * 7) + '日間）');

    // 料金内訳：複数商品なら商品ごとに表示、1商品なら週割引内訳
    if (state.products.length > 1) {
      var breakdown = state.products.map(function (p) {
        return p.name + '&nbsp;' + fmt(calcFee(weeks, p.price_per_week));
      }).join('、');
      $('#kogu-price-breakdown').html('内訳：' + breakdown).show();
    } else if (weeks >= 2) {
      var ppw  = state.products[0].price_per_week;
      var disc = Math.round(ppw * KoguData.week_discount_rate);
      $('#kogu-price-breakdown')
        .html('内訳：1週目 ' + fmt(ppw) + ' ＋ 2週目以降 ' + fmt(disc) + '/週×' + (weeks - 1) + '週')
        .show();
    } else {
      $('#kogu-price-breakdown').hide();
    }

    $('#disp-rental-fee').text(fmt(state.rental_fee));
    updateShippingDisplay();
    $('#kogu-date-summary').show();

    if (allAvailable) {
      var statusText = state.product_ids.length > 1 ? '全商品 在庫あり' : '在庫あり';
      $('#kogu-stock-badge').text(statusText).attr('class', 'kogu-badge kogu-badge-ok');
      $('#kogu-unavailable-msg').hide();
      if (KoguData.addon_products.length > 0) {
        $('.kogu-qty-input').val(0);
        $('#kogu-addon-total-row').hide();
        $('#kogu-addons-wrap').show();
      }
      $('#btn-to-info').prop('disabled', false);
    } else {
      $('#kogu-stock-badge').text('在庫なし').attr('class', 'kogu-badge kogu-badge-empty');
      $('#kogu-unavailable-msg').show();
      $('#kogu-addons-wrap').hide();
      $('#btn-to-info').prop('disabled', true);
    }
  }

  function buildAddonsPayload() {
    var list = [];
    $.each(state.addons, function (id, qty) { list.push({ id: id, qty: qty }); });
    return JSON.stringify(list);
  }

  function createPaymentIntent() {
    $.post(KoguData.ajax_url, {
      action: 'kogu_create_intent', nonce: KoguData.nonce,
      product_ids: JSON.stringify(state.product_ids),
      start_date: state.start_date, weeks: state.weeks,
      name: state.customer_info.name, email: state.customer_info.email,
      addons: buildAddonsPayload(),
    }, function (res) {
      if (!res.success) { showError(res.data); showStep('step-info'); return; }
      state.customer_id       = res.data.customer_id;
      state.payment_intent_id = res.data.payment_intent_id;
      $('#disp-total-payment').text(fmt(res.data.total));
      var stripe = Stripe(KoguData.stripe_public_key);
      state.stripe = stripe;
      var elements = stripe.elements({ clientSecret: res.data.client_secret, locale: 'ja' });
      state.stripe_elements = elements;
      elements.create('payment').mount('#stripe-payment-element');
    });
  }

  function handlePayment() {
    if (!$('#agree-terms').is(':checked')) { showError('利用規約への同意が必要です。'); return; }
    if (!state.stripe || !state.stripe_elements) return;
    setPayBtnLoading(true);
    state.stripe.confirmPayment({
      elements: state.stripe_elements,
      redirect: 'if_required',
      confirmParams: {
        payment_method_data: {
          billing_details: {
            name: state.customer_info.name, email: state.customer_info.email, phone: state.customer_info.phone,
          },
        },
      },
    }).then(function (result) {
      if (result.error) { showError(result.error.message); setPayBtnLoading(false); return; }
      confirmRental(result.paymentIntent.id);
    });
  }

  function confirmRental(piId) {
    $.post(KoguData.ajax_url, {
      action: 'kogu_confirm_rental', nonce: KoguData.nonce,
      product_ids: JSON.stringify(state.product_ids),
      payment_intent_id: piId, customer_id: state.customer_id,
      start_date: state.start_date, weeks: state.weeks,
      name: state.customer_info.name, email: state.customer_info.email,
      phone: state.customer_info.phone, postal_code: state.customer_info.postal_code,
      address: state.customer_info.address, addons: buildAddonsPayload(),
    }, function (res) {
      setPayBtnLoading(false);
      if (res.success) {
        var rentalId = res.data.rental_id || (res.data.rental_ids && res.data.rental_ids[0]);
        $('#disp-rental-id').text('#' + rentalId);
        $('#disp-reservation-number').text(res.data.reservation_number || '—');
        showStep('step-complete');
      } else {
        showError(res.data);
      }
    });
  }

  // ── My Page ────────────────────────────────────────────────────────────────
  function initMyPage() {
    $(document).on('submit', '.kogu-return-form', function (e) {
      e.preventDefault();
      var $form             = $(this);
      var rentalId          = $form.data('rental-id');
      var reservationNumber = $form.data('reservation-number') || '';
      var tracking          = $form.find('[name=tracking]').val().trim().replace(/\s/g, '');
      var $msg              = $form.find('.kogu-return-msg');
      if (!tracking) { $msg.text('追跡番号を入力してください。').css('color','red').show(); return; }
      if (!/^\d{12,13}$/.test(tracking)) {
        $msg.text('ゆうパックの追跡番号は12〜13桁の数字です。再確認してください。').css('color','red').show();
        return;
      }
      $.post(KoguData.ajax_url, {
        action: 'kogu_submit_return', nonce: KoguData.nonce,
        rental_id: rentalId, tracking: tracking,
        reservation_number: reservationNumber,
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
      }).catch(function () {});
  });

  // ── Helpers ────────────────────────────────────────────────────────────────
  function showStep(id) {
    $('.kogu-step').hide();
    $('#' + id).show();
    $('html, body').animate({ scrollTop: Math.max(0, ($('#kogu-rental-app').offset().top || 0) - 80) }, 300);
  }
  function showError(msg) { $('#stripe-error').text(msg).show(); }
  function setPayBtnLoading(on) {
    $('#btn-pay-text').toggle(!on);
    $('#btn-pay-loading').toggle(on);
    $('#btn-pay').prop('disabled', on);
  }

})(jQuery);

/* ── 商品詳細モーダル ─────────────────────────────────────────────────────── */
(function($) {
  var modal   = $('#kogu-product-modal');
  var imgs    = [];
  var current = 0;

  function openModal(card) {
    imgs    = JSON.parse(card.attr('data-gallery') || '[]');
    current = 0;
    modal.find('.kogu-modal-title').text(card.attr('data-product-name') || '');

    // 同梱物
    var contents = JSON.parse(card.attr('data-contents') || '[]');
    var ul = modal.find('.kogu-contents-list').empty();
    if (contents.length) {
      $.each(contents, function(_, item) { ul.append($('<li>').text(item)); });
      modal.find('.kogu-modal-contents').show();
    } else {
      modal.find('.kogu-modal-contents').hide();
    }

    // カルーセル
    renderCarousel();
    modal.css('display', 'flex');
    $('body').css('overflow', 'hidden');
  }

  function renderCarousel() {
    if (!imgs.length) return;
    modal.find('.kogu-carousel-img').attr('src', imgs[current]).attr('alt', '商品画像 ' + (current + 1));

    // dots
    var dots = modal.find('.kogu-carousel-dots').empty();
    if (imgs.length > 1) {
      $.each(imgs, function(i) {
        var dot = $('<button class="kogu-carousel-dot">').attr('aria-label', (i+1) + '枚目');
        if (i === current) dot.addClass('active');
        dot.on('click', function() { current = i; renderCarousel(); });
        dots.append(dot);
      });
    }

    // 矢印の有効/無効
    modal.find('.kogu-carousel-prev').prop('disabled', current === 0);
    modal.find('.kogu-carousel-next').prop('disabled', current === imgs.length - 1);
  }

  function closeModal() {
    modal.hide();
    $('body').css('overflow', '');
  }

  // <a>でないdiv.kogu-product-imgの場合のみモーダルを開く
  $(document).on('click', 'div.kogu-product-img', function() {
    openModal($(this).closest('.kogu-product-card-static'));
  });
  $(document).on('click', '.kogu-modal-close, .kogu-modal-overlay', function(e) {
    if (e.target === this) closeModal();
  });
  $(document).on('click', '.kogu-carousel-prev', function() {
    if (current > 0) { current--; renderCarousel(); }
  });
  $(document).on('click', '.kogu-carousel-next', function() {
    if (current < imgs.length - 1) { current++; renderCarousel(); }
  });
  $(document).on('keydown', function(e) {
    if (!modal.is(':visible')) return;
    if (e.key === 'Escape') closeModal();
    if (e.key === 'ArrowLeft' && current > 0) { current--; renderCarousel(); }
    if (e.key === 'ArrowRight' && current < imgs.length - 1) { current++; renderCarousel(); }
  });

  // ── 工具リクエスト ポップアップ ──────────────────────────────────────────
  var $fab     = $('#kogu-request-btn');
  var $popup   = $('#kogu-request-popup');
  var $close   = $popup.find('.kogu-request-popup-close');
  var $submit  = $('#kogu-request-submit');
  var $error   = $('#kogu-request-error');
  var $thanks  = $('#kogu-request-thanks');
  var $formWrap = $('#kogu-request-form-wrap');

  if ($fab.length) {
    $fab.on('click keydown', function(e) {
      if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
      var isHidden = $popup.prop('hidden');
      $popup.prop('hidden', !isHidden);
      if (isHidden) $popup.find('#kogu-req-tool').focus();
    });

    $close.on('click', function() { $popup.prop('hidden', true); });

    $(document).on('keydown', function(e) {
      if (e.key === 'Escape' && !$popup.prop('hidden')) $popup.prop('hidden', true);
    });

    $(document).on('click', function(e) {
      if (!$popup.prop('hidden') &&
          !$(e.target).closest('#kogu-request-popup, #kogu-request-btn').length) {
        $popup.prop('hidden', true);
      }
    });

    $submit.on('click', function() {
      var toolName = $.trim($('#kogu-req-tool').val());
      var email    = $.trim($('#kogu-req-email').val());

      $error.prop('hidden', true).text('');

      if (!toolName) { $error.text('希望の工具名を入力してください。').prop('hidden', false); return; }
      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        $error.text('正しいメールアドレスを入力してください。').prop('hidden', false); return;
      }

      $submit.prop('disabled', true).text('送信中…');

      $.post(KoguData.ajax_url, {
        action:    'kogu_tool_request',
        nonce:     KoguData.nonce,
        tool_name: toolName,
        email:     email
      }, function(res) {
        if (res.success) {
          $formWrap.hide();
          $thanks.prop('hidden', false);
        } else {
          $error.text(res.data || '送信に失敗しました。').prop('hidden', false);
          $submit.prop('disabled', false).text('リクエストする');
        }
      }).fail(function() {
        $error.text('通信エラーが発生しました。').prop('hidden', false);
        $submit.prop('disabled', false).text('リクエストする');
      });
    });
  }
})(jQuery);
