<?php
/**
 * 商城首页、商品分类、商品标签
 */

get_header();

$term     = is_tax() ? get_queried_object() : null;
$is_cat   = $term && $term->taxonomy === 'product_cat';
$sort     = isset($_GET['sort']) ? sanitize_key($_GET['sort']) : '';
$base     = $term ? get_term_link($term) : xi_shop_url();
$top_cats = get_terms(array('taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true));
$children = $is_cat ? get_terms(array('taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => true)) : array();
$ancestor = $is_cat ? get_ancestors($term->term_id, 'product_cat') : array();
$active   = $is_cat ? ($ancestor ? (int) end($ancestor) : (int) $term->term_id) : 0; // 当前所属的一级分类
?>

<main class="xi-main xi-shop">
    <?php xi_breadcrumb(); ?>

    <section class="xi-card xi-shop-hero">
        <div>
            <h1><?php echo esc_html($term ? $term->name : xi_shop_name()); ?></h1>
            <?php if ($term && $term->description) : ?>
                <p><?php echo esc_html(wp_strip_all_tags($term->description)); ?></p>
            <?php elseif (!$term) : ?>
                <p><?php esc_html_e('精选好物，付款后自动发货', 'xi'); ?></p>
            <?php endif; ?>
        </div>
        <form class="xi-shop-search" action="<?php echo esc_url(xi_shop_url()); ?>" method="get" role="search">
            <?php echo xi_icon('search'); ?>
            <input type="search" name="q" value="<?php echo esc_attr(isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : ''); ?>" placeholder="<?php esc_attr_e('搜索商品', 'xi'); ?>" aria-label="<?php esc_attr_e('搜索商品', 'xi'); ?>">
        </form>
        <?php if (is_user_logged_in()) : ?>
            <div class="xi-shop-hero-acts">
                <a class="xi-btn" href="<?php echo esc_url(xi_uc_url('shop')); ?>"><?php echo xi_icon('list'); ?><?php esc_html_e('我的订单', 'xi'); ?></a>
                <a class="xi-btn xi-btn-accent" href="<?php echo esc_url(xi_shop_cart_url()); ?>"><?php echo xi_icon('cart'); ?><?php esc_html_e('购物车', 'xi'); ?></a>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($promo_text = xi_shop_promo_text()) : ?>
        <div class="xi-shop-promo-bar"><?php echo xi_icon('gift'); ?><b><?php echo esc_html(xi_shop_promo()['name']); ?></b><span><?php echo esc_html($promo_text); ?></span>
            <?php if (xi_shop_promo()['end']) : ?><small data-xi-countdown="<?php echo (int) xi_shop_promo()['end']; ?>"></small><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['q'])) : ?>
        <p class="xi-shop-search-tip"><?php printf(esc_html__('「%1$s」的搜索结果：%2$d 件商品', 'xi'), esc_html(sanitize_text_field(wp_unslash($_GET['q']))), (int) $GLOBALS['wp_query']->found_posts); ?> <a href="<?php echo esc_url(xi_shop_url()); ?>"><?php esc_html_e('清除', 'xi'); ?></a></p>
    <?php endif; ?>

    <?php if ($top_cats && !is_wp_error($top_cats)) : ?>
        <nav class="xi-shop-cats" aria-label="<?php esc_attr_e('商品分类', 'xi'); ?>">
            <a href="<?php echo esc_url(xi_shop_url()); ?>" class="<?php echo !$term ? 'is-active' : ''; ?>"><?php esc_html_e('全部', 'xi'); ?></a>
            <?php foreach ($top_cats as $cat) : ?>
                <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="<?php echo $active === $cat->term_id ? 'is-active' : ''; ?>"><?php echo esc_html($cat->name); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($children && !is_wp_error($children)) : ?>
            <nav class="xi-shop-cats is-sub">
                <?php foreach ($children as $cat) : ?>
                    <a href="<?php echo esc_url(get_term_link($cat)); ?>"><?php echo esc_html($cat->name); ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <div class="xi-shop-sorts">
        <?php foreach (xi_shop_sorts() as $key => $label) : ?>
            <a href="<?php echo esc_url($key ? add_query_arg(array_filter(array('sort' => $key, 'q' => isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '')), $base) : (isset($_GET['q']) ? add_query_arg('q', sanitize_text_field(wp_unslash($_GET['q'])), $base) : $base)); ?>" class="<?php echo $sort === $key ? 'is-active' : ''; ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (have_posts()) : ?>
        <div class="xi-shop-grid">
            <?php
            while (have_posts()) {
                the_post();
                get_template_part('template-parts/shop-card');
            }
            ?>
        </div>
        <?php the_posts_pagination(array('mid_size' => 1, 'prev_text' => '‹', 'next_text' => '›')); ?>
    <?php else : ?>
        <div class="xi-card xi-empty"><p><?php esc_html_e('暂时还没有商品', 'xi'); ?></p></div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
