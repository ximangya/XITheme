<?php
/**
 * 商品详情
 */

get_header();

while (have_posts()) :
    the_post();
    $pid     = get_the_ID();
    $uid     = get_current_user_id();
    $data    = xi_product_data($pid);
    $gallery = xi_product_gallery($pid);
    $specs   = $data['specs'];
    $params  = xi_product_params($pid);
    $notice  = trim((string) $data['buy_notice']) !== '' ? trim((string) $data['buy_notice']) : trim((string) _xz('shop_notice', ''));
    $promo   = xi_shop_promo_text();
    $bought  = $uid ? xi_shop_user_bought_items($uid, $pid) : array();
    $rating  = (float) get_post_meta($pid, 'xi_rating', true);
    $reviews = (int) get_post_meta($pid, 'xi_rating_count', true);

    // 各规格的价格与库存交给前端切换显示（价格已按当前用户的会员折扣计算）
    $spec_data = array();
    foreach ($specs ? array_keys($specs) : array(0) as $i) {
        $p = xi_product_price($pid, $i, $uid);
        if (!$p) {
            continue;
        }
        $spec_data[] = array(
            'index'    => $i,
            'name'     => $specs ? $specs[$i]['name'] : '',
            'price'    => xi_price_html($p['price'], $p['type']),
            'original' => $p['original'] > $p['price'] && $p['type'] !== 'free' ? xi_price_text($p['original'], $p['type']) : '',
            'stock'    => xi_product_stock($pid, $i),
        );
    }
    $first    = $spec_data ? $spec_data[0] : null;
    $base     = xi_product_price($pid, $first ? $first['index'] : 0, $uid);
    $sale     = xi_product_sale($pid);
    $vip_rate = $data['vip_s'] && $base && $base['type'] !== 'free' ? array_filter(array(1 => (float) _xz('shop_vip_discount_1', 0), 2 => (float) _xz('shop_vip_discount_2', 0)), function ($d) {
        return $d > 0 && $d < 10;
    }) : array();
    $types    = xi_delivery_types();
    ?>
    <main class="xi-main xi-shop xi-product">
        <?php xi_breadcrumb(); ?>

        <section class="xi-card xi-product-top">
            <div class="xi-product-gallery" data-xi-gallery>
                <div class="xi-product-stage">
                    <?php if ($gallery) : ?>
                        <?php echo wp_get_attachment_image($gallery[0], 'large', false, array('data-xi-gallery-main' => '', 'alt' => esc_attr(get_the_title()))); ?>
                    <?php else : ?>
                        <span class="xi-shop-thumb-empty"><?php echo xi_icon('bag'); ?></span>
                    <?php endif; ?>
                    <?php if ($data['badge']) : ?><em class="xi-shop-badge"><?php echo esc_html($data['badge']); ?></em><?php endif; ?>
                </div>
                <?php if (count($gallery) > 1) : ?>
                    <div class="xi-product-thumbs">
                        <?php foreach ($gallery as $i => $img_id) : ?>
                            <button type="button" class="<?php echo $i === 0 ? 'is-active' : ''; ?>" data-src="<?php echo esc_url(wp_get_attachment_image_url($img_id, 'large')); ?>" aria-label="<?php echo esc_attr(sprintf(__('第 %d 张图', 'xi'), $i + 1)); ?>"><?php echo wp_get_attachment_image($img_id, 'thumbnail'); ?></button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="xi-product-info" data-xi-product="<?php echo (int) $pid; ?>" data-specs="<?php echo esc_attr(wp_json_encode($spec_data)); ?>">
                <h1><?php the_title(); ?></h1>
                <?php if (has_excerpt()) : ?><p class="xi-product-excerpt"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>

                <div class="xi-product-price-box<?php echo $sale ? ' is-sale' : ''; ?>">
                    <?php if ($sale) : ?>
                        <div class="xi-product-sale">
                            <b><?php echo esc_html(sprintf(__('限时 %s 折', 'xi'), rtrim(rtrim(number_format($sale[0] * 10, 1), '0'), '.'))); ?></b>
                            <?php if ($sale[1]) : ?><span data-xi-countdown="<?php echo (int) $sale[1]; ?>"></span><?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="xi-product-price">
                        <span data-xi-price><?php echo $first ? $first['price'] : ''; ?></span>
                        <del data-xi-original><?php echo $first ? esc_html($first['original']) : ''; ?></del>
                        <?php if ($base && $base['vip']) : ?><em class="xi-product-vip"><?php echo xi_icon('crown'); ?><?php esc_html_e('会员价', 'xi'); ?></em><?php endif; ?>
                    </div>
                    <?php if ($promo && $base && $base['type'] === 'money') : ?>
                        <p class="xi-product-promo"><em><?php echo esc_html(xi_shop_promo()['name']); ?></em><?php echo esc_html($promo); ?></p>
                    <?php endif; ?>
                    <?php if ($vip_rate && !($base && $base['vip'])) : ?>
                        <p class="xi-product-vip-tip"><?php echo xi_icon('crown'); ?>
                            <?php
                            $tips = array();
                            foreach ($vip_rate as $lv => $d) {
                                $cfg    = xi_vip_level_config($lv);
                                $tips[] = sprintf(__('%1$s %2$s 折', 'xi'), $cfg['name'], rtrim(rtrim(number_format($d, 1), '0'), '.'));
                            }
                            echo esc_html(implode(' · ', $tips));
                            ?>
                            <?php if (xi_vip_enabled() && xi_vip_levels()) : ?><a href="<?php echo esc_url(xi_uc_url('vip')); ?>"><?php esc_html_e('开通会员', 'xi'); ?></a><?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <ul class="xi-product-meta">
                    <li><span><?php esc_html_e('销量', 'xi'); ?></span><b><?php echo esc_html(xi_number(xi_product_sales($pid))); ?></b></li>
                    <li><span><?php esc_html_e('评分', 'xi'); ?></span><b><?php echo $reviews ? esc_html(number_format($rating, 1)) : '—'; ?></b></li>
                    <li><span><?php esc_html_e('浏览', 'xi'); ?></span><b><?php echo esc_html(xi_number(function_exists('xi_get_views') ? xi_get_views($pid) : 0)); ?></b></li>
                    <li><span><?php esc_html_e('发货', 'xi'); ?></span><b><?php echo esc_html(in_array($data['delivery'], array('auto_card', 'auto_text'), true) ? __('自动发货', 'xi') : ($data['delivery'] === 'physical' ? __('快递发货', 'xi') : __('人工发货', 'xi'))); ?></b></li>
                </ul>

                <?php if ($specs) : ?>
                    <div class="xi-product-row">
                        <span class="xi-product-label"><?php esc_html_e('规格', 'xi'); ?></span>
                        <div class="xi-product-specs" role="radiogroup">
                            <?php foreach ($spec_data as $n => $s) : ?>
                                <button type="button" role="radio" data-spec="<?php echo (int) $s['index']; ?>" class="<?php echo $n === 0 ? 'is-active' : ''; ?><?php echo $s['stock'] === 0 ? ' is-out' : ''; ?>" aria-checked="<?php echo $n === 0 ? 'true' : 'false'; ?>"><?php echo esc_html($s['name']); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="xi-product-row">
                    <span class="xi-product-label"><?php esc_html_e('数量', 'xi'); ?></span>
                    <div class="xi-qty" data-xi-qty>
                        <button type="button" data-step="-1" aria-label="<?php esc_attr_e('减少', 'xi'); ?>"><?php echo xi_icon('minus'); ?></button>
                        <input type="number" value="1" min="1" max="99" inputmode="numeric" aria-label="<?php esc_attr_e('数量', 'xi'); ?>">
                        <button type="button" data-step="1" aria-label="<?php esc_attr_e('增加', 'xi'); ?>"><?php echo xi_icon('plus'); ?></button>
                    </div>
                    <small class="xi-product-stock" data-xi-stock></small>
                    <?php if ((int) $data['limit'] > 0) : ?><small class="xi-product-stock"><?php printf(esc_html__('每人限购 %d 件', 'xi'), (int) $data['limit']); ?></small><?php endif; ?>
                </div>

                <div class="xi-product-btns">
                    <?php if (is_user_logged_in()) : ?>
                        <button type="button" class="xi-btn" data-xi-cart-add><?php echo xi_icon('cart'); ?><?php esc_html_e('加入购物车', 'xi'); ?></button>
                        <button type="button" class="xi-btn xi-btn-accent" data-xi-buy><?php echo $base && $base['type'] === 'free' ? esc_html__('免费领取', 'xi') : esc_html__('立即购买', 'xi'); ?></button>
                    <?php else : ?>
                        <a class="xi-btn xi-btn-accent" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" data-xi-sign="login"><?php esc_html_e('登录后购买', 'xi'); ?></a>
                    <?php endif; ?>
                </div>

                <div class="xi-product-sub-btns">
                    <?php if (_xz('fav_s', true)) :
                        $faved = $uid && in_array($pid, xi_get_favorites($uid), true); ?>
                        <button type="button" class="xi-link-btn<?php echo $faved ? ' is-active' : ''; ?>" data-xi-fav="<?php echo (int) $pid; ?>" aria-pressed="<?php echo $faved ? 'true' : 'false'; ?>"><?php echo xi_icon('star'); ?><span><?php esc_html_e('收藏', 'xi'); ?></span><em><?php echo esc_html(xi_number(get_post_meta($pid, 'xi_favs', true))); ?></em></button>
                    <?php endif; ?>
                    <?php if (xi_shop_contact_enabled()) : ?>
                        <button type="button" class="xi-link-btn" data-xi-modal="xi-contact"><?php echo xi_icon('chat'); ?><?php esc_html_e('联系商家', 'xi'); ?></button>
                    <?php endif; ?>
                </div>

                <ul class="xi-product-service">
                    <li><?php echo xi_icon('check'); ?><?php echo esc_html($types[$data['delivery']]); ?></li>
                    <?php if ($data['delivery'] === 'physical' && $base && $base['type'] === 'money') :
                        $fee  = (float) $data['shipping_fee'];
                        $free = (float) _xz('shop_free_shipping', 0); ?>
                        <li><?php echo xi_icon('truck'); ?><?php echo $fee > 0 ? esc_html(sprintf(__('运费 ¥%s', 'xi'), xi_shop_num($fee)) . ($free > 0 ? sprintf(__('，满 %s 包邮', 'xi'), xi_shop_num($free)) : '')) : esc_html__('包邮', 'xi'); ?></li>
                    <?php endif; ?>
                    <li><?php echo xi_icon('check'); ?><?php echo $data['refund'] && _xz('shop_refund_s', true) ? esc_html(sprintf(__('支持售后（%d 天内）', 'xi'), (int) _xz('shop_refund_days', 7))) : esc_html__('虚拟商品不支持退款', 'xi'); ?></li>
                    <li><?php echo xi_icon('check'); ?><?php esc_html_e('订单进度可在用户中心查看', 'xi'); ?></li>
                </ul>
            </div>
        </section>

        <?php if ($bought) : ?>
            <section class="xi-card xi-product-bought">
                <h3 class="xi-section-title"><?php esc_html_e('你已购买', 'xi'); ?></h3>
                <?php foreach ($bought as $b) : ?>
                    <div class="xi-product-bought-row">
                        <div class="xi-shop-order-main">
                            <small><?php echo esc_html(mysql2date('Y-m-d H:i', $b['time']) . ' · ' . ($b['item']['spec_name'] ? $b['item']['spec_name'] . ' · ' : '') . '× ' . (int) $b['item']['qty'] . ' · ' . xi_shop_status_name($b['status'])); ?></small>
                            <?php echo xi_shop_delivered_html($b['item']); ?>
                        </div>
                        <a class="xi-btn xi-btn-sm" href="<?php echo esc_url(add_query_arg('order', $b['order_no'], xi_uc_url('shop'))); ?>"><?php esc_html_e('查看订单', 'xi'); ?></a>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <section class="xi-card xi-product-detail">
            <nav class="xi-product-tabs" role="tablist">
                <button type="button" class="is-active" data-tab="desc" role="tab"><?php esc_html_e('商品详情', 'xi'); ?></button>
                <?php if ($params) : ?><button type="button" data-tab="params" role="tab"><?php esc_html_e('规格参数', 'xi'); ?></button><?php endif; ?>
                <button type="button" data-tab="reviews" role="tab"><?php echo esc_html(sprintf(__('评价 %d', 'xi'), $reviews)); ?></button>
            </nav>

            <div class="xi-product-pane is-active" data-pane="desc">
                <?php if ($notice) : ?>
                    <div class="xi-shop-notice"><?php echo xi_icon('info'); ?><p><?php echo nl2br(esc_html($notice)); ?></p></div>
                <?php endif; ?>
                <div class="xi-content"><?php the_content(); ?></div>
            </div>

            <?php if ($params) : ?>
                <div class="xi-product-pane" data-pane="params">
                    <table class="xi-product-params">
                        <?php foreach ($params as $row) : ?>
                            <tr><th><?php echo esc_html($row[0]); ?></th><td><?php echo esc_html($row[1]); ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>

            <div class="xi-product-pane" data-pane="reviews" id="comments">
                <?php if ($reviews) : ?>
                    <div class="xi-product-rating"><b><?php echo esc_html(number_format($rating, 1)); ?></b><?php echo xi_shop_stars($rating); ?><small><?php printf(esc_html__('%d 条评价', 'xi'), $reviews); ?></small></div>
                <?php endif; ?>
                <?php
                $comments = get_comments(array('post_id' => $pid, 'status' => 'approve', 'number' => 50));
                if ($comments) {
                    echo '<ol class="xi-comment-list">';
                    wp_list_comments(array('style' => 'ol', 'avatar_size' => 40, 'max_depth' => 2), $comments);
                    echo '</ol>';
                } else {
                    echo '<p class="xi-uc-empty">' . esc_html__('还没有评价', 'xi') . '</p>';
                }
                if (comments_open() && xi_shop_can_review($pid)) {
                    comment_form(array(
                        'title_reply'          => __('发表评价', 'xi'),
                        'label_submit'         => __('提交评价', 'xi'),
                        'class_submit'         => 'xi-btn xi-btn-accent',
                        'logged_in_as'         => '',
                        'comment_notes_before' => '',
                        'comment_field'        => '<p class="comment-form-comment"><textarea id="comment" name="comment" rows="3" required placeholder="' . esc_attr__('说说你的使用体验…', 'xi') . '"></textarea></p>',
                    ));
                } elseif (is_user_logged_in()) {
                    echo '<p class="xi-shop-tip">' . esc_html__('购买后可以发表评价', 'xi') . '</p>';
                }
                ?>
            </div>
        </section>

        <?php
        $cat_ids = wp_get_post_terms($pid, 'product_cat', array('fields' => 'ids'));
        $related = new WP_Query(array(
            'post_type'           => 'xi_product',
            'posts_per_page'      => 4,
            'post__not_in'        => array($pid),
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
            'orderby'             => 'rand',
            'tax_query'           => $cat_ids && !is_wp_error($cat_ids) ? array(array('taxonomy' => 'product_cat', 'terms' => $cat_ids)) : array(),
        ));
        if ($related->have_posts()) : ?>
            <section class="xi-product-related">
                <h3 class="xi-section-title"><?php esc_html_e('相关商品', 'xi'); ?></h3>
                <div class="xi-shop-grid">
                    <?php while ($related->have_posts()) {
                        $related->the_post();
                        get_template_part('template-parts/shop-card');
                    }
                    wp_reset_postdata(); ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <?php
    xi_shop_contact_dialog();
endwhile;

get_footer();
