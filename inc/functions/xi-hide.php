<?php
/**
 * 隐藏内容 / 付费内容
 *
 * [xi_hide type="login"]登录可见[/xi_hide]
 * [xi_hide type="reply"]评论后可见[/xi_hide]
 * [xi_hide type="points" points="20"]支付积分可见[/xi_hide]
 * [xi_hide type="vip" vip="1"]会员可见（1 及以上等级）[/xi_hide]
 * [xi_hide type="password" password="1234"]输入密码可见[/xi_hide]
 * [xi_hide type="role" roles="editor,author"]指定用户组可见（可含 xi_vip 会员）[/xi_hide]
 * [xi_hide type="time" time="2026-12-01 20:00"]到点后公开[/xi_hide]
 * 所有类型都可加 tip="自定义提示文字"，替换锁定时的默认说明（如：关注公众号回复「曦」获取密码）
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_user_unlocked($user_id, $post_id)
{
    $list = get_user_meta($user_id, 'xi_unlocked', true);
    return is_array($list) && in_array((int) $post_id, $list, true);
}

function xi_user_commented($user_id, $post_id)
{
    return (bool) get_comments(array(
        'user_id' => $user_id,
        'post_id' => $post_id,
        'status'  => 'approve',
        'count'   => true,
    ));
}

/**
 * 文章中积分解锁的价格：取所有 points 类型隐藏短代码与「隐藏内容」区块中的最高值
 */
function xi_post_hide_points($post_id)
{
    $content = get_post_field('post_content', $post_id);
    $price   = 0;
    if (has_shortcode($content, 'xi_hide')) {
        preg_match_all('/' . get_shortcode_regex(array('xi_hide')) . '/s', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $atts = shortcode_parse_atts($m[3]);
            if (is_array($atts) && isset($atts['type']) && $atts['type'] === 'points') {
                $price = max($price, isset($atts['points']) ? (int) $atts['points'] : 0);
            }
        }
    }
    if (strpos($content, '<!-- wp:xi/hide') !== false && function_exists('parse_blocks')) {
        $price = max($price, xi_hide_blocks_points(parse_blocks($content)));
    }
    return $price;
}

function xi_hide_blocks_points($blocks)
{
    $price = 0;
    foreach ($blocks as $b) {
        if ($b['blockName'] === 'xi/hide' && isset($b['attrs']['type']) && $b['attrs']['type'] === 'points') {
            // 区块属性等于默认值时不会写进文章，默认 10
            $price = max($price, isset($b['attrs']['points']) ? (int) $b['attrs']['points'] : 10);
        }
        if (!empty($b['innerBlocks'])) {
            $price = max($price, xi_hide_blocks_points($b['innerBlocks']));
        }
    }
    return $price;
}

/**
 * 去掉隐藏短代码与「隐藏内容」区块（含其中内容），用于摘要、SEO 描述等直接读取正文的地方
 */
function xi_strip_hidden($content)
{
    // 隐藏内容，以及「购买后可见」等有条件显示内容的扩展区块
    $names = array('hide');
    if (function_exists('xi_bx_defs')) {
        foreach (xi_bx_defs() as $name => $def) {
            if (!empty($def['inner'])) {
                $names[] = $name;
            }
        }
    }
    foreach ($names as $name) {
        $content = preg_replace('#<!-- wp:xi/' . $name . '\b.*?<!-- /wp:xi/' . $name . ' -->#s', '', (string) $content);
    }
    return (string) preg_replace('/' . get_shortcode_regex(array_map(function ($n) {
        return 'xi_' . $n;
    }, $names)) . '/s', '', $content);
}

/**
 * 判断是否可见，返回 true 或锁定原因
 */
function xi_hide_can_view($atts, $post_id)
{
    $user_id = get_current_user_id();
    $post    = get_post($post_id);

    if ($user_id && ($post && (int) $post->post_author === $user_id || current_user_can('edit_others_posts'))) {
        return true;
    }

    switch ($atts['type']) {
        case 'password':
            return (string) $atts['password'] === '' || xi_hide_password_ok($post_id, $atts['password']) ? true : 'password';
        case 'role':
            $roles = array_filter(array_map('trim', explode(',', (string) $atts['roles'])));
            if (!$roles) {
                return $user_id ? true : 'login';
            }
            if (!$user_id) {
                return 'role';
            }
            $user = get_userdata($user_id);
            if ($user && array_intersect((array) $user->roles, $roles)) {
                return true;
            }
            return in_array('xi_vip', $roles, true) && xi_vip_enabled() && xi_get_vip_level($user_id) ? true : 'role';
        case 'time':
            $at = xi_hide_time($atts['time']);
            return !$at || time() >= $at ? true : 'time';
        case 'reply':
            return ($user_id && xi_user_commented($user_id, $post_id)) ? true : 'reply';
        case 'points':
            if (!xi_points_enabled()) {
                return $user_id ? true : 'login';
            }
            if (!$user_id) {
                return 'points';
            }
            if (xi_user_unlocked($user_id, $post_id)) {
                return true;
            }
            if (_xz('vip_free_points', true) && xi_get_vip_level($user_id)) {
                return true;
            }
            return 'points';
        case 'vip':
            if (!xi_vip_enabled()) {
                return $user_id ? true : 'login';
            }
            return xi_get_vip_level($user_id) >= max(1, (int) $atts['vip']) ? true : 'vip';
        default:
            return $user_id ? true : 'login';
    }
}

function xi_hide_shortcode($atts, $content = '')
{
    $atts = shortcode_atts(array(
        'type'     => 'login',
        'points'   => 0,
        'vip'      => 1,
        'password' => '',
        'roles'    => '',
        'time'     => '',
        'tip'      => '',
    ), $atts, 'xi_hide');

    return xi_hide_output($atts, do_shortcode($content));
}
add_shortcode('xi_hide', 'xi_hide_shortcode');

/**
 * 隐藏内容的输出，短代码与「隐藏内容」区块共用；$html 为已渲染好的内容
 */
function xi_hide_output($atts, $html)
{
    $post_id = get_the_ID();
    $result  = xi_hide_can_view($atts, $post_id);

    if ($result === true) {
        return '<div class="xi-hide is-unlocked"><div class="xi-hide-label">' . xi_icon('check') . esc_html__('隐藏内容', 'xi') . '</div>' . $html . '</div>';
    }

    $tip = isset($atts['tip']) ? trim((string) $atts['tip']) : '';
    switch ($result) {
        case 'password':
            $text   = __('此处内容需输入密码查看', 'xi');
            $action = '<form class="xi-hide-pw" data-xi-hide-pw="' . (int) $post_id . '"><input type="password" name="pw" required autocomplete="off" placeholder="' . esc_attr__('输入密码', 'xi') . '" aria-label="' . esc_attr__('密码', 'xi') . '"><button type="submit" class="xi-btn xi-btn-accent xi-btn-sm">' . xi_icon('key') . esc_html__('查看', 'xi') . '</button></form>';
            break;
        case 'role':
            $names = array();
            foreach (array_filter(array_map('trim', explode(',', (string) $atts['roles']))) as $role) {
                $names[] = $role === 'xi_vip' ? __('会员', 'xi') : (function_exists('xi_role_options') && isset(xi_role_options()[$role]) ? xi_role_options()[$role] : translate_user_role(ucfirst($role)));
            }
            $text   = sprintf(__('此处内容仅限 %s 查看', 'xi'), implode('、', $names));
            $action = is_user_logged_in() ? '' : '<a class="xi-btn xi-btn-accent xi-btn-sm" href="' . esc_url(wp_login_url(get_permalink())) . '" data-xi-sign="login">' . xi_icon('login') . esc_html__('登录', 'xi') . '</a>';
            break;
        case 'time':
            $text   = sprintf(__('此处内容将于 %s 公开', 'xi'), wp_date('Y-m-d H:i', xi_hide_time($atts['time'])));
            $action = '<span class="xi-hide-countdown" data-xi-countdown="' . (int) xi_hide_time($atts['time']) . '"></span>';
            break;
        case 'reply':
            $text   = __('评论本文后刷新页面即可查看隐藏内容', 'xi');
            $action = is_user_logged_in()
                ? (xi_user_can('comment') ? '<a class="xi-btn xi-btn-accent xi-btn-sm" href="#respond">' . esc_html__('去评论', 'xi') . '</a>' : '')
                : '<a class="xi-btn xi-btn-accent xi-btn-sm" href="' . esc_url(wp_login_url(get_permalink())) . '" data-xi-sign="login">' . esc_html__('登录后评论', 'xi') . '</a>';
            if (is_user_logged_in() && !xi_user_can('comment')) {
                $text = __('此处内容需评论后查看，你所在的用户组暂无评论权限', 'xi');
            }
            break;
        case 'points':
            $price  = xi_post_hide_points($post_id);
            $text   = sprintf(__('此处内容需支付 %1$d %2$s查看', 'xi'), $price, xi_points_name());
            if (_xz('vip_free_points', true) && xi_vip_enabled() && xi_vip_levels()) {
                $text .= __('，会员可免费查看', 'xi');
            }
            $action = '<button type="button" class="xi-btn xi-btn-accent xi-btn-sm" data-xi-unlock="' . (int) $post_id . '">' . xi_icon('coin') . sprintf(esc_html__('支付 %d 解锁', 'xi'), $price) . '</button>';
            if (!is_user_logged_in()) {
                $action = '<a class="xi-btn xi-btn-accent xi-btn-sm" href="' . esc_url(wp_login_url(get_permalink())) . '" data-xi-sign="login">' . xi_icon('login') . esc_html__('登录后解锁', 'xi') . '</a>';
            } elseif (!xi_user_can('unlock')) {
                $text  .= __('（你所在的用户组暂不支持积分解锁）', 'xi');
                $action = '';
            }
            break;
        case 'vip':
            $config = xi_vip_level_config(max(1, (int) $atts['vip']));
            $text   = sprintf(__('此处内容仅限%s及以上查看', 'xi'), isset($config['name']) ? $config['name'] : __('会员', 'xi'));
            $action = is_user_logged_in()
                ? (!xi_user_can('vip_buy') ? '' : '<a class="xi-btn xi-btn-accent xi-btn-sm" href="' . esc_url(xi_uc_url('vip')) . '">' . xi_icon('crown') . esc_html__('开通会员', 'xi') . '</a>')
                : '<a class="xi-btn xi-btn-accent xi-btn-sm" href="' . esc_url(wp_login_url(get_permalink())) . '" data-xi-sign="login">' . esc_html__('登录', 'xi') . '</a>';
            break;
        default:
            $text   = __('此处内容登录后可见', 'xi');
            $action = '<a class="xi-btn xi-btn-accent xi-btn-sm" href="' . esc_url(wp_login_url(get_permalink())) . '" data-xi-sign="login">' . xi_icon('login') . esc_html__('登录', 'xi') . '</a>';
    }

    if ($tip !== '') {
        $text = $tip;
    }
    return '<div class="xi-hide is-locked is-' . esc_attr($result) . '"><span class="xi-hide-icon">' . xi_icon('lock') . '</span><p>' . esc_html($text) . '</p>' . $action . '</div>';
}

/* ---------- 密码可见 ---------- */

/**
 * 公开时间：站点时区的「Y-m-d H:i」转时间戳，无效返回 0
 */
function xi_hide_time($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }
    $date = date_create_from_format('Y-m-d H:i', $value, wp_timezone()) ?: date_create($value, wp_timezone());
    return $date ? $date->getTimestamp() : 0;
}

function xi_hide_pw_hash($post_id, $password)
{
    return substr(hash_hmac('sha256', $post_id . '|' . $password, wp_salt('auth')), 0, 16);
}

/**
 * 访客已输入过正确密码（记在 Cookie 里，30 天有效）
 */
function xi_hide_password_ok($post_id, $password)
{
    $cookie = isset($_COOKIE['xi_hide_pw']) ? explode('.', sanitize_text_field(wp_unslash($_COOKIE['xi_hide_pw']))) : array();
    return in_array(xi_hide_pw_hash($post_id, $password), $cookie, true);
}

/**
 * 文章中所有「密码可见」的密码（短代码与区块）
 */
function xi_post_hide_passwords($post_id)
{
    $content   = (string) get_post_field('post_content', $post_id);
    $passwords = array();
    if (has_shortcode($content, 'xi_hide')) {
        preg_match_all('/' . get_shortcode_regex(array('xi_hide')) . '/s', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $atts = shortcode_parse_atts($m[3]);
            if (is_array($atts) && isset($atts['type'], $atts['password']) && $atts['type'] === 'password' && $atts['password'] !== '') {
                $passwords[] = (string) $atts['password'];
            }
        }
    }
    if (strpos($content, '<!-- wp:xi/hide') !== false && function_exists('parse_blocks')) {
        $walk = function ($blocks) use (&$walk, &$passwords) {
            foreach ($blocks as $b) {
                if ($b['blockName'] === 'xi/hide' && isset($b['attrs']['type'], $b['attrs']['password']) && $b['attrs']['type'] === 'password' && $b['attrs']['password'] !== '') {
                    $passwords[] = (string) $b['attrs']['password'];
                }
                if (!empty($b['innerBlocks'])) {
                    $walk($b['innerBlocks']);
                }
            }
        };
        $walk(parse_blocks($content));
    }
    return array_values(array_unique($passwords));
}

function xi_ajax_hide_password()
{
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $pw      = isset($_POST['pw']) ? trim((string) wp_unslash($_POST['pw'])) : '';
    $post    = $post_id ? get_post($post_id) : null;
    if (!$post || $post->post_status !== 'publish' || $pw === '') {
        wp_send_json(array('ok' => false, 'msg' => __('请输入密码', 'xi')));
    }
    // 防止暴力尝试：同一 IP 每 10 分钟最多 10 次
    $key   = 'xi_hide_pw_' . md5(xi_client_ip());
    $tries = (int) get_transient($key);
    if ($tries >= 10) {
        wp_send_json(array('ok' => false, 'msg' => __('尝试次数过多，请 10 分钟后再试', 'xi')));
    }
    if (!in_array($pw, xi_post_hide_passwords($post_id), true)) {
        set_transient($key, $tries + 1, 10 * MINUTE_IN_SECONDS);
        wp_send_json(array('ok' => false, 'msg' => __('密码不正确', 'xi')));
    }
    $list   = isset($_COOKIE['xi_hide_pw']) ? explode('.', sanitize_text_field(wp_unslash($_COOKIE['xi_hide_pw']))) : array();
    $list[] = xi_hide_pw_hash($post_id, $pw);
    $list   = array_slice(array_values(array_unique(array_filter($list))), -50);
    $path = COOKIEPATH ? COOKIEPATH : '/';
    if (PHP_VERSION_ID >= 70300) {
        setcookie('xi_hide_pw', implode('.', $list), array('expires' => time() + 30 * DAY_IN_SECONDS, 'path' => $path, 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'));
    } else {
        setcookie('xi_hide_pw', implode('.', $list), time() + 30 * DAY_IN_SECONDS, $path, COOKIE_DOMAIN, is_ssl(), true);
    }
    wp_send_json(array('ok' => true, 'msg' => __('密码正确', 'xi'), 'reload' => true));
}
add_action('wp_ajax_xi_hide_password', 'xi_ajax_hide_password');
add_action('wp_ajax_nopriv_xi_hide_password', 'xi_ajax_hide_password');

/**
 * 摘要、SEO 描述、Feed 中去掉隐藏内容
 */
function xi_strip_hide_shortcode($content)
{
    return preg_replace('/' . get_shortcode_regex(array('xi_hide')) . '/s', '', $content);
}
add_filter('the_content_feed', 'xi_strip_hide_shortcode', 5);
add_filter('the_excerpt_rss', 'xi_strip_hide_shortcode', 5);

/**
 * 积分解锁
 *
 * @return string|WP_Error
 */
function xi_unlock_post($user_id, $post_id)
{
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        return new WP_Error('post', __('文章不存在', 'xi'));
    }
    // 防止双击等并发请求重复扣积分
    $lock = 'unlock_' . $user_id . '_' . $post_id;
    if (!xi_lock($lock)) {
        return new WP_Error('busy', __('正在处理，请稍候', 'xi'));
    }
    $result = xi_unlock_post_locked($user_id, $post, $post_id);
    xi_unlock($lock);
    return $result;
}

function xi_unlock_post_locked($user_id, $post, $post_id)
{
    wp_cache_delete($user_id, 'user_meta');
    if (xi_user_unlocked($user_id, $post_id)) {
        return __('已解锁', 'xi');
    }
    // 作者本人、编辑以上角色、享受免费查看的会员：本来就能看，不扣积分
    if ((int) $post->post_author === (int) $user_id || user_can($user_id, 'edit_others_posts') || (_xz('vip_free_points', true) && xi_get_vip_level($user_id))) {
        return __('你可以免费查看该内容', 'xi');
    }
    $price = xi_post_hide_points($post_id);
    if ($price <= 0) {
        return new WP_Error('price', __('该文章没有积分付费内容', 'xi'));
    }
    $result = xi_change_points($user_id, -$price, sprintf(__('解锁《%s》', 'xi'), wp_trim_words($post->post_title, 12, '…')));
    if (is_wp_error($result)) {
        return $result;
    }

    $list   = get_user_meta($user_id, 'xi_unlocked', true);
    $list   = is_array($list) ? $list : array();
    $list[] = (int) $post_id;
    update_user_meta($user_id, 'xi_unlocked', array_values(array_unique($list)));

    // 作者分成
    $share = (int) _xz('points_author_share', 50);
    if ($share > 0 && (int) $post->post_author !== $user_id) {
        $income = (int) floor($price * min(100, $share) / 100);
        if ($income > 0) {
            xi_change_points($post->post_author, $income, sprintf(__('《%s》被解锁分成', 'xi'), wp_trim_words($post->post_title, 12, '…')));
        }
    }
    return __('解锁成功', 'xi');
}
