<?php
/**
 * 商城小工具：商品列表（侧栏、首页模块位都可用）
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_widget_register_shop()
{
    if (!class_exists('XI_CFSwidget') || !xi_shop_enabled()) {
        return;
    }
    $cats = array();
    // 分类在 init 时才注册，这里直接查数据库里的分类名
    global $wpdb;
    if (isset($wpdb->term_taxonomy)) {
        foreach ((array) $wpdb->get_results("SELECT t.term_id, t.name FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = 'product_cat' ORDER BY t.name LIMIT 200") as $row) {
            $cats[(int) $row->term_id] = $row->name;
        }
    }
    XI_CFSwidget::create('xi_widget_ui_products', array(
        'title'       => __('商城商品', 'xi'),
        'description' => __('按分类、销量、价格显示商城商品', 'xi'),
        'callback'    => 'xi_widget_ui_products',
        'defaults'    => array('title' => __('热销商品', 'xi')),
        'fields'      => array(
            array(
                'id'          => 'cat',
                'type'        => 'select',
                'title'       => __('商品分类', 'xi'),
                'placeholder' => __('全部分类', 'xi'),
                'options'     => $cats,
            ),
            array(
                'id'      => 'orderby',
                'type'    => 'select',
                'title'   => __('排序', 'xi'),
                'default' => 'sales',
                'options' => array(
                    'sales' => __('销量最高', 'xi'),
                    'date'  => __('最新上架', 'xi'),
                    'price' => __('价格最低', 'xi'),
                    'rand'  => __('随机', 'xi'),
                ),
            ),
            array(
                'id'      => 'count',
                'type'    => 'spinner',
                'title'   => __('显示数量', 'xi'),
                'min'     => 1,
                'max'     => 20,
                'default' => 5,
            ),
            array(
                'id'      => 'style',
                'type'    => 'button_set',
                'title'   => __('样式', 'xi'),
                'default' => 'list',
                'options' => array('list' => __('列表', 'xi'), 'grid' => __('卡片', 'xi')),
            ),
        ),
    ));
}
add_action('after_setup_theme', 'xi_widget_register_shop', 12);

function xi_widget_ui_products($args, $instance)
{
    if (!xi_shop_enabled()) {
        return;
    }
    $instance = wp_parse_args($instance, array('cat' => '', 'orderby' => 'sales', 'count' => 5, 'style' => 'list'));
    $q        = array(
        'post_type'           => 'xi_product',
        'posts_per_page'      => max(1, min(20, (int) $instance['count'])),
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    );
    if ($instance['orderby'] === 'sales') {
        $q['meta_key'] = 'xi_sales_total';
        $q['orderby']  = array('meta_value_num' => 'DESC', 'date' => 'DESC');
    } elseif ($instance['orderby'] === 'price') {
        $q['meta_key'] = 'xi_price_sort';
        $q['orderby']  = array('meta_value_num' => 'ASC', 'date' => 'DESC');
    } elseif ($instance['orderby'] === 'rand') {
        $q['orderby'] = 'rand';
    }
    if (!empty($instance['cat'])) {
        $q['tax_query'] = array(array('taxonomy' => 'product_cat', 'terms' => (int) $instance['cat']));
    }
    if (is_singular('xi_product')) {
        $q['post__not_in'] = array(get_queried_object_id());
    }
    $query = new WP_Query($q);
    if (!$query->have_posts()) {
        return;
    }
    wp_enqueue_style('xi-shop', get_template_directory_uri() . '/assets/css/shop.css', array('xi-main'), XI_VERSION);

    echo $args['before_widget'];
    XI_CSF_Widget::show_title($instance);
    if ($instance['style'] === 'grid') {
        echo '<div class="xi-shop-grid is-widget">';
        while ($query->have_posts()) {
            $query->the_post();
            get_template_part('template-parts/shop-card');
        }
        echo '</div>';
    } else {
        echo '<ul class="xi-wposts xi-wposts-list xi-wproducts">';
        while ($query->have_posts()) {
            $query->the_post();
            $p = xi_product_display_price(get_the_ID(), get_current_user_id());
            echo '<li><a href="' . esc_url(get_permalink()) . '"><span class="xi-wposts-thumb">';
            if (has_post_thumbnail()) {
                the_post_thumbnail('thumbnail', array('loading' => 'lazy'));
            } else {
                echo xi_icon('bag');
            }
            echo '</span><span class="xi-wposts-body"><span class="xi-wposts-title">' . esc_html(get_the_title()) . '</span>';
            echo '<span class="xi-wproducts-meta">' . ($p ? xi_price_html($p['price'], $p['type']) : '') . '<small>' . esc_html(sprintf(__('已售 %s', 'xi'), xi_number(xi_product_sales(get_the_ID())))) . '</small></span></span>';
            echo '</a></li>';
        }
        echo '</ul>';
    }
    echo $args['after_widget'];
    wp_reset_postdata();
}
