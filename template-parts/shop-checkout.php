<?php
/**
 * 确认订单：?items=商品ID:规格:数量,...（立即购买）或 ?cart=购物车 key,...（购物车结算）
 */

get_header();
$uid       = get_current_user_id();
$raw_items = isset($_GET['items']) ? sanitize_text_field(wp_unslash($_GET['items'])) : '';
$raw_cart  = isset($_GET['cart']) ? sanitize_text_field(wp_unslash($_GET['cart'])) : '';
?>

<main class="xi-main xi-shop xi-shop-checkout-page">
    <?php xi_breadcrumb(); ?>

    <?php if (!$uid) : ?>
        <div class="xi-card xi-empty">
            <p><?php esc_html_e('登录后继续下单', 'xi'); ?></p>
            <a class="xi-btn xi-btn-accent" href="<?php echo esc_url(wp_login_url(xi_shop_checkout_url(array_filter(array('items' => $raw_items, 'cart' => $raw_cart))))); ?>" data-xi-sign="login"><?php esc_html_e('登录', 'xi'); ?></a>
        </div>
    <?php else :
        $built = xi_shop_build_items($uid, xi_shop_parse_lines($uid, $raw_items, $raw_cart));
        if (is_wp_error($built)) : ?>
            <div class="xi-card xi-empty">
                <p><?php echo esc_html($built->get_error_message()); ?></p>
                <a class="xi-btn" href="<?php echo esc_url($raw_cart !== '' ? xi_shop_cart_url() : xi_shop_url()); ?>"><?php esc_html_e('返回', 'xi'); ?></a>
            </div>
        <?php else :
            // 默认地址、不使用优惠码与积分时的结算结果；付款方式按应付金额决定
            $quote   = xi_shop_quote($uid, $built);
            $quote   = is_wp_error($quote) ? array('goods' => $built['goods_total'], 'promo' => null, 'coupon' => null, 'points' => array('points' => 0, 'value' => 0), 'points_available' => array('points' => 0, 'value' => 0), 'shipping' => 0, 'discount' => 0, 'total' => $built['goods_total']) : $quote;
            $preview = (object) array('total' => $quote['total'], 'price_type' => $built['price_type']);
            $options = xi_shop_pay_options($preview);
            $type    = $built['price_type'];
            ?>
            <form class="xi-shop-checkout" data-xi-checkout data-items="<?php echo esc_attr($raw_items); ?>" data-cart="<?php echo esc_attr($raw_cart); ?>" data-type="<?php echo esc_attr($type); ?>" novalidate>
                <div class="xi-shop-checkout-main">
                    <?php if ($built['need_address']) : ?>
                        <section class="xi-card xi-uc-box">
                            <div class="xi-uc-box-head">
                                <h3 class="xi-section-title"><?php esc_html_e('收货地址', 'xi'); ?></h3>
                                <button type="button" class="xi-btn xi-btn-sm" data-xi-address-edit><?php echo xi_icon('plus'); ?><?php esc_html_e('新增地址', 'xi'); ?></button>
                            </div>
                            <?php xi_shop_address_list($uid, true); ?>
                        </section>
                    <?php endif; ?>

                    <section class="xi-card xi-uc-box">
                        <h3 class="xi-section-title"><?php esc_html_e('商品清单', 'xi'); ?></h3>
                        <div class="xi-shop-checkout-items">
                            <?php foreach ($built['items'] as $it) : ?>
                                <div class="xi-shop-order-item">
                                    <?php echo $it['thumb'] ? '<img src="' . esc_url($it['thumb']) . '" alt="" loading="lazy">' : '<span class="xi-shop-thumb-empty">' . xi_icon('bag') . '</span>'; ?>
                                    <div class="xi-shop-order-main">
                                        <b><?php echo esc_html($it['title']); ?></b>
                                        <small><?php echo esc_html(($it['spec_name'] ? $it['spec_name'] . ' · ' : '') . xi_price_text($it['price'], $type) . ' × ' . (int) $it['qty']); ?></small>
                                    </div>
                                    <b><?php echo esc_html(xi_price_text($it['subtotal'], $type)); ?></b>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <section class="xi-card xi-uc-box xi-shop-extra">
                        <?php if ($built['need_contact']) : ?>
                            <label class="xi-field is-full"><span class="xi-field-label"><?php esc_html_e('联系方式', 'xi'); ?> *</span><span class="xi-field-box"><?php echo xi_icon('user'); ?><input type="text" name="contact" maxlength="100" placeholder="<?php esc_attr_e('QQ / 微信 / 手机号，方便商家联系你', 'xi'); ?>" required></span></label>
                        <?php endif; ?>
                        <label class="xi-field is-full is-textarea"><span class="xi-field-label"><?php esc_html_e('订单备注', 'xi'); ?><?php echo $built['need_remark'] ? ' *' : ''; ?></span><span class="xi-field-box"><textarea name="remark" rows="2" maxlength="500" placeholder="<?php esc_attr_e('选填，给商家留言', 'xi'); ?>"<?php echo $built['need_remark'] ? ' required' : ''; ?>></textarea></span></label>
                        <?php if ($type === 'money' && $quote['points_available']['points'] > 0) : ?>
                            <label class="xi-shop-check xi-shop-points-use"><input type="checkbox" name="points" value="1" data-xi-points> <?php printf(esc_html__('使用 %1$d %2$s抵扣 ¥%3$s', 'xi'), (int) $quote['points_available']['points'], esc_html(xi_points_name()), esc_html(xi_money($quote['points_available']['value']))); ?><small><?php printf(esc_html__('（当前 %d）', 'xi'), (int) xi_get_points($uid)); ?></small></label>
                        <?php endif; ?>
                        <?php if ($type === 'money' && $built['goods_total'] > 0 && xi_shop_coupons()) : ?>
                            <label class="xi-field is-full"><span class="xi-field-label"><?php esc_html_e('优惠码', 'xi'); ?></span><span class="xi-field-box"><?php echo xi_icon('tag'); ?><input type="text" name="coupon" maxlength="50" autocomplete="off"><button type="button" class="xi-field-btn" data-xi-coupon><?php esc_html_e('使用', 'xi'); ?></button></span></label>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="xi-card xi-uc-box xi-shop-checkout-side">
                    <h3 class="xi-section-title"><?php esc_html_e('付款', 'xi'); ?></h3>
                    <dl class="xi-shop-sum">
                        <div><dt><?php esc_html_e('商品金额', 'xi'); ?></dt><dd><?php echo esc_html(xi_price_text($built['goods_total'], $type)); ?></dd></div>
                        <div data-xi-row="promo"<?php echo $quote['promo'] ? '' : ' hidden'; ?>><dt data-xi-label><?php echo $quote['promo'] ? esc_html($quote['promo']['text']) : ''; ?></dt><dd>-¥<span data-xi-val><?php echo $quote['promo'] ? esc_html(xi_money($quote['promo']['amount'])) : ''; ?></span></dd></div>
                        <div data-xi-row="coupon" hidden><dt data-xi-label><?php esc_html_e('优惠码', 'xi'); ?></dt><dd>-¥<span data-xi-val></span></dd></div>
                        <div data-xi-row="points" hidden><dt data-xi-label><?php echo esc_html(sprintf(__('%s抵扣', 'xi'), xi_points_name())); ?></dt><dd>-¥<span data-xi-val></span></dd></div>
                        <div data-xi-row="shipping"<?php echo $built['need_address'] && $type === 'money' ? '' : ' hidden'; ?>><dt data-xi-label><?php esc_html_e('运费', 'xi'); ?></dt><dd><span data-xi-val><?php echo (float) $quote['shipping'] > 0 ? '¥' . esc_html(xi_money($quote['shipping'])) : esc_html__('包邮', 'xi'); ?></span></dd></div>
                        <div class="is-total"><dt><?php esc_html_e('应付', 'xi'); ?></dt><dd data-xi-total><?php echo esc_html(xi_price_text($quote['total'], $type)); ?></dd></div>
                    </dl>
                    <?php if ($type === 'money' && ($next = xi_shop_promo_next($quote['goods'])) && $quote['goods'] > 0) : ?>
                        <p class="xi-shop-tip"><?php echo xi_icon('gift'); ?> <?php echo esc_html($next); ?></p>
                    <?php endif; ?>
                    <?php if ($type === 'points') : ?>
                        <p class="xi-shop-tip"><?php printf(esc_html__('当前%1$s：%2$d', 'xi'), esc_html(xi_points_name()), (int) xi_get_points($uid)); ?></p>
                    <?php elseif (isset($options['balance'])) : ?>
                        <p class="xi-shop-tip"><?php printf(esc_html__('当前余额：%s', 'xi'), esc_html(xi_format_balance(xi_get_balance($uid)))); ?></p>
                    <?php endif; ?>

                    <?php if (!$options) : ?>
                        <p class="xi-shop-tip is-error"><?php esc_html_e('站点暂未开启可用的付款方式', 'xi'); ?></p>
                    <?php else : ?>
                        <div class="xi-shop-pays" role="radiogroup">
                            <?php $n = 0; foreach ($options as $k => $label) : ?>
                                <label class="xi-shop-pay is-<?php echo esc_attr(str_replace('online:', '', $k)); ?>"><input type="radio" name="pay" value="<?php echo esc_attr($k); ?>"<?php checked($n++, 0); ?>><span><?php echo esc_html($label); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="xi-btn xi-btn-accent xi-shop-submit"><?php echo $quote['total'] > 0 ? esc_html__('提交订单', 'xi') : esc_html__('免费领取', 'xi'); ?></button>
                    <?php endif; ?>
                    <?php if ($notice = trim((string) _xz('shop_notice', ''))) : ?>
                        <p class="xi-modal-tip"><?php echo nl2br(esc_html($notice)); ?></p>
                    <?php endif; ?>
                </aside>
            </form>
            <?php xi_shop_address_dialog(); ?>
        <?php endif; ?>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
