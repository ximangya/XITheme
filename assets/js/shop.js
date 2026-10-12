/* 曦主题：商城（商品页、购物车、结算、用户中心订单与地址） */
(function () {
  'use strict';

  var cfg = window.xiShop || {};
  var toast = function (text, ok) { if (text && window.xiToast) window.xiToast(text, ok); };
  var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  var ajax = function (action, data) {
    var body = new FormData();
    body.append('action', action);
    body.append('nonce', cfg.nonce || '');
    Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
    return fetch(cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (t) { try { return JSON.parse(t); } catch (e) { return { ok: false, msg: '服务器返回异常，请刷新后重试' }; } })
      .catch(function () { return { ok: false, msg: '网络错误，请稍后重试' }; });
  };

  var setCartCount = function (n) {
    $$('[data-xi-cart-count]').forEach(function (el) {
      el.textContent = n > 99 ? 99 : n;
      el.hidden = !n;
    });
  };

  var html = document.documentElement;
  var openModal = function (el) {
    el.classList.add('is-open');
    el.setAttribute('aria-hidden', 'false');
    html.classList.add('xi-modal-open');
    var first = el.querySelector('input:not([type="hidden"]), textarea');
    if (first) setTimeout(function () { first.focus(); }, 60);
  };
  var closeModal = function (el) {
    el.classList.remove('is-open');
    el.setAttribute('aria-hidden', 'true');
    html.classList.remove('xi-modal-open');
  };
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-modal-close]')) closeModal(e.target.closest('.xi-modal'));
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    $$('.xi-modal.is-open').forEach(closeModal);
  });

  // 复制卡密、单号
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-xi-copy]');
    if (!btn) return;
    var text = btn.getAttribute('data-xi-copy');
    var done = function () { toast('已复制', true); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(done, function () { toast('复制失败，请手动选择', false); });
    } else {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.cssText = 'position:fixed;opacity:0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); done(); } catch (err) { toast('复制失败，请手动选择', false); }
      ta.remove();
    }
  });

  // 数量步进器
  var bindQty = function (box, onChange) {
    var input = $('input', box);
    var clamp = function (v) {
      var max = parseInt(input.max, 10) || 99;
      v = parseInt(v, 10) || 1;
      return Math.max(1, Math.min(max, v));
    };
    box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-step]');
      if (!b) return;
      var v = clamp(parseInt(input.value, 10) + parseInt(b.getAttribute('data-step'), 10));
      if (String(v) !== input.value) { input.value = v; if (onChange) onChange(v); }
    });
    input.addEventListener('change', function () {
      input.value = clamp(input.value);
      if (onChange) onChange(parseInt(input.value, 10));
    });
  };

  // 倒计时
  $$('[data-xi-countdown]').forEach(function (el) {
    var end = parseInt(el.getAttribute('data-xi-countdown'), 10) * 1000;
    var pad = function (n) { return n < 10 ? '0' + n : n; };
    var tick = function () {
      var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
      var d = Math.floor(left / 86400);
      var h = Math.floor(left % 86400 / 3600);
      var m = Math.floor(left % 3600 / 60);
      var s = left % 60;
      var prefix = el.getAttribute('data-prefix') || '距结束';
      el.textContent = left ? prefix + ' ' + (d ? d + ' 天 ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s) : (el.hasAttribute('data-prefix') ? '已超时' : '活动已结束');
      if (!left) clearInterval(timer);
    };
    var timer = setInterval(tick, 1000);
    tick();
  });

  /* ---------- 商品页 ---------- */
  var info = $('[data-xi-product]');
  if (info) {
    var pid = info.getAttribute('data-xi-product');
    var specs = [];
    try { specs = JSON.parse(info.getAttribute('data-specs')) || []; } catch (e) {}
    var current = specs[0] || null;
    var qtyBox = $('[data-xi-qty]', info);
    var qtyInput = qtyBox && $('input', qtyBox);
    var stockEl = $('[data-xi-stock]', info);
    var btns = $$('[data-xi-cart-add], [data-xi-buy]', info);

    var render = function () {
      if (!current) return;
      $('[data-xi-price]', info).innerHTML = current.price;
      $('[data-xi-original]', info).textContent = current.original || '';
      var out = current.stock === 0;
      stockEl.textContent = out ? '已售罄' : (current.stock > 0 ? '库存 ' + current.stock + ' 件' : '');
      qtyInput.max = current.stock > 0 ? Math.min(99, current.stock) : 99;
      if (parseInt(qtyInput.value, 10) > parseInt(qtyInput.max, 10)) qtyInput.value = qtyInput.max;
      btns.forEach(function (b) { b.disabled = out; });
    };
    $$('[data-spec]', info).forEach(function (b) {
      b.addEventListener('click', function () {
        var index = parseInt(b.getAttribute('data-spec'), 10);
        current = specs.filter(function (s) { return s.index === index; })[0] || current;
        $$('[data-spec]', info).forEach(function (x) {
          x.classList.toggle('is-active', x === b);
          x.setAttribute('aria-checked', x === b ? 'true' : 'false');
        });
        render();
      });
    });
    if (qtyBox) bindQty(qtyBox);
    render();

    var addBtn = $('[data-xi-cart-add]', info);
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        addBtn.disabled = true;
        ajax('xi_cart_add', { pid: pid, spec: current ? current.index : 0, qty: qtyInput.value }).then(function (res) {
          addBtn.disabled = false;
          toast(res.msg, res.ok);
          if (res.ok) setCartCount(res.count);
        });
      });
    }
    var buyBtn = $('[data-xi-buy]', info);
    if (buyBtn) {
      buyBtn.addEventListener('click', function () {
        var u = new URL(cfg.checkout, location.href);
        u.searchParams.set('items', pid + ':' + (current ? current.index : 0) + ':' + qtyInput.value);
        location.href = u.href;
      });
    }

    // 图集
    var mainImg = $('[data-xi-gallery-main]');
    $$('.xi-product-thumbs button').forEach(function (b) {
      b.addEventListener('click', function () {
        if (!mainImg) return;
        mainImg.removeAttribute('srcset');
        mainImg.src = b.getAttribute('data-src');
        $$('.xi-product-thumbs button').forEach(function (x) { x.classList.toggle('is-active', x === b); });
      });
    });

    // 详情 / 参数 / 评价
    var tabs = $$('.xi-product-tabs [data-tab]');
    var showTab = function (name) {
      tabs.forEach(function (t) { t.classList.toggle('is-active', t.getAttribute('data-tab') === name); });
      $$('[data-pane]').forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-pane') === name); });
    };
    tabs.forEach(function (t) { t.addEventListener('click', function () { showTab(t.getAttribute('data-tab')); }); });
    if (/^#(comments|respond|comment-\d+)$/.test(location.hash)) showTab('reviews');
  }

  /* ---------- 购物车 ---------- */
  var cart = $('[data-xi-cart]');
  if (cart && $('.xi-shop-cart-list', cart)) {
    var rows = function () { return $$('.xi-shop-cart-row', cart); };
    var checked = function () { return rows().filter(function (r) { var c = $('.xi-shop-cart-check', r); return c && c.checked; }); };
    var sumEl = $('[data-xi-cart-sum]', cart);
    var sum = function () {
      var money = 0, points = 0;
      checked().forEach(function (r) {
        var q = parseInt($('input[type="number"]', r).value, 10) || 0;
        var p = parseFloat(r.getAttribute('data-price')) || 0;
        if (r.getAttribute('data-type') === 'points') points += p * q; else money += p * q;
      });
      var parts = [];
      if (money > 0 || !points) parts.push('¥' + money.toFixed(2));
      if (points > 0) parts.push(Math.round(points) + ' ' + (cfg.points || '积分'));
      sumEl.textContent = parts.join(' + ');
      var promoEl = $('[data-xi-promo]', cart);
      if (promoEl) {
        var tiers = [];
        try { tiers = JSON.parse(promoEl.getAttribute('data-xi-promo')) || []; } catch (e) {}
        var hit = null, next = null;
        tiers.forEach(function (t) { if (money >= t.min) hit = t; else if (!next) next = t; });
        promoEl.textContent = next ? '再买 ¥' + (next.min - money).toFixed(2) + ' 可减 ¥' + next.minus : (hit ? '已满 ' + hit.min + ' 减 ' + hit.minus : '');
      }
      var all = $('[data-xi-cart-all]', cart);
      var enabled = rows().filter(function (r) { var c = $('.xi-shop-cart-check', r); return c && !c.disabled; });
      all.checked = enabled.length > 0 && checked().length === enabled.length;
    };
    var remove = function (keys) {
      if (!keys.length) return;
      ajax('xi_cart_remove', { keys: keys.join(',') }).then(function (res) {
        if (!res.ok) { toast(res.msg, false); return; }
        setCartCount(res.count);
        keys.forEach(function (k) { var r = $('.xi-shop-cart-row[data-key="' + k + '"]', cart); if (r) r.remove(); });
        if (!rows().length) location.reload(); else sum();
      });
    };
    rows().forEach(function (r) {
      var box = $('[data-xi-qty]', r);
      var timer = null;
      if (box) {
        bindQty(box, function (v) {
          sum();
          clearTimeout(timer);
          timer = setTimeout(function () {
            ajax('xi_cart_update', { key: r.getAttribute('data-key'), qty: v }).then(function (res) {
              if (!res.ok) { toast(res.msg, false); if (res.qty) { $('input', box).value = res.qty; sum(); } return; }
              setCartCount(res.count);
            });
          }, 400);
        });
      }
      r.addEventListener('change', function (e) { if (e.target.classList.contains('xi-shop-cart-check')) sum(); });
      $('[data-xi-cart-del]', r).addEventListener('click', function () { remove([r.getAttribute('data-key')]); });
    });
    $('[data-xi-cart-all]', cart).addEventListener('change', function (e) {
      rows().forEach(function (r) { var c = $('.xi-shop-cart-check', r); if (c && !c.disabled) c.checked = e.target.checked; });
      sum();
    });
    $('[data-xi-cart-del-selected]', cart).addEventListener('click', function () {
      var keys = checked().map(function (r) { return r.getAttribute('data-key'); });
      if (!keys.length) { toast('请先选择商品', false); return; }
      if (confirm('确定删除选中的 ' + keys.length + ' 件商品吗？')) remove(keys);
    });
    $('[data-xi-cart-checkout]', cart).addEventListener('click', function () {
      var sel = checked();
      if (!sel.length) { toast('请选择要结算的商品', false); return; }
      var types = {};
      sel.forEach(function (r) { var t = r.getAttribute('data-type'); if (t !== 'free') types[t] = 1; });
      if (Object.keys(types).length > 1) { toast('现金商品与' + (cfg.points || '积分') + '商品请分开结算', false); return; }
      var u = new URL(cfg.checkout, location.href);
      u.searchParams.set('cart', sel.map(function (r) { return r.getAttribute('data-key'); }).join(','));
      location.href = u.href;
    });
    sum();
  }

  /* ---------- 收货地址 ---------- */
  var addrModal = document.getElementById('xi-address');
  if (addrModal) {
    var addrForm = $('[data-xi-address-form]', addrModal);
    document.addEventListener('click', function (e) {
      var edit = e.target.closest('[data-xi-address-edit]');
      if (edit) {
        var data = {};
        try { data = JSON.parse(edit.getAttribute('data-xi-address-edit') || '{}') || {}; } catch (err) {}
        ['id', 'name', 'phone', 'region', 'detail'].forEach(function (k) { addrForm.elements[k].value = data[k] || ''; });
        addrForm.elements['default'].checked = !!data['default'];
        openModal(addrModal);
        return;
      }
      var del = e.target.closest('[data-xi-address-del]');
      if (del && confirm('确定删除这个地址吗？')) {
        ajax('xi_shop_address_delete', { id: del.getAttribute('data-xi-address-del') }).then(function (res) {
          toast(res.msg, res.ok);
          if (res.ok) location.reload();
        });
      }
    });
    addrForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var data = {};
      ['id', 'name', 'phone', 'region', 'detail'].forEach(function (k) { data[k] = addrForm.elements[k].value.trim(); });
      if (addrForm.elements['default'].checked) data['default'] = 1;
      var btn = $('[type="submit"]', addrForm);
      btn.disabled = true;
      ajax('xi_shop_address_save', data).then(function (res) {
        btn.disabled = false;
        toast(res.msg, res.ok);
        if (res.ok) location.reload();
      });
    });
  }

  /* ---------- 结算 ---------- */
  var checkout = $('[data-xi-checkout]');
  if (checkout) {
    var couponBtn = $('[data-xi-coupon]', checkout);
    var pointsBox = $('[data-xi-points]', checkout);
    var couponOk = '';
    var totalEl = $('[data-xi-total]', checkout);
    var setRow = function (name, data, text) {
      var row = $('[data-xi-row="' + name + '"]', checkout);
      if (!row) return;
      row.hidden = !data;
      if (!data) return;
      if (text) $('[data-xi-label]', row).textContent = text;
      $('[data-xi-val]', row).textContent = data.amount;
    };
    // 服务端重新计算应付金额（满减、优惠码、积分抵扣、运费）
    var requote = function (coupon, silent) {
      var data = { items: checkout.getAttribute('data-items'), cart: checkout.getAttribute('data-cart') };
      if (coupon) data.coupon = coupon;
      if (pointsBox && pointsBox.checked) data.points = 1;
      return ajax('xi_shop_quote', data).then(function (res) {
        if (!res.ok) { toast(res.msg, false); return res; }
        if (!silent && res.msg) toast(res.msg, true);
        var q = res.quote;
        setRow('promo', q.promo, q.promo && q.promo.text);
        setRow('coupon', q.coupon, q.coupon && ('优惠码 ' + q.coupon.text));
        setRow('points', q.points, q.points && (q.points.points + ' ' + (cfg.points || '积分') + '抵扣'));
        var ship = $('[data-xi-row="shipping"] [data-xi-val]', checkout);
        if (ship) ship.textContent = parseFloat(q.shipping) > 0 ? '¥' + q.shipping : '包邮';
        totalEl.textContent = q.total;
        return res;
      });
    };
    if (couponBtn) {
      couponBtn.addEventListener('click', function () {
        var code = checkout.elements.coupon.value.trim();
        if (!code) { toast('请输入优惠码', false); return; }
        couponBtn.disabled = true;
        requote(code).then(function (res) {
          couponBtn.disabled = false;
          if (res.ok) couponOk = code; else if (res.coupon_error) requote(couponOk, true);
        });
      });
      checkout.elements.coupon.addEventListener('input', function () {
        if (!couponOk) return;
        // 修改了优惠码：撤销已应用的优惠，需重新点击「使用」
        couponOk = '';
        requote('', true);
      });
    }
    if (pointsBox) {
      pointsBox.addEventListener('change', function () { requote(couponOk, true); });
    }
    checkout.addEventListener('submit', function (e) {
      e.preventDefault();
      var f = checkout.elements;
      var data = { items: checkout.getAttribute('data-items'), cart: checkout.getAttribute('data-cart') };
      var addr = checkout.querySelector('input[name="address"]:checked');
      if ($('[data-xi-addresses]', checkout)) {
        if (!addr) { toast('请选择收货地址', false); return; }
        data.address = addr.value;
      }
      var empty = $$('[required]', checkout).filter(function (i) { return !i.value.trim(); })[0];
      if (empty) { toast((empty.name === 'contact' ? '请填写联系方式' : '请填写订单备注'), false); empty.focus(); return; }
      if (f.contact) data.contact = f.contact.value.trim();
      if (f.remark) data.remark = f.remark.value.trim();
      if (couponOk) data.coupon = couponOk;
      if (pointsBox && pointsBox.checked) data.points = 1;
      var pay = checkout.querySelector('input[name="pay"]:checked');
      data.pay = pay ? pay.value : '';
      var btn = $('.xi-shop-submit', checkout);
      btn.disabled = true;
      ajax('xi_shop_submit', data).then(function (res) {
        if (!res.ok) {
          toast(res.msg, false);
          // 订单已创建但付款失败（如余额不足）：去订单页继续付款，避免重复下单
          if (res.order_no && res.redirect) { setTimeout(function () { location.href = res.redirect + (res.redirect.indexOf('?') > -1 ? '&' : '?') + 'order=' + res.order_no; }, 1200); return; }
          btn.disabled = false;
          return;
        }
        if (res.paid) { toast(res.msg, true); setTimeout(function () { location.href = res.redirect; }, 900); return; }
        if (res.type === 'redirect') { location.href = res.url; return; }
        if (window.xiPayUI) { window.xiPayUI.show(res); btn.disabled = false; return; }
        location.href = res.done;
      });
    });
  }

  /* ---------- 用户中心订单 ---------- */
  var refundModal = document.getElementById('xi-refund');
  document.addEventListener('click', function (e) {
    var act = e.target.closest('[data-xi-order-do]');
    var box = e.target.closest('[data-order]');
    if (act && box) {
      var doName = act.getAttribute('data-xi-order-do');
      var tips = { cancel: '确定取消这个订单吗？', confirm: '确认已收到商品吗？', refund_cancel: '确定撤销售后申请吗？' };
      if (tips[doName] && !confirm(tips[doName])) return;
      act.disabled = true;
      ajax('xi_shop_order_action', { order_no: box.getAttribute('data-order'), 'do': doName }).then(function (res) {
        toast(res.msg, res.ok);
        if (res.ok && res.reload) setTimeout(function () { location.reload(); }, 800); else act.disabled = false;
      });
      return;
    }
    var payBtn = e.target.closest('[data-xi-order-pay]');
    if (payBtn && box) {
      var sel = $('.xi-shop-pay-select', box);
      payBtn.disabled = true;
      ajax('xi_shop_pay', { order_no: box.getAttribute('data-order'), pay: sel ? sel.value : '' }).then(function (res) {
        payBtn.disabled = false;
        if (!res.ok) { toast(res.msg, false); return; }
        if (res.paid) { toast(res.msg, true); setTimeout(function () { location.reload(); }, 900); return; }
        if (res.type === 'redirect') { location.href = res.url; return; }
        if (window.xiPayUI) window.xiPayUI.show(res);
      });
      return;
    }
    var refund = e.target.closest('[data-xi-order-refund]');
    if (refund && box && refundModal) {
      var form = $('[data-xi-refund-form]', refundModal);
      form.elements.order_no.value = box.getAttribute('data-order');
      form.elements.reason.value = '';
      // 已发货的实物订单才能选「退货退款」
      var types = $('[data-xi-refund-types]', form);
      var canReturn = refund.getAttribute('data-xi-order-refund') === 'return';
      types.hidden = !canReturn;
      $('input[value="refund"]', types).checked = true;
      openModal(refundModal);
      return;
    }
    var ret = e.target.closest('[data-xi-return-ship]');
    if (ret && box && returnModal) {
      var rform = $('[data-xi-return-form]', returnModal);
      rform.elements.order_no.value = box.getAttribute('data-order');
      openModal(returnModal);
    }
  });
  var returnModal = document.getElementById('xi-return');
  if (returnModal) {
    var retForm = $('[data-xi-return-form]', returnModal);
    retForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = $('[type="submit"]', retForm);
      btn.disabled = true;
      ajax('xi_shop_order_action', { order_no: retForm.elements.order_no.value, 'do': 'return_ship', company: retForm.elements.company.value.trim(), number: retForm.elements.number.value.trim() }).then(function (res) {
        btn.disabled = false;
        toast(res.msg, res.ok);
        if (res.ok) setTimeout(function () { location.reload(); }, 800);
      });
    });
  }
  if (refundModal) {
    var rf = $('[data-xi-refund-form]', refundModal);
    rf.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = $('[type="submit"]', rf);
      btn.disabled = true;
      var rtype = rf.querySelector('input[name="type"]:checked');
      ajax('xi_shop_order_action', { order_no: rf.elements.order_no.value, 'do': 'refund', type: rtype ? rtype.value : 'refund', reason: rf.elements.reason.value.trim() }).then(function (res) {
        btn.disabled = false;
        toast(res.msg, res.ok);
        if (res.ok) setTimeout(function () { location.reload(); }, 800);
      });
    });
  }
  var focus = $('.xi-shop-order.is-focus');
  if (focus) setTimeout(function () { focus.scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 300);
})();
