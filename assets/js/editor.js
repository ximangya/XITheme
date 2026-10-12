/**
 * 曦主题前台编辑器
 * contenteditable + 自定义工具栏；图片按钮 / 拖拽 / 粘贴上传；Markdown 快捷输入；本地自动保存
 * 「插入」面板（空行输入 / 也能打开）：引用、引言、代码块、提醒框、折叠框、按钮、文件下载、表格、分割线、隐藏内容，
 * 以及 PHP 定义的扩展区块（商品卡片、价格方案、步骤、视频等，window.xiBlockDefs），以短代码卡片保存
 */
(function () {
  var form = document.querySelector('.xi-writer');
  if (!form || !window.xiWrite) return;

  var cfg = window.xiWrite;
  // 发帖页复用本编辑器：接口、本地存储前缀、标签上限可由配置覆盖
  var saveAction = cfg.saveAction || 'xi_write_save';
  var uploadAction = cfg.uploadAction || 'xi_write_upload';
  var storePrefix = cfg.store || 'xi-write-';
  var maxTags = parseInt(cfg.maxTags, 10) || 5;
  var toast = window.xiToast || function (m) { alert(m); };
  var titleEl = form.querySelector('.xi-writer-title');
  var editor = form.querySelector('.xi-writer-content');
  var toolbar = form.querySelector('.xi-writer-toolbar');
  var blockSelect = toolbar.querySelector('[data-cmd="block"]');
  var flyout = document.querySelector('.xi-flyout');
  var countEl = form.querySelector('.xi-writer-count');
  var savedEl = form.querySelector('.xi-writer-saved');
  var fileInput = form.querySelector('.xi-writer-file');
  var coverInput = form.querySelector('.xi-writer-cover-file');
  var cover = form.querySelector('.xi-writer-cover');
  var tagsBox = form.querySelector('.xi-writer-tags');
  var postId = parseInt(form.getAttribute('data-post-id'), 10) || 0;
  var dirty = false;
  var saving = false;
  var savedRange = null;

  document.execCommand('defaultParagraphSeparator', false, 'p');

  var esc = function (s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };

  /* ---------- 选区 ---------- */

  var inEditor = function (node) { return node && (node === editor || editor.contains(node)); };

  document.addEventListener('selectionchange', function () {
    var sel = window.getSelection();
    if (sel.rangeCount && inEditor(sel.anchorNode)) {
      savedRange = sel.getRangeAt(0).cloneRange();
      updateToolbar();
    }
  });

  var restoreRange = function () {
    editor.focus();
    if (savedRange) {
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(savedRange);
    }
  };

  var closestInEditor = function (selector) {
    var sel = window.getSelection();
    if (!sel.rangeCount) return null;
    var node = sel.anchorNode;
    node = node && node.nodeType === 3 ? node.parentNode : node;
    var found = node && node.closest ? node.closest(selector) : null;
    return found && inEditor(found) ? found : null;
  };

  var exec = function (cmd, val) {
    restoreRange();
    document.execCommand(cmd, false, val === undefined ? null : val);
    markDirty();
  };

  /* ---------- 工具栏 ---------- */

  var updateToolbar = function () {
    ['bold', 'italic', 'underline', 'strikeThrough', 'insertUnorderedList', 'insertOrderedList'].forEach(function (cmd) {
      var btn = toolbar.querySelector('[data-cmd="' + cmd + '"]');
      if (btn) btn.classList.toggle('is-active', document.queryCommandState(cmd));
    });
    // 光标在代码块里时「插入」变成「代码设置」，可修改语言与文件名
    var insertBtn = toolbar.querySelector('[data-cmd="insert"]');
    var inPre = !!closestInEditor('pre');
    insertBtn.classList.toggle('is-active', inPre);
    insertBtn.lastChild.textContent = inPre ? '代码设置' : '插入';
    toolbar.querySelector('[data-cmd="inlineCode"]').classList.toggle('is-active', !!closestInEditor('code:not(pre code)'));
    var heading = closestInEditor('h2, h3');
    blockSelect.value = heading ? heading.tagName.toLowerCase() : 'p';
  };

  var toggleBlock = function (tag) {
    var inside = closestInEditor(tag);
    exec('formatBlock', inside ? '<p>' : '<' + tag + '>');
  };

  var commands = {
    undo: function () { exec('undo'); },
    redo: function () { exec('redo'); },
    quote: function () { toggleBlock('blockquote'); },
    codeBlock: function () { toggleBlock('pre'); },
    inlineCode: function () {
      var code = closestInEditor('code');
      if (code && !code.closest('pre')) {
        code.replaceWith(document.createTextNode(code.textContent));
        markDirty();
        return;
      }
      restoreRange();
      var text = window.getSelection().toString() || '代码';
      exec('insertHTML', '<code>' + esc(text) + '</code>&#8203;');
    },
    removeFormat: function () {
      exec('removeFormat');
      exec('unlink');
      if (closestInEditor('h2, h3, blockquote, pre')) exec('formatBlock', '<p>');
    },
    link: function () { openLinkFlyout(); },
    image: function () {
      if (!cfg.upload) { toast('你所在的用户组暂无上传图片权限', false); return; }
      fileInput.click();
    },
    hide: function () { openHideFlyout(); },
    insert: function (btn) {
      var pre = closestInEditor('pre');
      if (pre) { openCodeFlyout(pre); return; }
      if (panelOpen()) { closeFlyout(); return; }
      slashBlock = null;
      openPanel(btn, false);
    },
    focus: function () {
      document.body.classList.toggle('xi-writer-focus');
      toolbar.querySelector('[data-cmd="focus"]').classList.toggle('is-active', document.body.classList.contains('xi-writer-focus'));
    }
  };

  toolbar.addEventListener('mousedown', function (e) {
    // 保持编辑区选区不丢失
    if (e.target.closest('.xi-tb-btn')) e.preventDefault();
  });

  toolbar.addEventListener('click', function (e) {
    var btn = e.target.closest('.xi-tb-btn');
    if (!btn) return;
    var cmd = btn.getAttribute('data-cmd');
    if (commands[cmd]) { commands[cmd](btn); } else { exec(cmd); }
    updateToolbar();
  });

  blockSelect.addEventListener('change', function () {
    exec('formatBlock', '<' + blockSelect.value + '>');
  });

  /* ---------- 浮出面板：链接、隐藏内容 ---------- */

  var closeFlyout = function () {
    flyout.hidden = true;
    flyout.innerHTML = '';
    flyout.classList.remove('is-panel');
  };

  var openFlyout = function (anchor, html, onSubmit) {
    flyout.classList.remove('is-panel');
    flyout.innerHTML = '<form class="xi-flyout-form" novalidate>' + html + '<div class="xi-flyout-actions"><button type="button" class="xi-btn xi-btn-sm" data-flyout-cancel>取消</button><button type="submit" class="xi-btn xi-btn-sm xi-btn-accent">确定</button></div></form>';
    flyout.hidden = false;
    var rect = anchor.getBoundingClientRect();
    var left = Math.min(rect.left, window.innerWidth - flyout.offsetWidth - 12);
    flyout.style.left = Math.max(12, left) + 'px';
    var top = rect.bottom + 8;
    if (top + flyout.offsetHeight > window.innerHeight - 12) top = Math.max(12, rect.top - flyout.offsetHeight - 8);
    flyout.style.top = top + 'px';
    var f = flyout.querySelector('form');
    var first = f.querySelector('input, select');
    if (first) first.focus();
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      onSubmit(f);
      closeFlyout();
    });
    f.querySelector('[data-flyout-cancel]').addEventListener('click', function () { closeFlyout(); editor.focus(); });
  };

  document.addEventListener('mousedown', function (e) {
    if (!flyout.hidden && !flyout.contains(e.target) && !e.target.closest('.xi-tb-btn')) closeFlyout();
  });

  var openLinkFlyout = function () {
    var current = closestInEditor('a');
    var selected = savedRange ? savedRange.toString() : '';
    openFlyout(toolbar.querySelector('[data-cmd="link"]'),
      '<label>链接地址<input type="url" name="url" placeholder="https://" value="' + esc(current ? current.getAttribute('href') : '') + '"></label>' +
      (selected || current ? '' : '<label>显示文字<input type="text" name="text" placeholder="留空显示链接地址"></label>') +
      '<label class="xi-flyout-check"><input type="checkbox" name="blank"' + (current && current.target === '_blank' ? ' checked' : '') + '> 新窗口打开</label>',
      function (f) {
        var url = f.url.value.trim();
        if (current && !url) { current.replaceWith(document.createTextNode(current.textContent)); markDirty(); return; }
        if (!url) return;
        if (!/^(https?:|mailto:|\/|#)/i.test(url)) url = 'https://' + url;
        var blank = f.blank.checked ? ' target="_blank" rel="noopener"' : '';
        if (current) {
          current.href = url;
          if (f.blank.checked) { current.target = '_blank'; current.rel = 'noopener'; } else { current.removeAttribute('target'); current.removeAttribute('rel'); }
          markDirty();
        } else if (selected) {
          exec('insertHTML', '<a href="' + esc(url) + '"' + blank + '>' + esc(selected) + '</a>');
        } else {
          var text = f.text && f.text.value.trim() ? f.text.value.trim() : url;
          exec('insertHTML', '<a href="' + esc(url) + '"' + blank + '>' + esc(text) + '</a>&nbsp;');
        }
      });
  };

  var openHideFlyout = function () {
    var roles = window.xiHideRoles || {};
    var options = '<option value="login">登录可见</option><option value="reply">评论可见</option><option value="password">输入密码可见</option><option value="role">指定用户组可见</option><option value="time">定时公开</option>';
    if (cfg.points) options += '<option value="points">支付' + esc(cfg.pointName) + '可见</option>';
    if (cfg.vip) options += '<option value="vip">会员可见</option>';
    var roleBoxes = Object.keys(roles).map(function (k) {
      return '<label class="xi-flyout-check"><input type="checkbox" name="roles" value="' + esc(k) + '"> ' + esc(roles[k]) + '</label>';
    }).join('');
    var soon = new Date(Date.now() + 864e5);
    var pad = function (n) { return ('0' + n).slice(-2); };
    var soonText = soon.getFullYear() + '-' + pad(soon.getMonth() + 1) + '-' + pad(soon.getDate()) + 'T' + pad(soon.getHours()) + ':00';
    openFlyout(anchorRect(),
      '<label>可见条件<select name="type">' + options + '</select></label>' +
      (cfg.points ? '<label data-for="points">所需' + esc(cfg.pointName) + '<input type="number" name="points" min="1" value="10"></label>' : '') +
      (cfg.vip ? '<label data-for="vip">最低会员等级<select name="vip"><option value="1">1 级</option><option value="2">2 级</option></select></label>' : '') +
      '<label data-for="password">查看密码<input type="text" name="password" autocomplete="off" placeholder="访客输入这个密码后可见"></label>' +
      '<div data-for="role" class="xi-flyout-roles"><span>允许查看的用户组</span>' + roleBoxes + '</div>' +
      '<label data-for="time">公开时间<input type="datetime-local" name="time" value="' + soonText + '"></label>' +
      '<label>锁定时的提示<input type="text" name="tip" placeholder="可留空，例如：关注公众号回复「曦」获取密码"></label>' +
      '<p class="xi-flyout-tip">选中文字后插入，会把选中内容设为隐藏</p>',
      function (f) {
        var type = f.type.value;
        var attr = ' type="' + type + '"';
        var q = function (v) { return String(v).replace(/"/g, '”').replace(/\[/g, '【').replace(/\]/g, '】'); };
        if (type === 'points') attr += ' points="' + Math.max(1, parseInt(f.points.value, 10) || 1) + '"';
        if (type === 'vip') attr += ' vip="' + f.vip.value + '"';
        if (type === 'password') {
          if (!f.password.value.trim()) { toast('请设置查看密码', false); return; }
          attr += ' password="' + q(f.password.value.trim()) + '"';
        }
        if (type === 'role') {
          var picked = Array.prototype.filter.call(f.querySelectorAll('input[name="roles"]'), function (c) { return c.checked; }).map(function (c) { return c.value; });
          if (!picked.length) { toast('请至少选择一个用户组', false); return; }
          attr += ' roles="' + picked.join(',') + '"';
        }
        if (type === 'time') {
          if (!f.time.value) { toast('请选择公开时间', false); return; }
          attr += ' time="' + f.time.value.replace('T', ' ') + '"';
        }
        if (f.tip.value.trim()) attr += ' tip="' + q(f.tip.value.trim()) + '"';
        var selected = savedRange ? savedRange.toString() : '';
        exec('insertText', '[xi_hide' + attr + ']' + (selected || '在这里输入隐藏内容') + '[/xi_hide]');
      });
    var f = flyout.querySelector('form');
    var sync = function () {
      f.querySelectorAll('[data-for]').forEach(function (l) { l.hidden = l.getAttribute('data-for') !== f.type.value; });
    };
    f.type.addEventListener('change', sync);
    sync();
  };

  /* ---------- 区块：插入面板（工具栏「插入」或空行输入 /） ---------- */
  // 插入的 HTML 与后台区块渲染结果一致，共用 blocks.css；文件下载在编辑器里显示为卡片，保存为短代码

  var LANGS = window.xiCodeLangs || { '': '自动识别', plaintext: '纯文本' };
  var ICON = function (d) {
    return '<svg class="xi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + d + '</svg>';
  };
  var I = {
    quote: ICON('<path d="M7 7h4v4c0 3-1.5 5-4 6M14 7h4v4c0 3-1.5 5-4 6"/>'),
    cite: ICON('<path d="M5 6h14M5 12h9M5 18h6"/><path d="m16 15 3 3-3 3"/>'),
    code: ICON('<path d="m9 8-4 4 4 4M15 8l4 4-4 4"/>'),
    alert: ICON('<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>'),
    collapse: ICON('<rect x="4" y="5" width="16" height="14" rx="2"/><path d="m9 11 3 3 3-3"/>'),
    button: ICON('<rect x="3" y="7" width="18" height="10" rx="5"/><path d="M9 12h6"/>'),
    download: ICON('<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>'),
    table: ICON('<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M9 5v14M15 5v14"/>'),
    hr: ICON('<path d="M4 12h16"/>'),
    hide: ICON('<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>'),
    edit: ICON('<path d="M4 20h4L19 9l-4-4L4 16z"/>'),
    trash: ICON('<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>')
  };
  var ITEMS = [
    { key: 'quote', name: '引用', desc: '普通引用段落' },
    { key: 'cite', name: '引言', desc: '名言、书摘，可署名' },
    { key: 'code', name: '代码块', desc: '高亮、行号、复制' },
    { key: 'alert', name: '提醒框', desc: '提示、成功、警告' },
    { key: 'collapse', name: '折叠框', desc: '点击展开的内容' },
    { key: 'button', name: '按钮', desc: '醒目的链接按钮' },
    { key: 'download', name: '文件下载', desc: '下载卡片、提取码' },
    { key: 'table', name: '表格', desc: '插入行列表格' },
    { key: 'hr', name: '分割线', desc: '分隔上下内容' },
    { key: 'hide', name: '隐藏内容', desc: '登录、评论、付费可见' }
  ];

  /* 扩展区块：字段由 PHP 定义（inc/functions/xi-blocks-extra.php），这里自动生成插入表单 */
  var BX = window.xiBlockDefs || { blocks: [], choices: {} };
  var BX_DEFS = {};
  var BX_ICONS = {
    shop: ICON('<path d="M3 4h2.2l2.2 10.5a1.5 1.5 0 0 0 1.5 1.2h8.4a1.5 1.5 0 0 0 1.5-1.1L20.5 8H6.1"/><circle cx="9.5" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/>'),
    content: ICON('<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h8M8 17h5"/>')
  };
  (BX.blocks || []).forEach(function (d) {
    // 商城未开启时不显示商城区块
    if (d.group === 'shop' && !BX.shop) return;
    BX_DEFS[d.name] = d;
    I['bx:' + d.name] = BX_ICONS[d.group] || BX_ICONS.content;
    ITEMS.push({ key: 'bx:' + d.name, name: d.title, desc: d.desc.length > 14 ? d.desc.slice(0, 14) + '…' : d.desc });
  });

  var panelAnchor = null;
  var slashBlock = null;

  var anchorRect = function () {
    var a = panelAnchor || toolbar.querySelector('[data-cmd="insert"]');
    return a && a.getBoundingClientRect ? a : toolbar;
  };

  // 光标所在的编辑区顶层元素
  var topBlock = function () {
    var node = savedRange ? savedRange.startContainer : null;
    if (!node || !inEditor(node) || node === editor) return null;
    while (node.parentNode && node.parentNode !== editor) node = node.parentNode;
    return node.parentNode === editor ? node : null;
  };

  var placeCaret = function (el, select) {
    var r = document.createRange();
    r.selectNodeContents(el);
    if (!select) r.collapse(false);
    var s = window.getSelection();
    s.removeAllRanges();
    s.addRange(r);
    savedRange = r.cloneRange();
  };

  var newParagraph = function () {
    var p = document.createElement('p');
    p.innerHTML = '<br>';
    return p;
  };

  // 插入到光标所在段落之后（空段落直接替换），选中 focus 指定元素里的占位文字方便直接改写
  var insertBlock = function (html, focus) {
    editor.focus();
    var nodes;
    if (typeof html === 'string') {
      var tpl = document.createElement('div');
      tpl.innerHTML = html;
      nodes = Array.prototype.slice.call(tpl.childNodes);
    } else {
      nodes = [html];
    }
    if (!nodes.length) return null;
    var frag = document.createDocumentFragment();
    nodes.forEach(function (n) { frag.appendChild(n); });
    var ref = slashBlock && slashBlock.isConnected ? slashBlock : topBlock();
    slashBlock = null;
    var empty = ref && ref.tagName === 'P' && !ref.textContent.trim() && !ref.querySelector('img');
    if (ref && empty) ref.replaceWith(frag);
    else if (ref) ref.after(frag);
    else editor.appendChild(frag);
    var first = nodes[0];
    var last = nodes[nodes.length - 1];
    if (!last.nextElementSibling) last.after(newParagraph());
    var target = focus ? (first.matches && first.matches(focus) ? first : first.querySelector(focus)) : null;
    placeCaret(target || last.nextElementSibling, !!target);
    markDirty();
    updateToolbar();
    return first;
  };

  // 斜杠打开面板时，先清掉段落里的 /
  var clearSlash = function () {
    if (slashBlock && slashBlock.isConnected) {
      slashBlock.innerHTML = '<br>';
      placeCaret(slashBlock, false);
    }
  };

  var openPanel = function (anchor, fromSlash) {
    panelAnchor = anchor;
    flyout.innerHTML = '<div class="xi-insert-panel" role="menu">' + ITEMS.map(function (it, i) {
      return '<button type="button" class="xi-insert-item' + (i ? '' : ' is-active') + '" role="menuitem" data-insert="' + it.key + '">' + I[it.key] + '<b>' + it.name + '</b><small>' + it.desc + '</small></button>';
    }).join('') + '</div>' + (fromSlash ? '<p class="xi-flyout-tip">↑↓←→ 选择，回车插入，Esc 取消</p>' : '');
    flyout.hidden = false;
    flyout.classList.add('is-panel');
    var rect = anchor.getBoundingClientRect();
    var left = Math.min(rect.left, window.innerWidth - flyout.offsetWidth - 12);
    flyout.style.left = Math.max(12, left) + 'px';
    var top = rect.bottom + 8;
    if (top + flyout.offsetHeight > window.innerHeight - 12) top = Math.max(12, rect.top - flyout.offsetHeight - 8);
    flyout.style.top = top + 'px';
  };

  var panelOpen = function () { return !flyout.hidden && flyout.classList.contains('is-panel'); };

  flyout.addEventListener('mousedown', function (e) {
    if (e.target.closest('.xi-insert-item')) e.preventDefault();
  });
  flyout.addEventListener('click', function (e) {
    var item = e.target.closest('[data-insert]');
    if (item) runInsert(item.getAttribute('data-insert'));
  });

  var runInsert = function (key) {
    closeFlyout();
    restoreRange();
    clearSlash();
    if (key.indexOf('bx:') === 0) { openBxFlyout(key.slice(3), null); return; }
    if (insertCommands[key]) insertCommands[key]();
  };

  // 斜杠面板的键盘操作
  document.addEventListener('keydown', function (e) {
    if (!panelOpen()) return;
    var items = Array.prototype.slice.call(flyout.querySelectorAll('.xi-insert-item'));
    var cur = items.indexOf(flyout.querySelector('.xi-insert-item.is-active'));
    var cols = Math.max(1, Math.round(flyout.querySelector('.xi-insert-panel').offsetWidth / (items[0].offsetWidth || 1)));
    var move = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: cols, ArrowUp: -cols }[e.key];
    if (move) {
      e.preventDefault();
      var next = Math.min(items.length - 1, Math.max(0, cur + move));
      items.forEach(function (b, i) { b.classList.toggle('is-active', i === next); });
      items[next].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (items[cur]) runInsert(items[cur].getAttribute('data-insert'));
    }
  }, true);

  // 空段落只输入了 /（或中文输入法下的 、）时弹出面板；继续输入其他文字则收起
  editor.addEventListener('input', function () {
    var block = closestInEditor('p');
    var text = block ? block.textContent.trim() : '';
    if (block && (text === '/' || text === '、') && !block.closest('pre, .xi-block-alert, .xi-block-collapse, blockquote')) {
      slashBlock = block;
      var sel = window.getSelection();
      var rect = sel.rangeCount ? sel.getRangeAt(0).getBoundingClientRect() : null;
      var anchor = rect && rect.height ? { getBoundingClientRect: function () { return rect; } } : block;
      openPanel(anchor, true);
    } else if (panelOpen() && slashBlock) {
      slashBlock = null;
      closeFlyout();
    }
  });

  /* 各区块的设置面板 */
  var opt = function (map, current) {
    return Object.keys(map).map(function (k) {
      return '<option value="' + esc(k) + '"' + (k === current ? ' selected' : '') + '>' + esc(map[k]) + '</option>';
    }).join('');
  };
  var selectedText = function () { return savedRange ? savedRange.toString().trim() : ''; };

  var insertCommands = {
    quote: function () { toggleBlock('blockquote'); },
    cite: function () {
      var text = selectedText();
      openFlyout(anchorRect(),
        '<label>样式<select name="style">' + opt({ 'default': '大引号', card: '卡片', center: '居中' }, 'default') + '</select></label>' +
        '<label>出处 / 作者<input type="text" name="cite" placeholder="可留空"></label>',
        function (f) {
          var cite = f.cite.value.trim();
          insertBlock('<blockquote class="xi-quote is-' + esc(f.style.value) + '"><p>' + esc(text || '在这里输入引言') + '</p>' + (cite ? '<cite>' + esc(cite) + '</cite>' : '') + '</blockquote>', text ? null : 'p');
        });
    },
    code: function () { openCodeFlyout(null); },
    alert: function () {
      var text = selectedText();
      openFlyout(anchorRect(),
        '<label>类型<select name="type">' + opt({ info: '提示（蓝）', success: '成功（绿）', warning: '警告（橙）', danger: '危险（红）', note: '笔记（主题色）' }, 'info') + '</select></label>' +
        '<label>标题<input type="text" name="title" placeholder="可留空"></label>',
        function (f) {
          var title = f.title.value.trim();
          insertBlock('<div class="xi-block-alert is-' + esc(f.type.value) + '"><div class="xi-block-alert-body">' + (title ? '<p class="xi-block-alert-title">' + esc(title) + '</p>' : '') + '<p>' + esc(text || '提醒内容') + '</p></div></div>', text ? null : '.xi-block-alert-body > p:not(.xi-block-alert-title)');
        });
    },
    collapse: function () {
      openFlyout(anchorRect(),
        '<label>标题<input type="text" name="title" placeholder="例如：点击查看答案"></label>' +
        '<label class="xi-flyout-check"><input type="checkbox" name="open"> 默认展开</label>',
        function (f) {
          insertBlock('<details class="xi-block-collapse" open data-xi-open="' + (f.open.checked ? '1' : '0') + '"><summary>' + esc(f.title.value.trim() || '展开查看') + '</summary><div class="xi-block-collapse-body"><p>折叠的内容</p></div></details>', '.xi-block-collapse-body > p');
        });
    },
    button: function () {
      var text = selectedText();
      openFlyout(anchorRect(),
        '<label>按钮文字<input type="text" name="text" value="' + esc(text) + '" placeholder="例如：前往下载"></label>' +
        '<label>链接地址<input type="url" name="url" placeholder="https://"></label>' +
        '<label>样式<select name="style">' + opt({ main: '实色', soft: '浅色', line: '描边' }, 'main') + '</select></label>' +
        '<label class="xi-flyout-check"><input type="checkbox" name="center"> 居中</label>' +
        '<label class="xi-flyout-check"><input type="checkbox" name="blank" checked> 新窗口打开</label>',
        function (f) {
          var url = f.url.value.trim();
          if (!url) { toast('请填写链接地址', false); return; }
          if (!/^(https?:|mailto:|\/|#)/i.test(url)) url = 'https://' + url;
          insertBlock('<p class="xi-block-btns is-align-' + (f.center.checked ? 'center' : 'left') + '"><a class="xi-block-btn is-' + esc(f.style.value) + '" href="' + esc(url) + '"' + (f.blank.checked ? ' target="_blank" rel="noopener"' : '') + '>' + esc(f.text.value.trim() || '点击前往') + '</a></p>');
        });
    },
    download: function () { openDownloadFlyout(null); },
    table: function () {
      openFlyout(anchorRect(),
        '<label>行数<input type="number" name="rows" min="1" max="20" value="3"></label>' +
        '<label>列数<input type="number" name="cols" min="1" max="8" value="3"></label>' +
        '<label class="xi-flyout-check"><input type="checkbox" name="head" checked> 第一行为表头</label>',
        function (f) {
          var rows = Math.min(20, Math.max(1, parseInt(f.rows.value, 10) || 3));
          var cols = Math.min(8, Math.max(1, parseInt(f.cols.value, 10) || 3));
          var cell = function (tag) { var s = ''; for (var c = 0; c < cols; c++) s += '<' + tag + '><br></' + tag + '>'; return s; };
          var html = '<table>' + (f.head.checked ? '<thead><tr>' + cell('th') + '</tr></thead>' : '') + '<tbody>';
          for (var r = 0; r < rows; r++) html += '<tr>' + cell('td') + '</tr>';
          insertBlock(html + '</tbody></table>', f.head.checked ? 'th' : 'td');
        });
    },
    hr: function () { insertBlock('<hr>'); },
    hide: function () { openHideFlyout(); }
  };

  /* 代码块：插入新代码，或修改光标所在代码块的语言与文件名 */
  var openCodeFlyout = function (pre) {
    var lang = pre ? (pre.getAttribute('data-lang') || '') : '';
    var title = pre ? (pre.getAttribute('data-title') || '') : '';
    var text = pre ? '' : selectedText();
    openFlyout(pre ? pre : anchorRect(),
      '<label>语言<select name="lang">' + opt(LANGS, lang) + '</select></label>' +
      '<label>文件名 / 标题<input type="text" name="title" value="' + esc(title) + '" placeholder="可留空，例如 index.php"></label>' +
      (pre ? '' : '<label>代码<textarea name="code" rows="6" spellcheck="false" placeholder="粘贴代码，也可插入后再输入">' + esc(text) + '</textarea></label>'),
      function (f) {
        var l = f.lang.value;
        var t = f.title.value.trim();
        if (pre) {
          pre.classList.add('xi-code');
          if (l) pre.setAttribute('data-lang', l); else pre.removeAttribute('data-lang');
          if (t) pre.setAttribute('data-title', t); else pre.removeAttribute('data-title');
          var code = pre.querySelector('code');
          if (!code) {
            code = document.createElement('code');
            while (pre.firstChild) code.appendChild(pre.firstChild);
            pre.appendChild(code);
          }
          code.className = l ? 'language-' + l : '';
          markDirty();
          return;
        }
        var src = f.code.value.replace(/\r\n?/g, '\n').replace(/\n+$/, '');
        insertBlock('<pre class="xi-code"' + (l ? ' data-lang="' + esc(l) + '"' : '') + (t ? ' data-title="' + esc(t) + '"' : '') + '><code' + (l ? ' class="language-' + esc(l) + '"' : '') + '>' + esc(src || '// 在这里输入代码') + '</code></pre>', src ? null : 'code');
      });
    var ta = flyout.querySelector('textarea');
    if (ta) {
      // 代码框里 Tab 输入缩进
      ta.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') return;
        e.preventDefault();
        var s = ta.selectionStart;
        ta.value = ta.value.slice(0, s) + '    ' + ta.value.slice(ta.selectionEnd);
        ta.selectionStart = ta.selectionEnd = s + 4;
      });
    }
  };

  /* 文件下载：编辑器里是不可编辑的卡片，保存为 [xi_download] 短代码 */
  var DL_KEYS = ['name', 'url', 'code', 'label', 'size', 'version', 'desc', 'login'];
  var scAttr = function (v) { return String(v).replace(/"/g, '”').replace(/\[/g, '【').replace(/\]/g, '】'); };
  var buildShortcode = function (d) {
    return '[xi_download' + DL_KEYS.filter(function (k) { return d[k]; }).map(function (k) { return ' ' + k + '="' + scAttr(d[k]) + '"'; }).join('') + ']';
  };
  var parseShortcode = function (text) {
    var d = {};
    var re = /(\w+)="([^"]*)"/g;
    var m;
    while ((m = re.exec(text))) d[m[1]] = m[2];
    return d;
  };
  var downloadCard = function (d) {
    var host = '';
    try { host = d.url ? new URL(d.url, location.href).hostname : ''; } catch (e) {}
    var meta = [host || '未填写链接', d.code ? '提取码 ' + d.code : '', d.size, d.login ? '登录可下载' : ''].filter(Boolean).join(' · ');
    var card = document.createElement('div');
    card.className = 'xi-embed is-download';
    card.setAttribute('contenteditable', 'false');
    card.setAttribute('data-sc', buildShortcode(d));
    card.innerHTML = '<span class="xi-embed-icon">' + I.download + '</span><span class="xi-embed-text"><b>' + esc(d.name || '附件下载') + '</b><small>' + esc(meta) + '</small></span>' +
      '<button type="button" class="xi-embed-btn" data-embed-edit title="编辑">' + I.edit + '</button><button type="button" class="xi-embed-btn" data-embed-del title="删除">' + I.trash + '</button>';
    return card;
  };
  var openDownloadFlyout = function (card) {
    var d = card ? parseShortcode(card.getAttribute('data-sc')) : {};
    openFlyout(card || anchorRect(),
      '<label>文件名称<input type="text" name="name" value="' + esc(d.name || '') + '" placeholder="例如：曦主题 V1.0Beta1.zip"></label>' +
      '<label>下载链接<input type="url" name="url" value="' + esc(d.url || '') + '" placeholder="https://"></label>' +
      '<label>提取码<input type="text" name="code" value="' + esc(d.code || '') + '" placeholder="网盘提取码，可留空"></label>' +
      '<label>文件大小<input type="text" name="size" value="' + esc(d.size || '') + '" placeholder="例如：4.6 MB，可留空"></label>' +
      '<label class="xi-flyout-check"><input type="checkbox" name="login"' + (d.login ? ' checked' : '') + '> 登录后才能下载</label>',
      function (f) {
        var url = f.url.value.trim();
        if (!url) { toast('请填写下载链接', false); return; }
        var data = { name: f.name.value.trim(), url: url, code: f.code.value.trim(), size: f.size.value.trim(), login: f.login.checked ? '1' : '', label: d.label || '', version: d.version || '', desc: d.desc || '' };
        if (card) {
          card.replaceWith(downloadCard(data));
          markDirty();
        } else {
          insertBlock(downloadCard(data));
        }
      });
  };

  /* 扩展区块：表单、卡片、短代码 */
  var bxOptions = function (f) {
    if (f.type === 'select') return f.options || {};
    return (BX.choices || {})[f.type] || {};
  };
  var bxField = function (f, v) {
    var name = esc(f.key);
    var label = esc(f.label);
    var help = f.help ? '<small class="xi-flyout-help">' + esc(f.help) + '</small>' : '';
    switch (f.type) {
      case 'toggle':
        return '<label class="xi-flyout-check"><input type="checkbox" name="' + name + '"' + (v ? ' checked' : '') + '> ' + label + '</label>';
      case 'select': case 'product': case 'productcat': case 'user':
        return '<label>' + label + '<select name="' + name + '">' + opt(bxOptions(f), String(v === undefined || v === null || v === '' ? (f.type === 'select' ? f['default'] : '0') : v)) + '</select>' + help + '</label>';
      case 'textarea': case 'lines':
        return '<label>' + label + '<textarea name="' + name + '" rows="' + (f.type === 'lines' ? 5 : 3) + '">' + esc(v || '') + '</textarea>' + help + '</label>';
      case 'number':
        return '<label>' + label + '<input type="number" name="' + name + '" value="' + esc(v || '') + '">' + help + '</label>';
      case 'datetime':
        return '<label>' + label + '<input type="datetime-local" name="' + name + '" value="' + esc(String(v || '').replace(' ', 'T')) + '">' + help + '</label>';
      default:
        return '<label>' + label + '<input type="' + (f.type === 'url' || f.type === 'image' ? 'url' : 'text') + '" name="' + name + '" value="' + esc(v || '') + '" placeholder="' + esc(f.placeholder || (f.type === 'url' || f.type === 'image' ? 'https://' : '')) + '">' + help + '</label>';
    }
  };
  var bxShortcode = function (name, d) {
    var def = BX_DEFS[name];
    return '[xi_' + name + def.fields.map(function (f) {
      var v = d[f.key];
      if (f.type === 'toggle') return ' ' + f.key + '="' + (v ? '1' : '0') + '"';
      if (v === undefined || v === null || v === '') return '';
      return ' ' + f.key + '="' + scAttr(String(v).replace(/\r?\n/g, '{br}')) + '"';
    }).join('') + ']';
  };
  var bxParse = function (name, text) {
    var raw = parseShortcode(text);
    var d = {};
    BX_DEFS[name].fields.forEach(function (f) {
      if (raw[f.key] === undefined) { d[f.key] = f['default']; return; }
      d[f.key] = f.type === 'toggle' ? raw[f.key] === '1' : raw[f.key].replace(/\{br\}/g, '\n');
    });
    return d;
  };
  // 卡片副标题：取第一个有值的文字字段，商品显示商品名
  var bxSummary = function (def, d) {
    for (var i = 0; i < def.fields.length; i++) {
      var f = def.fields[i];
      var v = d[f.key];
      if (!v || f.type === 'toggle') continue;
      if (f.type === 'product' || f.type === 'productcat' || f.type === 'user') {
        var o = bxOptions(f);
        if (String(v) !== '0' && o[v]) return f.label + '：' + o[v];
        continue;
      }
      if (f.type === 'select') continue;
      var s = String(v).split(/\n/)[0];
      return f.label + '：' + (s.length > 30 ? s.slice(0, 30) + '…' : s);
    }
    return def.desc;
  };
  var bxCard = function (name, d) {
    var def = BX_DEFS[name];
    var card = document.createElement('div');
    card.className = 'xi-embed is-bx';
    card.setAttribute('contenteditable', 'false');
    card.setAttribute('data-bx', name);
    card.setAttribute('data-sc', bxShortcode(name, d));
    card.innerHTML = '<span class="xi-embed-icon">' + (I['bx:' + name] || BX_ICONS.content) + '</span><span class="xi-embed-text"><b>' + esc(def.title) + '</b><small>' + esc(bxSummary(def, d)) + '</small></span>' +
      '<button type="button" class="xi-embed-btn" data-embed-edit title="编辑">' + I.edit + '</button><button type="button" class="xi-embed-btn" data-embed-del title="删除">' + I.trash + '</button>';
    return card;
  };
  var openBxFlyout = function (name, card) {
    var def = BX_DEFS[name];
    if (!def) return;
    var d = card ? bxParse(name, card.getAttribute('data-sc')) : {};
    if (!card) def.fields.forEach(function (f) { d[f.key] = f['default']; });
    openFlyout(card || anchorRect(),
      '<p class="xi-flyout-title">' + esc(def.title) + '</p>' + def.fields.map(function (f) { return bxField(f, d[f.key]); }).join(''),
      function (form) {
        var out = {};
        def.fields.forEach(function (f) {
          var input = form.elements[f.key];
          if (!input) return;
          if (f.type === 'toggle') out[f.key] = input.checked;
          else if (f.type === 'datetime') out[f.key] = input.value.replace('T', ' ');
          else out[f.key] = input.value.trim();
        });
        if (def.inner) {
          // 容器区块：插入首尾两段短代码，中间是可编辑的正文
          insertBlock('<p>' + esc(bxShortcode(name, out)) + '</p><p>在这里输入只有满足条件才能看到的内容</p><p>[/xi_' + esc(name) + ']</p>');
          return;
        }
        if (card) {
          card.replaceWith(bxCard(name, out));
          markDirty();
        } else {
          insertBlock(bxCard(name, out));
        }
      });
  };

  editor.addEventListener('click', function (e) {
    var card = e.target.closest('.xi-embed');
    if (card && e.target.closest('[data-embed-del]')) { card.remove(); markDirty(); return; }
    if (card && e.target.closest('[data-embed-edit]')) {
      if (card.hasAttribute('data-bx')) openBxFlyout(card.getAttribute('data-bx'), card); else openDownloadFlyout(card);
      return;
    }
    // 编辑器里点折叠框标题不收起，方便修改标题
    if (e.target.closest('summary')) e.preventDefault();
  });

  // 载入内容：折叠框在编辑器里全部展开，下载短代码换成卡片
  var prepareContent = function () {
    editor.querySelectorAll('details').forEach(function (d) {
      if (!d.hasAttribute('data-xi-open')) d.setAttribute('data-xi-open', d.open ? '1' : '0');
      d.open = true;
    });
    editor.querySelectorAll('p').forEach(function (p) {
      var t = p.textContent.trim();
      if (/^\[xi_download\b[^\]]*\]$/.test(t) && p.children.length === 0) { p.replaceWith(downloadCard(parseShortcode(t))); return; }
      var m = t.match(/^\[xi_([a-z]+)\b[^\]]*\]$/);
      if (m && BX_DEFS[m[1]] && !BX_DEFS[m[1]].inner && p.children.length === 0) p.replaceWith(bxCard(m[1], bxParse(m[1], t)));
    });
  };

  // 区块内回车：空的末尾段落按回车跳出区块；折叠框标题里回车进入正文
  var blockKeys = function (e) {
    if (e.key !== 'Enter' || e.shiftKey || e.ctrlKey || e.metaKey) return false;
    var summary = closestInEditor('summary');
    if (summary) {
      e.preventDefault();
      var body = summary.parentNode.querySelector('.xi-block-collapse-body');
      var first = body && (body.firstElementChild || body);
      if (first) placeCaret(first, false);
      return true;
    }
    var alertTitle = closestInEditor('.xi-block-alert-title');
    if (alertTitle) {
      e.preventDefault();
      var after = alertTitle.nextElementSibling;
      if (!after) { after = newParagraph(); alertTitle.after(after); }
      placeCaret(after, false);
      return true;
    }
    var cite = closestInEditor('blockquote.xi-quote cite');
    var p = closestInEditor('p');
    var box = closestInEditor('.xi-block-alert, .xi-block-collapse, blockquote');
    if (!box) return false;
    var leave = cite || (p && !p.textContent.trim() && !p.querySelector('img') && !p.nextElementSibling);
    if (!leave) return false;
    e.preventDefault();
    if (p && !cite && p.parentNode.children.length > 1) p.remove();
    var next = newParagraph();
    box.after(next);
    placeCaret(next, false);
    markDirty();
    return true;
  };

  // 折叠框标题里按空格不要触发展开 / 收起
  editor.addEventListener('keydown', function (e) {
    if (e.key === ' ' && closestInEditor('summary')) {
      e.preventDefault();
      document.execCommand('insertText', false, ' ');
    }
  });

  /* ---------- 图片上传 ---------- */

  var upload = function (file) {
    if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
      toast('仅支持 JPG、PNG、GIF、WebP 图片', false);
      return Promise.reject();
    }
    if (file.size > cfg.maxSize * 1024 * 1024) {
      toast('图片不能超过 ' + cfg.maxSize + ' MB', false);
      return Promise.reject();
    }
    var data = new FormData();
    data.append('action', uploadAction);
    data.append('nonce', cfg.nonce);
    data.append('post_id', postId);
    data.append('file', file);
    return fetch(cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok) { toast(res.msg, false); throw new Error(res.msg); }
        return res;
      });
  };

  var insertImages = function (files) {
    if (!cfg.upload) { toast('你所在的用户组暂无上传图片权限', false); return; }
    Array.prototype.forEach.call(files, function (file) {
      var tmp = 'xi-up-' + Date.now() + Math.random().toString(16).slice(2);
      var blob = URL.createObjectURL(file);
      exec('insertHTML', '<p><img src="' + blob + '" data-up="' + tmp + '" class="is-uploading" alt=""></p><p><br></p>');
      upload(file).then(function (res) {
        var img = editor.querySelector('[data-up="' + tmp + '"]');
        if (!img) return;
        img.src = res.url;
        img.removeAttribute('data-up');
        img.className = 'wp-image-' + res.id;
        markDirty();
      }).catch(function () {
        var img = editor.querySelector('[data-up="' + tmp + '"]');
        if (img) img.closest('p') ? img.closest('p').remove() : img.remove();
      }).then(function () { URL.revokeObjectURL(blob); });
    });
  };

  fileInput.addEventListener('change', function () {
    insertImages(fileInput.files);
    fileInput.value = '';
  });

  editor.addEventListener('dragover', function (e) {
    if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') > -1) {
      e.preventDefault();
      editor.classList.add('is-dragover');
    }
  });
  editor.addEventListener('dragleave', function () { editor.classList.remove('is-dragover'); });
  editor.addEventListener('drop', function (e) {
    editor.classList.remove('is-dragover');
    if (!e.dataTransfer || !e.dataTransfer.files.length) return;
    e.preventDefault();
    if (document.caretRangeFromPoint) {
      var range = document.caretRangeFromPoint(e.clientX, e.clientY);
      if (range) savedRange = range;
    }
    insertImages(e.dataTransfer.files);
  });

  /* ---------- 粘贴：图片上传，HTML 清洗 ---------- */

  var ALLOWED = { P: 1, BR: 1, H2: 1, H3: 1, H4: 1, STRONG: 1, B: 1, EM: 1, I: 1, U: 1, S: 1, DEL: 1, A: 1, UL: 1, OL: 1, LI: 1, BLOCKQUOTE: 1, CITE: 1, PRE: 1, CODE: 1, IMG: 1, HR: 1, TABLE: 1, THEAD: 1, TBODY: 1, TR: 1, TH: 1, TD: 1 };
  var RENAME = { H1: 'H2', DIV: 'P', SECTION: 'P', ARTICLE: 'P', H5: 'H4', H6: 'H4' };

  var cleanNode = function (node) {
    Array.prototype.slice.call(node.childNodes).forEach(function (child) {
      if (child.nodeType === 8) { child.remove(); return; }
      if (child.nodeType !== 1) return;
      var tag = child.tagName;
      if (/^(SCRIPT|STYLE|META|LINK|IFRAME|OBJECT|EMBED|FORM|INPUT|BUTTON|SVG)$/.test(tag)) { child.remove(); return; }
      if (RENAME[tag]) {
        var el = document.createElement(RENAME[tag]);
        while (child.firstChild) el.appendChild(child.firstChild);
        child.replaceWith(el);
        child = el;
        tag = el.tagName;
      }
      cleanNode(child);
      if (!ALLOWED[tag]) {
        while (child.firstChild) child.parentNode.insertBefore(child.firstChild, child);
        child.remove();
        return;
      }
      Array.prototype.slice.call(child.attributes).forEach(function (attr) {
        var keep = (tag === 'A' && attr.name === 'href') || (tag === 'IMG' && (attr.name === 'src' || attr.name === 'alt'));
        if (!keep || /^\s*javascript:/i.test(attr.value)) child.removeAttribute(attr.name);
      });
    });
  };

  editor.addEventListener('paste', function (e) {
    var cd = e.clipboardData;
    if (!cd) return;
    var images = Array.prototype.filter.call(cd.files || [], function (f) { return /^image\//.test(f.type); });
    if (images.length && !cd.getData('text/html')) {
      e.preventDefault();
      insertImages(images);
      return;
    }
    var html = cd.getData('text/html');
    e.preventDefault();
    if (html && !closestInEditor('pre')) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      cleanNode(doc.body);
      exec('insertHTML', doc.body.innerHTML);
    } else {
      exec('insertText', cd.getData('text/plain'));
    }
  });

  /* ---------- 键盘：快捷键与 Markdown 快捷输入 ---------- */

  var MD = [
    [/^#{2}$/, function () { exec('formatBlock', '<h2>'); }],
    [/^#{3}$/, function () { exec('formatBlock', '<h3>'); }],
    [/^>$/, function () { exec('formatBlock', '<blockquote>'); }],
    [/^[-*]$/, function () { exec('insertUnorderedList'); }],
    [/^1\.$/, function () { exec('insertOrderedList'); }],
    [/^```$/, function () { exec('formatBlock', '<pre>'); }]
  ];

  editor.addEventListener('keydown', function (e) {
    var mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 'k') { e.preventDefault(); openLinkFlyout(); return; }
    if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save('draft'); return; }

    if (blockKeys(e)) return;

    // 代码块内回车换行而不是新起段落，Ctrl+回车跳出
    var pre = closestInEditor('pre');
    if (pre && e.key === 'Enter') {
      e.preventDefault();
      if (mod) {
        var p = document.createElement('p');
        p.innerHTML = '<br>';
        pre.after(p);
        var r = document.createRange();
        r.setStart(p, 0);
        var s = window.getSelection();
        s.removeAllRanges();
        s.addRange(r);
      } else {
        document.execCommand('insertText', false, '\n');
      }
      markDirty();
      return;
    }

    // 行首输入 ## / > / - / 1. / ``` 再按空格，转换为对应格式
    if (e.key === ' ' && !mod) {
      var sel = window.getSelection();
      if (!sel.isCollapsed) return;
      var block = closestInEditor('p');
      if (!block) return;
      var text = block.textContent;
      var rule = MD.filter(function (m) { return m[0].test(text); })[0];
      if (!rule || sel.anchorOffset !== text.length) return;
      e.preventDefault();
      block.innerHTML = '<br>';
      var range = document.createRange();
      range.setStart(block, 0);
      sel.removeAllRanges();
      sel.addRange(range);
      savedRange = range.cloneRange();
      rule[1]();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (!flyout.hidden) closeFlyout();
      else if (document.body.classList.contains('xi-writer-focus')) commands.focus();
    }
  });

  /* ---------- 标题自动高度 ---------- */

  var fitTitle = function () {
    titleEl.style.height = 'auto';
    titleEl.style.height = titleEl.scrollHeight + 'px';
  };
  titleEl.addEventListener('input', function () { fitTitle(); markDirty(); });
  titleEl.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); editor.focus(); }
  });

  /* ---------- 标签 ---------- */

  var tags = [];
  var tagInput = tagsBox.querySelector('input');
  var renderTags = function () {
    tagsBox.querySelectorAll('.xi-tag-chip').forEach(function (c) { c.remove(); });
    tags.forEach(function (t, i) {
      var chip = document.createElement('span');
      chip.className = 'xi-tag-chip';
      chip.innerHTML = esc(t) + '<button type="button" aria-label="移除">×</button>';
      chip.querySelector('button').addEventListener('click', function () { tags.splice(i, 1); renderTags(); markDirty(); });
      tagsBox.insertBefore(chip, tagInput);
    });
    tagInput.hidden = tags.length >= maxTags;
  };
  var addTag = function () {
    var v = tagInput.value.replace(/[,，]/g, '').trim();
    tagInput.value = '';
    if (v && tags.indexOf(v) === -1 && tags.length < maxTags) { tags.push(v); renderTags(); markDirty(); }
  };
  tags = (tagsBox.getAttribute('data-tags') || '').split(',').filter(Boolean);
  renderTags();
  tagInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ',' || e.key === '，') { e.preventDefault(); addTag(); }
    if (e.key === 'Backspace' && !tagInput.value && tags.length) { tags.pop(); renderTags(); markDirty(); }
  });
  tagInput.addEventListener('blur', addTag);
  tagsBox.addEventListener('click', function () { tagInput.focus(); });

  /* ---------- 封面（发帖页没有） ---------- */

  if (cover) {
    var setCover = function (id, url) {
      cover.setAttribute('data-thumb', id || 0);
      var img = cover.querySelector('img');
      if (id) {
        if (!img) { img = document.createElement('img'); img.alt = ''; cover.insertBefore(img, cover.firstChild); }
        img.src = url;
      } else if (img) {
        img.remove();
      }
      cover.classList.toggle('has-image', !!id);
      markDirty();
    };
    cover.addEventListener('click', function (e) {
      if (e.target.closest('.xi-writer-cover-remove')) { setCover(0); return; }
      if (!cfg.upload) { toast('你所在的用户组暂无上传图片权限', false); return; }
      coverInput.click();
    });
    cover.addEventListener('keydown', function (e) { if (e.key === 'Enter' && cfg.upload) coverInput.click(); });
    coverInput.addEventListener('change', function () {
      var file = coverInput.files[0];
      coverInput.value = '';
      if (!file) return;
      cover.classList.add('is-loading');
      upload(file).then(function (res) { setCover(res.id, res.thumb || res.url); })
        .catch(function () {})
        .then(function () { cover.classList.remove('is-loading'); });
    });
    form.querySelector('.xi-writer-cover-auto').addEventListener('click', function () {
      var img = editor.querySelector('img[class*="wp-image-"]');
      if (!img) { toast('正文中没有已上传的图片', false); return; }
      setCover(img.className.match(/wp-image-(\d+)/)[1], img.src);
    });
  }

  /* ---------- 字数与本地自动保存 ---------- */

  var storeKey = storePrefix + (postId || 'new');
  var localTimer = null;

  var updateCount = function () {
    var n = (editor.textContent || '').replace(/\s+/g, '').length;
    countEl.textContent = n + ' 字';
  };

  var saveLocal = function () {
    try {
      localStorage.setItem(storeKey, JSON.stringify({ title: titleEl.value, content: cleanContent(), t: Date.now() }));
      savedEl.textContent = '已自动保存到本地 ' + new Date().toTimeString().slice(0, 5);
    } catch (e) {}
  };

  var clearLocal = function () {
    try { localStorage.removeItem(storeKey); localStorage.removeItem(storePrefix + 'new'); } catch (e) {}
  };

  var markDirty = function () {
    dirty = true;
    updateCount();
    clearTimeout(localTimer);
    localTimer = setTimeout(saveLocal, 1200);
  };

  editor.addEventListener('input', markDirty);
  form.addEventListener('change', function (e) { if (e.target.name === 'cat' || e.target.name === 'excerpt') markDirty(); });

  // 载入的内容先整理成编辑器里的样子（折叠框展开、下载短代码换成卡片）
  prepareContent();

  // 恢复本地草稿
  try {
    var local = JSON.parse(localStorage.getItem(storeKey) || 'null');
    if (local && (local.content !== cleanContent() || local.title !== titleEl.value) && (local.content || local.title)) {
      var bar = document.createElement('div');
      bar.className = 'xi-writer-restore';
      bar.innerHTML = '<span>发现 ' + new Date(local.t).toLocaleString() + ' 未提交的内容</span><button type="button" class="xi-btn xi-btn-sm xi-btn-accent">恢复</button><button type="button" class="xi-btn xi-btn-sm">丢弃</button>';
      editor.parentNode.insertBefore(bar, editor);
      bar.querySelectorAll('button')[0].addEventListener('click', function () {
        titleEl.value = local.title;
        editor.innerHTML = local.content;
        prepareContent();
        fitTitle();
        markDirty();
        bar.remove();
      });
      bar.querySelectorAll('button')[1].addEventListener('click', function () { clearLocal(); bar.remove(); });
    }
  } catch (e) {}

  if (!editor.innerHTML.trim()) editor.innerHTML = '<p><br></p>';

  /* ---------- 提交 ---------- */

  function cleanContent() {
    var box = document.createElement('div');
    box.innerHTML = editor.innerHTML;
    box.querySelectorAll('img.is-uploading').forEach(function (img) { (img.closest('p') || img).remove(); });
    box.querySelectorAll('.xi-embed[data-sc]').forEach(function (card) {
      var p = document.createElement('p');
      p.textContent = card.getAttribute('data-sc');
      card.replaceWith(p);
    });
    box.querySelectorAll('details[data-xi-open]').forEach(function (d) {
      if (d.getAttribute('data-xi-open') === '1') d.setAttribute('open', ''); else d.removeAttribute('open');
      d.removeAttribute('data-xi-open');
    });
    box.querySelectorAll('[contenteditable], [data-up]').forEach(function (n) { n.removeAttribute('contenteditable'); n.removeAttribute('data-up'); });
    // 去掉末尾空段落
    var last = box.lastElementChild;
    while (last && last.tagName === 'P' && !last.textContent.trim() && !last.querySelector('img')) {
      last.remove();
      last = box.lastElementChild;
    }
    return box.innerHTML.replace(/​/g, '');
  }

  var save = function (mode) {
    if (saving) return;
    if (editor.querySelector('img.is-uploading')) { toast('图片还在上传，请稍候', false); return; }
    saving = true;
    form.querySelectorAll('[data-save]').forEach(function (b) { b.disabled = true; });

    var data = new FormData();
    data.append('action', saveAction);
    data.append('nonce', cfg.nonce);
    data.append('post_id', postId);
    data.append('mode', mode);
    data.append('title', titleEl.value.trim());
    data.append('content', cleanContent());
    data.append('cat', form.cat ? form.cat.value : '');
    data.append('tags', tags.join(','));
    if (cover) data.append('thumb', cover.getAttribute('data-thumb') || 0);
    if (form.excerpt) data.append('excerpt', form.excerpt.value);

    fetch(cfg.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { ok: false, msg: '网络错误，内容已保存在本地' }; })
      .then(function (res) {
        saving = false;
        form.querySelectorAll('[data-save]').forEach(function (b) { b.disabled = false; });
        toast(res.msg, res.ok);
        if (!res.ok) {
          if (res.field === 'title') titleEl.focus();
          if (res.field === 'content') editor.focus();
          if (res.field === 'cat') form.cat.focus();
          return;
        }
        dirty = false;
        clearLocal();
        if (!postId && res.post_id) {
          postId = res.post_id;
          form.setAttribute('data-post-id', postId);
          storeKey = storePrefix + postId;
          history.replaceState(null, '', res.edit_url);
        }
        savedEl.textContent = '已保存 ' + new Date().toTimeString().slice(0, 5);
        if (res.status !== 'draft') {
          setTimeout(function () { location.href = res.link || res.uc_url; }, 1000);
        }
      });
  };

  form.querySelectorAll('[data-save]').forEach(function (btn) {
    btn.addEventListener('click', function () { save(btn.getAttribute('data-save')); });
  });

  window.addEventListener('beforeunload', function (e) {
    if (dirty && !saving) { e.preventDefault(); e.returnValue = ''; }
  });

  fitTitle();
  updateCount();
  if (!titleEl.value) titleEl.focus();
})();
