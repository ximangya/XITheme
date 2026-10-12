(function () {
  var modal = document.getElementById('xi-sign');
  if (!modal || !window.xiSign) return;

  var msgBox = modal.querySelector('.xi-sign-msg');
  var tabs = modal.querySelectorAll('[data-sign-tab]');
  var forms = modal.querySelectorAll('[data-sign-form]');
  var current = 'login';
  var lastFocus = null;

  var showMsg = function (text, ok) {
    msgBox.textContent = text || '';
    msgBox.className = 'xi-sign-msg' + (text ? (ok ? ' is-ok' : ' is-error') : '');
  };

  var send = function (data) {
    data.set('nonce', xiSign.nonce);
    return fetch(xiSign.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (text) {
        try { return JSON.parse(text); } catch (e) { return { ok: false, msg: '服务器返回异常，请刷新页面后重试' }; }
      })
      .catch(function () { return { ok: false, msg: '网络错误，请稍后重试' }; });
  };

  // 页面被缓存导致 nonce 过期时，换新 nonce 后自动重试一次
  var post = function (data) {
    return send(data).then(function (res) {
      if (!res || !res.expired) return res;
      var fresh = new FormData();
      fresh.append('action', 'xi_sign_nonce');
      return send(fresh).then(function (r) {
        if (!r.ok || !r.nonce) return res;
        xiSign.nonce = r.nonce;
        return send(data);
      });
    });
  };

  // 人机验证
  var loadCaptcha = function (form) {
    var btn = form.querySelector('.xi-captcha');
    if (!btn) return;
    btn.textContent = '…';
    var data = new FormData();
    data.append('action', 'xi_captcha');
    post(data).then(function (res) {
      if (!res.ok) { btn.textContent = '点击重试'; return; }
      btn.textContent = res.question;
      form.querySelector('[name="captcha_token"]').value = res.token;
      form.querySelector('[name="captcha"]').value = '';
    });
  };

  var switchTab = function (type) {
    var form = modal.querySelector('[data-sign-form="' + type + '"]');
    if (!form) { type = 'login'; form = modal.querySelector('[data-sign-form="login"]'); }
    current = type;
    tabs.forEach(function (t) {
      var name = t.getAttribute('data-sign-tab');
      t.classList.toggle('is-active', name === type);
      t.setAttribute('aria-selected', name === type ? 'true' : 'false');
      if (name === 'lostpwd') t.classList.toggle('is-hidden', type !== 'lostpwd');
    });
    forms.forEach(function (f) { f.classList.toggle('is-active', f === form); });
    showMsg('');
    if (xiSign.captcha) loadCaptcha(form);
    var first = form.querySelector('input:not([type="hidden"])');
    if (first) setTimeout(function () { first.focus(); }, 60);
  };

  var open = function (type) {
    lastFocus = document.activeElement;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.documentElement.classList.add('xi-modal-open');
    switchTab(type || 'login');
  };

  var close = function () {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.documentElement.classList.remove('xi-modal-open');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  };

  // 跳转地址：优先 redirect_to，同源才使用
  var params = new URLSearchParams(location.search);
  var redirectTarget = function () {
    var to = params.get('redirect_to');
    if (to) {
      try {
        var u = new URL(decodeURIComponent(to), location.href);
        if (u.origin === location.origin) return u.href;
      } catch (e) {}
    }
    var here = new URL(location.href);
    here.searchParams.delete('xi_sign');
    here.searchParams.delete('redirect_to');
    return here.href;
  };

  // 触发：带 data-xi-sign 的元素、指向 ?xi_sign= 的链接
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-xi-sign], a[href*="xi_sign="]');
    if (!el) return;
    var type = el.getAttribute('data-xi-sign');
    if (!type) {
      try { type = new URL(el.href, location.href).searchParams.get('xi_sign'); } catch (err) { return; }
    }
    if (!type) return;
    e.preventDefault();
    if (modal.classList.contains('is-open')) { switchTab(type); } else { open(type); }
  });

  modal.addEventListener('click', function (e) {
    if (e.target.closest('[data-sign-close]')) close();
    var tab = e.target.closest('[data-sign-tab]');
    if (tab) switchTab(tab.getAttribute('data-sign-tab'));
    var cap = e.target.closest('.xi-captcha');
    if (cap) loadCaptcha(cap.closest('form'));
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
  });

  // 发送邮箱验证码
  modal.querySelectorAll('.xi-send-code').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (btn.disabled) return;
      var form = btn.closest('form');
      var data = new FormData();
      data.append('action', 'xi_send_code');
      data.append('purpose', btn.getAttribute('data-purpose'));
      var target = form.querySelector('[name="' + (btn.getAttribute('data-field') || 'email') + '"]');
      if (!target || !target.value.trim()) { showMsg((target ? target.getAttribute('placeholder') : '接收账号') + '不能为空', false); if (target) target.focus(); return; }
      data.append('to', target.value.trim());
      var cap = form.querySelector('[name="captcha"]');
      if (cap && !cap.value.trim()) { showMsg('请先填写人机验证答案', false); cap.focus(); return; }
      if (cap) {
        data.append('captcha', cap.value);
        data.append('captcha_token', form.querySelector('[name="captcha_token"]').value);
      }
      var text = btn.textContent;
      btn.disabled = true;
      btn.textContent = '发送中…';
      post(data).then(function (res) {
        showMsg(res.msg, res.ok);
        if (res.refresh && xiSign.captcha) loadCaptcha(form);
        if (!res.ok) { btn.disabled = false; btn.textContent = text; return; }
        var left = res.countdown || 60;
        var timer = setInterval(function () {
          left--;
          btn.textContent = left + ' 秒后重发';
          if (left <= 0) { clearInterval(timer); btn.disabled = false; btn.textContent = text; }
        }, 1000);
        btn.textContent = left + ' 秒后重发';
        var code = form.querySelector('[name="code"]');
        if (code) code.focus();
      });
    });
  });

  // 提交
  forms.forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var empty = Array.prototype.find.call(form.querySelectorAll('input[required]'), function (i) { return !i.value.trim(); });
      if (empty) { showMsg(empty.getAttribute('placeholder') + '不能为空', false); empty.focus(); return; }

      var type = form.getAttribute('data-sign-form');
      var data = new FormData(form);
      data.append('action', 'xi_' + type);
      var submit = form.querySelector('.xi-sign-submit');
      submit.disabled = true;

      post(data).then(function (res) {
        showMsg(res.msg, res.ok);
        submit.disabled = false;
        if (res.ok && res.reload) {
          submit.disabled = true;
          location.href = redirectTarget();
          return;
        }
        if (res.ok && res.switch) {
          var msg = res.msg;
          switchTab(res.switch);
          showMsg(msg, true);
          var user = modal.querySelector('[data-sign-form="login"] [name="user"]');
          if (res.login && user) user.value = res.login;
          return;
        }
        if (res.refresh && xiSign.captcha) loadCaptcha(form);
      });
    });
  });

  // 地址带 ?xi_sign= 时自动打开
  var auto = params.get('xi_sign');
  if (auto) open(auto);
})();
