/* 曦主题：收银台（选择付款方式 → 扫码或跳转 → 轮询到账） */
(function () {
  'use strict';

  var cfg = window.xiPay || {};
  var user = window.xiUser || {};
  var modal = document.getElementById('xi-pay');
  if (!modal || !cfg.methods || !cfg.methods.length) return;

  var html = document.documentElement;
  var toast = function (text, ok) { if (window.xiToast) window.xiToast(text, ok); };
  var steps = modal.querySelectorAll('.xi-pay-step');
  var methodsBox = modal.querySelector('.xi-pay-methods');
  var submit = modal.querySelector('.xi-pay-submit');
  var qrBox = modal.querySelector('.xi-pay-qr');
  var statusText = modal.querySelector('.xi-pay-status');
  var order = null;    // 当前要购买的商品
  var orderNo = '';
  var doneUrl = '';    // 支付成功后跳转的地址（商城订单等）
  var timer = null;
  var method = cfg.methods[0].id;
  var pointName = cfg.pointName || '积分';

  var ajax = function (data) {
    var body = new FormData();
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
    body.append('nonce', user.nonce);
    return fetch(user.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { ok: false, msg: '网络错误，请稍后重试' }; });
  };

  // 付款方式按钮
  cfg.methods.forEach(function (m, i) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'xi-pay-method is-' + m.id + (i === 0 ? ' is-active' : '');
    btn.setAttribute('role', 'radio');
    btn.setAttribute('aria-checked', i === 0 ? 'true' : 'false');
    btn.setAttribute('data-method', m.id);
    btn.textContent = m.name;
    methodsBox.appendChild(btn);
  });
  methodsBox.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-method]');
    if (!btn) return;
    method = btn.getAttribute('data-method');
    methodsBox.querySelectorAll('[data-method]').forEach(function (b) {
      b.classList.toggle('is-active', b === btn);
      b.setAttribute('aria-checked', b === btn ? 'true' : 'false');
    });
  });

  var showStep = function (name) {
    steps.forEach(function (s) { s.hidden = s.getAttribute('data-step') !== name; });
  };

  var open = function (item) {
    order = item;
    orderNo = '';
    doneUrl = '';
    modal.querySelectorAll('[data-pay-title]').forEach(function (el) { el.textContent = item.title; });
    modal.querySelectorAll('[data-pay-amount]').forEach(function (el) { el.textContent = item.amount; });
    submit.disabled = false;
    showStep('choose');
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    html.classList.add('xi-modal-open');
  };

  var close = function () {
    clearInterval(timer);
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    html.classList.remove('xi-modal-open');
  };

  var check = function (manual) {
    if (!orderNo) return;
    ajax({ action: 'xi_pay_check', order_no: orderNo }).then(function (res) {
      if (res.ok && res.paid) {
        clearInterval(timer);
        statusText.textContent = '支付成功，正在刷新…';
        statusText.classList.add('is-ok');
        toast('支付成功', true);
        setTimeout(function () { location.href = doneUrl || (location.pathname + '?tab=orders'); }, 1200);
      } else if (manual) {
        toast('暂未查询到支付结果，请稍候再试', false);
      }
    });
  };

  submit.addEventListener('click', function () {
    if (!order) return;
    submit.disabled = true;
    var data = { action: 'xi_pay_create', type: order.type, method: method, amount: order.amount };
    if (order.level) { data.level = order.level; data.plan = order.plan; }
    ajax(data).then(function (res) {
      submit.disabled = false;
      if (!res.ok) { toast(res.msg || '下单失败', false); return; }
      orderNo = res.order_no;
      showPay(res);
    });
  });

  // 显示扫码页或跳转到支付页，res：type、url、qr、method、amount、title
  var showPay = function (res) {
    if (res.type === 'redirect') {
      location.href = res.url;
      return;
    }
    // 扫码支付
    var names = { alipay: '支付宝', wxpay: '微信', qqpay: 'QQ' };
    modal.querySelector('[data-pay-scan]').textContent = '请使用' + (names[res.method] || '') + '扫码支付';
    var qr = window.qrcode(0, 'M');
    qr.addData(res.qr);
    qr.make();
    qrBox.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true });
    statusText.textContent = '等待支付…支付完成后会自动到账';
    statusText.classList.remove('is-ok');
    showStep('qrcode');
    clearInterval(timer);
    var started = Date.now();
    timer = setInterval(function () {
      if (Date.now() - started > 15 * 60 * 1000) { clearInterval(timer); statusText.textContent = '二维码已过期，请关闭后重新下单'; return; }
      check(false);
    }, 3000);
  };

  // 商城结算页：订单已在服务端创建，直接进入扫码步骤
  window.xiPayUI = {
    show: function (res) {
      order = { title: res.title || '', amount: res.amount };
      orderNo = res.pay_no;
      doneUrl = res.done || '';
      modal.querySelectorAll('[data-pay-title]').forEach(function (el) { el.textContent = order.title; });
      modal.querySelectorAll('[data-pay-amount]').forEach(function (el) { el.textContent = res.amount; });
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      html.classList.add('xi-modal-open');
      showPay(res);
    }
  };

  modal.addEventListener('click', function (e) {
    if (e.target.closest('[data-pay-close]')) close();
    if (e.target.closest('[data-pay-done]')) check(true);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
  });

  /* ---------- 充值卡片：档位与自定义金额 ---------- */
  document.querySelectorAll('[data-pay-card]').forEach(function (card) {
    var type = card.getAttribute('data-pay-card');
    var rate = parseInt(card.getAttribute('data-rate'), 10) || 100;
    var custom = card.querySelector('.xi-pay-custom input');
    var summary = card.querySelector('.xi-pay-summary');
    var amount = function () {
      var v = parseFloat(custom.value);
      if (v > 0) return v;
      var cur = card.querySelector('.xi-pay-preset.is-active');
      return cur ? parseFloat(cur.getAttribute('data-amount')) : 0;
    };
    var update = function () {
      var a = amount();
      summary.textContent = a > 0 ? (type === 'points' ? '支付 ¥' + a.toFixed(2) + '，获得 ' + Math.floor(a * rate) + ' ' + pointName : '支付 ¥' + a.toFixed(2)) : '';
    };
    card.addEventListener('click', function (e) {
      var p = e.target.closest('.xi-pay-preset');
      if (!p) return;
      card.querySelectorAll('.xi-pay-preset').forEach(function (b) { b.classList.toggle('is-active', b === p); });
      custom.value = '';
      update();
    });
    custom.addEventListener('input', function () {
      card.querySelectorAll('.xi-pay-preset').forEach(function (b) { b.classList.toggle('is-active', !custom.value && b === card.querySelector('.xi-pay-preset')); });
      update();
    });
    update();
    card.querySelector('[data-xi-pay]').addEventListener('click', function () {
      var a = amount();
      var min = parseFloat(custom.min) || 0.01;
      var max = parseFloat(custom.max) || 99999;
      if (!(a >= min && a <= max)) { toast('金额需在 ' + min + ' - ' + max + ' 元之间', false); custom.focus(); return; }
      open({
        type: type,
        amount: a.toFixed(2),
        title: type === 'points' ? '购买 ' + Math.floor(a * rate) + ' ' + pointName : '余额充值'
      });
    });
  });

  // 会员套餐「在线付」
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-xi-pay="vip"]');
    if (!btn) return;
    open({
      type: 'vip',
      amount: btn.getAttribute('data-amount'),
      level: btn.getAttribute('data-level'),
      plan: btn.getAttribute('data-plan'),
      title: '开通' + btn.getAttribute('data-title')
    });
  });
})();
