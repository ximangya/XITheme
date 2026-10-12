/* 曦主题：个人主页管理员一键封禁 / 解封（配置见 window.xiBan） */
(function () {
  'use strict';
  var cfg = window.xiBan;
  var form = document.querySelector('[data-xi-ban]');
  if (!cfg || !form) return;

  var toast = function (text, ok) { if (window.xiToast) window.xiToast(text, ok); };
  var custom = form.querySelector('input[name="custom_days"]');
  var customRadio = form.querySelector('input[name="days"][value="custom"]');

  // 点自定义输入框时自动选中“自定义”
  if (custom && customRadio) {
    custom.addEventListener('focus', function () { customRadio.checked = true; });
    customRadio.addEventListener('change', function () { if (customRadio.checked) custom.focus(); });
  }

  var send = function (data, btn) {
    data.append('action', 'xi_ban_user');
    data.append('nonce', cfg.nonce);
    var buttons = form.querySelectorAll('button');
    buttons.forEach(function (b) { b.disabled = true; });
    if (btn) btn.classList.add('is-loading');
    return fetch(cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { ok: false, msg: '网络错误，请稍后重试' }; })
      .then(function (res) {
        toast(res.msg, res.ok);
        if (res.ok && res.reload) {
          setTimeout(function () { location.reload(); }, 900);
          return;
        }
        buttons.forEach(function (b) { b.disabled = false; });
        if (btn) btn.classList.remove('is-loading');
      });
  };

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var data = new FormData(form);
    var days = data.get('days');
    if (days === 'custom') {
      var n = parseFloat(custom.value);
      if (!(n > 0)) {
        toast('请填写自定义天数', false);
        custom.focus();
        return;
      }
      data.set('days', String(n));
    }
    data.delete('custom_days');
    data.append('do', 'set');
    send(data, form.querySelector('button[type="submit"]'));
  });

  var unban = form.querySelector('[data-xi-unban]');
  if (unban) {
    unban.addEventListener('click', function () {
      if (!window.confirm(cfg.confirm)) return;
      var data = new FormData();
      data.append('user', form.querySelector('input[name="user"]').value);
      data.append('do', 'remove');
      send(data, unban);
    });
  }
})();
