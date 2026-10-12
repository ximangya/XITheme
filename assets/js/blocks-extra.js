/* 曦主题扩展区块（编辑器）：按 PHP 传来的字段定义自动生成设置面板，预览由服务端渲染 */
(function (wp) {
  var data = window.xiBlockDefs || {};
  if (!wp || !wp.blocks || !data.blocks) return;
  var el = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var be = wp.blockEditor;
  var c = wp.components;
  var SSR = wp.serverSideRender;
  var choices = data.choices || {};
  var MAIN = '#2bb39a';

  var options = function (map) {
    return Object.keys(map || {}).map(function (k) { return { value: k, label: map[k] }; });
  };

  // 字段 → 设置控件
  var control = function (f, props) {
    var a = props.attributes;
    var v = a[f.key];
    var set = function (val) { var o = {}; o[f.key] = val; props.setAttributes(o); };
    var common = { key: f.key, label: f.label, help: f.help || undefined };
    switch (f.type) {
      case 'toggle':
        return el(c.ToggleControl, Object.assign(common, { checked: !!v, onChange: set }));
      case 'select':
        return el(c.SelectControl, Object.assign(common, { value: String(v), options: options(f.options), onChange: set }));
      case 'product':
      case 'productcat':
      case 'user':
        return el(c.SelectControl, Object.assign(common, { value: String(v || '0'), options: options(choices[f.type]), onChange: set }));
      case 'textarea':
      case 'lines':
        return el(c.TextareaControl, Object.assign(common, { value: v || '', rows: f.type === 'lines' ? 6 : 3, onChange: set }));
      case 'number':
        return el(c.TextControl, Object.assign(common, { type: 'number', value: v, onChange: function (x) { set(parseInt(x, 10) || 0); } }));
      case 'datetime':
        return el(c.TextControl, Object.assign(common, { type: 'datetime-local', value: (v || '').replace(' ', 'T'), onChange: function (x) { set((x || '').replace('T', ' ')); } }));
      case 'color':
        return el(c.BaseControl, common,
          el(c.ColorPalette, { value: v || undefined, onChange: function (x) { set(x || ''); }, clearable: true }));
      case 'url':
      case 'image':
        return el(c.TextControl, Object.assign(common, { type: 'url', value: v || '', placeholder: f.placeholder || 'https://', onChange: set }));
      default:
        return el(c.TextControl, Object.assign(common, { value: v || '', placeholder: f.placeholder || '', onChange: set }));
    }
  };

  data.blocks.forEach(function (def) {
    var name = 'xi/' + def.name;
    if (wp.blocks.getBlockType(name)) return;
    wp.blocks.registerBlockType(name, {
      apiVersion: 3,
      title: def.title,
      description: def.desc,
      category: 'xi',
      icon: { src: def.icon, foreground: MAIN },
      keywords: [def.title, def.name, def.group === 'shop' ? '商城' : '内容'],
      edit: function (props) {
        var blockProps = be.useBlockProps({ className: 'xi-bx-edit' + (def.inner ? ' is-inner' : '') });
        var panel = el(be.InspectorControls, null,
          el(c.PanelBody, { title: def.title + '设置' }, def.fields.map(function (f) { return control(f, props); })));
        if (def.inner) {
          // 容器区块：内容直接在编辑器里编辑
          var label = def.title;
          if (def.fields[0] && def.fields[0].type === 'product') {
            var pid = String(props.attributes[def.fields[0].key] || '0');
            label += ' · ' + (pid !== '0' && choices.product && choices.product[pid] ? choices.product[pid] : '未选择商品');
          }
          return el(Fragment, null, panel,
            el('div', blockProps,
              el('div', { className: 'xi-bx-edit-label' }, label),
              el(be.InnerBlocks, { template: [['core/paragraph', { placeholder: '只有满足条件的用户能看到这里的内容…' }]] })));
        }
        return el(Fragment, null, panel,
          el('div', blockProps,
            SSR ? el(SSR, { block: name, attributes: props.attributes, httpMethod: 'POST' })
                : el('p', null, def.title + '（保存后在前台查看效果）')));
      },
      save: function () {
        return def.inner ? el(be.InnerBlocks.Content) : null;
      }
    });
  });
})(window.wp);
