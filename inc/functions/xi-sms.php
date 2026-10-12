<?php
/**
 * 手机短信：阿里云（短信服务 / 号码认证）、腾讯云（API 3.0）、短信宝
 * 验证码的生成、有效期、错误次数与邮箱验证码共用 xi-mail.php 中的存储
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_sms_enabled()
{
    return (bool) _xz('sms_s', false) && _xz('sms_sdk', '');
}

/**
 * 手机号格式：默认只允许中国大陆 11 位手机号
 */
function xi_is_phone($phone)
{
    $phone = trim((string) $phone);
    if (_xz('sms_cn_only', true)) {
        return (bool) preg_match('/^1[3-9]\d{9}$/', $phone);
    }
    return (bool) preg_match('/^\+?\d{6,15}$/', $phone);
}

/**
 * 手机号脱敏：138****5678
 */
function xi_mask_phone($phone)
{
    $phone = (string) $phone;
    return strlen($phone) >= 7 ? substr($phone, 0, 3) . '****' . substr($phone, -4) : $phone;
}

function xi_get_user_phone($user_id)
{
    return (string) get_user_meta($user_id, 'xi_phone', true);
}

/**
 * 按绑定的手机号查用户
 *
 * @return WP_User|false
 */
function xi_get_user_by_phone($phone)
{
    if (!$phone) {
        return false;
    }
    $users = get_users(array(
        'meta_key'   => 'xi_phone',
        'meta_value' => $phone,
        'number'     => 1,
    ));
    return $users ? $users[0] : false;
}

/**
 * 发送验证码短信，频率限制与邮箱验证码一致：同一号码 60 秒一次，同一 IP 每小时 10 次
 *
 * @return true|WP_Error
 */
function xi_send_sms_code($purpose, $phone)
{
    if (!xi_sms_enabled()) {
        return new WP_Error('off', __('短信功能未开启', 'xi'));
    }
    if (!xi_is_phone($phone)) {
        return new WP_Error('phone', __('手机号格式不正确', 'xi'));
    }
    $lock = 'xi_sms_lock_' . md5($phone);
    if (get_transient($lock)) {
        return new WP_Error('frequent', __('发送太频繁，请 60 秒后再试', 'xi'));
    }
    $ip_key   = 'xi_code_ip_' . md5(xi_client_ip());
    $ip_count = (int) get_transient($ip_key);
    if ($ip_count >= 10) {
        return new WP_Error('frequent', __('发送次数过多，请稍后再试', 'xi'));
    }

    $code   = (string) wp_rand(100000, 999999);
    $result = xi_sms_send($phone, $code);
    if (is_wp_error($result)) {
        return $result;
    }

    set_transient(xi_code_key($purpose, $phone), array('hash' => wp_hash($code), 'tries' => 0, 'expires' => time() + 10 * MINUTE_IN_SECONDS), 10 * MINUTE_IN_SECONDS);
    set_transient($lock, 1, 60);
    set_transient($ip_key, $ip_count + 1, HOUR_IN_SECONDS);
    return true;
}

/**
 * 校验邮箱或手机验证码（同一套存储）
 */
function xi_verify_code($purpose, $to, $code, $consume = true)
{
    return xi_verify_email_code($purpose, $to, $code, $consume);
}

/* ---------- 服务商 ---------- */

/**
 * 发送一条验证码短信
 *
 * @param string $sdk 留空使用后台设置的服务商
 * @return true|WP_Error
 */
function xi_sms_send($phone, $code, $sdk = '')
{
    // 测试或二次开发可接管发送
    $pre = apply_filters('xi_sms_pre_send', null, $phone, $code);
    if ($pre !== null) {
        return $pre;
    }
    $sdk = $sdk ? $sdk : _xz('sms_sdk', '');
    switch ($sdk) {
        case 'aliyun':
            return xi_sms_aliyun($phone, $code);
        case 'tencent':
            return xi_sms_tencent($phone, $code);
        case 'smsbao':
            return xi_sms_smsbao($phone, $code);
    }
    return new WP_Error('sdk', __('未选择短信服务商', 'xi'));
}

function xi_sms_option($sdk, $key, $default = '')
{
    $opt = (array) _xz('sms_' . $sdk, array());
    return isset($opt[$key]) && $opt[$key] !== '' ? trim((string) $opt[$key]) : $default;
}

/**
 * 阿里云：短信服务 SendSms，或号码认证服务 SendSmsVerifyCode（个人也可开通，签名和模板由阿里云提供）
 * RPC 风格签名 V1：HMAC-SHA1
 */
function xi_sms_aliyun($phone, $code)
{
    $id     = xi_sms_option('aliyun', 'keyid');
    $secret = xi_sms_option('aliyun', 'keysecret');
    $sign   = xi_sms_option('aliyun', 'sign_name');
    $type   = xi_sms_option('aliyun', 'api_type', 'sms');
    $tpl    = xi_sms_option('aliyun', 'template_code', $type === 'verify' ? '100001' : '');
    if (!$id || !$secret || !$sign || !$tpl) {
        return new WP_Error('config', __('阿里云短信配置不完整', 'xi'));
    }

    $params = array(
        'AccessKeyId'      => $id,
        'Format'           => 'JSON',
        'RegionId'         => 'cn-hangzhou',
        'SignName'         => $sign,
        'SignatureMethod'  => 'HMAC-SHA1',
        'SignatureNonce'   => wp_generate_uuid4(),
        'SignatureVersion' => '1.0',
        'TemplateCode'     => $tpl,
        'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
        'Version'          => '2017-05-25',
    );
    if ($type === 'verify') {
        $host                    = 'dypnsapi.aliyuncs.com';
        $params['Action']        = 'SendSmsVerifyCode';
        $params['PhoneNumber']   = $phone;
        $params['TemplateParam'] = wp_json_encode(array('code' => $code, 'min' => '10'));
    } else {
        $host                    = 'dysmsapi.aliyuncs.com';
        $params['Action']        = 'SendSms';
        $params['PhoneNumbers']  = $phone;
        $params['TemplateParam'] = wp_json_encode(array('code' => $code));
    }
    $params['Signature'] = xi_aliyun_rpc_sign($params, $secret);

    $res = wp_remote_get('https://' . $host . '/?' . xi_aliyun_query($params), array('timeout' => 10));
    if (is_wp_error($res)) {
        return new WP_Error('http', __('无法连接阿里云：', 'xi') . $res->get_error_message());
    }
    $body = json_decode(wp_remote_retrieve_body($res), true);
    if (isset($body['Code']) && $body['Code'] === 'OK') {
        return true;
    }
    $msg = isset($body['Message']) ? $body['Message'] . '（' . $body['Code'] . '）' : __('未知错误', 'xi');
    return new WP_Error('aliyun', __('阿里云短信发送失败：', 'xi') . $msg);
}

function xi_aliyun_encode($str)
{
    return str_replace(array('+', '*', '%7E'), array('%20', '%2A', '~'), rawurlencode((string) $str));
}

function xi_aliyun_query($params)
{
    ksort($params);
    $pairs = array();
    foreach ($params as $k => $v) {
        $pairs[] = xi_aliyun_encode($k) . '=' . xi_aliyun_encode($v);
    }
    return implode('&', $pairs);
}

function xi_aliyun_rpc_sign($params, $secret)
{
    unset($params['Signature']);
    $string = 'GET&%2F&' . xi_aliyun_encode(xi_aliyun_query($params));
    return base64_encode(hash_hmac('sha1', $string, $secret . '&', true));
}

/**
 * 腾讯云短信 API 3.0：TC3-HMAC-SHA256 签名
 * 模板变量可选「验证码」或「验证码 + 有效分钟」
 */
function xi_sms_tencent($phone, $code)
{
    $id     = xi_sms_option('tencent', 'secret_id');
    $key    = xi_sms_option('tencent', 'secret_key');
    $app    = xi_sms_option('tencent', 'sdk_app_id');
    $sign   = xi_sms_option('tencent', 'sign_name');
    $tpl    = xi_sms_option('tencent', 'template_id');
    $region = xi_sms_option('tencent', 'region', 'ap-guangzhou');
    if (!$id || !$key || !$app || !$sign || !$tpl) {
        return new WP_Error('config', __('腾讯云短信配置不完整', 'xi'));
    }

    $vars    = xi_sms_option('tencent', 'params', 'code_min') === 'code' ? array($code) : array($code, '10');
    $payload = wp_json_encode(array(
        'PhoneNumberSet'   => array(strpos($phone, '+') === 0 ? $phone : '+86' . $phone),
        'SmsSdkAppId'      => $app,
        'SignName'         => $sign,
        'TemplateId'       => $tpl,
        'TemplateParamSet' => $vars,
    ));
    $headers = xi_tencent_tc3_headers($id, $key, 'sms', 'sms.tencentcloudapi.com', 'SendSms', '2021-01-11', $region, $payload, time());

    $res = wp_remote_post('https://sms.tencentcloudapi.com/', array('timeout' => 10, 'headers' => $headers, 'body' => $payload));
    if (is_wp_error($res)) {
        return new WP_Error('http', __('无法连接腾讯云：', 'xi') . $res->get_error_message());
    }
    $body = json_decode(wp_remote_retrieve_body($res), true);
    if (!empty($body['Response']['Error'])) {
        return new WP_Error('tencent', __('腾讯云短信发送失败：', 'xi') . $body['Response']['Error']['Message'] . '（' . $body['Response']['Error']['Code'] . '）');
    }
    $status = isset($body['Response']['SendStatusSet'][0]) ? $body['Response']['SendStatusSet'][0] : array();
    if (isset($status['Code']) && $status['Code'] === 'Ok') {
        return true;
    }
    return new WP_Error('tencent', __('腾讯云短信发送失败：', 'xi') . (isset($status['Message']) ? $status['Message'] . '（' . $status['Code'] . '）' : __('未知错误', 'xi')));
}

/**
 * 腾讯云 TC3-HMAC-SHA256 请求头
 */
function xi_tencent_tc3_headers($secret_id, $secret_key, $service, $host, $action, $version, $region, $payload, $timestamp)
{
    $date      = gmdate('Y-m-d', $timestamp);
    $type      = 'application/json; charset=utf-8';
    $canonical = "POST\n/\n\ncontent-type:" . $type . "\nhost:" . $host . "\n\ncontent-type;host\n" . hash('sha256', $payload);
    $scope     = $date . '/' . $service . '/tc3_request';
    $string    = "TC3-HMAC-SHA256\n" . $timestamp . "\n" . $scope . "\n" . hash('sha256', $canonical);

    $secret_date    = hash_hmac('sha256', $date, 'TC3' . $secret_key, true);
    $secret_service = hash_hmac('sha256', $service, $secret_date, true);
    $secret_signing = hash_hmac('sha256', 'tc3_request', $secret_service, true);
    $signature      = hash_hmac('sha256', $string, $secret_signing);

    return array(
        'Authorization'  => 'TC3-HMAC-SHA256 Credential=' . $secret_id . '/' . $scope . ', SignedHeaders=content-type;host, Signature=' . $signature,
        'Content-Type'   => $type,
        'Host'           => $host,
        'X-TC-Action'    => $action,
        'X-TC-Timestamp' => (string) $timestamp,
        'X-TC-Version'   => $version,
        'X-TC-Region'    => $region,
    );
}

/**
 * 短信宝：模板内容需包含 {code}，可用 {time} 表示有效分钟；内容开头需带【签名】
 */
function xi_sms_smsbao($phone, $code)
{
    $user = xi_sms_option('smsbao', 'user');
    $pass = xi_sms_option('smsbao', 'api_key');
    $pass = $pass ? $pass : md5(xi_sms_option('smsbao', 'password'));
    $tpl  = xi_sms_option('smsbao', 'template', '【曦芒的博客】你的验证码是{code}，{time}分钟内有效。');
    if (!$user || !xi_sms_option('smsbao', 'api_key') && !xi_sms_option('smsbao', 'password')) {
        return new WP_Error('config', __('短信宝配置不完整', 'xi'));
    }
    if (strpos($tpl, '{code}') === false) {
        return new WP_Error('config', __('短信宝模板内容缺少 {code}', 'xi'));
    }
    $content = str_replace(array('{code}', '{time}'), array($code, '10'), $tpl);
    $intl    = strpos($phone, '+') === 0;
    $url     = 'https://api.smsbao.com/' . ($intl ? 'wsms' : 'sms') . '?u=' . rawurlencode($user) . '&p=' . rawurlencode($pass)
        . '&m=' . rawurlencode($phone) . '&c=' . rawurlencode($content);

    $res = wp_remote_get($url, array('timeout' => 10));
    if (is_wp_error($res)) {
        return new WP_Error('http', __('无法连接短信宝：', 'xi') . $res->get_error_message());
    }
    $status = trim(wp_remote_retrieve_body($res));
    if ($status === '0') {
        return true;
    }
    $errors = array(
        '-1' => __('参数不全', 'xi'),
        '-2' => __('服务器不支持 curl 或 fsocket', 'xi'),
        '30' => __('密码错误', 'xi'),
        '40' => __('账号不存在', 'xi'),
        '41' => __('余额不足', 'xi'),
        '42' => __('账号已过期', 'xi'),
        '43' => __('IP 地址受限', 'xi'),
        '50' => __('内容含有敏感词', 'xi'),
        '51' => __('手机号码不正确', 'xi'),
    );
    return new WP_Error('smsbao', __('短信宝发送失败：', 'xi') . (isset($errors[$status]) ? $errors[$status] : $status));
}

/* ---------- 后台测试 ---------- */

function xi_ajax_sms_test()
{
    check_ajax_referer('xi_sms_test', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('msg' => __('权限不足', 'xi')));
    }
    $phone = isset($_POST['to']) ? sanitize_text_field(wp_unslash($_POST['to'])) : '';
    if (!xi_is_phone($phone)) {
        wp_send_json_error(array('msg' => __('手机号格式不正确', 'xi')));
    }
    $result = xi_sms_send($phone, '888888');
    if (is_wp_error($result)) {
        wp_send_json_error(array('msg' => $result->get_error_message()));
    }
    wp_send_json_success(array('msg' => sprintf(__('已向 %s 发送测试验证码 888888', 'xi'), $phone)));
}
add_action('wp_ajax_xi_sms_test', 'xi_ajax_sms_test');

/* ---------- 账号设置：绑定手机 ---------- */

function xi_ajax_phone_code()
{
    $user_id = xi_user_ajax_check();
    $phone   = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    if ($phone === xi_get_user_phone($user_id)) {
        xi_user_ajax_result(new WP_Error('same', __('新手机号与当前绑定的相同', 'xi')));
    }
    $owner = xi_get_user_by_phone($phone);
    if ($owner && $owner->ID !== $user_id) {
        xi_user_ajax_result(new WP_Error('used', __('该手机号已被其他账号绑定', 'xi')));
    }
    $sent = xi_send_sms_code('bind', $phone);
    if (is_wp_error($sent)) {
        xi_user_ajax_result($sent);
    }
    wp_send_json(array('ok' => true, 'msg' => __('验证码已发送', 'xi'), 'countdown' => 60));
}
add_action('wp_ajax_xi_phone_code', 'xi_ajax_phone_code');

function xi_ajax_phone_save()
{
    $user_id = xi_user_ajax_check();
    $user    = get_userdata($user_id);
    $phone   = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $code    = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
    $pass    = isset($_POST['pass']) ? (string) wp_unslash($_POST['pass']) : '';

    if (!xi_sms_enabled()) {
        xi_user_ajax_result(new WP_Error('off', __('短信功能未开启', 'xi')));
    }
    if (!xi_is_phone($phone)) {
        xi_user_ajax_result(new WP_Error('phone', __('手机号格式不正确', 'xi')));
    }
    // 已绑定过手机的换绑需要验证当前密码
    if (xi_get_user_phone($user_id) && !xi_check_current_pass($user, $pass)) {
        xi_user_ajax_result(new WP_Error('pass', __('当前密码错误', 'xi')));
    }
    $owner = xi_get_user_by_phone($phone);
    if ($owner && $owner->ID !== $user_id) {
        xi_user_ajax_result(new WP_Error('used', __('该手机号已被其他账号绑定', 'xi')));
    }
    $verify = xi_verify_code('bind', $phone, $code);
    if (is_wp_error($verify)) {
        xi_user_ajax_result($verify);
    }
    $old = xi_get_user_phone($user_id);
    update_user_meta($user_id, 'xi_phone', $phone);
    do_action('xi_user_bind_phone', $user_id, $phone, $old);
    xi_user_ajax_result(__('手机号已绑定', 'xi'));
}
add_action('wp_ajax_xi_phone_save', 'xi_ajax_phone_save');
