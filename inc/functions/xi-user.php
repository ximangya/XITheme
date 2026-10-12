<?php
/**
 * 用户相关：角色、统计、菜单链接、右上角头像菜单
 * 用户信息小工具与头像菜单共用这里的函数
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_user_role_name($user)
{
    $names = array(
        'administrator' => __('管理员', 'xi'),
        'editor'        => __('编辑', 'xi'),
        'author'        => __('作者', 'xi'),
        'contributor'   => __('投稿者', 'xi'),
        'subscriber'    => __('读者', 'xi'),
    );
    $role = $user->roles ? reset($user->roles) : '';
    return isset($names[$role]) ? $names[$role] : __('用户', 'xi');
}

function xi_user_stats($user_id)
{
    static $memo = array();
    $user_id = (int) $user_id;
    if (!isset($memo[$user_id])) {
        $counts = xi_cache_remember('ustats|' . $user_id, HOUR_IN_SECONDS, function () use ($user_id) {
            return array(
                'posts'    => (int) count_user_posts($user_id, 'post', true),
                'comments' => (int) get_comments(array('user_id' => $user_id, 'status' => 'approve', 'count' => true)),
            );
        });
        $user = get_userdata($user_id);
        $memo[$user_id] = array(
            'posts'    => (int) $counts['posts'],
            'comments' => (int) $counts['comments'],
            'days'     => $user ? max(1, (int) floor((time() - strtotime($user->user_registered)) / DAY_IN_SECONDS)) : 0,
        );
    }
    return $memo[$user_id];
}

/**
 * 普通用户快捷入口
 */
function xi_user_menu_links($user)
{
    $links = array(
        array('title' => __('我的主页', 'xi'), 'url' => get_author_posts_url($user->ID), 'icon' => 'user'),
        array('title' => __('用户中心', 'xi'), 'url' => xi_uc_url(), 'icon' => 'grid'),
    );
    if (xi_write_enabled()) {
        if (xi_user_can('write', $user->ID)) {
            $links[] = array('title' => __('写文章', 'xi'), 'url' => xi_write_url(), 'icon' => 'edit');
        }
        $links[] = array('title' => __('我的文章', 'xi'), 'url' => xi_uc_url('posts'), 'icon' => 'book');
    } elseif (user_can($user, 'edit_posts') && xi_user_can('admin', $user->ID)) {
        $links[] = array('title' => __('写文章', 'xi'), 'url' => admin_url('post-new.php'), 'icon' => 'edit');
    }
    if (function_exists('xi_bbs_can_post_in') && xi_bbs_can_post_in(0, $user->ID)) {
        $links[] = array('title' => __('发布帖子', 'xi'), 'url' => xi_bbs_new_url(), 'icon' => 'chat');
    }
    $links[] = array('title' => __('账号设置', 'xi'), 'url' => xi_uc_url('settings'), 'icon' => 'gear');
    return apply_filters('xi_user_menu_links', $links, $user);
}

/**
 * 管理员快捷入口
 */
function xi_user_admin_links()
{
    if (!current_user_can('manage_options')) {
        return array();
    }
    $pending = (int) wp_count_comments()->moderated;
    $links   = array(
        array('title' => __('后台首页', 'xi'), 'url' => admin_url(), 'icon' => 'grid'),
        array('title' => __('主题设置', 'xi'), 'url' => admin_url('admin.php?page=' . XI_OPTIONS), 'icon' => 'gear'),
        array('title' => __('小工具', 'xi'), 'url' => admin_url('widgets.php'), 'icon' => 'layout'),
        array('title' => __('菜单', 'xi'), 'url' => admin_url('nav-menus.php'), 'icon' => 'menu'),
        array('title' => __('评论管理', 'xi'), 'url' => admin_url('edit-comments.php'), 'icon' => 'comment', 'badge' => $pending),
        array('title' => __('所有文章', 'xi'), 'url' => admin_url('edit.php'), 'icon' => 'list'),
    );
    if (!class_exists('CSF')) {
        unset($links[1]);
    }
    return apply_filters('xi_user_admin_links', array_values($links));
}

function xi_login_buttons()
{
    $current = (is_ssl() ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
    $current = remove_query_arg(array('xi_sign', 'redirect_to'), $current);
    $modal   = xi_sign_enabled();
    $can_reg = $modal ? xi_reg_enabled() : get_option('users_can_register');

    $html = '<a class="xi-btn xi-btn-accent xi-btn-sm" href="' . esc_url(wp_login_url($current)) . '"' . ($modal ? ' data-xi-sign="login"' : '') . '>' . xi_icon('login') . esc_html__('登录', 'xi') . '</a>';
    if ($can_reg) {
        $html .= '<a class="xi-btn xi-btn-sm" href="' . esc_url(wp_registration_url()) . '"' . ($modal ? ' data-xi-sign="register"' : '') . '>' . esc_html__('注册', 'xi') . '</a>';
    }
    return $html;
}

/**
 * 右上角头像与下拉菜单
 */
function xi_header_user()
{
    if (!_xz('header_user_s', true)) {
        return;
    }
    $user = wp_get_current_user();

    echo '<div class="xi-user">';
    echo '<button class="xi-user-btn" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr__('用户菜单', 'xi') . '">';
    echo $user->exists() ? get_avatar($user->ID, 32) : '<span class="xi-user-guest">' . xi_icon('user') . '</span>';
    echo '</button>';

    echo '<div class="xi-user-menu xi-glass" role="menu">';
    if ($user->exists()) {
        $stats = xi_user_stats($user->ID);

        echo '<div class="xi-um-head">';
        echo '<a href="' . esc_url(get_author_posts_url($user->ID)) . '">' . get_avatar($user->ID, 48) . '</a>';
        $badge = xi_vip_badge($user->ID);
        echo '<div class="xi-um-info"><b>' . esc_html($user->display_name) . ($badge ? $badge : '<span class="xi-role">' . esc_html(xi_user_role_name($user)) . '</span>') . '</b>';
        echo '<small>' . esc_html($user->user_email) . '</small></div>';
        echo '</div>';

        echo '<div class="xi-um-stats">';
        if (xi_points_enabled() || xi_balance_enabled()) {
            if (xi_points_enabled()) {
                echo '<a href="' . esc_url(xi_uc_url('points')) . '"><b>' . (int) xi_get_points($user->ID) . '</b><span>' . esc_html(xi_points_name()) . '</span></a>';
            }
            if (xi_balance_enabled()) {
                echo '<a href="' . esc_url(xi_uc_url('balance')) . '"><b>' . esc_html(xi_format_balance(xi_get_balance($user->ID))) . '</b><span>' . esc_html__('余额', 'xi') . '</span></a>';
            }
            echo '<div><b>' . $stats['posts'] . '</b><span>' . esc_html__('文章', 'xi') . '</span></div>';
        } else {
            echo '<div><b>' . $stats['posts'] . '</b><span>' . esc_html__('文章', 'xi') . '</span></div>';
            echo '<div><b>' . $stats['comments'] . '</b><span>' . esc_html__('评论', 'xi') . '</span></div>';
            echo '<div><b>' . $stats['days'] . '</b><span>' . esc_html__('天', 'xi') . '</span></div>';
        }
        echo '</div>';

        if (xi_points_enabled() && xi_user_can('checkin', $user->ID)) {
            $checkin = xi_checkin_status($user->ID);
            echo '<button type="button" class="xi-um-checkin" data-xi-checkin' . ($checkin['done'] ? ' disabled' : '') . '>' . xi_icon('calendar');
            echo $checkin['done'] ? sprintf(esc_html__('已连续签到 %d 天', 'xi'), $checkin['streak']) : esc_html__('今日签到', 'xi');
            echo '</button>';
        }
        if (xi_vip_enabled() && xi_vip_levels() && !xi_get_vip_level($user->ID) && xi_user_can('vip_buy', $user->ID)) {
            echo '<a class="xi-um-vip" href="' . esc_url(xi_uc_url('vip')) . '">' . xi_icon('crown') . '<span>' . esc_html__('开通会员，解锁专属权益', 'xi') . '</span>' . xi_icon('chevron') . '</a>';
        }

        echo '<div class="xi-um-grid">';
        foreach (xi_user_menu_links($user) as $link) {
            echo '<a href="' . esc_url($link['url']) . '" role="menuitem">' . xi_icon($link['icon']) . '<span>' . esc_html($link['title']) . '</span></a>';
        }
        echo '</div>';

        $admin_links = xi_user_admin_links();
        if ($admin_links) {
            echo '<div class="xi-um-section"><div class="xi-um-title">' . esc_html__('站点管理', 'xi') . '</div>';
            foreach ($admin_links as $link) {
                echo '<a class="xi-um-item" href="' . esc_url($link['url']) . '" role="menuitem">' . xi_icon($link['icon']) . '<span>' . esc_html($link['title']) . '</span>';
                if (!empty($link['badge'])) {
                    echo '<em>' . (int) $link['badge'] . '</em>';
                }
                echo '</a>';
            }
            echo '</div>';
        }

        echo '<a class="xi-um-item xi-um-logout" href="' . esc_url(wp_logout_url(home_url('/'))) . '" role="menuitem">' . xi_icon('logout') . '<span>' . esc_html__('退出登录', 'xi') . '</span></a>';
    } else {
        echo '<div class="xi-um-guest">';
        echo '<span class="xi-user-guest is-lg">' . xi_icon('user') . '</span>';
        echo '<b>' . esc_html__('欢迎来到', 'xi') . ' ' . esc_html(get_bloginfo('name')) . '</b>';
        echo '<small>' . esc_html__('登录后可发表评论、管理文章', 'xi') . '</small>';
        echo '<div class="xi-um-actions">' . xi_login_buttons() . '</div>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';
}
