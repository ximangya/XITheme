/* 曦主题：正文代码块增强 —— 顶栏（语言 / 文件名 / 复制）、行号、语法高亮（highlight.js 按需加载） */
(function () {
  'use strict';
  var cfg = window.xiCode || {};
  var langs = cfg.langs || {};
  var MAX = 30000; // 超长代码不做高亮，避免卡顿

  var blocks = Array.prototype.filter.call(document.querySelectorAll('.xi-content pre'), function (pre) {
    // 编辑器里的代码块不处理
    return !pre.closest('[contenteditable="true"]') && !pre.closest('.xi-codebox');
  });
  if (!blocks.length) return;

  var copyText = function (text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
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

  var langOf = function (pre, code) {
    var lang = pre.getAttribute('data-lang') || '';
    if (!lang) {
      var m = /(?:^|\s)lang(?:uage)?-([\w+#-]+)/.exec(code.className + ' ' + pre.className);
      lang = m ? m[1] : '';
    }
    return lang.toLowerCase();
  };
  var langName = function (lang) {
    if (!lang) return '';
    return langs[lang] || (lang === 'xml' ? 'XML' : lang.charAt(0).toUpperCase() + lang.slice(1));
  };

  var items = blocks.map(function (pre) {
    var code = pre.querySelector('code');
    if (!code) {
      // 没有 <code> 的旧代码块补一层，方便高亮
      code = document.createElement('code');
      while (pre.firstChild) code.appendChild(pre.firstChild);
      pre.appendChild(code);
    }
    var lang = langOf(pre, code);
    var title = pre.getAttribute('data-title') || '';
    var text = code.textContent.replace(/\n$/, '');

    var box = document.createElement('div');
    box.className = 'xi-codebox';
    pre.parentNode.insertBefore(box, pre);

    var bar = document.createElement('div');
    bar.className = 'xi-code-bar';
    bar.innerHTML = '<span class="xi-code-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="xi-code-label"></span>';
    var label = bar.querySelector('.xi-code-label');
    var setLabel = function (l) {
      label.textContent = title ? title : langName(l) || 'Code';
      if (title && l) label.setAttribute('data-lang', langName(l));
    };
    setLabel(lang);

    if (cfg.copy !== false) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'xi-code-copy';
      btn.innerHTML = '<svg class="xi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/></svg><span>复制</span>';
      btn.addEventListener('click', function () {
        copyText(code.textContent.replace(/\n$/, '')).then(function () {
          btn.classList.add('is-done');
          btn.lastChild.textContent = '已复制';
          setTimeout(function () { btn.classList.remove('is-done'); btn.lastChild.textContent = '复制'; }, 1600);
        }, function () {
          if (window.xiToast) window.xiToast('复制失败，请手动选择', false);
        });
      });
      bar.appendChild(btn);
    }

    box.appendChild(bar);
    box.appendChild(pre);

    // 行号：单独一列，横向滚动时固定在左侧
    var lines = text.split('\n').length;
    if (lines > 1) {
      var gutter = document.createElement('span');
      gutter.className = 'xi-code-ln';
      gutter.setAttribute('aria-hidden', 'true');
      var nums = [];
      for (var i = 1; i <= lines; i++) nums.push(i);
      gutter.textContent = nums.join('\n');
      pre.insertBefore(gutter, code);
      pre.classList.add('has-ln');
    }
    pre.classList.add('xi-code-pre');
    return { pre: pre, code: code, lang: lang, text: text, setLabel: setLabel };
  });

  /* ---------- 语法高亮 ---------- */
  var need = items.filter(function (it) { return it.lang !== 'plaintext' && it.lang !== 'text' && it.text.length <= MAX; });
  if (!need.length || !cfg.hljs) return;

  var run = function () {
    var hljs = window.hljs;
    if (!hljs) return;
    var auto = Object.keys(langs);
    need.forEach(function (it) {
      try {
        var res;
        if (it.lang && hljs.getLanguage(it.lang)) {
          res = hljs.highlight(it.text, { language: it.lang, ignoreIllegals: true });
        } else {
          res = hljs.highlightAuto(it.text, auto);
          if (res.language && !it.lang) it.setLabel(res.language);
        }
        it.code.innerHTML = res.value;
        it.code.classList.add('hljs');
      } catch (e) {}
    });
  };

  if (window.hljs) { run(); return; }
  var s = document.createElement('script');
  s.src = cfg.hljs;
  s.async = true;
  s.onload = run;
  document.head.appendChild(s);
})();
