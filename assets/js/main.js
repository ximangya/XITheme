(function () {
  var body = document.body;
  var root = document.documentElement;

  // 搜索面板、移动端菜单切换
  document.querySelectorAll('[data-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var type = btn.getAttribute('data-toggle');
      body.classList.toggle('xi-' + type + '-open');
      if (type === 'search' && body.classList.contains('xi-search-open')) {
        var input = document.querySelector('.xi-search-panel input');
        if (input) input.focus();
      }
    });
  });

  // 导航菜单放不下时（窗口较窄、浏览器缩放、翻译成外文），把末尾的菜单项收进「更多」
  (function () {
    var nav = document.querySelector('.xi-nav');
    var menu = nav && nav.querySelector('.xi-menu');
    if (!menu) return;
    var items = Array.prototype.slice.call(menu.children);
    if (items.length < 2) return;
    var more = document.createElement('li');
    more.className = 'menu-item menu-item-has-children xi-menu-more';
    more.innerHTML = '<a href="#" role="button" aria-haspopup="true" aria-expanded="false"><span></span><svg class="xi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></a><ul class="sub-menu"></ul>';
    more.firstChild.firstChild.textContent = nav.getAttribute('data-more') || '更多';
    var list = more.lastChild;
    var toggle = more.firstChild;
    var setOpen = function (open) {
      more.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    };
    toggle.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      setOpen(!more.classList.contains('is-open'));
    });
    document.addEventListener('click', function (e) { if (!more.contains(e.target)) setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });

    var mobile = window.matchMedia('(max-width: 720px)');
    var fit = function () {
      // 先全部放回原位再测量
      items.forEach(function (li) { menu.appendChild(li); });
      if (more.parentNode) menu.removeChild(more);
      more.classList.remove('current-menu-ancestor');
      if (mobile.matches || menu.scrollWidth <= nav.clientWidth + 1) return;
      menu.appendChild(more);
      for (var i = items.length - 1; i > 0 && menu.scrollWidth > nav.clientWidth + 1; i--) {
        list.insertBefore(items[i], list.firstChild);
      }
      if (list.querySelector('.current-menu-item, .current_page_item, .current-menu-ancestor')) more.classList.add('current-menu-ancestor');
    };

    var frame = 0;
    var schedule = function () {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(fit);
    };
    fit();
    window.addEventListener('resize', schedule);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(schedule);
    window.addEventListener('load', schedule);
    // 菜单文字被翻译等方式改写后重新排
    if (window.MutationObserver) {
      new MutationObserver(schedule).observe(menu, { characterData: true, subtree: true });
    }
  })();

  // 头像菜单
  var userBox = document.querySelector('.xi-user');
  var setUserMenu = function (open) {
    if (!userBox) return;
    userBox.classList.toggle('is-open', open);
    userBox.querySelector('.xi-user-btn').setAttribute('aria-expanded', open ? 'true' : 'false');
  };
  if (userBox) {
    userBox.querySelector('.xi-user-btn').addEventListener('click', function (e) {
      e.stopPropagation();
      setUserMenu(!userBox.classList.contains('is-open'));
    });
    document.addEventListener('click', function (e) {
      if (!userBox.contains(e.target)) setUserMenu(false);
    });
  }

  // Esc 关闭搜索、菜单与头像菜单
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      body.classList.remove('xi-search-open', 'xi-menu-open');
      setUserMenu(false);
    }
  });

  // 轻提示
  var toast = function (text, ok) {
    // 开启灵动岛提示时由 beautify.js 接管
    if (window.xiToastHook && window.xiToastHook(text, ok)) return;
    var el = document.createElement('div');
    el.className = 'xi-toast xi-glass' + (ok ? ' is-ok' : ' is-error');
    el.setAttribute('role', 'status');
    el.textContent = text;
    body.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-show'); });
    setTimeout(function () {
      el.classList.remove('is-show');
      setTimeout(function () { el.remove(); }, 300);
    }, ok ? 2200 : 3200);
  };
  window.xiToast = toast;

  // 第三方登录等跳转后的提示：服务端写入 xi_flash Cookie，读取后立即删除
  (function () {
    var m = document.cookie.match(/(?:^|;\s*)xi_flash=([^;]*)/);
    if (!m) return;
    document.cookie = 'xi_flash=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
    try {
      var flash = JSON.parse(decodeURIComponent(m[1]));
      if (flash && flash.msg) setTimeout(function () { toast(String(flash.msg), !!flash.ok); }, 300);
    } catch (e) {}
  })();

  // 资产操作：签到、开通会员、兑换卡密、积分解锁
  var userAction = function (data, trigger) {
    if (!window.xiUser) return Promise.resolve({ ok: false });
    data.append('nonce', xiUser.nonce);
    if (trigger) trigger.disabled = true;
    return fetch(xiUser.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { ok: false, msg: '网络错误，请稍后重试' }; })
      .then(function (res) {
        toast(res.msg, res.ok);
        if (res.ok && res.reload) {
          setTimeout(function () { location.reload(); }, 900);
        } else if (trigger) {
          trigger.disabled = false;
        }
        return res;
      });
  };

  // 顶部组件按钮的点击动作：搜索、深浅色、回到顶部（登录、签到复用 data-xi-sign / data-xi-checkin）
  document.addEventListener('click', function (e) {
    var hs = e.target.closest('[data-xi-hs-action]');
    if (!hs) return;
    var act = hs.getAttribute('data-xi-hs-action');
    if (act === 'search') {
      var s = document.querySelector('.xi-header [data-toggle="search"]');
      if (s) { s.click(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
    } else if (act === 'theme') {
      var t = document.querySelector('.xi-theme-toggle');
      if (t) t.click();
      else {
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('xi-theme', next); } catch (err) {}
      }
    } else if (act === 'top') {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });

  document.addEventListener('click', function (e) {
    var unbind = e.target.closest('[data-xi-oauth-unbind]');
    if (unbind && !unbind.disabled) {
      if (!confirm('确定解除绑定吗？解绑后不能再用该账号登录。')) return;
      var d0 = new FormData();
      d0.append('action', 'xi_oauth_unbind');
      d0.append('type', unbind.getAttribute('data-xi-oauth-unbind'));
      userAction(d0, unbind);
      return;
    }
    var checkin = e.target.closest('[data-xi-checkin]');
    if (checkin && !checkin.disabled) {
      var d1 = new FormData();
      d1.append('action', 'xi_checkin');
      userAction(d1, checkin);
      return;
    }
    var vip = e.target.closest('[data-xi-vip]');
    if (vip && !vip.disabled) {
      if (!confirm('确定使用 ' + vip.textContent.trim() + ' 开通吗？')) return;
      var d2 = new FormData();
      d2.append('action', 'xi_buy_vip');
      d2.append('level', vip.getAttribute('data-xi-vip'));
      d2.append('plan', vip.getAttribute('data-plan'));
      d2.append('pay', vip.getAttribute('data-pay'));
      userAction(d2, vip);
      return;
    }
    var unlock = e.target.closest('[data-xi-unlock]');
    if (unlock && !unlock.disabled) {
      if (!confirm(unlock.textContent.trim() + '？')) return;
      var d3 = new FormData();
      d3.append('action', 'xi_unlock');
      d3.append('post', unlock.getAttribute('data-xi-unlock'));
      userAction(d3, unlock);
      return;
    }
    // 用户中心标签
    var tab = e.target.closest('[data-uc-tab]');
    if (tab) {
      var name = tab.getAttribute('data-uc-tab');
      var panel = document.querySelector('[data-uc-panel="' + name + '"]');
      if (!panel) return;
      e.preventDefault();
      document.querySelectorAll('[data-uc-panel]').forEach(function (p) { p.classList.toggle('is-active', p === panel); });
      document.querySelectorAll('.xi-uc-nav [data-uc-tab]').forEach(function (a) { a.classList.toggle('is-active', a.getAttribute('data-uc-tab') === name); });
      revealTab();
      history.replaceState(null, '', tab.href);
    }
  });

  // 手机上标签栏横向滚动，把当前标签滚到可见位置
  var revealTab = function () {
    var nav = document.querySelector('.xi-uc-nav');
    var cur = nav && nav.querySelector('a.is-active');
    if (cur && nav.scrollWidth > nav.clientWidth) {
      nav.scrollTo({ left: cur.offsetLeft - (nav.clientWidth - cur.offsetWidth) / 2, behavior: 'smooth' });
    }
  };
  revealTab();

  document.querySelectorAll('[data-xi-redeem]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var data = new FormData(form);
      data.append('action', 'xi_redeem');
      userAction(data, form.querySelector('button'));
    });
  });

  // 账号设置：资料、邮箱、密码表单
  document.querySelectorAll('[data-xi-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var pass = form.querySelector('[name="new_pass"]');
      var again = form.querySelector('[name="new_pass2"]');
      if (pass && again && pass.value !== again.value) { toast('两次输入的新密码不一致', false); again.focus(); return; }
      var data = new FormData(form);
      data.append('action', form.getAttribute('data-xi-form'));
      userAction(data, form.querySelector('[type="submit"]'));
    });
  });

  // 密码显示 / 隐藏
  document.querySelectorAll('[data-xi-eye]').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      var input = btn.parentNode.querySelector('input');
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? '隐藏密码' : '显示密码');
    });
  });

  // 字数统计
  document.querySelectorAll('[data-xi-count]').forEach(function (count) {
    var field = count.closest('.xi-field').querySelector('textarea');
    var max = field.getAttribute('maxlength');
    field.addEventListener('input', function () { count.textContent = field.value.length + ' / ' + max; });
  });

  // 新邮箱验证码
  document.querySelectorAll('[data-xi-email-code]').forEach(function (btn) {
    var label = btn.textContent;
    btn.addEventListener('click', function () {
      var field = btn.getAttribute('data-field') || 'email';
      var input = btn.form.querySelector('[name="' + field + '"]');
      if (!input.checkValidity() || !input.value) { input.reportValidity(); return; }
      var data = new FormData();
      data.append('action', btn.getAttribute('data-action') || 'xi_email_code');
      data.append(field, input.value);
      btn.disabled = true;
      userAction(data).then(function (res) {
        if (!res.ok || !res.countdown) { btn.disabled = false; return; }
        var left = res.countdown;
        var timer = setInterval(function () {
          btn.textContent = left-- + ' 秒后重发';
          if (left < 0) { clearInterval(timer); btn.textContent = label; btn.disabled = false; }
        }, 1000);
      });
    });
  });

  // 更换头像 / 主页封面
  document.querySelectorAll('[data-xi-upload]').forEach(function (btn) {
    var kind = btn.getAttribute('data-xi-upload');
    var input = document.querySelector('[data-xi-upload-file="' + kind + '"]');
    var max = kind === 'cover' ? 5 : 2;
    if (!input) return;
    btn.addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () {
      var file = input.files[0];
      input.value = '';
      if (!file) return;
      if (file.size > max * 1024 * 1024) { toast((kind === 'cover' ? '封面' : '头像') + '不能超过 ' + max + ' MB', false); return; }
      var data = new FormData();
      data.append('action', 'xi_avatar_upload');
      data.append('kind', kind);
      data.append('file', file);
      userAction(data, btn);
    });
  });

  // 深浅色切换
  var toggle = document.querySelector('.xi-theme-toggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('xi-theme', next); } catch (e) {}
    });
  }

  // 幻灯片
  var isMobile = window.matchMedia('(max-width: 720px)');

  function loadSlideMedia(slide) {
    if (!slide) return;
    slide.querySelectorAll('img[data-src]').forEach(function (img) {
      img.src = img.getAttribute('data-src');
      img.removeAttribute('data-src');
    });
    if (!isMobile.matches) {
      slide.querySelectorAll('video[data-src]').forEach(function (video) {
        video.src = video.getAttribute('data-src');
        video.removeAttribute('data-src');
      });
    }
  }

  function initSlider(el) {
    // 移除当前设备隐藏的幻灯片及对应指示点
    var allDots = el.querySelectorAll('.xi-slider-dots button');
    el.querySelectorAll('.xi-slide').forEach(function (s, i) {
      if (getComputedStyle(s).display === 'none') {
        s.parentNode.removeChild(s);
        if (allDots[i]) allDots[i].parentNode.removeChild(allDots[i]);
      }
    });
    var slides = Array.prototype.slice.call(el.querySelectorAll('.xi-slide'));
    var dots = Array.prototype.slice.call(el.querySelectorAll('.xi-slider-dots button'));
    if (!slides.length) return;
    slides.forEach(function (s, i) {
      s.classList.toggle('is-active', i === 0);
      if (i === 0) { s.setAttribute('data-pos', 'active'); } else { s.removeAttribute('data-pos'); }
    });
    dots.forEach(function (d, i) { d.classList.toggle('is-active', i === 0); });
    if (slides.length < 2) {
      el.querySelectorAll('.xi-slider-btn, .xi-slider-dots').forEach(function (n) { n.style.display = 'none'; });
    }
    var effect = el.getAttribute('data-effect');
    var loop = el.getAttribute('data-loop') === '1';
    var delay = parseInt(el.getAttribute('data-autoplay'), 10) || 0;
    var len = slides.length;
    var cur = 0;
    var timer = null;

    var playVideo = function (slide, play) {
      var video = slide.querySelector('video');
      if (!video || !video.src) return;
      if (play) { video.play().catch(function () {}); } else { video.pause(); }
    };

    loadSlideMedia(slides[0]);
    playVideo(slides[0], true);
    if (len < 2) return;
    loadSlideMedia(slides[1]);

    var go = function (n, back) {
      if (n === cur) return;
      if (n < 0 || n >= len) {
        if (!loop) return;
        n = (n + len) % len;
      }
      var incoming = slides[n];
      var outgoing = slides[cur];

      loadSlideMedia(incoming);
      loadSlideMedia(slides[(n + 1) % len]);

      if (effect === 'slide') {
        incoming.style.transition = 'none';
        incoming.setAttribute('data-pos', back ? 'prev' : 'next');
        void incoming.offsetWidth;
        incoming.style.transition = '';
      }
      slides.forEach(function (s) {
        if (s !== incoming && s !== outgoing) s.removeAttribute('data-pos');
      });
      outgoing.setAttribute('data-pos', back ? 'next' : 'prev');
      incoming.setAttribute('data-pos', 'active');
      outgoing.classList.remove('is-active');
      incoming.classList.add('is-active');

      playVideo(outgoing, false);
      playVideo(incoming, true);

      dots.forEach(function (d, i) { d.classList.toggle('is-active', i === n); });
      cur = n;
    };

    var stop = function () { clearInterval(timer); timer = null; };
    var start = function () {
      stop();
      if (delay) timer = setInterval(function () { go(cur + 1 >= len && !loop ? 0 : cur + 1); }, delay);
    };

    var prev = el.querySelector('.xi-slider-btn.is-prev');
    var next = el.querySelector('.xi-slider-btn.is-next');
    if (prev) prev.addEventListener('click', function () { go(cur - 1, true); start(); });
    if (next) next.addEventListener('click', function () { go(cur + 1); start(); });
    dots.forEach(function (d, i) {
      d.addEventListener('click', function () { go(i, i < cur); start(); });
    });

    // 触摸 / 鼠标拖动切换
    var startX = null;
    var dragged = false;
    el.addEventListener('pointerdown', function (e) {
      if (e.target.closest('input, .xi-slider-overlay form')) return;
      startX = e.clientX;
      dragged = false;
    });
    el.addEventListener('pointerup', function (e) {
      if (startX === null) return;
      var dx = e.clientX - startX;
      startX = null;
      if (Math.abs(dx) > 40) {
        dragged = true;
        if (dx < 0) { go(cur + 1); } else { go(cur - 1, true); }
        start();
      }
    });
    el.addEventListener('click', function (e) {
      if (dragged) { e.preventDefault(); dragged = false; }
    }, true);
    el.addEventListener('dragstart', function (e) { e.preventDefault(); });

    el.addEventListener('mouseenter', stop);
    el.addEventListener('mouseleave', start);
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { stop(); } else { start(); }
    });
    start();
  }

  document.querySelectorAll('.xi-slider').forEach(initSlider);

  // 模块入场动画
  var animated = document.querySelectorAll('.xi-ani');
  if (animated.length) {
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('is-in');
            io.unobserve(entry.target);
          }
        });
      }, { rootMargin: '0px 0px -40px 0px' });
      animated.forEach(function (el) { io.observe(el); });
    } else {
      animated.forEach(function (el) { el.classList.add('is-in'); });
    }
  }

  // 文章目录
  var tocLinks = [];
  document.querySelectorAll('.xi-toc').forEach(function (toc) {
    var content = document.querySelector(toc.getAttribute('data-target') || '.xi-content');
    var heads = content ? content.querySelectorAll('h2, h3') : [];
    if (!heads.length) {
      var widget = toc.closest('.xi-widget');
      (widget || toc).style.display = 'none';
      return;
    }
    heads.forEach(function (h, i) {
      if (!h.id) h.id = 'xi-toc-' + (i + 1);
      var a = document.createElement('a');
      a.href = '#' + h.id;
      a.textContent = h.textContent;
      if (h.tagName === 'H3') a.className = 'is-h3';
      toc.appendChild(a);
      tocLinks.push({ link: a, head: h });
    });
  });

  // 回到顶部、阅读进度、目录高亮
  var toTop = document.querySelector('.xi-to-top');
  var progress = document.querySelector('.xi-progress');
  var article = document.querySelector('.xi-article');
  var ticking = false;

  var update = function () {
    ticking = false;
    var y = window.scrollY;
    if (toTop) toTop.classList.toggle('is-show', y > 400);
    if (progress && article) {
      var start = article.offsetTop;
      var total = article.offsetHeight - window.innerHeight;
      var ratio = total > 0 ? (y - start) / total : 1;
      progress.style.transform = 'scaleX(' + Math.min(1, Math.max(0, ratio)) + ')';
    }
    if (tocLinks.length) {
      var current = tocLinks[0];
      tocLinks.forEach(function (item) {
        if (item.head.getBoundingClientRect().top < 120) current = item;
      });
      tocLinks.forEach(function (item) {
        item.link.classList.toggle('is-active', item === current);
      });
    }
  };
  var onScroll = function () {
    if (!ticking) {
      ticking = true;
      window.requestAnimationFrame(update);
    }
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  update();

  if (toTop) {
    toTop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
})();
