<?php
/**
 * 幸运大转盘：后台默认选项、AJAX 抽奖、页面创建与前端资源
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 后台默认选项（名称|颜色，每行一项）
 */
function xi_wheel_default_raw()
{
    return "一等奖|#ff6b6b\n二等奖|#feca57\n三等奖|#48dbfb\n谢谢参与|#ff9ff3\n再来一次|#1dd1a1\n幸运奖|#5f27cd";
}

function xi_wheel_default_items()
{
    return array(
        array('name' => __('一等奖', 'xi'), 'color' => '#ff6b6b'),
        array('name' => __('二等奖', 'xi'), 'color' => '#feca57'),
        array('name' => __('三等奖', 'xi'), 'color' => '#48dbfb'),
        array('name' => __('谢谢参与', 'xi'), 'color' => '#ff9ff3'),
        array('name' => __('再来一次', 'xi'), 'color' => '#1dd1a1'),
        array('name' => __('幸运奖', 'xi'), 'color' => '#5f27cd'),
    );
}

/**
 * 解析 textarea：名称|颜色
 */
function xi_wheel_parse_items($raw)
{
    $palette = array('#ff6b6b', '#feca57', '#48dbfb', '#ff9ff3', '#1dd1a1', '#5f27cd', '#6c5ce7', '#00b894');
    $items   = array();

    foreach (preg_split('/\r\n|\r|\n/', (string) $raw) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = explode('|', $line, 2);
        $name  = sanitize_text_field(trim($parts[0]));
        if ($name === '') {
            continue;
        }
        $name  = mb_substr($name, 0, 12);
        $color = isset($parts[1]) ? sanitize_hex_color(trim($parts[1])) : '';
        if (!$color) {
            $color = $palette[count($items) % count($palette)];
        }
        $items[] = array('name' => $name, 'color' => $color);
        if (count($items) >= 24) {
            break;
        }
    }

    return count($items) >= 2 ? $items : xi_wheel_default_items();
}

function xi_wheel_items()
{
    static $items = null;
    if ($items === null) {
        $items = xi_wheel_parse_items(_xz('wheel_items', xi_wheel_default_raw()));
    }
    return $items;
}

/**
 * 前端配置
 */
function xi_wheel_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $items = xi_wheel_items();
    $config = array(
        'title'      => trim((string) _xz('wheel_title', '')),
        'subtitle'   => trim((string) _xz('wheel_subtitle', '')),
        'items'      => $items,
        'hash'       => substr(md5(wp_json_encode($items)), 0, 8),
        'ajax'       => admin_url('admin-ajax.php'),
        'nonce'      => wp_create_nonce('xi_wheel_spin'),
        'sound'      => (bool) _xz('wheel_sound', true),
        'confetti'   => (bool) _xz('wheel_confetti', true),
        'storageKey' => 'xi_wheel_items_' . substr(md5(wp_json_encode($items)), 0, 8),
    );
    if ($config['title'] === '') {
        $config['title'] = __('幸运大转盘', 'xi');
    }
    if ($config['subtitle'] === '') {
        $config['subtitle'] = __('真随机 · 公平 · 不可预测 · 配置实时保存', 'xi');
    }
    return $config;
}

function xi_wheel_is_page()
{
    return is_page_template('page-wheel.php') || is_page('wheel');
}

function xi_wheel_enqueue()
{
    if (!xi_wheel_is_page()) {
        return;
    }
    $uri = get_template_directory_uri();
    wp_enqueue_style('xi-wheel', $uri . '/assets/css/wheel.css', array('xi-main'), XI_VERSION);
    wp_enqueue_script('xi-wheel', $uri . '/assets/js/wheel.js', array(), XI_VERSION, true);
    wp_localize_script('xi-wheel', 'xiWheel', xi_wheel_config());
}
add_action('wp_enqueue_scripts', 'xi_wheel_enqueue', 30);

/**
 * 转盘页使用全宽布局
 */
function xi_wheel_body_class($classes)
{
    if (xi_wheel_is_page()) {
        $classes[] = 'xi-no-sidebar';
        $classes[] = 'xi-wheel-body';
    }
    return $classes;
}
add_filter('body_class', 'xi_wheel_body_class');

/**
 * AJAX：服务端使用密码学安全随机数
 */
function xi_wheel_ajax_spin()
{
    check_ajax_referer('xi_wheel_spin', 'nonce');

    $raw   = isset($_POST['items']) ? wp_unslash($_POST['items']) : '';
    $input = json_decode($raw, true);
    if (!is_array($input) || count($input) < 2) {
        $input = xi_wheel_items();
    }

    $names = array();
    foreach ($input as $item) {
        if (!is_array($item)) {
            $names[] = __('未命名', 'xi');
            continue;
        }
        $name = sanitize_text_field(trim((string) (isset($item['name']) ? $item['name'] : '')));
        if ($name === '') {
            $name = __('未命名', 'xi');
        }
        $names[] = mb_substr($name, 0, 12);
    }
    if (count($names) < 2) {
        wp_send_json_error(array('message' => __('至少需要 2 个选项', 'xi')), 400);
    }

    $index = random_int(0, count($names) - 1);
    wp_send_json_success(array(
        'index' => $index,
        'name'  => $names[$index],
    ));
}
add_action('wp_ajax_xi_wheel_spin', 'xi_wheel_ajax_spin');
add_action('wp_ajax_nopriv_xi_wheel_spin', 'xi_wheel_ajax_spin');

/**
 * 自动创建转盘页面；已存在时确保使用转盘模板
 */
function xi_wheel_maybe_create_page()
{
    if (!current_user_can('manage_options') || get_option('xi_wheel_page_setup')) {
        return;
    }

    $page = get_page_by_path('wheel');
    if (!$page) {
        $page_id = wp_insert_post(array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => _xz('wheel_title', '') ? _xz('wheel_title', '') : __('幸运大转盘', 'xi'),
            'post_name'    => 'wheel',
            'post_content' => '',
        ), true);
        if (is_wp_error($page_id)) {
            return;
        }
        $page = get_post($page_id);
    }

    if ($page instanceof WP_Post) {
        if (get_page_template_slug($page->ID) !== 'page-wheel.php') {
            update_post_meta($page->ID, '_wp_page_template', 'page-wheel.php');
        }
        update_option('xi_wheel_page_setup', 1);
        update_option('xi_wheel_page_id', $page->ID);
    }
}
add_action('admin_init', 'xi_wheel_maybe_create_page');
add_action('after_switch_theme', 'xi_wheel_maybe_create_page');
