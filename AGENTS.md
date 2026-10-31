# AGENTS.md · 曦主题（xi）

WordPress 主题，视觉语言是 **WinUI 3（Fluent）× 液态玻璃**。本文件给接手的开发者 / AI 代理一份「先读这个」的速查表；细节见 `docs/`。

## 一句话现状

- 主题版本 `V1.2`，内部构建号 `3015`（`functions.php` 的 `XI_BUILD`，`style.css` 的 `Version`）。**版本规则：每次小修改 XI_BUILD +5**（3000 → 3005 → 3010…），发版时同步更新。
- 149 个 PHP 文件、约 3.3 万行；无 npm / 无构建步骤，PHP + CSS + 原生 JS 直接运行。
- 功能范围：博客、前台投稿 / 编辑器（30 个区块）、用户体系（登录 / 积分 / VIP / 卡密）、发卡网商城（卡密 / 软件，自动发放）、论坛、第三方登录、AI 摘要、站点美化。
- Git：`origin` = <https://github.com/ximangya/XITheme.git>，`main` 已包含上游全部历史 + 本地基线提交。

## 快速命令

```powershell
# 语法检查（改完 PHP 必跑）
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }

# 启动本地开发站（WordPress 7.1.3 + SQLite，端口 8788）
php -S 127.0.0.1:8788 -t C:\Users\曦芒\Desktop\曦芒项目\dev-wp\site
# 后台 http://127.0.0.1:8788/wp-admin  admin / admin123

# 打包（在主题的父目录执行）
python build_xi.py
```

本地站原理、wp-cli 用法、示例内容重置见 `docs/DEVELOPMENT.md`。

## 目录地图

```
xi/
├─ style.css                主题头信息 + 样式入口说明（真正样式在 assets/css/main.css）
├─ functions.php            入口：常量、加载 inc/inc.php、挂 wp_head/wp_footer
├─ inc/
│  ├─ inc.php               模块加载顺序（唯一入口清单，加文件要改这里）
│  ├─ dependent.php         基础函数 _xz/_sxz、选项常量、主题色
│  ├─ options/              CSF 主题设置页（admin-options.php 是字段定义）、文章 metabox
│  ├─ codestar-framework/   内嵌 Codestar Framework（第三方，勿改）
│  ├─ csf-framework/        主题对 CSF 的扩展（小工具字段）
│  ├─ widgets/              小工具注册与侧栏位置
│  └─ functions/            业务模块，一功能一文件（见 docs/ARCHITECTURE.md）
├─ assets/
│  ├─ css/main.css          前台全部样式（令牌 / 材质 / 组件）
│  ├─ css/blocks.css        编辑器区块样式（前台与编辑器共用）
│  ├─ css/blocks-editor.css 仅编辑器用的辅助样式
│  ├─ css/shop.css          商城
│  ├─ js/                   main / interact / beautify / editor / shop / pay / code
│  ├─ fonts/gensen-rounded/ 源泉圆体（OFL）
│  └─ vendor/               Font Awesome 4.7、highlight.js、qrcode
├─ template-parts/          片段（卡片、循环、页头等）
└─ *.php                    模板层级：index/single/page/archive/taxonomy/user-center/write/bbs-new…
```

## 不可违反的约定

1. **选项只按单键读写**：读用 `_xz('key', $default)`，写用 `_sxz('key', $value)`。`xi_options` 是整包数组，**禁止整包覆盖**（会清掉其它字段）。
2. **文章级设置**用 `xi_post_meta()`（序列化在 `xi_post_meta`，定义在 `inc/options/metabox-options.php`），不要另起 meta key。
3. **新增 PHP 文件**要加进 `inc/inc.php` 的 `xi_require()` 列表，顺序按依赖排列。
4. **图标**统一 `xi_icon('name')`（内联 SVG，图标集在 `inc/functions/template-tags.php`），不要引外部图标库，除非确实需要品牌图标（用 `xi_brand_icon()`）。
5. **颜色 / 圆角 / 模糊**只能引用 CSS 变量（`--xi-main`、`--xi-radius`、`--xi-blur`、`--xi-glass`…），禁止在组件里写死品牌色。深浅色靠 `[data-theme="dark"]` 覆盖令牌，不在组件里写 `prefers-color-scheme`。
6. **玻璃效果只在有背景内容的位置用**：`.xi-glass` / `.xi-card` 必须叠加在 `.xi-backdrop`（Mica 色场）之上，否则 `backdrop-filter` 退化成灰色半透明块。新组件先问「它下面是什么」。
7. **资源版本自动处理**：`xi_asset_version()` 会把文件 mtime 追加到 CSS/JS 地址，改样式不需要手动升版本号。
8. **JS 不引框架**：原生 ES5 风格、IIFE 包裹；交互通过 `data-*` 属性约定（如 `data-toggle="search"`、`data-xi-sign`），事件委托 + AJAX 走 `wp_ajax_*` 并校验 `check_ajax_referer()`。
9. **输出转义**：`esc_html()` / `esc_attr()` / `esc_url()` / `wp_kses_post()`；后端权限用 `current_user_can()`，Ajax 一律 `check_ajax_referer(..., false)` 后返回 `wp_send_json_*`。
10. **文件必须是 UTF-8**（无 BOM）；注释和 UI 文案用中文，i18n 用 `__('…', 'xi')`。
11. **不要动** `inc/codestar-framework/`（第三方）与 `inc/codestar-framework/samples/`（已 gitignore，也不打包）。
12. **发布前**：PHP lint 全绿 → `CHANGELOG.md` 加一节 → `style.css` 的 `Version` 与 `functions.php` 的 `XI_BUILD` 同步（每次小修改 +5）→ `python build_xi.py` 出包。

## 设计系统速查

- 三层材质：Mica 色场 `.xi-backdrop` → 玻璃卡片 `.xi-glass` / `.xi-card` → 浮层菜单 / 弹窗（`--xi-glass-strong`）。
- 高光描边：`::before` + `mask-composite: exclude` 画 1px 渐变环，左上亮、右下暗。
- 控件映射：NavigationView → 顶部导航指示条；PipsPager → `.xi-pager`；InfoBar → `.xi-info`；SelectorBar → 登录弹窗标签；CommandBar → 编辑器工具栏。
- 完整令牌表、状态与动效规范：`docs/DESIGN-SYSTEM.md`。

## 文档索引

| 文档 | 内容 |
| --- | --- |
| `docs/ARCHITECTURE.md` | 模块地图、选项模型、数据表、模板层级、AJAX / 区块 / 缓存 |
| `docs/DESIGN-SYSTEM.md` | WinUI 3 × 液态玻璃的令牌、材质、组件与可访问性 |
| `docs/DEVELOPMENT.md` | 本地开发站、命令、打包、版本号、常见问题 |
| `CHANGELOG.md` | 版本变更记录（发版必更） |
