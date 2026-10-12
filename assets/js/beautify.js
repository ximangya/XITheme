/* 站点美化：动态标题、灵动岛、樱花 / 下雪飘落（配置见 window.xiFx） */
(function () {
  var cfg = window.xiFx || {};
  var root = document.documentElement;
  var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var store = function (key, val) {
    try {
      if (val === undefined) return sessionStorage.getItem(key);
      sessionStorage.setItem(key, val);
    } catch (e) {}
    return null;
  };

  // ---------- 动态标题 ----------
  if (cfg.title && cfg.title.leave) {
    var saved = '';
    var backTimer = 0;
    document.addEventListener('visibilitychange', function () {
      clearTimeout(backTimer);
      if (document.hidden) {
        if (document.title !== cfg.title.leave) saved = document.title;
        document.title = cfg.title.leave;
      } else if (saved) {
        if (cfg.title.back) {
          document.title = cfg.title.back;
          backTimer = setTimeout(function () { document.title = saved; }, 2000);
        } else {
          document.title = saved;
        }
      }
    });
  }

  // ---------- 灵动岛 ----------
  // 黑色胶囊：进站展开问候 → 收成胶囊（常驻模式显示当前页面标题、阅读进度）；
  // 悬停展开详情，点击回到顶部；站内提示、复制、深浅色切换都以展开的形式出现在岛上
  var greeting = function () {
    var h = new Date().getHours();
    if (h < 5) return '夜深了';
    if (h < 9) return '早上好';
    if (h < 11) return '上午好';
    if (h < 13) return '中午好';
    if (h < 17) return '下午好';
    if (h < 19) return '傍晚好';
    return '晚上好';
  };
  var source = function (own) {
    var host = '';
    try { host = document.referrer ? new URL(document.referrer).hostname : ''; } catch (e) {}
    if (!host || host === own) return '';
    var names = { baidu: '百度', google: 'Google', bing: '必应', sogou: '搜狗', 'so.com': '360 搜索', sm: '神马搜索', github: 'GitHub', bilibili: 'B站', zhihu: '知乎', weibo: '微博', 'qq.com': 'QQ', toutiao: '头条', douyin: '抖音', xiaohongshu: '小红书', csdn: 'CSDN', juejin: '掘金' };
    for (var k in names) {
      if (host.indexOf(k) !== -1) return names[k];
    }
    return host.replace(/^www\./, '');
  };
  var fill = function (tpl, vars) {
    return String(tpl || '').replace(/\{(\w+)\}/g, function (m, k) { return k in vars ? vars[k] : m; });
  };
  var svg = function (d) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
  };
  var ICONS = {
    ok: svg('<path d="m5 12.5 4.5 4.5L19 7.5"/>'),
    err: svg('<path d="M12 7v6M12 17h.01"/>'),
    moon: svg('<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/>'),
    sun: svg('<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>'),
    copy: svg('<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h8"/>'),
    up: svg('<path d="M12 19V5M6 11l6-6 6 6"/>')
  };

  var Island = function (c) {
    var self = this;
    var vars = { greeting: greeting(), name: c.name, site: c.site, title: c.pageName || c.site, from: '' };
    var from = source(c.host);
    vars.from = from ? '来自 ' + from + ' 的' : '';
    this.c = c;
    this.welcome = { title: fill(c.title, vars).trim(), desc: fill(c.desc, vars).trim() };
    this.resident = c.mode === 'resident';
    this.size = '';
    this.view = null;
    this.restore = null;
    this.hover = false;

    var el = this.el = document.createElement('div');
    // is-init：首次展开前关掉内部过渡，图标直接以最终尺寸出现
    el.className = 'xi-island is-init is-' + (c.pos === 'top' ? 'top' : 'header');
    el.setAttribute('role', 'status');
    el.setAttribute('aria-live', 'polite');
    el.innerHTML = '<div class="xi-island-body"></div><span class="xi-island-progress" aria-hidden="true"><i></i></span>';
    this.body = el.firstChild;

    // 站点图标：用户头像 > LOGO 图片 > 导航栏图标
    var img = c.avatar || c.logo;
    if (img) {
      this.mark = '<img alt="" src="' + String(img).replace(/"/g, '&quot;') + '">';
    } else {
      var logo = document.querySelector('.xi-logo-mark');
      this.mark = logo ? logo.innerHTML : '';
    }

    // 测量用的隐藏副本，避免在可见的岛上切换 width:auto 导致动画跳变
    this.ruler = document.createElement('div');
    this.ruler.className = 'xi-island is-ruler';
    this.ruler.setAttribute('aria-hidden', 'true');
    this.ruler.innerHTML = '<div class="xi-island-body"></div>';

    document.body.appendChild(this.ruler);
    document.body.appendChild(el);

    el.addEventListener('mouseenter', function () { self.hover = true; if (self.size === 'pill' && !self.restore) self.morph('open', self.detail()); });
    el.addEventListener('mouseleave', function () { self.hover = false; if (self.size === 'open' && !self.restore && self.resident && self.view && self.view.detail) self.morph('pill', self.page()); });
    el.addEventListener('click', function () { self.click(); });

    if (c.pos !== 'top') {
      this.place();
      var ticking = false;
      var onMove = function () {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () { ticking = false; self.place(); self.progress(); });
      };
      window.addEventListener('scroll', onMove, { passive: true });
      window.addEventListener('resize', onMove);
    } else {
      window.addEventListener('scroll', function () { self.progress(); }, { passive: true });
    }
  };

  // 放在导航栏下方；导航不吸顶时随页面滚走，岛最高停在屏幕顶部
  Island.prototype.place = function () {
    var bar = document.querySelector('.xi-header-inner');
    var top = bar ? bar.getBoundingClientRect().bottom + 10 : 10;
    this.el.style.setProperty('--island-top', Math.max(10, Math.round(top)) + 'px');
  };

  Island.prototype.ratio = function () {
    var max = document.documentElement.scrollHeight - window.innerHeight;
    return max > 0 ? Math.min(1, Math.max(0, window.scrollY / max)) : 0;
  };

  Island.prototype.progress = function () {
    if (!this.c.read) return;
    var p = this.ratio();
    this.el.style.setProperty('--p', p.toFixed(4));
    if (this.size === 'open' && this.view && this.view.detail) {
      var small = this.body.querySelector('small');
      if (small) small.textContent = this.detailText();
    }
  };

  Island.prototype.page = function () {
    return { title: this.c.page || document.title, wave: true };
  };

  Island.prototype.detailText = function () {
    if (this.c.read) return '已阅读 ' + Math.round(this.ratio() * 100) + '%' + (window.scrollY > 300 ? ' · 点击回到顶部' : '');
    return window.scrollY > 300 ? '点击回到顶部' : (this.welcome.desc || this.c.site);
  };

  Island.prototype.detail = function () {
    return { title: this.c.page || document.title, desc: this.detailText(), wave: true, detail: true };
  };

  Island.prototype.html = function (view) {
    var esc = function (s) { return String(s).replace(/[&<>"]/g, function (ch) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]; }); };
    var icon = view.icon ? '<span class="xi-island-icon is-' + (view.tone || 'ok') + '">' + view.icon + '</span>'
      : '<span class="xi-island-icon' + (this.c.avatar || this.c.logo ? ' is-img' : ' is-mark') + '">' + this.mark + '</span>';
    return icon
      + '<span class="xi-island-text"><b><span>' + esc(view.title) + '</span></b>' + (view.desc ? '<small>' + esc(view.desc) + '</small>' : '') + '</span>'
      + (view.wave ? '<span class="xi-island-wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>' : '');
  };

  Island.prototype.measure = function (size, html) {
    this.ruler.className = 'xi-island is-ruler is-' + size;
    this.ruler.firstChild.innerHTML = html;
    return Math.ceil(this.ruler.firstChild.scrollWidth);
  };

  // 切换尺寸与内容：内容先淡出，换好后按新内容宽度展开 / 收起
  Island.prototype.morph = function (size, view) {
    var self = this;
    var el = this.el;
    var html = this.html(view);
    var width = Math.min(this.measure(size, html), window.innerWidth - 24);
    this.view = view;
    clearTimeout(this.swapTimer);
    var apply = function () {
      self.body.innerHTML = html;
      el.classList.remove('is-swap');
      self.marquee();
    };
    var first = !this.size;
    // 先切换尺寸类再填内容：首次出现时图标不会先按胶囊尺寸渲染、再过渡到展开尺寸
    this.size = size;
    el.classList.toggle('is-open', size === 'open');
    el.classList.toggle('is-pill', size === 'pill');
    el.classList.toggle('has-progress', !!this.c.read && size === 'pill' && !view.icon);
    el.style.width = width + 'px';
    if (first) {
      apply();
    } else {
      el.classList.add('is-swap');
      this.swapTimer = setTimeout(apply, 170);
    }
  };

  // 胶囊里标题太长时来回滚动
  Island.prototype.marquee = function () {
    var b = this.body.querySelector('b');
    if (!b) return;
    var over = b.firstChild.scrollWidth - b.clientWidth;
    b.classList.toggle('is-marquee', this.size === 'pill' && over > 4);
    if (over > 4) {
      b.style.setProperty('--mq', -over + 'px');
      b.style.setProperty('--mq-time', Math.max(4, over / 18 + 3) + 's');
    }
  };

  Island.prototype.show = function () {
    var self = this;
    var first = this.welcome.title ? { title: this.welcome.title, desc: this.welcome.desc, wave: true } : this.page();
    this.morph(this.welcome.title ? 'open' : 'pill', first);
    requestAnimationFrame(function () {
      self.el.classList.add('is-in');
      // 入场动画开始后再恢复内部过渡
      requestAnimationFrame(function () { self.el.classList.remove('is-init'); });
    });
    this.timer = setTimeout(function () { self.settle(); }, (this.welcome.title ? this.c.duration : 1) * 1000);
  };

  // 欢迎结束：常驻模式收成胶囊，否则退场
  Island.prototype.settle = function () {
    clearTimeout(this.timer);
    this.restore = null;
    if (this.resident) {
      if (this.hover) this.morph('open', this.detail());
      else this.morph('pill', this.page());
      this.progress();
    } else {
      this.hide();
    }
  };

  Island.prototype.hide = function () {
    var el = this.el;
    var self = this;
    this.morph('pill', this.page());
    setTimeout(function () { el.classList.remove('is-in'); }, 380);
    setTimeout(function () { self.size = ''; }, 800);
  };

  Island.prototype.notify = function (text, tone, icon) {
    var self = this;
    clearTimeout(this.timer);
    this.restore = true;
    this.morph('open', { title: text, icon: icon || ICONS[tone === 'err' ? 'err' : 'ok'], tone: tone || 'ok' });
    this.el.classList.add('is-in');
    requestAnimationFrame(function () { self.el.classList.remove('is-init'); });
    this.timer = setTimeout(function () { self.settle(); }, tone === 'err' ? 3200 : 2200);
  };

  Island.prototype.click = function () {
    if (this.restore) { this.settle(); return; }
    if (!this.resident) { this.hide(); return; }
    if (window.scrollY > 300) {
      window.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
      return;
    }
    // 触屏没有悬停：点一下展开详情，再点收起
    if (this.size === 'pill') this.morph('open', this.detail());
    else this.morph('pill', this.page());
  };

  if (cfg.island) {
    var c = cfg.island;
    var island = null;
    var once = c.repeat !== 'page';
    var seen = once && store('xi-island');
    // 只弹一次的欢迎在本次会话已经出现过：常驻模式直接显示胶囊，欢迎模式不再弹出
    if (seen) c.title = '';
    var start = function () {
      if (island) return;
      island = new Island(c);
      if (once) store('xi-island', '1');
      if (c.title || c.mode === 'resident') island.show();
    };

    // 等页面加载完再弹，但慢图片不应把它拖太久：load 与 DOM 就绪后 1.5 秒取先到者
    // 头像 / LOGO 先加载好再出场：否则图片还没到，图标位置会先露出一块纯色方块
    // 首页：从 /wp-json/ 读取站点名称作为欢迎语里的 {title}，最多等 1.5 秒，失败沿用页面输出的名称
    var siteName = function (done) {
      if (!c.siteApi || !window.fetch) { done(); return; }
      var finished = false;
      var finish = function () { if (!finished) { finished = true; done(); } };
      setTimeout(finish, 1500);
      fetch(c.siteApi, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (data && data.name && !finished) {
            // 站点名称里的 &amp; 等实体还原成文字
            var t = document.createElement('textarea');
            t.innerHTML = String(data.name);
            if (t.value.trim()) { c.pageName = t.value.trim(); c.site = c.pageName; }
          }
        })
        .catch(function () {})
        .then(finish);
    };

    var preload = function (done) {
      var src = c.avatar || c.logo;
      if (!src) { done(); return; }
      var finished = false;
      var finish = function (ok) {
        if (finished) return;
        finished = true;
        if (!ok) {
          // 头像加载失败退回 LOGO，再失败用导航栏图标
          if (c.avatar) { c.avatar = ''; preload(done); return; }
          c.logo = '';
        }
        done();
      };
      var img = new Image();
      img.onload = function () { finish(true); };
      img.onerror = function () { finish(false); };
      img.src = src;
      if (img.complete && img.naturalWidth) finish(true);
      setTimeout(function () { finish(true); }, 2500);
    };

    var shown = false;
    var go = function () {
      if (shown) return;
      shown = true;
      setTimeout(function () { siteName(function () { preload(start); }); }, 500);
    };
    if (document.readyState === 'complete') go();
    else {
      window.addEventListener('load', go);
      setTimeout(go, 1500);
    }

    if (c.notify) {
      var ensure = function () { if (!island) start(); return island; };
      // 站内轻提示改由灵动岛显示（main.js 的 toast 会先询问这个钩子）
      window.xiToastHook = function (text, ok) {
        if (!text) return false;
        ensure().notify(text, ok ? 'ok' : 'err');
        return true;
      };
      // 深浅色切换
      new MutationObserver(function () {
        var dark = root.getAttribute('data-theme') === 'dark';
        ensure().notify(dark ? '已切换到深色模式' : '已切换到浅色模式', 'ok', dark ? ICONS.moon : ICONS.sun);
      }).observe(root, { attributes: true, attributeFilter: ['data-theme'] });
      // 复制正文
      document.addEventListener('copy', function (e) {
        var t = e.target;
        if (t && t.closest && t.closest('input, textarea, [contenteditable="true"]')) return;
        var sel = window.getSelection ? String(window.getSelection()) : '';
        if (sel.trim().length < 2) return;
        ensure().notify('复制成功，转载请注明出处', 'ok', ICONS.copy);
      });
    }
  }

  // ---------- 樱花 / 下雪 ----------
  var startFall = function (c) {
    var mobile = window.matchMedia('(max-width: 768px)').matches;
    if (reduced || (mobile && !c.mobile)) return;
    var scale = mobile ? 0.5 : 1;

    var canvas = document.createElement('canvas');
    canvas.className = 'xi-fall';
    canvas.setAttribute('aria-hidden', 'true');
    document.body.appendChild(canvas);
    var ctx = canvas.getContext('2d');
    var W = 0;
    var H = 0;
    var dpr = 1;
    var resize = function () {
      dpr = Math.min(window.devicePixelRatio || 1, 2);
      W = window.innerWidth;
      H = window.innerHeight;
      canvas.width = W * dpr;
      canvas.height = H * dpr;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };
    resize();
    window.addEventListener('resize', resize);

    var rand = function (a, b) { return a + Math.random() * (b - a); };
    var items = [];
    var make = function (type, initial) {
      var p = { type: type, x: rand(0, W), y: initial ? rand(-H, H) : rand(-60, -10), phase: rand(0, Math.PI * 2) };
      if (type === 'sakura') {
        p.size = rand(8, 15);
        p.vy = rand(0.5, 1.2);
        p.vx = rand(-0.3, 0.6);
        p.rot = rand(0, Math.PI * 2);
        p.vr = rand(-0.02, 0.02);
        p.flip = rand(0, Math.PI * 2);
        p.alpha = rand(0.7, 0.95);
      } else {
        p.size = rand(1.2, 3.6);
        p.vy = p.size * rand(0.25, 0.4);
        p.vx = rand(-0.25, 0.25);
        p.alpha = rand(0.5, 0.95);
      }
      return p;
    };
    var add = function (type, n) {
      for (var i = 0; i < Math.round(n * scale); i++) items.push(make(type, true));
    };
    add('sakura', c.sakura);
    add('snow', c.snow);

    var petal = function (p) {
      var s = p.size;
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rot);
      ctx.scale(1, Math.abs(Math.cos(p.flip)) * 0.7 + 0.3); // 翻转时变窄，模拟三维翻动
      ctx.globalAlpha = p.alpha;
      var g = ctx.createLinearGradient(0, -s, 0, s);
      g.addColorStop(0, '#ffd6e4');
      g.addColorStop(1, '#f59bb9');
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.moveTo(0, s);
      ctx.bezierCurveTo(-s * 0.9, s * 0.35, -s * 0.75, -s * 0.75, -s * 0.18, -s);
      ctx.lineTo(0, -s * 0.72); // 花瓣顶端的缺口
      ctx.lineTo(s * 0.18, -s);
      ctx.bezierCurveTo(s * 0.75, -s * 0.75, s * 0.9, s * 0.35, 0, s);
      ctx.fill();
      ctx.restore();
    };

    var dark = false;
    var readTheme = function () { dark = root.getAttribute('data-theme') === 'dark'; };
    readTheme();
    new MutationObserver(readTheme).observe(root, { attributes: true, attributeFilter: ['data-theme'] });

    var wind = 0;
    var t = 0;
    var frame = function () {
      if (document.hidden) {
        requestAnimationFrame(frame);
        return;
      }
      t += 0.01;
      wind = Math.sin(t * 0.7) * 0.35 + Math.sin(t * 0.23) * 0.25;
      ctx.clearRect(0, 0, W, H);
      // 浅色背景上纯白雪花看不见，换成淡蓝并加描边
      var snowFill = dark ? '255,255,255' : '186,214,232';
      for (var i = 0; i < items.length; i++) {
        var p = items[i];
        p.phase += 0.02;
        if (p.type === 'sakura') {
          p.x += p.vx + wind + Math.sin(p.phase) * 0.6;
          p.y += p.vy + Math.cos(p.phase * 0.7) * 0.2;
          p.rot += p.vr;
          p.flip += 0.03;
          petal(p);
        } else {
          p.x += p.vx + wind * 0.6 + Math.sin(p.phase) * 0.3;
          p.y += p.vy;
          ctx.globalAlpha = p.alpha;
          ctx.fillStyle = 'rgb(' + snowFill + ')';
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
          ctx.fill();
          if (!dark) {
            ctx.strokeStyle = 'rgba(120,160,190,.35)';
            ctx.lineWidth = 0.6;
            ctx.stroke();
          }
        }
        if (p.y > H + 20 || p.x < -40 || p.x > W + 40) items[i] = make(p.type, false);
      }
      ctx.globalAlpha = 1;
      requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
  };

  if (cfg.fall) startFall(cfg.fall);
})();
