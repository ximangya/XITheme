<?php
/**
 * 站点美化：源泉圆体、LOGO 扫光、动态标题、访问灵动岛、樱花 / 下雪飘落
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_font_gensen()
{
    return _xz('font_family', 'system') === 'gensen';
}

/**
 * 前端脚本需要的配置，没有任何效果开启时返回空数组
 */
function xi_fx_config()
{
    $cfg = array();

    if (_xz('dyn_title_s', false)) {
        $cfg['title'] = array(
            'leave' => (string) _xz('dyn_title_leave', ''),
            'back'  => (string) _xz('dyn_title_back', ''),
        );
    }

    if (_xz('island_s', false)) {
        $user  = wp_get_current_user();
        $logo  = _xz('logo');
        $mode  = xi_island_mode();
        $visit = $mode === 'visit';
        $cfg['island'] = array(
            // 访问欢迎模式：只在后台勾选的页面类型弹出，其他页面不显示（岛只用来承接操作提示）
            'title'    => $visit ? (xi_island_in_scope() ? (string) _xz('island_visit_title', '欢迎来到 {title}') : '') : (string) _xz('island_title', '{greeting}，{name}'),
            'desc'     => $visit ? (string) _xz('island_visit_desc', '{greeting}，{name}') : (string) _xz('island_desc', ''),
            'repeat'   => $visit || _xz('island_repeat', 'session') === 'page' ? 'page' : 'session',
            'duration' => max(2, min(15, (int) _xz('island_duration', 4))),
            'name'     => $user->exists() ? $user->display_name : __('朋友', 'xi'),
            'site'     => get_bloginfo('name'),
            'avatar'   => $user->exists() ? get_avatar_url($user->ID, array('size' => 96)) : '',
            'logo'     => $logo ? $logo : '',
            'host'     => wp_parse_url(home_url(), PHP_URL_HOST),
            'mode'     => $mode,
            'pageName' => xi_island_page_name(),
            // 首页的 {title} 从 WP REST 接口读取站点名称（页面被缓存插件缓存时也能显示最新的站点标题）
            'siteApi'  => is_front_page() || is_home() ? add_query_arg('_fields', 'name', rest_url()) : '',
            'pos'      => _xz('island_pos', 'header') === 'top' ? 'top' : 'header',
            'notify'   => (bool) _xz('island_notify', true),
            'page'     => xi_island_page_title(),
            'read'     => is_singular() && !(function_exists('xi_is_bbs_new') && xi_is_bbs_new()),
        );
    }

    $scope_ok = _xz('fall_scope', 'all') !== 'home' || is_front_page() || is_home();
    if ($scope_ok && (_xz('sakura_s', false) || _xz('snow_s', false))) {
        $cfg['fall'] = array(
            'sakura' => _xz('sakura_s', false) ? max(0, (int) _xz('sakura_num', 28)) : 0,
            'snow'   => _xz('snow_s', false) ? max(0, (int) _xz('snow_num', 70)) : 0,
            'mobile' => (bool) _xz('fall_mobile', false),
        );
    }

    return $cfg;
}

/**
 * 灵动岛显示方式：visit 访问页面时欢迎（默认）/ resident 常驻胶囊 / welcome 仅进站欢迎一次
 */
function xi_island_mode()
{
    $mode = _xz('island_mode', 'visit');
    return in_array($mode, array('visit', 'resident', 'welcome'), true) ? $mode : 'visit';
}

/**
 * 当前页面是否在「访问欢迎」的显示范围内：首页、页面、文章、论坛帖子、其他页面
 */
function xi_island_in_scope()
{
    $scope = (array) _xz('island_scope', array('home', 'page', 'post'));
    if (is_front_page() || is_home()) {
        $type = 'home';
    } elseif (is_page()) {
        $type = 'page';
    } elseif (is_singular('post')) {
        $type = 'post';
    } elseif (is_singular('forum_post')) {
        $type = 'bbs';
    } else {
        $type = 'other';
    }
    return in_array($type, $scope, true);
}

/**
 * 欢迎语里的 {title}：首页为站点名称，文章 / 页面为标题，其他页面为页面名称
 */
function xi_island_page_name()
{
    if (is_front_page() || is_home()) {
        return get_bloginfo('name');
    }
    if (is_singular()) {
        return wp_strip_all_tags(get_the_title(get_queried_object_id()));
    }
    return html_entity_decode(wp_strip_all_tags(xi_island_page_title()), ENT_QUOTES, 'UTF-8');
}

/**
 * 旧版默认是常驻胶囊，升级后改为访问欢迎（只迁移一次，之后以后台设置为准）
 */
function xi_island_migrate()
{
    if (_xz('island_migrated', false)) {
        return;
    }
    if (_xz('island_mode', '') === 'resident') {
        _sxz('island_mode', 'visit');
    }
    _sxz('island_migrated', true);
}
add_action('admin_init', 'xi_island_migrate');

/**
 * 灵动岛胶囊里显示的当前页面标题
 */
function xi_island_page_title()
{
    if (is_front_page() || is_home()) {
        $desc = get_bloginfo('description');
        return get_bloginfo('name') . ($desc ? ' · ' . $desc : '');
    }
    if (is_singular()) {
        return wp_strip_all_tags(get_the_title(get_queried_object_id()));
    }
    if (is_search()) {
        return sprintf(__('搜索：%s', 'xi'), get_search_query(false));
    }
    if (is_archive()) {
        return wp_strip_all_tags(is_post_type_archive('forum_post') && function_exists('xi_bbs_name') ? xi_bbs_name() : get_the_archive_title());
    }
    $title = wp_get_document_title();
    return html_entity_decode($title, ENT_QUOTES, 'UTF-8');
}

function xi_beautify_enqueue()
{
    $uri = get_template_directory_uri();

    if (xi_font_gensen()) {
        wp_enqueue_style('xi-font-gensen', $uri . '/assets/fonts/gensen-rounded/font.css', array(), XI_VERSION);
        $stack = "'Xi GenSen Rounded'";
        $css   = _xz('font_scope', 'all') === 'title'
            ? ':root{--xi-font-display:' . $stack . ',"Segoe UI Variable Display","Segoe UI",system-ui,-apple-system,"PingFang SC","Microsoft YaHei UI",sans-serif}'
            : ':root{--xi-font:' . $stack . ',"Segoe UI",system-ui,-apple-system,"PingFang SC","Microsoft YaHei UI","Microsoft YaHei",sans-serif;--xi-font-display:var(--xi-font)}';
        wp_add_inline_style('xi-main', $css);
    }

    if (_xz('logo_shine_s', false)) {
        wp_add_inline_style('xi-main', ':root{--xi-shine-time:' . (max(1, min(30, (int) _xz('logo_shine_gap', 4))) + 1.2) . 's}');
    }

    wp_add_inline_style('xi-main', xi_wave_css());

    $cfg = xi_fx_config();
    if ($cfg) {
        wp_enqueue_script('xi-beautify', $uri . '/assets/js/beautify.js', array(), XI_VERSION, true);
        wp_localize_script('xi-beautify', 'xiFx', $cfg);
    }
}
add_action('wp_enqueue_scripts', 'xi_beautify_enqueue', 20);

/**
 * 预加载最常用的两片字体（标点 + 高频 300 字），减少首屏字体切换
 */
function xi_beautify_preload()
{
    if (!xi_font_gensen()) {
        return;
    }
    $base = get_template_directory_uri() . '/assets/fonts/gensen-rounded/';
    foreach (array('r-00', 'r-01') as $name) {
        echo '<link rel="preload" href="' . esc_url($base . $name . '.woff2') . '" as="font" type="font/woff2" crossorigin>' . "\n";
    }
}
add_action('wp_head', 'xi_beautify_preload', 2);

function xi_beautify_body_class($classes)
{
    if (_xz('logo_shine_s', false)) {
        $classes[] = 'xi-logo-shine';
    }
    // 常驻在导航栏下方的灵动岛：给页面顶部留出位置，静止时不压住内容
    if (_xz('island_s', false) && xi_island_mode() === 'resident' && _xz('island_pos', 'header') !== 'top') {
        $classes[] = 'xi-island-resident';
    }
    return $classes;
}
add_filter('body_class', 'xi_beautify_body_class');

/* ---------- 波浪 ---------- */

/**
 * 内联 SVG 波浪：几层同形状的波浪错开位置、以不同速度左右飘动
 * 用内联 SVG 而不是 CSS 遮罩图片：不受浏览器遮罩支持、CSP（img-src 不允许 data:）和压缩插件影响，一定能显示
 *
 * @param string $id     页面内唯一的前缀（同一页可能同时有文章波浪和页脚波浪）
 * @param int    $layers 层数（1～4）
 */
function xi_wave_svg($id, $layers = 4)
{
    $id   = sanitize_html_class($id);
    $html = '<svg class="xi-waves" viewBox="0 24 150 28" preserveAspectRatio="none" aria-hidden="true" focusable="false">';
    $html .= '<defs><path id="' . $id . '-path" d="M-160 44c30 0 58-18 88-18s58 18 88 18 58-18 88-18 58 18 88 18v44h-352z"/></defs><g>';
    foreach (array_slice(array(0, 3, 5, 7), 0, max(1, min(4, (int) $layers))) as $y) {
        $html .= '<use href="#' . $id . '-path" x="48" y="' . $y . '"/>';
    }
    return $html . '</g></svg>';
}

/**
 * 波浪颜色与高度（后台可改）
 */
function xi_wave_css()
{
    $css  = '';
    $hero = trim((string) _xz('post_hero_wave_color', ''));
    $hero = preg_match('/^(#[0-9a-f]{3,8}|rgba?\([\d.,\s%]+\))$/i', $hero) ? $hero : '';
    $foot = sanitize_hex_color((string) _xz('footer_wave_color', '#4f9dff'));
    if ($hero) {
        $css .= '--xi-hero-wave:' . $hero . ';';
    }
    if ($foot) {
        $css .= '--xi-footer-wave:' . $foot . ';';
    }
    $css .= '--xi-footer-wave-h:' . max(16, min(120, (int) _xz('footer_wave_height', 42))) . 'px;';
    return ':root{' . $css . '}';
}

function xi_footer_wave_on()
{
    return (bool) _xz('footer_wave_s', true);
}

/**
 * 页脚上方的小波浪
 */
function xi_footer_wave()
{
    if (xi_footer_wave_on()) {
        echo '<div class="xi-footer-wave">' . xi_wave_svg('xi-fw', 4) . '</div>';
    }
}

/* ---------- 文章顶部波浪 ---------- */

/**
 * 当前页面是否显示顶部波浪色块（文章页；可选页面）
 */
function xi_post_hero_on()
{
    static $on = null;
    if ($on !== null) {
        return $on;
    }
    $on = _xz('post_hero_s', true)
        && (is_singular('post') || (is_page() && _xz('post_hero_page', false)))
        && !(function_exists('xi_header_slide_is_show') && xi_header_slide_is_show());
    return $on;
}

function xi_post_hero()
{
    if (!xi_post_hero_on()) {
        return;
    }
    $post    = get_queried_object();
    $post_id = $post->ID;
    $style   = '';
    $class   = 'xi-post-hero';

    $colors = (array) _xz('post_hero_colors', array());
    foreach (array('a', 'b', 'c') as $key) {
        $color = !empty($colors[$key]) ? sanitize_hex_color($colors[$key]) : '';
        if ($color) {
            $style .= '--hero-' . $key . ':' . $color . ';';
        }
    }
    if (_xz('post_hero_bg', 'color') === 'thumb' && has_post_thumbnail($post_id)) {
        $style .= '--hero-img:url(' . esc_url(get_the_post_thumbnail_url($post_id, 'full')) . ');';
        $class .= ' has-image';
    }
    if (_xz('post_hero_anim', true)) {
        $class .= ' is-anim';
    }

    $words    = mb_strlen((string) preg_replace('/\s+/u', '', wp_strip_all_tags(strip_shortcodes($post->post_content))));
    $author   = get_userdata((int) $post->post_author);
    $subtitle = xi_post_meta('subtitle', '', $post_id);

    echo '<section class="' . esc_attr($class) . '"' . ($style ? ' style="' . esc_attr($style) . '"' : '') . '>';
    echo '<div class="xi-container">';

    if (is_singular('post')) {
        echo '<div class="xi-hero-cats">';
        if (is_sticky($post_id)) {
            echo '<span>' . xi_icon('fire') . esc_html__('置顶', 'xi') . '</span>';
        }
        foreach (array_slice(get_the_category($post_id), 0, 3) as $cat) {
            echo '<a href="' . esc_url(get_category_link($cat)) . '">' . xi_icon('folder') . esc_html($cat->name) . '</a>';
        }
        echo '</div>';
    }

    echo '<h1>' . esc_html(get_the_title($post_id)) . '</h1>';
    if ($subtitle) {
        echo '<p class="xi-subtitle">' . esc_html($subtitle) . '</p>';
    }

    echo '<div class="xi-hero-meta">';
    echo '<span>' . xi_icon('book') . sprintf(esc_html__('%s 字', 'xi'), number_format_i18n($words)) . '</span>';
    echo '<span>' . xi_icon('clock') . sprintf(esc_html__('%d 分钟', 'xi'), xi_reading_minutes($post)) . '</span>';
    echo '<span>' . xi_icon('calendar') . '<time datetime="' . esc_attr(get_the_date('c', $post)) . '">' . esc_html(get_the_date('Y-m-d', $post)) . '</time></span>';
    if ($author) {
        echo '<span>' . xi_icon('user') . '<a href="' . esc_url(get_author_posts_url($author->ID)) . '">' . esc_html($author->display_name) . '</a></span>';
    }
    if (_xz('views_s', true)) {
        echo '<span>' . xi_icon('eye') . '<span data-xi-views>' . esc_html(xi_number(xi_get_views($post_id))) . '</span></span>';
    }
    if (comments_open($post_id) || get_comments_number($post_id)) {
        echo '<span>' . xi_icon('comment') . (int) get_comments_number($post_id) . '</span>';
    }
    if ($author && is_singular('post')) {
        echo '<span>' . xi_icon('edit') . sprintf(esc_html__('该作者已发布 %d 篇文章', 'xi'), (int) xi_user_stats($author->ID)['posts']) . '</span>';
    }
    echo '</div>';

    echo '</div>';
    echo '<div class="xi-hero-waves">' . xi_wave_svg('xi-hw', 4) . '</div>';
    echo '</section>';
}

function xi_post_hero_body_class($classes)
{
    if (xi_post_hero_on()) {
        $classes[] = 'xi-has-post-hero';
    }
    return $classes;
}
add_filter('body_class', 'xi_post_hero_body_class');
