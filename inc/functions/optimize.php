<?php
/**
 * SEO 与性能优化
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------- SEO ---------- */

/**
 * 当前页面的描述
 */
function xi_seo_description()
{
    if (is_front_page() || is_home()) {
        $desc = _xz('seo_description');
        return $desc ? $desc : get_bloginfo('description');
    }
    if (is_singular()) {
        $desc = xi_post_meta('description', '', get_queried_object_id());
        return $desc ? $desc : xi_get_excerpt(get_queried_object_id());
    }
    if (is_category() || is_tag() || is_tax()) {
        return wp_strip_all_tags(term_description());
    }
    return '';
}

function xi_seo_head()
{
    if (!_xz('seo_s', true)) {
        return;
    }

    $desc = trim((string) preg_replace('/\s+/u', ' ', xi_seo_description()));
    if ($desc) {
        echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    }

    if (is_front_page() || is_home()) {
        $keywords = _xz('seo_keywords', '博客,学习笔记,读书,前端');
    } elseif (is_singular('post')) {
        $tags     = wp_get_post_tags(get_queried_object_id(), array('fields' => 'names'));
        $keywords = implode(',', $tags);
    } else {
        $keywords = '';
    }
    if ($keywords) {
        echo '<meta name="keywords" content="' . esc_attr($keywords) . '">' . "\n";
    }

    if (is_singular()) {
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr(get_the_title(get_queried_object_id())) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink(get_queried_object_id())) . '">' . "\n";
        if (has_post_thumbnail(get_queried_object_id())) {
            echo '<meta property="og:image" content="' . esc_url(get_the_post_thumbnail_url(get_queried_object_id(), 'large')) . '">' . "\n";
        }
    }
}
add_action('wp_head', 'xi_seo_head', 1);

/* ---------- 性能与精简 ---------- */

function xi_optimize_init()
{
    if (_xz('modern_front_s', true)) {
        // Emoji 脚本
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('emoji_svg_url', '__return_false');
        // 版本号、RSD、WLW 等头部标签
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'feed_links_extra', 3);
        add_filter('the_generator', '__return_empty_string');
        // 古腾堡全局样式与 SVG 滤镜，主题自带区块样式
        remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
        remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
        remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
        // oEmbed 发现与嵌入脚本
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        remove_action('wp_head', 'rest_output_link_wp_head');
        // 旧式内联样式
        add_filter('use_default_gallery_style', '__return_false');
        add_filter('show_recent_comments_widget_style', '__return_false');
        add_filter('wp_resource_hints', 'xi_remove_dns_prefetch', 10, 2);
        add_filter('get_the_archive_title_prefix', '__return_empty_string');
    }

    if (_xz('hide_admin_bar', true)) {
        add_filter('show_admin_bar', '__return_false');
    }
}
add_action('init', 'xi_optimize_init');

/**
 * 移除区块库、经典主题兼容样式和 wp-embed，改由主题 main.css 提供区块样式
 */
function xi_dequeue_legacy_assets()
{
    if (!_xz('modern_front_s', true)) {
        return;
    }
    foreach (array('wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'core-block-supports') as $handle) {
        wp_dequeue_style($handle);
    }
    wp_deregister_script('wp-embed');
}
add_action('wp_enqueue_scripts', 'xi_dequeue_legacy_assets', 100);

function xi_remove_dns_prefetch($hints, $relation_type)
{
    if ($relation_type === 'dns-prefetch') {
        $hints = array_filter($hints, function ($hint) {
            $url = is_array($hint) ? (isset($hint['href']) ? $hint['href'] : '') : $hint;
            return strpos($url, 's.w.org') === false;
        });
    }
    return $hints;
}

/**
 * 前台管理条添加主题设置入口
 */
function xi_admin_bar_options_link($wp_admin_bar)
{
    if (!current_user_can('manage_options') || !class_exists('CSF')) {
        return;
    }
    $wp_admin_bar->add_node(array(
        'parent' => 'site-name',
        'id'     => 'xi-options',
        'title'  => __('曦主题设置', 'xi'),
        'href'   => admin_url('admin.php?page=' . XI_OPTIONS),
    ));
}
add_action('admin_bar_menu', 'xi_admin_bar_options_link', 99);
