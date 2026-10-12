/* 曦主题编辑器区块（无需构建，直接使用 wp.* 全局对象）。属性定义由 PHP 注册并自动同步到编辑器 */
(function (wp) {
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var RawHTML = wp.element.RawHTML;
  var registerBlockType = wp.blocks.registerBlockType;
  var be = wp.blockEditor;
  var c = wp.components;
  var useBlockProps = be.useBlockProps;
  var InnerBlocks = be.InnerBlocks;
  var RichText = be.RichText;
  var PlainText = be.PlainText;
  var InspectorControls = be.InspectorControls;
  var BlockControls = be.BlockControls;
  var cfg = window.xiBlocks || {};
  var MAIN = '#2bb39a';

  var ICONS = {
    info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
    check: '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    alert: '<path d="M12 4 2.5 20h19z"/><path d="M12 10v4M12 17h.01"/>',
    edit: '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
    download: '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
    lock: '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
    chevron: '<path d="m9 6 6 6-6 6"/>',
    copy: '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>'
  };
  var icon = function (name) {
    return el(RawHTML, null, '<svg class="xi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>');
  };
  var setter = function (props, key) {
    return function (v) {
      var o = {};
      o[key] = v;
      props.setAttributes(o);
    };
  };
  var innerSave = function () { return el(InnerBlocks.Content); };
  var nullSave = function () { return null; };
  var base = { apiVersion: 3, category: 'xi' };
  var reg = function (name, def) {
    registerBlockType('xi/' + name, Object.assign({}, base, def));
  };

  /* ---------- 提醒框 ---------- */
  var ALERT_TYPES = [
    { value: 'info', label: '提示（蓝）', icon: 'info' },
    { value: 'success', label: '成功（绿）', icon: 'check' },
    { value: 'warning', label: '警告（橙）', icon: 'alert' },
    { value: 'danger', label: '危险（红）', icon: 'alert' },
    { value: 'note', label: '笔记（主题色）', icon: 'edit' }
  ];
  reg('alert', {
    title: '提醒框',
    description: '提示、成功、警告、危险、笔记五种样式的提醒框，内容里可以继续放段落、列表等区块',
    icon: { src: 'warning', foreground: MAIN },
    keywords: ['alert', 'notice', 'tip', '提示', '警告', '提醒'],
    edit: function (props) {
      var a = props.attributes;
      var type = ALERT_TYPES.filter(function (t) { return t.value === a.type; })[0] || ALERT_TYPES[0];
      return el(Fragment, null,
        el(InspectorControls, null,
          el(c.PanelBody, { title: '提醒框设置' },
            el(c.SelectControl, { label: '样式', value: a.type, options: ALERT_TYPES, onChange: setter(props, 'type') }))),
        el(BlockControls, null,
          el(c.ToolbarGroup, null, ALERT_TYPES.map(function (t) {
            return el(c.ToolbarButton, { key: t.value, label: t.label, isPressed: a.type === t.value, onClick: function () { props.setAttributes({ type: t.value }); } },
              el('span', { className: 'xi-be-dot is-' + t.value }));
          }))),
        el('div', useBlockProps({ className: 'xi-block-alert is-' + type.value }),
          el('span', { className: 'xi-block-alert-icon' }, icon(type.icon)),
          el('div', { className: 'xi-block-alert-body' },
            (a.title || props.isSelected) && el(RichText, {
              tagName: 'b', className: 'xi-block-alert-title', value: a.title, onChange: setter(props, 'title'),
              placeholder: '标题（可选）', allowedFormats: ['core/bold', 'core/italic', 'core/link', 'core/code']
            }),
            el(InnerBlocks, { template: [['core/paragraph', { placeholder: '输入提醒内容…' }]] }))));
    },
    save: innerSave
  });

  /* ---------- 标题 ---------- */
  var HEADING_STYLES = [
    { value: 'line', label: '左侧竖线' },
    { value: 'under', label: '底部渐变线' },
    { value: 'pill', label: '主题色块' },
    { value: 'center', label: '居中 · 两侧短线' },
    { value: 'hash', label: '井号 #' },
    { value: 'num', label: '自动序号 01 02 03' }
  ];
  reg('heading', {
    title: '标题',
    description: '带装饰的小标题，会出现在文章目录里',
    icon: { src: 'heading', foreground: MAIN },
    keywords: ['heading', 'title', '标题'],
    edit: function (props) {
      var a = props.attributes;
      var align = a.style === 'center' ? 'center' : a.align;
      return el(Fragment, null,
        el(InspectorControls, null,
          el(c.PanelBody, { title: '标题设置' },
            el(c.SelectControl, { label: '装饰样式', value: a.style, options: HEADING_STYLES, onChange: setter(props, 'style') }))),
        el(BlockControls, null,
          el(c.ToolbarGroup, null, [2, 3, 4].map(function (lv) {
            return el(c.ToolbarButton, { key: lv, label: '标题 ' + lv, isPressed: a.level === lv, onClick: function () { props.setAttributes({ level: lv }); } }, 'H' + lv);
          })),
          a.style !== 'center' && el(c.ToolbarGroup, null,
            el(c.ToolbarButton, { icon: 'editor-alignleft', label: '居左', isPressed: a.align !== 'center', onClick: function () { props.setAttributes({ align: 'left' }); } }),
            el(c.ToolbarButton, { icon: 'editor-aligncenter', label: '居中', isPressed: a.align === 'center', onClick: function () { props.setAttributes({ align: 'center' }); } }))),
        el('h' + a.level, useBlockProps({ className: 'xi-block-heading is-' + a.style + ' is-align-' + align }),
          el(RichText, {
            tagName: 'span', value: a.content, onChange: setter(props, 'content'), placeholder: '输入标题…',
            allowedFormats: ['core/bold', 'core/italic', 'core/link', 'core/code']
          })));
    },
    save: nullSave
  });

  /* ---------- 文件下载 ---------- */
  var fileKind = function (name, url) {
    var m = String(name || '').trim().match(/\.([a-z0-9]{1,5})$/i) || String(url || '').split(/[?#]/)[0].match(/\.([a-z0-9]{1,5})$/i);
    var ext = m ? m[1].toLowerCase() : '';
    var kinds = {
      zip: 'zip rar 7z tar gz bz2 xz', app: 'exe msi apk ipa dmg pkg deb rpm appimage',
      doc: 'pdf doc docx txt md epub ppt pptx xls xlsx csv', media: 'mp3 flac wav mp4 mkv mov avi jpg jpeg png gif webp psd svg',
      code: 'js css php py json html sql sh bat ps1'
    };
    for (var k in kinds) {
      if (ext && kinds[k].split(' ').indexOf(ext) !== -1) return [k, ext.toUpperCase()];
    }
    return ['file', ext ? ext.toUpperCase() : 'FILE'];
  };
  reg('download', {
    title: '文件下载',
    description: '文件下载卡片：名称、版本、大小、更新时间、多个下载地址与提取码',
    icon: { src: 'download', foreground: MAIN },
    keywords: ['download', 'file', '下载', '网盘', '附件'],
    edit: function (props) {
      var a = props.attributes;
      var links = Array.isArray(a.links) ? a.links : [];
      var kind = fileKind(a.name, links[0] && links[0].url);
      var setLink = function (i, key, v) {
        var next = links.map(function (l, j) {
          if (j !== i) return l;
          var o = Object.assign({}, l);
          o[key] = v;
          return o;
        });
        props.setAttributes({ links: next });
      };
      var sel = props.isSelected;
      var metaPreview = [['version', '版本'], ['size', '大小'], ['updated', '更新']].filter(function (m) { return a[m[0]]; });
      return el(Fragment, null,
        el(InspectorControls, null,
          el(c.PanelBody, { title: '下载设置' },
            el(c.ToggleControl, { label: '登录后才能下载', checked: !!a.login, onChange: setter(props, 'login'), help: '未登录访客只看到文件信息和登录按钮。需要积分或会员才能下载时，可以把整个卡片放进「隐藏内容」区块' }))),
        el('div', useBlockProps({ className: 'xi-block-down' }),
          el('div', { className: 'xi-block-down-head' },
            el('span', { className: 'xi-block-down-icon is-' + kind[0] }, icon('download'), el('small', null, kind[1])),
            el('div', { className: 'xi-block-down-info' },
              el(PlainText, { className: 'xi-block-down-name xi-be-plain', value: a.name, onChange: setter(props, 'name'), placeholder: '文件名称，例如 曦主题-V1.0Beta1.zip' }),
              sel ? el('div', { className: 'xi-be-meta' },
                el(c.TextControl, { label: '版本', value: a.version, onChange: setter(props, 'version'), placeholder: 'v1.0' }),
                el(c.TextControl, { label: '大小', value: a.size, onChange: setter(props, 'size'), placeholder: '4.5 MB' }),
                el(c.TextControl, { label: '更新时间', value: a.updated, onChange: setter(props, 'updated'), placeholder: '2026-10-06' }))
                : metaPreview.length ? el('p', { className: 'xi-block-down-meta' }, metaPreview.map(function (m) {
                  return el('span', { key: m[0] }, m[1] + ' ', el('b', null, a[m[0]]));
                })) : null,
              (sel || a.desc) && el(PlainText, { className: 'xi-block-down-desc xi-be-plain', value: a.desc, onChange: setter(props, 'desc'), placeholder: '一句话简介（可选）' }))),
          el('div', { className: 'xi-block-down-links' + (sel ? ' is-editing' : '') },
            sel ? el(Fragment, null,
              links.map(function (l, i) {
                return el('div', { className: 'xi-be-link', key: i },
                  el(c.TextControl, { label: '按钮文字', value: l.label || '', onChange: function (v) { setLink(i, 'label', v); }, placeholder: '立即下载 / 百度网盘' }),
                  el(c.TextControl, { label: '下载地址', type: 'url', value: l.url || '', onChange: function (v) { setLink(i, 'url', v); }, placeholder: 'https://' }),
                  el(c.TextControl, { label: '提取码', value: l.code || '', onChange: function (v) { setLink(i, 'code', v); }, placeholder: '可选' }),
                  el(c.Button, { icon: 'trash', label: '删除', isDestructive: true, onClick: function () { props.setAttributes({ links: links.filter(function (x, j) { return j !== i; }) }); } }));
              }),
              el(c.Button, { variant: 'secondary', icon: 'plus-alt2', onClick: function () { props.setAttributes({ links: links.concat([{ label: '', url: '', code: '' }]) }); } }, '添加下载地址'))
              : links.filter(function (l) { return l.url; }).length
                ? links.filter(function (l) { return l.url; }).map(function (l, i) {
                  return el('span', { className: 'xi-block-down-item', key: i },
                    el('span', { className: 'xi-be-btn' }, icon('download'), l.label || '立即下载'),
                    l.code && el('span', { className: 'xi-block-down-code' }, '提取码 ', el('b', null, l.code), icon('copy')));
                })
                : el('span', { className: 'xi-block-down-empty' }, '选中卡片后填写下载地址'))));
    },
    save: nullSave
  });

  /* ---------- 折叠框 ---------- */
  reg('collapse', {
    title: '折叠框',
    description: '点击标题展开 / 收起的内容，适合放 FAQ、长代码、补充说明',
    icon: { src: 'arrow-down-alt2', foreground: MAIN },
    keywords: ['collapse', 'accordion', 'details', 'faq', '折叠', '展开'],
    edit: function (props) {
      var a = props.attributes;
      return el(Fragment, null,
        el(InspectorControls, null,
          el(c.PanelBody, { title: '折叠框设置' },
            el(c.ToggleControl, { label: '默认展开', checked: !!a.open, onChange: setter(props, 'open'), help: '编辑器里始终展开，方便编辑' }))),
        el('div', useBlockProps({ className: 'xi-block-collapse xi-be-collapse' }),
          el('div', { className: 'xi-be-summary' },
            el(RichText, { tagName: 'span', value: a.title, onChange: setter(props, 'title'), placeholder: '折叠框标题', allowedFormats: ['core/bold', 'core/italic', 'core/code'] }),
            el('span', { className: 'xi-be-tag' }, a.open ? '默认展开' : '默认收起')),
          el('div', { className: 'xi-block-collapse-body' },
            el(InnerBlocks, { template: [['core/paragraph', { placeholder: '折叠的内容…' }]] }))));
    },
    save: innerSave
  });

  /* ---------- 隐藏内容 ---------- */
  var vipOptions = function () {
    var opts = [];
    var v = cfg.vips || {};
    Object.keys(v).forEach(function (k) { opts.push({ value: String(k), label: (v[k] || ('等级 ' + k)) + ' 及以上' }); });
    return opts.length ? opts : [{ value: '1', label: '1 级会员及以上' }, { value: '2', label: '2 级会员及以上' }];
  };
  reg('hide', {
    title: '隐藏内容',
    description: '登录、评论、积分、会员、密码、指定用户组或定时公开的内容，与短代码 [xi_hide] 效果相同',
    icon: { src: 'lock', foreground: MAIN },
    keywords: ['hide', 'paid', 'vip', '隐藏', '付费', '会员', '回复可见'],
    edit: function (props) {
      var a = props.attributes;
      var pts = cfg.points || '积分';
      var label = {
        login: '登录后可见',
        reply: '评论后可见',
        points: '支付 ' + a.points + ' ' + pts + '后可见',
        vip: (vipOptions().filter(function (o) { return o.value === String(a.vip); })[0] || { label: '会员' }).label + '可见',
        password: '输入密码可见',
        role: '指定用户组可见',
        time: (a.time ? a.time + ' ' : '') + '定时公开'
      }[a.type];
      var roleMap = cfg.roles || {};
      var picked = (a.roles || '').split(',').filter(Boolean);
      var toggleRole = function (key, on) {
        var next = picked.filter(function (r) { return r !== key; });
        if (on) next.push(key);
        props.setAttributes({ roles: next.join(',') });
      };
      return el(Fragment, null,
        el(InspectorControls, null,
          el(c.PanelBody, { title: '查看条件' },
            el(c.SelectControl, {
              label: '谁可以看', value: a.type, onChange: setter(props, 'type'),
              options: [{ value: 'login', label: '登录用户' }, { value: 'reply', label: '评论过本文的用户' }, { value: 'points', label: '支付' + pts + '的用户' }, { value: 'vip', label: '会员' }, { value: 'password', label: '输入密码的访客' }, { value: 'role', label: '指定用户组' }, { value: 'time', label: '到点后所有人（定时公开）' }]
            }),
            a.type === 'password' && el(c.TextControl, { label: '查看密码', value: a.password, onChange: setter(props, 'password'), help: '访客输入正确密码后可见，30 天内免再次输入' }),
            a.type === 'role' && el('div', { className: 'xi-be-roles' },
              el('p', { className: 'xi-be-help' }, '允许查看的用户组'),
              Object.keys(roleMap).map(function (k) {
                return el(c.CheckboxControl, { key: k, label: roleMap[k], checked: picked.indexOf(k) > -1, onChange: function (on) { toggleRole(k, on); } });
              })),
            a.type === 'time' && el(c.TextControl, { label: '公开时间', type: 'datetime-local', value: (a.time || '').replace(' ', 'T'), onChange: function (v) { props.setAttributes({ time: (v || '').replace('T', ' ') }); }, help: '按站点时区，到点后自动对所有人公开' }),
            el(c.TextControl, { label: '锁定时的提示', value: a.tip, onChange: setter(props, 'tip'), help: '可留空；例如「关注公众号回复「曦」获取密码」' }),
            a.type === 'points' && el(c.TextControl, {
              label: '价格（' + pts + '）', type: 'number', min: 1, value: a.points,
              onChange: function (v) { props.setAttributes({ points: Math.max(1, parseInt(v, 10) || 1) }); },
              help: '一篇文章只需解锁一次：有多个付费区块时按其中最高的价格收取'
            }),
            a.type === 'vip' && el(c.SelectControl, { label: '会员等级', value: String(a.vip), options: vipOptions(), onChange: function (v) { props.setAttributes({ vip: parseInt(v, 10) || 1 }); } }),
            el('p', { className: 'xi-be-help' }, '文章作者和编辑始终可以看到隐藏内容；隐藏内容不会出现在摘要、SEO 描述和 RSS 中'))),
        el('div', useBlockProps({ className: 'xi-be-hide' }),
          el('div', { className: 'xi-be-hide-label' }, icon('lock'), '隐藏内容 · ' + label),
          el(InnerBlocks, { template: [['core/paragraph', { placeholder: '只有满足条件的用户才能看到这里的内容…' }]] })));
    },
    save: innerSave
  });

  /* ---------- 选项卡 ---------- */
  reg('tabs', {
    title: '选项卡',
    description: '多个标签页切换显示内容，例如不同系统的安装步骤',
    icon: { src: 'index-card', foreground: MAIN },
    keywords: ['tabs', 'tab', '选项卡', '标签页'],
    edit: function () {
      return el('div', useBlockProps({ className: 'xi-be-tabs' }),
        el('div', { className: 'xi-be-tabs-head' }, icon('chevron'), '选项卡 · 前台点击标签切换，编辑器里依次排列'),
        el(InnerBlocks, {
          allowedBlocks: ['xi/tab'],
          template: [['xi/tab', { title: '选项卡 1' }], ['xi/tab', { title: '选项卡 2' }]],
          renderAppender: function () { return el(InnerBlocks.ButtonBlockAppender); }
        }));
    },
    save: innerSave
  });

  reg('tab', {
    title: '选项卡页',
    description: '选项卡中的一页',
    icon: { src: 'media-default', foreground: MAIN },
    parent: ['xi/tabs'],
    edit: function (props) {
      return el('div', useBlockProps({ className: 'xi-be-tab' }),
        el(RichText, { tagName: 'div', className: 'xi-be-tab-title', value: props.attributes.title, onChange: setter(props, 'title'), placeholder: '标签名称', allowedFormats: [] }),
        el('div', { className: 'xi-be-tab-body' },
          el(InnerBlocks, { template: [['core/paragraph', { placeholder: '这一页的内容…' }]] })));
    },
    save: innerSave
  });
  /* ---------- 引言 ---------- */
  var QUOTE_STYLES = [
    { value: 'default', label: '大引号' },
    { value: 'card', label: '卡片' },
    { value: 'center', label: '居中' }
  ];
  reg('quote', {
    title: '引言',
    description: '名人名言、书摘、观点引用，可署名出处；与前台编辑器的「引言」一致',
    icon: { src: 'format-quote', foreground: MAIN },
    keywords: ['quote', 'cite', '引言', '引用', '名言', '书摘'],
    edit: function (props) {
      var a = props.attributes;
      return el(Fragment, null,
        el(InspectorControls, null,
          el(c.PanelBody, { title: '引言样式' },
            el(c.SelectControl, { label: '样式', value: a.style, options: QUOTE_STYLES, onChange: setter(props, 'style') }))),
        el('blockquote', useBlockProps({ className: 'xi-quote is-' + (a.style || 'default') }),
          el(RichText, { tagName: 'p', value: a.text, onChange: setter(props, 'text'), placeholder: '输入引言内容…', allowedFormats: ['core/bold', 'core/italic', 'core/link'] }),
          el(PlainText, { className: 'xi-be-cite', value: a.cite, onChange: setter(props, 'cite'), placeholder: '出处 / 作者（可留空）' })));
    },
    save: nullSave
  });

  /* ---------- 高亮代码 ---------- */
  var langOptions = function () {
    var l = cfg.langs || { '': '自动识别' };
    return Object.keys(l).map(function (k) { return { value: k, label: l[k] }; });
  };
  reg('code', {
    title: '高亮代码',
    description: '带语法高亮、行号、语言标签与一键复制的代码块；与前台编辑器的「代码块」一致',
    icon: { src: 'editor-code', foreground: MAIN },
    keywords: ['code', 'highlight', 'pre', '代码', '高亮'],
    edit: function (props) {
      var a = props.attributes;
      var langs = langOptions();
      var langLabel = (langs.filter(function (o) { return o.value === a.lang; })[0] || langs[0]).label;
      return el(Fragment, null,
        el(InspectorControls, null,
          el(c.PanelBody, { title: '代码设置' },
            el(c.SelectControl, { label: '语言', value: a.lang, options: langs, onChange: setter(props, 'lang') }),
            el(c.TextControl, { label: '文件名 / 标题', value: a.title, onChange: setter(props, 'title'), help: '显示在代码块顶栏，可留空' }))),
        el('div', useBlockProps({ className: 'xi-codebox xi-be-code' }),
          el('div', { className: 'xi-code-bar' },
            el('span', { className: 'xi-code-dots' }, el('i'), el('i'), el('i')),
            el('span', { className: 'xi-code-label' }, a.title || langLabel),
            el('select', {
              className: 'xi-be-code-lang', value: a.lang, 'aria-label': '语言',
              onChange: function (e) { props.setAttributes({ lang: e.target.value }); }
            }, langs.map(function (o) { return el('option', { key: o.value, value: o.value }, o.label); }))),
          el(PlainText, { className: 'xi-be-code-input', value: a.code, onChange: setter(props, 'code'), placeholder: '粘贴或输入代码…', spellCheck: false })));
    },
    save: nullSave
  });

  /* ---------- 按钮 ---------- */
  var BTN_STYLES = [
    { value: 'main', label: '实色' },
    { value: 'soft', label: '浅色' },
    { value: 'line', label: '描边' }
  ];
  reg('button', {
    title: '按钮',
    description: '醒目的链接按钮，适合「前往下载」「查看演示」等；与前台编辑器的「按钮」一致',
    icon: { src: 'button', foreground: MAIN },
    keywords: ['button', 'link', '按钮', '链接'],
    edit: function (props) {
      var a = props.attributes;
      return el(Fragment, null,
        el(InspectorControls, null,
          el(c.PanelBody, { title: '按钮设置' },
            el(c.TextControl, { label: '链接地址', value: a.url, onChange: setter(props, 'url'), placeholder: 'https://' }),
            el(c.SelectControl, { label: '样式', value: a.style, options: BTN_STYLES, onChange: setter(props, 'style') }),
            el(c.SelectControl, { label: '对齐', value: a.align, options: [{ value: 'left', label: '居左' }, { value: 'center', label: '居中' }], onChange: setter(props, 'align') }),
            el(c.ToggleControl, { label: '新窗口打开', checked: !!a.blank, onChange: setter(props, 'blank') }))),
        el('p', useBlockProps({ className: 'xi-block-btns is-align-' + (a.align || 'left') }),
          el('span', { className: 'xi-block-btn is-' + (a.style || 'main') },
            el(PlainText, { value: a.text, onChange: setter(props, 'text'), placeholder: '按钮文字', className: 'xi-be-btn-text' })),
          a.url ? null : el('span', { className: 'xi-be-tag' }, '在右侧填写链接')));
    },
    save: nullSave
  });
})(window.wp);
