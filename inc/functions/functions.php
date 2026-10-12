<?php
/**
 * 主题基础功能
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_theme_setup()
{
    load_theme_textdomain('xi', get_template_directory() . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));

    set_post_thumbnail_size(480, 320, true);

    register_nav_menus(array(
        'primary' => __('顶部导航', 'xi'),
        'footer'  => __('页脚导航', 'xi'),
    ));
}
add_action('after_setup_theme', 'xi_theme_setup');

function xi_enqueue_scripts()
{
    $uri = get_template_directory_uri();

    wp_enqueue_style('xi-main', $uri . '/assets/css/main.css', array(), XI_VERSION);
    wp_add_inline_style('xi-main', xi_inline_css());

    wp_enqueue_script('xi-main', $uri . '/assets/js/main.js', array(), XI_VERSION, true);
    wp_enqueue_script('xi-interact', $uri . '/assets/js/interact.js', array('xi-main'), XI_VERSION, true);

    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'xi_enqueue_scripts');

/**
 * 主题自带的 CSS / JS 版本号追加文件修改时间
 * 版本号不变时（同一测试版内多次更新）浏览器和 CDN 也会拿到新文件，不会出现新结构配旧样式
 */
function xi_asset_version($src)
{
    static $base = null;
    if ($base === null) {
        $base = preg_replace('#^https?:#', '', get_template_directory_uri());
    }
    $plain = preg_replace('#^https?:#', '', (string) $src);
    if (strpos($plain, $base . '/') !== 0) {
        return $src;
    }
    $path = get_template_directory() . strtok(substr($plain, strlen($base)), '?');
    if (is_file($path)) {
        $src = add_query_arg('ver', XI_VERSION . '.' . XI_BUILD . '.' . filemtime($path), $src);
    }
    return $src;
}
add_filter('style_loader_src', 'xi_asset_version', 20);
add_filter('script_loader_src', 'xi_asset_version', 20);

/**
 * 主题色、圆角、玻璃模糊度与自定义 CSS
 */
function xi_inline_css()
{
    $radius = (int) _xz('radius', 16);
    $blur   = (int) _xz('glass_blur', 24);

    $css  = ':root{--xi-main:' . xi_theme_color() . ';';
    $css .= '--xi-radius:' . $radius . 'px;--xi-ctrl-radius:' . xi_ctrl_radius($radius) . 'px;--xi-blur:' . $blur . 'px;}';
    if ($dark = xi_theme_color('dark')) {
        $css .= '[data-theme="dark"]{--xi-main:' . $dark . ';}';
    }

    $custom = _xz('custom_css');
    if ($custom) {
        $css .= wp_strip_all_tags($custom);
    }
    return $css;
}

/**
 * 按钮、输入框圆角：卡片圆角的一半，前后台共用
 */
function xi_ctrl_radius($radius = null)
{
    $radius = $radius === null ? (int) _xz('radius', 16) : (int) $radius;
    return (int) round($radius / 2);
}

/**
 * 在渲染前确定深浅色，避免闪烁
 */
function xi_theme_mode_script()
{
    $mode = _xz('theme_mode', 'auto');
    $mode = in_array($mode, array('light', 'dark', 'auto'), true) ? $mode : 'auto';
    echo "<script>(function(){var m='" . $mode . "',s=null;try{s=localStorage.getItem('xi-theme')}catch(e){}var d=s?s==='dark':(m==='dark'||(m==='auto'&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches));document.documentElement.setAttribute('data-theme',d?'dark':'light')})();</script>\n";
}
add_action('wp_head', 'xi_theme_mode_script', 0);

function xi_wp_head()
{
    // 移动端浏览器地址栏颜色跟随主题色
    echo '<meta name="theme-color" content="' . esc_attr(xi_theme_color()) . '">' . "
";

    $head_code = _xz('head_code');
    if ($head_code) {
        echo $head_code . "\n"; // 管理员自定义代码，原样输出
    }
}
add_action('wp_head', 'xi_wp_head', 99);

/**
 * 浏览器图标接入 WordPress 站点图标
 * 前台、登录页、后台以及 /favicon.ico 均由 wp_site_icon() / do_favicon() 统一输出，
 * 避免与 WP 自带的带尺寸图标冲突；协议跟随当前页面，防止 https 下被当作混合内容拦截
 */
function xi_site_icon_url($url)
{
    $favicon = trim((string) _xz('favicon'));
    return $favicon ? set_url_scheme($favicon) : $url;
}
add_filter('get_site_icon_url', 'xi_site_icon_url');

function xi_wp_footer()
{
    $foot_code = _xz('foot_code');
    if ($foot_code) {
        echo $foot_code . "\n"; // 管理员自定义代码，原样输出
    }
}
add_action('wp_footer', 'xi_wp_footer', 99);

function xi_body_class($classes)
{
    if (_xz('header_sticky', true)) {
        $classes[] = 'xi-sticky';
    }
    if (!xi_has_sidebar()) {
        $classes[] = 'xi-no-sidebar';
    }
    return $classes;
}
add_filter('body_class', 'xi_body_class');

/**
 * 中文摘要按字数截取
 */
function xi_get_excerpt($post = null)
{
    $post   = get_post($post);
    $length = (int) _xz('excerpt_length', 90);

    // 没有手写摘要时优先用 AI 摘要（见 xi-ai.php）
    $ai = has_excerpt($post) ? '' : (string) apply_filters('xi_ai_excerpt', '', $post);
    if ($ai !== '') {
        return mb_strlen($ai) > $length ? mb_substr($ai, 0, $length) . '…' : $ai;
    }

    $text = has_excerpt($post) ? $post->post_excerpt : $post->post_content;
    // 先去掉隐藏内容（短代码与区块），免得付费内容出现在摘要和 SEO 描述里
    $text = function_exists('xi_strip_hidden') ? xi_strip_hidden($text) : $text;
    // 块级元素结束处补空格，否则相邻段落、区块的文字会连在一起；行内标签不补，避免中文里多出空格
    $text = preg_replace('#</(p|div|h[1-6]|li|blockquote|pre|td|th|figcaption|summary|details)>|<br\s*/?>|<!-- /?wp:[^>]*-->#i', '$0 ', $text);
    $text = wp_strip_all_tags(strip_shortcodes($text));
    $text = trim((string) preg_replace('/\s+/u', ' ', $text));

    return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
}

/**
 * 当前页面是否显示侧边栏
 */
function xi_has_sidebar()
{
    if (is_404() || (function_exists('xi_is_user_center') && xi_is_user_center()) || (function_exists('xi_is_write_page') && xi_is_write_page()) || (function_exists('xi_is_bbs_new') && xi_is_bbs_new())) {
        return false;
    }
    if (function_exists('xi_is_shop_page') && xi_is_shop_page()) {
        return false;
    }
    if (is_singular() && xi_post_meta('hide_sidebar', false, get_queried_object_id())) {
        return false;
    }
    return true;
}
