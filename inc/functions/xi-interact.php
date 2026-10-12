<?php
/**
 * 文章互动与站点增强：浏览量、点赞、收藏、关注、分享、打赏、面包屑、结构化数据、全站公告
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------- 浏览量 ---------- */

function xi_get_views($post_id)
{
    return (int) get_post_meta($post_id, 'xi_views', true);
}

/**
 * 1.2 万这样的简写
 */
function xi_number($num)
{
    $num = (int) $num;
    return $num >= 10000 ? round($num / 10000, 1) . __('万', 'xi') : (string) $num;
}

/**
 * 浏览量由前端异步上报，页面缓存时也能计数；同一浏览器会话内只计一次（前端控制）
 */
function xi_ajax_view()
{
    $post_id = isset($_POST['post']) ? (int) $_POST['post'] : 0;
    if (!_xz('views_s', true) || get_post_status($post_id) !== 'publish') {
        wp_send_json(array('ok' => false));
    }
    $views = xi_get_views($post_id) + 1;
    update_post_meta($post_id, 'xi_views', $views);
    wp_send_json(array('ok' => true, 'views' => $views));
}
add_action('wp_ajax_xi_view', 'xi_ajax_view');
add_action('wp_ajax_nopriv_xi_view', 'xi_ajax_view');

/**
 * 热门文章小工具可按浏览量排序
 */
function xi_posts_orderby_views($query_args)
{
    if (isset($query_args['orderby']) && $query_args['orderby'] === 'views') {
        $query_args['meta_key'] = 'xi_views';
        $query_args['orderby']  = 'meta_value_num';
        $query_args['order']    = 'DESC';
    }
    return $query_args;
}
add_filter('xi_widget_posts_query', 'xi_posts_orderby_views');

/* ---------- 点赞 ---------- */

function xi_get_likes($post_id)
{
    return (int) get_post_meta($post_id, 'xi_likes', true);
}

/**
 * 当前访客是否已点赞：登录用户记在用户资料里，游客按 IP 记一天
 */
function xi_user_liked($post_id, $user_id = 0)
{
    $user_id = $user_id ? $user_id : get_current_user_id();
    if ($user_id) {
        return in_array((int) $post_id, (array) get_user_meta($user_id, 'xi_liked', true), true);
    }
    return (bool) get_transient('xi_like_' . md5(xi_client_ip() . '|' . $post_id));
}

function xi_ajax_like()
{
    check_ajax_referer('xi_post', 'nonce');
    $post_id = isset($_POST['post']) ? (int) $_POST['post'] : 0;
    if (!_xz('like_s', true) || get_post_status($post_id) !== 'publish') {
        wp_send_json(array('ok' => false, 'msg' => __('暂不支持点赞', 'xi')));
    }
    $likes   = xi_get_likes($post_id);
    $user_id = get_current_user_id();

    if ($user_id) {
        $list = array_map('intval', (array) get_user_meta($user_id, 'xi_liked', true));
        if (in_array($post_id, $list, true)) {
            // 登录用户可以取消点赞
            $list  = array_values(array_diff($list, array($post_id)));
            $likes = max(0, $likes - 1);
            $done  = false;
        } else {
            array_unshift($list, $post_id);
            $list  = array_slice($list, 0, 1000);
            $likes++;
            $done = true;
        }
        update_user_meta($user_id, 'xi_liked', $list);
    } else {
        $key = 'xi_like_' . md5(xi_client_ip() . '|' . $post_id);
        if (get_transient($key)) {
            wp_send_json(array('ok' => false, 'msg' => __('已经点过赞了', 'xi')));
        }
        set_transient($key, 1, DAY_IN_SECONDS);
        $likes++;
        $done = true;
    }
    update_post_meta($post_id, 'xi_likes', $likes);
    wp_send_json(array('ok' => true, 'msg' => $done ? __('感谢点赞', 'xi') : __('已取消点赞', 'xi'), 'active' => $done, 'count' => $likes));
}
add_action('wp_ajax_xi_like', 'xi_ajax_like');
add_action('wp_ajax_nopriv_xi_like', 'xi_ajax_like');

/* ---------- 收藏 ---------- */

function xi_get_favorites($user_id)
{
    return array_map('intval', (array) get_user_meta($user_id, 'xi_favs', true));
}

function xi_user_faved($post_id, $user_id = 0)
{
    $user_id = $user_id ? $user_id : get_current_user_id();
    return $user_id && in_array((int) $post_id, xi_get_favorites($user_id), true);
}

function xi_ajax_favorite()
{
    check_ajax_referer('xi_post', 'nonce');
    $user_id = get_current_user_id();
    $post_id = isset($_POST['post']) ? (int) $_POST['post'] : 0;
    if (!$user_id) {
        wp_send_json(array('ok' => false, 'msg' => __('登录后才能收藏', 'xi'), 'login' => true));
    }
    if (!_xz('fav_s', true) || get_post_status($post_id) !== 'publish') {
        wp_send_json(array('ok' => false, 'msg' => __('暂不支持收藏', 'xi')));
    }
    $list  = xi_get_favorites($user_id);
    $count = (int) get_post_meta($post_id, 'xi_favs', true);
    if (in_array($post_id, $list, true)) {
        $list  = array_values(array_diff($list, array($post_id)));
        $count = max(0, $count - 1);
        $done  = false;
    } else {
        array_unshift($list, $post_id);
        $list = array_slice($list, 0, 500);
        $count++;
        $done = true;
    }
    update_user_meta($user_id, 'xi_favs', $list);
    update_post_meta($post_id, 'xi_favs', $count);
    wp_send_json(array('ok' => true, 'msg' => $done ? __('已收藏，可在用户中心查看', 'xi') : __('已取消收藏', 'xi'), 'active' => $done, 'count' => $count));
}
add_action('wp_ajax_xi_favorite', 'xi_ajax_favorite');
add_action('wp_ajax_nopriv_xi_favorite', 'xi_ajax_favorite');

/* ---------- 关注 ---------- */

function xi_follow_enabled()
{
    return (bool) _xz('follow_s', true);
}

function xi_get_following($user_id)
{
    return array_map('intval', (array) get_user_meta($user_id, 'xi_following', true));
}

function xi_get_followers_count($user_id)
{
    return (int) get_user_meta($user_id, 'xi_followers', true);
}

function xi_is_following($target_id, $user_id = 0)
{
    $user_id = $user_id ? $user_id : get_current_user_id();
    return $user_id && in_array((int) $target_id, xi_get_following($user_id), true);
}

function xi_ajax_follow()
{
    check_ajax_referer('xi_post', 'nonce');
    $user_id = get_current_user_id();
    $target  = isset($_POST['user']) ? (int) $_POST['user'] : 0;
    if (!$user_id) {
        wp_send_json(array('ok' => false, 'msg' => __('登录后才能关注', 'xi'), 'login' => true));
    }
    if (!xi_follow_enabled() || !get_userdata($target) || $target === $user_id) {
        wp_send_json(array('ok' => false, 'msg' => __('无法关注该用户', 'xi')));
    }
    $list      = xi_get_following($user_id);
    $followers = xi_get_followers_count($target);
    if (in_array($target, $list, true)) {
        $list      = array_values(array_diff($list, array($target)));
        $followers = max(0, $followers - 1);
        $done      = false;
    } else {
        if (count($list) >= 1000) {
            wp_send_json(array('ok' => false, 'msg' => __('关注人数已达上限', 'xi')));
        }
        array_unshift($list, $target);
        $followers++;
        $done = true;
    }
    update_user_meta($user_id, 'xi_following', $list);
    update_user_meta($target, 'xi_followers', $followers);
    wp_send_json(array('ok' => true, 'msg' => $done ? __('已关注', 'xi') : __('已取消关注', 'xi'), 'active' => $done, 'count' => $followers));
}
add_action('wp_ajax_xi_follow', 'xi_ajax_follow');
add_action('wp_ajax_nopriv_xi_follow', 'xi_ajax_follow');

/**
 * 关注按钮
 */
function xi_follow_button($target_id)
{
    if (!xi_follow_enabled() || (int) $target_id === get_current_user_id()) {
        return '';
    }
    $on = xi_is_following($target_id);
    return '<button type="button" class="xi-btn' . ($on ? '' : ' xi-btn-accent') . ' xi-follow-btn' . ($on ? ' is-active' : '') . '" data-xi-follow="' . (int) $target_id . '">'
        . '<span class="xi-follow-on">' . xi_icon('check') . esc_html__('已关注', 'xi') . '</span>'
        . '<span class="xi-follow-off">' . xi_icon('user') . esc_html__('关注', 'xi') . '</span></button>';
}

/* ---------- 文章互动栏 ---------- */

function xi_share_links($post_id)
{
    $url   = rawurlencode(get_permalink($post_id));
    $title = rawurlencode(get_the_title($post_id));
    $pic   = has_post_thumbnail($post_id) ? rawurlencode(get_the_post_thumbnail_url($post_id, 'large')) : '';
    return array(
        'weibo'  => array(__('微博', 'xi'), 'https://service.weibo.com/share/share.php?url=' . $url . '&title=' . $title . '&pic=' . $pic),
        'qq'     => array('QQ', 'https://connect.qq.com/widget/shareqq/index.html?url=' . $url . '&title=' . $title . '&pics=' . $pic),
        'qzone'  => array(__('QQ空间', 'xi'), 'https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=' . $url . '&title=' . $title . '&pics=' . $pic),
        'x'      => array('X', 'https://x.com/intent/post?url=' . $url . '&text=' . $title),
    );
}

function xi_post_actions()
{
    $post_id = get_the_ID();
    $buttons = '';

    if (_xz('like_s', true)) {
        // 游客的点赞状态由前端从本地记录恢复，省去每次浏览都查一次数据库；防重复点赞仍在服务端判断
        $on       = is_user_logged_in() ? xi_user_liked($post_id) : false;
        $buttons .= '<button type="button" class="xi-action' . ($on ? ' is-active' : '') . '" data-xi-like="' . $post_id . '" aria-pressed="' . ($on ? 'true' : 'false') . '">'
            . xi_icon('heart') . '<span>' . esc_html__('点赞', 'xi') . '</span><em>' . xi_number(xi_get_likes($post_id)) . '</em></button>';
    }
    if (_xz('fav_s', true)) {
        $on       = xi_user_faved($post_id);
        $buttons .= '<button type="button" class="xi-action' . ($on ? ' is-active' : '') . '" data-xi-fav="' . $post_id . '" aria-pressed="' . ($on ? 'true' : 'false') . '">'
            . xi_icon('star') . '<span>' . esc_html__('收藏', 'xi') . '</span><em>' . xi_number(get_post_meta($post_id, 'xi_favs', true)) . '</em></button>';
    }
    if (_xz('share_s', true)) {
        $buttons .= '<button type="button" class="xi-action" data-xi-modal="xi-share">' . xi_icon('share') . '<span>' . esc_html__('分享', 'xi') . '</span></button>';
    }
    if (_xz('reward_s', false) && (_xz('reward_wechat') || _xz('reward_alipay'))) {
        $buttons .= '<button type="button" class="xi-action is-reward" data-xi-modal="xi-reward">' . xi_icon('gift') . '<span>' . esc_html__('打赏', 'xi') . '</span></button>';
    }
    if (!$buttons) {
        return;
    }
    echo '<div class="xi-post-actions">' . $buttons . '</div>';

    if (_xz('share_s', true)) {
        echo '<div class="xi-modal" id="xi-share" aria-hidden="true"><div class="xi-modal-mask" data-modal-close></div><div class="xi-modal-box xi-glass" role="dialog" aria-modal="true">';
        echo '<button type="button" class="xi-btn-icon xi-modal-close" data-modal-close aria-label="' . esc_attr__('关闭', 'xi') . '">' . xi_icon('close') . '</button>';
        echo '<h3 class="xi-modal-title">' . esc_html__('分享文章', 'xi') . '</h3><div class="xi-share-grid">';
        foreach (xi_share_links($post_id) as $key => $link) {
            echo '<a class="xi-share-' . esc_attr($key) . '" href="' . esc_url($link[1]) . '" target="_blank" rel="noopener nofollow">' . esc_html($link[0]) . '</a>';
        }
        echo '</div><div class="xi-share-copy"><input type="text" readonly value="' . esc_attr(get_permalink($post_id)) . '" aria-label="' . esc_attr__('文章链接', 'xi') . '"><button type="button" class="xi-btn xi-btn-accent" data-xi-copy>' . esc_html__('复制链接', 'xi') . '</button></div>';
        echo '<p class="xi-modal-tip">' . esc_html__('复制链接后可粘贴到微信等应用中分享', 'xi') . '</p></div></div>';
    }
    if (_xz('reward_s', false) && (_xz('reward_wechat') || _xz('reward_alipay'))) {
        echo '<div class="xi-modal" id="xi-reward" aria-hidden="true"><div class="xi-modal-mask" data-modal-close></div><div class="xi-modal-box xi-glass" role="dialog" aria-modal="true">';
        echo '<button type="button" class="xi-btn-icon xi-modal-close" data-modal-close aria-label="' . esc_attr__('关闭', 'xi') . '">' . xi_icon('close') . '</button>';
        echo '<h3 class="xi-modal-title">' . esc_html(_xz('reward_title', '请作者喝杯咖啡')) . '</h3><div class="xi-reward-codes">';
        foreach (array('reward_wechat' => __('微信', 'xi'), 'reward_alipay' => __('支付宝', 'xi')) as $key => $label) {
            if ($img = _xz($key)) {
                echo '<figure><img src="' . esc_url($img) . '" alt="' . esc_attr($label) . '" loading="lazy"><figcaption>' . esc_html($label) . '</figcaption></figure>';
            }
        }
        echo '</div></div></div>';
    }
}

/* ---------- 面包屑 ---------- */

function xi_breadcrumb()
{
    if (!_xz('breadcrumb_s', true) || is_front_page() || is_home()) {
        return;
    }
    $items = array(array(__('首页', 'xi'), home_url('/')));
    $bbs   = function_exists('xi_bbs_breadcrumb_items') ? xi_bbs_breadcrumb_items() : array();
    $shop  = function_exists('xi_shop_breadcrumb_items') ? xi_shop_breadcrumb_items() : array();
    if ($shop) {
        $items = array_merge($items, $shop);
    } elseif ($bbs) {
        $items = array_merge($items, $bbs);
    } elseif (is_single()) {
        $cats = get_the_category(get_queried_object_id());
        if ($cats) {
            foreach (array_reverse(get_ancestors($cats[0]->term_id, 'category')) as $parent) {
                $items[] = array(get_cat_name($parent), get_category_link($parent));
            }
            $items[] = array($cats[0]->name, get_category_link($cats[0]));
        }
        $items[] = array(__('正文', 'xi'), '');
    } elseif (is_page()) {
        foreach (array_reverse(get_post_ancestors(get_queried_object_id())) as $parent) {
            $items[] = array(get_the_title($parent), get_permalink($parent));
        }
        $items[] = array(get_the_title(get_queried_object_id()), '');
    } elseif (is_category() || is_tag()) {
        $items[] = array(single_term_title('', false), '');
    } elseif (is_search()) {
        $items[] = array(__('搜索', 'xi'), '');
    } elseif (is_author()) {
        $items[] = array(get_queried_object()->display_name, '');
    } else {
        return;
    }

    echo '<nav class="xi-breadcrumb" aria-label="' . esc_attr__('面包屑导航', 'xi') . '"><ol>';
    $schema = array();
    foreach ($items as $i => $item) {
        echo '<li>' . ($item[1] ? '<a href="' . esc_url($item[1]) . '">' . esc_html($item[0]) . '</a>' : '<span aria-current="page">' . esc_html($item[0]) . '</span>') . '</li>';
        $schema[] = array('@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0]) + ($item[1] ? array('item' => $item[1]) : array());
    }
    echo '</ol></nav>';
    if (_xz('seo_s', true)) {
        echo '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
    }
}

/* ---------- 结构化数据 ---------- */

function xi_schema_head()
{
    if (!_xz('seo_s', true)) {
        return;
    }
    if (is_front_page() || is_home()) {
        $data = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => get_bloginfo('name'),
            'url'             => home_url('/'),
            'potentialAction' => array(
                '@type'       => 'SearchAction',
                'target'      => home_url('/?s={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ),
        );
    } elseif (is_singular('post')) {
        $post = get_queried_object();
        $data = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'BlogPosting',
            'headline'      => get_the_title($post),
            'url'           => get_permalink($post),
            'datePublished' => get_the_date('c', $post),
            'dateModified'  => get_the_modified_date('c', $post),
            'author'        => array('@type' => 'Person', 'name' => get_the_author_meta('display_name', $post->post_author), 'url' => get_author_posts_url($post->post_author)),
            'publisher'     => array('@type' => 'Organization', 'name' => get_bloginfo('name')),
            'description'   => xi_seo_description(),
        );
        if (has_post_thumbnail($post)) {
            $data['image'] = get_the_post_thumbnail_url($post, 'large');
        }
    } else {
        return;
    }
    echo '<script type="application/ld+json">' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'xi_schema_head', 5);

/* ---------- 全站公告 ---------- */

function xi_site_notice()
{
    if (!_xz('notice_s', false) || !($content = _xz('notice_content'))) {
        return;
    }
    if (_xz('notice_scope', 'all') === 'home' && !is_front_page() && !is_home()) {
        return;
    }
    $units    = array('minute' => MINUTE_IN_SECONDS, 'hour' => HOUR_IN_SECONDS, 'day' => DAY_IN_SECONDS);
    $interval = (array) _xz('notice_interval', array('num' => 6, 'unit' => 'hour'));
    $unit     = isset($interval['unit'], $units[$interval['unit']]) ? $units[$interval['unit']] : HOUR_IN_SECONDS;
    $seconds  = max(1, (int) (isset($interval['num']) ? $interval['num'] : 6)) * $unit;
    // 内容变化后重新弹出
    $hash = substr(md5($content . _xz('notice_title')), 0, 10);
    $btn  = _xz('notice_btn_text');
    echo '<div class="xi-modal" id="xi-notice" aria-hidden="true" data-notice="' . esc_attr($hash) . '" data-repeat="' . esc_attr(_xz('notice_repeat', 'once')) . '" data-interval="' . (int) $seconds . '" data-delay="' . (int) _xz('notice_delay', 1) . '">';
    $title = _xz('notice_title', '站点公告');
    echo '<div class="xi-modal-mask" data-modal-close></div><div class="xi-modal-box xi-glass xi-notice-box" role="dialog" aria-modal="true"' . ($title ? ' aria-labelledby="xi-notice-title"' : ' aria-label="' . esc_attr__('站点公告', 'xi') . '"') . '>';
    echo '<button type="button" class="xi-btn-icon xi-modal-close" data-modal-close aria-label="' . esc_attr__('关闭', 'xi') . '">' . xi_icon('close') . '</button>';
    echo '<span class="xi-notice-icon">' . xi_icon('info') . '</span>';
    if ($title) {
        echo '<h3 class="xi-modal-title" id="xi-notice-title">' . esc_html($title) . '</h3>';
    }
    echo '<div class="xi-notice-content">' . wp_kses_post(wpautop($content)) . '</div>';
    echo '<div class="xi-notice-actions">';
    if ($btn && ($link = _xz('notice_btn_link'))) {
        echo '<a class="xi-btn xi-btn-accent" href="' . esc_url($link) . '">' . esc_html($btn) . '</a>';
    }
    echo '<button type="button" class="xi-btn" data-modal-close>' . esc_html__('我知道了', 'xi') . '</button></div>';
    echo '</div></div>';
}
add_action('wp_footer', 'xi_site_notice', 5);

/* ---------- 前端参数 ---------- */

function xi_interact_script_data()
{
    wp_localize_script('xi-interact', 'xiPost', array(
        'login'    => wp_login_url(is_singular() ? get_permalink() : home_url('/')),
        'ajax'     => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('xi_post'),
        'post'     => is_singular(array('post', 'forum_post', 'xi_product')) ? get_queried_object_id() : 0,
        'views'    => (bool) _xz('views_s', true),
        'lightbox' => (bool) _xz('lightbox_s', true),
        'copyCode' => (bool) _xz('code_copy_s', true),
    ));
}
add_action('wp_enqueue_scripts', 'xi_interact_script_data', 20);

/* ---------- 用户中心：我的收藏 ---------- */

function xi_favorites_query($user_id, $page = 1, $per = 10)
{
    $ids = xi_get_favorites($user_id);
    if (!$ids) {
        return null;
    }
    return new WP_Query(array(
        'post__in'            => $ids,
        'post_type'           => 'any', // 不指定时只查普通文章，收藏的帖子、商品不会显示
        'orderby'             => 'post__in',
        'posts_per_page'      => $per,
        'paged'               => $page,
        'ignore_sticky_posts' => true,
    ));
}
