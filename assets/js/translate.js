/* 曦主题：整页机器翻译（微软 Edge 免费翻译接口，配置见 window.xiTranslate） */
(function () {
  'use strict';
  var cfg = window.xiTranslate;
  if (!cfg || !cfg.langs || cfg.langs.length < 2) return;

  var html = document.documentElement;
  var KEY = 'xi-lang';
  var API = 'https://edge.microsoft.com/translate/translatetext';
  var ATTRS = ['placeholder', 'title', 'aria-label', 'alt'];
  var BATCH_ITEMS = 50;
  var BATCH_CHARS = 4500;
  var CACHE_MAX = 3000;

  var storage = function (type) { try { return window[type]; } catch (e) { return null; } };
  var local = storage('localStorage');
  var get = function (s, k) { try { return s ? s.getItem(k) : null; } catch (e) { return null; } };
  var set = function (s, k, v) { try { if (s) s.setItem(k, v); } catch (e) {} };
  var toast = function (text, ok) { if (window.xiToast) window.xiToast(text, ok); };
  var done = function () { html.classList.remove('xi-translating'); };

  // 至少含一个文字字符才翻译（纯数字、符号、表情跳过）
  var hasWord;
  try { hasWord = new RegExp('\\p{L}', 'u'); } catch (e) { hasWord = /[A-Za-zÀ-ɏͰ-ϿЀ-ӿ֐-ۿ฀-๿぀-ヿ㐀-鿿가-힯]/; }

  /* ---------- 译文缓存：每种语言一份，存在 localStorage ---------- */
  var caches = {};
  var cacheOf = function (lang) {
    if (!caches[lang]) {
      var data = null;
      try { data = JSON.parse(get(local, 'xi-tr-' + lang) || 'null'); } catch (e) {}
      caches[lang] = data && typeof data === 'object' ? data : {};
    }
    return caches[lang];
  };
  var saveCache = function (lang) {
    var data = caches[lang];
    var keys = Object.keys(data);
    // 超出上限时丢掉最早的一半
    if (keys.length > CACHE_MAX) {
      keys.slice(0, keys.length - CACHE_MAX / 2).forEach(function (k) { delete data[k]; });
    }
    set(local, 'xi-tr-' + lang, JSON.stringify(data));
  };

  /* ---------- 微软翻译 ---------- */
  // Edge 浏览器网页翻译使用的接口：免密钥、无需令牌，允许跨域；请求体为字符串数组，响应与 Azure v3 相同
  // 单次请求不超过 15000 字符，这里每批 4500 字符以内，同时最多 3 个请求
  var request = function (texts, to, retry) {
    var from = cfg.source ? '&from=' + encodeURIComponent(cfg.source) : '';
    return fetch(API + '?isEnterpriseClient=false&to=' + encodeURIComponent(to) + from, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json; charset=UTF-8' },
      body: JSON.stringify(texts)
    }).then(function (r) {
      // 限流或偶发错误稍等重试一次
      if ((r.status === 429 || r.status >= 500) && !retry) {
        return new Promise(function (ok) { setTimeout(ok, 1200); }).then(function () { return request(texts, to, true); });
      }
      if (!r.ok) throw new Error('translate ' + r.status);
      return r.json();
    }).then(function (res) {
      if (!Array.isArray(res)) throw new Error('translate');
      return res.map(function (row) { return row && row.translations && row.translations[0] ? row.translations[0].text : ''; });
    });
  };

  var pool = function (tasks, size) {
    var i = 0;
    var next = function () {
      if (i >= tasks.length) return Promise.resolve();
      var task = tasks[i++];
      return task().then(next);
    };
    var workers = [];
    for (var k = 0; k < Math.min(size, tasks.length); k++) workers.push(next());
    return Promise.all(workers);
  };

  // 分批翻译未缓存的文本，结果写入缓存
  var fetchMissing = function (texts, lang) {
    var cache = cacheOf(lang);
    var seen = {};
    var todo = texts.filter(function (s) {
      if (seen[s] || cache[s] !== undefined) return false;
      seen[s] = true;
      return true;
    });
    if (!todo.length) return Promise.resolve();
    var batches = [];
    var cur = [];
    var chars = 0;
    todo.forEach(function (s) {
      if (cur.length && (cur.length >= BATCH_ITEMS || chars + s.length > BATCH_CHARS)) {
        batches.push(cur);
        cur = [];
        chars = 0;
      }
      cur.push(s);
      chars += s.length;
    });
    if (cur.length) batches.push(cur);
    return pool(batches.map(function (batch) {
      return function () {
        return request(batch, lang).then(function (out) {
          batch.forEach(function (s, i) { if (out[i]) cache[s] = out[i]; });
        });
      };
    }), 3).then(function () { saveCache(lang); });
  };

  /* ---------- 收集页面文字 ---------- */
  // 每个文本节点 / 属性记住原文与当前写入的译文，切换语言或恢复原文时用
  var textRec = new WeakMap();
  var attrRec = new WeakMap();
  var tracked = new Set();

  var ignored = function (el) {
    try { return !!(el && el.closest && el.closest(cfg.ignore)); } catch (e) { return false; }
  };

  var cjk = function (lang) { return /^(zh|ja|ko|yue|lzh)/.test(lang); };

  var split = function (s) {
    var m = /^(\s*)([\s\S]*?)(\s*)$/.exec(s);
    return { lead: m[1], core: m[2], trail: m[3] };
  };

  // 返回 [{ apply(lang), core }]
  var collect = function (root) {
    var items = [];
    if (!root || (root.nodeType === 1 && ignored(root))) return items;

    var addText = function (node) {
      var rec = textRec.get(node);
      // 被页面脚本改过内容：当作新的原文
      if (!rec || (node.nodeValue !== rec.orig && node.nodeValue !== rec.tr)) {
        rec = { orig: node.nodeValue, tr: null };
        textRec.set(node, rec);
      }
      var parts = split(rec.orig);
      if (!parts.core || !hasWord.test(parts.core)) return;
      tracked.add(node);
      items.push({ core: parts.core, apply: function (value) {
        // 中文相邻两段之间没有空格，译成英文等语言后会粘在一起，补一个空格
        var gap = value !== null && !parts.trail && !cjk(current) && node.nextSibling ? ' ' : '';
        var next = value === null ? rec.orig : parts.lead + value + gap + parts.trail;
        rec.tr = value === null ? null : next;
        if (node.nodeValue !== next) node.nodeValue = next;
      } });
    };

    var addAttrs = function (el) {
      ATTRS.forEach(function (name) {
        if (!el.hasAttribute(name)) return;
        var map = attrRec.get(el);
        if (!map) { map = {}; attrRec.set(el, map); }
        var cur = el.getAttribute(name);
        var rec = map[name];
        if (!rec || (cur !== rec.orig && cur !== rec.tr)) rec = map[name] = { orig: cur, tr: null };
        var core = rec.orig.trim();
        if (!core || !hasWord.test(core)) return;
        tracked.add(el);
        items.push({ core: core, apply: function (value) {
          var next = value === null ? rec.orig : value;
          rec.tr = value === null ? null : next;
          if (el.getAttribute(name) !== next) el.setAttribute(name, next);
        } });
      });
      // 按钮式 input 的文字在 value 里
      if (el.tagName === 'INPUT' && /^(submit|button|reset)$/i.test(el.type) && el.value) {
        var map2 = attrRec.get(el) || {};
        attrRec.set(el, map2);
        var rec2 = map2.value;
        if (!rec2 || (el.value !== rec2.orig && el.value !== rec2.tr)) rec2 = map2.value = { orig: el.value, tr: null };
        if (hasWord.test(rec2.orig)) {
          tracked.add(el);
          items.push({ core: rec2.orig.trim(), apply: function (value) {
            rec2.tr = value === null ? null : value;
            el.value = value === null ? rec2.orig : value;
          } });
        }
      }
    };

    if (root.nodeType === 3) {
      if (!ignored(root.parentElement)) addText(root);
      return items;
    }
    if (root.nodeType === 1) addAttrs(root);
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
      acceptNode: function (n) {
        if (n.nodeType === 1) return ignored(n) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
        return NodeFilter.FILTER_ACCEPT;
      }
    });
    var n;
    while ((n = walker.nextNode())) {
      if (n.nodeType === 3) addText(n);
      else addAttrs(n);
    }
    return items;
  };

  /* ---------- 切换语言 ---------- */
  var current = cfg.source;
  var titleRec = { orig: document.title, tr: null };
  var observer = null;
  var job = 0;

  var applyItems = function (items, lang) {
    var cache = cacheOf(lang);
    items.forEach(function (it) {
      if (lang === cfg.source) it.apply(null);
      else if (cache[it.core] !== undefined) it.apply(cache[it.core]);
    });
    // 丢掉自己改动产生的变更记录，避免循环翻译
    if (observer) observer.takeRecords();
  };

  var translateTitle = function (lang) {
    if (document.title !== titleRec.orig && document.title !== titleRec.tr) titleRec = { orig: document.title, tr: null };
    if (lang === cfg.source) {
      document.title = titleRec.orig;
      return Promise.resolve();
    }
    return fetchMissing([titleRec.orig], lang).then(function () {
      var t = cacheOf(lang)[titleRec.orig];
      if (t && current === lang) { titleRec.tr = t; document.title = t; }
    });
  };

  var run = function (roots, lang) {
    var items = [];
    roots.forEach(function (r) { items = items.concat(collect(r)); });
    if (!items.length) return Promise.resolve();
    // 有缓存的先换上，再补翻剩下的
    applyItems(items, lang);
    if (lang === cfg.source) return Promise.resolve();
    var id = job;
    return fetchMissing(items.map(function (it) { return it.core; }), lang).then(function () {
      if (id === job && current === lang) applyItems(items, lang);
    });
  };

  var ui = document.querySelector('[data-xi-lang]');
  var setUI = function (lang, busy) {
    if (!ui) return;
    ui.classList.toggle('is-busy', !!busy);
    ui.classList.toggle('is-translated', lang !== cfg.source);
    var code = ui.querySelector('.xi-lang-code');
    if (code) code.textContent = lang === cfg.source ? '' : lang.split('-')[0].toUpperCase();
    ui.querySelectorAll('[data-lang]').forEach(function (b) {
      b.setAttribute('aria-checked', b.getAttribute('data-lang') === lang ? 'true' : 'false');
    });
  };

  var watch = function () {
    if (observer || !window.MutationObserver) return;
    var queue = [];
    var timer = null;
    observer = new MutationObserver(function (records) {
      if (current === cfg.source) return;
      records.forEach(function (r) {
        if (r.type === 'childList') r.addedNodes.forEach(function (n) { queue.push(n); });
        else queue.push(r.target);
      });
      clearTimeout(timer);
      timer = setTimeout(function () {
        var roots = queue.filter(function (n) { return n.isConnected; });
        queue = [];
        run(roots, current);
      }, 120);
    });
    observer.observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATTRS });
  };

  var switchTo = function (lang, quiet) {
    if (cfg.langs.indexOf(lang) === -1) lang = cfg.source;
    var prev = current;
    current = lang;
    job++;
    set(local, KEY, lang);
    html.setAttribute('lang', lang);
    setUI(lang, lang !== cfg.source);
    if (lang !== cfg.source) watch();
    return Promise.all([run([document.body], lang), translateTitle(lang)]).then(function () {
      done();
      setUI(lang, false);
      if (!quiet && lang !== prev) {
        toast(lang === cfg.source ? cfg.msg.back : cfg.msg.done.replace('%s', cfg.names[lang] || lang), true);
      }
    }, function () {
      // 失败退回原文
      done();
      current = cfg.source;
      set(local, KEY, cfg.source);
      html.setAttribute('lang', cfg.source);
      run([document.body], cfg.source);
      translateTitle(cfg.source);
      setUI(cfg.source, false);
      toast(cfg.msg.fail, false);
    });
  };

  /* ---------- 语言菜单 ---------- */
  if (ui) {
    var btn = ui.querySelector('.xi-lang-toggle');
    var open = function (on) {
      ui.classList.toggle('is-open', on);
      btn.setAttribute('aria-expanded', on ? 'true' : 'false');
    };
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      open(!ui.classList.contains('is-open'));
    });
    ui.addEventListener('click', function (e) {
      var item = e.target.closest('[data-lang]');
      if (!item) return;
      open(false);
      switchTo(item.getAttribute('data-lang'));
    });
    document.addEventListener('click', function (e) { if (!ui.contains(e.target)) open(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') open(false); });
  }

  /* ---------- 初始语言：上次选择 > 浏览器语言（可在后台关闭） ---------- */
  var browserLang = function () {
    var list = navigator.languages && navigator.languages.length ? navigator.languages : [navigator.language || ''];
    for (var i = 0; i < list.length; i++) {
      var l = String(list[i]).toLowerCase();
      var hit = null;
      if (/^zh-(tw|hk|mo|hant)/.test(l)) hit = 'zh-Hant';
      else if (/^zh/.test(l)) hit = 'zh-Hans';
      else {
        cfg.langs.forEach(function (code) {
          if (!hit && (code.toLowerCase() === l || code.toLowerCase().split('-')[0] === l.split('-')[0])) hit = code;
        });
      }
      if (hit && cfg.langs.indexOf(hit) !== -1) return hit;
      if (hit) return null;
    }
    return null;
  };

  var saved = get(local, KEY);
  var initial = saved && cfg.langs.indexOf(saved) !== -1 ? saved : (!saved && cfg.auto ? browserLang() : null);
  if (initial && initial !== cfg.source) switchTo(initial, true);
  else done();
})();
