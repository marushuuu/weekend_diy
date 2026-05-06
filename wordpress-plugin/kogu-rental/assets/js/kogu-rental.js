/* global KoguData, Stripe */
(function ($) {
  'use strict';

  // ── State ──────────────────────────────────────────────────────────────────
  const state = {
    startDate:        null,
    endDate:          null,
    rentalFee:        0,
    clientSecret:     null,
    paymentIntentId:  null,
    customerId:       null,
    stripe:           null,
    paymentElement:   null,
    customerInfo:     {},
  };

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
    if (KoguData.stripe_public_key) {
      state.stripe = Stripe(KoguData.stripe_public_key);
    }

    loadCalendar();

    $('#btn-to-info').on('click', function () {
      showStep('step-info');
    });

    $('#btn-back-dates').on('click', function () {
      showStep('step-dates');
    });

    $('#kogu-info-form').on('submit', function (e) {
      e.preventDefault();
      state.customerInfo = {
        name:        $(this).find('[name=name]').val().trim(),
        email:       $(this).find('[name=email]').val().trim(),
        phone:       $(this).find('[name=phone]').val().trim(),
        postal_code: $(this).find('[name=postal_code]').val().trim(),
        address:     $(this).find('[name=address]').val().trim(),
      };
      createPaymentIntent();
    });

    $('#btn-back-info').on('click', function () {
      showStep('step-info');
    });

    $('#btn-pay').on('click', handlePayment);
  }

  // ── Calendar ───────────────────────────────────────────────────────────────
  function loadCalendar() {
    $.post(KoguData.ajax_url, { action: 'kogu_availability', nonce: KoguData.nonce }, function (res) {
      if (res.success) {
        renderCalendar(res.data);
      }
    });
  }

  function renderCalendar(availMap) {
    const $cal    = $('#kogu-calendar');
    const today   = new Date();
    today.setHours(0,0,0,0);
    const months  = 3;
    let html      = '';

    for (let m = 0; m < months; m++) {
      const d = new Date(today.getFullYear(), today.getMonth() + m, 1);
      html += renderMonth(d, availMap, today);
    }
    $cal.html(html);

    $cal.on('click', '.kogu-cal-day.available', function () {
      const date = $(this).data('date');
      if (!state.startDate || (state.startDate && state.endDate)) {
        state.startDate = date;
        state.endDate   = null;
      } else if (date < state.startDate) {
        state.startDate = date;
        state.endDate   = null;
      } else {
        state.endDate = date;
      }
      highlightRange($cal, availMap);
      updateDateSummary();
    });
  }

  function renderMonth(date, availMap, today) {
    const y     = date.getFullYear();
    const m     = date.getMonth();
    const label = `${y}年${m+1}月`;
    const days  = new Date(y, m+1, 0).getDate();
    const first = new Date(y, m, 1).getDay();

    let html = `<div class="kogu-cal-month"><div class="kogu-cal-month-label">${label}</div>
      <div class="kogu-cal-grid">`;

    // 曜日ヘッダー
    ['日','月','火','水','木','金','土'].forEach(d => {
      html += `<div class="kogu-cal-dow">${d}</div>`;
    });

    // 空白セル
    for (let i = 0; i < first; i++) html += '<div></div>';

    for (let d = 1; d <= days; d++) {
      const dateStr = `${y}-${String(m+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const cellDate = new Date(y, m, d);
      const avail    = availMap[dateStr] ?? 0;
      const isPast   = cellDate < today;

      let cls = 'kogu-cal-day';
      if (isPast)       cls += ' past';
      else if (avail > 0) cls += ' available';
      else               cls += ' soldout';

      const badge = avail > 0 ? `<span class="kogu-avail-count">${avail}</span>` : '';
      html += `<div class="${cls}" data-date="${dateStr}" data-avail="${avail}">
        ${d}${badge}
      </div>`;
    }
    html += '</div></div>';
    return html;
  }

  function highlightRange($cal, availMap) {
    $cal.find('.kogu-cal-day').removeClass('selected in-range range-start range-end');

    if (!state.startDate) return;

    $cal.find(`.kogu-cal-day[data-date="${state.startDate}"]`).addClass('selected range-start');

    if (state.endDate) {
      $cal.find(`.kogu-cal-day[data-date="${state.endDate}"]`).addClass('selected range-end');
      $cal.find('.kogu-cal-day').each(function () {
        const d = $(this).data('date');
        if (d > state.startDate && d < state.endDate) {
          $(this).addClass('in-range');
        }
      });
    }
  }

  function updateDateSummary() {
    const $summary = $('#kogu-date-summary');
    if (!state.startDate) {
      $summary.hide();
      $('#btn-to-info').prop('disabled', true);
      return;
    }

    $('#disp-start').text(state.startDate);

    if (!state.endDate) {
      state.endDate = state.startDate;
    }
    $('#disp-end').text(state.endDate);

    const days = calcDays(state.startDate, state.endDate);
    $('#disp-days').text(days);

    state.rentalFee = days * KoguData.price_per_day;
    const deposit   = KoguData.deposit_amount;
    const total     = state.rentalFee + deposit;

    $('#disp-rental-fee').text('¥' + fmt(state.rentalFee));
    $('#disp-deposit').text('¥' + fmt(deposit));
    $('#disp-total').text('¥' + fmt(total));
    $('#disp-total-payment').text('¥' + fmt(total));

    $summary.show();
    $('#btn-to-info').prop('disabled', false);
  }

  // ── Create PaymentIntent ───────────────────────────────────────────────────
  function createPaymentIntent() {
    showStep('step-payment');
    $('#disp-total-payment').text('¥' + fmt(state.rentalFee + KoguData.deposit_amount));

    $.post(KoguData.ajax_url, {
      action:     'kogu_create_intent',
      nonce:      KoguData.nonce,
      start_date: state.startDate,
      end_date:   state.endDate,
      email:      state.customerInfo.email,
      name:       state.customerInfo.name,
    }, function (res) {
      if (!res.success) {
        showError(res.data);
        return;
      }
      state.clientSecret    = res.data.client_secret;
      state.paymentIntentId = res.data.payment_intent_id;
      state.customerId      = res.data.customer_id;

      mountStripeElement();
    });
  }

  function mountStripeElement() {
    if (!state.stripe || !state.clientSecret) return;

    const elements = state.stripe.elements({ clientSecret: state.clientSecret, locale: 'ja' });
    state.paymentElement = elements.create('payment');
    state.paymentElement.mount('#stripe-payment-element');
    state._elements = elements;
  }

  // ── Payment ────────────────────────────────────────────────────────────────
  async function handlePayment() {
    if (!$('#agree-terms').is(':checked')) {
      showError('利用規約への同意が必要です。');
      return;
    }

    setPayBtnLoading(true);

    const { error, paymentIntent } = await state.stripe.confirmPayment({
      elements: state._elements,
      redirect: 'if_required',
      confirmParams: {
        payment_method_data: {
          billing_details: {
            name:  state.customerInfo.name,
            email: state.customerInfo.email,
            phone: state.customerInfo.phone,
          },
        },
      },
    });

    if (error) {
      showError(error.message);
      setPayBtnLoading(false);
      return;
    }

    // 決済成功 → サーバーでレンタル確定
    $.post(KoguData.ajax_url, {
      action:             'kogu_confirm_rental',
      nonce:              KoguData.nonce,
      payment_intent_id:  paymentIntent.id,
      customer_id:        state.customerId,
      start_date:         state.startDate,
      end_date:           state.endDate,
      name:               state.customerInfo.name,
      email:              state.customerInfo.email,
      phone:              state.customerInfo.phone,
      postal_code:        state.customerInfo.postal_code,
      address:            state.customerInfo.address,
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

      const $form    = $(this);
      const rentalId = $form.data('rental-id');
      const tracking = $form.find('[name=tracking]').val().trim();
      const email    = $form.find('[name=email]').val().trim();
      const $msg     = $form.find('.kogu-return-msg');

      if (!tracking) { $msg.text('追跡番号を入力してください。').show(); return; }
      if (!/^\d{12,13}$/.test(tracking.replace(/\s/g,''))) {
        $msg.text('ゆうパックの追跡番号は12〜13桁の数字です。再確認してください。').css('color','red').show();
        return;
      }
      tracking = tracking.replace(/\s/g,'');

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

  // ── Helpers ────────────────────────────────────────────────────────────────
  function showStep(id) {
    $('.kogu-step').hide();
    $('#' + id).show();
    window.scrollTo({ top: $('#kogu-rental-app').offset().top - 80, behavior: 'smooth' });
  }

  function showError(msg) {
    $('#stripe-error').text(msg).show();
  }

  function setPayBtnLoading(on) {
    $('#btn-pay-text').toggle(!on);
    $('#btn-pay-loading').toggle(on);
    $('#btn-pay').prop('disabled', on);
  }

  function calcDays(start, end) {
    const s = new Date(start), e = new Date(end);
    return Math.max(1, Math.round((e - s) / 86400000) + 1);
  }

  function fmt(n) {
    return n.toLocaleString('ja-JP');
  }

})(jQuery);
