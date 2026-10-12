/* 曦主题：浏览量、点赞、收藏、关注、弹窗、复制、图片灯箱、全站公告 */
(function () {
  'use strict';

  var cfg = window.xiPost || {};
  // wp_localize_script 会把数字转成字符串
  cfg.post = parseInt(cfg.post, 10) || 0;
  var html = document.documentElement;
  var toast = function (text, ok) { if (window.xiToast) window.xiToast(text, ok); };

  var post = function (data) {
    var body = new FormData();
    Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
    body.append('nonce', cfg.nonce);
    return fetch(cfg.ajax, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { ok: false, msg: '网络错误，请稍后重试' }; });
  };

  var store = function (type) {
    try { return window[type]; } catch (e) { return null; }
  };

  /* ---------- 弹窗 ---------- */
  var openModal = function (modal) {
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    html.classList.add('xi-modal-open');
  };
  var closeModal = function (modal) {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.xi-modal.is-open, .xi-sign.is-open')) html.classList.remove('xi-modal-open');
  };
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.querySelectorAll('.xi-modal.is-open').forEach(closeModal);
  });

  var askLogin = function () {
    var trigger = document.querySelector('[data-xi-sign="login"]');
    if (trigger) trigger.click();
    else if (cfg.login) location.href = cfg.login;
  };

  /* ---------- 浏览量：同一会话每篇只计一次 ---------- */
  if (cfg.post && cfg.views) {
    var ss = store('sessionStorage');
    var key = 'xi-viewed-' + cfg.post;
    if (!ss || !ss.getItem(key)) {
      post({ action: 'xi_view', post: cfg.post }).then(function (res) {
        if (!res.ok) return;
        if (ss) ss.setItem(key, '1');
        document.querySelectorAll('.xi-article-head [data-xi-views], .xi-post-hero [data-xi-views]').forEach(function (el) { el.textContent = res.views >= 10000 ? (res.views / 10000).toFixed(1) + '万' : res.views; });
      });
    }
  }

  /* ---------- 点击事件 ---------- */
  var toggle = function (btn, data, after) {
    if (btn.disabled) return;
    btn.disabled = true;
    post(data).then(function (res) {
      btn.disabled = false;
      if (!res.ok) {
        toast(res.msg || '操作失败', false);
        if (res.login) askLogin();
        return;
      }
      btn.classList.toggle('is-active', res.active);
      btn.setAttribute('aria-pressed', res.active ? 'true' : 'false');
      var count = btn.querySelector('em');
      if (count) count.textContent = res.count;
      toast(res.msg, true);
      if (after) after(res);
    });
  };

  // 游客点赞记录保存在本地，打开页面时恢复按钮状态
  var guest = !document.body.classList.contains('logged-in');
  var likedStore = store('localStorage');
  var likedList = function () {
    try { return JSON.parse((likedStore && likedStore.getItem('xi-liked')) || '[]'); } catch (err) { return []; }
  };
  if (guest) {
    var liked = likedList();
    document.querySelectorAll('[data-xi-like]').forEach(function (b) {
      if (liked.indexOf(b.getAttribute('data-xi-like')) > -1) { b.classList.add('is-active'); b.setAttribute('aria-pressed', 'true'); }
    });
  }

  document.addEventListener('click', function (e) {
    var el;
    if ((el = e.target.closest('[data-xi-like]'))) {
      var likeId = el.getAttribute('data-xi-like');
      toggle(el, { action: 'xi_like', post: likeId }, function (res) {
        if (!guest || !likedStore) return;
        var list = likedList().filter(function (id) { return id !== likeId; });
        if (res.active) list.push(likeId);
        try { likedStore.setItem('xi-liked', JSON.stringify(list.slice(-300))); } catch (err) {}
      });
      if (!el.classList.contains('is-active')) {
        el.classList.add('is-pop');
        setTimeout(function () { el.classList.remove('is-pop'); }, 500);
      }
    } else if ((el = e.target.closest('[data-xi-fav]'))) {
      var row = el.hasAttribute('data-remove') ? el.closest('li') : null;
      toggle(el, { action: 'xi_favorite', post: el.getAttribute('data-xi-fav') }, function (res) {
        if (row && !res.active) row.remove();
      });
    } else if ((el = e.target.closest('[data-xi-follow]'))) {
      toggle(el, { action: 'xi_follow', user: el.getAttribute('data-xi-follow') }, function (res) {
        el.classList.toggle('xi-btn-accent', !res.active);
        var fans = document.querySelector('[data-xi-followers]');
        if (fans) fans.textContent = res.count;
      });
    } else if ((el = e.target.closest('[data-xi-modal]'))) {
      openModal(document.getElementById(el.getAttribute('data-xi-modal')));
    } else if ((el = e.target.closest('[data-modal-close]'))) {
      closeModal(el.closest('.xi-modal'));
    } else if ((el = e.target.closest('[data-xi-copy]'))) {
      var input = el.parentNode.querySelector('input');
      copyText(input.value).then(function () { toast('链接已复制', true); }, function () { input.select(); toast('请手动复制', false); });
    }
  });

  var copyText = function (text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.style.cssText = 'position:fixed;opacity:0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy') ? resolve() : reject(); } catch (err) { reject(err); }
      ta.remove();
    });
  };

  var content = document.querySelector('.xi-article .xi-content');

  /* ---------- AI 摘要：进入视野后打字机效果 ---------- */
  document.querySelectorAll('[data-xi-typing]').forEach(function (el) {
    var full = el.textContent;
    if (!full || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;
    el.style.minHeight = el.offsetHeight + 'px';
    el.textContent = '';
    el.classList.add('is-typing');
    var io = new IntersectionObserver(function (entries) {
      if (!entries[0].isIntersecting) return;
      io.disconnect();
      var i = 0;
      var step = Math.max(1, Math.round(full.length / 120));
      var timer = setInterval(function () {
        i = Math.min(full.length, i + step);
        el.textContent = full.slice(0, i);
        if (i >= full.length) { clearInterval(timer); el.classList.remove('is-typing'); el.style.minHeight = ''; }
      }, 24);
    });
    io.observe(el);
  });

  /* ---------- 评论表情 ---------- */
  var EMOJI = {
    '笑脸': '😀 😁 😂 🤣 😊 😇 🙂 😉 😍 🥰 😘 😋 😜 🤪 😎 🤩 🥳 😏 😌 🤗 🤭 🤔 🤨 😐 😶 🙄 😬 😴 🤤 😪 😮 😯 😲 🥺 😳 😢 😭 😤 😡 🤯 😱 😨 😰 😓 🤧 🤒 🤕 😵 🥴 🤠 🤡 👻 💀 🤖 👽 😺 😸 😹 😻 🙈 🙉 🙊',
    '手势': '👍 👎 👏 🙌 👐 🤝 🙏 ✌️ 🤞 🤟 🤘 👌 🤏 👈 👉 👆 👇 ☝️ ✋ 🤚 🖐️ 🖖 👋 🤙 💪 ✍️ 🫶 🤲 👀 🧠',
    '物品': '❤️ 🧡 💛 💚 💙 💜 🖤 🤍 💔 💯 💢 💥 💫 💦 💨 🔥 ✨ 🌟 ⭐ 🎉 🎊 🎁 🏆 🥇 🎮 🎵 🎧 📷 💻 📱 ☕ 🍵 🍺 🍻 🍰 🍉 🍎 🍜 🍔 🍟 🌹 🌸 🍀 🌈 ☀️ 🌙 ⚡ ☔ ❄️',
    '动物': '🐶 🐱 🐭 🐹 🐰 🦊 🐻 🐼 🐨 🐯 🦁 🐮 🐷 🐸 🐵 🐔 🐧 🐦 🐤 🦄 🐝 🦋 🐌 🐢 🐍 🐙 🐳 🐬 🐟 🦀',
    '符号': '✅ ❌ ❓ ❗ ⚠️ 🚫 ⭕ 🆗 🆒 🆕 🔝 💤 ➕ ➖ ✔️ ☑️ 🔔 📌 📍 🔗 🔒 🔑 💡 📢 📝 🗓️ ⏰ ♻️ 🈶 🈚'
  };
  var emojiPop = null;
  var closeEmoji = function () {
    if (!emojiPop) return;
    emojiPop.remove();
    emojiPop = null;
    document.querySelectorAll('[data-xi-emoji]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
  };
  var insertAtCaret = function (ta, text) {
    var start = ta.selectionStart || 0;
    var end = ta.selectionEnd || 0;
    ta.value = ta.value.slice(0, start) + text + ta.value.slice(end);
    ta.selectionStart = ta.selectionEnd = start + text.length;
    ta.dispatchEvent(new Event('input', { bubbles: true }));
  };
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-xi-emoji]');
    if (btn) {
      e.preventDefault();
      if (emojiPop) { closeEmoji(); return; }
      var form = btn.closest('form');
      var ta = form && form.querySelector('textarea[name="comment"]');
      if (!ta) return;
      var groups = Object.keys(EMOJI);
      emojiPop = document.createElement('div');
      emojiPop.className = 'xi-emoji-pop xi-glass';
      emojiPop.innerHTML = '<div class="xi-emoji-tabs">' + groups.map(function (g, i) { return '<button type="button" data-g="' + i + '"' + (i ? '' : ' class="is-active"') + '>' + g + '</button>'; }).join('') + '</div>'
        + groups.map(function (g, i) { return '<div class="xi-emoji-grid"' + (i ? ' hidden' : '') + '>' + EMOJI[g].split(' ').map(function (em) { return '<button type="button" class="xi-emoji-item">' + em + '</button>'; }).join('') + '</div>'; }).join('');
      btn.parentNode.appendChild(emojiPop);
      btn.setAttribute('aria-expanded', 'true');
      emojiPop.addEventListener('mousedown', function (ev) { ev.preventDefault(); }); // 保持输入框光标位置
      emojiPop.addEventListener('click', function (ev) {
        var tab = ev.target.closest('[data-g]');
        if (tab) {
          emojiPop.querySelectorAll('[data-g]').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
          emojiPop.querySelectorAll('.xi-emoji-grid').forEach(function (g, i) { g.hidden = String(i) !== tab.getAttribute('data-g'); });
          return;
        }
        var item = ev.target.closest('.xi-emoji-item');
        if (item) { insertAtCaret(ta, item.textContent); ta.focus(); }
      });
      return;
    }
    if (emojiPop && !emojiPop.contains(e.target)) closeEmoji();
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeEmoji(); });

  /* ---------- 隐藏内容：密码可见、定时公开倒计时 ---------- */
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-xi-hide-pw]');
    if (!form) return;
    e.preventDefault();
    var btn = form.querySelector('button');
    var data = new FormData();
    data.append('action', 'xi_hide_password');
    data.append('post_id', form.getAttribute('data-xi-hide-pw'));
    data.append('pw', form.pw.value);
    btn.disabled = true;
    fetch(cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { ok: false, msg: '网络错误，请稍后重试' }; })
      .then(function (res) {
        toast(res.msg, res.ok);
        if (res.ok) { setTimeout(function () { location.reload(); }, 600); return; }
        btn.disabled = false;
        form.pw.select();
      });
  });
  document.querySelectorAll('[data-xi-countdown]').forEach(function (el) {
    var at = parseInt(el.getAttribute('data-xi-countdown'), 10) * 1000;
    var tick = function () {
      var left = Math.max(0, Math.round((at - Date.now()) / 1000));
      if (!left) { el.textContent = '已到公开时间，刷新查看'; clearInterval(timer); return; }
      var d = Math.floor(left / 86400), h = Math.floor(left % 86400 / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
      el.textContent = '还剩 ' + (d ? d + ' 天 ' : '') + ('0' + h).slice(-2) + ':' + ('0' + m).slice(-2) + ':' + ('0' + s).slice(-2);
    };
    var timer = setInterval(tick, 1000);
    tick();
  });

  /* 代码块复制、行号、高亮见 code.js */

  /* ---------- 图片灯箱 ---------- */
  if (content && cfg.lightbox) {
    var imgs = Array.prototype.filter.call(content.querySelectorAll('img'), function (img) {
      var link = img.closest('a');
      // 链接到其他页面的图片保持原行为
      return !img.closest('.xi-hide.is-locked') && (!link || /\.(jpe?g|png|gif|webp|avif)(\?|$)/i.test(link.href));
    });
    if (imgs.length) {
      var box = document.createElement('div');
      box.className = 'xi-lightbox';
      box.innerHTML = '<img alt=""><button type="button" class="xi-lb-close" aria-label="关闭">×</button>' +
        '<button type="button" class="xi-lb-prev" aria-label="上一张">‹</button><button type="button" class="xi-lb-next" aria-label="下一张">›</button><span class="xi-lb-count"></span>';
      document.body.appendChild(box);
      var view = box.querySelector('img');
      var index = 0;
      var full = function (img) {
        var link = img.closest('a');
        if (link) return link.href;
        // srcset 中最大的一张
        var best = img.currentSrc || img.src;
        var max = 0;
        (img.getAttribute('srcset') || '').split(',').forEach(function (part) {
          var bits = part.trim().split(/\s+/);
          var w = parseInt(bits[1], 10) || 0;
          if (w > max) { max = w; best = bits[0]; }
        });
        return best;
      };
      var show = function (i) {
        index = (i + imgs.length) % imgs.length;
        view.src = full(imgs[index]);
        view.alt = imgs[index].alt || '';
        box.querySelector('.xi-lb-count').textContent = imgs.length > 1 ? (index + 1) + ' / ' + imgs.length : '';
      };
      var close = function () {
        box.classList.remove('is-open');
        html.classList.remove('xi-modal-open');
      };
      box.classList.toggle('is-single', imgs.length < 2);
      imgs.forEach(function (img, i) {
        img.classList.add('xi-zoomable');
        img.addEventListener('click', function (e) {
          e.preventDefault();
          show(i);
          box.classList.add('is-open');
          html.classList.add('xi-modal-open');
        });
      });
      box.addEventListener('click', function (e) {
        if (e.target.closest('.xi-lb-prev')) show(index - 1);
        else if (e.target.closest('.xi-lb-next')) show(index + 1);
        else close();
      });
      document.addEventListener('keydown', function (e) {
        if (!box.classList.contains('is-open')) return;
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowLeft') show(index - 1);
        else if (e.key === 'ArrowRight') show(index + 1);
      });
      var startX = 0;
      box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
      box.addEventListener('touchend', function (e) {
        var dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 50) show(index + (dx < 0 ? 1 : -1));
      });
    }
  }

  /* ---------- 全站公告 ---------- */
  var notice = document.getElementById('xi-notice');
  if (notice) {
    var repeat = notice.getAttribute('data-repeat');
    var hash = notice.getAttribute('data-notice');
    var interval = (parseInt(notice.getAttribute('data-interval'), 10) || 0) * 1000;
    var delay = (parseInt(notice.getAttribute('data-delay'), 10) || 0) * 1000;
    var storage = store(repeat === 'session' ? 'sessionStorage' : 'localStorage');
    // 记录格式：公告指纹|关闭时间戳
    var saved = ((storage && storage.getItem('xi-notice')) || '').split('|');
    var last = parseInt(saved[1], 10) || 0;
    var show = saved[0] !== hash;
    if (!show && repeat === 'daily') show = new Date(last).toDateString() !== new Date().toDateString();
    if (!show && repeat === 'interval') show = Date.now() - last >= interval;
    if (show) {
      setTimeout(function () { openModal(notice); }, Math.max(delay, 300));
      var remember = function () { if (storage) storage.setItem('xi-notice', hash + '|' + Date.now()); };
      notice.addEventListener('click', function (e) {
        if (e.target.closest('[data-modal-close], a')) remember();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && notice.classList.contains('is-open')) remember();
      }, true); // 捕获阶段执行，赶在 Esc 关闭弹窗之前
    }
  }
})();
