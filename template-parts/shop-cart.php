<?php
/**
 * 购物车
 */

get_header();
$uid = get_current_user_id();
?>

<main class="xi-main xi-shop xi-shop-cart-page">
    <?php xi_breadcrumb(); ?>

    <?php if (!$uid) : ?>
        <div class="xi-card xi-empty">
            <p><?php esc_html_e('登录后查看购物车', 'xi'); ?></p>
            <a class="xi-btn xi-btn-accent" href="<?php echo esc_url(wp_login_url(xi_shop_cart_url())); ?>" data-xi-sign="login"><?php esc_html_e('登录', 'xi'); ?></a>
        </div>
    <?php else :
        $lines = xi_cart_lines($uid);
        ?>
        <section class="xi-card xi-shop-cart" data-xi-cart>
            <h1 class="xi-section-title"><?php esc_html_e('购物车', 'xi'); ?></h1>
            <?php if (!$lines) : ?>
                <div class="xi-empty">
                    <p><?php esc_html_e('购物车还是空的', 'xi'); ?></p>
                    <a class="xi-btn xi-btn-accent" href="<?php echo esc_url(xi_shop_url()); ?>"><?php esc_html_e('去逛逛', 'xi'); ?></a>
                </div>
            <?php else : ?>
                <div class="xi-shop-cart-list">
                    <?php foreach ($lines as $l) :
                        $p = $l['price'];
                        ?>
                        <div class="xi-shop-cart-row<?php echo $l['valid'] ? '' : ' is-invalid'; ?>" data-key="<?php echo esc_attr($l['key']); ?>" data-price="<?php echo $p ? esc_attr($p['price']) : 0; ?>" data-type="<?php echo $p ? esc_attr($p['type']) : ''; ?>">
                            <input type="checkbox" class="xi-shop-cart-check" <?php echo $l['valid'] ? 'checked' : 'disabled'; ?> aria-label="<?php esc_attr_e('选择', 'xi'); ?>">
                            <?php echo $l['thumb'] ? '<img src="' . esc_url($l['thumb']) . '" alt="" loading="lazy">' : '<span class="xi-shop-thumb-empty">' . xi_icon('bag') . '</span>'; ?>
                            <div class="xi-shop-cart-main">
                                <b><?php echo $l['link'] ? '<a href="' . esc_url($l['link']) . '">' . esc_html($l['title']) . '</a>' : esc_html($l['title']); ?></b>
                                <small><?php echo esc_html($l['spec_name']); ?><?php if (!$l['valid']) : ?> <em><?php echo $l['stock'] === 0 ? esc_html__('已售罄', 'xi') : esc_html__('已失效', 'xi'); ?></em><?php endif; ?></small>
                                <span class="xi-shop-cart-price"><?php echo $p ? xi_price_html($p['price'], $p['type']) : ''; ?></span>
                            </div>
                            <?php if ($l['valid']) : ?>
                                <div class="xi-qty is-sm" data-xi-qty>
                                    <button type="button" data-step="-1" aria-label="<?php esc_attr_e('减少', 'xi'); ?>"><?php echo xi_icon('minus'); ?></button>
                                    <input type="number" value="<?php echo (int) $l['qty']; ?>" min="1" max="<?php echo $l['stock'] > 0 ? (int) min(99, $l['stock']) : 99; ?>" inputmode="numeric" aria-label="<?php esc_attr_e('数量', 'xi'); ?>">
                                    <button type="button" data-step="1" aria-label="<?php esc_attr_e('增加', 'xi'); ?>"><?php echo xi_icon('plus'); ?></button>
                                </div>
                            <?php endif; ?>
                            <button type="button" class="xi-btn-icon" data-xi-cart-del aria-label="<?php esc_attr_e('移除', 'xi'); ?>"><?php echo xi_icon('trash'); ?></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="xi-shop-cart-bar">
                    <label class="xi-shop-check"><input type="checkbox" data-xi-cart-all checked> <?php esc_html_e('全选', 'xi'); ?></label>
                    <button type="button" class="xi-link-btn is-danger" data-xi-cart-del-selected><?php esc_html_e('删除选中', 'xi'); ?></button>
                    <span class="xi-shop-cart-sum"><?php esc_html_e('合计', 'xi'); ?> <b data-xi-cart-sum></b><?php if ($promo = xi_shop_promo()) : ?><small data-xi-promo="<?php echo esc_attr(wp_json_encode($promo['tiers'])); ?>"></small><?php endif; ?></span>
                    <button type="button" class="xi-btn xi-btn-accent" data-xi-cart-checkout><?php esc_html_e('去结算', 'xi'); ?></button>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
