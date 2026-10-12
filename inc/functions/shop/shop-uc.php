<?php
/**
 * 用户中心：商城订单、收货地址
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_shop_uc_tabs($tabs, $user)
{
    if (!xi_shop_enabled()) {
        return $tabs;
    }
    $tabs['shop']    = array(__('商城订单', 'xi'), 'bag');
    $tabs['address'] = array(__('收货地址', 'xi'), 'pin');
    return $tabs;
}
add_filter('xi_uc_tabs', 'xi_shop_uc_tabs', 10, 2);

/**
 * 发货内容：链接可点击，卡密带复制按钮
 */
function xi_shop_delivered_html($item)
{
    if (empty($item['delivered'])) {
        return '';
    }
    $html = '<div class="xi-shop-delivered">';
    foreach ((array) $item['delivered'] as $line) {
        $html .= '<div class="xi-shop-delivered-row"><span>' . make_clickable(nl2br(esc_html($line))) . '</span>'
            . '<button type="button" class="xi-btn xi-btn-sm" data-xi-copy="' . esc_attr($line) . '">' . xi_icon('copy') . esc_html__('复制', 'xi') . '</button></div>';
    }
    return $html . '</div>';
}

/**
 * 用户在某商品下已付款的订单明细（商品页「你已购买」）
 *
 * @return array 每项 order_no, status, time, item
 */
function xi_shop_user_bought_items($user_id, $product_id)
{
    global $wpdb;
    if (!$user_id || get_option('xi_shop_db_ver') !== XI_SHOP_DB_VER) {
        return array();
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        'SELECT order_no, status, paid_at, items FROM ' . xi_shop_orders_table() . ' WHERE user_id = %d AND status IN (1,2,3,5) AND items LIKE %s ORDER BY id DESC LIMIT 10',
        $user_id,
        '%"pid":' . (int) $product_id . ',%'
    ));
    $out = array();
    foreach ($rows as $row) {
        foreach ((array) json_decode($row->items, true) as $item) {
            if ((int) $item['pid'] === (int) $product_id) {
                $out[] = array('order_no' => $row->order_no, 'status' => (int) $row->status, 'time' => $row->paid_at, 'item' => $item);
            }
        }
    }
    return $out;
}

/**
 * 快递查询链接（快递 100）
 */
function xi_shop_track_url($number)
{
    return 'https://www.kuaidi100.com/chaxun?nu=' . rawurlencode($number);
}

/* ---------- 联系商家 ---------- */

function xi_shop_contact_enabled()
{
    return (bool) (trim((string) _xz('shop_contact_qq', '')) || _xz('shop_contact_wechat', '') || trim((string) _xz('shop_contact_text', '')));
}

function xi_shop_contact_dialog()
{
    static $done = false;
    if ($done || !xi_shop_contact_enabled()) {
        return;
    }
    $done   = true;
    $qq     = preg_replace('/\D/', '', (string) _xz('shop_contact_qq', ''));
    $wechat = _xz('shop_contact_wechat', '');
    $wechat = is_array($wechat) ? (isset($wechat['url']) ? $wechat['url'] : '') : $wechat;
    $text   = trim((string) _xz('shop_contact_text', ''));
    ?>
    <div class="xi-modal" id="xi-contact" aria-hidden="true">
        <div class="xi-modal-mask" data-modal-close></div>
        <div class="xi-modal-box xi-glass xi-shop-dialog xi-shop-contact" role="dialog" aria-modal="true">
            <button type="button" class="xi-btn-icon xi-modal-close" data-modal-close aria-label="<?php esc_attr_e('关闭', 'xi'); ?>"><?php echo xi_icon('close'); ?></button>
            <h3 class="xi-modal-title"><?php esc_html_e('联系商家', 'xi'); ?></h3>
            <?php if ($qq) : ?>
                <a class="xi-btn" href="<?php echo esc_url('https://wpa.qq.com/msgrd?v=3&uin=' . $qq . '&site=qq&menu=yes'); ?>" target="_blank" rel="noopener nofollow"><?php echo function_exists('xi_brand_icon') ? xi_brand_icon('qq') : ''; ?><?php printf(esc_html__('QQ：%s', 'xi'), esc_html($qq)); ?></a>
            <?php endif; ?>
            <?php if ($wechat) : ?>
                <figure><img src="<?php echo esc_url($wechat); ?>" alt="<?php esc_attr_e('客服微信', 'xi'); ?>" loading="lazy"><figcaption><?php esc_html_e('微信扫码添加客服', 'xi'); ?></figcaption></figure>
            <?php endif; ?>
            <?php if ($text) : ?>
                <p class="xi-modal-tip"><?php echo nl2br(esc_html($text)); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function xi_shop_contact_footer()
{
    if (xi_shop_enabled() && (xi_is_shop_page() || (function_exists('xi_is_user_center') && xi_is_user_center()))) {
        xi_shop_contact_dialog();
    }
}
add_action('wp_footer', 'xi_shop_contact_footer', 7);

/**
 * 订单卡片
 */
function xi_shop_order_card($o, $open = false)
{
    $st      = (int) $o->status;
    $options = $st === 0 ? xi_shop_pay_options($o) : array();
    echo '<article class="xi-shop-order' . ($open ? ' is-focus' : '') . '" id="order-' . esc_attr($o->order_no) . '">';
    echo '<header><span>' . esc_html(mysql2date('Y-m-d H:i', $o->created_at)) . ' · ' . esc_html__('订单号', 'xi') . ' ' . esc_html($o->order_no) . '</span>';
    echo '<b class="xi-shop-st is-' . $st . '">' . esc_html(xi_shop_status_name($st));
    if ($st === 0) {
        echo ' <small data-xi-countdown="' . (int) xi_shop_pay_deadline($o) . '" data-prefix="' . esc_attr__('剩余', 'xi') . '"></small>';
    }
    echo '</b></header>';

    foreach ($o->items as $it) {
        $link = get_post_status($it['pid']) === 'publish' ? get_permalink($it['pid']) : '';
        echo '<div class="xi-shop-order-item">';
        echo $it['thumb'] ? '<img src="' . esc_url($it['thumb']) . '" alt="" loading="lazy">' : '<span class="xi-shop-thumb-empty">' . xi_icon('bag') . '</span>';
        echo '<div class="xi-shop-order-main"><b>' . ($link ? '<a href="' . esc_url($link) . '">' . esc_html($it['title']) . '</a>' : esc_html($it['title'])) . '</b>';
        echo '<small>' . esc_html(($it['spec_name'] ? $it['spec_name'] . ' · ' : '') . xi_price_text($it['price'], $o->price_type) . ' × ' . (int) $it['qty']) . '</small>';
        if (in_array($st, array(1, 2, 3, 5), true)) {
            echo xi_shop_delivered_html($it);
            if (!empty($it['short'])) {
                echo '<em class="xi-shop-tip">' . esc_html__('部分卡密待商家补发', 'xi') . '</em>';
            }
        }
        echo '</div>';
        if ($st === 3 && $link && xi_shop_can_review($it['pid'], (int) $o->user_id)) {
            echo '<a class="xi-btn xi-btn-sm" href="' . esc_url($link . '#respond') . '">' . esc_html__('评价', 'xi') . '</a>';
        }
        echo '</div>';
    }

    echo '<div class="xi-shop-order-info">';
    if ($o->address) {
        echo '<p>' . xi_icon('pin') . esc_html(xi_shop_address_text($o->address)) . '</p>';
    }
    if (!empty($o->express['number'])) {
        echo '<p>' . xi_icon('truck') . esc_html($o->express['company'] . ' ' . $o->express['number']) . ' <button type="button" class="xi-link-btn" data-xi-copy="' . esc_attr($o->express['number']) . '">' . esc_html__('复制单号', 'xi') . '</button> <a class="xi-link-btn" href="' . esc_url(xi_shop_track_url($o->express['number'])) . '" target="_blank" rel="noopener nofollow">' . esc_html__('查物流', 'xi') . '</a></p>';
    }
    if ($o->remark) {
        echo '<p>' . xi_icon('comment') . esc_html($o->remark) . '</p>';
    }
    if (!empty($o->refund['reason']) && in_array($st, array(5, 6), true)) {
        echo '<p>' . xi_icon('undo') . esc_html(sprintf(__('售后原因：%s', 'xi'), $o->refund['reason'])) . '</p>';
    }
    if (!empty($o->refund['reply'])) {
        echo '<p>' . xi_icon('info') . esc_html(sprintf(__('商家回复：%s', 'xi'), $o->refund['reply'])) . '</p>';
    }
    $stage = $st === 5 && isset($o->refund['stage']) ? $o->refund['stage'] : '';
    if ($stage === 'returning') {
        echo '<p class="is-strong">' . xi_icon('box') . esc_html__('商家已同意退货，请寄回商品后填写退货快递单号', 'xi') . (!empty($o->refund['address']) ? '<br>' . esc_html__('退货地址：', 'xi') . nl2br(esc_html($o->refund['address'])) : '') . '</p>';
    } elseif ($stage === 'returned' && !empty($o->refund['return_express']['number'])) {
        echo '<p>' . xi_icon('box') . esc_html(sprintf(__('已寄回：%1$s %2$s，等待商家确认收货', 'xi'), $o->refund['return_express']['company'], $o->refund['return_express']['number'])) . '</p>';
    }
    $ex    = xi_shop_order_extra($o);
    $parts = array();
    if ($ex['promo']) {
        $parts[] = $ex['promo']['text'] . ' -¥' . xi_money($ex['promo']['amount']);
    }
    if ($ex['coupon']) {
        $parts[] = sprintf(__('优惠码 -¥%s', 'xi'), xi_money($ex['coupon']['amount']));
    }
    if (!empty($ex['points']['points'])) {
        $parts[] = sprintf(__('%1$d %2$s抵扣 -¥%3$s', 'xi'), $ex['points']['points'], xi_points_name(), xi_money($ex['points']['value'])) . (!empty($ex['points_released']) ? __('（已退回）', 'xi') : '');
    }
    if ((float) $ex['shipping'] > 0) {
        $parts[] = sprintf(__('运费 +¥%s', 'xi'), xi_money($ex['shipping']));
    }
    if ($parts) {
        echo '<p>' . xi_icon('tag') . esc_html(implode('　', $parts)) . '</p>';
    }
    if ($o->note && $st === 4) {
        echo '<p>' . xi_icon('info') . esc_html($o->note) . '</p>';
    }
    echo '</div>';

    echo '<footer><span class="xi-shop-order-total">';
    if ((float) $o->discount > 0) {
        echo '<small>' . esc_html(sprintf(__('已优惠 ¥%s', 'xi'), xi_money($o->discount))) . '</small>';
    }
    echo esc_html__('实付', 'xi') . ' <b>' . esc_html(xi_price_text($o->total, $o->price_type)) . '</b></span>';
    echo '<span class="xi-shop-order-acts" data-order="' . esc_attr($o->order_no) . '">';
    if ($st === 0) {
        echo '<button type="button" class="xi-btn xi-btn-sm" data-xi-order-do="cancel">' . esc_html__('取消订单', 'xi') . '</button>';
        if ($options) {
            echo '<select class="xi-shop-pay-select" aria-label="' . esc_attr__('付款方式', 'xi') . '">';
            foreach ($options as $k => $label) {
                echo '<option value="' . esc_attr($k) . '">' . esc_html($label) . '</option>';
            }
            echo '</select><button type="button" class="xi-btn xi-btn-sm xi-btn-accent" data-xi-order-pay>' . esc_html__('去付款', 'xi') . '</button>';
        }
    } elseif ($st === 2) {
        echo '<button type="button" class="xi-btn xi-btn-sm xi-btn-accent" data-xi-order-do="confirm">' . esc_html__('确认收货', 'xi') . '</button>';
    } elseif ($st === 5) {
        if ($stage === 'returning') {
            echo '<button type="button" class="xi-btn xi-btn-sm xi-btn-accent" data-xi-return-ship>' . esc_html__('填写退货快递', 'xi') . '</button>';
        }
        if ($stage !== 'returned') {
            echo '<button type="button" class="xi-btn xi-btn-sm" data-xi-order-do="refund_cancel">' . esc_html__('撤销售后', 'xi') . '</button>';
        }
    }
    if (xi_shop_can_refund($o)) {
        $can_return = in_array($st, array(2, 3), true) && xi_shop_order_has_physical($o);
        echo '<button type="button" class="xi-btn xi-btn-sm" data-xi-order-refund="' . ($can_return ? 'return' : 'refund') . '">' . esc_html__('申请售后', 'xi') . '</button>';
    }
    if (in_array($st, array(3, 4, 6), true) && count($o->items) === 1 && get_post_status($o->items[0]['pid']) === 'publish') {
        echo '<a class="xi-btn xi-btn-sm" href="' . esc_url(get_permalink($o->items[0]['pid'])) . '">' . esc_html__('再次购买', 'xi') . '</a>';
    }
    if ($st !== 0 && xi_shop_contact_enabled()) {
        echo '<button type="button" class="xi-btn xi-btn-sm" data-xi-modal="xi-contact">' . esc_html__('联系商家', 'xi') . '</button>';
    }
    echo '</span></footer>';
    echo '</article>';
}

function xi_shop_uc_panels($user, $active)
{
    if (!xi_shop_enabled()) {
        return;
    }
    xi_shop_close_expired_for($user->ID);
    $filter = isset($_GET['st']) && $_GET['st'] !== '' ? (int) $_GET['st'] : null;
    $paged  = isset($_GET['pg']) ? max(1, (int) $_GET['pg']) : 1;
    $per    = 10;
    $focus  = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : '';
    $counts = xi_shop_user_order_counts($user->ID);
    $orders = xi_shop_user_orders($user->ID, $filter, $per + 1, ($paged - 1) * $per);
    $more   = count($orders) > $per;
    $orders = array_slice($orders, 0, $per);
    $base   = xi_uc_url('shop');
    ?>
    <section class="xi-uc-panel<?php echo $active === 'shop' ? ' is-active' : ''; ?>" data-uc-panel="shop">
        <div class="xi-card xi-uc-box">
            <div class="xi-uc-box-head">
                <h3 class="xi-section-title"><?php esc_html_e('商城订单', 'xi'); ?></h3>
                <a class="xi-btn xi-btn-sm" href="<?php echo esc_url(xi_shop_url()); ?>"><?php echo xi_icon('bag'); ?><?php esc_html_e('去逛逛', 'xi'); ?></a>
            </div>
            <nav class="xi-shop-st-tabs">
                <a href="<?php echo esc_url($base); ?>" class="<?php echo $filter === null ? 'is-active' : ''; ?>"><?php esc_html_e('全部', 'xi'); ?></a>
                <?php foreach (array(0, 1, 2, 3, 5) as $k) : ?>
                    <a href="<?php echo esc_url(add_query_arg('st', $k, $base)); ?>" class="<?php echo $filter === $k ? 'is-active' : ''; ?>"><?php echo esc_html(xi_shop_status_name($k)); ?><?php if (!empty($counts[$k]) && $k !== 3) : ?><em><?php echo (int) $counts[$k]; ?></em><?php endif; ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if (!$orders) : ?>
                <p class="xi-uc-empty"><?php esc_html_e('暂无订单', 'xi'); ?></p>
            <?php else : ?>
                <div class="xi-shop-orders">
                    <?php foreach ($orders as $o) {
                        xi_shop_order_card($o, $o->order_no === $focus);
                    } ?>
                </div>
                <?php if ($paged > 1 || $more) : ?>
                    <div class="xi-uc-pager">
                        <?php if ($paged > 1) : ?><a class="xi-btn xi-btn-sm" href="<?php echo esc_url(add_query_arg('pg', $paged - 1)); ?>"><?php esc_html_e('上一页', 'xi'); ?></a><?php endif; ?>
                        <?php if ($more) : ?><a class="xi-btn xi-btn-sm" href="<?php echo esc_url(add_query_arg('pg', $paged + 1)); ?>"><?php esc_html_e('下一页', 'xi'); ?></a><?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="xi-uc-panel<?php echo $active === 'address' ? ' is-active' : ''; ?>" data-uc-panel="address">
        <div class="xi-card xi-uc-box">
            <div class="xi-uc-box-head">
                <h3 class="xi-section-title"><?php esc_html_e('收货地址', 'xi'); ?></h3>
                <button type="button" class="xi-btn xi-btn-sm xi-btn-accent" data-xi-address-edit><?php echo xi_icon('plus'); ?><?php esc_html_e('新增地址', 'xi'); ?></button>
            </div>
            <?php xi_shop_address_list($user->ID, false); ?>
        </div>
    </section>
    <?php
    xi_shop_address_dialog();
    xi_shop_refund_dialog();
}
add_action('xi_uc_panels', 'xi_shop_uc_panels', 10, 2);

/**
 * 地址列表；$pick 为 true 时是结算页的单选列表
 */
function xi_shop_address_list($user_id, $pick)
{
    $list = xi_shop_addresses($user_id);
    echo '<div class="xi-shop-addresses' . ($pick ? ' is-pick' : '') . '" data-xi-addresses>';
    if (!$list) {
        echo '<p class="xi-uc-empty">' . esc_html__('还没有收货地址', 'xi') . '</p>';
    }
    foreach ($list as $id => $a) {
        echo '<div class="xi-shop-address' . (!empty($a['default']) ? ' is-default' : '') . '">';
        if ($pick) {
            echo '<label><input type="radio" name="address" value="' . esc_attr($id) . '"' . checked(!empty($a['default']), true, false) . '>';
        } else {
            echo '<div>';
        }
        echo '<b>' . esc_html($a['name']) . ' <small>' . esc_html($a['phone']) . '</small>' . (!empty($a['default']) ? '<em>' . esc_html__('默认', 'xi') . '</em>' : '') . '</b>';
        echo '<span>' . esc_html($a['region'] . ' ' . $a['detail']) . '</span>';
        echo $pick ? '</label>' : '</div>';
        echo '<span class="xi-shop-address-acts"><button type="button" class="xi-link-btn" data-xi-address-edit="' . esc_attr(wp_json_encode($a)) . '">' . esc_html__('编辑', 'xi') . '</button>';
        if (!$pick) {
            echo '<button type="button" class="xi-link-btn is-danger" data-xi-address-del="' . esc_attr($id) . '">' . esc_html__('删除', 'xi') . '</button>';
        }
        echo '</span></div>';
    }
    echo '</div>';
}

function xi_shop_address_dialog()
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    ?>
    <div class="xi-modal" id="xi-address" aria-hidden="true">
        <div class="xi-modal-mask" data-modal-close></div>
        <form class="xi-modal-box xi-glass xi-shop-dialog" role="dialog" aria-modal="true" data-xi-address-form>
            <button type="button" class="xi-btn-icon xi-modal-close" data-modal-close aria-label="<?php esc_attr_e('关闭', 'xi'); ?>"><?php echo xi_icon('close'); ?></button>
            <h3 class="xi-modal-title"><?php esc_html_e('收货地址', 'xi'); ?></h3>
            <input type="hidden" name="id">
            <div class="xi-shop-form-row"><input type="text" name="name" maxlength="20" placeholder="<?php esc_attr_e('收件人', 'xi'); ?>" required><input type="tel" name="phone" maxlength="20" placeholder="<?php esc_attr_e('手机号', 'xi'); ?>" required></div>
            <input type="text" name="region" maxlength="60" placeholder="<?php esc_attr_e('所在地区，如：广东省 深圳市 南山区', 'xi'); ?>" required>
            <input type="text" name="detail" maxlength="120" placeholder="<?php esc_attr_e('详细地址：街道、门牌号', 'xi'); ?>" required>
            <label class="xi-shop-check"><input type="checkbox" name="default" value="1"> <?php esc_html_e('设为默认地址', 'xi'); ?></label>
            <button type="submit" class="xi-btn xi-btn-accent"><?php esc_html_e('保存地址', 'xi'); ?></button>
        </form>
    </div>
    <?php
}

function xi_shop_refund_dialog()
{
    ?>
    <div class="xi-modal" id="xi-refund" aria-hidden="true">
        <div class="xi-modal-mask" data-modal-close></div>
        <form class="xi-modal-box xi-glass xi-shop-dialog" role="dialog" aria-modal="true" data-xi-refund-form>
            <button type="button" class="xi-btn-icon xi-modal-close" data-modal-close aria-label="<?php esc_attr_e('关闭', 'xi'); ?>"><?php echo xi_icon('close'); ?></button>
            <h3 class="xi-modal-title"><?php esc_html_e('申请售后', 'xi'); ?></h3>
            <input type="hidden" name="order_no">
            <div class="xi-shop-refund-types" data-xi-refund-types>
                <?php foreach (xi_shop_refund_types() as $k => $label) : ?>
                    <label class="xi-shop-pay"><input type="radio" name="type" value="<?php echo esc_attr($k); ?>"<?php checked($k, 'refund'); ?>><span><?php echo esc_html($label); ?></span></label>
                <?php endforeach; ?>
            </div>
            <textarea name="reason" rows="4" maxlength="300" placeholder="<?php esc_attr_e('请描述问题和诉求，商家会尽快处理', 'xi'); ?>" required></textarea>
            <p class="xi-modal-tip"><?php esc_html_e('退款同意后，款项退回到你的站内余额（积分订单退回积分）', 'xi'); ?></p>
            <button type="submit" class="xi-btn xi-btn-accent"><?php esc_html_e('提交申请', 'xi'); ?></button>
        </form>
    </div>
    <div class="xi-modal" id="xi-return" aria-hidden="true">
        <div class="xi-modal-mask" data-modal-close></div>
        <form class="xi-modal-box xi-glass xi-shop-dialog" role="dialog" aria-modal="true" data-xi-return-form>
            <button type="button" class="xi-btn-icon xi-modal-close" data-modal-close aria-label="<?php esc_attr_e('关闭', 'xi'); ?>"><?php echo xi_icon('close'); ?></button>
            <h3 class="xi-modal-title"><?php esc_html_e('填写退货快递', 'xi'); ?></h3>
            <input type="hidden" name="order_no">
            <div class="xi-shop-form-row"><input type="text" name="company" maxlength="30" placeholder="<?php esc_attr_e('快递公司', 'xi'); ?>" required><input type="text" name="number" maxlength="40" placeholder="<?php esc_attr_e('快递单号', 'xi'); ?>" required></div>
            <button type="submit" class="xi-btn xi-btn-accent"><?php esc_html_e('提交', 'xi'); ?></button>
        </form>
    </div>
    <?php
}
