<?php
/**
 * 文件加载
 *
 * functions.php
 * └─ inc/inc.php
 *    ├─ inc/dependent
 *    ├─ inc/functions/xi-safe                          后台安全入口与应急面板（尽量早加载，只依赖核心函数）
 *    ├─ inc/codestar-framework/codestar-framework      原始 CSF
 *    ├─ inc/options/options-module                     复合字段（幻灯片）
 *    ├─ inc/options/options                            主题设置
 *    ├─ inc/csf-framework/classes/widget-options.class 曦主题扩展层（CSF 小工具类）
 *    ├─ inc/widgets/widget-class                       小工具注册层
 *    ├─ inc/functions/xi-cache                         查询缓存（小工具、相关文章、统计）
 *    ├─ inc/functions/functions
 *    ├─ inc/functions/optimize
 *    ├─ inc/functions/template-tags
 *    ├─ inc/functions/xi-brand-icons                   社交图标 xi_brand_icon()、页脚
 *    ├─ inc/functions/xi-beautify                      站点美化：字体、LOGO 扫光、动态标题、灵动岛、飘落
 *    ├─ inc/functions/xi-menu-icons                    菜单图标
 *    ├─ inc/functions/xi-slider                        幻灯片渲染 xi_new_slider()
 *    ├─ inc/functions/xi-header                        顶部多功能组件
 *    ├─ inc/functions/xi-user                          用户信息与头像菜单
 *    ├─ inc/functions/xi-mail                          SMTP、邮件模板、邮箱验证码
 *    ├─ inc/functions/xi-sms                           短信：阿里云、腾讯云、短信宝
 *    ├─ inc/functions/xi-sign                          前台登录 / 注册 / 找回密码
 *    ├─ inc/functions/xi-assets                        积分、余额、签到、卡密
 *    ├─ inc/functions/xi-pay                           在线支付：易支付、支付宝官方、订单
 *    ├─ inc/functions/xi-vip                           VIP 会员
 *    ├─ inc/functions/xi-hide                          隐藏 / 付费内容 [xi_hide]
 *    ├─ inc/functions/xi-blocks                        编辑器区块：提醒框、标题、文件下载、折叠框、隐藏内容、选项卡
 *    ├─ inc/functions/xi-blocks-extra                  扩展区块 21 个：商品卡片、价格方案、优惠券、购买后可见、步骤、时间轴、视频等
 *    ├─ inc/functions/xi-usercenter                    用户中心与资产 Ajax
 *    ├─ inc/functions/xi-auth                          权限管理（按用户组）
 *    ├─ inc/functions/xi-ban                           用户封号 / 禁言、IP 封禁
 *    ├─ inc/functions/xi-translate                     多语言翻译（微软 Edge 免费翻译）
 *    ├─ inc/functions/xi-go                            外链安全跳转
 *    ├─ inc/functions/xi-ai                            AI 文章摘要（自定义接口）
 *    ├─ inc/functions/xi-author                        个人主页（作者页）与用户搜索
 *    ├─ inc/functions/xi-interact                      浏览、点赞、收藏、关注、分享、打赏、面包屑、公告
 *    ├─ inc/functions/xi-profile                       前台账号设置（资料、头像、邮箱、密码）
 *    ├─ inc/functions/xi-oauth                         第三方登录：QQ、微信、微博、GitHub、Gitee、彩虹聚合登录
 *    ├─ inc/functions/xi-write                         前台投稿 / 编辑
 *    ├─ inc/functions/xi-bbs                           论坛社区：版块、帖子、话题、发帖、版主管理
 *    ├─ inc/functions/shop/shop-core                   商城：商品类型、价格、库存、路由
 *    ├─ inc/functions/shop/shop-pricing                商城结算：满减、优惠码、积分抵扣、运费
 *    ├─ inc/functions/shop/shop-order                  商城订单：下单、付款、发货、售后、定时任务
 *    ├─ inc/functions/shop/shop-cart                   购物车、收货地址、优惠码、评价、前台 Ajax
 *    ├─ inc/functions/shop/shop-uc                     用户中心：商城订单、收货地址
 *    ├─ inc/functions/shop/shop-admin                  商城后台：商品设置、订单管理、统计、导出、设置字段
 *    ├─ inc/functions/shop/shop-widget                 商城小工具
 *    ├─ inc/widgets/widget-index                       侧栏位置与模块
 *    └─ do_action('xi_require_end')
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 批量引入主题文件
 *
 * @param array|string $files 不带 .php 后缀的相对路径
 * @param string       $dir   相对主题根目录的前缀目录
 */
function xi_require($files, $dir = '')
{
    foreach ((array) $files as $file) {
        $path = get_theme_file_path($dir . $file . '.php');
        if (file_exists($path)) {
            require_once $path;
        }
    }
}

xi_require(array(
    'inc/dependent',
    'inc/functions/xi-safe',
    'inc/codestar-framework/codestar-framework',
    'inc/options/options-module',
    'inc/options/options',
    'inc/csf-framework/classes/widget-options.class',
    'inc/widgets/widget-class',
    'inc/functions/xi-cache',
    'inc/functions/functions',
    'inc/functions/optimize',
    'inc/functions/template-tags',
    'inc/functions/xi-brand-icons',
    'inc/functions/xi-beautify',
    'inc/functions/xi-menu-icons',
    'inc/functions/xi-slider',
    'inc/functions/xi-header',
    'inc/functions/xi-user',
    'inc/functions/xi-mail',
    'inc/functions/xi-sms',
    'inc/functions/xi-sign',
    'inc/functions/xi-assets',
    'inc/functions/xi-vip',
    'inc/functions/xi-pay',
    'inc/functions/xi-hide',
    'inc/functions/xi-blocks',
    'inc/functions/xi-blocks-extra',
    'inc/functions/xi-usercenter',
    'inc/functions/xi-auth',
    'inc/functions/xi-ban',
    'inc/functions/xi-translate',
    'inc/functions/xi-go',
    'inc/functions/xi-ai',
    'inc/functions/xi-profile',
    'inc/functions/xi-oauth',
    'inc/functions/xi-author',
    'inc/functions/xi-interact',
    'inc/functions/xi-write',
    'inc/functions/xi-bbs',
    'inc/functions/shop/shop-core',
    'inc/functions/shop/shop-pricing',
    'inc/functions/shop/shop-order',
    'inc/functions/shop/shop-cart',
    'inc/functions/shop/shop-uc',
    'inc/functions/shop/shop-admin',
    'inc/functions/shop/shop-widget',
    'inc/widgets/widget-index',
));

do_action('xi_require_end');
