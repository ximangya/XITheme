<?php
/**
 * 商城核心：商品类型、分类、地址路由、商品数据、价格与库存
 *
 * 商品数据保存在 xi_product 文章的 xi_product 元数据（数组），读取统一走 xi_product_data()
 * 价格只在服务端计算：基础价（规格价）→ 限时折扣 → 会员折扣
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_shop_enabled()
{
    return (bool) _xz('shop_s', false);
}

function xi_shop_name()
{
    $name = trim((string) _xz('shop_name', ''));
    return $name !== '' ? $name : __('商城', 'xi');
}

function xi_shop_slug()
{
    $slug = sanitize_title(_xz('shop_slug', 'shop'));
    return $slug ? $slug : 'shop';
}

/* ---------- 注册 ---------- */

function xi_shop_register()
{
    if (!xi_shop_enabled()) {
        return;
    }
    $slug = xi_shop_slug();

    register_post_type('xi_product', array(
        'labels'        => array(
            'name'               => __('商品', 'xi'),
            'singular_name'      => __('商品', 'xi'),
            'menu_name'          => __('商城', 'xi'),
            'all_items'          => __('所有商品', 'xi'),
            'add_new'            => __('添加商品', 'xi'),
            'add_new_item'       => __('添加新商品', 'xi'),
            'edit_item'          => __('编辑商品', 'xi'),
            'view_item'          => __('查看商品', 'xi'),
            'search_items'       => __('搜索商品', 'xi'),
            'not_found'          => __('没有找到商品', 'xi'),
            'not_found_in_trash' => __('回收站中没有商品', 'xi'),
        ),
        'public'        => true,
        'has_archive'   => $slug,
        'rewrite'       => array('slug' => $slug . '/item', 'with_front' => false),
        'menu_icon'     => 'dashicons-cart',
        'menu_position' => 7,
        'show_in_rest'  => true,
        'supports'      => array('title', 'editor', 'thumbnail', 'excerpt', 'comments', 'page-attributes'),
    ));

    register_taxonomy('product_cat', 'xi_product', array(
        'labels'            => array(
            'name'          => __('商品分类', 'xi'),
            'singular_name' => __('商品分类', 'xi'),
            'menu_name'     => __('商品分类', 'xi'),
            'all_items'     => __('所有分类', 'xi'),
            'edit_item'     => __('编辑分类', 'xi'),
            'add_new_item'  => __('添加分类', 'xi'),
            'parent_item'   => __('父级分类', 'xi'),
        ),
        'hierarchical'      => true,
        'public'            => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => $slug . '/cat', 'with_front' => false, 'hierarchical' => true),
    ));

    register_taxonomy('product_tag', 'xi_product', array(
        'labels'            => array(
            'name'          => __('商品标签', 'xi'),
            'singular_name' => __('商品标签', 'xi'),
            'menu_name'     => __('商品标签', 'xi'),
            'all_items'     => __('所有标签', 'xi'),
            'edit_item'     => __('编辑标签', 'xi'),
            'add_new_item'  => __('添加标签', 'xi'),
        ),
        'hierarchical'      => false,
        'public'            => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => $slug . '/tag', 'with_front' => false),
    ));

    add_rewrite_rule('^' . $slug . '/cart/?$', 'index.php?xi_shop_page=cart', 'top');
    add_rewrite_rule('^' . $slug . '/checkout/?$', 'index.php?xi_shop_page=checkout', 'top');
}
add_action('init', 'xi_shop_register', 5);

function xi_shop_query_vars($vars)
{
    $vars[] = 'xi_shop_page';
    return $vars;
}
add_filter('query_vars', 'xi_shop_query_vars');

/**
 * 开关商城或修改地址前缀后，下次访问时重新生成固定链接规则
 */
function xi_shop_option_changed($old, $new)
{
    $old = is_array($old) ? $old : array();
    $new = is_array($new) ? $new : array();
    foreach (array('shop_s', 'shop_slug') as $key) {
        if ((isset($old[$key]) ? $old[$key] : '') !== (isset($new[$key]) ? $new[$key] : '')) {
            delete_option('xi_rewrite_ver');
            return;
        }
    }
}
add_action('update_option_' . XI_OPTIONS, 'xi_shop_option_changed', 10, 2);

/* ---------- 地址 ---------- */

function xi_shop_url()
{
    $link = xi_shop_enabled() ? get_post_type_archive_link('xi_product') : '';
    return $link ? $link : home_url('/');
}

function xi_shop_page_url($page)
{
    return get_option('permalink_structure')
        ? home_url('/' . xi_shop_slug() . '/' . $page . '/')
        : add_query_arg('xi_shop_page', $page, home_url('/'));
}

function xi_shop_cart_url()
{
    return xi_shop_page_url('cart');
}

function xi_shop_checkout_url($args = array())
{
    return add_query_arg($args, xi_shop_page_url('checkout'));
}

function xi_shop_current_page()
{
    return xi_shop_enabled() ? (string) get_query_var('xi_shop_page') : '';
}

/**
 * 商城相关页面：商城首页、分类、标签、商品、购物车、结算
 */
function xi_is_shop_page()
{
    if (!xi_shop_enabled()) {
        return false;
    }
    return xi_shop_current_page() !== '' || is_post_type_archive('xi_product') || is_singular('xi_product') || is_tax(array('product_cat', 'product_tag'));
}

/**
 * 购物车、结算页只有自定义查询变量：纠正为非首页并跳过文章查询
 */
function xi_shop_parse_query($query)
{
    if ($query->is_main_query() && $query->get('xi_shop_page')) {
        $query->is_home = false;
        $query->set('posts_per_page', 1);
        $query->set('no_found_rows', true);
    }
}
add_action('parse_query', 'xi_shop_parse_query');

function xi_shop_template($template)
{
    $page = xi_shop_current_page();
    if (in_array($page, array('cart', 'checkout'), true)) {
        nocache_headers();
        $file = locate_template('template-parts/shop-' . $page . '.php');
        return $file ? $file : $template;
    }
    if (xi_shop_enabled() && is_tax(array('product_cat', 'product_tag'))) {
        $file = locate_template('archive-xi_product.php');
        return $file ? $file : $template;
    }
    return $template;
}
add_filter('template_include', 'xi_shop_template', 20);

/**
 * 商品列表：每页数量与排序
 */
function xi_shop_main_query($query)
{
    if (is_admin() || !$query->is_main_query() || !xi_shop_enabled()) {
        return;
    }
    if (!$query->is_post_type_archive('xi_product') && !$query->is_tax(array('product_cat', 'product_tag'))) {
        return;
    }
    $query->set('post_type', 'xi_product');
    $query->set('posts_per_page', max(4, (int) _xz('shop_per_page', 12)));
    $q = isset($_GET['q']) ? trim(sanitize_text_field(wp_unslash($_GET['q']))) : '';
    if ($q !== '') {
        $query->set('s', mb_substr($q, 0, 50));
    }
    $sort = isset($_GET['sort']) ? sanitize_key($_GET['sort']) : '';
    switch ($sort) {
        case 'new':
            $query->set('orderby', 'date');
            $query->set('order', 'DESC');
            break;
        case 'sales':
            $query->set('meta_key', 'xi_sales_total');
            $query->set('orderby', array('meta_value_num' => 'DESC', 'date' => 'DESC'));
            break;
        case 'price_asc':
        case 'price_desc':
            $query->set('meta_key', 'xi_price_sort');
            $query->set('orderby', array('meta_value_num' => $sort === 'price_asc' ? 'ASC' : 'DESC', 'date' => 'DESC'));
            break;
        default:
            $query->set('orderby', array('menu_order' => 'ASC', 'date' => 'DESC'));
    }
}
add_action('pre_get_posts', 'xi_shop_main_query');

function xi_shop_sorts()
{
    return array(
        ''           => __('综合', 'xi'),
        'new'        => __('最新', 'xi'),
        'sales'      => __('销量', 'xi'),
        'price_asc'  => __('价格从低到高', 'xi'),
        'price_desc' => __('价格从高到低', 'xi'),
    );
}

/* ---------- 商品数据 ---------- */

function xi_product_defaults()
{
    return array(
        'price_type'    => 'money',   // money 现金 | points 积分 | free 免费
        'price'         => '',
        'original'      => '',
        'specs'         => array(),   // name, price, original, stock
        'stock'         => '',        // 留空不限
        'limit'         => 0,         // 每人限购，0 不限
        'sales_base'    => 0,         // 虚拟销量
        'delivery'      => 'auto_text', // auto_card 自动发卡 | auto_text 自动发货内容 | manual 人工发货（虚拟）| physical 实物
        'delivery_text' => '',
        'sale_discount' => '',        // 限时折扣，8.5 表示 85 折
        'sale_start'    => '',
        'sale_end'      => '',
        'vip_s'         => 1,         // 参与会员折扣
        'gallery'       => '',        // 图集附件 ID，逗号分隔
        'params'        => '',        // 商品参数，每行「名称：值」
        'need_contact'  => 0,
        'need_remark'   => 0,
        'refund'        => 1,         // 允许售后
        'badge'         => '',        // 角标文字
        'shipping_fee'  => '',        // 实物运费（元）
        'buy_notice'    => '',        // 本商品的购买须知
    );
}

/**
 * 读取商品数据（同一请求内缓存；$fresh 为 true 时重新读取，用于扣库存后）
 */
function xi_product_data($post_id, $fresh = false)
{
    static $cache = array();
    $post_id = (int) $post_id;
    if ($fresh || !isset($cache[$post_id])) {
        $meta              = get_post_meta($post_id, 'xi_product', true);
        $data              = wp_parse_args(is_array($meta) ? $meta : array(), xi_product_defaults());
        $data['specs']     = is_array($data['specs']) ? array_values($data['specs']) : array();
        $cache[$post_id]   = $data;
    }
    return $cache[$post_id];
}

function xi_delivery_types()
{
    return array(
        'auto_card' => __('自动发卡（卡密）', 'xi'),
        'auto_text' => __('自动发货（固定内容）', 'xi'),
        'manual'    => __('人工发货（虚拟）', 'xi'),
        'physical'  => __('实物（需收货地址）', 'xi'),
    );
}

/**
 * 卡密库存：按规格分组，key 为规格序号，无规格为 0
 */
function xi_product_cards($post_id, $spec = 0)
{
    $all  = get_post_meta($post_id, 'xi_product_cards', true);
    $list = is_array($all) && isset($all[(int) $spec]) ? $all[(int) $spec] : array();
    return array_values(array_filter(array_map('trim', (array) $list), 'strlen'));
}

function xi_product_set_cards($post_id, $spec, $cards)
{
    $all = get_post_meta($post_id, 'xi_product_cards', true);
    $all = is_array($all) ? $all : array();
    $all[(int) $spec] = array_values($cards);
    update_post_meta($post_id, 'xi_product_cards', $all);
}

/**
 * 有规格时返回规格；无规格返回 null；序号不存在返回 false
 */
function xi_product_spec($post_id, $spec)
{
    $data = xi_product_data($post_id);
    if (!$data['specs']) {
        return null;
    }
    $spec = (int) $spec;
    return isset($data['specs'][$spec]) ? $data['specs'][$spec] : false;
}

/**
 * 库存：-1 表示不限
 */
function xi_product_stock($post_id, $spec = 0)
{
    $data = xi_product_data($post_id);
    if ($data['delivery'] === 'auto_card') {
        return count(xi_product_cards($post_id, $data['specs'] ? $spec : 0));
    }
    $row   = $data['specs'] ? xi_product_spec($post_id, $spec) : null;
    $stock = $row ? (isset($row['stock']) ? $row['stock'] : '') : $data['stock'];
    return $stock === '' || $stock === null ? -1 : max(0, (int) $stock);
}

/**
 * 全部规格的总库存（列表展示用），-1 表示不限
 */
function xi_product_total_stock($post_id)
{
    $data = xi_product_data($post_id);
    if (!$data['specs']) {
        return xi_product_stock($post_id, 0);
    }
    $sum = 0;
    foreach (array_keys($data['specs']) as $i) {
        $s = xi_product_stock($post_id, $i);
        if ($s < 0) {
            return -1;
        }
        $sum += $s;
    }
    return $sum;
}

/**
 * 退款时恢复库存（有限库存的商品才需要）
 */
function xi_product_restore_stock($post_id, $spec, $qty)
{
    xi_product_reduce_stock($post_id, $spec, -(int) $qty);
}

/**
 * 扣减库存，$qty 为负数时增加（卡密商品在发货时出库，不走这里）
 */
function xi_product_reduce_stock($post_id, $spec, $qty)
{
    $data = xi_product_data($post_id);
    if ($data['delivery'] === 'auto_card') {
        return;
    }
    $meta = get_post_meta($post_id, 'xi_product', true);
    $meta = is_array($meta) ? $meta : array();
    if ($data['specs']) {
        if (isset($meta['specs'][$spec]['stock']) && $meta['specs'][$spec]['stock'] !== '') {
            $meta['specs'][$spec]['stock'] = max(0, (int) $meta['specs'][$spec]['stock'] - $qty);
        } else {
            return;
        }
    } elseif (isset($meta['stock']) && $meta['stock'] !== '') {
        $meta['stock'] = max(0, (int) $meta['stock'] - $qty);
    } else {
        return;
    }
    update_post_meta($post_id, 'xi_product', $meta);
    xi_product_data($post_id, true);
}

function xi_product_sales($post_id)
{
    $data = xi_product_data($post_id);
    return (int) get_post_meta($post_id, 'xi_sales', true) + (int) $data['sales_base'];
}

function xi_product_add_sales($post_id, $qty)
{
    $sales = max(0, (int) get_post_meta($post_id, 'xi_sales', true) + (int) $qty);
    update_post_meta($post_id, 'xi_sales', $sales);
    update_post_meta($post_id, 'xi_sales_total', $sales + (int) xi_product_data($post_id)['sales_base']);
}

/* ---------- 价格 ---------- */

/**
 * 会员折扣：9.5 表示 95 折，0 或留空不打折
 */
function xi_shop_vip_rate($user_id)
{
    $level = $user_id && function_exists('xi_get_vip_level') ? xi_get_vip_level($user_id) : 0;
    if (!$level) {
        return 1;
    }
    $d = (float) _xz('shop_vip_discount_' . $level, 0);
    return $d > 0 && $d < 10 ? $d / 10 : 1;
}

/**
 * 限时折扣是否生效，返回 array(折扣率, 结束时间戳) 或 null
 */
function xi_product_sale($post_id)
{
    $data = xi_product_data($post_id);
    $d    = (float) $data['sale_discount'];
    if ($d <= 0 || $d >= 10) {
        return null;
    }
    $now   = current_time('timestamp');
    $start = $data['sale_start'] ? strtotime($data['sale_start']) : 0;
    $end   = $data['sale_end'] ? strtotime($data['sale_end']) : 0;
    if (($start && $now < $start) || ($end && $now > $end)) {
        return null;
    }
    // current_time('timestamp') 是站点时区的「本地时间戳」，换算回真实时间戳给前端倒计时
    return array($d / 10, $end ? $end - (int) (get_option('gmt_offset') * HOUR_IN_SECONDS) : 0);
}

/**
 * 单价
 *
 * @return array|false type, price（实付单价）, base（规格原价）, original（划线价）, sale, sale_end, vip
 */
function xi_product_price($post_id, $spec = 0, $user_id = 0)
{
    $data = xi_product_data($post_id);
    $type = in_array($data['price_type'], array('money', 'points', 'free'), true) ? $data['price_type'] : 'money';
    $row  = $data['specs'] ? xi_product_spec($post_id, $spec) : null;
    if ($row === false) {
        return false;
    }
    $base     = (float) ($row ? $row['price'] : $data['price']);
    $original = (float) ($row ? (isset($row['original']) ? $row['original'] : 0) : $data['original']);
    if ($type === 'free') {
        $base = 0;
    }

    $price = $base;
    $sale  = $type !== 'free' ? xi_product_sale($post_id) : null;
    if ($sale) {
        $price *= $sale[0];
    }
    $vip_rate = ($type !== 'free' && $data['vip_s']) ? xi_shop_vip_rate($user_id) : 1;
    $price   *= $vip_rate;

    if ($type === 'points') {
        $price = $base > 0 ? max(1, (int) ceil($price)) : 0;
        $base  = (int) $base;
    } elseif ($type === 'money') {
        $price = $base > 0 ? max(0.01, round($price, 2)) : 0;
    } else {
        $price = 0;
    }
    return array(
        'type'     => $type,
        'price'    => $price,
        'base'     => $base,
        'original' => max($original, ($sale || $vip_rate < 1) ? $base : 0),
        'sale'     => $sale ? $sale[0] : 0,
        'sale_end' => $sale ? $sale[1] : 0,
        'vip'      => $vip_rate < 1,
    );
}

/**
 * 价格文字：¥9.90 / 100 积分 / 免费
 */
function xi_price_text($amount, $type = 'money')
{
    if ($type === 'free' || ($type === 'money' && (float) $amount <= 0)) {
        return __('免费', 'xi');
    }
    if ($type === 'points') {
        return (int) $amount . ' ' . xi_points_name();
    }
    return '¥' . xi_money($amount);
}

function xi_price_html($amount, $type = 'money')
{
    if ($type === 'free' || ($type === 'money' && (float) $amount <= 0)) {
        return '<span class="xi-price is-free">' . esc_html__('免费', 'xi') . '</span>';
    }
    if ($type === 'points') {
        return '<span class="xi-price is-points"><b>' . (int) $amount . '</b><small>' . esc_html(xi_points_name()) . '</small></span>';
    }
    $parts = explode('.', xi_money($amount));
    return '<span class="xi-price"><small>¥</small><b>' . esc_html($parts[0]) . '</b><small>.' . esc_html($parts[1]) . '</small></span>';
}

/**
 * 列表展示价：多规格取最低价
 */
function xi_product_display_price($post_id, $user_id = 0)
{
    $data = xi_product_data($post_id);
    if (!$data['specs']) {
        return xi_product_price($post_id, 0, $user_id);
    }
    $best = null;
    foreach (array_keys($data['specs']) as $i) {
        $p = xi_product_price($post_id, $i, $user_id);
        if ($p && ($best === null || $p['price'] < $best['price'])) {
            $best = $p;
        }
    }
    if ($best) {
        $best['from'] = count($data['specs']) > 1;
    }
    return $best;
}

/**
 * 商品参数表：每行「名称：值」
 */
function xi_product_params($post_id)
{
    $rows = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) xi_product_data($post_id)['params']) as $line) {
        $pair = preg_split('/[:：]/u', $line, 2);
        if (count($pair) === 2 && trim($pair[0]) !== '') {
            $rows[] = array(trim($pair[0]), trim($pair[1]));
        }
    }
    return $rows;
}

function xi_product_gallery($post_id)
{
    $ids = array_filter(array_map('intval', explode(',', (string) xi_product_data($post_id)['gallery'])));
    if (has_post_thumbnail($post_id)) {
        array_unshift($ids, (int) get_post_thumbnail_id($post_id));
    }
    return array_values(array_unique($ids));
}

/**
 * 保存后同步排序用的数值字段
 */
function xi_product_sync_sort_meta($post_id)
{
    $p = xi_product_display_price($post_id, 0);
    update_post_meta($post_id, 'xi_price_sort', $p ? ($p['type'] === 'points' ? $p['price'] / 100 : $p['price']) : 0);
    update_post_meta($post_id, 'xi_sales_total', xi_product_sales($post_id));
}

/* ---------- 页面辅助 ---------- */

function xi_shop_breadcrumb_items()
{
    if (!xi_is_shop_page()) {
        return array();
    }
    $items = array(array(xi_shop_name(), xi_shop_url()));
    $page  = xi_shop_current_page();
    if ($page === 'cart') {
        $items[] = array(__('购物车', 'xi'), '');
    } elseif ($page === 'checkout') {
        $items[] = array(__('确认订单', 'xi'), '');
    } elseif (is_singular('xi_product')) {
        $cats = get_the_terms(get_queried_object_id(), 'product_cat');
        if ($cats && !is_wp_error($cats)) {
            foreach (array_reverse(get_ancestors($cats[0]->term_id, 'product_cat')) as $parent) {
                $t       = get_term($parent, 'product_cat');
                $items[] = array($t->name, get_term_link($t));
            }
            $items[] = array($cats[0]->name, get_term_link($cats[0]));
        }
        $items[] = array(get_the_title(get_queried_object_id()), '');
    } elseif (is_tax()) {
        $items[] = array(single_term_title('', false), '');
    } else {
        $items[count($items) - 1][1] = '';
    }
    return $items;
}

/**
 * 页面标题：商城首页用商城名称，购物车、结算页补上标题
 */
function xi_shop_document_title($parts)
{
    if (!xi_shop_enabled()) {
        return $parts;
    }
    $page = xi_shop_current_page();
    if ($page === 'cart') {
        $parts['title'] = __('购物车', 'xi');
    } elseif ($page === 'checkout') {
        $parts['title'] = __('确认订单', 'xi');
    } elseif (is_post_type_archive('xi_product')) {
        $parts['title'] = xi_shop_name();
    }
    return $parts;
}
add_filter('document_title_parts', 'xi_shop_document_title');

function xi_shop_body_class($classes)
{
    if (xi_is_shop_page()) {
        $classes[] = 'xi-shop-page';
        $classes[] = 'xi-no-sidebar';
    }
    return $classes;
}
add_filter('body_class', 'xi_shop_body_class');

/**
 * 顶部购物车按钮
 */
function xi_shop_cart_count($user_id = 0)
{
    $user_id = $user_id ? $user_id : get_current_user_id();
    $count   = 0;
    foreach (xi_cart_get($user_id) as $qty) {
        $count += (int) $qty;
    }
    return $count;
}

function xi_shop_header_cart()
{
    if (!xi_shop_enabled() || !_xz('shop_header_cart', true)) {
        return;
    }
    $count = is_user_logged_in() ? xi_shop_cart_count() : 0;
    echo '<a class="xi-btn-icon xi-header-cart" href="' . esc_url(xi_shop_cart_url()) . '" aria-label="' . esc_attr__('购物车', 'xi') . '"' . (is_user_logged_in() ? '' : ' data-xi-sign="login"') . '>' . xi_icon('cart');
    echo '<em data-xi-cart-count' . ($count ? '' : ' hidden') . '>' . (int) min(99, $count) . '</em></a>';
}
add_action('xi_header_actions', 'xi_shop_header_cart');

/* ---------- 资源 ---------- */

function xi_shop_enqueue()
{
    if (!xi_shop_enabled()) {
        return;
    }
    $uc  = function_exists('xi_is_user_center') && xi_is_user_center();
    $uri = get_template_directory_uri();
    if (!xi_is_shop_page() && !$uc) {
        // 侧栏放了商城小工具时只加载样式
        if (is_active_widget(false, false, 'xi_widget_ui_products')) {
            wp_enqueue_style('xi-shop', $uri . '/assets/css/shop.css', array('xi-main'), XI_VERSION);
        }
        return;
    }
    wp_enqueue_style('xi-shop', $uri . '/assets/css/shop.css', array('xi-main'), XI_VERSION);
    wp_enqueue_script('xi-shop', $uri . '/assets/js/shop.js', array('xi-main'), XI_VERSION, true);
    wp_localize_script('xi-shop', 'xiShop', array(
        'ajax'     => admin_url('admin-ajax.php'),
        'nonce'    => is_user_logged_in() ? wp_create_nonce('xi_user') : '',
        'login'    => is_user_logged_in(),
        'cart'     => xi_shop_cart_url(),
        'checkout' => xi_shop_checkout_url(),
        'orders'   => function_exists('xi_uc_url') ? xi_uc_url('shop') : home_url('/'),
        'points'   => xi_points_name(),
    ));
}
add_action('wp_enqueue_scripts', 'xi_shop_enqueue', 25);

/**
 * 商品列表短代码：[xi_products cat="分类别名" tag="标签别名" count="8" sort="sales|new|price_asc|price_desc"]
 */
function xi_products_shortcode($atts)
{
    if (!xi_shop_enabled()) {
        return '';
    }
    $atts = shortcode_atts(array('cat' => '', 'tag' => '', 'count' => 8, 'sort' => '', 'ids' => ''), $atts, 'xi_products');
    $args = array(
        'post_type'           => 'xi_product',
        'posts_per_page'      => max(1, min(48, (int) $atts['count'])),
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    );
    $tax = array();
    if ($atts['cat']) {
        $tax[] = array('taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array_map('trim', explode(',', $atts['cat'])));
    }
    if ($atts['tag']) {
        $tax[] = array('taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => array_map('trim', explode(',', $atts['tag'])));
    }
    if ($tax) {
        $args['tax_query'] = $tax;
    }
    if ($atts['ids']) {
        $args['post__in'] = array_map('intval', explode(',', $atts['ids']));
        $args['orderby']  = 'post__in';
    } elseif ($atts['sort'] === 'sales') {
        $args['meta_key'] = 'xi_sales_total';
        $args['orderby']  = 'meta_value_num';
    } elseif (in_array($atts['sort'], array('price_asc', 'price_desc'), true)) {
        $args['meta_key'] = 'xi_price_sort';
        $args['orderby']  = 'meta_value_num';
        $args['order']    = $atts['sort'] === 'price_asc' ? 'ASC' : 'DESC';
    }
    $q = new WP_Query($args);
    if (!$q->have_posts()) {
        return '';
    }
    // 短代码可能出现在非商城页面，这里补上样式
    wp_enqueue_style('xi-shop', get_template_directory_uri() . '/assets/css/shop.css', array('xi-main'), XI_VERSION);
    ob_start();
    echo '<div class="xi-shop-grid">';
    while ($q->have_posts()) {
        $q->the_post();
        get_template_part('template-parts/shop-card');
    }
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('xi_products', 'xi_products_shortcode');
