<?php
/**
 * 后台设置加载
 */

if (!defined('ABSPATH')) {
    exit;
}

if (is_admin()) {
    xi_require(array(
        'admin-options',
        'metabox-options',
        'profile-options',
        'action',
    ), 'inc/options/');
}

add_filter('csf_fa4', '__return_true');

// CSF 每次请求都会读取不存在的 csf_demo_mode 选项（多一次数据库查询），主题不用示例模式，直接返回 0
add_filter('pre_option_csf_demo_mode', '__return_zero');

/**
 * CSF 默认从 jsdelivr 加载 Font Awesome，国内访问不稳定，改用主题内置的本地文件
 */
function xi_csf_local_fa()
{
    if (wp_style_is('csf-fa', 'registered')) {
        wp_deregister_style('csf-fa');
        wp_enqueue_style('csf-fa', get_template_directory_uri() . '/assets/vendor/font-awesome/css/font-awesome.min.css', array(), '4.7.0');
    }
}
add_action('admin_enqueue_scripts', 'xi_csf_local_fa', 99);

/**
 * 缺少 CSF 框架时在后台提示
 */
function xi_csf_missing_notice()
{
    if (class_exists('CSF') || !current_user_can('switch_themes')) {
        return;
    }
    echo '<div class="notice notice-warning"><p>';
    echo esc_html__('曦主题：未检测到 Codestar Framework，请将框架文件放入主题 inc/codestar-framework/ 目录，否则无法使用主题设置。', 'xi');
    echo '</p></div>';
}
add_action('admin_notices', 'xi_csf_missing_notice');
