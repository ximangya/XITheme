<?php
/**
 * 菜单图标：外观 → 菜单 中给每个菜单项填写图标，可选只显示图标
 *
 * 图标写法（自动识别）：
 * - 主题图标名：home、tag、fire、book、star … 以及品牌图标 qq、wechat、weibo、github、bilibili、email、rss
 * - Font Awesome 4.7 类名：fa fa-home 或 fa-home（前台用到时才加载图标字体）
 * - 图片地址：https://… 或 /wp-content/…
 * - 其他文字按 emoji / 短文字原样显示，例如 🔥
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_menu_brands()
{
    return array('qq', 'wechat', 'weibo', 'github', 'bilibili', 'email', 'rss');
}

/**
 * 判断图标写法：svg（主题图标）/ brand / fa / img / text
 */
function xi_menu_icon_type($icon)
{
    if ($icon === '') {
        return '';
    }
    if (preg_match('#^(https?:)?//|^/[^/]#i', $icon)) {
        return 'img';
    }
    if (preg_match('/^fa[-\s]/', $icon)) {
        return 'fa';
    }
    if (in_array($icon, xi_menu_brands(), true)) {
        return 'brand';
    }
    if (array_key_exists($icon, xi_icon_set())) {
        return 'svg';
    }
    return 'text';
}

function xi_menu_icon_html($icon)
{
    switch (xi_menu_icon_type($icon)) {
        case 'img':
            return '<span class="xi-menu-icon"><img src="' . esc_url($icon) . '" alt="" loading="lazy"></span>';
        case 'fa':
            $class = strpos($icon, 'fa ') === 0 ? $icon : 'fa ' . $icon;
            return '<span class="xi-menu-icon"><i class="' . esc_attr($class) . '" aria-hidden="true"></i></span>';
        case 'brand':
            return '<span class="xi-menu-icon">' . xi_brand_icon($icon) . '</span>';
        case 'svg':
            return '<span class="xi-menu-icon">' . xi_icon($icon) . '</span>';
        case 'text':
            return '<span class="xi-menu-icon is-text" aria-hidden="true">' . esc_html(mb_substr($icon, 0, 4)) . '</span>';
    }
    return '';
}

/* ---------- 前台输出 ---------- */

function xi_menu_item_title($title, $item)
{
    $icon = (string) get_post_meta($item->ID, '_xi_menu_icon', true);
    if ($icon === '') {
        return $title;
    }
    $html = xi_menu_icon_html($icon);
    if ($html && get_post_meta($item->ID, '_xi_menu_icon_only', true)) {
        return $html . '<span class="screen-reader-text">' . $title . '</span>';
    }
    return $html . '<span class="xi-menu-text">' . $title . '</span>';
}
add_filter('nav_menu_item_title', 'xi_menu_item_title', 10, 2);

/**
 * 只显示图标时，鼠标悬停提示菜单名称
 */
function xi_menu_link_attributes($atts, $item)
{
    if (get_post_meta($item->ID, '_xi_menu_icon_only', true) && get_post_meta($item->ID, '_xi_menu_icon', true) !== '' && empty($atts['title'])) {
        $atts['title']      = wp_strip_all_tags($item->title);
        $atts['aria-label'] = wp_strip_all_tags($item->title);
    }
    return $atts;
}
add_filter('nav_menu_link_attributes', 'xi_menu_link_attributes', 10, 2);

/**
 * 有菜单项使用 Font Awesome 时才在前台加载图标字体（保存菜单时更新标记）
 */
/**
 * 顶部组件按钮、版块图标里是否用了 Font Awesome 类名
 */
function xi_fa_used_in_options()
{
    if (_xz('header_slider_btn_s', false)) {
        foreach ((array) _xz('header_slider_btns', array()) as $btn) {
            if (is_array($btn) && !empty($btn['icon']) && xi_menu_icon_type(trim($btn['icon'])) === 'fa') {
                return true;
            }
        }
    }
    return (bool) apply_filters('xi_fa_used', false);
}

function xi_menu_fa_enqueue()
{
    $flag = get_option('xi_menu_uses_fa', null);
    if ($flag === null) {
        xi_menu_refresh_fa_flag(); // 写入为自动加载，之后不再单独查询
        $flag = get_option('xi_menu_uses_fa');
    }
    if ($flag || xi_fa_used_in_options()) {
        wp_enqueue_style('xi-fa', get_template_directory_uri() . '/assets/vendor/font-awesome/css/font-awesome.min.css', array(), '4.7.0');
    }
}
add_action('wp_enqueue_scripts', 'xi_menu_fa_enqueue');

function xi_menu_refresh_fa_flag()
{
    global $wpdb;
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_xi_menu_icon' AND p.post_type = 'nav_menu_item' AND (pm.meta_value LIKE %s OR pm.meta_value LIKE %s)",
        'fa %',
        'fa-%'
    ));
    update_option('xi_menu_uses_fa', $count ? 1 : 0, true);
}

/* ---------- 后台：菜单项字段 ---------- */

function xi_menu_item_fields($item_id, $item)
{
    $icon = (string) get_post_meta($item_id, '_xi_menu_icon', true);
    $only = (bool) get_post_meta($item_id, '_xi_menu_icon_only', true);
    ?>
    <div class="xi-menu-icon-field description-wide" style="margin:8px 0">
        <label for="xi-menu-icon-<?php echo (int) $item_id; ?>">
            <?php esc_html_e('图标', 'xi'); ?><br>
            <input type="text" id="xi-menu-icon-<?php echo (int) $item_id; ?>" class="widefat" name="xi_menu_icon[<?php echo (int) $item_id; ?>]" value="<?php echo esc_attr($icon); ?>" list="xi-menu-icon-list" placeholder="home / fa fa-home / 🔥 / https://…">
        </label>
        <span class="description"><?php esc_html_e('主题图标名、Font Awesome 4.7 类名、emoji 或图片地址，留空不显示图标', 'xi'); ?></span>
        <label style="display:block;margin-top:6px">
            <input type="checkbox" name="xi_menu_icon_only[<?php echo (int) $item_id; ?>]" value="1" <?php checked($only); ?>>
            <?php esc_html_e('只显示图标（文字仅对读屏软件可见，鼠标悬停显示名称）', 'xi'); ?>
        </label>
    </div>
    <?php
}
add_action('wp_nav_menu_item_custom_fields', 'xi_menu_item_fields', 10, 2);

/**
 * 输入框的候选列表：主题图标名与品牌图标名
 */
function xi_menu_icon_datalist()
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'nav-menus') {
        return;
    }
    echo '<datalist id="xi-menu-icon-list">';
    foreach (array_merge(array_keys(xi_icon_set()), xi_menu_brands()) as $name) {
        echo '<option value="' . esc_attr($name) . '">';
    }
    echo '</datalist>';
}
add_action('admin_footer', 'xi_menu_icon_datalist');

function xi_menu_item_save($menu_id, $item_id)
{
    // 只处理「外观 → 菜单」页面的表单提交（该页面在调用前已校验 update-nav_menu nonce）
    if (!current_user_can('edit_theme_options') || !isset($_POST['xi_menu_icon']) || !is_array($_POST['xi_menu_icon']) || !array_key_exists($item_id, $_POST['xi_menu_icon'])) {
        return;
    }
    $icon = trim(sanitize_text_field(wp_unslash($_POST['xi_menu_icon'][$item_id])));
    if (xi_menu_icon_type($icon) === 'img') {
        $icon = esc_url_raw($icon);
    }
    if ($icon === '') {
        delete_post_meta($item_id, '_xi_menu_icon');
    } else {
        update_post_meta($item_id, '_xi_menu_icon', $icon);
    }
    if (!empty($_POST['xi_menu_icon_only'][$item_id])) {
        update_post_meta($item_id, '_xi_menu_icon_only', 1);
    } else {
        delete_post_meta($item_id, '_xi_menu_icon_only');
    }
    xi_menu_refresh_fa_flag();
}
add_action('wp_update_nav_menu_item', 'xi_menu_item_save', 10, 2);
add_action('deleted_post', function ($post_id, $post = null) {
    if ($post && $post->post_type === 'nav_menu_item') {
        xi_menu_refresh_fa_flag();
    }
}, 10, 2);
