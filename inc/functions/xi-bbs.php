<?php
/**
 * 论坛社区（参考子比主题论坛）：版块、帖子、话题、前台发帖、版主管理
 *
 * - 帖子：文章类型 forum_post，回复即评论
 * - 版块：分类法 plate，两级——顶级为「分区」，下级为「版块」；没有下级的顶级项也可直接发帖
 * - 话题：分类法 forum_topic，类似标签，列表中显示为 #话题#
 *
 * 地址（slug 默认 forum，可在设置中修改）：
 * /forum/ 论坛首页 · /forum/plate/xx/ 版块 · /forum/topic/xx/ 话题 · /forum/t/123/ 帖子 · /forum/new/ 发帖
 * 未开启固定链接时分别为 ?post_type=forum_post、?plate=xx、?forum_topic=xx、?post_type=forum_post&p=123、?xi_bbs_new=1
 *
 * 帖子元数据：xi_bbs_top 置顶（1 版块置顶 / 2 全局置顶）、xi_bbs_essence 精华、xi_last_reply 最后回复时间戳、
 * xi_last_reply_user 最后回复人；浏览量、点赞、收藏与文章共用 xi_views、xi_likes、xi_favs
 * 版块元数据：xi_plate（icon、color、cover、mods、post_auth、notice、order），关注数 xi_follow_count
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_bbs_enabled()
{
    return (bool) _xz('bbs_s', false);
}

function xi_bbs_slug()
{
    $slug = sanitize_title((string) _xz('bbs_slug', 'forum'));
    return $slug ? $slug : 'forum';
}

function xi_bbs_name()
{
    $name = trim((string) _xz('bbs_name', ''));
    return $name !== '' ? $name : __('社区论坛', 'xi');
}

/**
 * 论坛地址：$path 为空是首页，new 是发帖页
 */
function xi_bbs_url($path = '')
{
    if (get_option('permalink_structure')) {
        return home_url('/' . xi_bbs_slug() . '/' . ($path ? trailingslashit($path) : ''));
    }
    if ($path === 'new') {
        return add_query_arg('xi_bbs_new', 1, home_url('/'));
    }
    return add_query_arg('post_type', 'forum_post', home_url('/'));
}

function xi_bbs_new_url($plate_id = 0, $post_id = 0)
{
    $url = xi_bbs_url('new');
    if ($post_id) {
        $url = add_query_arg('post', (int) $post_id, $url);
    } elseif ($plate_id) {
        $url = add_query_arg('plate', (int) $plate_id, $url);
    }
    return $url;
}

/* ---------- 注册 ---------- */

function xi_bbs_register()
{
    if (!xi_bbs_enabled()) {
        return;
    }
    $slug = xi_bbs_slug();

    register_post_type('forum_post', array(
        'labels'              => array(
            'name'               => __('帖子', 'xi'),
            'singular_name'      => __('帖子', 'xi'),
            'menu_name'          => __('论坛', 'xi'),
            'all_items'          => __('所有帖子', 'xi'),
            'add_new'            => __('发布帖子', 'xi'),
            'add_new_item'       => __('发布新帖子', 'xi'),
            'edit_item'          => __('编辑帖子', 'xi'),
            'view_item'          => __('查看帖子', 'xi'),
            'search_items'       => __('搜索帖子', 'xi'),
            'not_found'          => __('没有找到帖子', 'xi'),
            'not_found_in_trash' => __('回收站中没有帖子', 'xi'),
        ),
        'public'              => true,
        'has_archive'         => true,
        'rewrite'             => false, // 地址由下方规则与 xi_bbs_post_link() 生成
        'query_var'           => 'forum_post',
        'exclude_from_search' => !_xz('bbs_search', true),
        'menu_icon'           => 'dashicons-format-chat',
        'menu_position'       => 6,
        'show_in_rest'        => true,
        'supports'            => array('title', 'editor', 'author', 'comments', 'thumbnail', 'excerpt', 'revisions'),
    ));

    register_taxonomy('plate', 'forum_post', array(
        'labels'            => array(
            'name'          => __('版块', 'xi'),
            'singular_name' => __('版块', 'xi'),
            'menu_name'     => __('版块', 'xi'),
            'all_items'     => __('所有版块', 'xi'),
            'parent_item'   => __('所属分区', 'xi'),
            'edit_item'     => __('编辑版块', 'xi'),
            'add_new_item'  => __('添加版块', 'xi'),
            'search_items'  => __('搜索版块', 'xi'),
            'not_found'     => __('没有版块', 'xi'),
        ),
        'description'       => __('顶级项为分区，下级为版块', 'xi'),
        'hierarchical'      => true,
        'public'            => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => $slug . '/plate', 'with_front' => false),
    ));

    register_taxonomy('forum_topic', 'forum_post', array(
        'labels'            => array(
            'name'          => __('话题', 'xi'),
            'singular_name' => __('话题', 'xi'),
            'menu_name'     => __('话题', 'xi'),
            'all_items'     => __('所有话题', 'xi'),
            'edit_item'     => __('编辑话题', 'xi'),
            'add_new_item'  => __('添加话题', 'xi'),
            'search_items'  => __('搜索话题', 'xi'),
            'popular_items' => __('热门话题', 'xi'),
            'not_found'     => __('没有话题', 'xi'),
        ),
        'hierarchical'      => false,
        'public'            => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => array('slug' => $slug . '/topic', 'with_front' => false),
    ));

    add_rewrite_rule('^' . $slug . '/?$', 'index.php?post_type=forum_post', 'top');
    add_rewrite_rule('^' . $slug . '/page/([0-9]+)/?$', 'index.php?post_type=forum_post&paged=$matches[1]', 'top');
    add_rewrite_rule('^' . $slug . '/t/([0-9]+)/?$', 'index.php?post_type=forum_post&p=$matches[1]', 'top');
    add_rewrite_rule('^' . $slug . '/t/([0-9]+)/comment-page-([0-9]+)/?$', 'index.php?post_type=forum_post&p=$matches[1]&cpage=$matches[2]', 'top');
    add_rewrite_rule('^' . $slug . '/new/?$', 'index.php?xi_bbs_new=1', 'top');
}
add_action('init', 'xi_bbs_register', 5);

/**
 * 开关或地址变化后刷新一次固定链接规则
 * 放在 init 末尾：其他模块（用户中心、投稿页）的规则也已注册，刷新时不会丢
 */
function xi_bbs_maybe_flush()
{
    $sig = xi_bbs_enabled() ? md5(xi_bbs_slug() . '|1') : 'off';
    if ((string) get_option('xi_bbs_rules', '') !== $sig) {
        flush_rewrite_rules(false);
        update_option('xi_bbs_rules', $sig, true);
    }
}
add_action('init', 'xi_bbs_maybe_flush', 99);

function xi_bbs_post_link($link, $post)
{
    if ($post->post_type !== 'forum_post') {
        return $link;
    }
    if (get_option('permalink_structure')) {
        return home_url('/' . xi_bbs_slug() . '/t/' . $post->ID . '/');
    }
    return add_query_arg(array('post_type' => 'forum_post', 'p' => $post->ID), home_url('/'));
}
add_filter('post_type_link', 'xi_bbs_post_link', 10, 2);

function xi_bbs_query_vars($vars)
{
    $vars[] = 'xi_bbs_new';
    return $vars;
}
add_filter('query_vars', 'xi_bbs_query_vars');

/* ---------- 页面判断 ---------- */

function xi_is_bbs_new()
{
    return xi_bbs_enabled() && (bool) get_query_var('xi_bbs_new');
}

/**
 * 当前是否论坛页面（首页、版块、话题、帖子；不含发帖页）
 */
function xi_is_bbs()
{
    if (!xi_bbs_enabled()) {
        return false;
    }
    return is_post_type_archive('forum_post') || is_tax(array('plate', 'forum_topic')) || is_singular('forum_post');
}

/**
 * 发帖页只有自定义查询变量，纠正成非首页并跳过文章查询
 */
function xi_bbs_parse_query($query)
{
    if ($query->is_main_query() && $query->get('xi_bbs_new')) {
        $query->is_home = false;
        $query->set('posts_per_page', 1);
        $query->set('no_found_rows', true);
    }
}
add_action('parse_query', 'xi_bbs_parse_query');

function xi_bbs_template($template)
{
    if (xi_is_bbs_new()) {
        status_header(200);
        return get_theme_file_path('bbs-new.php');
    }
    return $template;
}
add_filter('template_include', 'xi_bbs_template');

function xi_bbs_title($title)
{
    if (xi_is_bbs_new()) {
        $title['title'] = !empty($_GET['post']) ? __('编辑帖子', 'xi') : __('发布帖子', 'xi');
    } elseif (xi_bbs_enabled() && is_post_type_archive('forum_post')) {
        $title['title'] = xi_bbs_name();
    }
    return $title;
}
add_filter('document_title_parts', 'xi_bbs_title');

function xi_bbs_body_class($classes)
{
    if (xi_is_bbs_new()) {
        $classes[] = 'xi-no-sidebar';
        $classes[] = 'xi-write-page';
        $classes[] = 'xi-bbs-new-page';
    } elseif (xi_is_bbs()) {
        $classes[] = 'xi-bbs-page';
    }
    return $classes;
}
add_filter('body_class', 'xi_bbs_body_class', 20);

function xi_bbs_noindex()
{
    if (xi_is_bbs_new()) {
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    }
}
add_action('wp_head', 'xi_bbs_noindex', 1);

/* ---------- 版块 ---------- */

function xi_plate_meta($term_id, $key = '', $default = '')
{
    $meta = get_term_meta((int) $term_id, 'xi_plate', true);
    $meta = is_array($meta) ? $meta : array();
    if (!$key) {
        return $meta;
    }
    return isset($meta[$key]) && $meta[$key] !== '' ? $meta[$key] : $default;
}

function xi_plate_color($term_id)
{
    $color = sanitize_hex_color(xi_plate_meta($term_id, 'color'));
    return $color ? $color : '';
}

/**
 * 版块图标：填写了图标按菜单图标的写法输出，否则取名称首字
 */
function xi_plate_icon($term, $class = '')
{
    $icon  = (string) xi_plate_meta($term->term_id, 'icon');
    $color = xi_plate_color($term->term_id);
    $style = $color ? ' style="--plate:' . esc_attr($color) . '"' : '';
    $inner = $icon !== '' ? xi_menu_icon_html($icon) : '<span class="xi-plate-letter">' . esc_html(mb_substr($term->name, 0, 1)) . '</span>';
    return '<span class="xi-plate-icon' . ($class ? ' ' . esc_attr($class) : '') . '"' . $style . ' aria-hidden="true">' . $inner . '</span>';
}

/**
 * 按分区分组的版块：array( array('section' => 分区, 'plates' => 版块[]) )
 * 没有下级的顶级版块归入「其他版块」组
 */
function xi_bbs_plate_groups()
{
    $terms = get_terms(array('taxonomy' => 'plate', 'hide_empty' => false));
    if (is_wp_error($terms) || !$terms) {
        return array();
    }
    usort($terms, function ($a, $b) {
        $oa = (int) xi_plate_meta($a->term_id, 'order', 0);
        $ob = (int) xi_plate_meta($b->term_id, 'order', 0);
        return $oa === $ob ? $a->term_id - $b->term_id : $oa - $ob;
    });

    $children = array();
    foreach ($terms as $term) {
        if ($term->parent) {
            $children[$term->parent][] = $term;
        }
    }
    $groups = array();
    $loose  = array();
    foreach ($terms as $term) {
        if ($term->parent) {
            continue;
        }
        if (!empty($children[$term->term_id])) {
            $groups[] = array('section' => $term, 'plates' => $children[$term->term_id]);
        } else {
            $loose[] = $term;
        }
    }
    if ($loose) {
        $groups[] = array('section' => null, 'plates' => $loose);
    }
    return $groups;
}

/**
 * 可发帖的版块（下级版块，以及没有下级的顶级版块）
 */
function xi_bbs_postable_plates($user_id = 0)
{
    $list = array();
    foreach (xi_bbs_plate_groups() as $group) {
        foreach ($group['plates'] as $plate) {
            if (xi_bbs_can_post_in($plate->term_id, $user_id)) {
                $list[] = array('group' => $group['section'] ? $group['section']->name : '', 'plate' => $plate);
            }
        }
    }
    return $list;
}

/**
 * 版块（含下级）今日新帖
 */
function xi_plate_today_count($term_id)
{
    static $counts = null;
    if ($counts === null) {
        $today  = current_time('Y-m-d');
        $counts = xi_cache_remember('plate_today|' . $today, 10 * MINUTE_IN_SECONDS, function () use ($today) {
            global $wpdb;
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT tt.term_id, COUNT(DISTINCT p.ID) AS total FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'plate'
                 WHERE p.post_type = 'forum_post' AND p.post_status = 'publish' AND p.post_date >= %s
                 GROUP BY tt.term_id",
                $today . ' 00:00:00'
            ), ARRAY_A);
            $map = array();
            foreach ((array) $rows as $row) {
                $map[(int) $row['term_id']] = (int) $row['total'];
            }
            return $map;
        });
    }
    return isset($counts[(int) $term_id]) ? (int) $counts[(int) $term_id] : 0;
}

/**
 * 版块（含下级）帖子总数
 */
function xi_plate_post_count($term)
{
    $count = (int) $term->count;
    $kids  = get_term_children($term->term_id, 'plate');
    if ($kids && !is_wp_error($kids)) {
        foreach ($kids as $kid) {
            $child = get_term($kid, 'plate');
            $count += $child && !is_wp_error($child) ? (int) $child->count : 0;
        }
    }
    return $count;
}

function xi_plate_mods($term_id)
{
    return array_values(array_filter(array_map('intval', (array) xi_plate_meta($term_id, 'mods', array()))));
}

/* ---------- 权限 ---------- */

/**
 * 是否某版块（或其上级分区）的版主；可编辑他人文章的角色视为全站版主
 */
function xi_bbs_is_mod($plate_id = 0, $user_id = 0)
{
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if (!$user_id) {
        return false;
    }
    if (user_can($user_id, 'edit_others_posts')) {
        return true;
    }
    if (!$plate_id) {
        return false;
    }
    foreach (array_merge(array((int) $plate_id), get_ancestors((int) $plate_id, 'plate', 'taxonomy')) as $id) {
        if (in_array($user_id, xi_plate_mods($id), true)) {
            return true;
        }
    }
    return false;
}

function xi_bbs_post_plate($post_id)
{
    $terms = get_the_terms($post_id, 'plate');
    return $terms && !is_wp_error($terms) ? $terms[0] : null;
}

/**
 * 能否管理某个帖子（置顶、加精、删除、审核）
 */
function xi_bbs_can_manage($post_id, $user_id = 0)
{
    $plate = xi_bbs_post_plate($post_id);
    return xi_bbs_is_mod($plate ? $plate->term_id : 0, $user_id);
}

/**
 * 能否在某版块发帖（不传版块时只判断用户组）
 */
function xi_bbs_can_post_in($plate_id = 0, $user_id = 0)
{
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if (!xi_bbs_enabled() || !$user_id || !xi_user_can('bbs_post', $user_id)) {
        return false;
    }
    if ($plate_id && xi_plate_meta($plate_id, 'post_auth', 'all') === 'mod') {
        return xi_bbs_is_mod($plate_id, $user_id);
    }
    return true;
}

/**
 * 能否编辑帖子，返回 true 或错误
 */
function xi_bbs_can_edit($post_id, $user_id = 0)
{
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    $post    = get_post($post_id);
    if (!$post || $post->post_type !== 'forum_post' || $post->post_status === 'trash') {
        return new WP_Error('post', __('帖子不存在', 'xi'));
    }
    if (xi_bbs_can_manage($post->ID, $user_id)) {
        return true;
    }
    if ((int) $post->post_author !== $user_id) {
        return new WP_Error('author', __('只能编辑自己的帖子', 'xi'));
    }
    if (!xi_user_can('bbs_post', $user_id)) {
        return new WP_Error('auth', __('你所在的用户组暂无发帖权限', 'xi'));
    }
    return true;
}

function xi_bbs_can_delete($post_id, $user_id = 0)
{
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    $post    = get_post($post_id);
    if (!$user_id || !$post || $post->post_type !== 'forum_post') {
        return false;
    }
    if (xi_bbs_can_manage($post->ID, $user_id)) {
        return true;
    }
    return (int) $post->post_author === $user_id && _xz('bbs_user_delete', true);
}

/* ---------- 列表查询 ---------- */

function xi_bbs_sorts()
{
    return array(
        'reply'   => __('最新回复', 'xi'),
        'new'     => __('最新发布', 'xi'),
        'hot'     => __('热门', 'xi'),
        'essence' => __('精华', 'xi'),
    );
}

function xi_bbs_current_sort()
{
    $sort = isset($_GET['sort']) ? sanitize_key($_GET['sort']) : '';
    if (!array_key_exists($sort, xi_bbs_sorts())) {
        $sort = _xz('bbs_sort', 'reply');
    }
    return array_key_exists($sort, xi_bbs_sorts()) ? $sort : 'reply';
}

/**
 * 把排序方式转成查询参数；按浏览量、最后回复排序由 xi_bbs_sort_clauses() 处理
 */
function xi_bbs_sort_args($sort)
{
    switch ($sort) {
        case 'new':
            return array('orderby' => 'date', 'order' => 'DESC');
        case 'essence':
            return array(
                'meta_query' => array(array('key' => 'xi_bbs_essence', 'value' => '1')),
                'orderby'    => 'date',
                'order'      => 'DESC',
            );
        case 'hot':
            return array('xi_sort_key' => 'xi_views');
        default:
            return array('xi_sort_key' => 'xi_last_reply');
    }
}

/**
 * 按某个数值元数据倒序，缺少该元数据的帖子按 0 计
 * 不用 meta_query 的 OR + NOT EXISTS：那样会连接帖子的全部元数据，缺值的帖子会按无关的值排序
 */
function xi_bbs_sort_clauses($clauses, $query)
{
    $key = $query->get('xi_sort_key');
    if (!$key || !in_array($key, array('xi_views', 'xi_last_reply'), true)) {
        return $clauses;
    }
    global $wpdb;
    $clauses['join']   .= $wpdb->prepare(" LEFT JOIN {$wpdb->postmeta} AS xi_sort ON (xi_sort.post_id = {$wpdb->posts}.ID AND xi_sort.meta_key = %s)", $key);
    $clauses['orderby'] = "COALESCE(xi_sort.meta_value + 0, 0) DESC, {$wpdb->posts}.post_date DESC";
    return $clauses;
}
add_filter('posts_clauses', 'xi_bbs_sort_clauses', 10, 2);

/**
 * 置顶帖：首页取全局置顶，版块页取本版块（含下级）的版块置顶与全局置顶
 */
function xi_bbs_top_ids($plate_id = 0)
{
    $args = array(
        'post_type'      => 'forum_post',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => 10,
        'no_found_rows'  => true,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => array(array('key' => 'xi_bbs_top', 'value' => $plate_id ? array('1', '2') : array('2'), 'compare' => 'IN')),
    );
    if ($plate_id) {
        $args['tax_query'] = array(array('taxonomy' => 'plate', 'terms' => (int) $plate_id));
    }
    return array_map('intval', get_posts($args));
}

/**
 * 主查询：论坛首页、版块页、话题页
 */
function xi_bbs_pre_get_posts($query)
{
    if (is_admin() || !$query->is_main_query() || !xi_bbs_enabled()) {
        return;
    }
    $is_home = $query->is_post_type_archive('forum_post');
    if (!$is_home && !$query->is_tax(array('plate', 'forum_topic'))) {
        return;
    }
    $sort = xi_bbs_current_sort();
    $query->set('post_type', 'forum_post');
    $query->set('posts_per_page', max(5, (int) _xz('bbs_per_page', 20)));
    foreach (xi_bbs_sort_args($sort) as $key => $value) {
        $query->set($key, $value);
    }

    // 置顶帖单独显示在列表最上方，不重复出现在列表中
    if ($sort !== 'essence' && !$query->is_tax('forum_topic')) {
        $plate = 0;
        if ($query->is_tax('plate')) {
            $term  = get_term_by('slug', $query->get('plate'), 'plate');
            $plate = $term ? $term->term_id : 0;
        }
        $top = xi_bbs_top_ids($plate);
        if ($top) {
            $query->set('post__not_in', $top);
            $GLOBALS['xi_bbs_top_ids'] = $top;
        }
    }

    // 版主可查看本版块待审核的帖子
    if ($query->is_tax('plate') && isset($_GET['sort']) && $_GET['sort'] === 'pending') {
        $term = get_term_by('slug', $query->get('plate'), 'plate');
        if ($term && xi_bbs_is_mod($term->term_id)) {
            $query->set('post_status', 'pending');
            $query->set('meta_query', array());
            $query->set('xi_sort_key', '');
            $query->set('orderby', 'date');
            $query->set('post__not_in', array());
            $GLOBALS['xi_bbs_top_ids'] = array();
        }
    }
}
add_action('pre_get_posts', 'xi_bbs_pre_get_posts');

/**
 * 当前列表是否在看待审核帖子
 */
function xi_bbs_is_pending_view()
{
    return is_tax('plate') && isset($_GET['sort']) && $_GET['sort'] === 'pending' && xi_bbs_is_mod(get_queried_object_id());
}

/**
 * 排序标签
 */
function xi_bbs_sort_tabs($base, $extra = array())
{
    $current = xi_bbs_is_pending_view() ? 'pending' : xi_bbs_current_sort();
    $tabs    = xi_bbs_sorts() + $extra;
    echo '<nav class="xi-bbs-tabs" aria-label="' . esc_attr__('排序', 'xi') . '">';
    foreach ($tabs as $key => $label) {
        $url = $key === _xz('bbs_sort', 'reply') ? $base : add_query_arg('sort', $key, $base);
        echo '<a href="' . esc_url($url) . '"' . ($key === $current ? ' class="is-active" aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
    }
    echo '</nav>';
}

/**
 * 论坛统计（缓存 10 分钟）
 */
function xi_bbs_stats()
{
    $stats = get_transient('xi_bbs_stats');
    if (is_array($stats)) {
        return $stats;
    }
    $today = new WP_Query(array(
        'post_type'      => 'forum_post',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'date_query'     => array(array('after' => current_time('Y-m-d') . ' 00:00:00', 'inclusive' => true)),
    ));
    $counts = wp_count_posts('forum_post');
    $stats  = array(
        'posts'  => isset($counts->publish) ? (int) $counts->publish : 0,
        'today'  => (int) $today->found_posts,
        'plates' => array_sum(array_map(function ($group) {
            return count($group['plates']);
        }, xi_bbs_plate_groups())), // 分区不计入
        'users'  => function_exists('get_user_count') ? (int) get_user_count() : (int) count_users()['total_users'],
    );
    set_transient('xi_bbs_stats', $stats, 10 * MINUTE_IN_SECONDS);
    return $stats;
}

function xi_bbs_flush_stats()
{
    delete_transient('xi_bbs_stats');
    delete_transient('xi_bbs_active_users');
}
add_action('save_post_forum_post', 'xi_bbs_flush_stats');
add_action('created_plate', 'xi_bbs_flush_stats');
add_action('delete_plate', 'xi_bbs_flush_stats');

/**
 * 近 30 天发帖最多的用户（缓存 1 小时）
 */
function xi_bbs_active_users($number = 8)
{
    $users = get_transient('xi_bbs_active_users');
    if (!is_array($users)) {
        global $wpdb;
        $since = gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS);
        $users = $wpdb->get_results($wpdb->prepare(
            "SELECT post_author AS id, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = 'forum_post' AND post_status = 'publish' AND post_date_gmt > %s GROUP BY post_author ORDER BY total DESC LIMIT 20",
            $since
        ), ARRAY_A);
        $users = is_array($users) ? $users : array();
        set_transient('xi_bbs_active_users', $users, HOUR_IN_SECONDS);
    }
    return array_slice($users, 0, $number);
}

/**
 * 帖子里的图片（列表预览用），跳过隐藏内容
 */
function xi_bbs_post_images($post = null, $limit = 3)
{
    $post    = get_post($post);
    $content = function_exists('xi_strip_hidden') ? xi_strip_hidden($post->post_content) : $post->post_content;
    if (!preg_match_all('/<img[^>]+>/i', $content, $m)) {
        return array();
    }
    $list = array();
    foreach ($m[0] as $tag) {
        if (preg_match('/wp-image-(\d+)/', $tag, $id) && ($src = wp_get_attachment_image_url((int) $id[1], 'post-thumbnail'))) {
            $list[] = $src;
        } elseif (preg_match('/src=["\']([^"\']+)["\']/i', $tag, $src)) {
            $list[] = $src[1];
        }
        if (count($list) >= $limit) {
            break;
        }
    }
    return $list;
}

/**
 * 相对时间：刚刚 / x 分钟前 / x 小时前 / x 天前 / 日期
 */
function xi_bbs_time($timestamp)
{
    $diff = time() - (int) $timestamp;
    if ($diff < 60) {
        return __('刚刚', 'xi');
    }
    // 不用 human_time_diff：站点语言为英文时它会输出 1 hour
    if ($diff < HOUR_IN_SECONDS) {
        return sprintf(__('%d 分钟前', 'xi'), (int) floor($diff / MINUTE_IN_SECONDS));
    }
    if ($diff < DAY_IN_SECONDS) {
        return sprintf(__('%d 小时前', 'xi'), (int) floor($diff / HOUR_IN_SECONDS));
    }
    if ($diff < DAY_IN_SECONDS * 7) {
        return sprintf(__('%d 天前', 'xi'), (int) floor($diff / DAY_IN_SECONDS));
    }
    return wp_date(wp_date('Y', (int) $timestamp) === wp_date('Y') ? 'm-d' : 'Y-m-d', (int) $timestamp);
}

/* ---------- 最后回复 ---------- */

function xi_bbs_touch_reply($comment)
{
    $comment = get_comment($comment);
    if (!$comment || get_post_type($comment->comment_post_ID) !== 'forum_post' || $comment->comment_approved !== '1') {
        return;
    }
    update_post_meta($comment->comment_post_ID, 'xi_last_reply', strtotime($comment->comment_date_gmt . ' UTC'));
    update_post_meta($comment->comment_post_ID, 'xi_last_reply_user', (int) $comment->user_id ? (int) $comment->user_id : $comment->comment_author);
}

/**
 * 所有新增评论（前台提交、后台回复、导入）都会经过 wp_insert_comment，未审核的由 touch 自行跳过
 */
function xi_bbs_on_comment($comment_id)
{
    xi_bbs_touch_reply($comment_id);
}
add_action('wp_insert_comment', 'xi_bbs_on_comment');

function xi_bbs_on_comment_status($comment_id, $status)
{
    if ($status === 'approve') {
        xi_bbs_touch_reply($comment_id);
    }
}
add_action('wp_set_comment_status', 'xi_bbs_on_comment_status', 10, 2);

/**
 * 新帖默认最后回复时间为发布时间，按「最新回复」排序时能排在前面
 */
function xi_bbs_on_save($post_id, $post)
{
    if (wp_is_post_revision($post_id) || get_post_meta($post_id, 'xi_last_reply', true)) {
        return;
    }
    update_post_meta($post_id, 'xi_last_reply', strtotime($post->post_date_gmt === '0000-00-00 00:00:00' ? 'now' : $post->post_date_gmt . ' UTC'));
}
add_action('save_post_forum_post', 'xi_bbs_on_save', 10, 2);

/**
 * 最后回复人的显示名
 */
function xi_bbs_last_reply_name($post_id)
{
    $who = get_post_meta($post_id, 'xi_last_reply_user', true);
    if ($who === '' || $who === false) {
        return '';
    }
    if (is_numeric($who)) {
        $user = get_userdata((int) $who);
        return $user ? $user->display_name : '';
    }
    return (string) $who;
}

/* ---------- 版块关注 ---------- */

function xi_bbs_user_follows($user_id = 0)
{
    $user_id = $user_id ? $user_id : get_current_user_id();
    return $user_id ? array_map('intval', (array) get_user_meta($user_id, 'xi_bbs_follow', true)) : array();
}

function xi_bbs_follow_button($term_id)
{
    $on = in_array((int) $term_id, xi_bbs_user_follows(), true);
    return '<button type="button" class="xi-btn' . ($on ? ' is-active' : ' xi-btn-accent') . '" data-xi-plate-follow="' . (int) $term_id . '" aria-pressed="' . ($on ? 'true' : 'false') . '">'
        . xi_icon($on ? 'check' : 'plus') . '<span>' . ($on ? esc_html__('已关注', 'xi') : esc_html__('关注', 'xi')) . '</span></button>';
}

/* ---------- Ajax ---------- */

function xi_bbs_ajax_check()
{
    if (!xi_bbs_enabled()) {
        wp_send_json(array('ok' => false, 'msg' => __('论坛已关闭', 'xi')));
    }
    if (!is_user_logged_in()) {
        wp_send_json(array('ok' => false, 'msg' => __('请先登录', 'xi'), 'login' => true));
    }
    if (!check_ajax_referer('xi_bbs', 'nonce', false)) {
        wp_send_json(array('ok' => false, 'msg' => __('页面已过期，请刷新后重试（内容已自动保存在本地）', 'xi')));
    }
    return get_current_user_id();
}

/**
 * 发帖 / 编辑帖子
 */
function xi_ajax_bbs_save()
{
    $user_id = xi_bbs_ajax_check();
    // 被禁言 / IP 被封时连已有内容也不能修改
    if (function_exists('xi_ban_notice') && ($ban_notice = xi_ban_notice($user_id))) {
        wp_send_json(array('ok' => false, 'msg' => $ban_notice));
    }
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $old     = $post_id ? get_post($post_id) : null;
    $is_mod  = false;

    if ($post_id) {
        $can = xi_bbs_can_edit($post_id, $user_id);
        if (is_wp_error($can)) {
            wp_send_json(array('ok' => false, 'msg' => $can->get_error_message()));
        }
        $is_mod = xi_bbs_can_manage($post_id, $user_id);
    } elseif (!xi_user_can('bbs_post', $user_id)) {
        wp_send_json(array('ok' => false, 'msg' => __('你所在的用户组暂无发帖权限', 'xi')));
    } elseif (!xi_user_can('nolimit', $user_id)) {
        $limit = (int) _xz('bbs_daily_limit', 10);
        if ($limit > 0) {
            $today = get_posts(array(
                'post_type'   => 'forum_post',
                'author'      => $user_id,
                'post_status' => array('publish', 'pending'),
                'date_query'  => array(array('after' => current_time('Y-m-d') . ' 00:00:00', 'inclusive' => true)),
                'fields'      => 'ids',
                'numberposts' => $limit + 1,
            ));
            if (count($today) >= $limit) {
                wp_send_json(array('ok' => false, 'msg' => sprintf(__('每天最多发布 %d 个帖子', 'xi'), $limit)));
            }
        }
    }

    $title   = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
    $plain   = trim((string) preg_replace('/\s+/u', '', wp_strip_all_tags($content)));

    if (mb_strlen($title) < 4) {
        wp_send_json(array('ok' => false, 'msg' => __('标题至少 4 个字', 'xi'), 'field' => 'title'));
    }
    $min = (int) _xz('bbs_min_length', 5);
    if (mb_strlen($plain) < $min && !preg_match('/<img\s/i', $content)) {
        wp_send_json(array('ok' => false, 'msg' => sprintf(__('内容至少 %d 个字', 'xi'), $min), 'field' => 'content'));
    }

    // 版块：必须存在且有发帖权限；版主编辑他人帖子时不受版块发帖权限限制
    $plate = isset($_POST['cat']) ? (int) $_POST['cat'] : 0;
    $term  = $plate ? get_term($plate, 'plate') : null;
    if (!$term || is_wp_error($term)) {
        wp_send_json(array('ok' => false, 'msg' => __('请选择版块', 'xi'), 'field' => 'cat'));
    }
    $old_plate = $old ? xi_bbs_post_plate($old->ID) : null;
    $same      = $old_plate && $old_plate->term_id === $term->term_id;
    if (!$same && !xi_bbs_can_post_in($term->term_id, $user_id)) {
        wp_send_json(array('ok' => false, 'msg' => __('该版块仅限版主发帖', 'xi'), 'field' => 'cat'));
    }

    if ($is_mod || xi_user_can('bbs_publish', $user_id)) {
        $status = 'publish';
    } else {
        $status = 'pending';
    }

    $data = array(
        'post_title'     => $title,
        'post_content'   => $content,
        'post_status'    => $status,
        'post_type'      => 'forum_post',
        'comment_status' => 'open',
    );
    if ($old) {
        $data['ID'] = $old->ID;
        $result     = wp_update_post(wp_slash($data), true);
    } else {
        $data['post_author'] = $user_id;
        $result              = wp_insert_post(wp_slash($data), true);
    }
    if (is_wp_error($result)) {
        wp_send_json(array('ok' => false, 'msg' => $result->get_error_message()));
    }
    $post_id = (int) $result;
    wp_set_object_terms($post_id, array($term->term_id), 'plate', false);

    // 话题：最多 3 个；不允许新建话题时只保留已有的
    $topics = isset($_POST['tags']) ? array_slice(array_filter(array_map(function ($t) {
        return mb_substr(trim(sanitize_text_field(str_replace('#', '', $t))), 0, 20);
    }, explode(',', wp_unslash($_POST['tags'])))), 0, 3) : array();
    if (!_xz('bbs_topic_create', true) && !user_can($user_id, 'manage_categories')) {
        $topics = array_values(array_filter($topics, function ($t) {
            return (bool) term_exists($t, 'forum_topic');
        }));
    }
    wp_set_object_terms($post_id, $topics, 'forum_topic', false);

    // 把本次上传的图片挂到帖子下
    if (preg_match_all('/wp-image-(\d+)/', $content, $m)) {
        foreach (array_unique(array_map('intval', $m[1])) as $att_id) {
            $att = get_post($att_id);
            if ($att && $att->post_type === 'attachment' && !$att->post_parent && (int) $att->post_author === $user_id) {
                wp_update_post(array('ID' => $att_id, 'post_parent' => $post_id));
            }
        }
    }

    if ($status === 'pending' && (!$old || $old->post_status !== 'pending') && _xz('write_notify', true)) {
        $author = get_userdata($user_id);
        xi_send_mail(
            get_option('admin_email'),
            __('有新的待审核帖子', 'xi'),
            __('新的待审核帖子', 'xi'),
            '<p>' . sprintf(esc_html__('%1$s 在「%2$s」发布了帖子《%3$s》，等待审核。', 'xi'), esc_html($author ? $author->display_name : ''), esc_html($term->name), esc_html($title)) . '</p>'
            . '<p><a href="' . esc_url(admin_url('edit.php?post_status=pending&post_type=forum_post')) . '">' . esc_html__('前往审核', 'xi') . '</a></p>'
        );
    }

    wp_send_json(array(
        'ok'       => true,
        'msg'      => $status === 'publish' ? ($old ? __('已保存', 'xi') : __('发布成功', 'xi')) : __('已提交，审核通过后将公开显示', 'xi'),
        'post_id'  => $post_id,
        'status'   => $status,
        'link'     => $status === 'publish' ? get_permalink($post_id) : get_term_link($term),
        'edit_url' => xi_bbs_new_url(0, $post_id),
        'uc_url'   => get_term_link($term),
    ));
}
add_action('wp_ajax_xi_bbs_save', 'xi_ajax_bbs_save');

function xi_ajax_bbs_upload()
{
    $user_id = xi_bbs_ajax_check();
    if (!xi_user_can('bbs_post', $user_id) || !xi_user_can('upload', $user_id)) {
        wp_send_json(array('ok' => false, 'msg' => __('你所在的用户组暂无上传图片权限', 'xi')));
    }
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if ($post_id && is_wp_error(xi_bbs_can_edit($post_id, $user_id))) {
        $post_id = 0;
    }
    $result = xi_upload_image($user_id, $post_id);
    wp_send_json(is_wp_error($result) ? array('ok' => false, 'msg' => $result->get_error_message()) : array('ok' => true) + $result);
}
add_action('wp_ajax_xi_bbs_upload', 'xi_ajax_bbs_upload');

/**
 * 帖子管理：置顶、加精、删除、审核通过
 */
function xi_ajax_bbs_manage()
{
    $user_id = xi_bbs_ajax_check();
    $post_id = isset($_POST['post']) ? (int) $_POST['post'] : 0;
    $do      = isset($_POST['do']) ? sanitize_key($_POST['do']) : '';
    $post    = get_post($post_id);
    if (!$post || $post->post_type !== 'forum_post') {
        wp_send_json(array('ok' => false, 'msg' => __('帖子不存在', 'xi')));
    }

    if ($do === 'delete') {
        if (!xi_bbs_can_delete($post_id, $user_id)) {
            wp_send_json(array('ok' => false, 'msg' => __('没有权限删除该帖子', 'xi')));
        }
        $plate = xi_bbs_post_plate($post_id);
        wp_trash_post($post_id);
        xi_bbs_flush_stats();
        wp_send_json(array('ok' => true, 'msg' => __('帖子已删除', 'xi'), 'redirect' => $plate ? get_term_link($plate) : xi_bbs_url()));
    }

    if (!xi_bbs_can_manage($post_id, $user_id)) {
        wp_send_json(array('ok' => false, 'msg' => __('只有版主可以执行此操作', 'xi')));
    }
    switch ($do) {
        case 'top0':
        case 'top1':
        case 'top2':
            $level = (int) substr($do, 3);
            if ($level === 2 && !user_can($user_id, 'edit_others_posts')) {
                wp_send_json(array('ok' => false, 'msg' => __('全局置顶需要管理员或编辑操作', 'xi')));
            }
            $level ? update_post_meta($post_id, 'xi_bbs_top', (string) $level) : delete_post_meta($post_id, 'xi_bbs_top');
            $msg = array(__('已取消置顶', 'xi'), __('已在版块置顶', 'xi'), __('已全局置顶', 'xi'));
            wp_send_json(array('ok' => true, 'msg' => $msg[$level], 'reload' => true));
            break;
        case 'essence':
            $on = !get_post_meta($post_id, 'xi_bbs_essence', true);
            $on ? update_post_meta($post_id, 'xi_bbs_essence', '1') : delete_post_meta($post_id, 'xi_bbs_essence');
            wp_send_json(array('ok' => true, 'msg' => $on ? __('已设为精华', 'xi') : __('已取消精华', 'xi'), 'reload' => true));
            break;
        case 'approve':
            if ($post->post_status !== 'pending') {
                wp_send_json(array('ok' => false, 'msg' => __('该帖子不在待审核状态', 'xi')));
            }
            wp_publish_post($post_id);
            xi_bbs_flush_stats();
            wp_send_json(array('ok' => true, 'msg' => __('已审核通过', 'xi'), 'reload' => true));
            break;
    }
    wp_send_json(array('ok' => false, 'msg' => __('未知操作', 'xi')));
}
add_action('wp_ajax_xi_bbs_manage', 'xi_ajax_bbs_manage');

function xi_ajax_bbs_follow()
{
    $user_id = xi_bbs_ajax_check();
    $term_id = isset($_POST['plate']) ? (int) $_POST['plate'] : 0;
    $term    = get_term($term_id, 'plate');
    if (!$term || is_wp_error($term)) {
        wp_send_json(array('ok' => false, 'msg' => __('版块不存在', 'xi')));
    }
    $list  = xi_bbs_user_follows($user_id);
    $count = (int) get_term_meta($term_id, 'xi_follow_count', true);
    if (in_array($term_id, $list, true)) {
        $list  = array_values(array_diff($list, array($term_id)));
        $count = max(0, $count - 1);
        $on    = false;
    } else {
        $list[] = $term_id;
        $count++;
        $on = true;
    }
    update_user_meta($user_id, 'xi_bbs_follow', $list);
    update_term_meta($term_id, 'xi_follow_count', $count);
    wp_send_json(array('ok' => true, 'msg' => $on ? __('已关注版块', 'xi') : __('已取消关注', 'xi'), 'active' => $on, 'count' => $count));
}
add_action('wp_ajax_xi_bbs_follow', 'xi_ajax_bbs_follow');
add_action('wp_ajax_nopriv_xi_bbs_follow', 'xi_ajax_bbs_follow'); // 游客点关注时返回登录提示

/* ---------- 前台资源 ---------- */

function xi_bbs_enqueue()
{
    if (!xi_bbs_enabled()) {
        return;
    }
    $uri = get_template_directory_uri();
    if (xi_is_bbs_new() && is_user_logged_in()) {
        wp_enqueue_script('xi-editor', $uri . '/assets/js/editor.js', array(), XI_VERSION, true);
        wp_localize_script('xi-editor', 'xiWrite', array(
            'ajax'         => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('xi_bbs'),
            'upload'       => xi_user_can('upload'),
            'maxSize'      => max(1, (int) _xz('write_upload_size', 5)),
            'points'       => xi_points_enabled(),
            'vip'          => xi_vip_enabled(),
            'pointName'    => xi_points_name(),
            'saveAction'   => 'xi_bbs_save',
            'uploadAction' => 'xi_bbs_upload',
            'store'        => 'xi-bbs-',
            'maxTags'      => 3,
        ));
    }
    if (xi_is_bbs() || is_author()) {
        wp_enqueue_script('xi-bbs', $uri . '/assets/js/bbs.js', array('xi-main'), XI_VERSION, true);
        wp_localize_script('xi-bbs', 'xiBbs', array(
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => is_user_logged_in() ? wp_create_nonce('xi_bbs') : '',
        ));
    }
}
add_action('wp_enqueue_scripts', 'xi_bbs_enqueue');

/* ---------- 模板片段 ---------- */

/**
 * 面包屑项：论坛 › 分区 › 版块 › 帖子
 */
function xi_bbs_breadcrumb_items()
{
    if (!xi_is_bbs() && !xi_is_bbs_new()) {
        return array();
    }
    $items = array(array(xi_bbs_name(), is_post_type_archive('forum_post') ? '' : xi_bbs_url()));
    $plate = null;
    if (is_tax('plate')) {
        $plate = get_queried_object();
    } elseif (is_singular('forum_post')) {
        $plate = xi_bbs_post_plate(get_queried_object_id());
    }
    if ($plate) {
        foreach (array_reverse(get_ancestors($plate->term_id, 'plate', 'taxonomy')) as $parent) {
            $p = get_term($parent, 'plate');
            if ($p && !is_wp_error($p)) {
                $items[] = array($p->name, get_term_link($p));
            }
        }
        $items[] = array($plate->name, is_tax('plate') ? '' : get_term_link($plate));
    }
    if (is_tax('forum_topic')) {
        $items[] = array('#' . single_term_title('', false) . '#', '');
    } elseif (is_singular('forum_post')) {
        $items[] = array(__('正文', 'xi'), '');
    } elseif (xi_is_bbs_new()) {
        $items[] = array(!empty($_GET['post']) ? __('编辑帖子', 'xi') : __('发布帖子', 'xi'), '');
    }
    return $items;
}

/**
 * 帖子角标
 */
function xi_bbs_flags($post_id)
{
    $html = '';
    $top  = (int) get_post_meta($post_id, 'xi_bbs_top', true);
    if ($top) {
        $html .= '<span class="xi-bbs-flag is-top">' . ($top === 2 ? esc_html__('全局置顶', 'xi') : esc_html__('置顶', 'xi')) . '</span>';
    }
    if (get_post_meta($post_id, 'xi_bbs_essence', true)) {
        $html .= '<span class="xi-bbs-flag is-essence">' . esc_html__('精华', 'xi') . '</span>';
    }
    if (get_post_status($post_id) === 'pending') {
        $html .= '<span class="xi-bbs-flag is-pending">' . esc_html__('待审核', 'xi') . '</span>';
    }
    return $html;
}

function xi_bbs_topics_html($post_id)
{
    $topics = get_the_terms($post_id, 'forum_topic');
    if (!$topics || is_wp_error($topics)) {
        return '';
    }
    $html = '<div class="xi-bbs-topics">';
    foreach ($topics as $topic) {
        $html .= '<a href="' . esc_url(get_term_link($topic)) . '">#' . esc_html($topic->name) . '#</a>';
    }
    return $html . '</div>';
}

/**
 * 帖子管理菜单（作者与版主可见）
 */
function xi_bbs_manage_menu($post_id)
{
    $user_id = get_current_user_id();
    if (!$user_id) {
        return '';
    }
    $items = array();
    if (!is_wp_error(xi_bbs_can_edit($post_id, $user_id))) {
        $items[] = '<a href="' . esc_url(xi_bbs_new_url(0, $post_id)) . '">' . xi_icon('edit') . esc_html__('编辑', 'xi') . '</a>';
    }
    if (xi_bbs_can_manage($post_id, $user_id)) {
        if (get_post_status($post_id) === 'pending') {
            $items[] = '<button type="button" data-xi-bbs="approve" data-post="' . (int) $post_id . '">' . xi_icon('check') . esc_html__('审核通过', 'xi') . '</button>';
        }
        $top = (int) get_post_meta($post_id, 'xi_bbs_top', true);
        if ($top) {
            $items[] = '<button type="button" data-xi-bbs="top0" data-post="' . (int) $post_id . '">' . xi_icon('pin') . esc_html__('取消置顶', 'xi') . '</button>';
        }
        if ($top !== 1) {
            $items[] = '<button type="button" data-xi-bbs="top1" data-post="' . (int) $post_id . '">' . xi_icon('pin') . esc_html__('版块置顶', 'xi') . '</button>';
        }
        if ($top !== 2 && user_can($user_id, 'edit_others_posts')) {
            $items[] = '<button type="button" data-xi-bbs="top2" data-post="' . (int) $post_id . '">' . xi_icon('pin') . esc_html__('全局置顶', 'xi') . '</button>';
        }
        $items[] = '<button type="button" data-xi-bbs="essence" data-post="' . (int) $post_id . '">' . xi_icon('star') . (get_post_meta($post_id, 'xi_bbs_essence', true) ? esc_html__('取消精华', 'xi') : esc_html__('设为精华', 'xi')) . '</button>';
    }
    if (xi_bbs_can_delete($post_id, $user_id)) {
        $items[] = '<button type="button" class="is-danger" data-xi-bbs="delete" data-post="' . (int) $post_id . '">' . xi_icon('trash') . esc_html__('删除', 'xi') . '</button>';
    }
    if (!$items) {
        return '';
    }
    return '<details class="xi-bbs-manage"><summary class="xi-btn-icon" aria-label="' . esc_attr__('管理', 'xi') . '">' . xi_icon('more') . '</summary><div class="xi-bbs-manage-menu xi-glass">' . implode('', $items) . '</div></details>';
}

/**
 * 论坛默认侧栏（论坛页没有添加小工具时）
 */
function xi_bbs_sidebar()
{
    $stats = xi_bbs_stats();
    // 论坛首页顶部已有统计与发帖按钮
    if (!is_post_type_archive('forum_post')) {
        xi_bbs_side_post($stats);
    }
    xi_bbs_side_lists();
}

function xi_bbs_side_post($stats)
{
    echo '<div class="xi-widget xi-card xi-bbs-side-post">';
    echo '<div class="xi-bbs-side-stats">';
    echo '<div><b>' . esc_html(xi_number($stats['posts'])) . '</b><span>' . esc_html__('帖子', 'xi') . '</span></div>';
    echo '<div><b>' . esc_html(xi_number($stats['today'])) . '</b><span>' . esc_html__('今日', 'xi') . '</span></div>';
    echo '<div><b>' . esc_html(xi_number($stats['users'])) . '</b><span>' . esc_html__('成员', 'xi') . '</span></div>';
    echo '</div>';
    $plate = is_tax('plate') ? get_queried_object_id() : 0;
    if (is_user_logged_in()) {
        if (xi_bbs_can_post_in($plate)) {
            echo '<a class="xi-btn xi-btn-accent xi-btn-block" href="' . esc_url(xi_bbs_new_url($plate)) . '">' . xi_icon('edit') . esc_html__('发布帖子', 'xi') . '</a>';
        }
    } else {
        echo '<div class="xi-bbs-side-login">' . xi_login_buttons() . '</div>';
    }
    echo '</div>';
}

function xi_bbs_side_lists()
{
    $topics = get_terms(array('taxonomy' => 'forum_topic', 'orderby' => 'count', 'order' => 'DESC', 'number' => 12, 'hide_empty' => true));
    if ($topics && !is_wp_error($topics)) {
        echo '<div class="xi-widget xi-card"><div class="xi-widget-head"><h3 class="xi-widget-title">' . esc_html__('热门话题', 'xi') . '</h3></div><div class="xi-bbs-hot-topics">';
        foreach ($topics as $i => $topic) {
            echo '<a href="' . esc_url(get_term_link($topic)) . '"><i>' . ($i + 1) . '</i><span>#' . esc_html($topic->name) . '#</span><small>' . (int) $topic->count . '</small></a>';
        }
        echo '</div></div>';
    }

    $users = xi_bbs_active_users();
    if ($users) {
        echo '<div class="xi-widget xi-card"><div class="xi-widget-head"><h3 class="xi-widget-title">' . esc_html__('活跃用户', 'xi') . '</h3></div><div class="xi-bbs-active-users">';
        foreach ($users as $row) {
            $user = get_userdata((int) $row['id']);
            if (!$user) {
                continue;
            }
            echo '<a href="' . esc_url(get_author_posts_url($user->ID)) . '" title="' . esc_attr($user->display_name) . '">' . get_avatar($user->ID, 44) . '<span>' . esc_html($user->display_name) . '</span><small>' . sprintf(esc_html__('%d 帖', 'xi'), (int) $row['total']) . '</small></a>';
        }
        echo '</div></div>';
    }
}

/**
 * 论坛页面有版块图标用了 Font Awesome 时加载图标字体
 */
function xi_bbs_fa_used($used)
{
    if ($used || !xi_bbs_enabled() || (!xi_is_bbs() && !is_author())) {
        return $used;
    }
    foreach ((array) get_terms(array('taxonomy' => 'plate', 'hide_empty' => false, 'fields' => 'ids')) as $id) {
        if (xi_menu_icon_type((string) xi_plate_meta($id, 'icon')) === 'fa') {
            return true;
        }
    }
    return false;
}
add_filter('xi_fa_used', 'xi_bbs_fa_used');

/* ---------- 积分：发帖与发文同样奖励 ---------- */

function xi_bbs_points_types($types)
{
    $types[] = 'forum_post';
    return $types;
}
add_filter('xi_points_post_types', 'xi_bbs_points_types');

/* ---------- 后台：版块字段 ---------- */

function xi_plate_fields()
{
    return array(
        'icon'      => array(__('图标', 'xi'), 'text', __('主题图标名（home、fire、book…）、emoji、Font Awesome 类名或图片地址，留空显示名称首字', 'xi')),
        'color'     => array(__('主色', 'xi'), 'color', __('图标底色与版块页顶部色彩，留空用主题色', 'xi')),
        'cover'     => array(__('封面图', 'xi'), 'url', __('版块页顶部背景图地址，留空使用主色渐变', 'xi')),
        'mods'      => array(__('版主', 'xi'), 'users', __('填写用户名，多个用英文逗号分隔；版主可置顶、加精、编辑和删除本版块（含下级）的帖子', 'xi')),
        'post_auth' => array(__('发帖权限', 'xi'), 'select', ''),
        'notice'    => array(__('版块公告', 'xi'), 'textarea', __('显示在版块页帖子列表上方，支持简单 HTML', 'xi')),
        'order'     => array(__('排序', 'xi'), 'number', __('数字越小越靠前', 'xi')),
    );
}

function xi_plate_field_input($key, $field, $value)
{
    $name = 'xi_plate[' . $key . ']';
    switch ($field[1]) {
        case 'color':
            return '<input type="text" name="' . $name . '" value="' . esc_attr($value) . '" class="xi-plate-color" placeholder="#2bb39a">';
        case 'textarea':
            return '<textarea name="' . $name . '" rows="3" class="large-text">' . esc_textarea($value) . '</textarea>';
        case 'number':
            return '<input type="number" name="' . $name . '" value="' . esc_attr($value) . '" class="small-text">';
        case 'select':
            return '<select name="' . $name . '"><option value="all"' . selected($value, 'all', false) . '>' . esc_html__('所有有发帖权限的用户', 'xi') . '</option><option value="mod"' . selected($value, 'mod', false) . '>' . esc_html__('仅版主与管理员', 'xi') . '</option></select>';
        case 'users':
            $logins = array();
            foreach ((array) $value as $uid) {
                $user = get_userdata((int) $uid);
                if ($user) {
                    $logins[] = $user->user_login;
                }
            }
            return '<input type="text" name="' . $name . '" value="' . esc_attr(implode(',', $logins)) . '" class="regular-text">';
        default:
            return '<input type="text" name="' . $name . '" value="' . esc_attr($value) . '" class="regular-text">';
    }
}

function xi_plate_add_fields()
{
    foreach (xi_plate_fields() as $key => $field) {
        echo '<div class="form-field"><label>' . esc_html($field[0]) . '</label>' . xi_plate_field_input($key, $field, $key === 'post_auth' ? 'all' : '');
        echo $field[2] ? '<p>' . esc_html($field[2]) . '</p>' : '';
        echo '</div>';
    }
    wp_nonce_field('xi_plate_save', 'xi_plate_nonce');
}
add_action('plate_add_form_fields', 'xi_plate_add_fields');

function xi_plate_edit_fields($term)
{
    $meta = xi_plate_meta($term->term_id);
    foreach (xi_plate_fields() as $key => $field) {
        $value = isset($meta[$key]) ? $meta[$key] : ($key === 'post_auth' ? 'all' : '');
        echo '<tr class="form-field"><th scope="row"><label>' . esc_html($field[0]) . '</label></th><td>' . xi_plate_field_input($key, $field, $value);
        echo $field[2] ? '<p class="description">' . esc_html($field[2]) . '</p>' : '';
        echo '</td></tr>';
    }
    wp_nonce_field('xi_plate_save', 'xi_plate_nonce');
}
add_action('plate_edit_form_fields', 'xi_plate_edit_fields');

function xi_plate_save($term_id)
{
    if (empty($_POST['xi_plate_nonce']) || !wp_verify_nonce(sanitize_key($_POST['xi_plate_nonce']), 'xi_plate_save') || !current_user_can('manage_categories')) {
        return;
    }
    $in   = isset($_POST['xi_plate']) ? wp_unslash((array) $_POST['xi_plate']) : array();
    $get  = function ($key) use ($in) {
        return isset($in[$key]) ? (string) $in[$key] : '';
    };
    $icon = trim($get('icon'));
    $mods = array();
    foreach (array_filter(array_map('trim', explode(',', str_replace('，', ',', $get('mods'))))) as $login) {
        $user = get_user_by('login', $login);
        if ($user) {
            $mods[] = (int) $user->ID;
        }
    }
    update_term_meta($term_id, 'xi_plate', array(
        'icon'      => preg_match('#^(https?:)?//|^/[^/]#i', $icon) ? esc_url_raw($icon) : sanitize_text_field($icon),
        'color'     => (string) sanitize_hex_color($get('color')),
        'cover'     => esc_url_raw($get('cover')),
        'mods'      => array_values(array_unique($mods)),
        'post_auth' => $get('post_auth') === 'mod' ? 'mod' : 'all',
        'notice'    => wp_kses_post($get('notice')),
        'order'     => (int) $get('order'),
    ));
}
add_action('created_plate', 'xi_plate_save');
add_action('edited_plate', 'xi_plate_save');

/* ---------- 后台：帖子置顶 / 精华 ---------- */

function xi_bbs_meta_box()
{
    add_meta_box('xi_bbs_flags', __('帖子状态', 'xi'), 'xi_bbs_meta_box_html', 'forum_post', 'side');
}
add_action('add_meta_boxes', 'xi_bbs_meta_box');

function xi_bbs_meta_box_html($post)
{
    $top = (int) get_post_meta($post->ID, 'xi_bbs_top', true);
    wp_nonce_field('xi_bbs_flags', 'xi_bbs_flags_nonce');
    echo '<p><label for="xi-bbs-top"><strong>' . esc_html__('置顶', 'xi') . '</strong></label><br><select id="xi-bbs-top" name="xi_bbs_top" style="width:100%">';
    foreach (array(__('不置顶', 'xi'), __('版块置顶', 'xi'), __('全局置顶（论坛首页）', 'xi')) as $i => $label) {
        echo '<option value="' . $i . '"' . selected($top, $i, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';
    echo '<p><label><input type="checkbox" name="xi_bbs_essence" value="1"' . checked((bool) get_post_meta($post->ID, 'xi_bbs_essence', true), true, false) . '> ' . esc_html__('精华帖', 'xi') . '</label></p>';
}

function xi_bbs_meta_box_save($post_id)
{
    if (empty($_POST['xi_bbs_flags_nonce']) || !wp_verify_nonce(sanitize_key($_POST['xi_bbs_flags_nonce']), 'xi_bbs_flags')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    $top = isset($_POST['xi_bbs_top']) ? max(0, min(2, (int) $_POST['xi_bbs_top'])) : 0;
    $top ? update_post_meta($post_id, 'xi_bbs_top', (string) $top) : delete_post_meta($post_id, 'xi_bbs_top');
    !empty($_POST['xi_bbs_essence']) ? update_post_meta($post_id, 'xi_bbs_essence', '1') : delete_post_meta($post_id, 'xi_bbs_essence');
}
add_action('save_post_forum_post', 'xi_bbs_meta_box_save');

function xi_bbs_admin_columns($columns)
{
    $columns['xi_bbs'] = __('状态', 'xi');
    return $columns;
}
add_filter('manage_forum_post_posts_columns', 'xi_bbs_admin_columns');

function xi_bbs_admin_column($column, $post_id)
{
    if ($column === 'xi_bbs') {
        echo wp_kses_post(xi_bbs_flags($post_id)) . ' <span title="' . esc_attr__('浏览', 'xi') . '">' . esc_html(xi_number(xi_get_views($post_id))) . '</span>';
    }
}
add_action('manage_forum_post_posts_custom_column', 'xi_bbs_admin_column', 10, 2);

function xi_bbs_admin_css()
{
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'forum_post') {
        echo '<style>.xi-bbs-flag{display:inline-block;margin:0 4px 2px 0;padding:0 6px;border-radius:4px;font-size:12px;line-height:20px;background:#f0f0f1}.xi-bbs-flag.is-top{background:#fde8e8;color:#b42318}.xi-bbs-flag.is-essence{background:#fff4d6;color:#a15c07}</style>';
    }
}
add_action('admin_head', 'xi_bbs_admin_css');
