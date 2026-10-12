<?php
/**
 * 用户资产：积分、余额、明细记录、获取规则、签到、卡密
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_points_enabled()
{
    return (bool) _xz('points_s', true);
}

function xi_balance_enabled()
{
    return (bool) _xz('balance_s', true);
}

function xi_points_name()
{
    $name = _xz('points_name', '积分');
    return $name ? $name : '积分';
}

function xi_balance_unit()
{
    $unit = _xz('balance_unit', '元');
    return $unit ? $unit : '元';
}

function xi_format_balance($amount)
{
    return number_format((float) $amount, 2, '.', '');
}

/* ---------- 读写 ---------- */

function xi_get_points($user_id)
{
    return max(0, (int) get_user_meta($user_id, 'xi_points', true));
}

function xi_get_balance($user_id)
{
    return max(0, round((float) get_user_meta($user_id, 'xi_balance', true), 2));
}

/**
 * 原子地给用户 Meta 里的数值加减
 * 用「比较并交换」写入：只有值没被其他请求改过时才更新，否则重读重算，
 * 同一用户并发扣款（双击、多标签页）不会互相覆盖导致少扣或扣成负数
 *
 * @param callable $calc 根据旧值算新值，返回 WP_Error 表示不允许（如余额不足）
 * @return mixed 新值或 WP_Error
 */
function xi_meta_cas($user_id, $key, $calc)
{
    global $wpdb;
    for ($i = 0; $i < 8; $i++) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT umeta_id, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id ASC LIMIT 1", $user_id, $key));
        if (!$row) {
            add_user_meta($user_id, $key, '0', true);
            continue;
        }
        $after = call_user_func($calc, $row->meta_value);
        if (is_wp_error($after)) {
            return $after;
        }
        $done = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->usermeta} SET meta_value = %s WHERE umeta_id = %d AND meta_value = %s", (string) $after, $row->umeta_id, $row->meta_value));
        wp_cache_delete($user_id, 'user_meta');
        if ((int) $done === 1 || (string) $row->meta_value === (string) $after) {
            return $after;
        }
        usleep(20000 * ($i + 1));
    }
    return new WP_Error('busy', __('操作太频繁，请稍后再试', 'xi'));
}

/**
 * 变更积分，扣减不足时返回 WP_Error
 */
function xi_change_points($user_id, $delta, $note)
{
    $delta = (int) $delta;
    if (!$user_id || !$delta) {
        return xi_get_points($user_id);
    }
    $after = xi_meta_cas($user_id, 'xi_points', function ($old) use ($delta) {
        $after = max(0, (int) $old) + $delta;
        return $after < 0 ? new WP_Error('points', sprintf(__('%s不足', 'xi'), xi_points_name())) : $after;
    });
    if (is_wp_error($after)) {
        return $after;
    }
    xi_add_log($user_id, 'points', $delta, $after, $note);
    do_action('xi_points_changed', $user_id, $delta, $after, $note);
    return $after;
}

function xi_change_balance($user_id, $delta, $note)
{
    $delta = round((float) $delta, 2);
    if (!$user_id || !$delta) {
        return xi_get_balance($user_id);
    }
    $after = xi_meta_cas($user_id, 'xi_balance', function ($old) use ($delta) {
        $after = round(max(0, round((float) $old, 2)) + $delta, 2);
        return $after < 0 ? new WP_Error('balance', __('余额不足', 'xi')) : xi_format_balance($after);
    });
    if (is_wp_error($after)) {
        return $after;
    }
    $after = (float) $after;
    xi_add_log($user_id, 'balance', $delta, $after, $note);
    do_action('xi_balance_changed', $user_id, $delta, $after, $note);
    return $after;
}

/**
 * 简单互斥锁：INSERT IGNORE 依赖 option_name 唯一约束，同一时刻只有一个请求能插入成功
 * （不能用 add_option：它内部是 ON DUPLICATE KEY UPDATE，并发时两边都会返回成功）
 * 用于卡密兑换、付费解锁等「先检查后写入」的操作
 */
function xi_lock($name, $ttl = 15)
{
    global $wpdb;
    $key = 'xi_lock_' . md5($name);
    $sql = $wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $key, (string) (time() + $ttl));
    if ((int) $wpdb->query($sql) === 1) {
        return true;
    }
    // 锁过期（上个请求异常中断）则清掉重试一次
    $expire = (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key));
    if ($expire && $expire < time()) {
        $wpdb->delete($wpdb->options, array('option_name' => $key));
        return (int) $wpdb->query($sql) === 1;
    }
    return false;
}

function xi_unlock($name)
{
    global $wpdb;
    $wpdb->delete($wpdb->options, array('option_name' => 'xi_lock_' . md5($name)));
}

/**
 * 明细记录：每个用户每类保留最近 100 条
 */
function xi_add_log($user_id, $type, $delta, $after, $note)
{
    $logs = get_user_meta($user_id, 'xi_logs_' . $type, true);
    $logs = is_array($logs) ? $logs : array();
    array_unshift($logs, array(
        't' => time(),
        'd' => $delta,
        'a' => $after,
        'n' => $note,
    ));
    update_user_meta($user_id, 'xi_logs_' . $type, array_slice($logs, 0, 100));
}

function xi_get_logs($user_id, $type, $limit = 30)
{
    $logs = get_user_meta($user_id, 'xi_logs_' . $type, true);
    return is_array($logs) ? array_slice($logs, 0, $limit) : array();
}

/* ---------- 积分获取规则 ---------- */

/**
 * 每日上限计数，返回是否还能获得
 */
function xi_earn_allowed($user_id, $key, $limit)
{
    if ($limit <= 0) {
        return true;
    }
    $meta  = 'xi_earn_' . $key;
    $today = current_time('Y-m-d');
    $data  = get_user_meta($user_id, $meta, true);
    $count = (is_array($data) && isset($data['date']) && $data['date'] === $today) ? (int) $data['count'] : 0;
    if ($count >= $limit) {
        return false;
    }
    update_user_meta($user_id, $meta, array('date' => $today, 'count' => $count + 1));
    return true;
}

function xi_points_on_register($user_id)
{
    $points = (int) _xz('points_register', 20);
    if (xi_points_enabled() && $points > 0) {
        xi_change_points($user_id, $points, __('注册奖励', 'xi'));
    }
}
add_action('user_register', 'xi_points_on_register');

function xi_points_on_publish($new_status, $old_status, $post)
{
    if (!xi_points_enabled() || $new_status !== 'publish' || $old_status === 'publish' || !in_array($post->post_type, (array) apply_filters('xi_points_post_types', array('post')), true)) {
        return;
    }
    if (get_post_meta($post->ID, '_xi_points_given', true)) {
        return;
    }
    $points = (int) _xz('points_post', 10);
    if ($points > 0 && xi_earn_allowed($post->post_author, 'post', (int) _xz('points_post_limit', 3))) {
        update_post_meta($post->ID, '_xi_points_given', 1);
        xi_change_points($post->post_author, $points, sprintf(__('发布文章《%s》', 'xi'), wp_trim_words($post->post_title, 12, '…')));
    }
}
add_action('transition_post_status', 'xi_points_on_publish', 10, 3);

function xi_points_give_comment($comment)
{
    $comment = get_comment($comment);
    if (!xi_points_enabled() || !$comment || !$comment->user_id || $comment->comment_approved !== '1' || get_comment_meta($comment->comment_ID, '_xi_points_given', true)) {
        return;
    }
    $points = (int) _xz('points_comment', 2);
    if ($points > 0 && xi_earn_allowed($comment->user_id, 'comment', (int) _xz('points_comment_limit', 10))) {
        update_comment_meta($comment->comment_ID, '_xi_points_given', 1);
        xi_change_points($comment->user_id, $points, __('发表评论', 'xi'));
    }
}

function xi_points_on_comment($comment_id, $approved)
{
    if ($approved === 1) {
        xi_points_give_comment($comment_id);
    }
}
add_action('comment_post', 'xi_points_on_comment', 10, 2);
add_action('comment_unapproved_to_approved', 'xi_points_give_comment');

/* ---------- 签到 ---------- */

function xi_checkin_status($user_id)
{
    $last   = get_user_meta($user_id, 'xi_checkin_date', true);
    $streak = (int) get_user_meta($user_id, 'xi_checkin_streak', true);
    $today  = current_time('Y-m-d');
    $yday   = wp_date('Y-m-d', current_time('timestamp', true) - DAY_IN_SECONDS);
    if ($last !== $today && $last !== $yday) {
        $streak = 0;
    }
    return array('done' => $last === $today, 'streak' => $streak);
}

/**
 * 本次签到可得积分：基础 + 连续签到加成（最多 7 天），会员按倍数
 */
function xi_checkin_points($user_id, $streak)
{
    $base   = (int) _xz('checkin_points', 5);
    $bonus  = (int) _xz('checkin_bonus', 1) * min(max($streak, 0), 6);
    $points = $base + $bonus;

    $level = function_exists('xi_get_vip_level') ? xi_get_vip_level($user_id) : 0;
    if ($level) {
        $vip    = xi_vip_level_config($level);
        $points = (int) round($points * max(1, (float) $vip['checkin_multi']));
    }
    return $points;
}

function xi_do_checkin($user_id)
{
    $status = xi_checkin_status($user_id);
    if ($status['done']) {
        return new WP_Error('done', __('今天已经签到过了', 'xi'));
    }
    $streak = $status['streak'] + 1;
    $points = xi_checkin_points($user_id, $status['streak']);

    update_user_meta($user_id, 'xi_checkin_date', current_time('Y-m-d'));
    update_user_meta($user_id, 'xi_checkin_streak', $streak);
    xi_change_points($user_id, $points, sprintf(__('每日签到（连续 %d 天）', 'xi'), $streak));

    return array('points' => $points, 'streak' => $streak);
}

/* ---------- 卡密 ---------- */

function xi_get_cards()
{
    $cards = get_option('xi_cards', array());
    return is_array($cards) ? $cards : array();
}

function xi_generate_cards($type, $amount, $count, $prefix = '')
{
    $cards  = xi_get_cards();
    $new    = array();
    $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $prefix));
    for ($i = 0; $i < $count; $i++) {
        do {
            $code = $prefix . strtoupper(wp_generate_password(16, false));
        } while (isset($cards[$code]));
        $cards[$code] = array(
            'type'    => $type,
            'amount'  => $amount,
            'created' => time(),
            'used_by' => 0,
            'used_at' => 0,
        );
        $new[] = $code;
    }
    update_option('xi_cards', $cards, false);
    return $new;
}

/**
 * 兑换卡密，同一用户每小时最多尝试 10 次
 */
function xi_redeem_card($user_id, $code)
{
    $key   = 'xi_redeem_try_' . $user_id;
    $tries = (int) get_transient($key);
    if ($tries >= 10) {
        return new WP_Error('frequent', __('尝试次数过多，请一小时后再试', 'xi'));
    }
    set_transient($key, $tries + 1, HOUR_IN_SECONDS);

    $code = strtoupper(preg_replace('/\s+/', '', (string) $code));
    if (!$code) {
        return new WP_Error('invalid', __('请输入卡密', 'xi'));
    }
    if (!xi_lock('card')) {
        return new WP_Error('busy', __('兑换人数较多，请稍后再试', 'xi'));
    }
    $result = xi_redeem_card_locked($user_id, $code);
    xi_unlock('card');
    return $result;
}

function xi_redeem_card_locked($user_id, $code)
{
    wp_cache_delete('xi_cards', 'options');
    $cards = xi_get_cards();
    if (!$code || !isset($cards[$code])) {
        return new WP_Error('invalid', __('卡密不存在', 'xi'));
    }
    if ($cards[$code]['used_by']) {
        return new WP_Error('used', __('该卡密已被使用', 'xi'));
    }

    $card = $cards[$code];
    if (($card['type'] === 'points' && !xi_points_enabled()) || ($card['type'] !== 'points' && !xi_balance_enabled())) {
        return new WP_Error('off', __('该类型卡密暂不可用', 'xi'));
    }

    $cards[$code]['used_by'] = $user_id;
    $cards[$code]['used_at'] = time();
    update_option('xi_cards', $cards, false);

    $note = sprintf(__('卡密充值 %s', 'xi'), substr($code, 0, 4) . '****' . substr($code, -4));
    if ($card['type'] === 'points') {
        xi_change_points($user_id, (int) $card['amount'], $note);
        return sprintf(__('成功充值 %1$d %2$s', 'xi'), (int) $card['amount'], xi_points_name());
    }
    xi_change_balance($user_id, (float) $card['amount'], $note);
    return sprintf(__('成功充值 %1$s %2$s', 'xi'), xi_format_balance($card['amount']), xi_balance_unit());
}
