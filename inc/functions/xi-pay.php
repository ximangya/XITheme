<?php
/**
 * 在线支付与订单
 * - 通道：易支付（彩虹易支付协议，MD5 签名）、支付宝官方（当面付扫码 / 电脑网站 / 手机网站，RSA2 签名）
 * - 商品：余额充值、积分充值、开通会员；金额一律由服务端计算
 * - 到账：异步通知验签 + 前端轮询主动查单，两条路径都走 xi_order_paid()，订单状态原子更新，重复通知不会重复发放
 */

if (!defined('ABSPATH')) {
    exit;
}

define('XI_ORDER_DB_VER', '1');

function xi_orders_table()
{
    global $wpdb;
    return $wpdb->prefix . 'xi_orders';
}

/**
 * 建表：主题更新或首次使用时执行
 */
function xi_orders_install()
{
    if (get_option('xi_orders_db_ver') === XI_ORDER_DB_VER) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = xi_orders_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_no varchar(32) NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  type varchar(20) NOT NULL DEFAULT '',
  title varchar(200) NOT NULL DEFAULT '',
  amount decimal(10,2) NOT NULL DEFAULT 0.00,
  product text NULL,
  gateway varchar(20) NOT NULL DEFAULT '',
  method varchar(20) NOT NULL DEFAULT '',
  trade_no varchar(64) NOT NULL DEFAULT '',
  status tinyint(4) NOT NULL DEFAULT 0,
  note varchar(255) NOT NULL DEFAULT '',
  ip varchar(64) NOT NULL DEFAULT '',
  created_at datetime NULL,
  paid_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY order_no (order_no),
  KEY user_id (user_id),
  KEY status (status)
) {$charset};");
    update_option('xi_orders_db_ver', XI_ORDER_DB_VER);
}
add_action('admin_init', 'xi_orders_install');

/* ---------- 设置 ---------- */

function xi_pay_enabled()
{
    return (bool) _xz('pay_s', false) && xi_pay_methods();
}

/**
 * 已启用的付款方式 => 通道
 *
 * @return array 例如 array('alipay' => 'alipay_f2f', 'wxpay' => 'epay')
 */
function xi_pay_methods()
{
    $methods = array();
    foreach (array('alipay', 'wxpay', 'qqpay') as $method) {
        $gateway = _xz('pay_method_' . $method, '');
        if ($gateway) {
            $methods[$method] = $gateway;
        }
    }
    return $methods;
}

function xi_pay_method_name($method)
{
    $names = array('alipay' => __('支付宝', 'xi'), 'wxpay' => __('微信支付', 'xi'), 'qqpay' => __('QQ钱包', 'xi'), 'manual' => __('后台补单', 'xi'));
    return isset($names[$method]) ? $names[$method] : $method;
}

function xi_pay_option($group, $key, $default = '')
{
    $opt = (array) _xz('pay_' . $group, array());
    return isset($opt[$key]) && $opt[$key] !== '' ? trim((string) $opt[$key]) : $default;
}

function xi_money($amount)
{
    return number_format((float) $amount, 2, '.', '');
}

/**
 * 充值档位，例如 "10,30,50,100"
 */
function xi_pay_presets($key, $default)
{
    $list = array_filter(array_map('floatval', preg_split('/[,，\s]+/u', (string) _xz($key, $default))));
    return array_values(array_unique($list));
}

/* ---------- 订单 ---------- */

function xi_order_no()
{
    return wp_date('YmdHis') . wp_rand(100000, 999999);
}

/**
 * @return object|null
 */
function xi_get_order($order_no)
{
    global $wpdb;
    $order = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . xi_orders_table() . ' WHERE order_no = %s', $order_no));
    if ($order) {
        $order->product = $order->product ? json_decode($order->product, true) : array();
    }
    return $order;
}

/**
 * 根据商品计算订单，金额只信任服务端
 *
 * @return array|WP_Error title, amount, product
 */
function xi_pay_build_product($user_id, $type, $args)
{
    // 扩展商品类型（商城订单等）：返回 array 或 WP_Error 即接管
    $custom = apply_filters('xi_pay_build_product', null, $user_id, $type, $args);
    if ($custom !== null) {
        return $custom;
    }
    switch ($type) {
        case 'recharge':
            if (!xi_balance_enabled() || !_xz('pay_recharge_s', true)) {
                return new WP_Error('off', __('暂未开放余额充值', 'xi'));
            }
            $amount = round((float) $args['amount'], 2);
            $min    = max(0.01, (float) _xz('pay_recharge_min', 1));
            $max    = max($min, (float) _xz('pay_recharge_max', 5000));
            if ($amount < $min || $amount > $max) {
                return new WP_Error('amount', sprintf(__('充值金额需在 %1$s - %2$s 元之间', 'xi'), xi_money($min), xi_money($max)));
            }
            return array(
                'title'   => sprintf(__('余额充值 %s 元', 'xi'), xi_money($amount)),
                'amount'  => $amount,
                'product' => array('balance' => $amount),
            );

        case 'points':
            if (!xi_points_enabled() || !_xz('pay_points_s', false)) {
                return new WP_Error('off', __('暂未开放积分充值', 'xi'));
            }
            $amount = round((float) $args['amount'], 2);
            $min    = max(0.01, (float) _xz('pay_recharge_min', 1));
            $max    = max($min, (float) _xz('pay_recharge_max', 5000));
            $points = (int) floor($amount * max(1, (int) _xz('pay_points_rate', 100)));
            if ($amount < $min || $amount > $max || $points < 1) {
                return new WP_Error('amount', sprintf(__('充值金额需在 %1$s - %2$s 元之间', 'xi'), xi_money($min), xi_money($max)));
            }
            return array(
                'title'   => sprintf(__('购买 %1$d %2$s', 'xi'), $points, xi_points_name()),
                'amount'  => $amount,
                'product' => array('points' => $points),
            );

        case 'vip':
            if (!xi_vip_enabled() || !_xz('pay_vip_s', true)) {
                return new WP_Error('off', __('暂未开放在线开通会员', 'xi'));
            }
            $level  = (int) $args['level'];
            $index  = (int) $args['plan'];
            $levels = xi_vip_levels();
            if (!isset($levels[$level]['plans'][$index])) {
                return new WP_Error('plan', __('套餐不存在', 'xi'));
            }
            $plan    = $levels[$level]['plans'][$index];
            $amount  = round((float) $plan['price'], 2);
            $current = xi_get_vip_level($user_id);
            if ($amount <= 0) {
                return new WP_Error('plan', __('该套餐不支持在线支付', 'xi'));
            }
            if ($current > $level) {
                return new WP_Error('lower', __('你已是更高等级会员', 'xi'));
            }
            if ($current === $level && xi_get_vip_exp($user_id) === 0) {
                return new WP_Error('forever', __('你已是永久会员', 'xi'));
            }
            return array(
                'title'   => sprintf(__('开通%1$s · %2$s', 'xi'), $levels[$level]['name'], $plan['name']),
                'amount'  => $amount,
                'product' => array('level' => $level, 'days' => (int) $plan['days']),
            );
    }
    return new WP_Error('type', __('商品类型错误', 'xi'));
}

/**
 * 创建订单
 *
 * @return object|WP_Error
 */
function xi_order_create($user_id, $type, $args, $method)
{
    $methods = xi_pay_methods();
    if (!xi_pay_enabled() || !isset($methods[$method])) {
        return new WP_Error('method', __('该付款方式暂不可用', 'xi'));
    }
    $item = xi_pay_build_product($user_id, $type, $args);
    if (is_wp_error($item)) {
        return $item;
    }
    // 同一用户 10 分钟内最多创建 20 个订单
    $key   = 'xi_order_rate_' . $user_id;
    $count = (int) get_transient($key);
    if ($count >= 20) {
        return new WP_Error('frequent', __('下单太频繁，请稍后再试', 'xi'));
    }
    set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);

    xi_orders_install();
    global $wpdb;
    $order_no = xi_order_no();
    $ok       = $wpdb->insert(xi_orders_table(), array(
        'order_no'   => $order_no,
        'user_id'    => $user_id,
        'type'       => $type,
        'title'      => $item['title'],
        'amount'     => xi_money($item['amount']),
        'product'    => wp_json_encode($item['product']),
        'gateway'    => $methods[$method],
        'method'     => $method,
        'status'     => 0,
        'ip'         => xi_client_ip(),
        'created_at' => current_time('mysql'),
    ));
    if (!$ok) {
        return new WP_Error('db', __('订单创建失败，请稍后再试', 'xi'));
    }
    do_action('xi_order_created', $order_no);
    return xi_get_order($order_no);
}

/**
 * 订单支付成功：原子更新状态后发放商品，只有第一次调用会发放
 *
 * @param float|null $paid_amount 通道返回的实付金额，用于核对
 * @return bool 本次是否完成了到账
 */
function xi_order_paid($order_no, $trade_no, $paid_amount = null, $note = '')
{
    $order = xi_get_order($order_no);
    if (!$order || (int) $order->status === 1) {
        return false;
    }
    if ($paid_amount !== null && xi_money($paid_amount) !== xi_money($order->amount)) {
        global $wpdb;
        $wpdb->update(xi_orders_table(), array('note' => sprintf(__('金额不一致：应付 %1$s 实付 %2$s', 'xi'), $order->amount, xi_money($paid_amount))), array('order_no' => $order_no));
        return false;
    }

    global $wpdb;
    $updated = $wpdb->query($wpdb->prepare(
        'UPDATE ' . xi_orders_table() . ' SET status = 1, trade_no = %s, paid_at = %s, note = %s WHERE order_no = %s AND status = 0',
        substr((string) $trade_no, 0, 64),
        current_time('mysql'),
        substr((string) $note, 0, 255),
        $order_no
    ));
    if ((int) $updated !== 1) {
        return false;
    }

    $p   = $order->product;
    $uid = (int) $order->user_id;
    switch ($order->type) {
        case 'recharge':
            xi_change_balance($uid, (float) $p['balance'], sprintf(__('在线充值 · %s', 'xi'), $order_no));
            break;
        case 'points':
            xi_change_points($uid, (int) $p['points'], sprintf(__('在线购买 · %s', 'xi'), $order_no));
            break;
        case 'vip':
            xi_set_vip($uid, (int) $p['level'], (int) $p['days']);
            break;
    }
    do_action('xi_order_paid', xi_get_order($order_no));
    return true;
}

/* ---------- 发起支付 ---------- */

function xi_pay_notify_url($gateway)
{
    return add_query_arg(array('action' => 'xi_pay_notify', 'gateway' => $gateway), admin_url('admin-ajax.php'));
}

function xi_pay_return_url($order_no)
{
    // 商城订单付款后回到商城订单页并定位到该订单
    $order = xi_get_order($order_no);
    if ($order && $order->type === 'shop' && !empty($order->product['shop_no'])) {
        return add_query_arg(array('xi_pay_return' => $order_no, 'order' => $order->product['shop_no']), xi_uc_url('shop'));
    }
    return add_query_arg('xi_pay_return', $order_no, xi_uc_url('orders'));
}

/**
 * @return array|WP_Error type=qrcode|redirect，qr / url
 */
function xi_pay_initiate($order)
{
    switch ($order->gateway) {
        case 'epay':
            return xi_epay_initiate($order);
        case 'alipay_f2f':
            return xi_alipay_precreate($order);
        case 'alipay_page':
            return xi_alipay_page($order);
    }
    return new WP_Error('gateway', __('支付通道配置错误', 'xi'));
}

/**
 * 主动查单，查到已支付则完成到账
 */
function xi_pay_query($order)
{
    if ((int) $order->status === 1) {
        return true;
    }
    switch ($order->gateway) {
        case 'epay':
            $res = xi_epay_query($order);
            break;
        case 'alipay_f2f':
        case 'alipay_page':
            $res = xi_alipay_query($order);
            break;
        default:
            return false;
    }
    if (is_array($res) && !empty($res['paid'])) {
        xi_order_paid($order->order_no, $res['trade_no'], $res['amount'], __('查单确认', 'xi'));
        return true;
    }
    return false;
}

/* ---------- 易支付 ---------- */

function xi_epay_api()
{
    return trailingslashit(xi_pay_option('epay', 'api_url'));
}

/**
 * 易支付 MD5 签名：去掉 sign、sign_type 和空值，按键名排序拼接后加商户密钥
 */
function xi_epay_sign($params, $key)
{
    unset($params['sign'], $params['sign_type']);
    $params = array_filter($params, function ($v) {
        return $v !== '' && $v !== null;
    });
    ksort($params);
    $pairs = array();
    foreach ($params as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    return md5(implode('&', $pairs) . $key);
}

function xi_epay_initiate($order)
{
    $api = xi_epay_api();
    $pid = xi_pay_option('epay', 'pid');
    $key = xi_pay_option('epay', 'key');
    if (!$api || $api === '/' || !$pid || !$key) {
        return new WP_Error('config', __('易支付配置不完整', 'xi'));
    }
    $params = array(
        'pid'          => $pid,
        'type'         => $order->method,
        'out_trade_no' => $order->order_no,
        'notify_url'   => xi_pay_notify_url('epay'),
        'return_url'   => xi_pay_return_url($order->order_no),
        'name'         => $order->title,
        'money'        => xi_money($order->amount),
        'sitename'     => get_bloginfo('name'),
    );

    if (xi_pay_option('epay', 'mode', 'jump') === 'api') {
        $params['clientip']  = xi_client_ip();
        $params['device']    = wp_is_mobile() ? 'mobile' : 'pc';
        $params['sign']      = xi_epay_sign($params, $key);
        $params['sign_type'] = 'MD5';
        $res = wp_remote_post($api . 'mapi.php', array('timeout' => 15, 'body' => $params));
        if (is_wp_error($res)) {
            return new WP_Error('http', __('无法连接支付接口：', 'xi') . $res->get_error_message());
        }
        $body = json_decode(wp_remote_retrieve_body($res), true);
        if (!is_array($body) || (int) $body['code'] !== 1) {
            return new WP_Error('epay', __('支付接口返回错误：', 'xi') . (isset($body['msg']) ? $body['msg'] : __('未知错误', 'xi')));
        }
        if (!empty($body['qrcode'])) {
            return array('type' => 'qrcode', 'qr' => $body['qrcode']);
        }
        $url = !empty($body['payurl']) ? $body['payurl'] : (isset($body['urlscheme']) ? $body['urlscheme'] : '');
        return $url ? array('type' => 'redirect', 'url' => $url) : new WP_Error('epay', __('支付接口未返回支付地址', 'xi'));
    }

    $params['sign']      = xi_epay_sign($params, $key);
    $params['sign_type'] = 'MD5';
    return array('type' => 'redirect', 'url' => $api . 'submit.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

/**
 * 验证易支付通知参数
 *
 * @return array|false 订单号、交易号、金额
 */
function xi_epay_verify($data)
{
    $key = xi_pay_option('epay', 'key');
    if (!$key || empty($data['sign']) || empty($data['out_trade_no'])) {
        return false;
    }
    if (!isset($data['pid'], $data['money']) || (string) $data['pid'] !== xi_pay_option('epay', 'pid')) {
        return false;
    }
    if (!hash_equals(xi_epay_sign($data, $key), strtolower((string) $data['sign']))) {
        return false;
    }
    if (!isset($data['trade_status']) || $data['trade_status'] !== 'TRADE_SUCCESS') {
        return false;
    }
    return array('order_no' => $data['out_trade_no'], 'trade_no' => isset($data['trade_no']) ? $data['trade_no'] : '', 'amount' => $data['money']);
}

function xi_epay_query($order)
{
    $url = add_query_arg(array(
        'act'          => 'order',
        'pid'          => xi_pay_option('epay', 'pid'),
        'key'          => xi_pay_option('epay', 'key'),
        'out_trade_no' => $order->order_no,
    ), xi_epay_api() . 'api.php');
    $res = wp_remote_get($url, array('timeout' => 10));
    if (is_wp_error($res)) {
        return false;
    }
    $body = json_decode(wp_remote_retrieve_body($res), true);
    if (is_array($body) && isset($body['code'], $body['status'], $body['money']) && (int) $body['code'] === 1 && (int) $body['status'] === 1) {
        return array('paid' => true, 'trade_no' => isset($body['trade_no']) ? $body['trade_no'] : '', 'amount' => $body['money']);
    }
    return false;
}

/* ---------- 支付宝官方 ---------- */

function xi_alipay_gateway()
{
    return xi_pay_option('alipay', 'sandbox') ? 'https://openapi-sandbox.dl.alipaydev.com/gateway.do' : 'https://openapi.alipay.com/gateway.do';
}

/**
 * 把后台填写的密钥补全为 PEM 格式
 */
function xi_pem($key, $type)
{
    $key = trim((string) $key);
    if (strpos($key, '-----BEGIN') !== false) {
        return $key;
    }
    $body = chunk_split(preg_replace('/\s+/', '', $key), 64, "\n");
    return "-----BEGIN {$type}-----\n{$body}-----END {$type}-----";
}

function xi_alipay_private_key()
{
    $raw = xi_pay_option('alipay', 'private_key');
    foreach (array('RSA PRIVATE KEY', 'PRIVATE KEY') as $type) {
        $key = openssl_pkey_get_private(xi_pem($raw, $type));
        if ($key) {
            return $key;
        }
    }
    return false;
}

function xi_alipay_public_key()
{
    return openssl_pkey_get_public(xi_pem(xi_pay_option('alipay', 'public_key'), 'PUBLIC KEY'));
}

/**
 * 待签名字符串：去掉 sign、空值（通知验签时还要去掉 sign_type），按键名排序，原值拼接
 */
function xi_alipay_sign_content($params, $drop_sign_type = false)
{
    unset($params['sign']);
    if ($drop_sign_type) {
        unset($params['sign_type']);
    }
    $params = array_filter($params, function ($v) {
        return $v !== '' && $v !== null;
    });
    ksort($params);
    $pairs = array();
    foreach ($params as $k => $v) {
        $pairs[] = $k . '=' . $v;
    }
    return implode('&', $pairs);
}

/**
 * 组装并签名请求参数
 *
 * @return array|WP_Error
 */
function xi_alipay_params($method, $biz, $extra = array())
{
    $app_id = xi_pay_option('alipay', 'app_id');
    $key    = xi_alipay_private_key();
    if (!$app_id || !$key) {
        return new WP_Error('config', __('支付宝 AppID 或应用私钥配置有误', 'xi'));
    }
    $now    = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
    $params = array_merge(array(
        'app_id'      => $app_id,
        'method'      => $method,
        'format'      => 'JSON',
        'charset'     => 'utf-8',
        'sign_type'   => 'RSA2',
        'timestamp'   => $now->format('Y-m-d H:i:s'),
        'version'     => '1.0',
        'biz_content' => wp_json_encode($biz, JSON_UNESCAPED_UNICODE),
    ), $extra);
    openssl_sign(xi_alipay_sign_content($params), $sign, $key, OPENSSL_ALGO_SHA256);
    $params['sign'] = base64_encode($sign);
    return $params;
}

/**
 * 调用支付宝接口并校验响应签名
 *
 * @return array|WP_Error 响应节点
 */
function xi_alipay_request($method, $biz)
{
    $params = xi_alipay_params($method, $biz, array('notify_url' => xi_pay_notify_url('alipay')));
    if (is_wp_error($params)) {
        return $params;
    }
    $res = wp_remote_post(xi_alipay_gateway() . '?charset=utf-8', array('timeout' => 15, 'body' => $params));
    if (is_wp_error($res)) {
        return new WP_Error('http', __('无法连接支付宝：', 'xi') . $res->get_error_message());
    }
    $body = wp_remote_retrieve_body($res);
    $node = str_replace('.', '_', $method) . '_response';
    $raw  = xi_json_node_raw($body, $node);
    $data = json_decode($body, true);
    if ($raw === null || !isset($data[$node])) {
        return new WP_Error('alipay', __('支付宝返回数据异常', 'xi'));
    }
    // 业务成功时校验响应签名，防止伪造
    if (isset($data[$node]['code']) && $data[$node]['code'] === '10000') {
        $pub = xi_alipay_public_key();
        if (!$pub || empty($data['sign']) || openssl_verify($raw, base64_decode($data['sign']), $pub, OPENSSL_ALGO_SHA256) !== 1) {
            return new WP_Error('alipay', __('支付宝响应验签失败，请检查支付宝公钥', 'xi'));
        }
    }
    return $data[$node];
}

/**
 * 取出 JSON 中某个节点的原始字符串（验签需要原文）
 */
function xi_json_node_raw($json, $node)
{
    $pos = strpos($json, '"' . $node . '"');
    if ($pos === false) {
        return null;
    }
    $start = strpos($json, '{', $pos);
    if ($start === false) {
        return null;
    }
    $depth  = 0;
    $in_str = false;
    $len    = strlen($json);
    for ($i = $start; $i < $len; $i++) {
        $c = $json[$i];
        if ($in_str) {
            if ($c === '\\') {
                $i++;
            } elseif ($c === '"') {
                $in_str = false;
            }
            continue;
        }
        if ($c === '"') {
            $in_str = true;
        } elseif ($c === '{') {
            $depth++;
        } elseif ($c === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($json, $start, $i - $start + 1);
            }
        }
    }
    return null;
}

/**
 * 当面付：生成收款二维码
 */
function xi_alipay_precreate($order)
{
    $res = xi_alipay_request('alipay.trade.precreate', array(
        'out_trade_no'    => $order->order_no,
        'total_amount'    => xi_money($order->amount),
        'subject'         => $order->title,
        'timeout_express' => '30m',
    ));
    if (is_wp_error($res)) {
        return $res;
    }
    if ($res['code'] !== '10000' || empty($res['qr_code'])) {
        return new WP_Error('alipay', __('支付宝下单失败：', 'xi') . (isset($res['sub_msg']) ? $res['sub_msg'] : $res['msg']));
    }
    return array('type' => 'qrcode', 'qr' => $res['qr_code']);
}

/**
 * 电脑网站 / 手机网站支付：跳转到支付宝收银台
 */
function xi_alipay_page($order)
{
    $mobile = wp_is_mobile();
    $biz    = array(
        'out_trade_no' => $order->order_no,
        'total_amount' => xi_money($order->amount),
        'subject'      => $order->title,
        'product_code' => $mobile ? 'QUICK_WAP_WAY' : 'FAST_INSTANT_TRADE_PAY',
    );
    if ($mobile) {
        $biz['quit_url'] = xi_uc_url('orders');
    }
    $params = xi_alipay_params($mobile ? 'alipay.trade.wap.pay' : 'alipay.trade.page.pay', $biz, array(
        'notify_url' => xi_pay_notify_url('alipay'),
        'return_url' => xi_pay_return_url($order->order_no),
    ));
    if (is_wp_error($params)) {
        return $params;
    }
    return array('type' => 'redirect', 'url' => xi_alipay_gateway() . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
}

function xi_alipay_query($order)
{
    $res = xi_alipay_request('alipay.trade.query', array('out_trade_no' => $order->order_no));
    if (is_wp_error($res) || $res['code'] !== '10000') {
        return false;
    }
    if (in_array($res['trade_status'], array('TRADE_SUCCESS', 'TRADE_FINISHED'), true)) {
        return array('paid' => true, 'trade_no' => $res['trade_no'], 'amount' => $res['total_amount']);
    }
    return false;
}

/**
 * 验证支付宝异步通知
 *
 * @return array|false
 */
function xi_alipay_verify($data)
{
    $pub = xi_alipay_public_key();
    if (!$pub || empty($data['sign']) || empty($data['out_trade_no'])) {
        return false;
    }
    if (openssl_verify(xi_alipay_sign_content($data, true), base64_decode($data['sign']), $pub, OPENSSL_ALGO_SHA256) !== 1) {
        return false;
    }
    if (!isset($data['app_id'], $data['trade_status'], $data['total_amount']) || (string) $data['app_id'] !== xi_pay_option('alipay', 'app_id')) {
        return false;
    }
    if (!in_array($data['trade_status'], array('TRADE_SUCCESS', 'TRADE_FINISHED'), true)) {
        return false;
    }
    return array('order_no' => $data['out_trade_no'], 'trade_no' => isset($data['trade_no']) ? $data['trade_no'] : '', 'amount' => $data['total_amount']);
}

/* ---------- 异步通知 ---------- */

function xi_ajax_pay_notify()
{
    $gateway = isset($_GET['gateway']) ? sanitize_key($_GET['gateway']) : '';
    // 易支付多为 GET 通知，支付宝为 POST
    $data = wp_unslash(array_merge($_GET, $_POST));
    unset($data['action'], $data['gateway']);

    $result = $gateway === 'alipay' ? xi_alipay_verify($data) : ($gateway === 'epay' ? xi_epay_verify($data) : false);
    if (!$result) {
        echo 'fail';
        exit;
    }
    $order = xi_get_order($result['order_no']);
    if (!$order) {
        echo 'fail';
        exit;
    }
    if ((int) $order->status !== 1) {
        xi_order_paid($order->order_no, $result['trade_no'], $result['amount'], __('异步通知', 'xi'));
        $order = xi_get_order($result['order_no']);
    }
    echo (int) $order->status === 1 ? 'success' : 'fail';
    exit;
}
add_action('wp_ajax_xi_pay_notify', 'xi_ajax_pay_notify');
add_action('wp_ajax_nopriv_xi_pay_notify', 'xi_ajax_pay_notify');

/* ---------- 前台 Ajax ---------- */

function xi_ajax_pay_create()
{
    $user_id = xi_user_ajax_check();
    $type    = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
    $method  = isset($_POST['method']) ? sanitize_key($_POST['method']) : '';
    $args    = array(
        'amount' => isset($_POST['amount']) ? (float) $_POST['amount'] : 0,
        'level'  => isset($_POST['level']) ? (int) $_POST['level'] : 0,
        'plan'   => isset($_POST['plan']) ? (int) $_POST['plan'] : -1,
    );
    if ($type === 'shop') {
        // 商城订单走 xi_shop_pay 接口，不允许在这里直接创建
        xi_user_ajax_result(new WP_Error('type', __('商品类型错误', 'xi')));
    }
    if ($type === 'vip' && !xi_user_can('vip_buy', $user_id)) {
        xi_user_ajax_result(xi_auth_error('vip_buy'));
    }
    $order = xi_order_create($user_id, $type, $args, $method);
    if (is_wp_error($order)) {
        xi_user_ajax_result($order);
    }
    $pay = xi_pay_initiate($order);
    if (is_wp_error($pay)) {
        global $wpdb;
        $wpdb->update(xi_orders_table(), array('status' => 2, 'note' => substr($pay->get_error_message(), 0, 255)), array('order_no' => $order->order_no));
        xi_user_ajax_result($pay);
    }
    wp_send_json(array_merge($pay, array(
        'ok'       => true,
        'order_no' => $order->order_no,
        'amount'   => xi_money($order->amount),
        'title'    => $order->title,
        'method'   => $order->method,
    )));
}
add_action('wp_ajax_xi_pay_create', 'xi_ajax_pay_create');

/**
 * 前端轮询：订单创建 5 秒后才主动查单，避免频繁请求通道
 */
function xi_ajax_pay_check()
{
    $user_id = xi_user_ajax_check();
    $order   = xi_get_order(isset($_POST['order_no']) ? sanitize_text_field(wp_unslash($_POST['order_no'])) : '');
    if (!$order || (int) $order->user_id !== $user_id) {
        wp_send_json(array('ok' => false, 'msg' => __('订单不存在', 'xi')));
    }
    $paid = (int) $order->status === 1;
    if (!$paid && (int) $order->status === 0 && time() - strtotime($order->created_at . ' ' . wp_timezone_string()) > 5) {
        $last = 'xi_pay_q_' . $order->order_no;
        if (!get_transient($last)) {
            set_transient($last, 1, 3);
            $paid = xi_pay_query($order);
        }
    }
    wp_send_json(array('ok' => true, 'paid' => $paid, 'msg' => $paid ? __('支付成功', 'xi') : ''));
}
add_action('wp_ajax_xi_pay_check', 'xi_ajax_pay_check');

/**
 * 支付完成跳回网站时立即查一次单
 */
function xi_pay_return_check()
{
    if (empty($_GET['xi_pay_return']) || !is_user_logged_in()) {
        return;
    }
    $order = xi_get_order(sanitize_text_field(wp_unslash($_GET['xi_pay_return'])));
    if ($order && (int) $order->user_id === get_current_user_id() && (int) $order->status === 0) {
        xi_pay_query($order);
    }
}
add_action('template_redirect', 'xi_pay_return_check');

/**
 * 用户的订单
 */
function xi_user_orders($user_id, $limit = 20)
{
    global $wpdb;
    if (get_option('xi_orders_db_ver') !== XI_ORDER_DB_VER) {
        return array();
    }
    return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . xi_orders_table() . ' WHERE user_id = %d ORDER BY id DESC LIMIT %d', $user_id, $limit));
}

function xi_order_status_text($order)
{
    $status = (int) $order->status;
    if ($status === 1) {
        return array('paid', __('已支付', 'xi'));
    }
    if ($status === 2 || strtotime($order->created_at . ' ' . wp_timezone_string()) < time() - 2 * HOUR_IN_SECONDS) {
        return array('closed', __('已关闭', 'xi'));
    }
    return array('pending', __('待支付', 'xi'));
}

/* ---------- 收银台 ---------- */

/**
 * 收银台出现的页面：用户中心、商城结算页
 */
function xi_pay_page()
{
    return xi_is_user_center() || (function_exists('xi_shop_current_page') && xi_shop_current_page() === 'checkout');
}

function xi_pay_enqueue()
{
    if (!is_user_logged_in() || !xi_pay_enabled() || !xi_pay_page()) {
        return;
    }
    $uri = get_template_directory_uri();
    wp_enqueue_script('xi-qrcode', $uri . '/assets/vendor/qrcode/qrcode.js', array(), '1.4.4', true);
    wp_enqueue_script('xi-pay', $uri . '/assets/js/pay.js', array('xi-main', 'xi-qrcode'), XI_VERSION, true);
    $methods = array();
    foreach (xi_pay_methods() as $method => $gateway) {
        $methods[] = array('id' => $method, 'name' => xi_pay_method_name($method));
    }
    wp_localize_script('xi-pay', 'xiPay', array(
        'methods'   => $methods,
        'pointName' => xi_points_name(),
    ));
}
add_action('wp_enqueue_scripts', 'xi_pay_enqueue', 30);

function xi_pay_modal()
{
    if (!is_user_logged_in() || !xi_pay_enabled() || !xi_pay_page()) {
        return;
    }
    ?>
    <div class="xi-modal" id="xi-pay" aria-hidden="true">
        <div class="xi-modal-mask" data-pay-close></div>
        <div class="xi-modal-box xi-glass xi-pay-box" role="dialog" aria-modal="true">
            <button type="button" class="xi-btn-icon xi-modal-close" data-pay-close aria-label="<?php esc_attr_e('关闭', 'xi'); ?>"><?php echo xi_icon('close'); ?></button>
            <div class="xi-pay-step" data-step="choose">
                <h3 class="xi-modal-title" data-pay-title></h3>
                <div class="xi-pay-amount"><small>¥</small><b data-pay-amount></b></div>
                <div class="xi-pay-methods" role="radiogroup"></div>
                <button type="button" class="xi-btn xi-btn-accent xi-pay-submit"><?php esc_html_e('立即支付', 'xi'); ?></button>
                <?php if ($tip = _xz('pay_tip', '')) : ?>
                    <p class="xi-modal-tip"><?php echo esc_html($tip); ?></p>
                <?php endif; ?>
            </div>
            <div class="xi-pay-step" data-step="qrcode" hidden>
                <h3 class="xi-modal-title" data-pay-scan></h3>
                <div class="xi-pay-amount"><small>¥</small><b data-pay-amount></b></div>
                <div class="xi-pay-qr"></div>
                <p class="xi-pay-status"><?php esc_html_e('等待支付…支付完成后会自动到账', 'xi'); ?></p>
                <button type="button" class="xi-btn" data-pay-done><?php esc_html_e('我已完成支付', 'xi'); ?></button>
            </div>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'xi_pay_modal', 6);

/* ---------- 后台：测试连接 ---------- */

function xi_ajax_pay_test()
{
    check_ajax_referer('xi_pay_test', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('msg' => __('权限不足', 'xi')));
    }
    $lines = array();
    if (in_array('epay', xi_pay_methods(), true)) {
        $url = add_query_arg(array('act' => 'query', 'pid' => xi_pay_option('epay', 'pid'), 'key' => xi_pay_option('epay', 'key')), xi_epay_api() . 'api.php');
        $res = wp_remote_get($url, array('timeout' => 10));
        $body = is_wp_error($res) ? null : json_decode(wp_remote_retrieve_body($res), true);
        $lines[] = is_array($body) && (int) $body['code'] === 1
            ? __('易支付：连接正常，商户信息校验通过', 'xi')
            : __('易支付：连接失败，', 'xi') . (is_wp_error($res) ? $res->get_error_message() : (isset($body['msg']) ? $body['msg'] : __('请检查接口地址、商户 ID 与密钥', 'xi')));
    }
    if (array_intersect(array('alipay_f2f', 'alipay_page'), xi_pay_methods())) {
        $ok_pri  = (bool) xi_alipay_private_key();
        $ok_pub  = (bool) xi_alipay_public_key();
        $lines[] = sprintf(__('支付宝：应用私钥%1$s，支付宝公钥%2$s', 'xi'), $ok_pri ? __('格式正确', 'xi') : __('无法解析', 'xi'), $ok_pub ? __('格式正确', 'xi') : __('无法解析', 'xi'));
        if ($ok_pri && $ok_pub) {
            $res     = xi_alipay_request('alipay.trade.query', array('out_trade_no' => 'XI_TEST_' . time()));
            $lines[] = is_wp_error($res) ? $res->get_error_message() : sprintf(__('支付宝接口已连通（查询测试订单返回：%s）', 'xi'), isset($res['sub_msg']) ? $res['sub_msg'] : $res['msg']);
        }
    }
    if (!$lines) {
        wp_send_json_error(array('msg' => __('请先选择付款方式对应的通道并保存', 'xi')));
    }
    wp_send_json_success(array('msg' => implode('；', $lines)));
}
add_action('wp_ajax_xi_pay_test', 'xi_ajax_pay_test');

/* ---------- 后台：订单管理 ---------- */

function xi_orders_admin_menu()
{
    add_users_page(__('订单管理', 'xi'), __('订单管理', 'xi'), 'manage_options', 'xi_orders', 'xi_orders_admin_page');
}
add_action('admin_menu', 'xi_orders_admin_menu');

function xi_orders_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    xi_orders_install();
    global $wpdb;
    $table = xi_orders_table();

    if (!empty($_POST['xi_order_action']) && !empty($_POST['order_no']) && check_admin_referer('xi_orders')) {
        $no = sanitize_text_field(wp_unslash($_POST['order_no']));
        if ($_POST['xi_order_action'] === 'paid') {
            $done = xi_order_paid($no, 'manual-' . get_current_user_id(), null, sprintf(__('管理员 %s 补单', 'xi'), wp_get_current_user()->user_login));
            echo '<div class="notice notice-' . ($done ? 'success' : 'warning') . '"><p>' . esc_html($done ? __('已补单，商品已发放给用户', 'xi') : __('订单已是支付状态或不存在', 'xi')) . '</p></div>';
        } elseif ($_POST['xi_order_action'] === 'close') {
            $wpdb->update($table, array('status' => 2), array('order_no' => $no, 'status' => 0));
            echo '<div class="notice notice-success"><p>' . esc_html__('订单已关闭', 'xi') . '</p></div>';
        }
    }

    $status = isset($_GET['status']) && $_GET['status'] !== '' ? (int) $_GET['status'] : null;
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $paged  = max(1, isset($_GET['paged']) ? (int) $_GET['paged'] : 1);
    $per    = 20;

    $where = array('1=1');
    $args  = array();
    if ($status !== null) {
        $where[] = 'status = %d';
        $args[]  = $status;
    }
    if ($search) {
        $where[] = '(order_no LIKE %s OR trade_no LIKE %s OR title LIKE %s)';
        $like    = '%' . $wpdb->esc_like($search) . '%';
        array_push($args, $like, $like, $like);
    }
    $sql_where = implode(' AND ', $where);
    $total     = (int) $wpdb->get_var($args ? $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$sql_where}", $args) : "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}");
    $rows      = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge($args, array($per, ($paged - 1) * $per))));
    $income    = (float) $wpdb->get_var("SELECT SUM(amount) FROM {$table} WHERE status = 1");
    $today     = (float) $wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$table} WHERE status = 1 AND paid_at >= %s", current_time('Y-m-d') . ' 00:00:00'));
    $base      = admin_url('users.php?page=xi_orders');
    $colors    = array('paid' => '#0f9d58', 'pending' => '#e2a33b', 'closed' => '#999');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('订单管理', 'xi'); ?></h1>
        <p><?php printf(esc_html__('累计收入 ¥%1$s，今日收入 ¥%2$s', 'xi'), esc_html(xi_money($income)), esc_html(xi_money($today))); ?></p>
        <ul class="subsubsub">
            <?php
            $filters = array('' => __('全部', 'xi'), '0' => __('待支付', 'xi'), '1' => __('已支付', 'xi'), '2' => __('已关闭', 'xi'));
            $links   = array();
            foreach ($filters as $key => $label) {
                $current = ($key === '' && $status === null) || ($key !== '' && $status === (int) $key);
                $links[] = '<li><a href="' . esc_url($key === '' ? $base : add_query_arg('status', $key, $base)) . '"' . ($current ? ' class="current"' : '') . '>' . esc_html($label) . '</a></li>';
            }
            echo implode(' | ', $links);
            ?>
        </ul>
        <form method="get" style="float:right;margin:8px 0">
            <input type="hidden" name="page" value="xi_orders">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('订单号 / 交易号 / 商品', 'xi'); ?>">
            <?php submit_button(__('搜索', 'xi'), '', '', false); ?>
        </form>
        <table class="widefat striped" style="clear:both">
            <thead><tr>
                <th><?php esc_html_e('订单号', 'xi'); ?></th><th><?php esc_html_e('用户', 'xi'); ?></th><th><?php esc_html_e('商品', 'xi'); ?></th>
                <th><?php esc_html_e('金额', 'xi'); ?></th><th><?php esc_html_e('方式', 'xi'); ?></th><th><?php esc_html_e('状态', 'xi'); ?></th>
                <th><?php esc_html_e('时间', 'xi'); ?></th><th></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows) : ?>
                <tr><td colspan="8"><?php esc_html_e('暂无订单', 'xi'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row) :
                $u  = get_userdata($row->user_id);
                $st = xi_order_status_text($row);
                ?>
                <tr>
                    <td><code><?php echo esc_html($row->order_no); ?></code><?php if ($row->trade_no) : ?><br><small><?php echo esc_html($row->trade_no); ?></small><?php endif; ?></td>
                    <td><?php echo $u ? '<a href="' . esc_url(get_edit_user_link($u->ID)) . '">' . esc_html($u->display_name) . '</a>' : '#' . (int) $row->user_id; ?></td>
                    <td><?php echo esc_html($row->title); ?><?php if ($row->note) : ?><br><small style="color:#888"><?php echo esc_html($row->note); ?></small><?php endif; ?></td>
                    <td>¥<?php echo esc_html(xi_money($row->amount)); ?></td>
                    <td><?php echo esc_html(xi_pay_method_name($row->method)); ?><br><small><?php echo esc_html($row->gateway); ?></small></td>
                    <td><b style="color:<?php echo esc_attr($colors[$st[0]]); ?>"><?php echo esc_html($st[1]); ?></b></td>
                    <td><?php echo esc_html(mysql2date('Y-m-d H:i', $row->created_at)); ?><?php if ($row->paid_at) : ?><br><small><?php echo esc_html(sprintf(__('支付于 %s', 'xi'), mysql2date('Y-m-d H:i', $row->paid_at))); ?></small><?php endif; ?></td>
                    <td>
                        <?php if ((int) $row->status !== 1) : ?>
                            <form method="post" style="display:inline">
                                <?php wp_nonce_field('xi_orders'); ?>
                                <input type="hidden" name="order_no" value="<?php echo esc_attr($row->order_no); ?>">
                                <button class="button button-small" name="xi_order_action" value="paid" data-confirm="<?php esc_attr_e('确认用户已付款并补单？商品会立即发放', 'xi'); ?>"><?php esc_html_e('补单', 'xi'); ?></button>
                                <?php if ((int) $row->status === 0) : ?>
                                    <button class="button button-small button-link-delete" name="xi_order_action" value="close"><?php esc_html_e('关闭', 'xi'); ?></button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $pages = (int) ceil($total / $per);
        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links(array('base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $paged, 'total' => $pages)) . '</div></div>';
        }
        ?>
        <script>
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-confirm]');
            if (btn && !confirm(btn.getAttribute('data-confirm'))) e.preventDefault();
        });
        </script>
    </div>
    <?php
}
