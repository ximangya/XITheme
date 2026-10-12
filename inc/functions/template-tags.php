<?php
/**
 * 模板输出函数
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 主题内置图标集：名称 => SVG 内部路径
 */
function xi_icon_set()
{
    return array(
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'menu'    => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'comment' => '<path d="M5 18.5V6a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H8.5z"/>',
        'folder'  => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'up'      => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'book'    => '<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z"/><path d="M4 21V5"/>',
        'leaf'    => '<path d="M5 19c0-8 5-13 14-14-1 9-6 14-14 14z"/><path d="M5 19l7-7"/>',
        'chevron' => '<path d="m9 6 6 6-6 6"/>',
        'info'    => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'check'   => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'alert'   => '<path d="M12 4 2.5 20h19z"/><path d="M12 10v4M12 17h.01"/>',
        'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'    => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
        'gear'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'code'     => '<path d="m8 8-4 4 4 4M16 8l4 4-4 4M14 4l-4 16"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
        'star'     => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2l1.1-6.2L3 9.6l6.2-.9z"/>',
        'heart'    => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
        'eye'      => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'share'    => '<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4"/>',
        'zoom'     => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.4-4.4M11 8v6M8 11h6"/>',
        'copy'     => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"/>',
        'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'key'      => '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 8.7-8.7M17 6l2.5 2.5M14.5 8.5 16 10"/>',
        'camera'   => '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/>',
        'edit'     => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'login'    => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 16l4-4-4-4M14 12H4"/>',
        'logout'   => '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="m16 16 4-4-4-4M20 12H10"/>',
        'grid'     => '<rect x="4" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1.5"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1.5"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1.5"/>',
        'layout'   => '<rect x="3.5" y="4" width="17" height="16" rx="2"/><path d="M3.5 9h17M10 9v11"/>',
        'list'     => '<path d="M9 6h11M9 12h11M9 18h11M4.5 6h.01M4.5 12h.01M4.5 18h.01"/>',
        'crown'    => '<path d="M4 18 3 7l5 4 4-6 4 6 5-4-1 11z"/><path d="M4 21h16"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
        'ban'      => '<circle cx="12" cy="12" r="8.5"/><path d="m6 6 12 12"/>',
        'lock'     => '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/>',
        'coin'     => '<circle cx="12" cy="12" r="8.5"/><path d="M9.5 9.5h4a1.5 1.5 0 0 1 0 3h-3a1.5 1.5 0 0 0 0 3h4M12 7.5v1.5M12 15.5V17"/>',
        'wallet'   => '<path d="M4 7a2 2 0 0 1 2-2h11v4"/><rect x="4" y="9" width="16" height="11" rx="2"/><path d="M16 14.5h.01"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'gift'     => '<rect x="4" y="9" width="16" height="11" rx="1.5"/><path d="M3 9h18M12 9v11M12 9c-2-4-6-4-6-1.5S10 9 12 9zM12 9c2-4 6-4 6-1.5S14 9 12 9z"/>',
        'undo'      => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
        'redo'      => '<path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/>',
        'bold'      => '<path d="M7 5h6a3.5 3.5 0 0 1 0 7H7zM7 12h7a3.5 3.5 0 0 1 0 7H7z"/>',
        'italic'    => '<path d="M10 5h8M6 19h8M14.5 5l-5 14"/>',
        'underline' => '<path d="M7 4v7a5 5 0 0 0 10 0V4M5 20h14"/>',
        'strike'    => '<path d="M4 12h16M16.5 7.5C16 5.6 14.2 4.5 12 4.5c-2.6 0-4.5 1.4-4.5 3.5 0 1.3.7 2.3 2 3M8 16.5c.5 1.9 2.2 3 4.3 3 2.7 0 4.7-1.4 4.7-3.6 0-.8-.2-1.4-.6-1.9"/>',
        'listol'    => '<path d="M10 6h10M10 12h10M10 18h10M4 5h1.5v4M4 9h3M4 15.5a1.5 1.5 0 0 1 3 0c0 1.5-3 2-3 3.5h3"/>',
        'quote'     => '<path d="M5 18c2.5-1 4-3.2 4-6V7H5v5h3M14 18c2.5-1 4-3.2 4-6V7h-4v5h3"/>',
        'terminal'  => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="m7.5 10 3 2.5-3 2.5M12.5 15.5h4"/>',
        'minus'     => '<path d="M4 12h16"/>',
        'link'      => '<path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4L11.5 6"/><path d="M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1.5-1.5"/>',
        'image'     => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="m20.5 16-5-5-9 8.5"/>',
        'eraser'    => '<path d="m7 20-3.3-3.3a1.5 1.5 0 0 1 0-2.1L13.6 4.7a1.5 1.5 0 0 1 2.1 0l4.6 4.6a1.5 1.5 0 0 1 0 2.1L11.4 20H7zM20 20h-8.6M9 9.3l6 6"/>',
        'expand'    => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
        'hash'    => '<path d="M5 9h14M5 15h14M10 4 8 20M16 4l-2 16"/>',
        // 菜单常用
        'home'     => '<path d="M4 11 12 4l8 7"/><path d="M6 10v10h12V10"/><path d="M10 20v-5h4v5"/>',
        'tag'      => '<path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="8.5" r="1.3"/>',
        'fire'     => '<path d="M12 21c-4 0-7-2.7-7-6.5 0-3 2-5 3.5-6.5.3 1.6 1.2 2.6 2.2 3C10.5 7.5 12 5 14 3c.4 3 2.5 4.6 3.7 6.3A7 7 0 0 1 19 14.5C19 18.3 16 21 12 21z"/>',
        'music'    => '<path d="M9 18V5l11-2v13"/><circle cx="6.5" cy="18" r="2.5"/><circle cx="17.5" cy="16" r="2.5"/>',
        'play'     => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3z"/>',
        'chat'     => '<path d="M4 5h16v11H9l-5 4z"/>',
        'bell'     => '<path d="M6 16v-5a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'compass'  => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
        'bookmark' => '<path d="M6 3h12v18l-6-4-6 4z"/>',
        'archive'  => '<rect x="3" y="4" width="18" height="5" rx="1"/><path d="M5 9v11h14V9M10 13h4"/>',
        'pin'      => '<path d="M9 4h6l-1 5 3 3v2H7v-2l3-3z"/><path d="M12 14v6"/>',
        'trash'    => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'more'     => '<circle cx="5" cy="12" r="1.2"/><circle cx="12" cy="12" r="1.2"/><circle cx="19" cy="12" r="1.2"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        'flame'    => '<path d="M12 21a7 7 0 0 0 7-7c0-4-3-6-4-9-1 3-3 4-4 4 0-2-1-4-2-5-2 4-4 6-4 10a7 7 0 0 0 7 7z"/>',
        // 商城
        'cart'     => '<path d="M3 4h2.2l2.2 10.5a1.5 1.5 0 0 0 1.5 1.2h8.4a1.5 1.5 0 0 0 1.5-1.1L20.5 8H6.1"/><circle cx="9.5" cy="19.5" r="1.3"/><circle cx="17" cy="19.5" r="1.3"/>',
        'bag'      => '<path d="M5 8h14l-1 12H6z"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/>',
        'truck'    => '<path d="M3 6h11v10H3zM14 9.5h4l3 3.5v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>',
        'box'      => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
    );
}

/**
 * 内联 SVG 图标，前台不依赖图标字体
 */
function xi_icon($name)
{
    static $icons = null;
    if ($icons === null) {
        $icons = xi_icon_set();
    }
    if (!isset($icons[$name])) {
        return '';
    }
    return '<svg class="xi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $icons[$name] . '</svg>';
}

function xi_logo()
{
    $logo = _xz('logo');
    $name = get_bloginfo('name');

    echo '<a class="xi-logo" href="' . esc_url(home_url('/')) . '" rel="home">';
    if ($logo) {
        echo '<img src="' . esc_url($logo) . '" alt="' . esc_attr($name) . '">';
    } else {
        echo '<span class="xi-logo-mark">' . xi_icon('leaf') . '</span>';
    }
    if (!$logo) {
        echo '<span class="xi-logo-text">' . esc_html($name) . '</span>';
    }
    echo '</a>';
}

/**
 * 未设置菜单时的默认导航
 */
function xi_menu_fallback()
{
    echo '<ul class="xi-menu">';
    echo '<li class="' . (is_front_page() ? 'current-menu-item' : '') . '"><a href="' . esc_url(home_url('/')) . '">' . esc_html__('首页', 'xi') . '</a></li>';
    wp_list_pages(array('title_li' => '', 'depth' => 1));
    echo '</ul>';
}

/**
 * 预计阅读时长（按每分钟 400 字估算）
 */
function xi_reading_minutes($post = null)
{
    $post  = get_post($post);
    $words = mb_strlen((string) preg_replace('/\s+/u', '', wp_strip_all_tags(strip_shortcodes($post->post_content))));
    return max(1, (int) ceil($words / 400));
}

function xi_entry_meta($show_reading = false)
{
    echo '<div class="xi-meta">';
    echo '<span>' . xi_icon('clock') . '<time datetime="' . esc_attr(get_the_date('c')) . '">' . esc_html(get_the_date('Y-m-d')) . '</time></span>';
    if ($show_reading) {
        echo '<span>' . xi_icon('book') . sprintf(esc_html__('约 %d 分钟读完', 'xi'), xi_reading_minutes()) . '</span>';
    }
    if (_xz('views_s', true)) {
        echo '<span class="xi-meta-views">' . xi_icon('eye') . '<span data-xi-views>' . esc_html(xi_number(xi_get_views(get_the_ID()))) . '</span></span>';
    }
    if (comments_open() || get_comments_number()) {
        echo '<span>' . xi_icon('comment') . esc_html(get_comments_number()) . '</span>';
    }
    echo '</div>';
}

/**
 * 文章列表样式：list 单列 / grid 双列卡片 / card 三列卡片 / big 大图卡片 / mini 极简
 */
function xi_list_style()
{
    $style = _xz('list_style', 'list');
    return in_array($style, array('list', 'grid', 'card', 'big', 'mini'), true) ? $style : 'list';
}

/**
 * 卡片类样式是否显示 Mac 窗口三点
 */
function xi_list_mac()
{
    return in_array(xi_list_style(), array('grid', 'card', 'big'), true) && _xz('list_mac_s', true);
}

/**
 * 文章列表容器
 */
function xi_post_list_class()
{
    $style = xi_list_style();
    $class = 'xi-post-list xi-list-' . $style;
    if ($style === 'grid') {
        $class .= ' xi-post-grid';
    }
    if ($style === 'mini') {
        $class .= ' xi-card';
    }
    return $class;
}

/**
 * 同分类相关文章
 */
function xi_related_posts()
{
    if (!_xz('related_s', true)) {
        return;
    }
    $post_id = get_the_ID();
    xi_cache_fragment('related|' . $post_id . '|' . (int) _xz('related_count', 4), 12 * HOUR_IN_SECONDS, 'xi_related_posts_render');
}

function xi_related_posts_render()
{
    $cats = wp_get_post_categories(get_the_ID());
    if (!$cats) {
        return;
    }
    $query = new WP_Query(array(
        'category__in'        => $cats,
        'post__not_in'        => array(get_the_ID()),
        'posts_per_page'      => (int) _xz('related_count', 4),
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    ));
    if (!$query->have_posts()) {
        return;
    }

    echo '<section class="xi-card xi-related">';
    echo '<h3 class="xi-section-title">' . esc_html__('相关文章', 'xi') . '</h3>';
    echo '<ul>';
    while ($query->have_posts()) {
        $query->the_post();
        echo '<li><a href="' . esc_url(get_permalink()) . '">';
        echo '<span class="xi-related-thumb">';
        if (has_post_thumbnail()) {
            the_post_thumbnail('post-thumbnail', array('loading' => 'lazy'));
        } else {
            echo xi_icon('leaf');
        }
        echo '</span>';
        echo '<span class="xi-related-title">' . esc_html(get_the_title()) . '</span>';
        echo '<time>' . esc_html(get_the_date('Y-m-d')) . '</time>';
        echo '</a></li>';
    }
    echo '</ul></section>';
    wp_reset_postdata();
}

function xi_entry_category()
{
    $cats = get_the_category();
    if (!$cats) {
        return;
    }
    echo '<a class="xi-cat" href="' . esc_url(get_category_link($cats[0])) . '">' . esc_html($cats[0]->name) . '</a>';
}

function xi_footer_copyright()
{
    $text = _xz('footer_copyright', '© {year} {site}');
    $text = str_replace(array('{year}', '{site}'), array(date_i18n('Y'), get_bloginfo('name')), $text);
    echo esc_html($text);
}
