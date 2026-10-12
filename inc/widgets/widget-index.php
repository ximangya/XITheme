<?php
/**
 * 小工具模块入口：侧栏位置、模板输出、后台资源
 */

if (!defined('ABSPATH')) {
    exit;
}

xi_require(array(
    'more',
    'posts',
), 'inc/widgets/widget-');

/**
 * 页面类型 => 名称
 */
function xi_widget_page_types()
{
    $types = array(
        'home'   => __('首页', 'xi'),
        'single' => __('文章页', 'xi'),
        'page'   => __('页面', 'xi'),
        'cat'    => __('分类页', 'xi'),
        'tag'    => __('标签页', 'xi'),
        'search' => __('搜索页', 'xi'),
    );
    // 论坛关闭时不注册论坛小工具区域
    if (function_exists('xi_bbs_enabled') && xi_bbs_enabled()) {
        $types['bbs'] = __('论坛', 'xi');
    }
    return $types;
}

function xi_register_sidebar($sidebars)
{
    foreach ($sidebars as $value) {
        register_sidebar(array(
            'name'          => $value['name'],
            'id'            => $value['id'],
            'description'   => $value['description'],
            'before_widget' => '<div id="%1$s" class="xi-widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<div class="xi-widget-head"><h3 class="xi-widget-title">',
            'after_title'   => '</h3></div>',
        ));
    }
}

function xi_main_register_sidebar()
{
    $sidebars = array(
        array('id' => 'all_top_fluid', 'name' => __('全站-顶部全宽度', 'xi'), 'description' => __('显示在所有页面导航栏下方，适合放图文封面等宽模块', 'xi')),
        array('id' => 'all_bottom_fluid', 'name' => __('全站-底部全宽度', 'xi'), 'description' => __('显示在所有页面页脚上方', 'xi')),
        array('id' => 'all_sidebar_top', 'name' => __('全站-侧边栏顶部', 'xi'), 'description' => __('显示在所有侧边栏的最上方', 'xi')),
        array('id' => 'all_sidebar_bottom', 'name' => __('全站-侧边栏底部', 'xi'), 'description' => __('显示在所有侧边栏的最下方', 'xi')),
        array('id' => 'all_footer', 'name' => __('全站-页脚内部', 'xi'), 'description' => __('显示在页脚版权信息上方', 'xi')),
    );

    $positions = array(
        'top_fluid'      => __('顶部全宽度', 'xi'),
        'top_content'    => __('主内容上面', 'xi'),
        'bottom_content' => __('主内容下面', 'xi'),
        'bottom_fluid'   => __('底部全宽度', 'xi'),
        'sidebar'        => __('侧边栏', 'xi'),
    );

    foreach (xi_widget_page_types() as $type => $type_name) {
        foreach ($positions as $pos => $pos_name) {
            $sidebars[] = array(
                'id'          => $type . '_' . $pos,
                'name'        => $type_name . '-' . $pos_name,
                'description' => sprintf(__('显示在%1$s的%2$s位置', 'xi'), $type_name, $pos_name),
            );
        }
    }

    xi_register_sidebar($sidebars);
}
add_action('widgets_init', 'xi_main_register_sidebar');

/**
 * 移除 WordPress 自带小工具，只保留自定义 HTML
 */
function xi_unregister_core_widgets()
{
    if (!_xz('modern_front_s', true)) {
        return;
    }
    $core = array(
        'WP_Widget_Pages', 'WP_Widget_Calendar', 'WP_Widget_Archives', 'WP_Widget_Links', 'WP_Widget_Media_Audio',
        'WP_Widget_Media_Image', 'WP_Widget_Media_Gallery', 'WP_Widget_Media_Video', 'WP_Widget_Meta', 'WP_Widget_Search',
        'WP_Widget_Text', 'WP_Widget_Categories', 'WP_Widget_Recent_Posts', 'WP_Widget_Recent_Comments', 'WP_Widget_RSS',
        'WP_Widget_Tag_Cloud', 'WP_Nav_Menu_Widget', 'WP_Widget_Block',
    );
    foreach ($core as $class) {
        unregister_widget($class);
    }
}
add_action('widgets_init', 'xi_unregister_core_widgets', 30);

/**
 * 使用经典小工具界面，CSF 字段只能在经典界面中初始化
 */
function xi_widgets_classic_editor()
{
    remove_theme_support('widgets-block-editor');
}
add_action('after_setup_theme', 'xi_widgets_classic_editor', 20);

/**
 * 小工具页、自定义器加载 CSF 资源
 */
function xi_widgets_enqueue_csf_assets($enqueue)
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && in_array($screen->id, array('widgets', 'customize'), true)) {
        return true;
    }
    return $enqueue;
}
add_filter('csf_enqueue_assets', 'xi_widgets_enqueue_csf_assets');

/* ---------- 模板输出 ---------- */

function xi_page_type()
{
    if ((function_exists('xi_is_user_center') && xi_is_user_center()) || (function_exists('xi_is_write_page') && xi_is_write_page())) {
        return '';
    }
    if (function_exists('xi_is_bbs_new') && xi_is_bbs_new()) {
        return '';
    }
    if (function_exists('xi_is_bbs') && xi_is_bbs()) {
        return 'bbs';
    }
    if (is_front_page() || is_home()) {
        return 'home';
    }
    if (is_single()) {
        return 'single';
    }
    if (is_page()) {
        return 'page';
    }
    if (is_category()) {
        return 'cat';
    }
    if (is_tag()) {
        return 'tag';
    }
    if (is_search()) {
        return 'search';
    }
    return '';
}

function xi_get_sidebars($ids)
{
    ob_start();
    foreach (array_filter($ids) as $id) {
        if (is_active_sidebar($id)) {
            dynamic_sidebar($id);
        }
    }
    return trim(ob_get_clean());
}

/**
 * 全宽区域：$place = top | bottom
 */
function xi_fluid_widgets($place)
{
    $type = xi_page_type();
    if ($place === 'top') {
        $ids = array('all_top_fluid', $type ? $type . '_top_fluid' : '');
    } else {
        $ids = array($type ? $type . '_bottom_fluid' : '', 'all_bottom_fluid');
    }
    $html = xi_get_sidebars($ids);
    if ($html) {
        echo '<div class="xi-fluid-area xi-fluid-' . esc_attr($place) . '">' . $html . '</div>';
    }
}

/**
 * 主内容上下区域：$place = top | bottom
 */
function xi_content_widgets($place)
{
    $type = xi_page_type();
    if (!$type) {
        return;
    }
    $html = xi_get_sidebars(array($type . '_' . $place . '_content'));
    if ($html) {
        echo '<div class="xi-content-area">' . $html . '</div>';
    }
}

function xi_sidebar_widgets()
{
    $type = xi_page_type();
    $html = xi_get_sidebars(array('all_sidebar_top', $type ? $type . '_sidebar' : '', 'all_sidebar_bottom'));
    if ($html) {
        echo $html;
        return;
    }

    // 未添加任何小工具时的默认侧栏
    if ($type === 'bbs') {
        xi_bbs_sidebar();
        return;
    }
    if (is_singular()) {
        XI_CFSwidget::render('xi_widget_ui_toc', array('title' => __('文章目录', 'xi')));
    }
    XI_CFSwidget::render('xi_widget_ui_user');
    XI_CFSwidget::render('xi_widget_ui_posts', array('title' => __('热门文章', 'xi'), 'orderby' => 'comment_count', 'count' => 5));
    XI_CFSwidget::render('xi_widget_ui_tags', array('title' => __('标签', 'xi')));
}
