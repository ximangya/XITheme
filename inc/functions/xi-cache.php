<?php
/**
 * 查询缓存：把侧栏热门文章、标签云、相关文章、用户统计、版块今日帖数等
 * 每次访问都要重新查询的结果缓存起来（装了 Redis / Memcached 等对象缓存时存在内存里，否则存为 transient）
 *
 * 失效方式：任何文章、评论、分类标签、小工具或主题设置变化时，版本号 xi_cache_ver 自增，旧缓存全部作废，
 * 不需要逐个清理；过期的 transient 由 WordPress 每天自动删除
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_cache_enabled()
{
    return (bool) _xz('cache_s', true) && !is_customize_preview() && !(defined('WP_DEBUG') && WP_DEBUG && isset($_GET['xi_nocache']));
}

function xi_cache_ver()
{
    static $ver = null;
    if ($ver === null) {
        $ver = (string) get_option('xi_cache_ver', '');
        if ($ver === '') {
            $ver = (string) time();
            update_option('xi_cache_ver', $ver, true);
        }
    }
    return $ver;
}

/**
 * 让全部查询缓存失效
 */
function xi_cache_flush()
{
    static $done = false;
    if ($done) {
        return; // 一次请求里改动很多内容时只更新一次
    }
    $done = true;
    update_option('xi_cache_ver', (string) microtime(true), true);
}

function xi_cache_key($key)
{
    return 'xi_c_' . md5($key . '|' . xi_cache_ver());
}

/**
 * 取缓存，没有就调用 $callback 计算并保存
 */
function xi_cache_remember($key, $ttl, $callback)
{
    if (!xi_cache_enabled()) {
        return call_user_func($callback);
    }
    $k     = xi_cache_key($key);
    $value = get_transient($k);
    if ($value !== false) {
        return $value;
    }
    $value = call_user_func($callback);
    set_transient($k, $value, $ttl);
    return $value;
}

/**
 * 缓存一段输出的 HTML（小工具、相关文章等）
 */
function xi_cache_fragment($key, $ttl, $render)
{
    echo xi_cache_remember('frag|' . $key, $ttl, function () use ($render) {
        ob_start();
        call_user_func($render);
        return (string) ob_get_clean();
    });
}

/* ---------- 失效时机 ---------- */

function xi_cache_flush_on_post($post_id)
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || get_post_type($post_id) === 'nav_menu_item') {
        return;
    }
    xi_cache_flush();
}
add_action('save_post', 'xi_cache_flush_on_post');
add_action('deleted_post', 'xi_cache_flush_on_post');
add_action('trashed_post', 'xi_cache_flush_on_post');
add_action('untrashed_post', 'xi_cache_flush_on_post');

add_action('wp_update_comment_count', 'xi_cache_flush');
add_action('transition_comment_status', 'xi_cache_flush');
add_action('created_term', 'xi_cache_flush');
add_action('edited_term', 'xi_cache_flush');
add_action('delete_term', 'xi_cache_flush');
add_action('profile_update', 'xi_cache_flush');
add_action('switch_theme', 'xi_cache_flush');
add_action('wp_update_nav_menu', 'xi_cache_flush');

/**
 * 主题设置、小工具设置保存后
 */
function xi_cache_flush_on_option($option)
{
    if ($option === XI_OPTIONS || $option === 'sidebars_widgets' || strpos($option, 'widget_') === 0 || $option === 'permalink_structure' || $option === 'blogname') {
        xi_cache_flush();
    }
}
add_action('updated_option', 'xi_cache_flush_on_option');

/**
 * 设置页「清除缓存」按钮
 */
function xi_cache_tools_field()
{
    $url = wp_nonce_url(admin_url('admin-post.php?action=xi_cache_clear'), 'xi_cache_clear');
    echo '<a class="button" href="' . esc_url($url) . '">' . esc_html__('立即清除查询缓存', 'xi') . '</a>';
    if (!empty($_GET['xi_cache_cleared'])) {
        echo ' <span style="color:#008a20">' . esc_html__('已清除', 'xi') . '</span>';
    }
    echo '<p style="color:#646970;margin:8px 0 0">' . esc_html__('发布或修改文章、评论、分类标签、小工具和主题设置时会自动清除，一般不需要手动操作', 'xi') . '</p>';
}

function xi_cache_clear_action()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('没有权限', 'xi'), 403);
    }
    check_admin_referer('xi_cache_clear');
    xi_cache_flush();
    wp_safe_redirect(add_query_arg('xi_cache_cleared', 1, wp_get_referer() ? wp_get_referer() : admin_url()));
    exit;
}
add_action('admin_post_xi_cache_clear', 'xi_cache_clear_action');
