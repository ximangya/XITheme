<?php
/**
 * 商城结算计算：满减活动 → 优惠码 → 积分抵扣 → 运费
 *
 * 确认订单页的展示、试算接口和创建订单共用 xi_shop_quote()，保证页面显示的金额与实际扣款一致
 * 积分商品只按积分计价：不参与满减、优惠码、积分抵扣，也不收运费
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------- 满减活动 ---------- */

/**
 * 当前生效的满减活动
 *
 * @return array|null name, tiers（按门槛升序，每项 min、minus）, end（真实时间戳，0 为不限）
 */
function xi_shop_promo()
{
    static $promo = false;
    if ($promo !== false) {
        return $promo;
    }
    $promo = null;
    if (!_xz('shop_promo_s', false)) {
        return null;
    }
    $tiers = array();
    foreach (preg_split('/[\r\n,，;；]+/u', (string) _xz('shop_promo_tiers', '')) as $line) {
        if (preg_match('/(\d+(?:\.\d+)?)\D+(\d+(?:\.\d+)?)/', $line, $m) && (float) $m[1] > 0 && (float) $m[2] > 0 && (float) $m[2] < (float) $m[1]) {
            $tiers[] = array('min' => round((float) $m[1], 2), 'minus' => round((float) $m[2], 2));
        }
    }
    if (!$tiers) {
        return null;
    }
    usort($tiers, function ($a, $b) {
        return $a['min'] < $b['min'] ? -1 : 1;
    });
    $now   = current_time('timestamp');
    $start = _xz('shop_promo_start', '') ? strtotime(_xz('shop_promo_start') . ' 00:00:00') : 0;
    $end   = _xz('shop_promo_end', '') ? strtotime(_xz('shop_promo_end') . ' 23:59:59') : 0;
    if (($start && $now < $start) || ($end && $now > $end)) {
        return null;
    }
    $name  = trim((string) _xz('shop_promo_name', ''));
    $promo = array(
        'name'  => $name !== '' ? $name : __('满减活动', 'xi'),
        'tiers' => $tiers,
        'end'   => $end ? $end - (int) (get_option('gmt_offset') * HOUR_IN_SECONDS) : 0,
    );
    return $promo;
}

function xi_shop_promo_text()
{
    $promo = xi_shop_promo();
    if (!$promo) {
        return '';
    }
    $parts = array();
    foreach ($promo['tiers'] as $t) {
        $parts[] = sprintf(__('满%1$s减%2$s', 'xi'), xi_shop_num($t['min']), xi_shop_num($t['minus']));
    }
    return implode(__('，', 'xi'), $parts);
}

/**
 * 去掉多余的 0：10.00 → 10，9.50 → 9.5
 */
function xi_shop_num($n)
{
    return rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
}

/**
 * 金额达到的最高档
 */
function xi_shop_promo_hit($amount)
{
    $promo = xi_shop_promo();
    $hit   = null;
    if ($promo) {
        foreach ($promo['tiers'] as $t) {
            if ($amount >= $t['min'] && (!$hit || $t['minus'] > $hit['minus'])) {
                $hit = $t;
            }
        }
    }
    return $hit;
}

/**
 * 下一档提示：再买 ¥X 可减 ¥Y
 */
function xi_shop_promo_next($amount)
{
    $promo = xi_shop_promo();
    if (!$promo) {
        return '';
    }
    foreach ($promo['tiers'] as $t) {
        if ($amount < $t['min']) {
            return sprintf(__('再买 ¥%1$s 可减 ¥%2$s', 'xi'), xi_money($t['min'] - $amount), xi_shop_num($t['minus']));
        }
    }
    return '';
}

/* ---------- 积分抵扣 ---------- */

function xi_shop_points_deduct_on()
{
    return (bool) _xz('shop_points_deduct_s', false) && xi_points_enabled();
}

/**
 * 多少积分抵 1 元
 */
function xi_shop_points_rate()
{
    return max(1, (int) _xz('shop_points_rate', 100));
}

/**
 * 可抵扣：受最高比例、用户积分余额限制，且至少保留 0.01 元实付
 *
 * @return array points, value
 */
function xi_shop_points_deduct_calc($user_id, $payable)
{
    if (!xi_shop_points_deduct_on() || $payable <= 0.01) {
        return array('points' => 0, 'value' => 0);
    }
    $rate  = xi_shop_points_rate();
    $pct   = min(100, max(1, (int) _xz('shop_points_max_pct', 20)));
    $max   = floor($payable * $pct) / 100;
    $max   = min($max, round($payable - 0.01, 2));
    $own   = floor(xi_get_points($user_id) / $rate * 100) / 100;
    $value = max(0, min($max, $own));
    $value = floor($value * 100) / 100;
    if ($value <= 0) {
        return array('points' => 0, 'value' => 0);
    }
    return array('points' => (int) ceil($value * $rate), 'value' => $value);
}

/* ---------- 运费 ---------- */

/**
 * 实物商品运费：同一订单取各商品运费的最高值（合并发一个包裹）
 * 达到包邮门槛、会员包邮时免运费
 */
function xi_shop_shipping_fee($user_id, $built, $goods_after_discount)
{
    if (!$built['need_address'] || $built['price_type'] !== 'money') {
        return 0;
    }
    $fee = 0;
    foreach ($built['items'] as $item) {
        if ($item['delivery'] === 'physical') {
            $fee = max($fee, (float) xi_product_data($item['pid'])['shipping_fee']);
        }
    }
    $free_over = (float) _xz('shop_free_shipping', 0);
    if ($fee <= 0 || ($free_over > 0 && $goods_after_discount >= $free_over)) {
        return 0;
    }
    if (_xz('shop_vip_free_shipping', false) && function_exists('xi_get_vip_level') && xi_get_vip_level($user_id)) {
        return 0;
    }
    return round($fee, 2);
}

/* ---------- 结算 ---------- */

/**
 * 计算订单应付
 *
 * @param array $built xi_shop_build_items() 的结果
 * @param array $opts  coupon 优惠码, points 是否使用积分抵扣
 * @return array|WP_Error goods, promo, coupon, points, shipping, discount, total
 */
function xi_shop_quote($user_id, $built, $opts = array())
{
    $goods = $built['goods_total'];
    $quote = array(
        'type'     => $built['price_type'],
        'goods'    => $goods,
        'promo'    => null,
        'coupon'   => null,
        'points'   => array('points' => 0, 'value' => 0),
        'points_available' => array('points' => 0, 'value' => 0),
        'shipping' => 0,
        'discount' => 0,
        'total'    => $goods,
    );
    if ($built['price_type'] !== 'money') {
        return $quote;
    }

    $pay = $goods;
    $hit = $pay > 0 ? xi_shop_promo_hit($pay) : null;
    if ($hit) {
        $minus          = min($hit['minus'], max(0, round($pay - 0.01, 2)));
        $promo          = xi_shop_promo();
        $quote['promo'] = array('text' => sprintf(__('%1$s：满%2$s减%3$s', 'xi'), $promo['name'], xi_shop_num($hit['min']), xi_shop_num($hit['minus'])), 'amount' => $minus);
        $pay            = round($pay - $minus, 2);
    }

    if (!empty($opts['coupon']) && $pay > 0) {
        $c = xi_shop_coupon_check($opts['coupon'], $pay, $user_id);
        if (is_wp_error($c)) {
            return $c;
        }
        $quote['coupon'] = array('code' => $c['code'], 'text' => $c['text'], 'amount' => $c['discount']);
        $pay             = round($pay - $c['discount'], 2);
    }

    $quote['points_available'] = xi_shop_points_deduct_calc($user_id, $pay);
    if (!empty($opts['points']) && $quote['points_available']['value'] > 0) {
        $quote['points'] = $quote['points_available'];
        $pay             = round($pay - $quote['points']['value'], 2);
    }

    $quote['shipping'] = xi_shop_shipping_fee($user_id, $built, $pay);
    $quote['discount'] = round($goods - $pay, 2);
    $quote['total']    = max(0, round($pay + $quote['shipping'], 2));
    return $quote;
}

/**
 * 给前端的试算结果
 */
function xi_shop_quote_json($quote)
{
    return array(
        'goods'     => xi_money($quote['goods']),
        'promo'     => $quote['promo'] ? array('text' => $quote['promo']['text'], 'amount' => xi_money($quote['promo']['amount'])) : null,
        'coupon'    => $quote['coupon'] ? array('text' => $quote['coupon']['text'], 'amount' => xi_money($quote['coupon']['amount'])) : null,
        'points'    => $quote['points']['points'] ? array('points' => $quote['points']['points'], 'amount' => xi_money($quote['points']['value'])) : null,
        'available' => $quote['points_available']['points'] ? array('points' => $quote['points_available']['points'], 'amount' => xi_money($quote['points_available']['value'])) : null,
        'shipping'  => xi_money($quote['shipping']),
        'total'     => xi_price_text($quote['total'], $quote['type']),
    );
}

/**
 * 订单的优惠与运费明细（详情展示用）
 */
function xi_shop_order_extra($order)
{
    $extra = isset($order->extra) && is_array($order->extra) ? $order->extra : array();
    return wp_parse_args($extra, array('promo' => null, 'coupon' => null, 'points' => array('points' => 0, 'value' => 0), 'shipping' => 0, 'points_released' => 0));
}

/**
 * 归还订单占用的积分抵扣（关闭订单、退款时），只归还一次
 */
function xi_shop_release_points($order, $note)
{
    $extra = xi_shop_order_extra($order);
    $used  = (int) $extra['points']['points'];
    if ($used <= 0 || !empty($extra['points_released'])) {
        return;
    }
    $extra['points_released'] = 1;
    global $wpdb;
    $wpdb->update(xi_shop_orders_table(), array('extra' => wp_json_encode($extra)), array('order_no' => $order->order_no));
    xi_change_points((int) $order->user_id, $used, $note);
}
