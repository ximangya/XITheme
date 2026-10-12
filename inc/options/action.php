<?php
/**
 * 后台杂项
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 去掉嵌入式 CSF 在「工具」菜单里注册的框架介绍页
 */
function xi_remove_csf_welcome_page()
{
    remove_submenu_page('tools.php', 'csf-welcome');
}
add_action('admin_menu', 'xi_remove_csf_welcome_page', 999);

/**
 * 后台样式：主题色 + 与前台一致的圆角
 * 作用于主题设置页、小工具页（CSF 字段）和文章设置框
 */
function xi_csf_admin_style()
{
    $screen = get_current_screen();
    if (!$screen) {
        return;
    }
    $is_options = strpos($screen->id, XI_OPTIONS) !== false;
    if (!$is_options && !in_array($screen->base, array('widgets', 'customize', 'post'), true)) {
        return;
    }

    $color  = xi_theme_color();
    $radius = (int) _xz('radius', 16);
    $ctrl   = xi_ctrl_radius($radius);
    ?>
    <style>
        :root { --xi-main: <?php echo $color; ?>; --xi-r: <?php echo $radius; ?>px; --xi-cr: <?php echo $ctrl; ?>px; }

        /* 控件圆角：设置页、小工具、文章设置框 */
        .csf .button, .csf .button-primary, .csf .button-secondary, .csf .csf-warning-primary,
        .csf input[type="text"], .csf input[type="number"], .csf input[type="email"], .csf input[type="url"],
        .csf textarea, .csf select, .csf .chosen-container .chosen-single, .csf .wp-color-result,
        .csf-field-upload .csf--preview .csf--src, .csf-field-spinner .ui-spinner,
        #xi_post_meta input[type="text"], #xi_post_meta textarea { border-radius: var(--xi-cr) !important; }
        .csf .wp-color-result .color-alpha, .csf .wp-color-result-text { border-radius: 0 var(--xi-cr) var(--xi-cr) 0; }
        .csf input:focus, .csf textarea:focus, .csf select:focus,
        #xi_post_meta input:focus, #xi_post_meta textarea:focus { border-color: var(--xi-main); box-shadow: 0 0 0 1px var(--xi-main); }

        .csf .button-primary { background: var(--xi-main); border-color: var(--xi-main); }
        .csf .button-primary:hover, .csf .button-primary:focus { background: var(--xi-main); border-color: var(--xi-main); filter: brightness(.92); }

        .csf-field-button_set .csf--button:first-child { border-radius: var(--xi-cr) 0 0 var(--xi-cr); }
        .csf-field-button_set .csf--button:last-child { border-radius: 0 var(--xi-cr) var(--xi-cr) 0; }
        .csf-field-button_set .csf--button:only-child { border-radius: var(--xi-cr); }
        .csf-field-button_set .csf--active, .csf-field-button_set .csf--active:hover { background: var(--xi-main); border-color: var(--xi-main); }

        .csf-field-switcher .csf--switcher { border-radius: calc(var(--xi-cr) + 4px); }
        .csf-field-switcher .csf--ball { border-radius: var(--xi-cr); }
        .csf-field-switcher .csf--active { background: var(--xi-main); }

        .csf-field-slider .ui-slider { border-radius: 999px; }
        .csf-field-slider .ui-slider-range { background: var(--xi-main); border-radius: 999px; }
        .csf-field-slider .ui-slider-handle { border-radius: 50%; border-color: var(--xi-main); }

        /* 主题色调色板：圆角色块，自定义块为四色条 */
        .xi-color-palette .csf--palette { display: inline-flex; border: 2px solid transparent; border-radius: calc(var(--xi-cr) + 4px); padding: 3px; overflow: hidden; box-shadow: none; }
        .xi-color-palette .csf--palette span { width: 40px; height: 40px; line-height: 40px; border-radius: var(--xi-cr); }
        .xi-color-palette .csf--palette span + span { width: 10px; border-radius: 0; }
        .xi-color-palette .csf--palette span:first-child:not(:only-child) { width: 10px; border-radius: var(--xi-cr) 0 0 var(--xi-cr); }
        .xi-color-palette .csf--palette span:last-child:not(:only-child) { border-radius: 0 var(--xi-cr) var(--xi-cr) 0; }
        .xi-color-palette .csf--palette:hover { border-color: #c9d3d0; }
        .xi-color-palette .csf--active, .xi-color-palette .csf--active:hover { border-color: var(--xi-main); box-shadow: none; }
        .xi-color-palette .csf--palette:before { top: 50%; left: 50%; width: 18px; height: 18px; margin: -9px 0 0 -9px; line-height: 18px; border-radius: 50%; background: rgba(0, 0, 0, .35); }

        .csf-field-group .csf-cloneable-item { border-radius: var(--xi-cr); overflow: hidden; margin-bottom: 6px; }
        .csf-field-group .csf-cloneable-content { border-radius: 0 0 var(--xi-cr) var(--xi-cr); }
        .csf-submessage, .csf-notice { border-radius: var(--xi-cr); }

        /* 设置页框架 */
        .csf-options .csf-container { border-radius: var(--xi-r); overflow: hidden; box-shadow: 0 1px 2px rgba(10, 40, 34, .05), 0 12px 32px -14px rgba(10, 40, 34, .2); }
        .csf-options .csf-header-inner { background: #fff; border-bottom: 1px solid #e5eeeb; }
        .csf-options .csf-header-inner h1 { color: #17221f; }
        .csf-options .csf-header-inner h1 small { color: var(--xi-main); font-size: 12px; margin-left: 6px; font-weight: 400; }
        .csf-options .csf-search input { border-radius: 999px !important; padding-left: 14px; }
        .csf-theme-light .csf-nav-background, .csf-theme-light .csf-nav ul li a { background: #f4f8f7; }
        .csf-theme-light .csf-nav ul li a { margin: 2px 8px; border: 0 !important; border-radius: var(--xi-cr); color: #4a5955; }
        .csf-theme-light .csf-nav ul ul li a { background: transparent; }
        .csf-theme-light .csf-nav > ul { padding: 6px 0; }
        .csf-theme-light .csf-nav ul li a:hover { color: #17221f; background: rgba(15, 45, 40, .05); }
        .csf-theme-light .csf-nav ul li .csf-active { color: #17221f; font-weight: 600; background: rgba(15, 45, 40, .07); }
        .csf-theme-light .csf-nav ul li .csf-active::before {
            content: ""; position: absolute; left: 0; top: 50%; width: 3px; height: 16px; margin-top: -8px;
            border-radius: 3px; background: var(--xi-main);
        }
        .csf-nav-normal ul li .csf-active:after { display: none; }
        .csf-options .csf-footer { color: #84928e; }
        .csf-options .csf-section-title { border-radius: 0; }

        /* 小工具页 */
        .widgets-php .widget, .widgets-php .widgets-holder-wrap { border-radius: var(--xi-cr); }
        .widgets-php .widget-top { border-radius: var(--xi-cr); }
        .widgets-php .widget.open .widget-top { border-radius: var(--xi-cr) var(--xi-cr) 0 0; }
        .widgets-php .widget-inside { border-radius: 0 0 var(--xi-cr) var(--xi-cr); }

        /* 文章设置框 */
        #xi_post_meta { border-radius: var(--xi-cr); overflow: hidden; }
    </style>
    <?php
}
add_action('admin_head', 'xi_csf_admin_style');

/**
 * 设置页：邮件、短信、支付的测试按钮
 */
function xi_smtp_test_script()
{
    $screen = get_current_screen();
    if (!$screen || strpos($screen->id, XI_OPTIONS) === false) {
        return;
    }
    ?>
    <script>
    (function () {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.xi-admin-test button');
            if (!btn) return;
            var box = btn.closest('.xi-admin-test');
            var tip = box.querySelector('.description');
            var action = box.getAttribute('data-action');
            var nonces = <?php echo wp_json_encode(array('xi_smtp_test' => wp_create_nonce('xi_smtp_test'), 'xi_sms_test' => wp_create_nonce('xi_sms_test'), 'xi_pay_test' => wp_create_nonce('xi_pay_test'))); ?>;
            var data = new FormData();
            data.append('action', action);
            data.append('nonce', nonces[action] || '');
            var input = box.querySelector('input');
            if (input) data.append('to', input.value);
            btn.disabled = true;
            tip.textContent = '<?php echo esc_js(__('发送中…', 'xi')); ?>';
            fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    tip.textContent = res.data && res.data.msg ? res.data.msg : '<?php echo esc_js(__('发送失败', 'xi')); ?>';
                    tip.style.color = res.success ? '#0f9d58' : '#d13438';
                })
                .catch(function () { tip.textContent = '<?php echo esc_js(__('请求失败', 'xi')); ?>'; tip.style.color = '#d13438'; })
                .finally(function () { btn.disabled = false; });
        });
    })();
    </script>
    <?php
}
add_action('admin_footer', 'xi_smtp_test_script');

/* ---------- 固定链接：一键设置推荐结构 ---------- */

function xi_permalink_presets()
{
    return array(
        '/%post_id%.html'  => __('文章ID.html（推荐）', 'xi'),
        '/%postname%.html' => __('文章别名.html', 'xi'),
        '/archives/%post_id%' => __('archives/文章ID', 'xi'),
    );
}

/**
 * 设置 → 固定链接 页面顶部的一键设置卡片
 */
function xi_permalink_notice()
{
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'options-permalink' || !current_user_can('manage_options')) {
        return;
    }
    $current = (string) get_option('permalink_structure');
    echo '<div class="notice notice-info xi-permalink-box" style="padding:14px 16px;border-left-color:' . esc_attr(xi_theme_color()) . '">';
    echo '<p style="margin:0 0 4px;font-size:14px"><b>' . esc_html__('曦主题 · 一键设置固定链接', 'xi') . '</b></p>';
    echo '<p style="margin:0 0 10px;color:#646970">' . esc_html__('推荐使用 /%post_id%.html：链接短、不含中文、改标题也不会变，对 SEO 和分享都友好。当前结构：', 'xi') . '<code>' . esc_html($current !== '' ? $current : __('朴素（?p=123）', 'xi')) . '</code></p>';
    echo '<p style="margin:0;display:flex;flex-wrap:wrap;gap:8px">';
    foreach (xi_permalink_presets() as $structure => $label) {
        $url = wp_nonce_url(admin_url('admin-post.php?action=xi_set_permalink&structure=' . rawurlencode($structure)), 'xi_set_permalink');
        $on  = $current === $structure;
        echo '<a class="button' . ($on ? '' : ($structure === '/%post_id%.html' ? ' button-primary' : '')) . '" href="' . esc_url($url) . '"' . ($on ? ' aria-disabled="true" style="pointer-events:none;opacity:.6"' : '') . '>' . esc_html($label) . ($on ? ' ✓' : '') . '</a>';
    }
    echo '</p></div>';
}
add_action('admin_notices', 'xi_permalink_notice');

function xi_set_permalink()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('没有权限', 'xi'), 403);
    }
    check_admin_referer('xi_set_permalink');
    $structure = isset($_GET['structure']) ? wp_unslash($_GET['structure']) : '';
    if (!isset(xi_permalink_presets()[$structure])) {
        wp_die(esc_html__('无效的固定链接结构', 'xi'), 400);
    }
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure($structure);
    flush_rewrite_rules();
    wp_safe_redirect(admin_url('options-permalink.php?settings-updated=true&xi_permalink=1'));
    exit;
}
add_action('admin_post_xi_set_permalink', 'xi_set_permalink');
