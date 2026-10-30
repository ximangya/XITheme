<?php
/**
 * 站长身份组与管理员资料保护
 *
 * - 「站长」用户组（xi_webmaster）：拥有与管理员等价的权限，可在
 *   「主题设置 → 用户&互动 → 站长身份」中开关，默认关闭。
 * - 兼容性：角色能力从 administrator 实时复制，插件给管理员新增的能力会自动同步；
 *   站长只是角色，不改任何用户数据，关闭开关或切换主题时自动恢复为管理员。
 * - 「禁止管理员互相修改资料」：可选开关，开启后管理员不能再编辑其他管理员/站长的资料，
 *   自己的资料、普通用户、超级管理员不受影响。
 */

if (!defined('ABSPATH')) {
    exit;
}

define('XI_ROLE_WEBMASTER', 'xi_webmaster');

/**
 * 站长用户组开关
 */
function xi_webmaster_enabled()
{
    return (bool) _xz('webmaster_role_s', false);
}

/**
 * 是否禁止管理员互相修改资料
 */
function xi_admin_profile_lock_enabled()
{
    return (bool) _xz('admin_profile_lock_s', false);
}

/**
 * 被视为「管理员级」的角色：管理员与站长
 */
function xi_admin_roles()
{
    return array('administrator', XI_ROLE_WEBMASTER);
}

/**
 * 用户是否属于管理员级角色
 */
function xi_is_admin_account($user_id)
{
    $user = get_userdata((int) $user_id);
    if (!$user || empty($user->roles)) {
        return false;
    }
    return (bool) array_intersect((array) $user->roles, xi_admin_roles());
}

/**
 * 站长组的能力：完整复制管理员，插件新增的能力随后自动补上
 * 管理员角色被改名或删除时给一组最小后台权限兜底
 */
function xi_webmaster_caps()
{
    $caps = array();
    $role = get_role('administrator');
    if ($role && !empty($role->capabilities)) {
        $caps = (array) $role->capabilities;
    }
    if (!$caps) {
        $caps = array(
            'read'           => true,
            'manage_options' => true,
            'edit_users'     => true,
            'list_users'     => true,
            'promote_users'  => true,
            'create_users'   => true,
            'delete_users'   => true,
            'edit_posts'     => true,
            'publish_posts'  => true,
            'upload_files'   => true,
        );
    }
    return (array) apply_filters('xi_webmaster_capabilities', $caps);
}

/**
 * 创建或同步站长角色
 *
 * @param bool|null $enabled 传 false 表示强制不创建（设置保存时用）
 * @return bool 当前是否存在可用角色
 */
function xi_webmaster_sync_role($enabled = null)
{
    if ($enabled === null) {
        $enabled = xi_webmaster_enabled();
    }
    if (!$enabled) {
        return false;
    }

    $caps = xi_webmaster_caps();
    $role = get_role(XI_ROLE_WEBMASTER);

    if (!$role) {
        add_role(XI_ROLE_WEBMASTER, __('站长', 'xi'), $caps);
        return true;
    }

    // 只在能力有差异时写库，避免每次请求都 update_option
    $current = (array) $role->capabilities;
    $changed = false;
    foreach ($caps as $cap => $grant) {
        if (!array_key_exists($cap, $current) || (bool) $current[$cap] !== (bool) $grant) {
            $role->add_cap($cap, (bool) $grant);
            $changed = true;
        }
    }
    foreach ($current as $cap => $grant) {
        if (!array_key_exists($cap, $caps)) {
            $role->remove_cap($cap);
            $changed = true;
        }
    }
    if ($changed) {
        do_action('xi_webmaster_role_synced', $role);
    }
    return true;
}

/**
 * 站长全部恢复为管理员并删除角色
 *
 * WordPress 不允许删除启用中的主题，所以「卸载」在实践上等于切换主题；
 * switch_theme 钩子由当前（正在被停用的）主题注册，可以在离开前完成恢复。
 *
 * @return int 恢复的用户数
 */
function xi_webmaster_revert()
{
    if (!get_role(XI_ROLE_WEBMASTER)) {
        return 0;
    }

    $count = 0;
    $users = get_users(array(
        'role'   => XI_ROLE_WEBMASTER,
        'fields' => 'ID',
        'number' => -1,
    ));
    foreach ($users as $user_id) {
        $user = new WP_User((int) $user_id);
        if (!$user->exists()) {
            continue;
        }
        // 先加管理员再移除站长：即使站长账号同时还有别的角色也不会变成无角色用户
        $user->add_role('administrator');
        $user->remove_role(XI_ROLE_WEBMASTER);
        $count++;
    }

    remove_role(XI_ROLE_WEBMASTER);
    do_action('xi_webmaster_reverted', $count);
    return $count;
}

/**
 * 每个请求校正一次角色状态：
 * 开关打开 → 角色存在且能力最新；开关关闭 → 角色与成员全部还原
 */
function xi_webmaster_bootstrap()
{
    if (xi_webmaster_enabled()) {
        xi_webmaster_sync_role(true);
    } elseif (get_role(XI_ROLE_WEBMASTER)) {
        xi_webmaster_revert();
    }
}
add_action('init', 'xi_webmaster_bootstrap', 1);

/**
 * 设置保存后立即生效，不等到下一次请求
 * 注意：_xz() 在本次请求里有静态缓存，这里必须用保存的 $data 判断
 */
function xi_webmaster_on_options_saved($data)
{
    if (!is_array($data)) {
        return;
    }
    if (!empty($data['webmaster_role_s'])) {
        xi_webmaster_sync_role(true);
    } else {
        xi_webmaster_revert();
    }
}
add_action('csf_xi_options_saved', 'xi_webmaster_on_options_saved');

/**
 * 主题被切换 / 停用前把站长恢复为管理员
 */
function xi_webmaster_on_switch_theme()
{
    xi_webmaster_revert();
}
add_action('switch_theme', 'xi_webmaster_on_switch_theme', 1);

/**
 * 主题重新启用时：如果开关已关闭，清掉可能残留的角色
 */
function xi_webmaster_on_after_switch_theme()
{
    if (!xi_webmaster_enabled() && get_role(XI_ROLE_WEBMASTER)) {
        xi_webmaster_revert();
    }
}
add_action('after_switch_theme', 'xi_webmaster_on_after_switch_theme', 1);

/**
 * 设置页里显示站长成员概览
 */
function xi_webmaster_status_html()
{
    if (!xi_webmaster_enabled()) {
        return '<span class="description">' . esc_html__('开启后会在用户列表中新增「站长」角色，保存设置即生效。', 'xi') . '</span>';
    }

    if (!get_role(XI_ROLE_WEBMASTER)) {
        return '<span class="description">' . esc_html__('角色尚未创建，保存设置或刷新页面后自动创建。', 'xi') . '</span>';
    }

    $users = get_users(array(
        'role'   => XI_ROLE_WEBMASTER,
        'fields' => 'ID',
        'number' => -1,
        'orderby' => 'ID',
    ));

    $html  = '<span class="description">' . sprintf(esc_html__('当前站长 %d 人：', 'xi'), count($users));
    if ($users) {
        $names = array();
        foreach (array_slice($users, 0, 8) as $user_id) {
            $user = get_userdata((int) $user_id);
            if ($user) {
                $names[] = esc_html($user->display_name);
            }
        }
        $html .= implode(esc_html__('、', 'xi'), $names);
        if (count($users) > 8) {
            $html .= esc_html__(' 等', 'xi');
        }
    } else {
        $html .= esc_html__('（暂无，可在「用户」中把角色改为站长）', 'xi');
    }
    $html .= '</span><br><a href="' . esc_url(admin_url('users.php?role=' . XI_ROLE_WEBMASTER)) . '">' . esc_html__('管理站长成员', 'xi') . '</a>';
    return $html;
}

/* ---------- 禁止管理员互相修改资料 ---------- */

/**
 * 拦截 edit_user：管理员不能编辑另一位管理员 / 站长的资料
 * 覆盖后台用户编辑、REST API 与任何使用 current_user_can('edit_user', $id) 的代码
 */
function xi_protect_admin_profiles($caps, $cap, $user_id, $args)
{
    if (!xi_admin_profile_lock_enabled() || 'edit_user' !== $cap || empty($args[0])) {
        return $caps;
    }
    if (is_multisite() && is_super_admin($user_id)) {
        return $caps;
    }

    $target = (int) $args[0];
    if (!$target || $target === (int) $user_id) {
        return $caps;
    }
    if (xi_is_admin_account($user_id) && xi_is_admin_account($target)) {
        $caps[] = 'do_not_allow';
    }
    return $caps;
}
add_filter('map_meta_cap', 'xi_protect_admin_profiles', 10, 4);

/**
 * 直接访问 user-edit.php 时给出中文提示（比核心的英文 wp_die 更清楚）
 */
function xi_admin_profile_lock_guard()
{
    global $pagenow;

    if (!xi_admin_profile_lock_enabled() || 'user-edit.php' !== $pagenow) {
        return;
    }
    if (is_multisite() && is_super_admin()) {
        return;
    }

    $target = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
    if (!$target || $target === get_current_user_id()) {
        return;
    }
    if (!xi_is_admin_account(get_current_user_id()) || !xi_is_admin_account($target)) {
        return;
    }

    wp_die(
        '<p>' . esc_html__('主题设置里开启了「禁止管理员互相修改资料」，不能编辑其他管理员或站长的资料。', 'xi') . '</p>'
        . '<p><a href="' . esc_url(admin_url('users.php')) . '">' . esc_html__('返回用户列表', 'xi') . '</a></p>',
        esc_html__('操作被拒绝', 'xi'),
        array('response' => 403, 'back_link' => true)
    );
}
add_action('admin_init', 'xi_admin_profile_lock_guard', 1);
