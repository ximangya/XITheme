<?php
/**
 * 主题设置页
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_csf_admin_options()
{
    if (!class_exists('CSF')) {
        return;
    }

    $prefix = XI_OPTIONS;

    // 非设置页、非本设置的 Ajax 请求，不强行保存默认值
    $no_create = (
        (wp_doing_ajax() && (empty($_POST['action']) || !strstr($_POST['action'], 'csf_' . $prefix)))
        ||
        (!wp_doing_ajax() && (empty($_GET['page']) || $_GET['page'] !== $prefix))
    );

    CSF::createOptions($prefix, array(
        'menu_title'         => __('曦主题设置', 'xi'),
        'menu_slug'          => $prefix,
        'menu_icon'          => 'dashicons-palmtree',
        'framework_title'    => __('曦主题', 'xi') . ' <small>' . XI_VERSION . ' · ' . XI_BUILD . '</small>',
        'show_in_customizer' => false,
        'save_defaults'      => !$no_create,
        'footer_text'        => sprintf(__('淡白 · 青薄荷 —— 曦主题 %s', 'xi'), XI_VERSION),
        'footer_credit'      => '<i class="fa fa-fw fa-leaf" aria-hidden="true"></i> ',
        'theme'              => 'light',
    ));

    /* ---------- 一级分组 ---------- */

    CSF::createSection($prefix, array(
        'id'    => 'basic',
        'title' => __('全局&功能', 'xi'),
        'icon'  => 'fa fa-fw fa-bullseye',
    ));

    CSF::createSection($prefix, array(
        'id'    => 'page',
        'title' => __('页面&显示', 'xi'),
        'icon'  => 'fa fa-fw fa-calendar-check-o',
    ));

    CSF::createSection($prefix, array(
        'id'    => 'post',
        'title' => __('文章&列表', 'xi'),
        'icon'  => 'fa fa-fw fa-map-o',
    ));

    CSF::createSection($prefix, array(
        'id'    => 'user',
        'title' => __('用户&互动', 'xi'),
        'icon'  => 'fa fa-fw fa-user-o',
    ));

    CSF::createSection($prefix, array(
        'id'     => 'bbs',
        'title'  => __('论坛社区', 'xi'),
        'icon'   => 'fa fa-fw fa-comments-o',
        'fields' => array(
            array(
                'id'      => 'bbs_s',
                'type'    => 'switcher',
                'title'   => __('启用论坛', 'xi'),
                'desc'    => __('开启后后台出现「论坛」菜单：先在 论坛 → 版块 中建分区和版块（顶级为分区，下级为版块），再把论坛首页地址加到导航菜单。关闭后论坛页面与数据都会隐藏，但不会删除', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'type'       => 'content',
                'title'      => __('论坛地址', 'xi'),
                'content'    => function_exists('xi_bbs_url') ? '<code>' . esc_html(xi_bbs_url()) . '</code> <span class="description">' . esc_html__('发帖页：', 'xi') . '</span><code>' . esc_html(xi_bbs_url('new')) . '</code>' : '',
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'id'         => 'bbs_name',
                'type'       => 'text',
                'title'      => __('论坛名称', 'xi'),
                'default'    => __('社区论坛', 'xi'),
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'id'         => 'bbs_desc',
                'type'       => 'text',
                'title'      => __('论坛简介', 'xi'),
                'default'    => __('交流、分享、提问，在这里遇见同好', 'xi'),
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'id'         => 'bbs_slug',
                'type'       => 'text',
                'title'      => __('地址前缀', 'xi'),
                'desc'       => __('只能用小写字母、数字和短横线，例如 forum、bbs、community；修改后旧链接会失效', 'xi'),
                'default'    => 'forum',
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'id'         => 'bbs_sort',
                'type'       => 'button_set',
                'title'      => __('默认排序', 'xi'),
                'default'    => 'reply',
                'options'    => array(
                    'reply' => __('最新回复', 'xi'),
                    'new'   => __('最新发布', 'xi'),
                    'hot'   => __('热门', 'xi'),
                ),
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'id'         => 'bbs_per_page',
                'type'       => 'spinner',
                'title'      => __('每页帖子数', 'xi'),
                'min'        => 5,
                'max'        => 60,
                'default'    => 20,
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'id'         => 'bbs_search',
                'type'       => 'switcher',
                'title'      => __('站内搜索包含帖子', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'type'       => 'subheading',
                'content'    => __('发帖', 'xi'),
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'type'       => 'notice',
                'style'      => 'info',
                'content'    => __('哪些用户组可以发帖、是否免审核，在「权限管理 → 论坛」中设置；上传图片沿用「上传图片」权限和投稿的图片大小限制', 'xi'),
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'id'         => 'bbs_daily_limit',
                'type'       => 'spinner',
                'title'      => __('每日发帖上限', 'xi'),
                'desc'       => __('0 为不限制；「不受每日投稿上限」的用户组同样不受此限制', 'xi'),
                'min'        => 0,
                'max'        => 200,
                'default'    => 10,
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'id'         => 'bbs_min_length',
                'type'       => 'spinner',
                'title'      => __('正文最少字数', 'xi'),
                'desc'       => __('只发图片时不受限制', 'xi'),
                'min'        => 0,
                'max'        => 500,
                'default'    => 5,
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'id'         => 'bbs_topic_create',
                'type'       => 'switcher',
                'title'      => __('用户可新建话题', 'xi'),
                'desc'       => __('关闭后发帖只能选择后台已有的话题', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'id'         => 'bbs_user_delete',
                'type'       => 'switcher',
                'title'      => __('作者可删除自己的帖子', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('bbs_s', '!=', ''),
                'id'         => 'bbs_notice',
                'type'       => 'textarea',
                'title'      => __('发帖须知', 'xi'),
                'desc'       => __('显示在发帖页右侧，留空不显示', 'xi'),
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'id'     => 'auth',
        'title'  => __('权限管理', 'xi'),
        'icon'   => 'fa fa-fw fa-shield',
        'fields' => xi_auth_option_fields(),
    ));

    CSF::createSection($prefix, array(
        'id'    => 'over',
        'title' => __('扩展&增强', 'xi'),
        'icon'  => 'fa fa-fw fa-puzzle-piece',
    ));

    /* ---------- 全局&功能 ---------- */

    CSF::createSection($prefix, array(
        'parent' => 'basic',
        'title'  => __('基础设置', 'xi'),
        'icon'   => 'fa fa-fw fa-paint-brush',
        'fields' => array(
            array(
                'id'      => 'logo',
                'type'    => 'upload',
                'title'   => __('LOGO', 'xi'),
                'desc'    => __('留空则显示站点标题，建议高度 80px 的透明 PNG', 'xi'),
                'library' => 'image',
                'preview' => true,
            ),
            array(
                'id'      => 'favicon',
                'type'    => 'upload',
                'title'   => __('浏览器图标', 'xi'),
                'library' => 'image',
                'preview' => true,
            ),
            array(
                'id'      => 'theme_color_preset',
                'type'    => 'palette',
                'title'   => __('主题色', 'xi'),
                'desc'    => __('依次为：青薄荷、天空蓝、靛青、紫罗兰、樱花粉、珊瑚橙、琥珀黄、石墨灰；最后一个彩色块为自定义', 'xi'),
                'class'   => 'xi-color-palette',
                'default' => 'custom',
                'options' => array_merge(
                    array_map(function ($color) {
                        return array($color);
                    }, xi_theme_color_presets()),
                    array('custom' => array('#f0659b', '#e9a321', '#2bb39a', '#2f8fe8'))
                ),
            ),
            array(
                'dependency' => array('theme_color_preset', '==', 'custom'),
                'id'         => 'theme_color',
                'type'       => 'color',
                'title'      => __('自定义主题色', 'xi'),
                'desc'       => __('默认青薄荷 #2bb39a；按钮、链接、高亮、背景色场都由它派生', 'xi'),
                'default'    => '#2bb39a',
            ),
            array(
                'id'      => 'theme_color_dark_s',
                'type'    => 'switcher',
                'title'   => __('深色模式单独配色', 'xi'),
                'desc'    => __('关闭时深色模式沿用上面的主题色', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('theme_color_dark_s', '!=', ''),
                'id'         => 'theme_color_dark',
                'type'       => 'color',
                'title'      => __('深色模式主题色', 'xi'),
                'desc'       => __('深色背景上建议用稍亮、饱和度稍低的颜色', 'xi'),
                'default'    => '#3cc7ad',
            ),
            array(
                'id'      => 'theme_mode',
                'type'    => 'button_set',
                'title'   => __('默认深浅色', 'xi'),
                'default' => 'auto',
                'options' => array(
                    'light' => __('浅色', 'xi'),
                    'dark'  => __('深色', 'xi'),
                    'auto'  => __('跟随系统', 'xi'),
                ),
            ),
            array(
                'id'      => 'radius',
                'type'    => 'slider',
                'title'   => __('圆角大小', 'xi'),
                'desc'    => __('前台卡片使用该值，按钮与输入框按比例缩小；后台设置页同步', 'xi'),
                'min'     => 0,
                'max'     => 28,
                'unit'    => 'px',
                'default' => 16,
            ),
            array(
                'id'      => 'glass_blur',
                'type'    => 'slider',
                'title'   => __('液态玻璃模糊度', 'xi'),
                'desc'    => __('0 为关闭模糊', 'xi'),
                'min'     => 0,
                'max'     => 48,
                'step'    => 2,
                'unit'    => 'px',
                'default' => 24,
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'basic',
        'title'  => __('SEO优化', 'xi'),
        'icon'   => 'fa fa-fw fa-search',
        'fields' => array(
            array(
                'id'      => 'seo_s',
                'type'    => 'switcher',
                'title'   => __('输出 SEO 标签', 'xi'),
                'desc'    => __('如已使用其他 SEO 插件请关闭', 'xi'),
                'default' => true,
            ),
            array(
                'dependency' => array('seo_s', '!=', ''),
                'id'         => 'seo_keywords',
                'type'       => 'text',
                'title'      => __('首页关键词', 'xi'),
                'desc'       => __('用英文逗号分隔', 'xi'),
                'default'    => '博客,学习笔记,读书,前端',
            ),
            array(
                'dependency' => array('seo_s', '!=', ''),
                'id'         => 'seo_description',
                'type'       => 'textarea',
                'title'      => __('首页描述', 'xi'),
                'desc'       => __('留空使用站点副标题', 'xi'),
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'basic',
        'title'  => __('功能优化', 'xi'),
        'icon'   => 'fa fa-fw fa-rocket',
        'fields' => array(
            array(
                'id'      => 'cache_s',
                'type'    => 'switcher',
                'title'   => __('查询缓存', 'xi'),
                'desc'    => __('缓存侧栏热门文章、最新评论、标签云、相关文章、用户统计、版块今日帖数等查询结果，减少每次访问的数据库查询。内容或设置有变化时自动刷新。装了 Redis / Memcached 对象缓存时效果更好', 'xi'),
                'default' => true,
            ),
            array(
                'dependency' => array('cache_s', '!=', ''),
                'type'       => 'callback',
                'function'   => 'xi_cache_tools_field',
            ),
            array(
                'id'      => 'modern_front_s',
                'type'    => 'switcher',
                'title'   => __('精简 WordPress 前台', 'xi'),
                'desc'    => __('移除 Emoji 脚本、版本号等头部标签、区块库与全局样式、oEmbed、旧式内联样式和自带小工具，区块样式由主题提供', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'hide_admin_bar',
                'type'    => 'switcher',
                'title'   => __('前台隐藏管理工具栏', 'xi'),
                'desc'    => __('可通过右上角头像菜单进入后台', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'avatar_source',
                'type'    => 'button_set',
                'title'   => __('头像来源', 'xi'),
                'desc'    => __('没有上传头像的用户使用的头像服务。国内站点推荐 Cravatar 或 WeAvatar；选择「本地默认」则不请求任何外部服务，全部显示默认头像。用户上传的头像、第三方登录头像始终优先', 'xi'),
                'default' => 'cravatar',
                'options' => array(
                    'cravatar' => 'Cravatar',
                    'weavatar' => 'WeAvatar',
                    'gravatar' => 'Gravatar',
                    'local'    => __('本地默认', 'xi'),
                ),
            ),
            array(
                'id'      => 'avatar_default_img',
                'type'    => 'upload',
                'title'   => __('默认头像', 'xi'),
                'library' => 'image',
                'desc'    => __('留空使用主题自带的默认头像。头像图片加载失败时也会自动换成它；搭配外部头像服务时请上传公网可访问的 JPG / PNG', 'xi'),
            ),
        ),
    ));

    /* ---------- 页面&显示 ---------- */

    CSF::createSection($prefix, array(
        'parent' => 'basic',
        'title'  => __('多语言翻译', 'xi'),
        'icon'   => 'fa fa-fw fa-language',
        'fields' => array(
            array(
                'id'      => 'translate_s',
                'type'    => 'switcher',
                'title'   => __('启用多语言翻译', 'xi'),
                'desc'    => __('导航栏明暗切换按钮左边显示语言选择按钮，访客选择后整页机器翻译。使用微软翻译免费接口（与 Edge 浏览器网页翻译同源），在访客浏览器中直接请求，不消耗服务器资源，无需申请密钥；译文缓存在访客本地，再次访问秒切换', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('translate_s', '!=', ''),
                'id'         => 'translate_source',
                'type'       => 'select',
                'title'      => __('网站原文语言', 'xi'),
                'desc'       => __('始终显示在语言菜单第一项，选择它即恢复原文', 'xi'),
                'default'    => 'zh-Hans',
                'options'    => xi_translate_catalog_options(),
            ),
            array(
                'dependency'   => array('translate_s', '!=', ''),
                'id'           => 'translate_langs',
                'type'         => 'group',
                'title'        => __('可选语言', 'xi'),
                'desc'         => __('按顺序显示在语言菜单中，可拖动排序；与原文相同或重复的语言会自动忽略', 'xi'),
                'button_title' => __('添加语言', 'xi'),
                'accordion_title_by' => 'code',
                'default'      => xi_translate_default_langs(),
                'fields'       => array(
                    array(
                        'id'      => 'code',
                        'type'    => 'select',
                        'title'   => __('语言', 'xi'),
                        'options' => xi_translate_catalog_options(),
                        'default' => 'en',
                    ),
                    array(
                        'id'    => 'name',
                        'type'  => 'text',
                        'title' => __('显示名称', 'xi'),
                        'desc'  => __('留空使用该语言自己的名称，如 English、日本語', 'xi'),
                    ),
                ),
            ),
            array(
                'dependency' => array('translate_s', '!=', ''),
                'id'         => 'translate_auto',
                'type'       => 'switcher',
                'title'      => __('跟随浏览器语言', 'xi'),
                'desc'       => __('访客首次访问且没有手动选过语言时，如果浏览器语言在可选语言中，自动翻译为该语言', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('translate_s', '!=', ''),
                'id'         => 'translate_ignore',
                'type'       => 'text',
                'title'      => __('不翻译的元素', 'xi'),
                'desc'       => __('CSS 选择器，多个用英文逗号分隔。代码块、输入框已默认排除；也可以给任意元素加 class="notranslate"', 'xi'),
                'default'    => '.xi-logo',
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'basic',
        'title'  => __('外链跳转', 'xi'),
        'icon'   => 'fa fa-fw fa-external-link',
        'fields' => array(
            array(
                'id'      => 'go_s',
                'type'    => 'switcher',
                'title'   => __('外链安全跳转', 'xi'),
                'desc'    => __('文章、评论里的站外链接先打开「即将离开本站」提示页，访客确认后再前往，并自动加上 nofollow。跳转链接带签名，不会被别人拿来当跳板', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('go_s', '!=', ''),
                'id'         => 'go_scope',
                'type'       => 'checkbox',
                'title'      => __('作用范围', 'xi'),
                'inline'     => true,
                'default'    => array('content', 'comment', 'author'),
                'options'    => array(
                    'content' => __('文章 / 页面 / 帖子正文', 'xi'),
                    'comment' => __('评论内容', 'xi'),
                    'author'  => __('评论者网站', 'xi'),
                ),
            ),
            array(
                'dependency' => array('go_s', '!=', ''),
                'id'         => 'go_whitelist',
                'type'       => 'textarea',
                'title'      => __('白名单域名', 'xi'),
                'desc'       => __('这些域名（含子域名）直接打开、不经过提示页，每行一个或用逗号分隔，例如 github.com。本站域名自动包含', 'xi'),
                'attributes' => array('rows' => 4, 'placeholder' => "github.com\nbilibili.com"),
            ),
            array(
                'dependency' => array('go_s', '!=', ''),
                'id'         => 'go_wait',
                'type'       => 'spinner',
                'title'      => __('自动跳转', 'xi'),
                'desc'       => __('提示页停留几秒后自动前往，0 为不自动跳转、需要访客点击「继续访问」', 'xi'),
                'unit'       => __('秒', 'xi'),
                'min'        => 0,
                'max'        => 30,
                'default'    => 0,
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'page',
        'title'  => __('顶部导航', 'xi'),
        'icon'   => 'fa fa-fw fa-window-maximize',
        'fields' => array(
            array(
                'id'      => 'header_sticky',
                'type'    => 'switcher',
                'title'   => __('导航栏吸顶', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'header_search',
                'type'    => 'switcher',
                'title'   => __('搜索按钮', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'search_user_s',
                'type'    => 'switcher',
                'title'   => __('搜索用户', 'xi'),
                'desc'    => __('搜索结果页增加「用户」标签，按昵称搜索站内用户并进入其个人主页', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'theme_switch_s',
                'type'    => 'switcher',
                'title'   => __('深浅色切换按钮', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'header_user_s',
                'type'    => 'switcher',
                'title'   => __('用户头像菜单', 'xi'),
                'desc'    => __('右上角显示头像，点击弹出用户菜单；未登录时显示登录入口', 'xi'),
                'default' => true,
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'page',
        'title'  => __('顶部多功能组件', 'xi'),
        'icon'   => 'fa fa-fw fa-picture-o',
        'fields' => array(
            array(
                'id'      => 'header_slider_s',
                'type'    => 'switcher',
                'title'   => __('启用顶部幻灯片', 'xi'),
                'desc'    => __('未添加幻灯片时显示站点名称与副标题', 'xi'),
                'default' => true,
            ),
            array(
                'dependency' => array('header_slider_s', '!=', ''),
                'id'         => 'header_slider_show',
                'type'       => 'checkbox',
                'title'      => __('显示范围', 'xi'),
                'inline'     => true,
                'default'    => array('home'),
                'options'    => array(
                    'home'   => __('首页', 'xi'),
                    'single' => __('文章页', 'xi'),
                    'page'   => __('页面', 'xi'),
                    'cat'    => __('分类页', 'xi'),
                    'tag'    => __('标签页', 'xi'),
                    'search' => __('搜索页', 'xi'),
                    'bbs'    => __('论坛', 'xi'),
                ),
            ),
            array(
                'dependency' => array('header_slider_s', '!=', ''),
                'id'         => 'header_slider_show_type',
                'type'       => 'button_set',
                'title'      => __('显示设备', 'xi'),
                'default'    => 'all',
                'options'    => array(
                    'all'     => __('全部', 'xi'),
                    'only_pc' => __('仅PC端', 'xi'),
                    'only_sm' => __('仅移动端', 'xi'),
                ),
            ),
            array(
                'dependency' => array('header_slider_s', '!=', ''),
                'id'         => 'header_slider_layout',
                'type'       => 'button_set',
                'title'      => __('布局', 'xi'),
                'desc'       => __('通栏：铺满屏幕宽度，导航栏悬浮其上；卡片：限制在内容宽度内', 'xi'),
                'default'    => 'full',
                'options'    => array(
                    'full' => __('通栏', 'xi'),
                    'box'  => __('卡片', 'xi'),
                ),
            ),
            array_merge(XI_CFS_Module::add_slider('header_slider'), array('dependency' => array('header_slider_s', '!=', ''))),
            array_merge(XI_CFS_Module::slide('header_slider_option'), array('dependency' => array('header_slider_s', '!=', ''))),
            array(
                'dependency' => array('header_slider_s', '!=', ''),
                'id'         => 'header_slider_search_s',
                'type'       => 'switcher',
                'title'      => __('叠加搜索框', 'xi'),
                'default'    => false,
            ),
            array(
                'dependency' => array(array('header_slider_s', '!=', ''), array('header_slider_search_s', '!=', '')),
                'id'         => 'header_slider_search_option',
                'type'       => 'fieldset',
                'title'      => __('搜索框设置', 'xi'),
                'fields'     => array(
                    array(
                        'id'      => 'placeholder',
                        'type'    => 'text',
                        'title'   => __('占位文字', 'xi'),
                        'default' => __('搜索你感兴趣的内容…', 'xi'),
                    ),
                    array(
                        'id'    => 'keywords',
                        'type'  => 'text',
                        'title' => __('热门关键词', 'xi'),
                        'desc'  => __('用逗号分隔，移动端不显示', 'xi'),
                    ),
                    array(
                        'id'       => 'before_html',
                        'type'     => 'textarea',
                        'title'    => __('搜索框上方内容', 'xi'),
                        'desc'     => __('支持 HTML', 'xi'),
                        'sanitize' => false,
                    ),
                    array(
                        'id'       => 'after_html',
                        'type'     => 'textarea',
                        'title'    => __('搜索框下方内容', 'xi'),
                        'desc'     => __('支持 HTML', 'xi'),
                        'sanitize' => false,
                    ),
                ),
            ),
            array(
                'dependency' => array('header_slider_s', '!=', ''),
                'id'         => 'header_slider_btn_s',
                'type'       => 'switcher',
                'title'      => __('按钮组', 'xi'),
                'desc'       => __('在幻灯片下方（或叠加在幻灯片上）放一排快捷按钮', 'xi'),
                'default'    => false,
            ),
            array(
                'dependency' => array(array('header_slider_s', '!=', ''), array('header_slider_btn_s', '!=', '')),
                'id'         => 'header_slider_btn_pos',
                'type'       => 'button_set',
                'title'      => __('按钮位置', 'xi'),
                'default'    => 'below',
                'options'    => array(
                    'below'   => __('幻灯片下方', 'xi'),
                    'overlay' => __('叠加在幻灯片中部（搜索框下方）', 'xi'),
                ),
            ),
            array(
                'dependency' => array(array('header_slider_s', '!=', ''), array('header_slider_btn_s', '!=', '')),
                'id'         => 'header_slider_btn_shape',
                'type'       => 'button_set',
                'title'      => __('按钮形状', 'xi'),
                'default'    => 'pill',
                'options'    => array(
                    'pill'   => __('胶囊', 'xi'),
                    'round'  => __('圆角', 'xi'),
                    'square' => __('小圆角', 'xi'),
                ),
            ),
            array(
                'dependency' => array(array('header_slider_s', '!=', ''), array('header_slider_btn_s', '!=', '')),
                'id'         => 'header_slider_btn_align',
                'type'       => 'button_set',
                'title'      => __('对齐方式', 'xi'),
                'default'    => 'center',
                'options'    => array(
                    'center' => __('居中', 'xi'),
                    'left'   => __('居左', 'xi'),
                ),
            ),
            array(
                'dependency'   => array(array('header_slider_s', '!=', ''), array('header_slider_btn_s', '!=', '')),
                'id'           => 'header_slider_btns',
                'type'         => 'group',
                'title'        => __('按钮', 'xi'),
                'button_title' => __('添加按钮', 'xi'),
                'fields'       => array(
                    array(
                        'id'    => 'text',
                        'type'  => 'text',
                        'title' => __('文字', 'xi'),
                    ),
                    array(
                        'id'    => 'desc',
                        'type'  => 'text',
                        'title' => __('副标题', 'xi'),
                        'desc'  => __('显示在文字下方的一行小字，可留空；「卡片」样式下效果最好', 'xi'),
                    ),
                    array(
                        'id'      => 'action',
                        'type'    => 'select',
                        'title'   => __('点击动作', 'xi'),
                        'default' => 'link',
                        'options' => array(
                            'link'    => __('打开链接', 'xi'),
                            'search'  => __('打开搜索', 'xi'),
                            'login'   => __('打开登录（登录后自动隐藏）', 'xi'),
                            'checkin' => __('每日签到', 'xi'),
                            'theme'   => __('切换深浅色', 'xi'),
                            'top'     => __('回到顶部', 'xi'),
                        ),
                    ),
                    array(
                        'dependency' => array('action', '==', 'link'),
                        'id'         => 'link',
                        'type'       => 'text',
                        'title'      => __('链接', 'xi'),
                    ),
                    array(
                        'id'    => 'icon',
                        'type'  => 'text',
                        'title' => __('图标', 'xi'),
                        'desc'  => __('写法同菜单图标：主题图标名（home、fire、book…）、品牌名、emoji、fa fa-xxx 或图片地址，可留空', 'xi'),
                    ),
                    array(
                        'id'      => 'style',
                        'type'    => 'select',
                        'title'   => __('样式', 'xi'),
                        'default' => 'main',
                        'options' => array(
                            'main'     => __('实色', 'xi'),
                            'soft'     => __('浅色', 'xi'),
                            'glass'    => __('玻璃', 'xi'),
                            'line'     => __('描边', 'xi'),
                            'gradient' => __('渐变（可设第二颜色）', 'xi'),
                            'neon'     => __('霓虹发光', 'xi'),
                            'card'     => __('卡片（图标 + 双行文字）', 'xi'),
                        ),
                    ),
                    array(
                        'id'      => 'size',
                        'type'    => 'button_set',
                        'title'   => __('尺寸', 'xi'),
                        'default' => 'md',
                        'options' => array('sm' => __('小', 'xi'), 'md' => __('中', 'xi'), 'lg' => __('大', 'xi')),
                    ),
                    array(
                        'id'    => 'color',
                        'type'  => 'color',
                        'title' => __('颜色', 'xi'),
                        'desc'  => __('留空使用主题色', 'xi'),
                    ),
                    array(
                        'dependency' => array('style', '==', 'gradient'),
                        'id'         => 'color2',
                        'type'       => 'color',
                        'title'      => __('第二颜色', 'xi'),
                        'desc'       => __('渐变的终点颜色，留空自动取相近色', 'xi'),
                    ),
                    array(
                        'id'      => 'anim',
                        'type'    => 'select',
                        'title'   => __('动效', 'xi'),
                        'default' => 'none',
                        'options' => array(
                            'none'   => __('无（仅悬停效果）', 'xi'),
                            'shine'  => __('自动扫光', 'xi'),
                            'pulse'  => __('脉冲光圈', 'xi'),
                            'breath' => __('呼吸', 'xi'),
                            'float'  => __('上下浮动', 'xi'),
                            'shake'  => __('间歇摇晃', 'xi'),
                        ),
                        'desc'    => __('系统开启「减少动态效果」时自动关闭', 'xi'),
                    ),
                    array(
                        'id'      => 'show',
                        'type'    => 'button_set',
                        'title'   => __('显示给', 'xi'),
                        'default' => 'all',
                        'options' => array('all' => __('所有人', 'xi'), 'guest' => __('仅游客', 'xi'), 'user' => __('仅登录用户', 'xi')),
                    ),
                    array(
                        'id'    => 'badge',
                        'type'  => 'text',
                        'title' => __('角标', 'xi'),
                        'desc'  => __('显示在按钮右上角的小标签，如 NEW、HOT，最多 6 个字，可留空', 'xi'),
                    ),
                    array(
                        'id'      => 'blank',
                        'type'    => 'switcher',
                        'title'   => __('新窗口打开', 'xi'),
                        'default' => false,
                    ),
                ),
            ),
            array(
                'dependency' => array('header_slider_s', '!=', ''),
                'id'         => 'header_slider_card_s',
                'type'       => 'switcher',
                'title'      => __('叠加图标卡片', 'xi'),
                'default'    => false,
            ),
            array(
                'dependency'   => array(array('header_slider_s', '!=', ''), array('header_slider_card_s', '!=', '')),
                'id'           => 'header_slider_cards',
                'type'         => 'group',
                'title'        => __('卡片', 'xi'),
                'button_title' => __('添加卡片', 'xi'),
                'fields'       => array(
                    array(
                        'id'    => 'title',
                        'type'  => 'text',
                        'title' => __('标题', 'xi'),
                    ),
                    array(
                        'id'    => 'desc',
                        'type'  => 'text',
                        'title' => __('简介', 'xi'),
                    ),
                    array(
                        'id'      => 'icon',
                        'type'    => 'select',
                        'title'   => __('图标', 'xi'),
                        'default' => 'leaf',
                        'options' => xi_icon_options(),
                    ),
                    array(
                        'id'      => 'image',
                        'type'    => 'upload',
                        'title'   => __('自定义图标图片', 'xi'),
                        'desc'    => __('填写后替代上方图标', 'xi'),
                        'library' => 'image',
                    ),
                    array(
                        'id'    => 'link',
                        'type'  => 'text',
                        'title' => __('链接', 'xi'),
                    ),
                ),
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'page',
        'title'  => __('底部页脚', 'xi'),
        'icon'   => 'fa fa-fw fa-copyright',
        'fields' => array(
            array(
                'type'    => 'subheading',
                'content' => __('页脚波浪', 'xi'),
            ),
            array(
                'id'      => 'footer_wave_s',
                'type'    => 'switcher',
                'title'   => __('页脚上方波浪', 'xi'),
                'desc'    => __('页脚上方一排低矮的流动波浪，页脚区域铺上同色的浅色底', 'xi'),
                'default' => true,
            ),
            array(
                'dependency' => array('footer_wave_s', '!=', ''),
                'id'         => 'footer_wave_color',
                'type'       => 'color',
                'title'      => __('波浪颜色', 'xi'),
                'desc'       => __('按层自动调淡，深色模式下自动压暗', 'xi'),
                'default'    => '#4f9dff',
            ),
            array(
                'dependency' => array('footer_wave_s', '!=', ''),
                'id'         => 'footer_wave_height',
                'type'       => 'slider',
                'title'      => __('波浪高度', 'xi'),
                'min'        => 16,
                'max'        => 120,
                'unit'       => 'px',
                'default'    => 42,
            ),
            array(
                'type'    => 'subheading',
                'content' => __('品牌与简介', 'xi'),
            ),
            array(
                'id'      => 'footer_brand_s',
                'type'    => 'switcher',
                'title'   => __('显示品牌栏', 'xi'),
                'desc'    => __('页脚左侧显示 LOGO、简介和社交图标', 'xi'),
                'default' => true,
            ),
            array(
                'dependency' => array('footer_brand_s', '!=', ''),
                'id'         => 'footer_about',
                'type'       => 'textarea',
                'title'      => __('站点简介', 'xi'),
                'desc'       => __('留空则使用站点副标题', 'xi'),
                'attributes' => array('rows' => 3),
            ),
            array(
                'dependency'   => array('footer_brand_s', '!=', ''),
                'id'           => 'footer_social',
                'type'         => 'group',
                'title'        => __('社交图标', 'xi'),
                'button_title' => __('添加图标', 'xi'),
                'fields'       => array(
                    array(
                        'id'      => 'icon',
                        'type'    => 'select',
                        'title'   => __('平台', 'xi'),
                        'options' => array(
                            'qq'       => 'QQ',
                            'wechat'   => __('微信', 'xi'),
                            'weibo'    => __('微博', 'xi'),
                            'bilibili' => __('哔哩哔哩', 'xi'),
                            'github'   => 'GitHub',
                            'email'    => __('邮箱', 'xi'),
                            'rss'      => 'RSS',
                        ),
                    ),
                    array(
                        'id'    => 'title',
                        'type'  => 'text',
                        'title' => __('提示文字', 'xi'),
                    ),
                    array(
                        'id'    => 'link',
                        'type'  => 'text',
                        'title' => __('链接', 'xi'),
                        'desc'  => __('邮箱可直接填写地址', 'xi'),
                    ),
                    array(
                        'id'      => 'qr',
                        'type'    => 'upload',
                        'title'   => __('二维码', 'xi'),
                        'desc'    => __('可选，鼠标悬停时弹出（适合微信、QQ）', 'xi'),
                        'library' => 'image',
                    ),
                ),
                'default'      => array(
                    array('icon' => 'rss', 'title' => 'RSS', 'link' => '/feed/'),
                ),
            ),
            array(
                'type'    => 'subheading',
                'content' => __('链接与二维码', 'xi'),
            ),
            array(
                'id'           => 'footer_cols',
                'type'         => 'group',
                'title'        => __('链接栏目', 'xi'),
                'desc'         => __('建议 1 - 3 栏', 'xi'),
                'button_title' => __('添加栏目', 'xi'),
                'fields'       => array(
                    array(
                        'id'    => 'title',
                        'type'  => 'text',
                        'title' => __('栏目标题', 'xi'),
                    ),
                    array(
                        'id'         => 'links',
                        'type'       => 'textarea',
                        'title'      => __('链接', 'xi'),
                        'desc'       => __('每行一个，格式：名称|链接', 'xi'),
                        'attributes' => array('rows' => 5),
                    ),
                ),
            ),
            array(
                'id'           => 'footer_qrs',
                'type'         => 'group',
                'title'        => __('二维码', 'xi'),
                'desc'         => __('公众号、交流群等，建议不超过 2 个', 'xi'),
                'button_title' => __('添加二维码', 'xi'),
                'fields'       => array(
                    array(
                        'id'      => 'image',
                        'type'    => 'upload',
                        'title'   => __('图片', 'xi'),
                        'library' => 'image',
                        'preview' => true,
                    ),
                    array(
                        'id'    => 'title',
                        'type'  => 'text',
                        'title' => __('说明', 'xi'),
                    ),
                ),
            ),
            array(
                'type'    => 'subheading',
                'content' => __('版权与备案', 'xi'),
            ),
            array(
                'id'       => 'footer_since',
                'type'     => 'date',
                'title'    => __('建站日期', 'xi'),
                'desc'     => __('填写后页脚显示「本站已运行 N 天」', 'xi'),
                'settings' => array('dateFormat' => 'yy-mm-dd', 'changeMonth' => true, 'changeYear' => true),
            ),
            array(
                'id'      => 'footer_copyright',
                'type'    => 'text',
                'title'   => __('版权信息', 'xi'),
                'desc'    => __('可用变量：{year} 当前年份，{site} 站点名称', 'xi'),
                'default' => '© {year} {site}',
            ),
            array(
                'id'      => 'footer_icp',
                'type'    => 'text',
                'title'   => __('ICP备案号', 'xi'),
                'default' => '',
                'desc'    => __('填写本站的备案号，留空不显示', 'xi'),
            ),
            array(
                'id'    => 'footer_gongan',
                'type'  => 'text',
                'title' => __('公安备案号', 'xi'),
            ),
            array(
                'id'       => 'footer_html',
                'type'     => 'textarea',
                'title'    => __('页脚自定义内容', 'xi'),
                'desc'     => __('支持 HTML', 'xi'),
                'sanitize' => false,
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'page',
        'title'  => __('404 页面', 'xi'),
        'icon'   => 'fa fa-fw fa-chain-broken',
        'fields' => array(
            array(
                'id'          => 'notfound_page',
                'type'        => 'select',
                'title'       => __('使用自定义页面', 'xi'),
                'desc'        => __('在「页面」里新建一个页面写好 404 内容（可用编辑器的所有区块），在这里选中后，所有找不到的地址都显示该页面的内容（仍返回 404 状态码，不影响 SEO）。留空使用下面的默认样式', 'xi'),
                'placeholder' => __('不使用，显示默认样式', 'xi'),
                'options'     => 'pages',
                'query_args'  => array('posts_per_page' => -1),
                'chosen'      => true,
                'settings'    => array('width' => '100%'),
            ),
            array(
                'dependency' => array('notfound_page', '==', ''),
                'id'         => 'notfound_img',
                'type'       => 'upload',
                'title'      => __('配图', 'xi'),
                'desc'       => __('显示在大字上方，可留空', 'xi'),
                'library'    => 'image',
                'preview'    => true,
            ),
            array(
                'dependency' => array('notfound_page', '==', ''),
                'id'         => 'notfound_title',
                'type'       => 'text',
                'title'      => __('大字', 'xi'),
                'default'    => '404',
            ),
            array(
                'dependency' => array('notfound_page', '==', ''),
                'id'         => 'notfound_text',
                'type'       => 'textarea',
                'title'      => __('说明文字', 'xi'),
                'desc'       => __('支持简单 HTML', 'xi'),
                'default'    => '你要找的页面不见了，也许被风吹走了。',
            ),
            array(
                'dependency' => array('notfound_page', '==', ''),
                'id'         => 'notfound_search',
                'type'       => 'switcher',
                'title'      => __('显示搜索框', 'xi'),
                'default'    => true,
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'page',
        'title'  => __('全站公告', 'xi'),
        'icon'   => 'fa fa-fw fa-bullhorn',
        'fields' => array(
            array(
                'id'      => 'notice_s',
                'type'    => 'switcher',
                'title'   => __('启用公告弹窗', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('notice_s', '!=', ''),
                'id'         => 'notice_title',
                'type'       => 'text',
                'title'      => __('公告标题', 'xi'),
                'default'    => '站点公告',
            ),
            array(
                'dependency' => array('notice_s', '!=', ''),
                'id'         => 'notice_content',
                'type'       => 'textarea',
                'title'      => __('公告内容', 'xi'),
                'desc'       => __('支持 HTML。修改内容后会重新对所有访客弹出', 'xi'),
                'sanitize'   => false,
            ),
            array(
                'dependency' => array('notice_s', '!=', ''),
                'id'         => 'notice_btn_text',
                'type'       => 'text',
                'title'      => __('按钮文字', 'xi'),
                'desc'       => __('与按钮链接都填写才显示', 'xi'),
            ),
            array(
                'dependency' => array('notice_s', '!=', ''),
                'id'         => 'notice_btn_link',
                'type'       => 'text',
                'title'      => __('按钮链接', 'xi'),
            ),
            array(
                'dependency' => array('notice_s', '!=', ''),
                'id'         => 'notice_repeat',
                'type'       => 'button_set',
                'title'      => __('弹出频率', 'xi'),
                'default'    => 'once',
                'options'    => array(
                    'once'     => __('只弹一次', 'xi'),
                    'daily'    => __('每天一次', 'xi'),
                    'session'  => __('每次打开网站', 'xi'),
                    'interval' => __('自定义间隔', 'xi'),
                ),
            ),
            array(
                'dependency' => array(array('notice_s', '!=', ''), array('notice_repeat', '==', 'interval')),
                'id'         => 'notice_interval',
                'type'       => 'fieldset',
                'title'      => __('弹出间隔', 'xi'),
                'desc'       => __('访客关闭公告后，至少间隔这么久才会再次弹出', 'xi'),
                'fields'     => array(
                    array(
                        'id'      => 'num',
                        'type'    => 'number',
                        'title'   => __('数值', 'xi'),
                        'default' => 6,
                    ),
                    array(
                        'id'      => 'unit',
                        'type'    => 'button_set',
                        'title'   => __('单位', 'xi'),
                        'default' => 'hour',
                        'options' => array(
                            'minute' => __('分钟', 'xi'),
                            'hour'   => __('小时', 'xi'),
                            'day'    => __('天', 'xi'),
                        ),
                    ),
                ),
            ),
            array(
                'dependency' => array('notice_s', '!=', ''),
                'id'         => 'notice_scope',
                'type'       => 'button_set',
                'title'      => __('显示页面', 'xi'),
                'default'    => 'all',
                'options'    => array(
                    'all'  => __('全站', 'xi'),
                    'home' => __('仅首页', 'xi'),
                ),
            ),
            array(
                'dependency' => array('notice_s', '!=', ''),
                'id'         => 'notice_delay',
                'type'       => 'spinner',
                'title'      => __('打开页面后延迟弹出', 'xi'),
                'unit'       => __('秒', 'xi'),
                'min'        => 0,
                'max'        => 60,
                'default'    => 1,
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'page',
        'title'  => __('站点美化', 'xi'),
        'icon'   => 'fa fa-fw fa-magic',
        'fields' => array(
            array(
                'type'    => 'subheading',
                'content' => __('字体', 'xi'),
            ),
            array(
                'id'      => 'font_family',
                'type'    => 'button_set',
                'title'   => __('全站字体', 'xi'),
                'desc'    => __('源泉圆体已内置并按字频切片，访客只下载页面用到的部分（常规 + 粗体，首次访问一般 300～600 KB）。字形为台湾标准，个别字写法与大陆规范略有差异', 'xi'),
                'default' => 'system',
                'options' => array(
                    'system' => __('系统字体', 'xi'),
                    'gensen' => __('源泉圆体', 'xi'),
                ),
            ),
            array(
                'dependency' => array('font_family', '==', 'gensen'),
                'id'         => 'font_scope',
                'type'       => 'button_set',
                'title'      => __('使用范围', 'xi'),
                'default'    => 'all',
                'options'    => array(
                    'all'   => __('全部文字', 'xi'),
                    'title' => __('仅标题与 LOGO', 'xi'),
                ),
            ),
            array(
                'type'    => 'subheading',
                'content' => __('LOGO 与标题', 'xi'),
            ),
            array(
                'id'      => 'logo_shine_s',
                'type'    => 'switcher',
                'title'   => __('LOGO 扫光', 'xi'),
                'desc'    => __('一道高光定时从 LOGO 上扫过', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('logo_shine_s', '!=', ''),
                'id'         => 'logo_shine_gap',
                'type'       => 'spinner',
                'title'      => __('扫光间隔', 'xi'),
                'unit'       => __('秒', 'xi'),
                'min'        => 1,
                'max'        => 30,
                'default'    => 4,
            ),
            array(
                'id'      => 'dyn_title_s',
                'type'    => 'switcher',
                'title'   => __('网站动态标题', 'xi'),
                'desc'    => __('访客切换到其他标签页时，浏览器标签上的标题变成下面的文字', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('dyn_title_s', '!=', ''),
                'id'         => 'dyn_title_leave',
                'type'       => 'text',
                'title'      => __('离开时', 'xi'),
                'default'    => '(つェ⊂) 我藏好了哦~',
            ),
            array(
                'dependency' => array('dyn_title_s', '!=', ''),
                'id'         => 'dyn_title_back',
                'type'       => 'text',
                'title'      => __('回来时', 'xi'),
                'desc'       => __('显示 2 秒后恢复原标题，留空则直接恢复', 'xi'),
                'default'    => '(*´∇｀*) 欸嘿，被你发现啦~',
            ),
            array(
                'type'    => 'subheading',
                'content' => __('访问灵动岛', 'xi'),
            ),
            array(
                'id'      => 'island_s',
                'type'    => 'switcher',
                'title'   => __('灵动岛', 'xi'),
                'desc'    => __('仿 iPhone 灵动岛的黑色胶囊：访问首页、页面或文章时展开欢迎语，几秒后自动收起消失；也可改为常驻胶囊（显示当前页面标题与阅读进度，悬停展开详情，点击回到顶部）', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('island_s', '!=', ''),
                'id'         => 'island_mode',
                'type'       => 'button_set',
                'title'      => __('显示方式', 'xi'),
                'default'    => 'visit',
                'options'    => array(
                    'visit'    => __('访问页面时欢迎', 'xi'),
                    'welcome'  => __('仅进站欢迎一次', 'xi'),
                    'resident' => __('常驻胶囊', 'xi'),
                ),
                'desc'       => __('访问页面时欢迎：打开下方勾选的页面时弹出欢迎语，几秒后消失，不会一直显示', 'xi'),
            ),
            array(
                'dependency' => array(array('island_s', '!=', ''), array('island_mode', '==', 'visit')),
                'id'         => 'island_scope',
                'type'       => 'checkbox',
                'title'      => __('在这些页面显示', 'xi'),
                'inline'     => true,
                'default'    => array('home', 'page', 'post'),
                'options'    => array(
                    'home'  => __('首页', 'xi'),
                    'page'  => __('页面', 'xi'),
                    'post'  => __('文章', 'xi'),
                    'bbs'   => __('论坛帖子', 'xi'),
                    'other' => __('其他页面（分类、搜索、论坛首页等）', 'xi'),
                ),
            ),
            array(
                'dependency' => array(array('island_s', '!=', ''), array('island_mode', '==', 'visit')),
                'id'         => 'island_visit_title',
                'type'       => 'text',
                'title'      => __('欢迎语', 'xi'),
                'desc'       => __('{title} 为当前页面名称：首页显示站点名称，文章、页面显示标题。其余变量：{greeting} 时段问候，{name} 登录用户昵称（游客为「朋友」），{site} 站点名称，{from} 来源网站', 'xi'),
                'default'    => '欢迎来到 {title}',
            ),
            array(
                'dependency' => array(array('island_s', '!=', ''), array('island_mode', '==', 'visit')),
                'id'         => 'island_visit_desc',
                'type'       => 'text',
                'title'      => __('副标题', 'xi'),
                'desc'       => __('变量同上，留空则只显示欢迎语', 'xi'),
                'default'    => '{greeting}，{name}',
            ),
            array(
                'dependency' => array('island_s', '!=', ''),
                'id'         => 'island_pos',
                'type'       => 'button_set',
                'title'      => __('位置', 'xi'),
                'desc'       => __('常驻胶囊建议放在导航栏下方，避免挡住导航菜单', 'xi'),
                'default'    => 'header',
                'options'    => array(
                    'header' => __('导航栏下方', 'xi'),
                    'top'    => __('屏幕最顶部', 'xi'),
                ),
            ),
            array(
                'dependency' => array('island_s', '!=', ''),
                'id'         => 'island_notify',
                'type'       => 'switcher',
                'title'      => __('提示消息显示在灵动岛', 'xi'),
                'desc'       => __('点赞、收藏、复制、深浅色切换等操作提示改为从灵动岛展开显示', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array(array('island_s', '!=', ''), array('island_mode', '!=', 'visit')),
                'id'         => 'island_title',
                'type'       => 'text',
                'title'      => __('主标题', 'xi'),
                'desc'       => __('可用变量：{greeting} 时段问候（早上好 / 下午好…），{name} 登录用户昵称（游客为「朋友」），{site} 站点名称，{from} 来源网站（如「来自 百度 的」，直接访问时为空）', 'xi'),
                'default'    => '{greeting}，{name}',
            ),
            array(
                'dependency' => array(array('island_s', '!=', ''), array('island_mode', '!=', 'visit')),
                'id'         => 'island_desc',
                'type'       => 'text',
                'title'      => __('副标题', 'xi'),
                'desc'       => __('变量同上，留空则只显示主标题', 'xi'),
                'default'    => '欢迎{from}你来到 {site}',
            ),
            array(
                'dependency' => array(array('island_s', '!=', ''), array('island_mode', '!=', 'visit')),
                'id'         => 'island_repeat',
                'type'       => 'button_set',
                'title'      => __('显示频率', 'xi'),
                'default'    => 'session',
                'desc'       => __('指欢迎问候的显示频率；常驻胶囊在每个页面都会显示', 'xi'),
                'options'    => array(
                    'session' => __('每次打开网站一次', 'xi'),
                    'page'    => __('每个页面', 'xi'),
                ),
            ),
            array(
                'dependency' => array('island_s', '!=', ''),
                'id'         => 'island_duration',
                'type'       => 'spinner',
                'title'      => __('展开时长', 'xi'),
                'unit'       => __('秒', 'xi'),
                'min'        => 2,
                'max'        => 15,
                'default'    => 4,
            ),
            array(
                'type'    => 'subheading',
                'content' => __('全局飘落', 'xi'),
            ),
            array(
                'id'      => 'sakura_s',
                'type'    => 'switcher',
                'title'   => __('樱花飘落', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('sakura_s', '!=', ''),
                'id'         => 'sakura_num',
                'type'       => 'slider',
                'title'      => __('樱花数量', 'xi'),
                'min'        => 5,
                'max'        => 80,
                'default'    => 28,
            ),
            array(
                'id'      => 'snow_s',
                'type'    => 'switcher',
                'title'   => __('下雪', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('snow_s', '!=', ''),
                'id'         => 'snow_num',
                'type'       => 'slider',
                'title'      => __('雪花数量', 'xi'),
                'min'        => 10,
                'max'        => 200,
                'default'    => 70,
            ),
            array(
                'id'      => 'fall_scope',
                'type'    => 'button_set',
                'title'   => __('飘落显示页面', 'xi'),
                'default' => 'all',
                'options' => array(
                    'all'  => __('全站', 'xi'),
                    'home' => __('仅首页', 'xi'),
                ),
            ),
            array(
                'id'      => 'fall_mobile',
                'type'    => 'switcher',
                'title'   => __('手机端也显示飘落', 'xi'),
                'desc'    => __('开启后手机端数量自动减半。系统开启「减弱动态效果」的访客始终不显示', 'xi'),
                'default' => false,
            ),
        ),
    ));

    /* ---------- 文章&列表 ---------- */

    CSF::createSection($prefix, array(
        'parent' => 'post',
        'title'  => __('文章设置', 'xi'),
        'icon'   => 'fa fa-fw fa-file-text-o',
        'fields' => array(
            array(
                'type'    => 'subheading',
                'content' => __('文章列表', 'xi'),
            ),
            array(
                'id'      => 'home_list_title',
                'type'    => 'text',
                'title'   => __('首页列表标题', 'xi'),
                'default' => '最近更新',
            ),
            array(
                'id'      => 'list_thumb_s',
                'type'    => 'switcher',
                'title'   => __('显示缩略图', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'list_style',
                'type'    => 'button_set',
                'title'   => __('列表样式', 'xi'),
                'desc'    => __('卡片类样式适合封面图较多的站点，屏幕变窄时自动减少列数；极简列表只显示日期和标题，适合笔记、日志类站点', 'xi'),
                'default' => 'list',
                'options' => array(
                    'list' => __('单列列表', 'xi'),
                    'grid' => __('双列卡片', 'xi'),
                    'card' => __('三列卡片', 'xi'),
                    'big'  => __('大图卡片', 'xi'),
                    'mini' => __('极简列表', 'xi'),
                ),
            ),
            array(
                'dependency' => array('list_style', 'any', 'grid,card,big'),
                'id'         => 'list_mac_s',
                'type'       => 'switcher',
                'title'      => __('Mac 窗口装饰', 'xi'),
                'desc'       => __('卡片顶部显示 macOS 风格的红黄绿三个圆点和「~/分类名」，鼠标悬停时圆点显示 × − ＋', 'xi'),
                'default'    => true,
            ),
            array(
                'id'      => 'excerpt_length',
                'type'    => 'spinner',
                'title'   => __('摘要字数', 'xi'),
                'min'     => 20,
                'max'     => 300,
                'step'    => 10,
                'default' => 90,
            ),
            array(
                'type'    => 'subheading',
                'content' => __('文章页面', 'xi'),
            ),
            array(
                'id'      => 'breadcrumb_s',
                'type'    => 'switcher',
                'title'   => __('面包屑导航', 'xi'),
                'desc'    => __('文章、页面、分类、标签、搜索页顶部显示「首页 › 分类 › 正文」，并输出结构化数据', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'lightbox_s',
                'type'    => 'switcher',
                'title'   => __('图片灯箱', 'xi'),
                'desc'    => __('点击正文图片全屏查看，支持左右切换、键盘与手势', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'code_copy_s',
                'type'    => 'switcher',
                'title'   => __('代码块复制按钮', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'comment_emoji_s',
                'type'    => 'switcher',
                'title'   => __('评论表情', 'xi'),
                'desc'    => __('评论框下方显示表情按钮，点击插入 Emoji 表情', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'post_hero_s',
                'type'    => 'switcher',
                'title'   => __('文章顶部波浪', 'xi'),
                'desc'    => __('文章标题、分类和信息移到页面顶部的通栏色块中，底部为流动的波浪；文章页同时显示顶部幻灯片时不生效', 'xi'),
                'default' => true,
            ),
            array(
                'dependency' => array('post_hero_s', '!=', ''),
                'id'         => 'post_hero_bg',
                'type'       => 'button_set',
                'title'      => __('背景', 'xi'),
                'default'    => 'color',
                'options'    => array(
                    'color' => __('主题色渐变', 'xi'),
                    'thumb' => __('特色图片（无图时用渐变）', 'xi'),
                ),
            ),
            array(
                'dependency' => array('post_hero_s', '!=', ''),
                'id'         => 'post_hero_colors',
                'type'       => 'color_group',
                'title'      => __('渐变色', 'xi'),
                'desc'       => __('留空使用主题色自动生成', 'xi'),
                'options'    => array(
                    'a' => __('起始', 'xi'),
                    'b' => __('中间', 'xi'),
                    'c' => __('结束', 'xi'),
                ),
            ),
            array(
                'dependency' => array('post_hero_s', '!=', ''),
                'id'         => 'post_hero_wave_color',
                'type'       => 'color',
                'title'      => __('波浪颜色', 'xi'),
                'desc'       => __('色块底部几道浪的颜色，按层自动降低透明度；留空为白色。最下沿始终与页面背景衔接', 'xi'),
                'default'    => '',
            ),
            array(
                'dependency' => array('post_hero_s', '!=', ''),
                'id'         => 'post_hero_anim',
                'type'       => 'switcher',
                'title'      => __('波浪流动', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('post_hero_s', '!=', ''),
                'id'         => 'post_hero_page',
                'type'       => 'switcher',
                'title'      => __('页面也使用', 'xi'),
                'default'    => false,
            ),
            array(
                'id'      => 'reading_progress_s',
                'type'    => 'switcher',
                'title'   => __('阅读进度条', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'post_copyright_s',
                'type'    => 'switcher',
                'title'   => __('文末版权声明', 'xi'),
                'default' => true,
            ),
            array(
                'dependency' => array('post_copyright_s', '!=', ''),
                'id'         => 'post_copyright',
                'type'       => 'textarea',
                'title'      => __('版权声明内容', 'xi'),
                'default'    => '本文为原创内容，转载请注明出处。',
            ),
            array(
                'id'      => 'post_nav_s',
                'type'    => 'switcher',
                'title'   => __('上一篇 / 下一篇', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'related_s',
                'type'    => 'switcher',
                'title'   => __('相关文章', 'xi'),
                'default' => true,
            ),
            array(
                'dependency' => array('related_s', '!=', ''),
                'id'         => 'related_count',
                'type'       => 'spinner',
                'title'      => __('相关文章数量', 'xi'),
                'min'        => 2,
                'max'        => 8,
                'step'       => 2,
                'default'    => 4,
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'post',
        'title'  => __('互动功能', 'xi'),
        'icon'   => 'fa fa-fw fa-heart-o',
        'fields' => array(
            array(
                'id'      => 'views_s',
                'type'    => 'switcher',
                'title'   => __('浏览量统计', 'xi'),
                'desc'    => __('前端异步计数，开启页面缓存也能正常统计；「文章列表」小工具可按浏览量排序', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'like_s',
                'type'    => 'switcher',
                'title'   => __('文章点赞', 'xi'),
                'desc'    => __('登录用户可取消点赞，游客同一篇文章每天只能点赞一次', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'fav_s',
                'type'    => 'switcher',
                'title'   => __('文章收藏', 'xi'),
                'desc'    => __('登录用户可收藏文章，在用户中心「我的收藏」查看', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'follow_s',
                'type'    => 'switcher',
                'title'   => __('关注用户', 'xi'),
                'desc'    => __('个人主页显示关注按钮与关注、粉丝数', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'share_s',
                'type'    => 'switcher',
                'title'   => __('文章分享', 'xi'),
                'desc'    => __('微博、QQ、QQ空间、X 与复制链接', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'reward_s',
                'type'    => 'switcher',
                'title'   => __('文章打赏', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('reward_s', '!=', ''),
                'id'         => 'reward_title',
                'type'       => 'text',
                'title'      => __('打赏标题', 'xi'),
                'default'    => '请作者喝杯咖啡',
            ),
            array(
                'dependency' => array('reward_s', '!=', ''),
                'id'         => 'reward_wechat',
                'type'       => 'upload',
                'title'      => __('微信收款码', 'xi'),
                'library'    => 'image',
            ),
            array(
                'dependency' => array('reward_s', '!=', ''),
                'id'         => 'reward_alipay',
                'type'       => 'upload',
                'title'      => __('支付宝收款码', 'xi'),
                'library'    => 'image',
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'post',
        'title'  => __('AI 摘要', 'xi'),
        'icon'   => 'fa fa-fw fa-magic',
        'fields' => array(
            array(
                'id'      => 'ai_summary_s',
                'type'    => 'switcher',
                'title'   => __('AI 文章摘要', 'xi'),
                'desc'    => __('调用你自己的大模型接口为文章生成摘要，显示在正文上方。隐藏 / 付费内容不会发送给接口', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'id'         => 'ai_api_format',
                'type'       => 'button_set',
                'title'      => __('接口格式', 'xi'),
                'default'    => 'openai',
                'options'    => array(
                    'openai'    => __('OpenAI 兼容', 'xi'),
                    'anthropic' => __('Anthropic（Claude）', 'xi'),
                ),
                'desc'       => __('DeepSeek、通义千问、Kimi、智谱、豆包、OpenAI 以及大多数中转服务都选「OpenAI 兼容」', 'xi'),
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'id'         => 'ai_api_url',
                'type'       => 'text',
                'title'      => __('接口地址', 'xi'),
                'desc'       => __('填完整地址。OpenAI 兼容示例：<br><code>https://api.deepseek.com/chat/completions</code><br><code>https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions</code>（通义千问）<br><code>https://api.moonshot.cn/v1/chat/completions</code>（Kimi）<br><code>https://open.bigmodel.cn/api/paas/v4/chat/completions</code>（智谱）<br>Anthropic 格式留空即使用官方地址 <code>https://api.anthropic.com/v1/messages</code>', 'xi'),
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'id'         => 'ai_api_key',
                'type'       => 'text',
                'title'      => __('API Key', 'xi'),
                'attributes' => array('type' => 'password', 'autocomplete' => 'off'),
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'id'         => 'ai_model',
                'type'       => 'text',
                'title'      => __('模型名称', 'xi'),
                'desc'       => __('按服务商文档填写，例如 deepseek-chat、qwen-plus、moonshot-v1-8k、glm-4-flash。Anthropic 格式留空默认 claude-opus-5-5', 'xi'),
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'type'       => 'callback',
                'function'   => 'xi_ai_tools_field',
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'id'         => 'ai_post_types',
                'type'       => 'checkbox',
                'title'      => __('生成范围', 'xi'),
                'inline'     => true,
                'default'    => array('post'),
                'options'    => array(
                    'post'       => __('文章', 'xi'),
                    'forum_post' => __('论坛帖子', 'xi'),
                ),
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'id'         => 'ai_auto',
                'type'       => 'switcher',
                'title'      => __('发布时自动生成', 'xi'),
                'desc'       => __('在后台计划任务中生成，不会拖慢发布；修改文章内容后自动重新生成（手动改过的摘要除外）', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'id'         => 'ai_length',
                'type'       => 'spinner',
                'title'      => __('摘要长度', 'xi'),
                'unit'       => __('字左右', 'xi'),
                'min'        => 30,
                'max'        => 500,
                'step'       => 10,
                'default'    => 150,
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'id'         => 'ai_max_input',
                'type'       => 'spinner',
                'title'      => __('正文最多发送', 'xi'),
                'desc'       => __('超长文章只发送开头这么多字，控制接口费用', 'xi'),
                'unit'       => __('字', 'xi'),
                'min'        => 1000,
                'max'        => 100000,
                'step'       => 1000,
                'default'    => 8000,
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'id'         => 'ai_prompt',
                'type'       => 'textarea',
                'title'      => __('提示词', 'xi'),
                'desc'       => __('留空使用默认提示词；{length} 会替换为上面的摘要长度', 'xi'),
                'attributes' => array('rows' => 5, 'placeholder' => xi_ai_default_prompt()),
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'id'         => 'ai_show',
                'type'       => 'switcher',
                'title'      => __('显示在正文上方', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array(array('ai_summary_s', '!=', ''), array('ai_show', '!=', '')),
                'id'         => 'ai_title',
                'type'       => 'text',
                'title'      => __('卡片标题', 'xi'),
                'default'    => 'AI 摘要',
            ),
            array(
                'dependency' => array(array('ai_summary_s', '!=', ''), array('ai_show', '!=', '')),
                'id'         => 'ai_typing',
                'type'       => 'switcher',
                'title'      => __('打字机效果', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array(array('ai_summary_s', '!=', ''), array('ai_show', '!=', '')),
                'id'         => 'ai_show_model',
                'type'       => 'switcher',
                'title'      => __('显示模型名称', 'xi'),
                'default'    => false,
            ),
            array(
                'dependency' => array('ai_summary_s', '!=', ''),
                'id'         => 'ai_as_excerpt',
                'type'       => 'switcher',
                'title'      => __('用作文章摘要与 SEO 描述', 'xi'),
                'desc'       => __('文章没有手写摘要时，列表摘要和 SEO 描述使用 AI 摘要', 'xi'),
                'default'    => true,
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'post',
        'title'  => __('前台投稿', 'xi'),
        'icon'   => 'fa fa-fw fa-pencil-square-o',
        'fields' => array(
            array(
                'id'      => 'write_s',
                'type'    => 'switcher',
                'title'   => __('启用前台投稿', 'xi'),
                'desc'    => __('用户在前台 /write/ 发布和编辑自己的文章，哪些用户组可以投稿、免审核、上传图片等请到「权限管理」设置', 'xi'),
                'default' => true,
            ),
            array(
                'dependency' => array('write_s', '!=', ''),
                'id'         => 'write_notify',
                'type'       => 'switcher',
                'title'      => __('有待审投稿时邮件通知管理员', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency'  => array('write_s', '!=', ''),
                'id'          => 'write_cats',
                'type'        => 'select',
                'title'       => __('可投稿分类', 'xi'),
                'desc'        => __('留空为全部分类', 'xi'),
                'options'     => 'categories',
                'multiple'    => true,
                'chosen'      => true,
                'placeholder' => __('全部分类', 'xi'),
            ),
            array(
                'dependency' => array('write_s', '!=', ''),
                'id'         => 'write_daily_limit',
                'type'       => 'number',
                'title'      => __('每人每日投稿上限', 'xi'),
                'desc'       => __('0 为不限制，不受限制的用户组在「权限管理」中设置', 'xi'),
                'default'    => 5,
            ),
            array(
                'dependency' => array('write_s', '!=', ''),
                'id'         => 'write_min_length',
                'type'       => 'number',
                'title'      => __('正文最少字数', 'xi'),
                'default'    => 20,
            ),
            array(
                'dependency' => array('write_s', '!=', ''),
                'id'         => 'write_upload_size',
                'type'       => 'number',
                'title'      => __('单张图片大小上限（MB）', 'xi'),
                'default'    => 5,
            ),
            array(
                'dependency' => array('write_s', '!=', ''),
                'id'         => 'write_notice',
                'type'       => 'textarea',
                'title'      => __('投稿须知', 'xi'),
                'desc'       => __('显示在编辑器右侧，支持 HTML', 'xi'),
                'default'    => "请勿发布违法违规、侵权或广告内容。
图片请使用原创或已获授权的素材。",
                'sanitize'   => false,
            ),
        ),
    ));

    /* ---------- 用户&互动 ---------- */

    CSF::createSection($prefix, array(
        'parent' => 'user',
        'title'  => __('登录注册', 'xi'),
        'icon'   => 'fa fa-fw fa-sign-in',
        'fields' => array(
            array(
                'id'      => 'sign_modal_s',
                'type'    => 'switcher',
                'title'   => __('前台弹窗登录', 'xi'),
                'desc'    => __('登录、注册、找回密码地址全部替换为前台弹窗，不再跳转 wp-login.php', 'xi'),
                'default' => true,
            ),
            array(
                'type'    => 'subheading',
                'content' => __('后台安全入口', 'xi'),
            ),
            array(
                'type'     => 'callback',
                'function' => 'xi_safe_option_field',
            ),
            array(
                'type'    => 'subheading',
                'content' => __('登录方式', 'xi'),
            ),
            array(
                'dependency' => array('sign_modal_s', '!=', ''),
                'id'         => 'sign_block_wp_login',
                'type'       => 'switcher',
                'title'      => __('禁止直接访问 wp-login.php', 'xi'),
                'desc'       => __('访问 wp-login.php 的登录、注册、找回密码页面时跳转到前台弹窗；退出登录和后台会话续期不受影响', 'xi'),
                'default'    => false,
            ),
            array(
                'dependency' => array('sign_modal_s', '!=', ''),
                'id'         => 'sign_reg_s',
                'type'       => 'switcher',
                'title'      => __('允许注册', 'xi'),
                'desc'       => __('新用户角色使用「设置 → 常规 → 新用户默认角色」', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('sign_modal_s', '!=', ''),
                'type'       => 'subheading',
                'content'    => __('安全验证', 'xi'),
            ),
            array(
                'dependency' => array('sign_modal_s', '!=', ''),
                'id'         => 'sign_captcha_s',
                'type'       => 'switcher',
                'title'      => __('人机验证', 'xi'),
                'desc'       => __('登录、注册、发送邮箱验证码前需回答一道算术题', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('sign_modal_s', '!=', ''),
                'id'         => 'sign_fail_limit',
                'type'       => 'number',
                'title'      => __('登录失败锁定', 'xi'),
                'desc'       => __('同一 IP 连续输错密码达到次数后锁定 10 分钟；对前台弹窗、wp-login.php、XML-RPC 同时生效，最少 3 次', 'xi'),
                'unit'       => __('次', 'xi'),
                'default'    => 5,
            ),
            array(
                'dependency' => array(array('sign_modal_s', '!=', ''), array('sign_reg_s', '!=', '')),
                'id'         => 'sign_reg_ip_limit',
                'type'       => 'number',
                'title'      => __('每个 IP 每天注册上限', 'xi'),
                'desc'       => __('防止批量注册，0 为不限', 'xi'),
                'unit'       => __('个', 'xi'),
                'default'    => 5,
            ),
            array(
                'id'      => 'sign_xmlrpc_off',
                'type'    => 'switcher',
                'title'   => __('关闭 XML-RPC', 'xi'),
                'desc'    => __('XML-RPC 是暴力破解密码的常见入口；不使用离线写作工具、Jetpack 等的站点建议关闭', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array(array('sign_modal_s', '!=', ''), array('sign_reg_s', '!=', '')),
                'id'         => 'sign_reg_email_s',
                'type'       => 'switcher',
                'title'      => __('注册需邮箱验证码', 'xi'),
                'desc'       => __('开启前请先在「邮件SMTP」中配置并测试发信', 'xi'),
                'default'    => false,
            ),
            array(
                'dependency' => array(array('sign_modal_s', '!=', ''), array('sign_reg_s', '!=', '')),
                'id'         => 'sign_reg_mode',
                'type'       => 'button_set',
                'title'      => __('注册方式', 'xi'),
                'desc'       => __('手机号注册需先在「短信接口」中开启短信，注册时验证短信验证码，邮箱可不填', 'xi'),
                'default'    => 'email',
                'options'    => array(
                    'email' => __('邮箱注册', 'xi'),
                    'phone' => __('手机号注册', 'xi'),
                ),
            ),
            array(
                'dependency' => array('sign_modal_s', '!=', ''),
                'id'         => 'sign_phone_login_s',
                'type'       => 'switcher',
                'title'      => __('允许手机号登录', 'xi'),
                'desc'       => __('已绑定手机的用户可用手机号 + 密码登录（需开启短信）', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('sign_modal_s', '!=', ''),
                'id'         => 'sign_lostpwd_phone_s',
                'type'       => 'switcher',
                'title'      => __('允许手机号找回密码', 'xi'),
                'desc'       => __('找回密码时可填写邮箱或已绑定的手机号接收验证码（需开启短信）', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('sign_modal_s', '!=', ''),
                'type'       => 'submessage',
                'style'      => 'info',
                'content'    => __('找回密码通过邮箱或短信验证码完成，请确保对应通道能正常发送。开启前台弹窗后，注册只能通过弹窗进行，wp-login.php 的注册入口会被拒绝（防止绕过验证码与注册开关）。', 'xi'),
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'user',
        'title'  => __('第三方登录', 'xi'),
        'icon'   => 'fa fa-fw fa-users',
        'fields' => function_exists('xi_oauth_option_fields') ? xi_oauth_option_fields() : array(),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'user',
        'title'  => __('短信接口', 'xi'),
        'icon'   => 'fa fa-fw fa-mobile',
        'fields' => array(
            array(
                'id'      => 'sms_s',
                'type'    => 'switcher',
                'title'   => __('启用手机短信', 'xi'),
                'desc'    => __('开启后可在「登录注册」中启用手机号注册、手机号登录和手机号找回密码，用户可在账号设置中绑定手机', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('sms_s', '!=', ''),
                'id'         => 'sms_sdk',
                'type'       => 'select',
                'title'      => __('短信服务商', 'xi'),
                'options'    => array(
                    ''        => __('请选择', 'xi'),
                    'aliyun'  => __('阿里云', 'xi'),
                    'tencent' => __('腾讯云', 'xi'),
                    'smsbao'  => __('短信宝', 'xi'),
                ),
            ),
            array(
                'dependency' => array('sms_s', '!=', ''),
                'id'         => 'sms_cn_only',
                'type'       => 'switcher',
                'title'      => __('仅允许中国大陆手机号', 'xi'),
                'desc'       => __('关闭后可填写带国家码的号码，例如 +85212345678（需服务商开通国际短信）', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array(array('sms_s', '!=', ''), array('sms_sdk', '==', 'aliyun')),
                'id'         => 'sms_aliyun',
                'type'       => 'fieldset',
                'title'      => __('阿里云', 'xi'),
                'fields'     => array(
                    array(
                        'id'      => 'api_type',
                        'type'    => 'button_set',
                        'title'   => __('产品', 'xi'),
                        'default' => 'sms',
                        'options' => array(
                            'sms'    => __('短信服务', 'xi'),
                            'verify' => __('号码认证·短信认证', 'xi'),
                        ),
                        'desc'    => __('短信服务需企业资质与自定义签名、模板；号码认证个人也可开通，使用阿里云提供的签名和模板', 'xi'),
                    ),
                    array('id' => 'keyid', 'type' => 'text', 'title' => 'AccessKey ID'),
                    array('id' => 'keysecret', 'type' => 'text', 'title' => 'AccessKey Secret', 'attributes' => array('type' => 'password', 'autocomplete' => 'new-password')),
                    array('id' => 'sign_name', 'type' => 'text', 'title' => __('短信签名', 'xi'), 'desc' => __('已审核通过的签名名称', 'xi')),
                    array('id' => 'template_code', 'type' => 'text', 'title' => __('模板 CODE', 'xi'), 'desc' => __('例如 SMS_207952000，模板变量为 ${code}；号码认证留空默认 100001', 'xi')),
                ),
            ),
            array(
                'dependency' => array(array('sms_s', '!=', ''), array('sms_sdk', '==', 'tencent')),
                'id'         => 'sms_tencent',
                'type'       => 'fieldset',
                'title'      => __('腾讯云', 'xi'),
                'fields'     => array(
                    array('id' => 'secret_id', 'type' => 'text', 'title' => 'SecretId', 'desc' => __('在「访问管理 → API 密钥管理」中获取', 'xi')),
                    array('id' => 'secret_key', 'type' => 'text', 'title' => 'SecretKey', 'attributes' => array('type' => 'password', 'autocomplete' => 'new-password')),
                    array('id' => 'sdk_app_id', 'type' => 'text', 'title' => 'SDK AppID', 'desc' => __('短信控制台「应用管理」中的 SdkAppId，例如 1400006666', 'xi')),
                    array('id' => 'sign_name', 'type' => 'text', 'title' => __('短信签名', 'xi')),
                    array('id' => 'template_id', 'type' => 'text', 'title' => __('模板 ID', 'xi')),
                    array(
                        'id'      => 'params',
                        'type'    => 'button_set',
                        'title'   => __('模板变量', 'xi'),
                        'default' => 'code_min',
                        'options' => array(
                            'code'     => __('{1}=验证码', 'xi'),
                            'code_min' => __('{1}=验证码 {2}=有效分钟', 'xi'),
                        ),
                        'desc'    => __('需与模板中的变量个数一致，例如“验证码为{1}，{2}分钟内有效”', 'xi'),
                    ),
                    array('id' => 'region', 'type' => 'text', 'title' => __('地域', 'xi'), 'default' => 'ap-guangzhou'),
                ),
            ),
            array(
                'dependency' => array(array('sms_s', '!=', ''), array('sms_sdk', '==', 'smsbao')),
                'id'         => 'sms_smsbao',
                'type'       => 'fieldset',
                'title'      => __('短信宝', 'xi'),
                'fields'     => array(
                    array('id' => 'user', 'type' => 'text', 'title' => __('用户名', 'xi')),
                    array('id' => 'api_key', 'type' => 'text', 'title' => 'ApiKey', 'desc' => __('推荐使用，填写后无需密码', 'xi'), 'attributes' => array('type' => 'password', 'autocomplete' => 'new-password')),
                    array('id' => 'password', 'type' => 'text', 'title' => __('密码', 'xi'), 'attributes' => array('type' => 'password', 'autocomplete' => 'new-password')),
                    array(
                        'id'      => 'template',
                        'type'    => 'textarea',
                        'title'   => __('短信内容', 'xi'),
                        'desc'    => __('必须包含 {code}，可用 {time} 表示有效分钟；开头需带已报备的【签名】', 'xi'),
                        'default' => '【曦芒的博客】你的验证码是{code}，{time}分钟内有效，请勿泄露给他人。',
                    ),
                ),
            ),
            array(
                'dependency' => array('sms_s', '!=', ''),
                'type'       => 'content',
                'title'      => __('发送测试', 'xi'),
                'content'    => '<div class="xi-admin-test" data-action="xi_sms_test"><input type="tel" placeholder="' . esc_attr__('接收手机号', 'xi') . '" style="width:200px"> <button type="button" class="button button-primary">' . esc_html__('发送测试短信', 'xi') . '</button><p class="description">' . esc_html__('请先保存设置再测试，将发送验证码 888888', 'xi') . '</p></div>',
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'user',
        'title'  => __('邮件SMTP', 'xi'),
        'icon'   => 'fa fa-fw fa-envelope-o',
        'fields' => array(
            array(
                'id'      => 'smtp_s',
                'type'    => 'switcher',
                'title'   => __('启用 SMTP 发信', 'xi'),
                'desc'    => __('大多数主机无法直接发信，建议使用 QQ 邮箱、163 邮箱或企业邮箱的 SMTP 服务', 'xi'),
                'default' => false,
            ),
            array(
                'dependency'  => array('smtp_s', '!=', ''),
                'id'          => 'smtp_host',
                'type'        => 'text',
                'title'       => __('SMTP 服务器', 'xi'),
                'placeholder' => 'smtp.qq.com',
            ),
            array(
                'dependency' => array('smtp_s', '!=', ''),
                'id'         => 'smtp_secure',
                'type'       => 'button_set',
                'title'      => __('加密方式', 'xi'),
                'default'    => 'ssl',
                'options'    => array(
                    'ssl'  => 'SSL',
                    'tls'  => 'TLS',
                    'none' => __('无', 'xi'),
                ),
            ),
            array(
                'dependency' => array('smtp_s', '!=', ''),
                'id'         => 'smtp_port',
                'type'       => 'number',
                'title'      => __('端口', 'xi'),
                'desc'       => __('SSL 通常为 465，TLS 通常为 587，无加密通常为 25', 'xi'),
                'default'    => 465,
            ),
            array(
                'dependency' => array('smtp_s', '!=', ''),
                'id'         => 'smtp_user',
                'type'       => 'text',
                'title'      => __('邮箱账号', 'xi'),
                'sanitize'   => 'xi_sanitize_smtp_credential',
            ),
            array(
                'dependency' => array('smtp_s', '!=', ''),
                'id'         => 'smtp_pass',
                'type'       => 'text',
                'title'      => __('密码 / 授权码', 'xi'),
                'sanitize'   => 'xi_sanitize_smtp_credential',
                'desc'       => __('QQ、163 邮箱需填写在邮箱设置中生成的授权码，不是登录密码', 'xi'),
                'attributes' => array('type' => 'password', 'autocomplete' => 'new-password'),
            ),
            array(
                'dependency' => array('smtp_s', '!=', ''),
                'id'         => 'smtp_from',
                'type'       => 'text',
                'title'      => __('发件人邮箱', 'xi'),
                'desc'       => __('留空使用邮箱账号，多数服务商要求与账号一致', 'xi'),
            ),
            array(
                'dependency' => array('smtp_s', '!=', ''),
                'id'         => 'smtp_from_name',
                'type'       => 'text',
                'title'      => __('发件人名称', 'xi'),
                'desc'       => __('留空使用站点名称', 'xi'),
            ),
            array(
                'type'    => 'content',
                'title'   => __('发送测试邮件', 'xi'),
                'content' => '<div class="xi-admin-test" data-action="xi_smtp_test"><input type="email" placeholder="' . esc_attr__('收件邮箱，留空发给管理员邮箱', 'xi') . '" style="width:260px"> <button type="button" class="button button-primary">' . esc_html__('发送测试', 'xi') . '</button><p class="description">' . esc_html__('请先保存设置再测试', 'xi') . '</p></div>'
                    . (function_exists('xi_mail_last_error_text') && xi_mail_last_error_text() ? '<p style="margin:10px 0 0;color:#d63638">' . esc_html__('最近一次发信失败：', 'xi') . esc_html(xi_mail_last_error_text()) . '</p>' : ''),
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'user',
        'title'  => __('积分系统', 'xi'),
        'icon'   => 'fa fa-fw fa-diamond',
        'fields' => array(
            array(
                'id'      => 'points_s',
                'type'    => 'switcher',
                'title'   => __('启用积分', 'xi'),
                'default' => true,
            ),
            array(
                'dependency' => array('points_s', '!=', ''),
                'id'         => 'points_name',
                'type'       => 'text',
                'title'      => __('积分名称', 'xi'),
                'desc'       => __('例如：积分、金币、曦币', 'xi'),
                'default'    => '积分',
            ),
            array(
                'dependency' => array('points_s', '!=', ''),
                'type'       => 'subheading',
                'content'    => __('获取规则', 'xi'),
            ),
            array(
                'dependency' => array('points_s', '!=', ''),
                'id'         => 'checkin_points',
                'type'       => 'number',
                'title'      => __('签到基础奖励', 'xi'),
                'default'    => 5,
            ),
            array(
                'dependency' => array('points_s', '!=', ''),
                'id'         => 'checkin_bonus',
                'type'       => 'number',
                'title'      => __('连续签到加成', 'xi'),
                'desc'       => __('连续签到每多一天额外奖励，最多累计 6 天', 'xi'),
                'default'    => 1,
            ),
            array(
                'dependency' => array('points_s', '!=', ''),
                'id'         => 'points_register',
                'type'       => 'number',
                'title'      => __('注册奖励', 'xi'),
                'default'    => 20,
            ),
            array(
                'dependency' => array('points_s', '!=', ''),
                'id'         => 'points_post',
                'type'       => 'number',
                'title'      => __('发布文章奖励', 'xi'),
                'default'    => 10,
            ),
            array(
                'dependency' => array('points_s', '!=', ''),
                'id'         => 'points_post_limit',
                'type'       => 'number',
                'title'      => __('发布文章每日上限（次）', 'xi'),
                'default'    => 3,
            ),
            array(
                'dependency' => array('points_s', '!=', ''),
                'id'         => 'points_comment',
                'type'       => 'number',
                'title'      => __('评论奖励', 'xi'),
                'desc'       => __('仅审核通过的登录用户评论', 'xi'),
                'default'    => 2,
            ),
            array(
                'dependency' => array('points_s', '!=', ''),
                'id'         => 'points_comment_limit',
                'type'       => 'number',
                'title'      => __('评论每日上限（次）', 'xi'),
                'default'    => 10,
            ),
            array(
                'dependency' => array('points_s', '!=', ''),
                'type'       => 'subheading',
                'content'    => __('付费内容', 'xi'),
            ),
            array(
                'dependency' => array('points_s', '!=', ''),
                'id'         => 'points_author_share',
                'type'       => 'slider',
                'title'      => __('作者分成比例', 'xi'),
                'desc'       => __('用户支付积分解锁文章隐藏内容时，作者获得的比例', 'xi'),
                'min'        => 0,
                'max'        => 100,
                'step'       => 5,
                'unit'       => '%',
                'default'    => 50,
            ),
            array(
                'type'    => 'submessage',
                'style'   => 'info',
                'content' => __('在文章中使用短代码添加隐藏内容：<br><code>[xi_hide type="login"]登录可见[/xi_hide]</code><br><code>[xi_hide type="reply"]评论可见[/xi_hide]</code><br><code>[xi_hide type="points" points="20"]支付积分可见[/xi_hide]</code><br><code>[xi_hide type="vip" vip="1"]会员可见，vip 填 1 或 2 表示最低等级[/xi_hide]</code>', 'xi'),
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'user',
        'title'  => __('余额&卡密', 'xi'),
        'icon'   => 'fa fa-fw fa-credit-card',
        'fields' => array(
            array(
                'id'      => 'balance_s',
                'type'    => 'switcher',
                'title'   => __('启用余额', 'xi'),
                'default' => true,
            ),
            array(
                'dependency' => array('balance_s', '!=', ''),
                'id'         => 'balance_unit',
                'type'       => 'text',
                'title'      => __('余额单位', 'xi'),
                'default'    => '元',
            ),
            array(
                'id'      => 'card_s',
                'type'    => 'switcher',
                'title'   => __('卡密充值', 'xi'),
                'desc'    => sprintf(__('卡密在「用户 → <a href="%s">充值卡密</a>」中生成和管理', 'xi'), esc_url(admin_url('users.php?page=xi_cards'))),
                'default' => true,
            ),
            array(
                'dependency' => array('card_s', '!=', ''),
                'id'         => 'card_tip',
                'type'       => 'textarea',
                'title'      => __('充值说明', 'xi'),
                'desc'       => __('显示在兑换框下方，可填写购买卡密的链接，支持 HTML', 'xi'),
                'sanitize'   => false,
            ),
        ),
    ));

    $vip_fields = array(
        array(
            'id'      => 'vip_s',
            'type'    => 'switcher',
            'title'   => __('启用会员', 'xi'),
            'default' => true,
        ),
        array(
            'dependency' => array('vip_s', '!=', ''),
            'id'         => 'vip_free_points',
            'type'       => 'switcher',
            'title'      => __('会员免费查看积分付费内容', 'xi'),
            'default'    => true,
        ),
    );
    foreach (array(1, 2) as $lv) {
        $d            = xi_vip_defaults($lv);
        $vip_fields[] = array(
            'dependency' => array('vip_s', '!=', ''),
            'id'         => 'vip_' . $lv,
            'type'       => 'fieldset',
            'title'      => sprintf(__('%d 级会员', 'xi'), $lv),
            'fields'     => array(
                array(
                    'id'      => 'enable',
                    'type'    => 'switcher',
                    'title'   => __('启用', 'xi'),
                    'default' => $d['enable'],
                ),
                array(
                    'id'      => 'name',
                    'type'    => 'text',
                    'title'   => __('名称', 'xi'),
                    'default' => $d['name'],
                ),
                array(
                    'id'      => 'color',
                    'type'    => 'color',
                    'title'   => __('标识颜色', 'xi'),
                    'default' => $d['color'],
                ),
                array(
                    'id'      => 'benefits',
                    'type'    => 'textarea',
                    'title'   => __('权益说明', 'xi'),
                    'desc'    => __('每行一条，显示在开通页面', 'xi'),
                    'default' => $d['benefits'],
                ),
                array(
                    'id'      => 'checkin_multi',
                    'type'    => 'number',
                    'title'   => __('签到积分倍数', 'xi'),
                    'default' => $d['checkin_multi'],
                ),
                array(
                    'id'           => 'plans',
                    'type'         => 'group',
                    'title'        => __('套餐', 'xi'),
                    'button_title' => __('添加套餐', 'xi'),
                    'default'      => $d['plans'],
                    'fields'       => array(
                        array('id' => 'name', 'type' => 'text', 'title' => __('套餐名称', 'xi')),
                        array('id' => 'days', 'type' => 'number', 'title' => __('天数', 'xi'), 'desc' => __('0 为永久', 'xi'), 'default' => 30),
                        array('id' => 'price', 'type' => 'number', 'title' => __('余额价格', 'xi'), 'desc' => __('0 为不支持余额支付', 'xi'), 'attributes' => array('step' => '0.01')),
                        array('id' => 'points', 'type' => 'number', 'title' => __('积分价格', 'xi'), 'desc' => __('0 为不支持积分支付', 'xi')),
                    ),
                ),
            ),
        );
    }

    CSF::createSection($prefix, array(
        'parent' => 'user',
        'title'  => __('在线支付', 'xi'),
        'icon'   => 'fa fa-fw fa-credit-card',
        'fields' => array(
            array(
                'id'      => 'pay_s',
                'type'    => 'switcher',
                'title'   => __('启用在线支付', 'xi'),
                'desc'    => __('用于余额充值、积分充值和在线开通会员，订单在后台「用户 → 订单管理」查看', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_method_alipay',
                'type'       => 'select',
                'title'      => __('支付宝', 'xi'),
                'options'    => array(
                    ''            => __('关闭', 'xi'),
                    'alipay_f2f'  => __('支付宝官方 · 当面付（扫码）', 'xi'),
                    'alipay_page' => __('支付宝官方 · 电脑/手机网站支付（跳转）', 'xi'),
                    'epay'        => __('易支付', 'xi'),
                ),
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_method_wxpay',
                'type'       => 'select',
                'title'      => __('微信支付', 'xi'),
                'options'    => array(
                    ''     => __('关闭', 'xi'),
                    'epay' => __('易支付', 'xi'),
                ),
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_method_qqpay',
                'type'       => 'select',
                'title'      => __('QQ钱包', 'xi'),
                'options'    => array(
                    ''     => __('关闭', 'xi'),
                    'epay' => __('易支付', 'xi'),
                ),
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_epay',
                'type'       => 'fieldset',
                'title'      => __('易支付', 'xi'),
                'subtitle'   => __('兼容彩虹易支付等标准易支付接口', 'xi'),
                'fields'     => array(
                    array('id' => 'api_url', 'type' => 'text', 'title' => __('接口地址', 'xi'), 'desc' => __('支付平台提供的网址，例如 https://pay.example.com/', 'xi')),
                    array('id' => 'pid', 'type' => 'text', 'title' => __('商户 ID', 'xi')),
                    array('id' => 'key', 'type' => 'text', 'title' => __('商户密钥', 'xi'), 'attributes' => array('type' => 'password', 'autocomplete' => 'new-password')),
                    array(
                        'id'      => 'mode',
                        'type'    => 'button_set',
                        'title'   => __('支付方式', 'xi'),
                        'default' => 'jump',
                        'options' => array(
                            'jump' => __('跳转到收银台', 'xi'),
                            'api'  => __('站内扫码（接口支付）', 'xi'),
                        ),
                        'desc'    => __('站内扫码需平台支持 mapi.php 接口，不支持时请选择跳转', 'xi'),
                    ),
                ),
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_alipay',
                'type'       => 'fieldset',
                'title'      => __('支付宝官方', 'xi'),
                'subtitle'   => __('在支付宝开放平台创建应用并签约「当面付」或「电脑/手机网站支付」，接口加签方式选择公钥（RSA2）', 'xi'),
                'fields'     => array(
                    array('id' => 'app_id', 'type' => 'text', 'title' => 'AppID'),
                    array('id' => 'private_key', 'type' => 'textarea', 'title' => __('应用私钥', 'xi'), 'desc' => __('支付宝密钥工具生成的应用私钥（PKCS1 或 PKCS8 均可），不含换行也可以', 'xi'), 'attributes' => array('rows' => 4, 'spellcheck' => 'false')),
                    array('id' => 'public_key', 'type' => 'textarea', 'title' => __('支付宝公钥', 'xi'), 'desc' => __('注意是开放平台中的「支付宝公钥」，不是应用公钥', 'xi'), 'attributes' => array('rows' => 4, 'spellcheck' => 'false')),
                    array('id' => 'sandbox', 'type' => 'switcher', 'title' => __('沙箱环境', 'xi'), 'desc' => __('用于测试，正式使用请关闭', 'xi'), 'default' => false),
                ),
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'type'       => 'subheading',
                'content'    => __('商品', 'xi'),
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_recharge_s',
                'type'       => 'switcher',
                'title'      => __('余额充值', 'xi'),
                'desc'       => __('需开启余额系统，1 元 = 1 余额', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_recharge_presets',
                'type'       => 'text',
                'title'      => __('充值档位', 'xi'),
                'desc'       => __('用逗号分隔，用户也可输入自定义金额', 'xi'),
                'default'    => '10,30,50,100,200',
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_recharge_min',
                'type'       => 'number',
                'title'      => __('单笔最低金额', 'xi'),
                'default'    => 1,
                'attributes' => array('step' => '0.01'),
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_recharge_max',
                'type'       => 'number',
                'title'      => __('单笔最高金额', 'xi'),
                'default'    => 5000,
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_points_s',
                'type'       => 'switcher',
                'title'      => __('积分充值', 'xi'),
                'desc'       => __('需开启积分系统', 'xi'),
                'default'    => false,
            ),
            array(
                'dependency' => array(array('pay_s', '!=', ''), array('pay_points_s', '!=', '')),
                'id'         => 'pay_points_rate',
                'type'       => 'number',
                'title'      => __('兑换比例', 'xi'),
                'desc'       => __('1 元可购买的积分数', 'xi'),
                'default'    => 100,
            ),
            array(
                'dependency' => array(array('pay_s', '!=', ''), array('pay_points_s', '!=', '')),
                'id'         => 'pay_points_presets',
                'type'       => 'text',
                'title'      => __('积分充值档位（元）', 'xi'),
                'default'    => '1,5,10,30,50',
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_vip_s',
                'type'       => 'switcher',
                'title'      => __('在线开通会员', 'xi'),
                'desc'       => __('会员套餐的「余额价格」同时作为在线支付价格（元）', 'xi'),
                'default'    => true,
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'id'         => 'pay_tip',
                'type'       => 'text',
                'title'      => __('收银台提示', 'xi'),
                'default'    => '支付成功后自动到账，如遇问题请联系站长',
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'type'       => 'content',
                'title'      => __('回调地址', 'xi'),
                'content'    => '<p>' . esc_html__('异步通知地址由主题自动传给支付平台，无需手动填写。如平台要求配置白名单，请放行：', 'xi') . '</p><code>' . esc_html(admin_url('admin-ajax.php')) . '</code><p class="description">' . esc_html__('请确保该地址能被外网访问，且没有被缓存、防火墙或安全插件拦截', 'xi') . '</p>',
            ),
            array(
                'dependency' => array('pay_s', '!=', ''),
                'type'       => 'content',
                'title'      => __('测试连接', 'xi'),
                'content'    => '<div class="xi-admin-test" data-action="xi_pay_test"><button type="button" class="button button-primary">' . esc_html__('测试支付配置', 'xi') . '</button><p class="description">' . esc_html__('请先保存设置再测试，不会产生真实扣款', 'xi') . '</p></div>',
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'user',
        'title'  => __('商城系统', 'xi'),
        'icon'   => 'fa fa-fw fa-shopping-cart',
        'fields' => function_exists('xi_shop_option_fields') ? xi_shop_option_fields() : array(),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'user',
        'title'  => __('VIP会员', 'xi'),
        'icon'   => 'fa fa-fw fa-star',
        'fields' => $vip_fields,
    ));

    /* ---------- 扩展&增强 ---------- */

    CSF::createSection($prefix, array(
        'parent' => 'user',
        'title'  => __('封禁管理', 'xi'),
        'icon'   => 'fa fa-fw fa-ban',
        'fields' => array(
            array(
                'type'    => 'submessage',
                'style'   => 'info',
                'content' => __('<b>用户封禁：</b>管理员打开用户的个人主页，点击「封禁」即可一键禁言或封号，可设置时长与原因，并可同时封禁 TA 的 IP。封号后该用户立即下线且无法登录；禁言后可以浏览，但不能评论、发帖、投稿。到期自动解除。所有用户的封禁状态与最近 IP 可在后台「用户」列表查看。', 'xi'),
            ),
            array(
                'type'    => 'subheading',
                'content' => __('IP 封禁', 'xi'),
            ),
            array(
                'id'      => 'ip_ban_s',
                'type'    => 'switcher',
                'title'   => __('启用 IP 黑名单', 'xi'),
                'desc'    => __('在前台封禁用户时勾选「同时封禁 IP」会自动开启', 'xi'),
                'default' => false,
            ),
            array(
                'dependency' => array('ip_ban_s', '!=', ''),
                'id'         => 'ip_ban_scope',
                'type'       => 'button_set',
                'title'      => __('封禁范围', 'xi'),
                'default'    => 'action',
                'options'    => array(
                    'action' => __('禁止登录、注册、评论、发帖、投稿', 'xi'),
                    'all'    => __('禁止访问整站', 'xi'),
                ),
                'desc'       => __('整站模式下已登录的管理员与 wp-login.php 不受限制，误封自己也能登录后台解除', 'xi'),
            ),
            array(
                'dependency' => array('ip_ban_s', '!=', ''),
                'id'         => 'ip_ban_list',
                'type'       => 'textarea',
                'title'      => __('IP 黑名单', 'xi'),
                'desc'       => __('每行一条，# 后面是备注。支持：单个 IP（1.2.3.4）、通配符（1.2.3.*）、网段（1.2.3.0/24、2408:8000::/32）', 'xi'),
                'attributes' => array('rows' => 8, 'style' => 'font-family:Consolas,monospace', 'placeholder' => "1.2.3.4 # 广告机器人
5.6.7.*
10.0.0.0/8"),
                'sanitize'   => false,
            ),
            array(
                'dependency' => array('ip_ban_s', '!=', ''),
                'id'         => 'ip_ban_msg',
                'type'       => 'text',
                'title'      => __('拦截提示', 'xi'),
                'default'    => '你的 IP 已被本站限制访问，如有疑问请联系站长',
            ),
            array(
                'id'      => 'client_ip_source',
                'type'    => 'select',
                'title'   => __('访客 IP 获取方式', 'xi'),
                'desc'    => __('站点使用了 CDN 或反向代理时，REMOTE_ADDR 是 CDN 节点的地址，需改为对应的请求头，否则会把 CDN 节点封掉。不确定时保持默认', 'xi') . '<br>' . sprintf(__('当前识别到你的 IP：%s', 'xi'), '<code>' . esc_html(function_exists('xi_client_ip') ? xi_client_ip() : '') . '</code>'),
                'default' => 'remote',
                'options' => array(
                    'remote' => __('REMOTE_ADDR（未使用 CDN）', 'xi'),
                    'xff'    => __('X-Forwarded-For（大多数 CDN、Nginx 反代）', 'xi'),
                    'real'   => __('X-Real-IP（Nginx 反代）', 'xi'),
                    'cf'     => __('CF-Connecting-IP（Cloudflare）', 'xi'),
                ),
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'over',
        'title'  => __('自定义代码', 'xi'),
        'icon'   => 'fa fa-fw fa-code',
        'fields' => array(
            array(
                'id'         => 'custom_css',
                'type'       => 'textarea',
                'title'      => __('自定义CSS', 'xi'),
                'attributes' => array('rows' => 10, 'spellcheck' => 'false', 'style' => 'font-family:Consolas,Menlo,monospace'),
                'sanitize'   => false,
            ),
            array(
                'id'       => 'head_code',
                'type'     => 'textarea',
                'title'    => __('头部代码', 'xi'),
                'desc'     => __('输出在 &lt;/head&gt; 之前，可放统计代码', 'xi'),
                'sanitize' => false,
            ),
            array(
                'id'       => 'foot_code',
                'type'     => 'textarea',
                'title'    => __('底部代码', 'xi'),
                'desc'     => __('输出在 &lt;/body&gt; 之前', 'xi'),
                'sanitize' => false,
            ),
        ),
    ));

    CSF::createSection($prefix, array(
        'parent' => 'over',
        'title'  => __('备份&导入', 'xi'),
        'icon'   => 'fa fa-fw fa-copy',
        'fields' => array(
            array(
                'type' => 'backup',
            ),
        ),
    ));

    /* ---------- 关于主题 ---------- */

    CSF::createSection($prefix, array(
        'id'     => 'about',
        'title'  => __('关于主题', 'xi'),
        'icon'   => 'fa fa-fw fa-info-circle',
        'fields' => array(
            array(
                'type'    => 'content',
                'content' => xi_about_html(),
            ),
        ),
    ));
}

/**
 * 关于主题：开发者与交流入口。地址暂未公开，留空时按钮显示为「即将开放」
 */
function xi_about_links()
{
    return array(
        array(
            'icon'  => 'fa-github',
            'title' => __('GitHub', 'xi'),
            'desc'  => __('源码、更新日志与问题反馈', 'xi'),
            'url'   => '',
        ),
        array(
            'icon'  => 'fa-qq',
            'title' => __('QQ 交流群', 'xi'),
            'desc'  => __('使用交流、建议与新版本通知', 'xi'),
            'url'   => '',
        ),
    );
}

function xi_about_html()
{
    $color = function_exists('xi_theme_color') ? xi_theme_color() : '#2bb39a';
    $html  = '<style>
        .xi-about{display:grid;gap:18px;max-width:760px}
        .xi-about-head{display:flex;align-items:center;gap:16px}
        .xi-about-logo{display:grid;place-items:center;flex:none;width:60px;height:60px;border-radius:16px;background:linear-gradient(145deg,color-mix(in srgb,' . esc_attr($color) . ' 75%,#fff),' . esc_attr($color) . ');color:#fff;font-size:28px;font-weight:700;box-shadow:0 10px 24px -12px ' . esc_attr($color) . '}
        .xi-about-head h2{margin:0 0 4px;font-size:22px;line-height:1.3}
        .xi-about-head p{margin:0;color:#646970}
        .xi-about-ver{display:inline-block;margin-left:8px;padding:1px 8px;border-radius:999px;background:color-mix(in srgb,' . esc_attr($color) . ' 14%,transparent);color:' . esc_attr($color) . ';font-size:12px;font-weight:600;vertical-align:3px}
        .xi-about-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin:0;padding:0;list-style:none}
        .xi-about-meta li{padding:10px 14px;border:1px solid #e2e4e7;border-radius:10px;background:#fff}
        .xi-about-meta span{display:block;color:#8c8f94;font-size:12px}
        .xi-about-meta b{font-size:14px}
        .xi-about-links{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}
        .xi-about-link{display:flex;align-items:center;gap:14px;padding:16px;border:1px solid #e2e4e7;border-radius:12px;background:#fff;color:#1d2327;text-decoration:none;transition:border-color .15s,box-shadow .15s,transform .15s}
        a.xi-about-link:hover{border-color:' . esc_attr($color) . ';box-shadow:0 8px 20px -14px rgba(0,0,0,.4);transform:translateY(-1px);color:#1d2327}
        .xi-about-link.is-soon{cursor:default;opacity:.75}
        .xi-about-link .fa{display:grid;place-items:center;flex:none;width:44px;height:44px;border-radius:12px;background:#f0f0f1;font-size:22px}
        .xi-about-link b{display:block;font-size:15px}
        .xi-about-link small{color:#646970}
        .xi-about-tag{margin-left:auto;flex:none;padding:2px 10px;border-radius:999px;background:#f0f0f1;color:#8c8f94;font-size:12px}
        a.xi-about-link .xi-about-tag{background:color-mix(in srgb,' . esc_attr($color) . ' 14%,transparent);color:' . esc_attr($color) . '}
    </style>';
    $html .= '<div class="xi-about">';
    $html .= '<div class="xi-about-head"><span class="xi-about-logo">曦</span><div><h2>' . esc_html__('曦主题', 'xi') . '<span class="xi-about-ver">' . esc_html(XI_VERSION) . '</span></h2><p>' . esc_html__('淡白与青薄荷配色的 WordPress 主题 · WinUI 3 × 液态玻璃', 'xi') . '</p></div></div>';
    $html .= '<ul class="xi-about-meta">';
    $html .= '<li><span>' . esc_html__('开发者', 'xi') . '</span><b>' . esc_html__('曦芒', 'xi') . '</b></li>';
    $html .= '<li><span>' . esc_html__('当前版本', 'xi') . '</span><b>' . esc_html(XI_VERSION) . '</b></li>';
    $html .= '<li><span>' . esc_html__('内部版本号', 'xi') . '</span><b>' . esc_html(XI_BUILD) . '</b></li>';
    $html .= '<li><span>' . esc_html__('运行环境', 'xi') . '</span><b>WordPress ' . esc_html(get_bloginfo('version')) . ' · PHP ' . esc_html(PHP_VERSION) . '</b></li>';
    $html .= '</ul>';
    $html .= '<div class="xi-about-links">';
    foreach (xi_about_links() as $link) {
        $inner = '<i class="fa ' . esc_attr($link['icon']) . '" aria-hidden="true"></i><span><b>' . esc_html($link['title']) . '</b><small>' . esc_html($link['desc']) . '</small></span>';
        if ($link['url'] !== '') {
            $html .= '<a class="xi-about-link" href="' . esc_url($link['url']) . '" target="_blank" rel="noopener">' . $inner . '<span class="xi-about-tag">' . esc_html__('前往', 'xi') . ' ↗</span></a>';
        } else {
            $html .= '<div class="xi-about-link is-soon">' . $inner . '<span class="xi-about-tag">' . esc_html__('即将开放', 'xi') . '</span></div>';
        }
    }
    $html .= '</div></div>';
    return $html;
}
/**
 * 权限管理字段：每项一组用户组复选框
 */
function xi_auth_option_fields()
{
    $roles  = xi_role_options();
    $groups = array(
        __('内容发布', 'xi') => array(
            'write'         => array(__('前台投稿', 'xi'), __('可以在前台 /write/ 发布新文章、编辑自己未发布的文章', 'xi')),
            'publish'       => array(__('投稿免审核', 'xi'), __('投稿直接发布，其他用户组提交后进入待审核', 'xi')),
            'edit_publish'  => array(__('修改已发布的文章', 'xi'), __('可以在前台修改自己已经发布的文章', 'xi')),
            'edit_noreview' => array(__('修改免审核', 'xi'), __('修改已发布的文章后保持发布，其他用户组修改后重新进入待审核', 'xi')),
            'upload'        => array(__('上传图片', 'xi'), __('在编辑器中上传正文图片和封面', 'xi')),
            'nolimit'       => array(__('不受每日投稿上限', 'xi'), __('上限在「文章&列表 → 前台投稿」中设置', 'xi')),
        ),
        __('论坛', 'xi') => array(
            'bbs_post'    => array(__('论坛发帖', 'xi'), __('需在「论坛社区」中开启论坛；版块可单独设为仅版主发帖', 'xi')),
            'bbs_publish' => array(__('发帖免审核', 'xi'), __('其他用户组发帖后进入待审核，由版主或管理员审核', 'xi')),
        ),
        __('互动', 'xi') => array(
            'comment' => array(__('发表评论', 'xi'), __('同时受「设置 → 讨论」中“必须登录才能评论”的限制', 'xi'), true),
        ),
        __('积分 / 会员 / 余额', 'xi') => array(
            'checkin' => array(__('每日签到', 'xi'), __('需开启积分系统', 'xi')),
            'unlock'  => array(__('积分解锁付费内容', 'xi'), __('不在范围内的用户无法用积分购买隐藏内容', 'xi')),
            'vip_buy' => array(__('开通会员', 'xi'), __('需开启 VIP 会员', 'xi')),
            'redeem'  => array(__('卡密兑换', 'xi'), __('需开启卡密充值', 'xi')),
        ),
        __('后台', 'xi') => array(
            'admin' => array(__('访问 WordPress 后台', 'xi'), __('未勾选的用户组完全不能进入后台（包括个人资料页），访问时跳转到前台用户中心；资料、头像、邮箱、密码都在用户中心「账号设置」修改。默认只有管理员可以进入', 'xi')),
        ),
    );

    $fields = array(
        array(
            'type'    => 'notice',
            'style'   => 'info',
            'content' => __('勾选允许使用该功能的用户组。管理员始终拥有全部权限；勾选「VIP 会员」后，任意角色的有效会员都可使用。', 'xi'),
        ),
    );
    foreach ($groups as $heading => $items) {
        $fields[] = array(
            'type'    => 'subheading',
            'content' => $heading,
        );
        foreach ($items as $key => $item) {
            $fields[] = array(
                'id'      => 'auth_' . $key,
                'type'    => 'checkbox',
                'title'   => $item[0],
                'desc'    => $item[1],
                'inline'  => true,
                'options' => empty($item[2]) ? $roles : xi_role_options(true),
                'default' => xi_auth_defaults($key),
            );
        }
    }
    return $fields;
}

// 翻译要在 after_setup_theme 之后才能加载；CSF 在同一钩子的优先级 10 处理设置，这里需要更早注册
add_action('after_setup_theme', 'xi_csf_admin_options', 5);
