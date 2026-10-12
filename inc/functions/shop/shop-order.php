<?php
/**
 * 商城订单：独立的 xi_shop_orders 表
 *
 * 状态：0 待付款 → 1 待发货 → 2 待收货 → 3 已完成；4 已关闭；5 售后中；6 已退款
 * 付款方式：online 在线支付（经 xi_orders 支付单）| balance 余额 | points 积分 | free 免费
 * 到账统一走 xi_shop_order_paid()：状态原子更新，只会履约一次；超时关闭后才到账的订单照常履约
 */

if (!defined('ABSPATH')) {
    exit;
}

define('XI_SHOP_DB_VER', '2');

function xi_shop_orders_table()
{
    global $wpdb;
    return $wpdb->prefix . 'xi_shop_orders';
}

function xi_shop_install()
{
    if (get_option('xi_shop_db_ver') === XI_SHOP_DB_VER) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = xi_shop_orders_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_no varchar(32) NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  title varchar(200) NOT NULL DEFAULT '',
  items longtext NULL,
  price_type varchar(10) NOT NULL DEFAULT 'money',
  goods_total decimal(10,2) NOT NULL DEFAULT 0.00,
  discount decimal(10,2) NOT NULL DEFAULT 0.00,
  shipping decimal(10,2) NOT NULL DEFAULT 0.00,
  total decimal(10,2) NOT NULL DEFAULT 0.00,
  extra text NULL,
  coupon varchar(50) NOT NULL DEFAULT '',
  pay_type varchar(20) NOT NULL DEFAULT '',
  pay_no varchar(32) NOT NULL DEFAULT '',
  status tinyint(4) NOT NULL DEFAULT 0,
  address text NULL,
  contact varchar(100) NOT NULL DEFAULT '',
  remark varchar(500) NOT NULL DEFAULT '',
  express varchar(255) NOT NULL DEFAULT '',
  refund text NULL,
  note varchar(255) NOT NULL DEFAULT '',
  admin_note varchar(255) NOT NULL DEFAULT '',
  created_at datetime NULL,
  paid_at datetime NULL,
  shipped_at datetime NULL,
  finished_at datetime NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY order_no (order_no),
  KEY user_id (user_id),
  KEY status (status),
  KEY pay_no (pay_no)
) {$charset};");
    update_option('xi_shop_db_ver', XI_SHOP_DB_VER);
}
// 前后台都检查：主题更新后即使管理员还没进后台，前台订单也能正常读写
add_action('admin_init', 'xi_shop_install');
add_action('init', 'xi_shop_install', 20);

/* ---------- 状态 ---------- */

function xi_shop_statuses()
{
    return array(
        0 => __('待付款', 'xi'),
        1 => __('待发货', 'xi'),
        2 => __('待收货', 'xi'),
        3 => __('已完成', 'xi'),
        4 => __('已关闭', 'xi'),
        5 => __('售后中', 'xi'),
        6 => __('已退款', 'xi'),
    );
}

function xi_shop_status_name($status)
{
    $list = xi_shop_statuses();
    return isset($list[(int) $status]) ? $list[(int) $status] : '';
}

function xi_shop_pay_type_name($type)
{
    $names = array(
        'online'  => __('在线支付', 'xi'),
        'balance' => __('余额支付', 'xi'),
        'points'  => sprintf(__('%s支付', 'xi'), xi_points_name()),
        'free'    => __('免费领取', 'xi'),
        'manual'  => __('后台补单', 'xi'),
    );
    return isset($names[$type]) ? $names[$type] : $type;
}

/* ---------- 读取 ---------- */

function xi_shop_decode_order($order)
{
    if (!$order) {
        return null;
    }
    foreach (array('items', 'address', 'refund', 'extra') as $key) {
        $order->$key = isset($order->$key) && $order->$key ? json_decode($order->$key, true) : array();
        $order->$key = is_array($order->$key) ? $order->$key : array();
    }
    $order->express = $order->express ? json_decode($order->express, true) : array();
    $order->express = is_array($order->express) ? $order->express : array();
    return $order;
}

function xi_shop_get_order($order_no)
{
    global $wpdb;
    return xi_shop_decode_order($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . xi_shop_orders_table() . ' WHERE order_no = %s', $order_no)));
}

/**
 * 用户的订单
 *
 * @param int|null $status 状态筛选
 */
function xi_shop_user_orders($user_id, $status = null, $limit = 20, $offset = 0)
{
    global $wpdb;
    if (get_option('xi_shop_db_ver') !== XI_SHOP_DB_VER) {
        return array();
    }
    $sql  = 'SELECT * FROM ' . xi_shop_orders_table() . ' WHERE user_id = %d';
    $args = array($user_id);
    if ($status !== null) {
        $sql   .= ' AND status = %d';
        $args[] = (int) $status;
    }
    $sql   .= ' ORDER BY id DESC LIMIT %d OFFSET %d';
    $args[] = $limit;
    $args[] = $offset;
    return array_map('xi_shop_decode_order', $wpdb->get_results($wpdb->prepare($sql, $args)));
}

function xi_shop_user_order_counts($user_id)
{
    global $wpdb;
    $counts = array_fill_keys(array_keys(xi_shop_statuses()), 0);
    if (get_option('xi_shop_db_ver') !== XI_SHOP_DB_VER) {
        return $counts;
    }
    $rows = $wpdb->get_results($wpdb->prepare('SELECT status, COUNT(*) AS n FROM ' . xi_shop_orders_table() . ' WHERE user_id = %d GROUP BY status', $user_id));
    foreach ($rows as $row) {
        $counts[(int) $row->status] = (int) $row->n;
    }
    return $counts;
}

/**
 * 用户已购买（已付款且未退款）某商品的数量，用于限购与评价资格
 */
function xi_shop_user_bought($user_id, $product_id)
{
    global $wpdb;
    if (!$user_id || get_option('xi_shop_db_ver') !== XI_SHOP_DB_VER) {
        return 0;
    }
    $rows = $wpdb->get_col($wpdb->prepare(
        'SELECT items FROM ' . xi_shop_orders_table() . ' WHERE user_id = %d AND status IN (1,2,3,5) AND items LIKE %s',
        $user_id,
        '%"pid":' . (int) $product_id . ',%'
    ));
    $qty = 0;
    foreach ($rows as $json) {
        foreach ((array) json_decode($json, true) as $item) {
            if ((int) $item['pid'] === (int) $product_id) {
                $qty += (int) $item['qty'];
            }
        }
    }
    return $qty;
}

/* ---------- 创建 ---------- */

/**
 * 校验并计算购买的商品
 *
 * @param array $lines 每项 pid, spec, qty
 * @return array|WP_Error items, price_type, goods_total, need_address, need_contact, need_remark
 */
function xi_shop_build_items($user_id, $lines)
{
    if (!$lines) {
        return new WP_Error('empty', __('请选择要购买的商品', 'xi'));
    }
    $items  = array();
    $type   = null;
    $total  = 0;
    $flags  = array('need_address' => false, 'need_contact' => false, 'need_remark' => false, 'refund' => true);
    $merged = array();
    foreach ($lines as $line) {
        $key = (int) $line['pid'] . ':' . (int) $line['spec'];
        $merged[$key] = (isset($merged[$key]) ? $merged[$key] : 0) + max(1, (int) $line['qty']);
    }

    foreach ($merged as $key => $qty) {
        list($pid, $spec) = array_map('intval', explode(':', $key));
        $post = get_post($pid);
        if (!$post || $post->post_type !== 'xi_product' || $post->post_status !== 'publish') {
            return new WP_Error('product', __('商品不存在或已下架', 'xi'));
        }
        $data  = xi_product_data($pid);
        $price = xi_product_price($pid, $spec, $user_id);
        if (!$price) {
            return new WP_Error('spec', sprintf(__('「%s」的规格已变更，请重新选择', 'xi'), $post->post_title));
        }
        if ($qty > 99) {
            return new WP_Error('qty', __('单个商品一次最多购买 99 件', 'xi'));
        }
        $stock = xi_product_stock($pid, $spec);
        if ($stock >= 0 && $qty > $stock) {
            return new WP_Error('stock', $stock ? sprintf(__('「%1$s」库存仅剩 %2$d 件', 'xi'), $post->post_title, $stock) : sprintf(__('「%s」已售罄', 'xi'), $post->post_title));
        }
        $limit = (int) $data['limit'];
        if ($limit > 0 && xi_shop_user_bought($user_id, $pid) + $qty > $limit) {
            return new WP_Error('limit', sprintf(__('「%1$s」每人限购 %2$d 件', 'xi'), $post->post_title, $limit));
        }
        // 现金、积分商品不能放在同一个订单；免费商品可以和任意一类一起
        $t = $price['type'] === 'free' ? null : $price['type'];
        if ($t && $type && $t !== $type) {
            return new WP_Error('mixed', sprintf(__('现金商品与%s商品请分开下单', 'xi'), xi_points_name()));
        }
        $type = $t ? $t : $type;

        $spec_row = xi_product_spec($pid, $spec);
        $subtotal = $price['type'] === 'points' ? (int) $price['price'] * $qty : round($price['price'] * $qty, 2);
        $total   += $subtotal;
        $items[]  = array(
            'pid'      => $pid,
            'spec'     => $spec,
            'spec_name' => $spec_row ? (string) $spec_row['name'] : '',
            'title'    => $post->post_title,
            'thumb'    => (string) get_the_post_thumbnail_url($pid, 'thumbnail'),
            'price'    => $price['price'],
            'qty'      => $qty,
            'subtotal' => $subtotal,
            'delivery' => $data['delivery'],
        );
        $flags['need_address'] = $flags['need_address'] || $data['delivery'] === 'physical';
        $flags['need_contact'] = $flags['need_contact'] || !empty($data['need_contact']);
        $flags['need_remark']  = $flags['need_remark'] || !empty($data['need_remark']);
        $flags['refund']       = $flags['refund'] && !empty($data['refund']);
    }

    return array_merge($flags, array(
        'items'       => $items,
        'price_type'  => $type ? $type : 'money',
        'goods_total' => $type === 'points' ? (int) $total : round($total, 2),
    ));
}

function xi_shop_order_no()
{
    return 'S' . wp_date('YmdHis') . wp_rand(10000, 99999);
}

/**
 * 创建商城订单（待付款）
 *
 * @param array $args lines, address, contact, remark, coupon
 * @return object|WP_Error
 */
function xi_shop_order_create($user_id, $args)
{
    if (!xi_shop_enabled()) {
        return new WP_Error('off', __('商城已关闭', 'xi'));
    }
    $built = xi_shop_build_items($user_id, $args['lines']);
    if (is_wp_error($built)) {
        return $built;
    }

    $address = array();
    if ($built['need_address']) {
        $address = xi_shop_get_address($user_id, isset($args['address']) ? $args['address'] : '');
        if (!$address) {
            return new WP_Error('address', __('请选择收货地址', 'xi'));
        }
    }
    $contact = isset($args['contact']) ? trim((string) $args['contact']) : '';
    if ($built['need_contact'] && $contact === '') {
        return new WP_Error('contact', __('请填写联系方式', 'xi'));
    }
    $remark = isset($args['remark']) ? trim((string) $args['remark']) : '';
    if ($built['need_remark'] && $remark === '') {
        return new WP_Error('remark', __('请填写订单备注', 'xi'));
    }

    $quote = xi_shop_quote($user_id, $built, array(
        'coupon' => isset($args['coupon']) ? $args['coupon'] : '',
        'points' => !empty($args['points']),
    ));
    if (is_wp_error($quote)) {
        return $quote;
    }
    $total = $built['price_type'] === 'points' ? (int) $quote['total'] : $quote['total'];
    $extra = array(
        'promo'    => $quote['promo'],
        'coupon'   => $quote['coupon'],
        'points'   => $quote['points'],
        'shipping' => $quote['shipping'],
    );

    // 同一用户 10 分钟内最多下 20 单
    $key   = 'xi_shop_rate_' . $user_id;
    $count = (int) get_transient($key);
    if ($count >= 20) {
        return new WP_Error('frequent', __('下单太频繁，请稍后再试', 'xi'));
    }
    set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);

    $title = $built['items'][0]['title'];
    if (count($built['items']) > 1) {
        $title = sprintf(__('%1$s 等 %2$d 件商品', 'xi'), $title, count($built['items']));
    }

    xi_shop_install();
    global $wpdb;
    $order_no = xi_shop_order_no();

    // 积分抵扣在下单时扣除，订单关闭或退款时归还
    if ($quote['points']['points'] > 0) {
        $res = xi_change_points($user_id, -$quote['points']['points'], sprintf(__('商城抵扣 · %s', 'xi'), $order_no));
        if (is_wp_error($res)) {
            return new WP_Error('points', sprintf(__('%s不足，请刷新页面重新计算', 'xi'), xi_points_name()));
        }
    }

    $ok       = $wpdb->insert(xi_shop_orders_table(), array(
        'order_no'    => $order_no,
        'user_id'     => $user_id,
        'title'       => mb_substr($title, 0, 190),
        'items'       => wp_json_encode($built['items']),
        'price_type'  => $built['price_type'],
        'goods_total' => $built['goods_total'],
        'discount'    => $quote['discount'],
        'shipping'    => $quote['shipping'],
        'total'       => $total,
        'coupon'      => $quote['coupon'] ? $quote['coupon']['code'] : '',
        'extra'       => wp_json_encode($extra),
        'status'      => 0,
        'address'     => $address ? wp_json_encode($address) : '',
        'contact'     => mb_substr($contact, 0, 100),
        'remark'      => mb_substr($remark, 0, 500),
        'refund'      => wp_json_encode(array('allow' => $built['refund'])),
        'created_at'  => current_time('mysql'),
    ));
    if (!$ok) {
        if ($quote['points']['points'] > 0) {
            xi_change_points($user_id, $quote['points']['points'], sprintf(__('商城抵扣退回 · %s', 'xi'), $order_no));
        }
        return new WP_Error('db', __('订单创建失败，请稍后再试', 'xi'));
    }
    do_action('xi_shop_order_created', $order_no);
    return xi_shop_get_order($order_no);
}

/* ---------- 付款 ---------- */

/**
 * 订单可用的付款方式
 */
function xi_shop_pay_options($order)
{
    $total = (float) $order->total;
    if ($total <= 0) {
        return array('free' => xi_shop_pay_type_name('free'));
    }
    if ($order->price_type === 'points') {
        return xi_points_enabled() ? array('points' => xi_shop_pay_type_name('points')) : array();
    }
    $list = array();
    if (function_exists('xi_pay_enabled') && xi_pay_enabled()) {
        foreach (xi_pay_methods() as $method => $gateway) {
            $list['online:' . $method] = xi_pay_method_name($method);
        }
    }
    if (xi_balance_enabled() && _xz('shop_balance_pay', true)) {
        $list['balance'] = __('余额支付', 'xi');
    }
    return $list;
}

/**
 * 发起付款
 *
 * @param string $pay free | points | balance | online:alipay ...
 * @return array|WP_Error paid=true 已到账；或 type=qrcode|redirect 的在线支付参数
 */
function xi_shop_order_pay($order, $pay)
{
    if ((int) $order->status !== 0) {
        return new WP_Error('status', __('订单不是待付款状态', 'xi'));
    }
    $options = xi_shop_pay_options($order);
    if (!isset($options[$pay])) {
        return new WP_Error('pay', __('该付款方式暂不可用', 'xi'));
    }
    // 付款前再核对一次库存，避免长时间未付款的订单超卖
    foreach ($order->items as $item) {
        $stock = xi_product_stock($item['pid'], $item['spec']);
        if ($stock >= 0 && $item['qty'] > $stock) {
            return new WP_Error('stock', sprintf(__('「%s」库存不足，请重新下单', 'xi'), $item['title']));
        }
    }
    $user_id = (int) $order->user_id;

    if ($pay === 'free') {
        xi_shop_order_paid($order->order_no, 'free');
        return array('paid' => true);
    }
    if ($pay === 'points') {
        $res = xi_change_points($user_id, -(int) $order->total, sprintf(__('商城购买 · %s', 'xi'), $order->order_no));
        if (is_wp_error($res)) {
            return $res;
        }
        if (!xi_shop_order_paid($order->order_no, 'points')) {
            // 订单已被其他请求处理：退回刚扣的积分
            xi_change_points($user_id, (int) $order->total, sprintf(__('商城重复扣款退回 · %s', 'xi'), $order->order_no));
            return new WP_Error('status', __('订单状态已变化，请刷新页面', 'xi'));
        }
        return array('paid' => true);
    }
    if ($pay === 'balance') {
        $res = xi_change_balance($user_id, -(float) $order->total, sprintf(__('商城购买 · %s', 'xi'), $order->order_no));
        if (is_wp_error($res)) {
            return $res;
        }
        if (!xi_shop_order_paid($order->order_no, 'balance')) {
            xi_change_balance($user_id, (float) $order->total, sprintf(__('商城重复扣款退回 · %s', 'xi'), $order->order_no));
            return new WP_Error('status', __('订单状态已变化，请刷新页面', 'xi'));
        }
        return array('paid' => true);
    }

    // 在线支付：生成支付单，到账后由 xi_order_paid 回调履约
    $method   = substr($pay, 7);
    $pay_order = xi_order_create($user_id, 'shop', array('shop_no' => $order->order_no), $method);
    if (is_wp_error($pay_order)) {
        return $pay_order;
    }
    global $wpdb;
    $wpdb->update(xi_shop_orders_table(), array('pay_no' => $pay_order->order_no), array('order_no' => $order->order_no, 'status' => 0));
    $res = xi_pay_initiate($pay_order);
    if (is_wp_error($res)) {
        $wpdb->update(xi_orders_table(), array('status' => 2, 'note' => substr($res->get_error_message(), 0, 255)), array('order_no' => $pay_order->order_no));
        return $res;
    }
    return array_merge($res, array(
        'pay_no' => $pay_order->order_no,
        'amount' => xi_money($pay_order->amount),
        'method' => $method,
    ));
}

/**
 * 在线支付单的商品：金额取自商城订单，不信任前端
 */
function xi_shop_pay_build_product($item, $user_id, $type, $args)
{
    if ($type !== 'shop') {
        return $item;
    }
    $order = isset($args['shop_no']) ? xi_shop_get_order($args['shop_no']) : null;
    if (!$order || (int) $order->user_id !== (int) $user_id) {
        return new WP_Error('order', __('订单不存在', 'xi'));
    }
    if ((int) $order->status !== 0 || $order->price_type !== 'money' || (float) $order->total <= 0) {
        return new WP_Error('status', __('订单不是待付款状态', 'xi'));
    }
    return array(
        'title'   => sprintf(__('商城订单 · %s', 'xi'), $order->title),
        'amount'  => (float) $order->total,
        'product' => array('shop_no' => $order->order_no),
    );
}
add_filter('xi_pay_build_product', 'xi_shop_pay_build_product', 10, 4);

/**
 * 在线支付到账
 */
function xi_shop_on_pay_order_paid($pay_order)
{
    if ($pay_order && $pay_order->type === 'shop' && !empty($pay_order->product['shop_no'])) {
        xi_shop_order_paid($pay_order->product['shop_no'], 'online', $pay_order->order_no);
    }
}
add_action('xi_order_paid', 'xi_shop_on_pay_order_paid');

/**
 * 订单到账：原子更新状态并履约，只有第一次调用生效
 */
function xi_shop_order_paid($order_no, $pay_type, $pay_no = '')
{
    global $wpdb;
    $table  = xi_shop_orders_table();
    $before = xi_shop_get_order($order_no);
    $set    = 'status = 1, pay_type = %s, paid_at = %s';
    $args  = array($pay_type, current_time('mysql'));
    if ($pay_no) {
        $set   .= ', pay_no = %s';
        $args[] = $pay_no;
    }
    $args[] = $order_no;
    if ((int) $wpdb->query($wpdb->prepare("UPDATE {$table} SET {$set} WHERE order_no = %s AND status IN (0,4)", $args)) !== 1) {
        return false;
    }
    $order = xi_shop_get_order($order_no);

    // 超时关闭后才到账：关闭时归还过的抵扣积分重新扣回
    $extra = xi_shop_order_extra($order);
    if ($before && (int) $before->status === 4 && !empty($extra['points_released']) && $extra['points']['points'] > 0) {
        $res                      = xi_change_points((int) $order->user_id, -(int) $extra['points']['points'], sprintf(__('商城抵扣 · %s', 'xi'), $order_no));
        $extra['points_released'] = 0;
        $wpdb->update($table, array('extra' => wp_json_encode($extra)), array('order_no' => $order_no));
        if (is_wp_error($res)) {
            $wpdb->update($table, array('admin_note' => __('订单关闭后才到账，抵扣积分已退回且用户积分不足，未能重新扣除', 'xi')), array('order_no' => $order_no));
        }
    }

    // 发货：卡密出库、固定内容直接交付，其余等待商家发货
    $items   = $order->items;
    $pending = false;
    foreach ($items as $i => $item) {
        xi_product_reduce_stock($item['pid'], $item['spec'], $item['qty']);
        xi_product_add_sales($item['pid'], $item['qty']);
        $pdata = xi_product_data($item['pid'], true);
        if ($item['delivery'] === 'auto_card') {
            $spec = $pdata['specs'] ? $item['spec'] : 0;
            // 两笔订单同时到账时不能领到同一张卡密：出库期间加锁，最多等 3 秒
            $lock = 'cards_' . $item['pid'];
            for ($try = 0; $try < 30 && !xi_lock($lock); $try++) {
                usleep(100000);
            }
            wp_cache_delete($item['pid'], 'post_meta');
            $cards = xi_product_cards($item['pid'], $spec);
            $take  = array_splice($cards, 0, $item['qty']);
            xi_product_set_cards($item['pid'], $spec, $cards);
            xi_unlock($lock);
            $items[$i]['delivered'] = $take;
            if (count($take) < $item['qty']) {
                $items[$i]['short'] = $item['qty'] - count($take);
                $pending = true;
            }
        } elseif ($item['delivery'] === 'auto_text') {
            $items[$i]['delivered'] = array((string) $pdata['delivery_text']);
        } else {
            $pending = true;
        }
    }

    $update = array('items' => wp_json_encode($items));
    if (!$pending) {
        $update['status']      = 3;
        $update['shipped_at']  = current_time('mysql');
        $update['finished_at'] = current_time('mysql');
    } elseif (array_filter($items, function ($it) {
        return !empty($it['short']);
    })) {
        $update['note'] = __('卡密库存不足，部分商品需人工补发', 'xi');
    }
    $wpdb->update($table, $update, array('order_no' => $order_no));

    if ($order->coupon) {
        xi_shop_coupon_used($order->coupon);
    }

    $order = xi_shop_get_order($order_no);
    xi_shop_notify_paid($order);
    xi_shop_stock_alert($order);
    do_action('xi_shop_order_paid', $order);
    return true;
}

/* ---------- 关闭、发货、收货 ---------- */

function xi_shop_order_close($order_no, $note = '')
{
    global $wpdb;
    $done = (int) $wpdb->query($wpdb->prepare('UPDATE ' . xi_shop_orders_table() . ' SET status = 4, note = %s WHERE order_no = %s AND status = 0', $note, $order_no)) === 1;
    if ($done) {
        xi_shop_release_points(xi_shop_get_order($order_no), sprintf(__('商城订单关闭，退回抵扣 · %s', 'xi'), $order_no));
        do_action('xi_shop_order_closed', $order_no);
    }
    return $done;
}

/**
 * 关闭超时未付款的订单（逐个关闭，以便归还抵扣积分）
 *
 * @param int $user_id 0 为全部用户
 */
function xi_shop_close_expired($user_id = 0)
{
    global $wpdb;
    if (get_option('xi_shop_db_ver') !== XI_SHOP_DB_VER) {
        return;
    }
    $minutes = max(5, (int) _xz('shop_unpaid_minutes', 30));
    $before  = date('Y-m-d H:i:s', current_time('timestamp') - $minutes * MINUTE_IN_SECONDS);
    $sql     = 'SELECT order_no FROM ' . xi_shop_orders_table() . ' WHERE status = 0 AND created_at < %s' . ($user_id ? ' AND user_id = %d' : '') . ' LIMIT 200';
    $nos     = $wpdb->get_col($user_id ? $wpdb->prepare($sql, $before, $user_id) : $wpdb->prepare($sql, $before));
    foreach ($nos as $no) {
        xi_shop_order_close($no, __('超时未付款，系统自动关闭', 'xi'));
    }
}

/**
 * 待付款订单的自动关闭时间（真实时间戳）
 */
function xi_shop_pay_deadline($order)
{
    $minutes = max(5, (int) _xz('shop_unpaid_minutes', 30));
    return strtotime($order->created_at) + $minutes * MINUTE_IN_SECONDS - (int) (get_option('gmt_offset') * HOUR_IN_SECONDS);
}

/**
 * 发货
 *
 * @param array $args company, number（实物）; content（虚拟商品补发内容）
 */
function xi_shop_order_ship($order_no, $args)
{
    $order = xi_shop_get_order($order_no);
    if (!$order || (int) $order->status !== 1) {
        return new WP_Error('status', __('订单不是待发货状态', 'xi'));
    }
    $items   = $order->items;
    $content = isset($args['content']) ? trim((string) $args['content']) : '';
    $has_physical = false;
    foreach ($items as $i => $item) {
        if ($item['delivery'] === 'physical') {
            $has_physical = true;
        } elseif (empty($item['delivered']) || !empty($item['short'])) {
            if ($content === '') {
                return new WP_Error('content', __('请填写发货内容（卡密、下载地址等）', 'xi'));
            }
            $items[$i]['delivered'] = array_merge(isset($item['delivered']) ? (array) $item['delivered'] : array(), array($content));
            unset($items[$i]['short']);
        }
    }
    $express = array();
    if ($has_physical) {
        $express = array(
            'company' => isset($args['company']) ? sanitize_text_field($args['company']) : '',
            'number'  => isset($args['number']) ? sanitize_text_field($args['number']) : '',
        );
        if ($express['company'] === '' || $express['number'] === '') {
            return new WP_Error('express', __('请填写快递公司和单号', 'xi'));
        }
    }
    global $wpdb;
    $wpdb->update(xi_shop_orders_table(), array(
        'items'      => wp_json_encode($items),
        'express'    => $express ? wp_json_encode($express) : '',
        // 只有虚拟商品时发货即完成；有实物时等待买家确认收货
        'status'     => $has_physical ? 2 : 3,
        'shipped_at' => current_time('mysql'),
        'finished_at' => $has_physical ? null : current_time('mysql'),
        'note'       => '',
    ), array('order_no' => $order_no, 'status' => 1));
    $order = xi_shop_get_order($order_no);
    xi_shop_notify_shipped($order);
    do_action('xi_shop_order_shipped', $order);
    return true;
}

function xi_shop_order_confirm($order_no)
{
    global $wpdb;
    return (int) $wpdb->query($wpdb->prepare('UPDATE ' . xi_shop_orders_table() . ' SET status = 3, finished_at = %s WHERE order_no = %s AND status = 2', current_time('mysql'), $order_no)) === 1;
}

/* ---------- 售后 ---------- */

/**
 * 是否还能申请售后
 */
function xi_shop_can_refund($order)
{
    // 免费领取、后台补单的订单没有可退的款项
    if (!_xz('shop_refund_s', true) || (float) $order->total <= 0 || $order->pay_type === 'manual' || !in_array((int) $order->status, array(1, 2, 3), true) || empty($order->refund['allow'])) {
        return false;
    }
    if (!empty($order->refund['rejected']) && (int) $order->refund['rejected'] >= 2) {
        return false; // 被驳回两次后不能再申请
    }
    $days = max(0, (int) _xz('shop_refund_days', 7));
    return $days === 0 || strtotime($order->paid_at) + $days * DAY_IN_SECONDS >= current_time('timestamp');
}

/**
 * 订单是否包含实物（可以申请退货退款）
 */
function xi_shop_order_has_physical($order)
{
    foreach ($order->items as $item) {
        if ($item['delivery'] === 'physical') {
            return true;
        }
    }
    return false;
}

function xi_shop_refund_types()
{
    return array('refund' => __('仅退款', 'xi'), 'return' => __('退货退款', 'xi'));
}

/**
 * @param string $type refund 仅退款 | return 退货退款（已发货的实物订单）
 */
function xi_shop_refund_apply($order, $reason, $type = 'refund')
{
    if (!xi_shop_can_refund($order)) {
        return new WP_Error('refund', __('该订单不支持售后或已超过售后期限', 'xi'));
    }
    $reason = trim($reason);
    if (mb_strlen($reason) < 4) {
        return new WP_Error('reason', __('请填写售后原因（至少 4 个字）', 'xi'));
    }
    $type = $type === 'return' && in_array((int) $order->status, array(2, 3), true) && xi_shop_order_has_physical($order) ? 'return' : 'refund';
    $refund = array_merge($order->refund, array(
        'type'   => $type,
        'stage'  => 'apply',
        'reason' => mb_substr($reason, 0, 300),
        'from'   => (int) $order->status,
        'time'   => current_time('mysql'),
        'reply'  => '',
        'return_express' => array(),
    ));
    global $wpdb;
    $done = (int) $wpdb->query($wpdb->prepare('UPDATE ' . xi_shop_orders_table() . ' SET status = 5, refund = %s WHERE order_no = %s AND status = %d', wp_json_encode($refund), $order->order_no, (int) $order->status)) === 1;
    if (!$done) {
        return new WP_Error('status', __('订单状态已变化，请刷新页面', 'xi'));
    }
    $types = xi_shop_refund_types();
    xi_shop_notify_admin(sprintf(__('订单 %1$s 申请售后（%2$s）', 'xi'), $order->order_no, $types[$type]), '<p>' . esc_html($reason) . '</p>');
    return true;
}

/**
 * 商家同意退货：买家按退货地址寄回后填写快递单号
 */
function xi_shop_refund_agree_return($order_no, $reply = '')
{
    $order = xi_shop_get_order($order_no);
    if (!$order || (int) $order->status !== 5 || (isset($order->refund['type']) ? $order->refund['type'] : '') !== 'return' || $order->refund['stage'] !== 'apply') {
        return new WP_Error('status', __('该售后不是待处理的退货申请', 'xi'));
    }
    $refund = array_merge($order->refund, array('stage' => 'returning', 'reply' => $reply, 'address' => trim((string) _xz('shop_return_address', ''))));
    global $wpdb;
    $wpdb->update(xi_shop_orders_table(), array('refund' => wp_json_encode($refund)), array('order_no' => $order_no, 'status' => 5));
    xi_shop_notify_user($order, __('退货申请已同意', 'xi'), '<p>' . esc_html(sprintf(__('你的订单 %s 退货申请已同意，请将商品寄回后在订单中填写退货快递单号。', 'xi'), $order_no)) . '</p>' . ($refund['address'] ? '<p>' . esc_html__('退货地址：', 'xi') . nl2br(esc_html($refund['address'])) . '</p>' : '') . ($reply ? '<p>' . esc_html($reply) . '</p>' : ''));
    return true;
}

/**
 * 买家填写退货快递
 */
function xi_shop_refund_return_ship($order, $company, $number)
{
    if ((int) $order->status !== 5 || (isset($order->refund['stage']) ? $order->refund['stage'] : '') !== 'returning') {
        return new WP_Error('status', __('当前不需要填写退货快递', 'xi'));
    }
    $company = mb_substr(trim($company), 0, 30);
    $number  = mb_substr(preg_replace('/\s+/', '', $number), 0, 40);
    if ($company === '' || strlen($number) < 6) {
        return new WP_Error('express', __('请填写快递公司和单号', 'xi'));
    }
    $refund = array_merge($order->refund, array('stage' => 'returned', 'return_express' => array('company' => $company, 'number' => $number, 'time' => current_time('mysql'))));
    global $wpdb;
    $wpdb->update(xi_shop_orders_table(), array('refund' => wp_json_encode($refund)), array('order_no' => $order->order_no, 'status' => 5));
    xi_shop_notify_admin(sprintf(__('订单 %s 买家已寄回退货', 'xi'), $order->order_no), '<p>' . esc_html($company . ' ' . $number) . '</p>');
    return true;
}

function xi_shop_refund_cancel($order)
{
    if ((int) $order->status !== 5) {
        return new WP_Error('status', __('订单不在售后中', 'xi'));
    }
    if (isset($order->refund['stage']) && $order->refund['stage'] === 'returned') {
        return new WP_Error('status', __('退货已寄出，请联系商家处理', 'xi'));
    }
    $from = isset($order->refund['from']) ? (int) $order->refund['from'] : 1;
    global $wpdb;
    $wpdb->update(xi_shop_orders_table(), array('status' => $from), array('order_no' => $order->order_no, 'status' => 5));
    return true;
}

/**
 * 同意退款：余额、在线支付退回站内余额，积分订单退回积分
 * 未开启余额功能时只标记已退款，由站长线下处理
 */
function xi_shop_refund_approve($order_no, $reply = '')
{
    $order = xi_shop_get_order($order_no);
    if (!$order || (int) $order->status !== 5) {
        return new WP_Error('status', __('订单不在售后中', 'xi'));
    }
    global $wpdb;
    $refund = array_merge($order->refund, array('reply' => $reply, 'done' => current_time('mysql')));
    $ok     = (int) $wpdb->query($wpdb->prepare('UPDATE ' . xi_shop_orders_table() . ' SET status = 6, refund = %s WHERE order_no = %s AND status = 5', wp_json_encode($refund), $order_no)) === 1;
    if (!$ok) {
        return new WP_Error('status', __('订单状态已变化', 'xi'));
    }
    // 退回销量；还没发货、或已寄回的实物 / 人工发货商品恢复库存（卡密、固定内容已交付，不再回库）
    $returned = isset($order->refund['type'], $order->refund['stage']) && $order->refund['type'] === 'return' && $order->refund['stage'] === 'returned';
    $shipped  = isset($order->refund['from']) && (int) $order->refund['from'] !== 1 && !$returned;
    foreach ($order->items as $item) {
        if (get_post_type($item['pid']) !== 'xi_product') {
            continue;
        }
        xi_product_add_sales($item['pid'], -(int) $item['qty']);
        if (!$shipped && in_array($item['delivery'], array('physical', 'manual'), true)) {
            xi_product_restore_stock($item['pid'], $item['spec'], $item['qty']);
        }
    }

    $uid  = (int) $order->user_id;
    $note = sprintf(__('商城退款 · %s', 'xi'), $order_no);
    $to   = '';
    xi_shop_release_points($order, sprintf(__('商城退款，退回抵扣 · %s', 'xi'), $order_no));
    if ($order->pay_type === 'points') {
        xi_change_points($uid, (int) $order->total, $note);
        $to = xi_points_name();
    } elseif ((float) $order->total > 0 && in_array($order->pay_type, array('balance', 'online'), true)) {
        if (xi_balance_enabled()) {
            xi_change_balance($uid, (float) $order->total, $note);
            $to = __('余额', 'xi');
        } else {
            $refund['manual'] = 1;
            $wpdb->update(xi_shop_orders_table(), array('refund' => wp_json_encode($refund)), array('order_no' => $order_no));
        }
    }
    xi_shop_notify_user($order, __('售后已同意', 'xi'), '<p>' . esc_html(sprintf(__('你的订单 %s 售后申请已同意。', 'xi'), $order_no)) . ($to ? esc_html(sprintf(__('退款已退回到你的%s。', 'xi'), $to)) : '') . '</p>' . ($reply ? '<p>' . esc_html($reply) . '</p>' : ''));
    do_action('xi_shop_order_refunded', xi_shop_get_order($order_no));
    return true;
}

function xi_shop_refund_reject($order_no, $reply)
{
    $order = xi_shop_get_order($order_no);
    if (!$order || (int) $order->status !== 5) {
        return new WP_Error('status', __('订单不在售后中', 'xi'));
    }
    $refund = array_merge($order->refund, array(
        'reply'    => $reply,
        'rejected' => (isset($order->refund['rejected']) ? (int) $order->refund['rejected'] : 0) + 1,
    ));
    $from = isset($order->refund['from']) ? (int) $order->refund['from'] : 1;
    global $wpdb;
    $wpdb->update(xi_shop_orders_table(), array('status' => $from, 'refund' => wp_json_encode($refund)), array('order_no' => $order_no, 'status' => 5));
    xi_shop_notify_user($order, __('售后申请未通过', 'xi'), '<p>' . esc_html(sprintf(__('你的订单 %s 售后申请未通过。', 'xi'), $order_no)) . '</p>' . ($reply ? '<p>' . esc_html($reply) . '</p>' : ''));
    return true;
}

/* ---------- 定时任务：超时关单、自动收货 ---------- */

function xi_shop_cron_schedule()
{
    if (xi_shop_enabled() && !wp_next_scheduled('xi_shop_cron')) {
        wp_schedule_event(time() + 300, 'hourly', 'xi_shop_cron');
    } elseif (!xi_shop_enabled() && wp_next_scheduled('xi_shop_cron')) {
        wp_clear_scheduled_hook('xi_shop_cron');
    }
}
add_action('init', 'xi_shop_cron_schedule', 99);

function xi_shop_cron()
{
    if (get_option('xi_shop_db_ver') !== XI_SHOP_DB_VER) {
        return;
    }
    global $wpdb;
    $table = xi_shop_orders_table();
    $now   = current_time('timestamp');

    xi_shop_close_expired();

    $days = max(1, (int) _xz('shop_auto_confirm_days', 10));
    $wpdb->query($wpdb->prepare("UPDATE {$table} SET status = 3, finished_at = %s WHERE status = 2 AND shipped_at < %s", current_time('mysql'), date('Y-m-d H:i:s', $now - $days * DAY_IN_SECONDS)));
}
add_action('xi_shop_cron', 'xi_shop_cron');

/**
 * WP-Cron 依赖访问触发：用户打开订单列表时顺带检查一次自己的超时订单
 */
function xi_shop_close_expired_for($user_id)
{
    xi_shop_close_expired((int) $user_id);
}

/* ---------- 通知 ---------- */

/**
 * 库存预警：付款后剩余库存不多时提醒站长（同一商品规格每天最多一次）
 */
function xi_shop_stock_alert($order)
{
    $limit = (int) _xz('shop_stock_alert', 3);
    if ($limit <= 0) {
        return;
    }
    $lines = array();
    foreach ($order->items as $item) {
        $stock = xi_product_stock($item['pid'], $item['spec']);
        $key   = 'xi_stock_alert_' . $item['pid'] . '_' . $item['spec'];
        if ($stock >= 0 && $stock <= $limit && !get_transient($key)) {
            set_transient($key, 1, DAY_IN_SECONDS);
            $lines[] = '<li>' . esc_html($item['title'] . ($item['spec_name'] ? '（' . $item['spec_name'] . '）' : '') . '：' . sprintf(__('剩余 %d', 'xi'), $stock)) . '</li>';
        }
    }
    if ($lines) {
        xi_shop_notify_admin(__('商品库存不足提醒', 'xi'), '<ul>' . implode('', $lines) . '</ul>');
    }
}

function xi_shop_notify_user($order, $subject, $html)
{
    $user = get_userdata((int) $order->user_id);
    if ($user && is_email($user->user_email) && function_exists('xi_send_mail')) {
        xi_send_mail($user->user_email, $subject, $subject, $html . '<p><a href="' . esc_url(xi_uc_url('shop')) . '">' . esc_html__('查看我的订单', 'xi') . '</a></p>');
    }
}

function xi_shop_notify_admin($subject, $html)
{
    $to = trim((string) _xz('shop_notify_email', ''));
    $to = is_email($to) ? $to : get_option('admin_email');
    if ($to && function_exists('xi_send_mail')) {
        xi_send_mail($to, $subject, $subject, $html . '<p><a href="' . esc_url(admin_url('edit.php?post_type=xi_product&page=xi_shop_orders')) . '">' . esc_html__('前往订单管理', 'xi') . '</a></p>');
    }
}

/**
 * 发货内容（卡密、下载地址等）的邮件片段
 */
function xi_shop_delivery_html($order)
{
    $html = '';
    foreach ($order->items as $item) {
        if (empty($item['delivered'])) {
            continue;
        }
        $html .= '<p style="margin:14px 0 6px;font-weight:600">' . esc_html($item['title'] . ($item['spec_name'] ? ' · ' . $item['spec_name'] : '')) . '</p>';
        foreach ((array) $item['delivered'] as $line) {
            $html .= '<div style="padding:10px 12px;margin:4px 0;border-radius:8px;background:#f3f7f6;word-break:break-all">' . wp_kses_post(make_clickable(nl2br(esc_html($line)))) . '</div>';
        }
    }
    return $html;
}

function xi_shop_notify_paid($order)
{
    $html = '<p>' . esc_html(sprintf(__('订单 %1$s（%2$s）已付款。', 'xi'), $order->order_no, $order->title)) . '</p>';
    if ((int) $order->status === 3) {
        $html .= '<p>' . esc_html__('商品已自动发货：', 'xi') . '</p>' . xi_shop_delivery_html($order);
    } else {
        $html .= '<p>' . esc_html__('商家会尽快为你发货。', 'xi') . '</p>';
    }
    xi_shop_notify_user($order, __('订单付款成功', 'xi'), $html);
    if ((int) $order->status === 1 && _xz('shop_notify_admin_s', true)) {
        xi_shop_notify_admin(sprintf(__('新订单待发货：%s', 'xi'), $order->title), '<p>' . esc_html(sprintf(__('订单号 %1$s，金额 %2$s', 'xi'), $order->order_no, xi_price_text($order->total, $order->price_type))) . '</p>' . ($order->note ? '<p>' . esc_html($order->note) . '</p>' : ''));
    }
}

function xi_shop_notify_shipped($order)
{
    $html = '<p>' . esc_html(sprintf(__('你的订单 %1$s（%2$s）已发货。', 'xi'), $order->order_no, $order->title)) . '</p>';
    if (!empty($order->express['number'])) {
        $html .= '<p>' . esc_html(sprintf(__('快递：%1$s，单号：%2$s', 'xi'), $order->express['company'], $order->express['number'])) . '</p>';
    }
    $html .= xi_shop_delivery_html($order);
    xi_shop_notify_user($order, __('订单已发货', 'xi'), $html);
}
