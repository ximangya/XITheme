<?php
/**
 * 第三方登录：QQ、微信、微博、GitHub、Gitee 官方接口 + 彩虹聚合登录
 *
 * - 发起：/?xi_oauth={平台}（已登录时为绑定）
 * - 回调：/?xi_oauth_cb={平台}
 * - 每个平台可选「官方接口」或「彩虹聚合登录」通道，两种通道的身份标识不同，分开保存：
 *   官方 xi_oauth_{平台}，聚合 xi_oauth_c_{平台}
 * - state 同时写入 transient 和浏览器 Cookie，回调时两者一致才继续，防止登录 CSRF
 * - 新的第三方账号按设置自动注册；自动注册的账号没有已知密码（xi_pass_unset），可在账号设置中直接设置密码
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 平台表
 * native：是否支持官方接口；clogin：彩虹聚合登录里的类型名，留空表示不支持
 */
function xi_oauth_platforms()
{
    static $list = null;
    if ($list === null) {
        $list = apply_filters('xi_oauth_platforms', array(
            'qq'        => array('name' => 'QQ', 'icon' => 'qq', 'color' => '#12b7f5', 'native' => true, 'clogin' => 'qq'),
            'wechat'    => array('name' => __('微信', 'xi'), 'icon' => 'wechat', 'color' => '#07c160', 'native' => true, 'clogin' => 'wx'),
            'weibo'     => array('name' => __('微博', 'xi'), 'icon' => 'weibo', 'color' => '#e6162d', 'native' => true, 'clogin' => 'sina'),
            'alipay'    => array('name' => __('支付宝', 'xi'), 'icon' => '', 'color' => '#1677ff', 'native' => false, 'clogin' => 'alipay'),
            'github'    => array('name' => 'GitHub', 'icon' => 'github', 'color' => '#24292f', 'native' => true, 'clogin' => 'github'),
            'gitee'     => array('name' => 'Gitee', 'icon' => '', 'color' => '#c71d23', 'native' => true, 'clogin' => 'gitee'),
            'baidu'     => array('name' => __('百度', 'xi'), 'icon' => '', 'color' => '#2932e1', 'native' => false, 'clogin' => 'baidu'),
            'douyin'    => array('name' => __('抖音', 'xi'), 'icon' => '', 'color' => '#161823', 'native' => false, 'clogin' => 'douyin'),
            'huawei'    => array('name' => __('华为', 'xi'), 'icon' => '', 'color' => '#cf0a2c', 'native' => false, 'clogin' => 'huawei'),
            'xiaomi'    => array('name' => __('小米', 'xi'), 'icon' => '', 'color' => '#ff6900', 'native' => false, 'clogin' => 'xiaomi'),
            'dingtalk'  => array('name' => __('钉钉', 'xi'), 'icon' => '', 'color' => '#1677ff', 'native' => false, 'clogin' => 'dingtalk'),
            'microsoft' => array('name' => 'Microsoft', 'icon' => '', 'color' => '#2f6fd0', 'native' => false, 'clogin' => 'microsoft'),
            'google'    => array('name' => 'Google', 'icon' => '', 'color' => '#4285f4', 'native' => false, 'clogin' => 'google'),
        ));
    }
    return $list;
}

function xi_oauth_platform($type)
{
    $list = xi_oauth_platforms();
    return isset($list[$type]) ? $list[$type] : null;
}

function xi_oauth_name($type)
{
    $p = xi_oauth_platform($type);
    return $p ? $p['name'] : $type;
}

/**
 * 彩虹聚合登录接口配置
 */
function xi_clogin_config()
{
    $api = trim((string) _xz('clogin_api', ''));
    if ($api !== '' && !preg_match('#^https?://#i', $api)) {
        $api = 'https://' . $api;
    }
    // 后台可能填成 https://域名/ 或 https://域名/connect.php，统一成目录形式
    $api = preg_replace('#connect\.php.*$#i', '', $api);
    return array(
        'api'    => $api ? trailingslashit($api) : '',
        'appid'  => trim((string) _xz('clogin_appid', '')),
        'appkey' => trim((string) _xz('clogin_appkey', '')),
    );
}

function xi_clogin_ready()
{
    $c = xi_clogin_config();
    return (bool) _xz('clogin_s', false) && $c['api'] && $c['appid'] && $c['appkey'];
}

/**
 * 平台当前使用的通道：native | clogin | ''（未开启或未配置）
 */
function xi_oauth_channel($type)
{
    $p = xi_oauth_platform($type);
    if (!$p || !_xz('oauth_s', false) || !_xz('oauth_' . $type . '_s', false)) {
        return '';
    }
    $via = $p['native'] ? _xz('oauth_' . $type . '_via', 'native') : 'clogin';
    if ($via === 'native' && $p['native'] && trim((string) _xz('oauth_' . $type . '_appid', '')) && trim((string) _xz('oauth_' . $type . '_secret', ''))) {
        return 'native';
    }
    if ($via === 'clogin' && $p['clogin'] && xi_clogin_ready()) {
        return 'clogin';
    }
    return '';
}

/**
 * 已开启并配置完整的平台
 */
function xi_oauth_enabled_types()
{
    $types = array();
    foreach (array_keys(xi_oauth_platforms()) as $type) {
        if (xi_oauth_channel($type)) {
            $types[] = $type;
        }
    }
    return $types;
}

function xi_oauth_meta_key($type, $channel)
{
    return 'xi_oauth_' . ($channel === 'clogin' ? 'c_' : '') . $type;
}

/* ---------- 地址 ---------- */

/**
 * 发起登录 / 绑定的地址
 */
function xi_oauth_url($type, $redirect = '')
{
    $args = array('xi_oauth' => $type);
    if ($redirect) {
        $args['redirect_to'] = rawurlencode($redirect);
    }
    if (is_user_logged_in()) {
        $args['_wpnonce'] = wp_create_nonce('xi_oauth_bind');
    }
    return add_query_arg($args, home_url('/'));
}

/**
 * 回调地址（官方接口需要在开放平台填写同样的地址）
 */
function xi_oauth_callback_url($type)
{
    return add_query_arg('xi_oauth_cb', $type, home_url('/'));
}

/**
 * 当前页面地址（去掉登录弹窗参数），用作登录后的返回地址
 */
function xi_oauth_current_url()
{
    $url = (is_ssl() ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? wp_unslash($_SERVER['HTTP_HOST']) : '') . (isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/');
    return remove_query_arg(array('xi_sign', 'redirect_to', 'xi_oauth', 'xi_oauth_cb', '_wpnonce'), $url);
}

/* ---------- 提示：跳转后由前端读取 Cookie 弹出 ---------- */

function xi_oauth_flash($msg, $ok)
{
    setcookie('xi_flash', rawurlencode(wp_json_encode(array('ok' => (bool) $ok, 'msg' => (string) $msg))), time() + 120, '/', COOKIE_DOMAIN, is_ssl(), false);
}

function xi_oauth_finish($msg, $ok, $redirect = '')
{
    xi_oauth_flash($msg, $ok);
    $redirect = $redirect ? wp_validate_redirect($redirect, home_url('/')) : home_url('/');
    nocache_headers();
    wp_safe_redirect($redirect);
    exit;
}

/* ---------- 路由 ---------- */

function xi_oauth_route()
{
    if (!empty($_GET['xi_oauth'])) {
        xi_oauth_start(sanitize_key(wp_unslash($_GET['xi_oauth'])));
    } elseif (!empty($_GET['xi_oauth_cb'])) {
        xi_oauth_callback(sanitize_key(wp_unslash($_GET['xi_oauth_cb'])));
    }
}
add_action('init', 'xi_oauth_route', 30);

function xi_oauth_start($type)
{
    $redirect = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : '';
    $redirect = wp_validate_redirect($redirect, home_url('/'));
    $channel  = xi_oauth_channel($type);
    if (!$channel) {
        xi_oauth_finish(__('该登录方式未开启', 'xi'), false, $redirect);
    }
    if (function_exists('xi_ip_banned') && xi_ip_banned()) {
        xi_oauth_finish(xi_ip_ban_message(), false, $redirect);
    }

    $bind = 0;
    if (is_user_logged_in()) {
        if (empty($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_GET['_wpnonce'])), 'xi_oauth_bind')) {
            xi_oauth_finish(__('页面已过期，请刷新后重试', 'xi'), false, xi_uc_url('settings'));
        }
        $bind = get_current_user_id();
    }

    $state = wp_generate_password(32, false);
    set_transient('xi_oauth_s_' . $state, array(
        'type'     => $type,
        'channel'  => $channel,
        'bind'     => $bind,
        'redirect' => $redirect,
    ), 10 * MINUTE_IN_SECONDS);
    setcookie('xi_oauth_state', $state, time() + 10 * MINUTE_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);

    $url = $channel === 'clogin' ? xi_clogin_login_url($type, $state) : xi_oauth_native_authorize_url($type, $state);
    if (is_wp_error($url)) {
        xi_oauth_finish($url->get_error_message(), false, $redirect);
    }
    nocache_headers();
    // 授权页在第三方域名，不能用 wp_safe_redirect
    wp_redirect($url);
    exit;
}

function xi_oauth_callback($type)
{
    $state  = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_GET['state'])) : '';
    $cookie = isset($_COOKIE['xi_oauth_state']) ? preg_replace('/[^A-Za-z0-9]/', '', wp_unslash($_COOKIE['xi_oauth_state'])) : '';
    $data   = $state ? get_transient('xi_oauth_s_' . $state) : false;

    if ($state) {
        delete_transient('xi_oauth_s_' . $state);
    }
    setcookie('xi_oauth_state', '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);

    if (!$data || !$cookie || !hash_equals($state, $cookie) || $data['type'] !== $type) {
        xi_oauth_finish(__('登录已超时或来源无效，请重新发起登录', 'xi'), false);
    }
    $redirect = $data['redirect'];
    $code     = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
    if ($code === '') {
        xi_oauth_finish(__('已取消授权', 'xi'), false, $redirect);
    }

    $profile = $data['channel'] === 'clogin' ? xi_clogin_profile($type, $code) : xi_oauth_native_profile($type, $code);
    if (is_wp_error($profile)) {
        xi_oauth_finish(sprintf(__('%1$s登录失败：%2$s', 'xi'), xi_oauth_name($type), $profile->get_error_message()), false, $redirect);
    }
    $profile['type']    = $type;
    $profile['channel'] = $data['channel'];

    if ($data['bind']) {
        xi_oauth_bind((int) $data['bind'], $profile);
    }
    xi_oauth_login($profile, $redirect);
}

/* ---------- 绑定、登录、注册 ---------- */

/**
 * 按第三方身份查本站用户
 */
function xi_oauth_find_user($type, $channel, $openid)
{
    $users = get_users(array(
        'meta_key'   => xi_oauth_meta_key($type, $channel),
        'meta_value' => $openid,
        'number'     => 1,
        'fields'     => 'ID',
    ));
    return $users ? (int) $users[0] : 0;
}

/**
 * 写入绑定关系与展示信息；用户没有自己上传头像时使用第三方头像
 */
function xi_oauth_save_binding($user_id, $profile)
{
    update_user_meta($user_id, xi_oauth_meta_key($profile['type'], $profile['channel']), $profile['openid']);
    update_user_meta($user_id, 'xi_oauth_info_' . $profile['type'], array(
        'channel' => $profile['channel'],
        'name'    => $profile['name'],
        'avatar'  => $profile['avatar'],
        'time'    => time(),
    ));
    if ($profile['avatar'] && !get_user_meta($user_id, 'xi_avatar', true)) {
        update_user_meta($user_id, 'xi_avatar_url', esc_url_raw($profile['avatar']));
    }
}

function xi_oauth_bind($user_id, $profile)
{
    $back = xi_uc_url('settings');
    $name = xi_oauth_name($profile['type']);
    if (get_current_user_id() !== $user_id) {
        xi_oauth_finish(__('登录状态已变化，请重新绑定', 'xi'), false, $back);
    }
    $owner = xi_oauth_find_user($profile['type'], $profile['channel'], $profile['openid']);
    if ($owner && $owner !== $user_id) {
        xi_oauth_finish(sprintf(__('该%1$s账号已绑定本站其他账号，请先登录那个账号解绑', 'xi'), $name), false, $back);
    }
    $current = get_user_meta($user_id, xi_oauth_meta_key($profile['type'], $profile['channel']), true);
    if ($current && $current !== $profile['openid']) {
        xi_oauth_finish(sprintf(__('你已绑定其他%1$s账号，请先解绑', 'xi'), $name), false, $back);
    }
    xi_oauth_save_binding($user_id, $profile);
    do_action('xi_oauth_bound', $user_id, $profile);
    xi_oauth_finish(sprintf(__('%s绑定成功', 'xi'), $name), true, $back);
}

/**
 * 生成不重复的用户名：qq_x8k2m4p1
 */
function xi_oauth_new_login($type)
{
    $prefix = preg_replace('/[^a-z0-9]/', '', $type);
    do {
        $login = $prefix . '_' . strtolower(wp_generate_password(8, false));
    } while (username_exists($login));
    return $login;
}

function xi_oauth_login($profile, $redirect)
{
    $name    = xi_oauth_name($profile['type']);
    $user_id = xi_oauth_find_user($profile['type'], $profile['channel'], $profile['openid']);

    if (function_exists('xi_ip_banned') && xi_ip_banned()) {
        xi_oauth_finish(xi_ip_ban_message(), false, $redirect);
    }

    if (!$user_id) {
        if (!xi_reg_enabled() || !_xz('oauth_auto_reg', true)) {
            xi_oauth_finish(sprintf(__('该%1$s账号尚未绑定本站账号。请先用账号密码登录，再到「用户中心 → 账号设置」中绑定', 'xi'), $name), false, xi_sign_url('login', $redirect));
        }
        $nick    = trim(wp_strip_all_tags($profile['name']));
        $nick    = $nick !== '' ? mb_substr($nick, 0, 20) : sprintf(__('%s用户', 'xi'), $name);
        $user_id = wp_insert_user(array(
            'user_login'   => xi_oauth_new_login($profile['type']),
            'user_pass'    => wp_generate_password(24, true, true),
            'user_email'   => '',
            'display_name' => $nick,
            'nickname'     => $nick,
            'role'         => xi_register_role(),
        ));
        if (is_wp_error($user_id)) {
            xi_oauth_finish($user_id->get_error_message(), false, $redirect);
        }
        update_user_meta($user_id, 'xi_pass_unset', 1);
        xi_oauth_save_binding($user_id, $profile);
        wp_new_user_notification($user_id, null, 'admin');
        do_action('xi_user_registered', $user_id, 'oauth_' . $profile['type']);
        $welcome = sprintf(__('已通过%1$s创建账号并登录，可在账号设置中绑定邮箱、设置密码', 'xi'), $name);
    } else {
        if (function_exists('xi_get_ban') && ($ban = xi_get_ban($user_id)) && $ban['type'] === 'ban' && !user_can($user_id, 'manage_options')) {
            xi_oauth_finish(xi_ban_message($ban), false, $redirect);
        }
        // 刷新第三方昵称与头像（不覆盖用户自己改过的昵称）
        xi_oauth_save_binding($user_id, $profile);
        $welcome = __('登录成功', 'xi');
    }

    $user = get_userdata($user_id);
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());
    do_action('wp_login', $user->user_login, $user);
    xi_oauth_finish($welcome, true, $redirect);
}

/**
 * 解绑
 */
function xi_ajax_oauth_unbind()
{
    $user_id = xi_user_ajax_check();
    $type    = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
    if (!xi_oauth_platform($type)) {
        xi_user_ajax_result(new WP_Error('type', __('参数错误', 'xi')));
    }
    $user  = get_userdata($user_id);
    $bound = xi_oauth_user_bindings($user_id);
    if (!isset($bound[$type])) {
        xi_user_ajax_result(new WP_Error('none', __('尚未绑定', 'xi')));
    }
    // 没设置过密码、没有邮箱和手机、只有这一个第三方账号：解绑后将无法再登录
    $phone = function_exists('xi_get_user_phone') ? xi_get_user_phone($user_id) : '';
    if (xi_user_pass_unset($user_id) && !$user->user_email && !$phone && count($bound) < 2) {
        xi_user_ajax_result(new WP_Error('last', __('这是你唯一的登录方式，请先设置密码或绑定邮箱后再解绑', 'xi')));
    }
    delete_user_meta($user_id, xi_oauth_meta_key($type, 'native'));
    delete_user_meta($user_id, xi_oauth_meta_key($type, 'clogin'));
    $info = get_user_meta($user_id, 'xi_oauth_info_' . $type, true);
    delete_user_meta($user_id, 'xi_oauth_info_' . $type);
    // 正在使用的是这个平台的头像时一并移除
    if (is_array($info) && !empty($info['avatar']) && get_user_meta($user_id, 'xi_avatar_url', true) === esc_url_raw($info['avatar'])) {
        delete_user_meta($user_id, 'xi_avatar_url');
    }
    do_action('xi_oauth_unbound', $user_id, $type);
    xi_user_ajax_result(sprintf(__('已解除%s绑定', 'xi'), xi_oauth_name($type)));
}
add_action('wp_ajax_xi_oauth_unbind', 'xi_ajax_oauth_unbind');

/**
 * 用户已绑定的平台：type => info
 */
function xi_oauth_user_bindings($user_id)
{
    $list = array();
    foreach (array_keys(xi_oauth_platforms()) as $type) {
        if (get_user_meta($user_id, xi_oauth_meta_key($type, 'native'), true) || get_user_meta($user_id, xi_oauth_meta_key($type, 'clogin'), true)) {
            $info        = get_user_meta($user_id, 'xi_oauth_info_' . $type, true);
            $list[$type] = is_array($info) ? $info : array();
        }
    }
    return $list;
}

/* ---------- HTTP ---------- */

/**
 * 请求第三方接口并解析返回；兼容 JSON、QQ 旧版 callback( {...} ) 与 a=b&c=d 三种格式
 *
 * @return array|WP_Error
 */
function xi_oauth_http($url, $method = 'GET', $body = null, $headers = array())
{
    $args = array(
        'timeout'    => 15,
        'headers'    => array_merge(array('Accept' => 'application/json', 'User-Agent' => 'XiTheme/' . XI_VERSION . '; ' . home_url('/')), $headers),
        'user-agent' => 'XiTheme/' . XI_VERSION,
    );
    if ($method === 'POST') {
        $args['body'] = $body;
        $res          = wp_remote_post($url, $args);
    } else {
        $res = wp_remote_get($url, $args);
    }
    if (is_wp_error($res)) {
        return new WP_Error('http', __('连接接口失败：', 'xi') . $res->get_error_message());
    }
    $text = trim((string) wp_remote_retrieve_body($res));
    if (preg_match('/^callback\(\s*(.*)\s*\);?$/s', $text, $m)) {
        $text = $m[1];
    }
    $json = json_decode($text, true);
    if (is_array($json)) {
        return $json;
    }
    parse_str($text, $pairs);
    if ($pairs && !isset($pairs[$text])) {
        return $pairs;
    }
    return new WP_Error('http', sprintf(__('接口返回异常（HTTP %d）', 'xi'), (int) wp_remote_retrieve_response_code($res)));
}

/* ---------- 彩虹聚合登录 ---------- */

function xi_clogin_login_url($type, $state)
{
    $c   = xi_clogin_config();
    $p   = xi_oauth_platform($type);
    // 聚合平台回调时原样带回 state，并附加 type、code
    $res = xi_oauth_http(add_query_arg(rawurlencode_deep(array(
        'act'          => 'login',
        'appid'        => $c['appid'],
        'appkey'       => $c['appkey'],
        'type'         => $p['clogin'],
        'redirect_uri' => xi_oauth_callback_url($type),
        'state'        => $state,
    )), $c['api'] . 'connect.php'));
    if (is_wp_error($res)) {
        return $res;
    }
    if (!isset($res['code']) || (int) $res['code'] !== 0 || empty($res['url'])) {
        return new WP_Error('clogin', __('聚合登录：', 'xi') . (!empty($res['msg']) ? $res['msg'] : __('获取登录地址失败', 'xi')));
    }
    return $res['url'];
}

function xi_clogin_profile($type, $code)
{
    $c   = xi_clogin_config();
    $p   = xi_oauth_platform($type);
    $res = xi_oauth_http(add_query_arg(rawurlencode_deep(array(
        'act'    => 'callback',
        'appid'  => $c['appid'],
        'appkey' => $c['appkey'],
        'type'   => $p['clogin'],
        'code'   => $code,
    )), $c['api'] . 'connect.php'));
    if (is_wp_error($res)) {
        return $res;
    }
    if (!isset($res['code']) || (int) $res['code'] !== 0 || empty($res['social_uid'])) {
        return new WP_Error('clogin', !empty($res['msg']) ? $res['msg'] : __('获取用户信息失败', 'xi'));
    }
    return array(
        'openid' => (string) $res['social_uid'],
        'name'   => isset($res['nickname']) ? (string) $res['nickname'] : '',
        'avatar' => isset($res['faceimg']) ? (string) $res['faceimg'] : '',
    );
}

/* ---------- 官方接口 ---------- */

function xi_oauth_native_keys($type)
{
    return array(trim((string) _xz('oauth_' . $type . '_appid', '')), trim((string) _xz('oauth_' . $type . '_secret', '')));
}

function xi_oauth_native_authorize_url($type, $state)
{
    list($appid) = xi_oauth_native_keys($type);
    $cb = xi_oauth_callback_url($type);
    switch ($type) {
        case 'qq':
            return add_query_arg(rawurlencode_deep(array('response_type' => 'code', 'client_id' => $appid, 'redirect_uri' => $cb, 'state' => $state, 'scope' => 'get_user_info')), 'https://graph.qq.com/oauth2.0/authorize');
        case 'wechat':
            return add_query_arg(rawurlencode_deep(array('appid' => $appid, 'redirect_uri' => $cb, 'response_type' => 'code', 'scope' => 'snsapi_login', 'state' => $state)), 'https://open.weixin.qq.com/connect/qrconnect') . '#wechat_redirect';
        case 'weibo':
            return add_query_arg(rawurlencode_deep(array('client_id' => $appid, 'redirect_uri' => $cb, 'response_type' => 'code', 'state' => $state)), 'https://api.weibo.com/oauth2/authorize');
        case 'github':
            return add_query_arg(rawurlencode_deep(array('client_id' => $appid, 'redirect_uri' => $cb, 'state' => $state, 'scope' => 'read:user')), 'https://github.com/login/oauth/authorize');
        case 'gitee':
            return add_query_arg(rawurlencode_deep(array('client_id' => $appid, 'redirect_uri' => $cb, 'response_type' => 'code', 'state' => $state, 'scope' => 'user_info')), 'https://gitee.com/oauth/authorize');
    }
    return new WP_Error('type', __('该平台不支持官方接口，请改用聚合登录', 'xi'));
}

/**
 * 用授权码换取用户信息
 *
 * @return array|WP_Error openid、name、avatar
 */
function xi_oauth_native_profile($type, $code)
{
    list($appid, $secret) = xi_oauth_native_keys($type);
    $cb = xi_oauth_callback_url($type);

    switch ($type) {
        case 'qq':
            $token = xi_oauth_http(add_query_arg(rawurlencode_deep(array('grant_type' => 'authorization_code', 'client_id' => $appid, 'client_secret' => $secret, 'code' => $code, 'redirect_uri' => $cb, 'fmt' => 'json')), 'https://graph.qq.com/oauth2.0/token'));
            if (is_wp_error($token)) {
                return $token;
            }
            if (empty($token['access_token'])) {
                return new WP_Error('qq', isset($token['error_description']) ? $token['error_description'] : __('获取令牌失败', 'xi'));
            }
            $me = xi_oauth_http(add_query_arg(array('access_token' => rawurlencode($token['access_token']), 'fmt' => 'json'), 'https://graph.qq.com/oauth2.0/me'));
            if (is_wp_error($me)) {
                return $me;
            }
            if (empty($me['openid'])) {
                return new WP_Error('qq', isset($me['error_description']) ? $me['error_description'] : __('获取 OpenID 失败', 'xi'));
            }
            $info = xi_oauth_http(add_query_arg(rawurlencode_deep(array('access_token' => $token['access_token'], 'oauth_consumer_key' => $appid, 'openid' => $me['openid'])), 'https://graph.qq.com/user/get_user_info'));
            $info = is_array($info) && isset($info['ret']) && (int) $info['ret'] === 0 ? $info : array();
            return array(
                'openid' => (string) $me['openid'],
                'name'   => isset($info['nickname']) ? $info['nickname'] : '',
                'avatar' => !empty($info['figureurl_qq_2']) ? $info['figureurl_qq_2'] : (isset($info['figureurl_qq_1']) ? $info['figureurl_qq_1'] : ''),
            );

        case 'wechat':
            $token = xi_oauth_http(add_query_arg(rawurlencode_deep(array('appid' => $appid, 'secret' => $secret, 'code' => $code, 'grant_type' => 'authorization_code')), 'https://api.weixin.qq.com/sns/oauth2/access_token'));
            if (is_wp_error($token)) {
                return $token;
            }
            if (empty($token['access_token']) || empty($token['openid'])) {
                return new WP_Error('wechat', isset($token['errmsg']) ? $token['errmsg'] : __('获取令牌失败', 'xi'));
            }
            $info = xi_oauth_http(add_query_arg(rawurlencode_deep(array('access_token' => $token['access_token'], 'openid' => $token['openid'], 'lang' => 'zh_CN')), 'https://api.weixin.qq.com/sns/userinfo'));
            $info = is_array($info) && empty($info['errcode']) ? $info : array();
            return array(
                'openid' => (string) $token['openid'],
                'name'   => isset($info['nickname']) ? $info['nickname'] : '',
                'avatar' => isset($info['headimgurl']) ? $info['headimgurl'] : '',
            );

        case 'weibo':
            $token = xi_oauth_http('https://api.weibo.com/oauth2/access_token', 'POST', array('client_id' => $appid, 'client_secret' => $secret, 'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $cb));
            if (is_wp_error($token)) {
                return $token;
            }
            if (empty($token['access_token']) || empty($token['uid'])) {
                return new WP_Error('weibo', isset($token['error_description']) ? $token['error_description'] : __('获取令牌失败', 'xi'));
            }
            $info = xi_oauth_http(add_query_arg(rawurlencode_deep(array('access_token' => $token['access_token'], 'uid' => $token['uid'])), 'https://api.weibo.com/2/users/show.json'));
            $info = is_array($info) && empty($info['error_code']) ? $info : array();
            return array(
                'openid' => (string) $token['uid'],
                'name'   => isset($info['screen_name']) ? $info['screen_name'] : '',
                'avatar' => isset($info['avatar_large']) ? $info['avatar_large'] : '',
            );

        case 'github':
        case 'gitee':
            $is_gh = $type === 'github';
            $token = xi_oauth_http(
                $is_gh ? 'https://github.com/login/oauth/access_token' : 'https://gitee.com/oauth/token',
                'POST',
                array('grant_type' => 'authorization_code', 'client_id' => $appid, 'client_secret' => $secret, 'code' => $code, 'redirect_uri' => $cb)
            );
            if (is_wp_error($token)) {
                return $token;
            }
            if (empty($token['access_token'])) {
                return new WP_Error($type, isset($token['error_description']) ? $token['error_description'] : __('获取令牌失败', 'xi'));
            }
            $info = $is_gh
                ? xi_oauth_http('https://api.github.com/user', 'GET', null, array('Authorization' => 'Bearer ' . $token['access_token']))
                : xi_oauth_http(add_query_arg('access_token', rawurlencode($token['access_token']), 'https://gitee.com/api/v5/user'));
            if (is_wp_error($info)) {
                return $info;
            }
            if (empty($info['id'])) {
                return new WP_Error($type, isset($info['message']) ? $info['message'] : __('获取用户信息失败', 'xi'));
            }
            return array(
                'openid' => (string) $info['id'],
                'name'   => !empty($info['name']) ? $info['name'] : (isset($info['login']) ? $info['login'] : ''),
                'avatar' => isset($info['avatar_url']) ? $info['avatar_url'] : '',
            );
    }
    return new WP_Error('type', __('该平台不支持官方接口', 'xi'));
}

/* ---------- 前台输出 ---------- */

function xi_oauth_icon($type)
{
    $p = xi_oauth_platform($type);
    if ($p && $p['icon'] && function_exists('xi_brand_icon')) {
        return xi_brand_icon($p['icon']);
    }
    return '<b class="xi-oauth-letter">' . esc_html(mb_substr($p ? $p['name'] : $type, 0, 1)) . '</b>';
}

/**
 * 登录弹窗底部的第三方登录按钮
 */
function xi_oauth_sign_buttons()
{
    $types = xi_oauth_enabled_types();
    if (!$types) {
        return;
    }
    $back = xi_oauth_current_url();
    echo '<div class="xi-oauth"><div class="xi-oauth-title"><span>' . esc_html__('其他方式登录', 'xi') . '</span></div><div class="xi-oauth-list">';
    foreach ($types as $type) {
        $p = xi_oauth_platform($type);
        echo '<a class="xi-oauth-btn" href="' . esc_url(xi_oauth_url($type, $back)) . '" style="--oauth:' . esc_attr($p['color']) . '" title="' . esc_attr(sprintf(__('使用%s登录', 'xi'), $p['name'])) . '" aria-label="' . esc_attr(sprintf(__('使用%s登录', 'xi'), $p['name'])) . '" rel="nofollow">' . xi_oauth_icon($type) . '</a>';
    }
    echo '</div></div>';
}
add_action('xi_sign_modal_after', 'xi_oauth_sign_buttons');

/**
 * 用户中心「账号设置」：第三方账号绑定
 */
function xi_oauth_uc_card($user)
{
    $types = xi_oauth_enabled_types();
    $bound = xi_oauth_user_bindings($user->ID);
    if (!$types && !$bound) {
        return;
    }
    $types = array_unique(array_merge($types, array_keys($bound)));
    echo '<div class="xi-card xi-uc-box"><h3 class="xi-section-title">' . esc_html__('第三方账号', 'xi') . '</h3><ul class="xi-oauth-binds">';
    foreach ($types as $type) {
        $p = xi_oauth_platform($type);
        if (!$p) {
            continue;
        }
        $info = isset($bound[$type]) ? $bound[$type] : null;
        echo '<li style="--oauth:' . esc_attr($p['color']) . '"><span class="xi-oauth-btn is-static">' . xi_oauth_icon($type) . '</span>';
        echo '<span class="xi-oauth-bind-main"><b>' . esc_html($p['name']) . '</b><small>' . ($info !== null ? esc_html(!empty($info['name']) ? sprintf(__('已绑定：%s', 'xi'), $info['name']) : __('已绑定', 'xi')) : esc_html__('未绑定', 'xi')) . '</small></span>';
        if ($info !== null) {
            echo '<button type="button" class="xi-btn xi-btn-sm" data-xi-oauth-unbind="' . esc_attr($type) . '">' . esc_html__('解绑', 'xi') . '</button>';
        } elseif (xi_oauth_channel($type)) {
            echo '<a class="xi-btn xi-btn-sm xi-btn-accent" href="' . esc_url(xi_oauth_url($type, xi_uc_url('settings'))) . '" rel="nofollow">' . esc_html__('绑定', 'xi') . '</a>';
        }
        echo '</li>';
    }
    echo '</ul></div>';
}
add_action('xi_uc_settings_after_contact', 'xi_oauth_uc_card');

/* ---------- 后台设置字段 ---------- */

function xi_oauth_option_fields()
{
    $fields = array(
        array(
            'id'      => 'oauth_s',
            'type'    => 'switcher',
            'title'   => __('启用第三方登录', 'xi'),
            'desc'    => __('登录弹窗底部显示第三方登录按钮，用户可在「用户中心 → 账号设置」中绑定 / 解绑', 'xi'),
            'default' => false,
        ),
        array(
            'dependency' => array('oauth_s', '!=', ''),
            'id'         => 'oauth_auto_reg',
            'type'       => 'switcher',
            'title'      => __('新账号自动注册', 'xi'),
            'desc'       => __('未绑定过的第三方账号首次登录时自动创建本站账号（需同时开启「允许注册」）。关闭后只能由已有用户登录后绑定', 'xi'),
            'default'    => true,
        ),
        array(
            'dependency' => array('oauth_s', '!=', ''),
            'type'       => 'subheading',
            'content'    => __('彩虹聚合登录', 'xi'),
        ),
        array(
            'dependency' => array('oauth_s', '!=', ''),
            'id'         => 'clogin_s',
            'type'       => 'switcher',
            'title'      => __('接入彩虹聚合登录', 'xi'),
            'desc'       => __('一个聚合平台账号即可提供 QQ、微信、支付宝、微博、百度、抖音、华为、小米、钉钉、GitHub、Gitee、Microsoft、Google 等登录，无需逐个申请开放平台', 'xi'),
            'default'    => false,
        ),
        array(
            'dependency'  => array(array('oauth_s', '!=', ''), array('clogin_s', '!=', '')),
            'id'          => 'clogin_api',
            'type'        => 'text',
            'title'       => __('接口地址', 'xi'),
            'placeholder' => 'https://u.example.com/',
            'desc'        => __('聚合登录平台的网址，填到域名即可（不含 connect.php）', 'xi'),
        ),
        array(
            'dependency' => array(array('oauth_s', '!=', ''), array('clogin_s', '!=', '')),
            'id'         => 'clogin_appid',
            'type'       => 'text',
            'title'      => 'APPID',
        ),
        array(
            'dependency' => array(array('oauth_s', '!=', ''), array('clogin_s', '!=', '')),
            'id'         => 'clogin_appkey',
            'type'       => 'text',
            'title'      => 'APPKEY',
            'attributes' => array('type' => 'password', 'autocomplete' => 'new-password'),
        ),
        array(
            'dependency' => array(array('oauth_s', '!=', ''), array('clogin_s', '!=', '')),
            'type'       => 'submessage',
            'style'      => 'info',
            'content'    => sprintf(__('在聚合登录平台的应用设置中，回调域名填写：<code>%s</code>', 'xi'), esc_html(wp_parse_url(home_url(), PHP_URL_HOST))),
        ),
        array(
            'dependency' => array('oauth_s', '!=', ''),
            'type'       => 'subheading',
            'content'    => __('登录平台', 'xi'),
        ),
    );

    foreach (xi_oauth_platforms() as $type => $p) {
        $on       = array('oauth_' . $type . '_s', '!=', '');
        $fields[] = array(
            'dependency' => array('oauth_s', '!=', ''),
            'id'         => 'oauth_' . $type . '_s',
            'type'       => 'switcher',
            'title'      => sprintf(__('%s登录', 'xi'), $p['name']),
            'desc'       => $p['native'] ? '' : __('通过彩虹聚合登录提供', 'xi'),
            'default'    => false,
        );
        if (!$p['native']) {
            continue;
        }
        $via      = array('oauth_' . $type . '_via', '==', 'native');
        $fields[] = array(
            'dependency' => array(array('oauth_s', '!=', ''), $on),
            'id'         => 'oauth_' . $type . '_via',
            'type'       => 'button_set',
            'title'      => sprintf(__('%s接入方式', 'xi'), $p['name']),
            'default'    => 'native',
            'options'    => array(
                'native' => __('官方接口', 'xi'),
                'clogin' => __('彩虹聚合登录', 'xi'),
            ),
        );
        $labels   = array(
            'qq'     => array('APP ID', 'APP Key', __('QQ 互联（connect.qq.com）创建网站应用', 'xi')),
            'wechat' => array('AppID', 'AppSecret', __('微信开放平台（open.weixin.qq.com）创建网站应用，扫码登录', 'xi')),
            'weibo'  => array('App Key', 'App Secret', __('微博开放平台（open.weibo.com）创建网站应用', 'xi')),
            'github' => array('Client ID', 'Client Secret', __('GitHub → Settings → Developer settings → OAuth Apps', 'xi')),
            'gitee'  => array('Client ID', 'Client Secret', __('Gitee → 设置 → 第三方应用', 'xi')),
        );
        $label    = isset($labels[$type]) ? $labels[$type] : array('AppID', 'Secret', '');
        $fields[] = array(
            'dependency' => array(array('oauth_s', '!=', ''), $on, $via),
            'id'         => 'oauth_' . $type . '_appid',
            'type'       => 'text',
            'title'      => $p['name'] . ' ' . $label[0],
            'desc'       => $label[2] . '<br>' . sprintf(__('回调地址：<code>%s</code>', 'xi'), esc_html(xi_oauth_callback_url($type))),
        );
        $fields[] = array(
            'dependency' => array(array('oauth_s', '!=', ''), $on, $via),
            'id'         => 'oauth_' . $type . '_secret',
            'type'       => 'text',
            'title'      => $p['name'] . ' ' . $label[1],
            'attributes' => array('type' => 'password', 'autocomplete' => 'new-password'),
        );
    }
    return $fields;
}
