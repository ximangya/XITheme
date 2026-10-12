/* 编辑器区块的前台交互：复制提取码、选项卡切换 */
(function () {
  var toast = function (text, ok) {
    if (window.xiToast) window.xiToast(text, ok);
  };

  var copy = function (text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text);
    }
    // http 站点没有 clipboard API，退回到 execCommand
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.cssText = 'position:fixed;top:-100px;opacity:0';
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) {}
      ta.remove();
      if (ok) resolve(); else reject();
    });
  };

  // 点「提取码」复制；点带提取码的下载按钮时顺便复制，链接照常在新窗口打开
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-xi-copy]');
    if (!el) return;
    var code = el.getAttribute('data-xi-copy');
    var isLink = el.tagName === 'A';
    copy(code).then(function () {
      toast(isLink ? '提取码 ' + code + ' 已复制' : '已复制', true);
    }, function () {
      if (!isLink) toast('复制失败，请手动选择', false);
    });
  });

  // 倒计时区块、满减活动：data-xi-bx-countdown 是结束时间（秒级时间戳）
  document.querySelectorAll('[data-xi-bx-countdown]').forEach(function (box) {
    var end = parseInt(box.getAttribute('data-xi-bx-countdown'), 10) * 1000;
    var nums = {};
    box.querySelectorAll('[data-k]').forEach(function (n) { nums[n.getAttribute('data-k')] = n; });
    var pad = function (n) { return n < 10 ? '0' + n : String(n); };
    var timer;
    var tick = function () {
      var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
      var d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
      if (nums.d) {
        nums.d.textContent = pad(d); nums.h.textContent = pad(h); nums.m.textContent = pad(m); nums.s.textContent = pad(s);
      } else {
        box.textContent = left ? '距结束 ' + (d ? d + ' 天 ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s) : (box.getAttribute('data-done') || '');
      }
      if (!left) {
        clearInterval(timer);
        box.classList.add('is-done');
        var done = box.querySelector('.xi-bx-countdown-done');
        if (done) done.hidden = false;
      }
    };
    timer = setInterval(tick, 1000);
    tick();
  });

  // 选项卡：点击切换，支持左右方向键
  document.querySelectorAll('.xi-block-tabs').forEach(function (box) {
    var tabs = Array.prototype.slice.call(box.querySelectorAll(':scope > .xi-block-tabs-nav > [role="tab"]'));
    var select = function (tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
      if (focus) tab.focus();
    };
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { select(t); });
      t.addEventListener('keydown', function (e) {
        var d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
        if (!d) return;
        e.preventDefault();
        select(tabs[(i + d + tabs.length) % tabs.length], true);
      });
    });
  });
})();
