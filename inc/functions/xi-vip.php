<?php
/**
 * VIP 会员：等级配置、会员状态、开通、标识
 * 状态保存在用户 meta：xi_vip_level（1/2）、xi_vip_exp（到期时间戳，0 为永久）
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_vip_enabled()
{
    return (bool) _xz('vip_s', true);
}

function xi_vip_level_config($level)
{
    $level = (int) $level;
    if ($level !== 1 && $level !== 2) {
        return array();
    }
    $config = wp_parse_args((array) _xz('vip_' . $level, array()), xi_vip_defaults($level));
    $config['plans'] = array_values(array_filter((array) $config['plans'], 'is_array'));
    return $config;
}

/**
 * 已启用的等级
 */
function xi_vip_levels()
{
    $levels = array();
    foreach (array(1, 2) as $level) {
        $config = xi_vip_level_config($level);
        if (!empty($config['enable'])) {
            $levels[$level] = $config;
        }
    }
    return $levels;
}

/**
 * 当前有效等级，过期或功能关闭返回 0
 */
function xi_get_vip_level($user_id)
{
    if (!$user_id || !xi_vip_enabled()) {
        return 0;
    }
    $level = (int) get_user_meta($user_id, 'xi_vip_level', true);
    if (!$level) {
        return 0;
    }
    $exp = (int) get_user_meta($user_id, 'xi_vip_exp', true);
    return ($exp && $exp < time()) ? 0 : $level;
}

function xi_get_vip_exp($user_id)
{
    return (int) get_user_meta($user_id, 'xi_vip_exp', true);
}

function xi_vip_exp_text($user_id)
{
    $exp = xi_get_vip_exp($user_id);
    return $exp ? sprintf(__('%s 到期', 'xi'), wp_date('Y-m-d', $exp)) : __('永久有效', 'xi');
}

/**
 * 开通 / 续费：同等级在有效期内续期，升级则从现在起算
 *
 * @param int $days 0 为永久
 */
function xi_set_vip($user_id, $level, $days)
{
    $current = xi_get_vip_level($user_id);
    $exp     = xi_get_vip_exp($user_id);

    if ((int) $days === 0) {
        $new_exp = 0;
    } elseif ($current === (int) $level && $exp > time()) {
        $new_exp = $exp + $days * DAY_IN_SECONDS;
    } elseif ($current === (int) $level && $exp === 0) {
        return;
    } else {
        $new_exp = time() + $days * DAY_IN_SECONDS;
    }

    update_user_meta($user_id, 'xi_vip_level', (int) $level);
    update_user_meta($user_id, 'xi_vip_exp', $new_exp);
    do_action('xi_vip_changed', $user_id, $level, $new_exp);
}

function xi_remove_vip($user_id)
{
    delete_user_meta($user_id, 'xi_vip_level');
    delete_user_meta($user_id, 'xi_vip_exp');
}

/**
 * 购买套餐
 *
 * @param string $pay balance | points
 * @return string|WP_Error
 */
function xi_buy_vip($user_id, $level, $plan_index, $pay)
{
    if (!xi_vip_enabled()) {
        return new WP_Error('off', __('会员功能未开启', 'xi'));
    }
    $levels = xi_vip_levels();
    if (!isset($levels[$level]['plans'][$plan_index])) {
        return new WP_Error('plan', __('套餐不存在', 'xi'));
    }
    $config  = $levels[$level];
    $plan    = $config['plans'][$plan_index];
    $current = xi_get_vip_level($user_id);

    if ($current > $level) {
        return new WP_Error('lower', __('你已是更高等级会员', 'xi'));
    }
    if ($current === $level && xi_get_vip_exp($user_id) === 0) {
        return new WP_Error('forever', __('你已是永久会员', 'xi'));
    }

    $note = sprintf(__('开通%1$s · %2$s', 'xi'), $config['name'], $plan['name']);
    if ($pay === 'points') {
        $price = (int) $plan['points'];
        if (!xi_points_enabled() || $price <= 0) {
            return new WP_Error('pay', __('该套餐不支持积分支付', 'xi'));
        }
        $result = xi_change_points($user_id, -$price, $note);
    } else {
        $price = round((float) $plan['price'], 2);
        if (!xi_balance_enabled() || $price <= 0) {
            return new WP_Error('pay', __('该套餐不支持余额支付', 'xi'));
        }
        $result = xi_change_balance($user_id, -$price, $note);
    }
    if (is_wp_error($result)) {
        return $result;
    }

    xi_set_vip($user_id, $level, (int) $plan['days']);
    return sprintf(__('已成功开通%s', 'xi'), $config['name']);
}

/**
 * 会员标识
 */
function xi_vip_badge($user_id)
{
    $level = xi_get_vip_level($user_id);
    if (!$level) {
        return '';
    }
    $config = xi_vip_level_config($level);
    $color  = sanitize_hex_color($config['color']);
    return '<span class="xi-vip xi-vip-' . $level . '"' . ($color ? ' style="--vip:' . $color . '"' : '') . ' title="' . esc_attr($config['name']) . '">' . xi_icon('crown') . esc_html($config['name']) . '</span>';
}

/**
 * 评论者名称后显示会员标识
 */
function xi_comment_vip_badge($link, $author, $comment_id)
{
    $comment = get_comment($comment_id);
    if ($comment && $comment->user_id && !is_admin()) {
        $link .= xi_vip_badge($comment->user_id);
    }
    return $link;
}
add_filter('get_comment_author_link', 'xi_comment_vip_badge', 10, 3);
