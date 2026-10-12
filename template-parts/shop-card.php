<?php
/**
 * 商品卡片
 */
$pid   = get_the_ID();
$data  = xi_product_data($pid);
$price = xi_product_display_price($pid, get_current_user_id());
$stock = xi_product_total_stock($pid);
?>
<a class="xi-card xi-shop-card" href="<?php the_permalink(); ?>">
    <span class="xi-shop-card-img">
        <?php if (has_post_thumbnail()) : ?>
            <?php the_post_thumbnail('medium_large', array('loading' => 'lazy', 'alt' => esc_attr(get_the_title()))); ?>
        <?php else : ?>
            <span class="xi-shop-thumb-empty"><?php echo xi_icon('bag'); ?></span>
        <?php endif; ?>
        <?php if ($data['badge']) : ?><em class="xi-shop-badge"><?php echo esc_html($data['badge']); ?></em><?php endif; ?>
        <?php if ($stock === 0) : ?><em class="xi-shop-soldout"><?php esc_html_e('已售罄', 'xi'); ?></em><?php endif; ?>
    </span>
    <span class="xi-shop-card-body">
        <b class="xi-shop-card-title"><?php the_title(); ?></b>
        <span class="xi-shop-card-foot">
            <?php if ($price) : ?>
                <span class="xi-shop-card-price">
                    <?php echo xi_price_html($price['price'], $price['type']); ?>
                    <?php if (!empty($price['from'])) : ?><small><?php esc_html_e('起', 'xi'); ?></small><?php endif; ?>
                    <?php if ($price['original'] > $price['price'] && $price['type'] !== 'free') : ?><del><?php echo esc_html(xi_price_text($price['original'], $price['type'])); ?></del><?php endif; ?>
                </span>
            <?php endif; ?>
            <small class="xi-shop-card-sales"><?php printf(esc_html__('已售 %s', 'xi'), esc_html(xi_number(xi_product_sales($pid)))); ?></small>
        </span>
    </span>
</a>
