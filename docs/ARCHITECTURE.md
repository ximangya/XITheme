# 架构说明 · 曦主题

> 目标读者：要在这个主题里加功能、改样式或排查线上问题的人。读完后应能回答「一个请求从哪儿进来、数据存哪儿、要改哪个文件」。

## 1. 请求生命周期

```
WordPress
└─ functions.php（15 行，只做常量 + 加载）
   ├─ define XI_VERSION  ← style.css 的 Version
   ├─ define XI_BUILD    ← '3020'，内部构建号（每次小修改 +5）
   └─ require inc/inc.php
      ├─ xi_require([...48 个模块...])   ← 顺序 = 依赖顺序，见 inc/inc.php 文件头注释
      └─ do_action('xi_require_end')     ← 主题唯一的加载完成钩子
```

- 后台（`is_admin()`）时 `inc/options/options.php` 再补加载 `admin-options` / `metabox-options` / `profile-options` / `action`，并把 CSF 的 Font Awesome 换成主题内本地文件（`xi_csf_local_fa`，`inc/options/options.php`）。
- 前台装配都在 `inc/functions/functions.php`：

| 钩子 | 回调 | 作用 |
| --- | --- | --- |
| `after_setup_theme` | `xi_theme_setup()` (`:27`) | `add_theme_support`、菜单位置 `primary`/`footer`、文本域 |
| `wp_enqueue_scripts` | `xi_enqueue_scripts()` (`:43`) | `xi-main` CSS、`xi-main` JS、`xi-interact` |
| `style_loader_src` / `script_loader_src` | `xi_asset_version()` (`:65`) | 追加 `XI_VERSION.XI_BUILD.mtime` 破缓存 |
| `wp_head` 优先级 0 | `xi_theme_mode_script()` (`:107`) | 首帧前写 `data-theme`，防深浅色闪烁 |
| `wp_head` 优先级 99 | `xi_wp_head()` (`:120`) | 内联 CSS 变量、SEO/结构化数据等 |
| `wp_footer` 优先级 99 | `xi_wp_footer()` (`:141`) | 返回顶部、飘落、公告等挂载点 |
| `body_class` | `xi_body_class()` (`:153`) | 布局类（侧栏、透明头等） |

模板层级：标准 WordPress 模板文件在主题根目录；`single-xi_product.php` / `archive-xi_product.php` / `single-forum_post.php` / `archive-forum_post.php` / `taxonomy-plate.php` / `taxonomy-forum_topic.php` 对应商城与论坛的自定义类型；`user-center.php` / `write.php` / `bbs-new.php` 是分配给页面的专用模板（通过页面属性 `template` 选择，页面内容里用短代码或区块填充）。

## 2. 模块地图

`inc/inc.php` 的加载顺序就是模块地图，按层分组：

**基础设施**
`inc/dependent.php`（`_xz`/`_sxz`/`XI_OPTIONS`/主题色）→ `inc/functions/xi-safe.php`（应急安全入口）→ `inc/functions/xi-cache.php`（查询缓存）→ `inc/functions/functions.php`（装配/资源/内联 CSS）→ `inc/functions/optimize.php`（精简前台）→ `inc/functions/template-tags.php`（`xi_icon()` 等模板工具）。

**站点外观**
`xi-brand-icons`（社交图标）→ `xi-beautify`（字体、LOGO 扫光、动态标题、灵动岛、飘落）→ `xi-menu-icons` → `xi-slider` → `xi-header`（顶部多功能组件：搜索、通知、登录态）。

**用户与身份**
`xi-user`（用户信息/头像菜单）→ `xi-mail`（SMTP、邮件模板、验证码）→ `xi-sms`（阿里云/腾讯云/短信宝）→ `xi-sign`（登录/注册/找回）→ `xi-assets`（积分、余额、签到、卡密）→ `xi-vip`（VIP 等级）→ `xi-pay`（易支付/支付宝官方 + 支付订单表）→ `xi-hide`（`[xi_hide]` 付费/隐藏内容）→ `xi-usercenter` → `xi-auth`（用户组权限）→ `xi-webmaster`（站长身份组、管理员资料保护）→ `xi-ban`（封号/禁言/IP）→ `xi-profile`（前台账号设置）→ `xi-oauth`（QQ/微信/微博/GitHub/Gitee/彩虹聚合）→ `xi-author`（作者页）。

**内容与互动**
`xi-interact`（浏览/点赞/收藏/关注/分享/打赏/面包屑/公告）→ `xi-write`（前台投稿）→ `xi-blocks`（基础区块）→ `xi-blocks-extra`（扩展区块）。

**商城（发卡网模式）**
`shop/shop-core`（`xi_product` CPT、`product_cat`/`product_tag`、卡密库存与价格）→ `shop-pricing`（满减、优惠码、积分抵扣）→ `shop-order`（订单表；付款后自动发卡 / 自动发放下载内容）→ `shop-cart`（购物车、优惠码、评价）→ `shop-uc`（用户中心订单）→ `shop-admin`（后台商品 / 订单 / 统计 / 导出）→ `shop-widget`。实物、运费、收货地址、物流与售后流程已从界面移除，仅保留兼容函数。

**论坛**
`xi-bbs`（`forum_post` CPT、`plate`/`forum_topic` 分类、发帖、版主）。

**其它**
`xi-translate`（微软 Edge 免费翻译）、`xi-go`（外链跳转拦截）、`xi-ai`（AI 摘要）。

**CSF 与设置**
`inc/codestar-framework/`（第三方框架，勿改）→ `inc/options/options-module.php`（自定义复合字段，如幻灯片）→ `inc/options/options.php`（后台入口）→ `inc/csf-framework/`（主题对 CSF 小工具的扩展）→ `inc/widgets/`（小工具与侧栏位置）。

## 3. 选项与数据模型

### 主题设置（option）

- 全部主题设置存在**单个 option** `xi_options`（常量 `XI_OPTIONS`）里，是一个扁平大数组。
- 读：`_xz('key', $default)`；带子键：`_xz('key', $default, 'sub')`。
- 写：`_sxz('key', $value)` —— 只改单个键，**禁止**读出整包再 `update_option()` 回去，会覆盖其它字段。
- 字段定义在 `inc/options/admin-options.php`（2822 行，CSF 声明式数组），一级分组：`basic` 全局&功能、`page` 页面&显示、`post` 文章&列表、`user` 用户&互动、`bbs` 论坛社区。
- 主题色预设由 `xi_theme_color_presets()` 提供，`xi_theme_color()` 取当前色；`xi_inline_css()` 把 `--xi-main`、`--xi-radius`、`--xi-ctrl-radius`、`--xi-blur` 写到 `:root`。

### 文章级设置（post meta）

- 统一存进 **一个** meta key `xi_post_meta`，值是序列化数组（字段清单见 `inc/options/metabox-options.php` 的 `xi_post_meta_fields()`）。
- 读：`xi_post_meta('hide_sidebar', false, $post_id)`；新增字段只改 `xi_post_meta_fields()`，前后台自动同步。

### 用户数据（user meta）

| key | 含义 | 读取函数 |
| --- | --- | --- |
| `xi_points` | 积分 | `xi_user_points($user_id)` |
| `xi_balance` | 余额（两位小数） | `xi_user_balance($user_id)` |
| `xi_vip_level` / `xi_vip_exp` | VIP 等级与成长值 | `xi_vip_level()` / `xi_vip_exp()` |
| `xi_checkin_date` | 最近签到日期 | 签到模块 |
| `xi_logs_{type}` | 积分/余额流水（最多 100 条） | `xi_user_logs($user_id, $type)` |

### 自定义类型与数据表

- CPT：`xi_product`（商品，模板 `single-xi_product.php`）、`forum_post`（帖子，模板 `single-forum_post.php`）。
- 分类法：`product_cat` / `product_tag`（商品）、`plate`（版块）/ `forum_topic`（话题）（论坛）。
- 自建表（`dbDelta` 升级，升级逻辑挂在 `admin_init`）：
  - `xi_orders_table()` → `{prefix}xi_orders`：在线支付订单（`inc/functions/xi-pay.php`）。
  - `xi_shop_orders_table()` → `{prefix}xi_shop_orders`：商城订单（`inc/functions/shop/shop-order.php`，含金额明细、卡密交付内容、商家备注，首次访问自动补列）。
- 购物车 / 优惠码等轻量数据走 session / user meta / option，不建表；收货地址字段仅保留兼容，发卡网模式不再使用。

### 站长身份组（`xi_webmaster`）

- 角色由 `inc/functions/xi-webmaster.php` 维护，开关是主题设置 `webmaster_role_s`（默认关闭）。
- 能力不写死：`xi_webmaster_caps()` 每次从 `administrator` 角色复制，`init` 时做差异同步，所以插件给管理员新增的能力会自动出现在站长组。
- 生命周期：开关打开 → `init` 保证角色存在且能力最新；开关关闭 → 该角色成员全部 `add_role('administrator')` + `remove_role('xi_webmaster')`，再删除角色；主题被切换（`switch_theme`）时同样还原，避免主题被移除后留下孤立的高权限角色。
- 分配方式：后台用户列表/用户编辑页把角色改为「站长」，前后台身份标识（`xi_user_badges()`）会显示金色「站长」徽章（`.xi-role.is-xi_webmaster`）。
- 注意：WordPress 不允许删除启用中的主题，所以「卸载」在实践上等于切换主题；如果直接删文件绕过 WordPress，`switch_theme` 不会触发，需要手动把成员改回管理员。

### 管理员资料保护

- 开关 `admin_profile_lock_s`（默认关闭），实现是 `map_meta_cap` 过滤器：当 `edit_user` 的操作者与被操作者都属于 `administrator` / `xi_webmaster` 且不是同一人时，追加 `do_not_allow`。
- 站长（`xi_webmaster`）不受该限制，可以编辑管理员与其他站长；限制只作用于管理员之间。超级管理员（多站点）同样不受限。
- 对后台用户编辑、REST API、任何 `current_user_can('edit_user', $id)` 判断都生效。
- 另外在 `user-edit.php` 上做了中文提示守卫，直接访问时给出「操作被拒绝」页面而不是核心的英文提示（站长同样放行）。

## 4. 前端契约（JS ↔ PHP）

- 无构建、无框架：`assets/js/*.js` 是 IIFE 包裹的原生 ES5，靠 `data-*` 属性绑定行为，事件委托挂在 `document` 上。
- 本地化对象：`wp_localize_script()` 注入 `xiData`（ajax url、nonce、登录态、文案），商城另有 `xiShop`。
- Ajax 约定：动作名 `xi_*`，注册 `wp_ajax_{action}` / `wp_ajax_nopriv_{action}`；处理函数内先 `check_ajax_referer()`，再 `wp_send_json_success/error`。
- 需要登录态的动作（典型）：`xi_like`、`xi_favorite`、`xi_follow`、`xi_view`、`xi_hide_password`、`xi_bbs_follow`；开放动作：`xi_login`、`xi_register`、`xi_lostpwd`、`xi_captcha`、`xi_send_code`、`xi_sign_nonce`、`xi_pay_notify`。
- 支付回调 `xi_pay_notify` 不走 nonce，靠签名校验，改动时务必保持幂等。

## 5. 区块系统

- 全部是**动态区块**，`register_block_type('xi/{name}', [...])` + `render_callback`，PHP 侧渲染，无 `block.json`、无 JS 编译。
- 基础区块（`xi-blocks.php`）：`xi/alert` 提醒框、`xi/heading` 标题、`xi/download` 文件下载（多线路 + 登录门槛）、`xi/quote` 引言、`xi/code` 高亮代码、`xi/button` 按钮、`xi/collapse` 折叠框、`xi/hide` 隐藏内容（登录/积分/VIP/密码/用户组/时间）、`xi/tabs` + `xi/tab` 选项卡。
- 扩展区块（`xi-blocks-extra.php`，21 个）：商品卡片、价格方案、优惠券、购买后可见、步骤、时间轴、视频等。
- 商城关闭（`xi_shop_enabled()` 为 false）时，`group=shop` 的 8 个区块不注册，编辑器与前台投稿面板都不会出现；对应短代码仍然注册，渲染返回空内容，旧文章里不会露出裸短代码。
- 编辑器侧样式在 `assets/css/blocks-editor.css`，前台/编辑器共用 `assets/css/blocks.css`；`main.css` 已移除 `wp-block-library`，核心区块样式由主题自绘。
- 新增区块：在对应文件的 `$blocks` 数组加一项（属性用 `$str()/$int()/$bool()` 声明）+ 写 `xi_block_render_{name}()` 渲染函数 + 在 `blocks.css` 加样式。区块属性变更会触发「区块验证失败」，改结构时记得写 `deprecated` 或保持属性向后兼容。

## 6. 缓存

`inc/functions/xi-cache.php` 提供薄封装（后台可整体关闭）：

- `xi_cache_remember($key, $ttl, $callback)`：读缓存，未命中则执行回调并写入。
- `xi_cache_fragment($key, $ttl, $render)`：缓存一段 HTML 片段（小工具、相关文章、统计）。
- `xi_cache_flush()` 清全部；`xi_cache_flush_on_post($post_id)` 在文章保存/评论时按 key 失效；`xi_cache_flush_on_option($option)` 在设置保存时失效。
- 缓存键由 `xi_cache_key()` 加前缀与版本号（`xi_cache_ver()`），改缓存结构时递增版本即可整体失效。

注意：用户相关数据（积分、登录态、点赞状态）**不能**进整页/片段缓存，否则会出现「串号」。

## 7. 安全与运维

- `inc/functions/xi-safe.php`：`wp-login.php?xi_safe=<密钥>` 应急通道、`XI_SAFE_MODE` 常量可临时关掉主题的危险模块（用于主题报错时自救）。
- `inc/functions/xi-ban.php`：封号、禁言、IP 封禁；判断集中在 `xi_user_banned()` / `xi_ip_banned()`，新功能要尊重这两个判断。
- `inc/functions/xi-auth.php`：按用户组的能力矩阵，模板里用 `xi_user_can()` 而不是直接 `current_user_can()`。
- `inc/functions/optimize.php`：精简前台（去 emoji、去 oEmbed 等），部分开关在设置里。

## 8. 扩展点

- 加载完成：`do_action('xi_require_end')`（子主题或插件在此追加模块）。
- 支付成功：`xi_pay.php` / `shop-order.php` 内有订单状态流转钩子（`xi_order_paid` 一类），接第三方发卡/通知优先挂这里，不要改支付回调本身。
- 模板覆盖：所有模板与 `template-parts/` 都可用子主题同名文件覆盖；优先用 filter（如 `xi_body_class`、`the_content` 优先级）而不是改核心文件。
- 样式覆盖：子主题 `style.css` 里重写 CSS 变量即可换肤，不必覆盖 `main.css`。
