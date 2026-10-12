/* 曦主题论坛：关注版块、帖子管理（置顶、加精、审核、删除） */
(function () {
  'use strict';

  var cfg = window.xiBbs || {};
  var toast = function (text, ok) { if (window.xiToast) window.xiToast(text, ok); };
  var askLogin = function () {
    var trigger = document.querySelector('[data-xi-sign="login"]');
    if (trigger) trigger.click();
  };

  var post = function (data) {
    var body = new FormData();
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
    body.append('nonce', cfg.nonce || '');
    return fetch(cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { ok: false, msg: '网络错误，请稍后重试' }; });
  };

  var confirms = { delete: '确定删除这个帖子吗？' };

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-xi-plate-follow]');
    if (btn) {
      if (btn.disabled) return;
      btn.disabled = true;
      post({ action: 'xi_bbs_follow', plate: btn.getAttribute('data-xi-plate-follow') }).then(function (res) {
        btn.disabled = false;
        toast(res.msg || '操作失败', res.ok);
        if (!res.ok) { if (res.login) askLogin(); return; }
        btn.classList.toggle('is-active', res.active);
        btn.classList.toggle('xi-btn-accent', !res.active);
        btn.setAttribute('aria-pressed', res.active ? 'true' : 'false');
        btn.querySelector('span').textContent = res.active ? '已关注' : '关注';
        var count = document.querySelector('[data-xi-plate-followers]');
        if (count) count.textContent = res.count;
      });
      return;
    }

    var act = e.target.closest('[data-xi-bbs]');
    if (act) {
      var what = act.getAttribute('data-xi-bbs');
      if (confirms[what] && !window.confirm(confirms[what])) return;
      act.disabled = true;
      post({ action: 'xi_bbs_manage', do: what, post: act.getAttribute('data-post') }).then(function (res) {
        act.disabled = false;
        toast(res.msg || '操作失败', res.ok);
        if (!res.ok) return;
        if (res.redirect) setTimeout(function () { location.href = res.redirect; }, 700);
        else if (res.reload) setTimeout(function () { location.reload(); }, 700);
      });
      return;
    }

    // 点击菜单外部时收起管理菜单
    document.querySelectorAll('.xi-bbs-manage[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });
})();
