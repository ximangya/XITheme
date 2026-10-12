<?php
/**
 * 后台安全入口：主题设置出错、前台登录弹窗异常、把自己锁在外面时，管理员应急进入后台
 *
 * - 入口：wp-login.php?xi_safe=密钥（密钥单独保存在 xi_safe_key，不受主题设置损坏影响）
 * - 持有入口的浏览器 2 小时内处于「安全模式」：不跳转前台弹窗、不受「禁止访问 wp-login.php」、
 *   登录失败锁定和 IP 封禁影响；密码校验照常进行
 * - 登录后进入应急面板：关闭前台登录弹窗、清除登录锁定、清除主题缓存、切换到默认主题
 * - 后台都打不开时：在 wp-config.php 加 define('XI_SAFE_MODE', true); 对所有访客开启安全模式
 *
 * 本文件只依赖 WordPress 核心函数，尽量在主题其他模块之前加载
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 安全入口密钥（首次读取时生成）
 */
function xi_safe_key()
{
    $key = (string) get_option('xi_safe_key', '');
    if (strlen($key) < 16) {
        $key = strtolower(wp_generate_password(24, false));
        update_option('xi_safe_key', $key, false);
    }
    return $key;
}

function xi_safe_url()
{
    return add_query_arg('xi_safe', xi_safe_key(), site_url('wp-login.php', 'login'));
}

/**
 * 安全模式凭证：Cookie 里保存 过期时间|签名，签名绑定密钥，更换密钥后旧凭证全部失效
 */
function xi_safe_sign($expire)
{
    return hash_hmac('sha256', 'xi_safe|' . $expire, xi_safe_key() . wp_salt('auth'));
}

function xi_safe_active()
{
    static $active = null;
    if ($active !== null) {
        return $active;
    }
    if (defined('XI_SAFE_MODE') && XI_SAFE_MODE) {
        return $active = true;
    }
    $active = false;
    if (!empty($_COOKIE['xi_safe'])) {
        $parts = explode('|', (string) wp_unslash($_COOKIE['xi_safe']), 2);
        if (count($parts) === 2 && (int) $parts[0] > time() && hash_equals(xi_safe_sign((int) $parts[0]), $parts[1])) {
            $active = true;
        }
    }
    return $active;
}

/**
 * 访问入口：校验密钥，写入 2 小时的安全模式 Cookie，跳到原生登录页（登录后进入应急面板）
 */
function xi_safe_entry()
{
    if (!isset($_GET['xi_safe'])) {
        return;
    }
    $given = (string) wp_unslash($_GET['xi_safe']);
    // 错误密钥按 IP 计次，防止猜测
    $fail_key = 'xi_safe_fail_' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $fails    = (int) get_transient($fail_key);
    if ($fails >= 10 || !hash_equals(xi_safe_key(), $given)) {
        set_transient($fail_key, $fails + 1, HOUR_IN_SECONDS);
        wp_die(esc_html__('安全入口地址无效', 'xi'), esc_html__('访问受限', 'xi'), array('response' => 403));
    }
    delete_transient($fail_key);
    $expire = time() + 2 * HOUR_IN_SECONDS;
    setcookie('xi_safe', $expire . '|' . xi_safe_sign($expire), $expire, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
    if (SITECOOKIEPATH !== COOKIEPATH) {
        setcookie('xi_safe', $expire . '|' . xi_safe_sign($expire), $expire, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }
    $panel = add_query_arg('action', 'xi_safe', site_url('wp-login.php', 'login'));
    wp_safe_redirect(is_user_logged_in() ? $panel : add_query_arg('redirect_to', rawurlencode($panel), site_url('wp-login.php', 'login')));
    exit;
}
add_action('login_init', 'xi_safe_entry', 0);

/**
 * 应急面板：wp-login.php?action=xi_safe
 */
function xi_safe_panel()
{
    if (!xi_safe_active()) {
        wp_die(esc_html__('请先通过安全入口地址访问', 'xi'), esc_html__('访问受限', 'xi'), array('response' => 403));
    }
    if (!is_user_logged_in()) {
        wp_safe_redirect(add_query_arg('redirect_to', rawurlencode(add_query_arg('action', 'xi_safe', site_url('wp-login.php', 'login'))), site_url('wp-login.php', 'login')));
        exit;
    }
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('只有管理员可以使用应急面板', 'xi'), esc_html__('没有权限', 'xi'), array('response' => 403));
    }

    $msg = '';
    if (!empty($_POST['xi_safe_do']) && check_admin_referer('xi_safe_panel')) {
        $do = sanitize_key($_POST['xi_safe_do']);
        switch ($do) {
            case 'sign_off':
                $o = get_option('xi_options');
                if (is_array($o)) {
                    $o['sign_modal_s']        = false;
                    $o['sign_block_wp_login'] = false;
                    update_option('xi_options', $o);
                }
                $msg = __('已关闭前台登录弹窗与「禁止访问 wp-login.php」，登录、注册恢复为 WordPress 原生页面', 'xi');
                break;
            case 'unlock':
                global $wpdb;
                $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_xi\\_login\\_fail\\_%' OR option_name LIKE '\\_transient\\_timeout\\_xi\\_login\\_fail\\_%' OR option_name LIKE 'xi\\_lock\\_%'");
                wp_cache_flush();
                $msg = __('已清除所有登录失败锁定和操作锁', 'xi');
                break;
            case 'ipban_off':
                $o = get_option('xi_options');
                if (is_array($o)) {
                    $o['ip_ban_s'] = false;
                    update_option('xi_options', $o);
                }
                $msg = __('已关闭 IP 封禁（封禁名单保留，可在主题设置中重新开启）', 'xi');
                break;
            case 'cache':
                update_option('xi_cache_ver', (string) microtime(true), true);
                delete_option('xi_rewrite_ver');
                flush_rewrite_rules(false);
                $msg = __('已清除主题查询缓存并刷新固定链接', 'xi');
                break;
            case 'theme':
                $target = xi_safe_fallback_theme();
                if ($target) {
                    switch_theme($target->get_stylesheet());
                    $msg = sprintf(__('已切换到「%s」。修复曦主题后可在「外观 → 主题」中重新启用', 'xi'), $target->get('Name'));
                } else {
                    $msg = __('没有找到可用的默认主题，请先安装一个 WordPress 官方主题', 'xi');
                }
                break;
            case 'rekey':
                delete_option('xi_safe_key');
                setcookie('xi_safe', '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true);
                wp_safe_redirect(admin_url());
                exit;
        }
    }

    $fallback = xi_safe_fallback_theme();
    login_header(__('曦主题 · 应急面板', 'xi'));
    ?>
    <style>
        .xi-safe{background:#fff;border:1px solid #c3c4c7;border-radius:8px;padding:20px 22px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
        .xi-safe h2{margin:0 0 6px;font-size:18px}
        .xi-safe p{margin:0 0 14px;color:#50575e}
        .xi-safe form{margin:0}
        .xi-safe .button{display:block;width:100%;margin:0 0 10px;text-align:left;white-space:normal;height:auto;padding:8px 12px;line-height:1.5}
        .xi-safe .button small{display:block;color:#646970}
        .xi-safe .xi-safe-msg{padding:10px 12px;margin-bottom:14px;border-left:4px solid #00a32a;background:#f0f6f0;color:#1d2327}
        #login{width:380px}
    </style>
    <div class="xi-safe">
        <h2><?php esc_html_e('应急面板', 'xi'); ?></h2>
        <p><?php esc_html_e('你正处于安全模式（2 小时内有效）。主题出问题时可以用下面的操作恢复站点。', 'xi'); ?></p>
        <?php if ($msg) : ?><div class="xi-safe-msg"><?php echo esc_html($msg); ?></div><?php endif; ?>
        <a class="button button-primary" href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('进入 WordPress 后台', 'xi'); ?></a>
        <form method="post">
            <?php wp_nonce_field('xi_safe_panel'); ?>
            <button class="button" name="xi_safe_do" value="sign_off"><?php esc_html_e('关闭前台登录弹窗', 'xi'); ?><small><?php esc_html_e('登录弹窗异常、跳转循环时使用', 'xi'); ?></small></button>
            <button class="button" name="xi_safe_do" value="unlock"><?php esc_html_e('清除登录锁定', 'xi'); ?><small><?php esc_html_e('多次输错密码被锁定时使用', 'xi'); ?></small></button>
            <button class="button" name="xi_safe_do" value="ipban_off"><?php esc_html_e('关闭 IP 封禁', 'xi'); ?><small><?php esc_html_e('误封了自己的 IP 时使用', 'xi'); ?></small></button>
            <button class="button" name="xi_safe_do" value="cache"><?php esc_html_e('清除主题缓存并刷新固定链接', 'xi'); ?><small><?php esc_html_e('页面 404、内容不更新时使用', 'xi'); ?></small></button>
            <?php if ($fallback) : ?>
                <button class="button" name="xi_safe_do" value="theme" onclick="return confirm('<?php echo esc_js(__('确定切换到默认主题吗？曦主题的设置会保留', 'xi')); ?>')"><?php printf(esc_html__('切换到「%s」', 'xi'), esc_html($fallback->get('Name'))); ?><small><?php esc_html_e('主题前台无法打开时临时使用，设置与数据都会保留', 'xi'); ?></small></button>
            <?php endif; ?>
            <button class="button" name="xi_safe_do" value="rekey" onclick="return confirm('<?php echo esc_js(__('更换后旧的安全入口地址立即失效，确定吗？', 'xi')); ?>')"><?php esc_html_e('更换安全入口地址', 'xi'); ?><small><?php esc_html_e('入口地址泄露时使用，新地址在主题设置「登录注册」中查看', 'xi'); ?></small></button>
        </form>
    </div>
    <?php
    login_footer();
    exit;
}
add_action('login_form_xi_safe', 'xi_safe_panel');

/**
 * 可切换的默认主题：优先较新的 WordPress 官方主题
 */
function xi_safe_fallback_theme()
{
    foreach (array('twentytwentyfive', 'twentytwentyfour', 'twentytwentythree', 'twentytwentytwo', 'twentytwentyone', 'twentytwenty') as $slug) {
        $theme = wp_get_theme($slug);
        if ($theme->exists() && !$theme->errors()) {
            return $theme;
        }
    }
    foreach (wp_get_themes(array('errors' => false)) as $theme) {
        if ($theme->get_template() !== get_template()) {
            return $theme;
        }
    }
    return null;
}

/**
 * 主题设置「登录注册」中显示入口地址
 */
function xi_safe_option_field()
{
    $url = xi_safe_url();
    echo '<input type="text" readonly value="' . esc_attr($url) . '" style="width:100%;max-width:620px" onclick="this.select()">';
    echo '<p class="description" style="margin-top:8px">' . esc_html__('请收藏或抄写到安全的地方，不要公开。主题出问题（登录弹窗异常、被锁定、误封 IP、前台打不开）时访问此地址，登录后进入应急面板。', 'xi') . '</p>';
    echo '<p class="description">' . wp_kses(__('后台也打不开时，可在 <code>wp-config.php</code> 中加入 <code>define(\'XI_SAFE_MODE\', true);</code> 临时对所有访客开启安全模式，修复后务必删除。主题代码出现致命错误时，WordPress 会给管理员邮箱发送「恢复模式」链接。', 'xi'), array('code' => array())) . '</p>';
    $mail = wp_nonce_url(admin_url('admin-post.php?action=xi_safe_mail'), 'xi_safe_mail');
    echo '<p><a class="button" href="' . esc_url($mail) . '">' . esc_html__('发送到管理员邮箱', 'xi') . '</a> ';
    echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=xi_safe_rekey'), 'xi_safe_rekey')) . '" onclick="return confirm(\'' . esc_js(__('更换后旧地址立即失效，确定吗？', 'xi')) . '\')">' . esc_html__('更换地址', 'xi') . '</a></p>';
    if (!empty($_GET['xi_safe_msg'])) {
        echo '<p style="color:#008a20">' . esc_html(sanitize_text_field(wp_unslash($_GET['xi_safe_msg']))) . '</p>';
    }
}

function xi_safe_admin_action()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('没有权限', 'xi'), 403);
    }
    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    check_admin_referer($action);
    $msg = '';
    if ($action === 'xi_safe_rekey') {
        delete_option('xi_safe_key');
        xi_safe_key();
        $msg = __('已更换安全入口地址', 'xi');
    } elseif ($action === 'xi_safe_mail') {
        $to   = get_option('admin_email');
        $body = '<p>' . esc_html__('曦主题后台安全入口：', 'xi') . '</p><p><a href="' . esc_url(xi_safe_url()) . '">' . esc_html(xi_safe_url()) . '</a></p><p>' . esc_html__('请妥善保管，不要转发给他人。', 'xi') . '</p>';
        $sent = function_exists('xi_send_mail') ? xi_send_mail($to, __('后台安全入口', 'xi'), __('后台安全入口', 'xi'), $body) : wp_mail($to, __('后台安全入口', 'xi'), wp_strip_all_tags($body));
        $msg  = $sent ? sprintf(__('已发送到 %s', 'xi'), $to) : __('邮件发送失败，请检查邮件设置', 'xi');
    }
    wp_safe_redirect(add_query_arg('xi_safe_msg', rawurlencode($msg), wp_get_referer() ? wp_get_referer() : admin_url()));
    exit;
}
add_action('admin_post_xi_safe_rekey', 'xi_safe_admin_action');
add_action('admin_post_xi_safe_mail', 'xi_safe_admin_action');
