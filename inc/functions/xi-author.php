<?php
/**
 * 个人主页（作者页 /author/xxx/）与用户搜索
 * 自己和其他用户都可以查看：资料、会员状态、站点身份、认证、发布的文章和评论
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 个人主页封面图，未上传返回空字符串
 */
function xi_user_cover_url($user_id)
{
    $att_id = (int) get_user_meta($user_id, 'xi_cover', true);
    $url    = $att_id ? wp_get_attachment_image_url($att_id, 'large') : '';
    return $url ? $url : '';
}

/**
 * 身份标识：站点身份（角色）+ 认证身份 + 会员
 *
 * @param WP_User $user
 * @param bool    $vip 是否包含会员标识
 */
function xi_user_badges($user, $vip = true)
{
    $role = $user->roles ? reset($user->roles) : '';
    $html = '<span class="xi-role is-' . esc_attr($role ? $role : 'user') . '">' . esc_html(xi_user_role_name($user)) . '</span>';
    if ($identity = get_user_meta($user->ID, 'xi_identity', true)) {
        $html .= '<span class="xi-identity" title="' . esc_attr__('认证身份', 'xi') . '">' . xi_icon('check') . esc_html($identity) . '</span>';
    }
    if ($vip && xi_vip_enabled()) {
        $html .= xi_vip_badge($user->ID);
    }
    return $html;
}

/**
 * 评论者为注册用户时，名称链接到其个人主页
 */
function xi_comment_author_profile_url($url, $comment_id, $comment)
{
    return ($comment && (int) $comment->user_id) ? get_author_posts_url((int) $comment->user_id) : $url;
}
add_filter('get_comment_author_url', 'xi_comment_author_profile_url', 10, 3);

function xi_author_body_class($classes)
{
    if (is_author()) {
        $classes[] = 'xi-author-page';
    }
    return $classes;
}
add_filter('body_class', 'xi_author_body_class', 20);

/**
 * 个人主页的评论标签：该用户已通过审核的评论
 */
function xi_author_comments_query($user_id, $page, $per = 15)
{
    $args = array(
        'user_id'     => $user_id,
        'status'      => 'approve',
        'post_status' => 'publish',
        'type'        => 'comment',
    );
    $total = (int) get_comments(array_merge($args, array('count' => true)));
    $list  = get_comments(array_merge($args, array('number' => $per, 'offset' => ($page - 1) * $per)));
    return array('list' => $list, 'pages' => (int) ceil($total / $per), 'total' => $total);
}

/* ---------- 用户搜索 ---------- */

function xi_search_user_enabled()
{
    return (bool) _xz('search_user_s', true);
}

/**
 * 当前搜索类型：post | user
 */
function xi_search_type()
{
    return xi_search_user_enabled() && isset($_GET['stype']) && $_GET['stype'] === 'user' ? 'user' : 'post';
}

/**
 * 按昵称、个性域名搜索用户（不匹配登录名和邮箱，避免泄露账号）
 */
function xi_search_users($keyword, $page = 1, $per = 12)
{
    static $cache = array();
    $key = $keyword . '|' . $page . '|' . $per;
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    $query = new WP_User_Query(array(
        'search'         => '*' . $keyword . '*',
        'search_columns' => array('display_name', 'user_nicename'),
        'number'         => $per,
        'paged'          => $page,
        'orderby'        => 'post_count',
        'order'          => 'DESC',
        'count_total'    => true,
    ));
    $cache[$key] = array(
        'users' => $query->get_results(),
        'total' => (int) $query->get_total(),
        'pages' => (int) ceil($query->get_total() / $per),
    );
    return $cache[$key];
}

/**
 * 用户卡片：搜索结果中使用
 */
function xi_user_card($user)
{
    $stats = xi_user_stats($user->ID);
    $bio   = get_the_author_meta('description', $user->ID);
    $cover = xi_user_cover_url($user->ID);
    echo '<a class="xi-card xi-user-card" href="' . esc_url(get_author_posts_url($user->ID)) . '">';
    echo '<span class="xi-user-card-cover"' . ($cover ? ' style="background-image:url(' . esc_url($cover) . ')"' : '') . '></span>';
    echo get_avatar($user->ID, 64);
    echo '<b>' . esc_html($user->display_name) . '</b>';
    echo '<span class="xi-badges">' . xi_user_badges($user) . '</span>';
    echo '<p>' . esc_html($bio ? wp_trim_words($bio, 30, '…') : __('这个人很低调，还没有写简介', 'xi')) . '</p>';
    echo '<span class="xi-user-card-stats"><span><b>' . $stats['posts'] . '</b>' . esc_html__('文章', 'xi') . '</span><span><b>' . $stats['comments'] . '</b>' . esc_html__('评论', 'xi') . '</span></span>';
    echo '</a>';
}

/**
 * 搜索框提示文字
 */
function xi_search_placeholder()
{
    return xi_search_user_enabled() ? __('搜索文章或用户…', 'xi') : __('搜索文章…', 'xi');
}
