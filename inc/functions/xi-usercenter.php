<?php
/**
 * 用户中心：/user-center/（未开启固定链接时为 ?xi_uc=1）
 * 以及签到、开通会员、兑换卡密、积分解锁的 Ajax 接口
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_uc_rewrite()
{
    add_rewrite_rule('^user-center/?$', 'index.php?xi_uc=1', 'top');
    add_rewrite_rule('^write/?$', 'index.php?xi_write=1', 'top');

    // 主题升级后自动刷新一次固定链接规则
    if (get_option('xi_rewrite_ver') !== XI_VERSION) {
        flush_rewrite_rules(false);
        update_option('xi_rewrite_ver', XI_VERSION);
    }
}
add_action('init', 'xi_uc_rewrite');

function xi_uc_query_vars($vars)
{
    $vars[] = 'xi_uc';
    return $vars;
}
add_filter('query_vars', 'xi_uc_query_vars');

/**
 * 用户中心、投稿页只有自定义查询变量，WordPress 会把它们识别成首页，这里纠正
 * 并跳过无用的文章查询
 */
function xi_route_not_home($query)
{
    if ($query->is_main_query() && ($query->get('xi_uc') || $query->get('xi_write'))) {
        $query->is_home = false;
        $query->set('posts_per_page', 1);
        $query->set('no_found_rows', true);
    }
}
add_action('parse_query', 'xi_route_not_home');

function xi_uc_flush_rules()
{
    xi_uc_rewrite();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'xi_uc_flush_rules');

function xi_uc_url($tab = '')
{
    $url = get_option('permalink_structure') ? home_url('/user-center/') : add_query_arg('xi_uc', 1, home_url('/'));
    return $tab ? add_query_arg('tab', $tab, $url) : $url;
}

function xi_is_user_center()
{
    return (bool) get_query_var('xi_uc');
}

function xi_uc_template($template)
{
    if (xi_is_user_center()) {
        status_header(200);
        return get_theme_file_path('user-center.php');
    }
    return $template;
}
add_filter('template_include', 'xi_uc_template');

function xi_uc_title($title)
{
    if (xi_is_user_center()) {
        $title['title'] = __('用户中心', 'xi');
    }
    return $title;
}
add_filter('document_title_parts', 'xi_uc_title');

function xi_uc_noindex()
{
    if (xi_is_user_center()) {
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    }
}
add_action('wp_head', 'xi_uc_noindex', 1);

/**
 * 用户中心不显示侧边栏、顶部幻灯片
 */
function xi_uc_body_class($classes)
{
    if (xi_is_user_center()) {
        $classes[] = 'xi-no-sidebar';
        $classes[] = 'xi-user-center-page';
    }
    return $classes;
}
add_filter('body_class', 'xi_uc_body_class', 20);

/* ---------- 前台脚本参数 ---------- */

function xi_user_script_data()
{
    if (!is_user_logged_in()) {
        return;
    }
    wp_localize_script('xi-main', 'xiUser', array(
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('xi_user'),
    ));
}
add_action('wp_enqueue_scripts', 'xi_user_script_data', 20);

/* ---------- Ajax ---------- */

function xi_user_ajax_check()
{
    if (!is_user_logged_in()) {
        wp_send_json(array('ok' => false, 'msg' => __('请先登录', 'xi')));
    }
    if (!check_ajax_referer('xi_user', 'nonce', false)) {
        wp_send_json(array('ok' => false, 'msg' => __('页面已过期，请刷新后重试', 'xi')));
    }
    return get_current_user_id();
}

function xi_user_ajax_result($result, $extra = array())
{
    if (is_wp_error($result)) {
        wp_send_json(array('ok' => false, 'msg' => $result->get_error_message()));
    }
    wp_send_json(array_merge(array('ok' => true, 'msg' => $result, 'reload' => true), $extra));
}

function xi_ajax_checkin()
{
    $user_id = xi_user_ajax_check();
    if (!xi_points_enabled()) {
        xi_user_ajax_result(new WP_Error('off', __('积分功能未开启', 'xi')));
    }
    if (!xi_user_can('checkin', $user_id)) {
        xi_user_ajax_result(xi_auth_error('checkin'));
    }
    $result = xi_do_checkin($user_id);
    if (is_wp_error($result)) {
        xi_user_ajax_result($result);
    }
    xi_user_ajax_result(sprintf(__('签到成功，获得 %1$d %2$s，已连续签到 %3$d 天', 'xi'), $result['points'], xi_points_name(), $result['streak']));
}
add_action('wp_ajax_xi_checkin', 'xi_ajax_checkin');

function xi_ajax_buy_vip()
{
    $user_id = xi_user_ajax_check();
    if (!xi_user_can('vip_buy', $user_id)) {
        xi_user_ajax_result(xi_auth_error('vip_buy'));
    }
    $level   = isset($_POST['level']) ? (int) $_POST['level'] : 0;
    $plan    = isset($_POST['plan']) ? (int) $_POST['plan'] : -1;
    $pay     = isset($_POST['pay']) && $_POST['pay'] === 'points' ? 'points' : 'balance';
    xi_user_ajax_result(xi_buy_vip($user_id, $level, $plan, $pay));
}
add_action('wp_ajax_xi_buy_vip', 'xi_ajax_buy_vip');

function xi_ajax_redeem()
{
    $user_id = xi_user_ajax_check();
    if (!_xz('card_s', true)) {
        xi_user_ajax_result(new WP_Error('off', __('卡密充值未开启', 'xi')));
    }
    if (!xi_user_can('redeem', $user_id)) {
        xi_user_ajax_result(xi_auth_error('redeem'));
    }
    $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
    xi_user_ajax_result(xi_redeem_card($user_id, $code));
}
add_action('wp_ajax_xi_redeem', 'xi_ajax_redeem');

function xi_ajax_unlock()
{
    $user_id = xi_user_ajax_check();
    if (!xi_user_can('unlock', $user_id)) {
        xi_user_ajax_result(xi_auth_error('unlock'));
    }
    $post_id = isset($_POST['post']) ? (int) $_POST['post'] : 0;
    xi_user_ajax_result(xi_unlock_post($user_id, $post_id));
}
add_action('wp_ajax_xi_unlock', 'xi_ajax_unlock');
