<?php
/**
 * 权限管理：按用户组控制投稿、评论、签到、会员、后台访问等功能
 * 设置项 auth_{动作}，值为允许的用户组（WordPress 角色、guest、xi_vip）
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 用户是否拥有某项权限
 *
 * @param string $action  write|publish|edit_publish|edit_noreview|upload|nolimit|comment|checkin|unlock|vip_buy|redeem|admin
 * @param int    $user_id 默认当前用户，0 且未登录时按游客判断
 */
function xi_user_can($action, $user_id = null)
{
    $user_id = $user_id === null ? get_current_user_id() : (int) $user_id;
    $allowed = _xz('auth_' . $action, xi_auth_defaults($action));
    $allowed = array_filter((array) $allowed);

    if (!$user_id) {
        $can = in_array('guest', $allowed, true);
    } elseif (user_can($user_id, 'manage_options')) {
        $can = true;
    } else {
        $user = get_userdata($user_id);
        $can  = $user && array_intersect((array) $user->roles, $allowed);
        if (!$can && in_array('xi_vip', $allowed, true) && xi_vip_enabled()) {
            $can = xi_get_vip_level($user_id) > 0;
        }
    }
    return (bool) apply_filters('xi_user_can', $can, $action, $user_id);
}

/**
 * 无权限时的统一提示
 */
function xi_auth_error($action = '')
{
    return new WP_Error('auth', __('你所在的用户组暂无此权限', 'xi'), $action);
}

/* ---------- 评论 ---------- */

function xi_auth_comments_open($open)
{
    return $open && (is_admin() || xi_user_can('comment'));
}
add_filter('comments_open', 'xi_auth_comments_open', 99);

/**
 * 文章本身开放评论、但当前用户无评论权限（评论区据此显示提示而非“评论已关闭”）
 */
function xi_comment_denied($post_id = 0)
{
    remove_filter('comments_open', 'xi_auth_comments_open', 99);
    $open = comments_open($post_id ? $post_id : null);
    add_filter('comments_open', 'xi_auth_comments_open', 99);
    return $open && !xi_user_can('comment');
}

/* ---------- 后台访问 ---------- */

function xi_auth_admin_redirect()
{
    if (!is_user_logged_in() || wp_doing_ajax() || xi_user_can('admin')) {
        return;
    }
    global $pagenow;
    if ($pagenow === 'admin-post.php') {
        return;
    }
    wp_safe_redirect(xi_uc_url());
    exit;
}
add_action('admin_init', 'xi_auth_admin_redirect');

function xi_auth_admin_bar($show)
{
    return $show && (is_admin() || xi_user_can('admin'));
}
add_filter('show_admin_bar', 'xi_auth_admin_bar', 20);
