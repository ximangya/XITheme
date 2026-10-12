<?php
/**
 * 前台登录 / 注册 / 找回密码
 * - 替换 WordPress 的登录、注册、找回密码地址为前台弹窗
 * - 人机验证（算术题）与注册邮箱验证码由后台开关控制
 * - 开启短信后：可选手机号注册、手机号登录、手机号找回密码
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_sign_enabled()
{
    return (bool) _xz('sign_modal_s', true);
}

function xi_reg_enabled()
{
    return (bool) _xz('sign_reg_s', true);
}

/**
 * 注册方式：email | phone（手机号注册需要开启短信）
 */
function xi_reg_mode()
{
    return _xz('sign_reg_mode', 'email') === 'phone' && xi_sms_enabled() ? 'phone' : 'email';
}

function xi_phone_login_enabled()
{
    return xi_sms_enabled() && (bool) _xz('sign_phone_login_s', true);
}

function xi_lostpwd_phone_enabled()
{
    return xi_sms_enabled() && (bool) _xz('sign_lostpwd_phone_s', true);
}

/**
 * 找回密码等场景：用邮箱或已绑定的手机号查用户
 *
 * @return WP_User|false
 */
function xi_get_user_by_account($account)
{
    if (is_email($account)) {
        return get_user_by('email', $account);
    }
    if (xi_lostpwd_phone_enabled() && xi_is_phone($account)) {
        return xi_get_user_by_phone($account);
    }
    return false;
}

/**
 * 按格式把验证码发到邮箱或手机
 */
function xi_send_code_to($purpose, $to)
{
    return is_email($to) ? xi_send_email_code($purpose, $to) : xi_send_sms_code($purpose, $to);
}

/**
 * 前台弹窗地址：首页 + ?xi_sign=login|register|lostpwd
 */
function xi_sign_url($type = 'login', $redirect = '')
{
    $args = array('xi_sign' => $type);
    if ($redirect) {
        $args['redirect_to'] = rawurlencode($redirect);
    }
    return add_query_arg($args, home_url('/'));
}

/* ---------- 替换 WordPress 地址 ---------- */

function xi_filter_login_url($login_url, $redirect, $force_reauth)
{
    // 后台会话过期的 interim 登录、安全模式仍走 wp-login.php
    if ($force_reauth || !xi_sign_enabled() || (function_exists('xi_safe_active') && xi_safe_active())) {
        return $login_url;
    }
    return xi_sign_url('login', $redirect);
}
add_filter('login_url', 'xi_filter_login_url', 20, 3);

function xi_filter_register_url($url)
{
    return xi_sign_enabled() && !(function_exists('xi_safe_active') && xi_safe_active()) ? xi_sign_url('register') : $url;
}
add_filter('register_url', 'xi_filter_register_url', 20);

function xi_filter_lostpassword_url($url, $redirect)
{
    return xi_sign_enabled() && !(function_exists('xi_safe_active') && xi_safe_active()) ? xi_sign_url('lostpwd', $redirect) : $url;
}
add_filter('lostpassword_url', 'xi_filter_lostpassword_url', 20, 2);

/**
 * 可选：禁止直接访问 wp-login.php 的登录、注册、找回密码页面
 */
function xi_block_wp_login()
{
    if (!xi_sign_enabled() || !_xz('sign_block_wp_login', false) || $_SERVER['REQUEST_METHOD'] === 'POST' || (function_exists('xi_safe_active') && xi_safe_active())) {
        return;
    }
    $action = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : 'login';
    if (isset($_REQUEST['interim-login']) || !in_array($action, array('login', 'register', 'lostpassword', 'retrievepassword'), true)) {
        return;
    }
    $map      = array('register' => 'register', 'lostpassword' => 'lostpwd', 'retrievepassword' => 'lostpwd');
    $redirect = isset($_REQUEST['redirect_to']) ? esc_url_raw(wp_unslash($_REQUEST['redirect_to'])) : '';
    wp_safe_redirect(xi_sign_url(isset($map[$action]) ? $map[$action] : 'login', $redirect));
    exit;
}
add_action('login_init', 'xi_block_wp_login');

/* ---------- 人机验证：算术题 ---------- */

function xi_captcha_enabled()
{
    return (bool) _xz('sign_captcha_s', true);
}

function xi_captcha_new()
{
    $a  = wp_rand(1, 9);
    $b  = wp_rand(1, 9);
    $op = wp_rand(0, 1);
    if ($op) {
        $question = $a . ' × ' . $b . ' = ?';
        $answer   = $a * $b;
    } else {
        $question = $a . ' + ' . $b . ' = ?';
        $answer   = $a + $b;
    }
    $token = wp_generate_password(20, false);
    set_transient('xi_cap_' . $token, $answer, 10 * MINUTE_IN_SECONDS);
    return array('token' => $token, 'question' => $question);
}

/**
 * 校验后立即作废，一题只能用一次
 */
function xi_captcha_check()
{
    if (!xi_captcha_enabled()) {
        return true;
    }
    $token  = isset($_POST['captcha_token']) ? sanitize_key(wp_unslash($_POST['captcha_token'])) : '';
    $answer = isset($_POST['captcha']) ? trim(sanitize_text_field(wp_unslash($_POST['captcha']))) : '';
    $saved  = $token ? get_transient('xi_cap_' . $token) : false;
    if ($token) {
        delete_transient('xi_cap_' . $token);
    }
    if ($saved === false || $answer === '' || (string) (int) $answer !== (string) $saved) {
        return new WP_Error('captcha', __('人机验证答案错误', 'xi'));
    }
    return true;
}

/* ---------- 登录失败限制：前台弹窗、wp-login.php、XML-RPC 统一计数 ---------- */

function xi_login_max_fails()
{
    return max(3, (int) _xz('sign_fail_limit', 5));
}

function xi_login_fail_key()
{
    return 'xi_login_fail_' . md5(xi_client_ip());
}

function xi_login_fail_count()
{
    return (int) get_transient(xi_login_fail_key());
}

function xi_login_locked()
{
    return xi_login_fail_count() >= xi_login_max_fails();
}

function xi_login_locked_message()
{
    return __('登录失败次数过多，请 10 分钟后再试', 'xi');
}

function xi_login_fail_reset()
{
    delete_transient(xi_login_fail_key());
}

/**
 * 锁定期间即使密码正确也拒绝登录（优先级在校验密码之后、封禁检查之前）
 */
function xi_login_lock_authenticate($user)
{
    // 安全入口进入的管理员不受锁定影响（密码仍需正确）
    if (function_exists('xi_safe_active') && xi_safe_active()) {
        return $user;
    }
    if (($user instanceof WP_User || is_wp_error($user)) && xi_login_locked()) {
        return new WP_Error('xi_login_locked', xi_login_locked_message());
    }
    return $user;
}
add_filter('authenticate', 'xi_login_lock_authenticate', 99);

function xi_login_failed_count($username, $error = null)
{
    if ($error instanceof WP_Error && array_intersect($error->get_error_codes(), array('xi_login_locked', 'xi_banned', 'xi_ip_banned', 'empty_username', 'empty_password'))) {
        return;
    }
    set_transient(xi_login_fail_key(), xi_login_fail_count() + 1, 10 * MINUTE_IN_SECONDS);
}
add_action('wp_login_failed', 'xi_login_failed_count', 10, 2);

function xi_login_success_reset()
{
    xi_login_fail_reset();
}
add_action('wp_login', 'xi_login_success_reset');

/**
 * 可选：关闭 XML-RPC（大多数站点用不到，是暴力破解的常见入口）
 */
function xi_xmlrpc_switch($enabled)
{
    return _xz('sign_xmlrpc_off', false) ? false : $enabled;
}
add_filter('xmlrpc_enabled', 'xi_xmlrpc_switch');

/* ---------- 注册规则 ---------- */

/**
 * 用户名校验：3-20 位字母、数字、下划线；不能是手机号或保留名称
 *
 * @return string 错误信息，合法时为空
 */
function xi_username_error($login)
{
    $login = (string) $login;
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $login)) {
        return __('用户名需为 3-20 位字母、数字或下划线', 'xi');
    }
    // 手机号格式的用户名会抢占别人的手机号登录入口
    if (preg_match('/^1[3-9]\d{9}$/', $login)) {
        return __('用户名不能是手机号', 'xi');
    }
    $reserved = apply_filters('xi_reserved_usernames', array('admin', 'administrator', 'root', 'system', 'webmaster', 'support', 'service', 'official', 'kefu', 'guanfang'));
    if (in_array(strtolower($login), $reserved, true)) {
        return __('该用户名为保留名称，请换一个', 'xi');
    }
    return '';
}

/**
 * 新用户角色：默认角色被误设为有管理权限的角色时，降为订阅者
 */
function xi_register_role()
{
    $role = get_option('default_role', 'subscriber');
    $obj  = get_role($role);
    if (!$obj || $obj->has_cap('edit_others_posts') || $obj->has_cap('manage_options') || $obj->has_cap('promote_users')) {
        return 'subscriber';
    }
    return $role;
}

/**
 * 同一 IP 每天注册上限（0 为不限）
 */
function xi_reg_ip_limit()
{
    return max(0, (int) _xz('sign_reg_ip_limit', 5));
}

function xi_reg_ip_key()
{
    return 'xi_reg_ip_' . md5(xi_client_ip() . '|' . wp_date('Ymd'));
}

function xi_reg_ip_allowed()
{
    $limit = xi_reg_ip_limit();
    return !$limit || (int) get_transient(xi_reg_ip_key()) < $limit;
}

function xi_reg_ip_count()
{
    set_transient(xi_reg_ip_key(), (int) get_transient(xi_reg_ip_key()) + 1, DAY_IN_SECONDS);
}

/**
 * 开启前台登录后，注册只能走主题弹窗（人机验证、邮箱 / 短信验证码、注册开关都在那里），
 * 直接 POST 到 wp-login.php?action=register 会绕过这些校验
 */
function xi_block_core_register($errors)
{
    if (xi_sign_enabled()) {
        $errors->add('xi_register_closed', xi_reg_enabled() ? __('请使用本站的注册窗口注册', 'xi') : __('本站暂未开放注册', 'xi'));
    }
    return $errors;
}
add_filter('registration_errors', 'xi_block_core_register', 1);

/* ---------- Ajax ---------- */

function xi_sign_response($ok, $msg, $data = array())
{
    wp_send_json(array_merge(array('ok' => $ok, 'msg' => $msg), $data));
}

function xi_sign_check_request()
{
    if (!check_ajax_referer('xi_sign', 'nonce', false)) {
        // 页面被缓存插件缓存后 nonce 会过期：前端拿到 expired 会自动换新 nonce 重试一次
        xi_sign_response(false, __('页面已过期，请刷新后重试', 'xi'), array('expired' => true));
    }
    if (!xi_sign_enabled()) {
        xi_sign_response(false, __('前台登录已关闭', 'xi'));
    }
    // 黑名单 IP 不能登录、注册、发送验证码
    if (function_exists('xi_ip_banned') && xi_ip_banned()) {
        xi_sign_response(false, xi_ip_ban_message());
    }
}

function xi_post_field($key)
{
    return isset($_POST[$key]) ? trim(sanitize_text_field(wp_unslash($_POST[$key]))) : '';
}

/**
 * 换取新的 nonce：页面缓存导致 nonce 过期时由前端自动调用
 */
function xi_ajax_sign_nonce()
{
    nocache_headers();
    xi_sign_response(true, '', array('nonce' => wp_create_nonce('xi_sign')));
}
add_action('wp_ajax_nopriv_xi_sign_nonce', 'xi_ajax_sign_nonce');

/**
 * 已登录用户（例如在另一个标签页刚登录）再提交登录、注册表单：直接刷新，不再报「网络错误」
 */
function xi_ajax_sign_logged_in()
{
    xi_sign_response(true, __('你已登录', 'xi'), array('reload' => true));
}
foreach (array('xi_login', 'xi_register', 'xi_lostpwd', 'xi_send_code', 'xi_sign_nonce') as $xi_sign_action) {
    add_action('wp_ajax_' . $xi_sign_action, 'xi_ajax_sign_logged_in');
}
unset($xi_sign_action);

function xi_ajax_captcha()
{
    xi_sign_check_request();
    xi_sign_response(true, '', xi_captcha_new());
}
add_action('wp_ajax_nopriv_xi_captcha', 'xi_ajax_captcha');
add_action('wp_ajax_xi_captcha', 'xi_ajax_captcha');

function xi_ajax_login()
{
    xi_sign_check_request();

    if (xi_login_locked()) {
        xi_sign_response(false, xi_login_locked_message());
    }

    $check = xi_captcha_check();
    if (is_wp_error($check)) {
        xi_sign_response(false, $check->get_error_message(), array('refresh' => true));
    }

    $login = xi_post_field('user');
    $pass  = isset($_POST['pass']) ? (string) wp_unslash($_POST['pass']) : '';
    if (!$login || !$pass) {
        xi_sign_response(false, __('请输入账号和密码', 'xi'), array('refresh' => true));
    }
    if (is_email($login)) {
        $by_email = get_user_by('email', $login);
        $login    = $by_email ? $by_email->user_login : $login;
    } elseif (xi_phone_login_enabled() && xi_is_phone($login) && !username_exists($login)) {
        $by_phone = xi_get_user_by_phone($login);
        $login    = $by_phone ? $by_phone->user_login : $login;
    }

    $user = wp_signon(array(
        'user_login'    => $login,
        'user_password' => $pass,
        'remember'      => !empty($_POST['remember']),
    ), is_ssl());

    if (is_wp_error($user) && in_array($user->get_error_code(), array('xi_banned', 'xi_ip_banned', 'xi_login_locked'), true)) {
        // 账号被封或 IP 已锁定：提示原因（失败次数由 wp_login_failed 统一统计）
        xi_sign_response(false, $user->get_error_message(), array('refresh' => true));
    }
    if (is_wp_error($user)) {
        $left = xi_login_max_fails() - xi_login_fail_count();
        xi_sign_response(false, $left > 0 && $left <= 2 ? sprintf(__('账号或密码错误，再错 %d 次将锁定 10 分钟', 'xi'), $left) : __('账号或密码错误', 'xi'), array('refresh' => true));
    }

    xi_sign_response(true, __('登录成功', 'xi'), array('reload' => true));
}
add_action('wp_ajax_nopriv_xi_login', 'xi_ajax_login');

function xi_ajax_send_code()
{
    xi_sign_check_request();

    $purpose = xi_post_field('purpose') === 'lostpwd' ? 'lostpwd' : 'register';
    $to      = xi_post_field('to');
    $to      = $to ? $to : xi_post_field('email');
    $phone   = xi_is_phone($to);

    if ($purpose === 'register') {
        if (!xi_reg_enabled()) {
            xi_sign_response(false, __('本站暂未开放注册', 'xi'));
        }
        if (xi_reg_mode() === 'phone') {
            if (!$phone) {
                xi_sign_response(false, __('请输入正确的手机号', 'xi'));
            }
        } elseif (!_xz('sign_reg_email_s', false)) {
            xi_sign_response(false, __('当前无需验证码', 'xi'));
        } elseif (!is_email($to)) {
            xi_sign_response(false, __('请输入正确的邮箱地址', 'xi'));
        }
    } elseif (!is_email($to) && !($phone && xi_lostpwd_phone_enabled())) {
        xi_sign_response(false, xi_lostpwd_phone_enabled() ? __('请输入正确的邮箱或手机号', 'xi') : __('请输入正确的邮箱地址', 'xi'));
    }

    $check = xi_captcha_check();
    if (is_wp_error($check)) {
        xi_sign_response(false, $check->get_error_message(), array('refresh' => true));
    }

    if ($purpose === 'register') {
        $used = $phone ? xi_get_user_by_phone($to) : email_exists($to);
        if ($used) {
            xi_sign_response(false, $phone ? __('该手机号已注册，可直接登录', 'xi') : __('该邮箱已注册，可直接登录', 'xi'), array('refresh' => true));
        }
    } elseif (!xi_get_user_by_account($to)) {
        xi_sign_response(false, $phone ? __('该手机号未绑定任何账号', 'xi') : __('该邮箱尚未注册', 'xi'), array('refresh' => true));
    }

    $sent = xi_send_code_to($purpose, $to);
    if (is_wp_error($sent)) {
        xi_sign_response(false, $sent->get_error_message(), array('refresh' => true));
    }
    $msg = $phone ? __('验证码已发送，请查收短信', 'xi') : __('验证码已发送，请查收邮箱', 'xi');
    xi_sign_response(true, $msg, array('countdown' => 60, 'refresh' => true));
}
add_action('wp_ajax_nopriv_xi_send_code', 'xi_ajax_send_code');

function xi_ajax_register()
{
    xi_sign_check_request();
    if (!xi_reg_enabled()) {
        xi_sign_response(false, __('本站暂未开放注册', 'xi'));
    }

    $mode      = xi_reg_mode();
    $need_code = $mode === 'phone' || (bool) _xz('sign_reg_email_s', false);

    // 需要验证码时，人机验证已在发送验证码时完成
    if (!$need_code) {
        $check = xi_captcha_check();
        if (is_wp_error($check)) {
            xi_sign_response(false, $check->get_error_message(), array('refresh' => true));
        }
    }

    $login = xi_post_field('user');
    $email = sanitize_email(xi_post_field('email'));
    $phone = xi_post_field('phone');
    $pass  = isset($_POST['pass']) ? (string) wp_unslash($_POST['pass']) : '';

    $error = xi_username_error($login);
    if (!$error && !xi_reg_ip_allowed()) {
        $error = __('该网络今天注册的账号过多，请明天再试', 'xi');
    }
    if ($error) {
        // 用户名或频率不合格，直接返回下方的错误
    } elseif (username_exists($login)) {
        $error = __('用户名已被使用', 'xi');
    } elseif ($mode === 'phone' && !xi_is_phone($phone)) {
        $error = __('请输入正确的手机号', 'xi');
    } elseif ($mode === 'phone' && xi_get_user_by_phone($phone)) {
        $error = __('该手机号已注册，可直接登录', 'xi');
    } elseif ($mode === 'email' && !is_email($email)) {
        $error = __('请输入正确的邮箱地址', 'xi');
    } elseif ($email && (!is_email($email) || email_exists($email))) {
        $error = is_email($email) ? __('该邮箱已注册，可直接登录', 'xi') : __('请输入正确的邮箱地址', 'xi');
    } elseif (strlen($pass) < 6) {
        $error = __('密码至少 6 位', 'xi');
    }
    if ($error) {
        xi_sign_response(false, $error, array('refresh' => !$need_code));
    }

    $code_to = $mode === 'phone' ? $phone : $email;
    if ($need_code) {
        $verify = xi_verify_code('register', $code_to, xi_post_field('code'), false);
        if (is_wp_error($verify)) {
            xi_sign_response(false, $verify->get_error_message());
        }
    }

    $user_id = wp_insert_user(array(
        'user_login'   => $login,
        'user_email'   => $email,
        'user_pass'    => $pass,
        'display_name' => $login,
        'role'         => xi_register_role(),
    ));
    if (is_wp_error($user_id)) {
        xi_sign_response(false, $user_id->get_error_message(), array('refresh' => !$need_code));
    }
    xi_reg_ip_count();
    if ($need_code) {
        xi_consume_code('register', $code_to);
    }
    if ($mode === 'phone') {
        update_user_meta($user_id, 'xi_phone', $phone);
    }
    if ($email && $need_code) {
        // 通过验证码确认过的邮箱
        update_user_meta($user_id, 'xi_email_verified', 1);
    }

    wp_new_user_notification($user_id, null, 'admin');
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());
    do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));
    do_action('xi_user_registered', $user_id, $mode);

    xi_sign_response(true, __('注册成功，欢迎加入', 'xi'), array('reload' => true));
}
add_action('wp_ajax_nopriv_xi_register', 'xi_ajax_register');

function xi_ajax_lostpwd()
{
    xi_sign_check_request();

    $account = xi_post_field('account');
    $account = $account ? $account : xi_post_field('email');
    $pass    = isset($_POST['pass']) ? (string) wp_unslash($_POST['pass']) : '';
    $user    = xi_get_user_by_account($account);

    if (!$user) {
        xi_sign_response(false, xi_is_phone($account) ? __('该手机号未绑定任何账号', 'xi') : __('该邮箱尚未注册', 'xi'));
    }
    if (strlen($pass) < 6) {
        xi_sign_response(false, __('新密码至少 6 位', 'xi'));
    }
    $verify = xi_verify_code('lostpwd', $account, xi_post_field('code'), false);
    if (is_wp_error($verify)) {
        xi_sign_response(false, $verify->get_error_message());
    }

    reset_password($user, $pass);
    xi_consume_code('lostpwd', $account);
    delete_user_meta($user->ID, 'xi_pass_unset');
    if (is_email($account)) {
        update_user_meta($user->ID, 'xi_email_verified', 1);
    }
    // 失败计数也一并清掉，找回密码后可以马上登录
    xi_login_fail_reset();
    xi_sign_response(true, __('密码已重置，请使用新密码登录', 'xi'), array('switch' => 'login', 'login' => $user->user_login));
}
add_action('wp_ajax_nopriv_xi_lostpwd', 'xi_ajax_lostpwd');

/* ---------- 前台资源与弹窗 ---------- */

function xi_sign_enqueue()
{
    if (is_user_logged_in() || !xi_sign_enabled()) {
        return;
    }
    wp_enqueue_script('xi-sign', get_template_directory_uri() . '/assets/js/sign.js', array(), XI_VERSION, true);
    wp_localize_script('xi-sign', 'xiSign', array(
        'ajax'    => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('xi_sign'),
        'captcha' => xi_captcha_enabled(),
    ));
}
add_action('wp_enqueue_scripts', 'xi_sign_enqueue');

/**
 * @param bool $for_code 只在发送验证码时校验（注册需验证码、找回密码），提交表单时不再要求填写
 */
function xi_sign_captcha_row($for_code = false)
{
    if (!xi_captcha_enabled()) {
        return '';
    }
    return '<div class="xi-sign-row xi-sign-inline">'
        . '<input type="text" name="captcha" inputmode="numeric" autocomplete="off" placeholder="' . esc_attr__('人机验证答案', 'xi') . '"' . ($for_code ? ' data-for-code' : ' required') . '>'
        . '<button type="button" class="xi-captcha" title="' . esc_attr__('看不清？点击换一题', 'xi') . '">…</button>'
        . '<input type="hidden" name="captcha_token">'
        . '</div>';
}

/**
 * 验证码输入行
 *
 * @param string $field 接收验证码的输入框 name（email、phone、account）
 */
function xi_sign_code_row($purpose, $field = 'email', $placeholder = '')
{
    $placeholder = $placeholder ? $placeholder : __('邮箱验证码', 'xi');
    return '<div class="xi-sign-row xi-sign-inline">'
        . '<input type="text" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="' . esc_attr($placeholder) . '" required>'
        . '<button type="button" class="xi-btn xi-send-code" data-purpose="' . esc_attr($purpose) . '" data-field="' . esc_attr($field) . '">' . esc_html__('获取验证码', 'xi') . '</button>'
        . '</div>';
}

function xi_sign_modal()
{
    if (is_user_logged_in() || !xi_sign_enabled()) {
        return;
    }
    $reg       = xi_reg_enabled();
    $reg_mode  = xi_reg_mode();
    $reg_email = (bool) _xz('sign_reg_email_s', false);
    $lost_sms  = xi_lostpwd_phone_enabled();
    $logo      = _xz('logo');
    ?>
    <div class="xi-sign" id="xi-sign" aria-hidden="true">
        <div class="xi-sign-mask" data-sign-close></div>
        <div class="xi-sign-box xi-glass" role="dialog" aria-modal="true" aria-labelledby="xi-sign-title">
            <button type="button" class="xi-btn-icon xi-sign-close" data-sign-close aria-label="<?php esc_attr_e('关闭', 'xi'); ?>"><?php echo xi_icon('close'); ?></button>

            <div class="xi-sign-head">
                <?php if ($logo) : ?>
                    <img src="<?php echo esc_url($logo); ?>" alt="">
                <?php else : ?>
                    <span class="xi-logo-mark"><?php echo xi_icon('leaf'); ?></span>
                <?php endif; ?>
                <b id="xi-sign-title"><?php echo esc_html(get_bloginfo('name')); ?></b>
            </div>

            <div class="xi-sign-tabs" role="tablist">
                <button type="button" data-sign-tab="login" role="tab"><?php esc_html_e('登录', 'xi'); ?></button>
                <?php if ($reg) : ?>
                    <button type="button" data-sign-tab="register" role="tab"><?php esc_html_e('注册', 'xi'); ?></button>
                <?php endif; ?>
                <button type="button" data-sign-tab="lostpwd" role="tab" class="is-hidden"><?php esc_html_e('找回密码', 'xi'); ?></button>
            </div>

            <form class="xi-sign-form" data-sign-form="login" novalidate>
                <div class="xi-sign-row"><input type="text" name="user" autocomplete="username" placeholder="<?php echo esc_attr(xi_phone_login_enabled() ? __('用户名 / 邮箱 / 手机号', 'xi') : __('用户名或邮箱', 'xi')); ?>" required></div>
                <div class="xi-sign-row"><input type="password" name="pass" autocomplete="current-password" placeholder="<?php esc_attr_e('密码', 'xi'); ?>" required></div>
                <?php echo xi_sign_captcha_row(); ?>
                <div class="xi-sign-extra">
                    <label><input type="checkbox" name="remember" value="1" checked> <?php esc_html_e('记住我', 'xi'); ?></label>
                    <a href="<?php echo esc_url(xi_sign_url('lostpwd')); ?>" data-xi-sign="lostpwd"><?php esc_html_e('忘记密码？', 'xi'); ?></a>
                </div>
                <button type="submit" class="xi-btn xi-btn-accent xi-sign-submit"><?php esc_html_e('登录', 'xi'); ?></button>
            </form>

            <?php if ($reg) : ?>
                <form class="xi-sign-form" data-sign-form="register" novalidate>
                    <div class="xi-sign-row"><input type="text" name="user" autocomplete="username" placeholder="<?php esc_attr_e('用户名（3-20 位字母、数字）', 'xi'); ?>" required></div>
                    <?php if ($reg_mode === 'phone') : ?>
                        <div class="xi-sign-row"><input type="tel" name="phone" autocomplete="tel" inputmode="tel" placeholder="<?php esc_attr_e('手机号', 'xi'); ?>" required></div>
                        <?php echo xi_sign_captcha_row(true); ?>
                        <?php echo xi_sign_code_row('register', 'phone', __('短信验证码', 'xi')); ?>
                    <?php else : ?>
                        <div class="xi-sign-row"><input type="email" name="email" autocomplete="email" placeholder="<?php esc_attr_e('邮箱', 'xi'); ?>" required></div>
                        <?php echo xi_sign_captcha_row($reg_email); ?>
                        <?php echo $reg_email ? xi_sign_code_row('register') : ''; ?>
                    <?php endif; ?>
                    <div class="xi-sign-row"><input type="password" name="pass" autocomplete="new-password" placeholder="<?php esc_attr_e('设置密码（至少 6 位）', 'xi'); ?>" required></div>
                    <button type="submit" class="xi-btn xi-btn-accent xi-sign-submit"><?php esc_html_e('注册', 'xi'); ?></button>
                </form>
            <?php endif; ?>

            <form class="xi-sign-form" data-sign-form="lostpwd" novalidate>
                <p class="xi-sign-tip"><?php echo esc_html($lost_sms ? __('输入注册邮箱或绑定的手机号获取验证码，验证后即可设置新密码', 'xi') : __('输入注册邮箱获取验证码，验证后即可设置新密码', 'xi')); ?></p>
                <div class="xi-sign-row"><input type="text" name="account" autocomplete="username" placeholder="<?php echo esc_attr($lost_sms ? __('邮箱或手机号', 'xi') : __('注册邮箱', 'xi')); ?>" required></div>
                <?php echo xi_sign_captcha_row(true); ?>
                <?php echo xi_sign_code_row('lostpwd', 'account', __('验证码', 'xi')); ?>
                <div class="xi-sign-row"><input type="password" name="pass" autocomplete="new-password" placeholder="<?php esc_attr_e('新密码（至少 6 位）', 'xi'); ?>" required></div>
                <button type="submit" class="xi-btn xi-btn-accent xi-sign-submit"><?php esc_html_e('重置密码', 'xi'); ?></button>
                <div class="xi-sign-extra is-center"><a href="<?php echo esc_url(xi_sign_url('login')); ?>" data-xi-sign="login"><?php esc_html_e('返回登录', 'xi'); ?></a></div>
            </form>

            <div class="xi-sign-msg" role="alert" aria-live="polite"></div>

            <?php do_action('xi_sign_modal_after'); ?>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'xi_sign_modal', 5);
