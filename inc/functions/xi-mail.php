<?php
/**
 * 邮件：SMTP 发信、邮件模板、邮箱验证码
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SMTP 账号、授权码保存：原样保留，只去掉首尾空白
 * CSF 默认的 wp_kses_post 会把 & < > 转义甚至删掉，导致 SMTP 认证失败
 */
function xi_sanitize_smtp_credential($value)
{
    return trim((string) $value);
}

/**
 * 读取 SMTP 账号、授权码，还原旧版本被 wp_kses_post 转义后保存的值
 */
function xi_smtp_credential($name)
{
    $value = (string) _xz($name, '');
    if (strpos($value, '&') !== false) {
        $value = wp_specialchars_decode($value, ENT_QUOTES);
    }
    return trim($value);
}

/**
 * SMTP 发信配置
 */
function xi_smtp_phpmailer_init($phpmailer)
{
    if (!_xz('smtp_s', false) || !_xz('smtp_host')) {
        return;
    }
    $secure = _xz('smtp_secure', 'ssl');
    $user   = xi_smtp_credential('smtp_user');
    $port   = (int) _xz('smtp_port', 465);
    $port   = $port > 0 ? $port : 465;
    // 最常见的配置错误：465 端口选了 TLS、587 端口选了 SSL，按端口纠正
    if ($port === 465 && $secure === 'tls') {
        $secure = 'ssl';
    } elseif ($port === 587 && $secure === 'ssl') {
        $secure = 'tls';
    }

    $phpmailer->isSMTP();
    $phpmailer->Host       = trim(_xz('smtp_host'));
    $phpmailer->Port       = $port;
    $phpmailer->SMTPAuth   = $user !== '';
    $phpmailer->Username   = $user;
    $phpmailer->Password   = xi_smtp_credential('smtp_pass');
    $phpmailer->SMTPSecure = in_array($secure, array('ssl', 'tls'), true) ? $secure : '';
    $phpmailer->SMTPAutoTLS = $secure !== 'none';
    $phpmailer->CharSet    = 'UTF-8';

    $from = trim((string) _xz('smtp_from'));
    $from = $from ? $from : $user;
    if (is_email($from)) {
        $phpmailer->setFrom($from, _xz('smtp_from_name') ? _xz('smtp_from_name') : get_bloginfo('name'), false);
        $phpmailer->Sender = $from;
    }
}
add_action('phpmailer_init', 'xi_smtp_phpmailer_init');

/**
 * 主题邮件模板
 */
function xi_mail_template($title, $content)
{
    $color = xi_theme_color();
    $site  = esc_html(get_bloginfo('name'));

    return '<div style="background:#eef4f2;padding:32px 12px;font-family:-apple-system,Segoe UI,PingFang SC,Microsoft YaHei,sans-serif;">'
        . '<div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(10,40,34,.08);">'
        . '<div style="padding:22px 28px;background:' . $color . ';color:#fff;font-size:18px;font-weight:600;">' . $site . '</div>'
        . '<div style="padding:28px;color:#17221f;font-size:15px;line-height:1.8;">'
        . '<h2 style="margin:0 0 12px;font-size:18px;">' . esc_html($title) . '</h2>'
        . $content
        . '</div>'
        . '<div style="padding:14px 28px;border-top:1px solid #e5eeeb;color:#84928e;font-size:12px;">此邮件由系统自动发送，请勿直接回复 · <a href="' . esc_url(home_url('/')) . '" style="color:' . $color . ';text-decoration:none;">' . $site . '</a></div>'
        . '</div></div>';
}

/**
 * 发送主题邮件；失败原因记录下来，方便管理员在设置页排查
 */
function xi_send_mail($to, $subject, $title, $content)
{
    $headers = array('Content-Type: text/html; charset=UTF-8');
    $error   = '';
    $catch   = function ($wp_error) use (&$error) {
        $error = $wp_error->get_error_message();
    };
    add_action('wp_mail_failed', $catch);
    $sent = wp_mail($to, '[' . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES) . '] ' . $subject, xi_mail_template($title, $content), $headers);
    remove_action('wp_mail_failed', $catch);

    if (!$sent) {
        update_option('xi_mail_last_error', array('time' => time(), 'to' => $to, 'msg' => $error ? $error : __('未知错误', 'xi')), false);
    }
    return $sent;
}

/**
 * 最近一次发信失败的原因（仅管理员可见）
 */
function xi_mail_last_error_text()
{
    $last = get_option('xi_mail_last_error');
    if (!is_array($last) || empty($last['msg'])) {
        return '';
    }
    return sprintf(__('%1$s 发往 %2$s 失败：%3$s', 'xi'), wp_date('Y-m-d H:i', (int) $last['time']), xi_mask_email($last['to']), $last['msg']);
}

/* ---------- 邮箱验证码 ---------- */

/**
 * 统一验证码接收方的写法：邮箱转小写，手机号去掉空白
 * 发送和校验两端都经过这里，保证同一个邮箱算出同一个缓存键
 */
function xi_code_target($to)
{
    $to = trim((string) $to);
    return is_email($to) ? strtolower(sanitize_email($to)) : preg_replace('/\s+/', '', $to);
}

function xi_code_key($purpose, $email)
{
    return 'xi_code_' . md5($purpose . '|' . xi_code_target($email));
}

/**
 * 校验通过、业务也完成后再作废验证码：注册或改邮箱中途失败时用户不必重新获取
 */
function xi_consume_code($purpose, $to)
{
    delete_transient(xi_code_key($purpose, $to));
}

/**
 * 访客 IP：默认 REMOTE_ADDR；站点在 CDN / 反向代理后面时按设置读取代理传来的真实 IP
 */
function xi_client_ip()
{
    static $ip = null;
    if ($ip !== null) {
        return $ip;
    }
    $headers = array(
        'xff'  => 'HTTP_X_FORWARDED_FOR',
        'real' => 'HTTP_X_REAL_IP',
        'cf'   => 'HTTP_CF_CONNECTING_IP',
    );
    $source = _xz('client_ip_source', 'remote');
    $ip     = '';
    if (isset($headers[$source]) && !empty($_SERVER[$headers[$source]])) {
        // X-Forwarded-For 可能是逗号分隔的链路，第一个是客户端
        $first = trim(explode(',', wp_unslash($_SERVER[$headers[$source]]))[0]);
        $ip    = filter_var($first, FILTER_VALIDATE_IP) ? $first : '';
    }
    if ($ip === '' && isset($_SERVER['REMOTE_ADDR'])) {
        $remote = trim(wp_unslash($_SERVER['REMOTE_ADDR']));
        $ip     = filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '';
    }
    return $ip;
}

/**
 * 发送验证码
 *
 * @return true|WP_Error
 */
function xi_send_email_code($purpose, $email)
{
    $email      = xi_code_target($email);
    $lock_email = 'xi_code_lock_' . md5($email);
    if (get_transient($lock_email)) {
        return new WP_Error('frequent', __('发送太频繁，请 60 秒后再试', 'xi'));
    }
    $ip_key   = 'xi_code_ip_' . md5(xi_client_ip());
    $ip_count = (int) get_transient($ip_key);
    if ($ip_count >= 10) {
        return new WP_Error('frequent', __('发送次数过多，请稍后再试', 'xi'));
    }

    $code = (string) wp_rand(100000, 999999);
    $texts = array(
        'lostpwd'  => __('你正在找回密码', 'xi'),
        'register' => __('你正在注册账号', 'xi'),
        'email'    => __('你正在修改账号绑定的邮箱', 'xi'),
    );
    $text  = isset($texts[$purpose]) ? $texts[$purpose] : $texts['register'];
    $sent = xi_send_mail(
        $email,
        __('邮箱验证码', 'xi'),
        $text,
        '<p>' . esc_html__('你的验证码是：', 'xi') . '</p>'
        . '<p style="margin:16px 0;font-size:30px;font-weight:700;letter-spacing:6px;color:#17221f;">' . $code . '</p>'
        . '<p style="color:#84928e;font-size:13px;">' . esc_html__('验证码 10 分钟内有效。如果不是你本人操作，请忽略此邮件。', 'xi') . '</p>'
    );
    if (!$sent) {
        $msg = __('邮件发送失败，请联系站长检查邮件设置', 'xi');
        if (current_user_can('manage_options')) {
            $msg .= '：' . xi_mail_last_error_text();
        }
        return new WP_Error('mail', $msg);
    }

    set_transient(xi_code_key($purpose, $email), array('hash' => wp_hash($code), 'tries' => 0, 'expires' => time() + 10 * MINUTE_IN_SECONDS), 10 * MINUTE_IN_SECONDS);
    set_transient($lock_email, 1, 60);
    set_transient($ip_key, $ip_count + 1, HOUR_IN_SECONDS);
    return true;
}

/**
 * 校验验证码，最多尝试 5 次
 *
 * @param bool $consume 校验通过后立即作废；传 false 时由调用方在业务完成后调用 xi_consume_code()
 * @return true|WP_Error
 */
function xi_verify_email_code($purpose, $email, $code, $consume = true)
{
    $code = preg_replace('/\s+/', '', (string) $code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return new WP_Error('code', __('请输入 6 位数字验证码', 'xi'));
    }
    $key  = xi_code_key($purpose, $email);
    $data = get_transient($key);
    if (!$data || empty($data['hash'])) {
        return new WP_Error('code', is_email($email) ? __('请先获取邮箱验证码', 'xi') : __('请先获取短信验证码', 'xi'));
    }
    if ($data['tries'] >= 5) {
        delete_transient($key);
        return new WP_Error('code', __('验证码错误次数过多，请重新获取', 'xi'));
    }
    if (!hash_equals($data['hash'], wp_hash($code))) {
        $data['tries'] = (int) $data['tries'] + 1;
        // 保留原来的过期时间，不能因为输错一次就把有效期重新延长到 10 分钟
        $left = isset($data['expires']) ? (int) $data['expires'] - time() : 10 * MINUTE_IN_SECONDS;
        if ($left > 0) {
            set_transient($key, $data, $left);
        } else {
            delete_transient($key);
        }
        return new WP_Error('code', is_email($email) ? __('邮箱验证码错误', 'xi') : __('短信验证码错误', 'xi'));
    }
    if ($consume) {
        delete_transient($key);
    }
    return true;
}

/**
 * 后台：发送测试邮件
 */
function xi_ajax_smtp_test()
{
    check_ajax_referer('xi_smtp_test', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('msg' => __('权限不足', 'xi')));
    }
    $to = isset($_POST['to']) ? sanitize_email(wp_unslash($_POST['to'])) : '';
    $to = is_email($to) ? $to : get_option('admin_email');

    $sent = xi_send_mail($to, __('测试邮件', 'xi'), __('邮件设置成功', 'xi'), '<p>' . esc_html__('收到这封邮件，说明你的 SMTP 设置可以正常发信。', 'xi') . '</p>');
    if ($sent) {
        delete_option('xi_mail_last_error');
        wp_send_json_success(array('msg' => sprintf(__('已发送到 %s，请查收', 'xi'), $to)));
    }
    $last = get_option('xi_mail_last_error');
    wp_send_json_error(array('msg' => __('发送失败：', 'xi') . (is_array($last) && !empty($last['msg']) ? $last['msg'] : __('未知错误', 'xi'))));
}
add_action('wp_ajax_xi_smtp_test', 'xi_ajax_smtp_test');
