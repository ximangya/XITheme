<?php
/**
 * 购物车、收货地址、优惠码、商品评价，以及商城前台 Ajax
 * 购物车与地址保存在用户 Meta：xi_cart（"商品ID:规格序号" => 数量）、xi_addresses
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------- 购物车 ---------- */

function xi_cart_get($user_id)
{
    $cart = $user_id ? get_user_meta($user_id, 'xi_cart', true) : array();
    return is_array($cart) ? $cart : array();
}

function xi_cart_save($user_id, $cart)
{
    $cart = array_filter($cart, function ($qty) {
        return (int) $qty > 0;
    });
    // 最多保存 50 种商品
    update_user_meta($user_id, 'xi_cart', array_slice($cart, -50, null, true));
}

function xi_cart_key($pid, $spec)
{
    return (int) $pid . ':' . (int) $spec;
}

/**
 * 购物车明细（失效商品单独标记，不参与结算）
 */
function xi_cart_lines($user_id)
{
    $lines = array();
    foreach (xi_cart_get($user_id) as $key => $qty) {
        list($pid, $spec) = array_map('intval', explode(':', $key . ':0'));
        $post  = get_post($pid);
        $valid = $post && $post->post_type === 'xi_product' && $post->post_status === 'publish';
        $price = $valid ? xi_product_price($pid, $spec, $user_id) : false;
        $row   = $valid ? xi_product_spec($pid, $spec) : null;
        $stock = $valid ? xi_product_stock($pid, $spec) : 0;
        $lines[] = array(
            'key'       => $key,
            'pid'       => $pid,
            'spec'      => $spec,
            'qty'       => (int) $qty,
            'title'     => $post ? $post->post_title : __('商品已删除', 'xi'),
            'link'      => $valid ? get_permalink($pid) : '',
            'thumb'     => $valid ? (string) get_the_post_thumbnail_url($pid, 'thumbnail') : '',
            'spec_name' => $row ? $row['name'] : '',
            'price'     => $price,
            'stock'     => $stock,
            'valid'     => $valid && $price && $stock !== 0,
        );
    }
    return $lines;
}

/* ---------- 收货地址 ---------- */

function xi_shop_addresses($user_id)
{
    $list = get_user_meta($user_id, 'xi_addresses', true);
    return is_array($list) ? $list : array();
}

/**
 * 按 ID 取地址；留空取默认地址
 */
function xi_shop_get_address($user_id, $id = '')
{
    $list = xi_shop_addresses($user_id);
    if ($id !== '' && isset($list[$id])) {
        return $list[$id];
    }
    if ($id === '') {
        foreach ($list as $addr) {
            if (!empty($addr['default'])) {
                return $addr;
            }
        }
    }
    return null;
}

function xi_shop_address_text($addr)
{
    if (!$addr) {
        return '';
    }
    return trim($addr['name'] . ' ' . $addr['phone'] . ' ' . $addr['region'] . ' ' . $addr['detail']);
}

/* ---------- 优惠码 ---------- */

/**
 * 后台设置的优惠码：code, type(reduce 立减 | discount 折扣), value, min, expire, limit
 */
function xi_shop_coupons()
{
    $list = (array) _xz('shop_coupons', array());
    $out  = array();
    foreach ($list as $c) {
        if (!empty($c['code'])) {
            $out[strtoupper(trim($c['code']))] = $c;
        }
    }
    return $out;
}

/**
 * @return array|WP_Error code, discount, text
 */
function xi_shop_coupon_check($code, $amount, $user_id)
{
    $code = strtoupper(trim((string) $code));
    $list = xi_shop_coupons();
    if ($code === '' || !isset($list[$code])) {
        return new WP_Error('coupon', __('优惠码无效', 'xi'));
    }
    $c   = $list[$code];
    $now = current_time('timestamp');
    if (!empty($c['expire']) && strtotime($c['expire'] . ' 23:59:59') < $now) {
        return new WP_Error('coupon', __('优惠码已过期', 'xi'));
    }
    $used = (array) get_option('xi_coupon_used', array());
    if (!empty($c['limit']) && (int) (isset($used[$code]) ? $used[$code] : 0) >= (int) $c['limit']) {
        return new WP_Error('coupon', __('优惠码已被领完', 'xi'));
    }
    if (!empty($c['vip']) && !(function_exists('xi_get_vip_level') && xi_get_vip_level($user_id))) {
        return new WP_Error('coupon', __('该优惠码仅限会员使用', 'xi'));
    }
    $min = (float) (isset($c['min']) ? $c['min'] : 0);
    if ($amount < $min) {
        return new WP_Error('coupon', sprintf(__('订单满 %s 元才能使用该优惠码', 'xi'), xi_money($min)));
    }
    $value = (float) $c['value'];
    if ($c['type'] === 'discount') {
        if ($value <= 0 || $value >= 10) {
            return new WP_Error('coupon', __('优惠码配置错误', 'xi'));
        }
        $discount = round($amount * (1 - $value / 10), 2);
        $text     = sprintf(__('%s 折', 'xi'), rtrim(rtrim(number_format($value, 2), '0'), '.'));
    } else {
        $discount = min($amount, round($value, 2));
        $text     = sprintf(__('立减 %s 元', 'xi'), xi_money($value));
    }
    // 至少支付 0.01 元，避免折后为 0 的现金订单
    $discount = min($discount, max(0, round($amount - 0.01, 2)));
    return array('code' => $code, 'discount' => $discount, 'text' => $text);
}

function xi_shop_coupon_used($code)
{
    $used        = (array) get_option('xi_coupon_used', array());
    $used[$code] = (isset($used[$code]) ? (int) $used[$code] : 0) + 1;
    update_option('xi_coupon_used', $used, false);
}

/* ---------- 评价：只有买过的用户能评价，评分 1-5 ---------- */

function xi_shop_can_review($product_id, $user_id = 0)
{
    $user_id = $user_id ? $user_id : get_current_user_id();
    return $user_id && (user_can($user_id, 'moderate_comments') || xi_shop_user_bought($user_id, $product_id) > 0);
}

function xi_shop_review_check($data)
{
    $post = get_post((int) $data['comment_post_ID']);
    if (!$post || $post->post_type !== 'xi_product' || !empty($data['comment_parent'])) {
        return $data;
    }
    $user_id = isset($data['user_id']) ? (int) $data['user_id'] : (isset($data['user_ID']) ? (int) $data['user_ID'] : 0);
    if (!xi_shop_can_review($post->ID, $user_id)) {
        wp_die(esc_html__('购买后才能评价该商品', 'xi'), esc_html__('无法评价', 'xi'), array('response' => 403, 'back_link' => true));
    }
    return $data;
}
add_filter('preprocess_comment', 'xi_shop_review_check', 5);

function xi_shop_review_save($comment_id, $approved, $data)
{
    if (get_post_type((int) $data['comment_post_ID']) !== 'xi_product' || !empty($data['comment_parent'])) {
        return;
    }
    $rating = isset($_POST['xi_rating']) ? max(1, min(5, (int) $_POST['xi_rating'])) : 5;
    update_comment_meta($comment_id, 'xi_rating', $rating);
    xi_shop_update_rating((int) $data['comment_post_ID']);
}
add_action('comment_post', 'xi_shop_review_save', 10, 3);

function xi_shop_update_rating($post_id)
{
    global $wpdb;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT AVG(m.meta_value) AS avg_rating, COUNT(*) AS n FROM {$wpdb->comments} c INNER JOIN {$wpdb->commentmeta} m ON m.comment_id = c.comment_ID AND m.meta_key = 'xi_rating' WHERE c.comment_post_ID = %d AND c.comment_approved = '1'",
        $post_id
    ));
    update_post_meta($post_id, 'xi_rating', $row && $row->n ? round((float) $row->avg_rating, 1) : 0);
    update_post_meta($post_id, 'xi_rating_count', $row ? (int) $row->n : 0);
}

function xi_shop_rating_on_status($new, $old, $comment)
{
    if ($new !== $old && get_post_type($comment->comment_post_ID) === 'xi_product') {
        xi_shop_update_rating((int) $comment->comment_post_ID);
    }
}
add_action('transition_comment_status', 'xi_shop_rating_on_status', 10, 3);

function xi_shop_stars($rating)
{
    $rating = max(0, min(5, (float) $rating));
    return '<span class="xi-stars" style="--rate:' . esc_attr($rating / 5 * 100) . '%" aria-label="' . esc_attr(sprintf(__('%s 星', 'xi'), $rating)) . '"></span>';
}

/**
 * 评价内容前显示星级
 */
function xi_shop_review_text($text, $comment = null)
{
    if ($comment && !is_admin() && get_post_type($comment->comment_post_ID) === 'xi_product') {
        $rating = (int) get_comment_meta($comment->comment_ID, 'xi_rating', true);
        if ($rating) {
            $text = xi_shop_stars($rating) . $text;
        }
    }
    return $text;
}
add_filter('comment_text', 'xi_shop_review_text', 9, 2);

/**
 * 商品评价表单：评分选择 + 购买校验提示
 */
function xi_shop_review_field()
{
    if (!is_singular('xi_product')) {
        return;
    }
    echo '<div class="xi-rate-pick" role="radiogroup" aria-label="' . esc_attr__('评分', 'xi') . '">';
    for ($i = 5; $i >= 1; $i--) {
        echo '<input type="radio" id="xi-rate-' . $i . '" name="xi_rating" value="' . $i . '"' . checked($i, 5, false) . '><label for="xi-rate-' . $i . '" title="' . esc_attr(sprintf(__('%d 星', 'xi'), $i)) . '">★</label>';
    }
    echo '</div>';
}
add_action('comment_form_logged_in_after', 'xi_shop_review_field');

/* ---------- Ajax ---------- */

function xi_shop_ajax_check()
{
    $user_id = xi_user_ajax_check();
    if (!xi_shop_enabled()) {
        wp_send_json(array('ok' => false, 'msg' => __('商城已关闭', 'xi')));
    }
    return $user_id;
}

function xi_shop_json($ok, $msg = '', $extra = array())
{
    wp_send_json(array_merge(array('ok' => (bool) $ok, 'msg' => $msg), $extra));
}

function xi_ajax_cart_add()
{
    $user_id = xi_shop_ajax_check();
    $pid     = isset($_POST['pid']) ? (int) $_POST['pid'] : 0;
    $spec    = isset($_POST['spec']) ? (int) $_POST['spec'] : 0;
    $qty     = isset($_POST['qty']) ? max(1, min(99, (int) $_POST['qty'])) : 1;

    $post = get_post($pid);
    if (!$post || $post->post_type !== 'xi_product' || $post->post_status !== 'publish') {
        xi_shop_json(false, __('商品不存在或已下架', 'xi'));
    }
    if (!xi_product_price($pid, $spec, $user_id)) {
        xi_shop_json(false, __('请选择规格', 'xi'));
    }
    $cart  = xi_cart_get($user_id);
    $key   = xi_cart_key($pid, $spec);
    $total = (isset($cart[$key]) ? (int) $cart[$key] : 0) + $qty;
    $stock = xi_product_stock($pid, $spec);
    if ($stock === 0) {
        xi_shop_json(false, __('该商品已售罄', 'xi'));
    }
    if ($stock > 0 && $total > $stock) {
        xi_shop_json(false, sprintf(__('库存仅剩 %d 件', 'xi'), $stock));
    }
    unset($cart[$key]);
    $cart[$key] = min(99, $total);
    xi_cart_save($user_id, $cart);
    xi_shop_json(true, __('已加入购物车', 'xi'), array('count' => xi_shop_cart_count($user_id)));
}
add_action('wp_ajax_xi_cart_add', 'xi_ajax_cart_add');

function xi_ajax_cart_update()
{
    $user_id = xi_shop_ajax_check();
    $cart    = xi_cart_get($user_id);
    $key     = isset($_POST['key']) ? preg_replace('/[^0-9:]/', '', wp_unslash($_POST['key'])) : '';
    $qty     = isset($_POST['qty']) ? (int) $_POST['qty'] : 0;
    if (!isset($cart[$key])) {
        xi_shop_json(false, __('商品不在购物车中', 'xi'));
    }
    if ($qty <= 0) {
        unset($cart[$key]);
    } else {
        list($pid, $spec) = array_map('intval', explode(':', $key));
        $stock = xi_product_stock($pid, $spec);
        if ($stock >= 0 && $qty > $stock) {
            xi_shop_json(false, $stock ? sprintf(__('库存仅剩 %d 件', 'xi'), $stock) : __('该商品已售罄', 'xi'), array('qty' => (int) $cart[$key]));
        }
        $cart[$key] = min(99, $qty);
    }
    xi_cart_save($user_id, $cart);
    xi_shop_json(true, '', array('count' => xi_shop_cart_count($user_id)));
}
add_action('wp_ajax_xi_cart_update', 'xi_ajax_cart_update');

function xi_ajax_cart_remove()
{
    $user_id = xi_shop_ajax_check();
    $cart    = xi_cart_get($user_id);
    $keys    = isset($_POST['keys']) ? array_map(function ($k) {
        return preg_replace('/[^0-9:]/', '', $k);
    }, explode(',', wp_unslash($_POST['keys']))) : array();
    foreach ($keys as $key) {
        unset($cart[$key]);
    }
    xi_cart_save($user_id, $cart);
    xi_shop_json(true, __('已移除', 'xi'), array('count' => xi_shop_cart_count($user_id)));
}
add_action('wp_ajax_xi_cart_remove', 'xi_ajax_cart_remove');

/**
 * 解析结算商品：items=商品ID:规格:数量,... 或 cart=购物车 key 列表
 */
function xi_shop_parse_lines($user_id, $raw_items, $raw_cart)
{
    $lines = array();
    if ($raw_cart !== '') {
        $cart = xi_cart_get($user_id);
        foreach (explode(',', $raw_cart) as $key) {
            $key = preg_replace('/[^0-9:]/', '', $key);
            if (isset($cart[$key])) {
                list($pid, $spec) = array_map('intval', explode(':', $key));
                $lines[] = array('pid' => $pid, 'spec' => $spec, 'qty' => (int) $cart[$key]);
            }
        }
    } else {
        foreach (explode(',', $raw_items) as $part) {
            $p = array_map('intval', explode(':', $part . '::'));
            if ($p[0] > 0) {
                $lines[] = array('pid' => $p[0], 'spec' => $p[1], 'qty' => max(1, $p[2]));
            }
        }
    }
    return array_slice($lines, 0, 30);
}

/**
 * 结算试算：使用 / 取消优惠码、积分抵扣时重新计算应付金额
 */
function xi_ajax_shop_quote()
{
    $user_id = xi_shop_ajax_check();
    $lines   = xi_shop_parse_lines($user_id, isset($_POST['items']) ? wp_unslash($_POST['items']) : '', isset($_POST['cart']) ? wp_unslash($_POST['cart']) : '');
    $built   = xi_shop_build_items($user_id, $lines);
    if (is_wp_error($built)) {
        xi_shop_json(false, $built->get_error_message());
    }
    $coupon = isset($_POST['coupon']) ? sanitize_text_field(wp_unslash($_POST['coupon'])) : '';
    if ($coupon !== '' && $built['price_type'] !== 'money') {
        xi_shop_json(false, __('该订单不能使用优惠码', 'xi'));
    }
    $quote = xi_shop_quote($user_id, $built, array('coupon' => $coupon, 'points' => !empty($_POST['points'])));
    if (is_wp_error($quote)) {
        xi_shop_json(false, $quote->get_error_message(), array('coupon_error' => true));
    }
    xi_shop_json(true, $quote['coupon'] && $coupon !== '' ? sprintf(__('已使用优惠码：%s', 'xi'), $quote['coupon']['text']) : '', array('quote' => xi_shop_quote_json($quote)));
}
add_action('wp_ajax_xi_shop_quote', 'xi_ajax_shop_quote');
add_action('wp_ajax_xi_shop_coupon', 'xi_ajax_shop_quote');

/**
 * 付款结果：在线支付返回扫码 / 跳转参数，其余直接到账
 */
function xi_shop_pay_response($order, $res)
{
    if (is_wp_error($res)) {
        xi_shop_json(false, $res->get_error_message(), array('order_no' => $order->order_no, 'redirect' => xi_uc_url('shop')));
    }
    if (!empty($res['paid'])) {
        $fresh = xi_shop_get_order($order->order_no);
        xi_shop_json(true, (int) $fresh->status === 3 ? __('支付成功，商品已发货', 'xi') : __('支付成功，等待商家发货', 'xi'), array('paid' => true, 'redirect' => add_query_arg('order', $order->order_no, xi_uc_url('shop'))));
    }
    xi_shop_json(true, '', array_merge($res, array(
        'order_no' => $order->order_no,
        'title'    => $order->title,
        'done'     => add_query_arg('order', $order->order_no, xi_uc_url('shop')),
    )));
}

function xi_ajax_shop_submit()
{
    $user_id = xi_shop_ajax_check();
    if (function_exists('xi_ban_notice') && ($ban = xi_ban_notice($user_id))) {
        xi_shop_json(false, $ban);
    }
    $raw_cart = isset($_POST['cart']) ? wp_unslash($_POST['cart']) : '';
    $lines    = xi_shop_parse_lines($user_id, isset($_POST['items']) ? wp_unslash($_POST['items']) : '', $raw_cart);
    $order    = xi_shop_order_create($user_id, array(
        'lines'   => $lines,
        'address' => isset($_POST['address']) ? sanitize_key(wp_unslash($_POST['address'])) : '',
        'contact' => isset($_POST['contact']) ? sanitize_text_field(wp_unslash($_POST['contact'])) : '',
        'remark'  => isset($_POST['remark']) ? sanitize_textarea_field(wp_unslash($_POST['remark'])) : '',
        'coupon'  => isset($_POST['coupon']) ? sanitize_text_field(wp_unslash($_POST['coupon'])) : '',
        'points'  => !empty($_POST['points']),
    ));
    if (is_wp_error($order)) {
        xi_shop_json(false, $order->get_error_message());
    }
    $pay = isset($_POST['pay']) ? sanitize_text_field(wp_unslash($_POST['pay'])) : '';
    if (!isset(xi_shop_pay_options($order)[$pay])) {
        // 付款方式不可用：删掉刚建的订单，避免留下无法支付的空单
        global $wpdb;
        $wpdb->delete(xi_shop_orders_table(), array('order_no' => $order->order_no, 'status' => 0));
        xi_shop_json(false, __('该付款方式暂不可用，请重新选择', 'xi'));
    }
    // 下单后从购物车移除这些商品
    if ($raw_cart !== '') {
        $cart = xi_cart_get($user_id);
        foreach ($lines as $line) {
            unset($cart[xi_cart_key($line['pid'], $line['spec'])]);
        }
        xi_cart_save($user_id, $cart);
    }
    xi_shop_pay_response($order, xi_shop_order_pay($order, $pay));
}
add_action('wp_ajax_xi_shop_submit', 'xi_ajax_shop_submit');

/**
 * 取自己的订单
 */
function xi_shop_ajax_order($user_id)
{
    $order = xi_shop_get_order(isset($_POST['order_no']) ? sanitize_text_field(wp_unslash($_POST['order_no'])) : '');
    if (!$order || (int) $order->user_id !== $user_id) {
        xi_shop_json(false, __('订单不存在', 'xi'));
    }
    return $order;
}

/**
 * 待付款订单继续付款
 */
function xi_ajax_shop_pay()
{
    $user_id = xi_shop_ajax_check();
    $order   = xi_shop_ajax_order($user_id);
    xi_shop_close_expired_for($user_id);
    $order = xi_shop_get_order($order->order_no);
    if ((int) $order->status !== 0) {
        xi_shop_json(false, __('订单已关闭或已付款', 'xi'));
    }
    $pay = isset($_POST['pay']) ? sanitize_text_field(wp_unslash($_POST['pay'])) : '';
    xi_shop_pay_response($order, xi_shop_order_pay($order, $pay));
}
add_action('wp_ajax_xi_shop_pay', 'xi_ajax_shop_pay');

/**
 * 订单操作：取消、确认收货、申请售后、撤销售后
 */
function xi_ajax_shop_order_action()
{
    $user_id = xi_shop_ajax_check();
    $order   = xi_shop_ajax_order($user_id);
    $do      = isset($_POST['do']) ? sanitize_key($_POST['do']) : '';
    switch ($do) {
        case 'cancel':
            $ok = xi_shop_order_close($order->order_no, __('用户取消', 'xi'));
            xi_shop_json($ok, $ok ? __('订单已取消', 'xi') : __('订单状态已变化，请刷新页面', 'xi'), array('reload' => $ok));
            break;
        case 'confirm':
            $ok = xi_shop_order_confirm($order->order_no);
            xi_shop_json($ok, $ok ? __('已确认收货', 'xi') : __('订单状态已变化，请刷新页面', 'xi'), array('reload' => $ok));
            break;
        case 'refund':
            $res = xi_shop_refund_apply($order, isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '', isset($_POST['type']) ? sanitize_key($_POST['type']) : 'refund');
            xi_shop_json(!is_wp_error($res), is_wp_error($res) ? $res->get_error_message() : __('售后申请已提交，请等待商家处理', 'xi'), array('reload' => !is_wp_error($res)));
            break;
        case 'refund_cancel':
            $res = xi_shop_refund_cancel($order);
            xi_shop_json(!is_wp_error($res), is_wp_error($res) ? $res->get_error_message() : __('已撤销售后申请', 'xi'), array('reload' => !is_wp_error($res)));
            break;
        case 'return_ship':
            $res = xi_shop_refund_return_ship($order, isset($_POST['company']) ? sanitize_text_field(wp_unslash($_POST['company'])) : '', isset($_POST['number']) ? sanitize_text_field(wp_unslash($_POST['number'])) : '');
            xi_shop_json(!is_wp_error($res), is_wp_error($res) ? $res->get_error_message() : __('退货信息已提交，商家收货后会尽快退款', 'xi'), array('reload' => !is_wp_error($res)));
            break;
    }
    xi_shop_json(false, __('参数错误', 'xi'));
}
add_action('wp_ajax_xi_shop_order_action', 'xi_ajax_shop_order_action');

function xi_ajax_shop_address_save()
{
    $user_id = xi_shop_ajax_check();
    $addr    = array(
        'name'    => isset($_POST['name']) ? mb_substr(sanitize_text_field(wp_unslash($_POST['name'])), 0, 20) : '',
        'phone'   => isset($_POST['phone']) ? preg_replace('/[^0-9+\- ]/', '', wp_unslash($_POST['phone'])) : '',
        'region'  => isset($_POST['region']) ? mb_substr(sanitize_text_field(wp_unslash($_POST['region'])), 0, 60) : '',
        'detail'  => isset($_POST['detail']) ? mb_substr(sanitize_text_field(wp_unslash($_POST['detail'])), 0, 120) : '',
        'default' => !empty($_POST['default']) ? 1 : 0,
    );
    if ($addr['name'] === '' || strlen($addr['phone']) < 6 || $addr['region'] === '' || $addr['detail'] === '') {
        xi_shop_json(false, __('请完整填写收件人、电话、所在地区和详细地址', 'xi'));
    }
    $list = xi_shop_addresses($user_id);
    $id   = isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';
    if ($id === '' || !isset($list[$id])) {
        if (count($list) >= 20) {
            xi_shop_json(false, __('最多保存 20 个收货地址', 'xi'));
        }
        $id = 'a' . strtolower(wp_generate_password(8, false));
    }
    if ($addr['default'] || !$list) {
        foreach ($list as $k => $v) {
            $list[$k]['default'] = 0;
        }
        $addr['default'] = 1;
    }
    $addr['id'] = $id;
    $list[$id]  = $addr;
    update_user_meta($user_id, 'xi_addresses', $list);
    xi_shop_json(true, __('地址已保存', 'xi'), array('id' => $id, 'address' => $addr, 'text' => xi_shop_address_text($addr)));
}
add_action('wp_ajax_xi_shop_address_save', 'xi_ajax_shop_address_save');

function xi_ajax_shop_address_delete()
{
    $user_id = xi_shop_ajax_check();
    $list    = xi_shop_addresses($user_id);
    $id      = isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';
    if (!isset($list[$id])) {
        xi_shop_json(false, __('地址不存在', 'xi'));
    }
    $was_default = !empty($list[$id]['default']);
    unset($list[$id]);
    if ($was_default && $list) {
        reset($list);
        $list[key($list)]['default'] = 1;
    }
    update_user_meta($user_id, 'xi_addresses', $list);
    xi_shop_json(true, __('地址已删除', 'xi'), array('reload' => true));
}
add_action('wp_ajax_xi_shop_address_delete', 'xi_ajax_shop_address_delete');
