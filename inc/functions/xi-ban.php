<?php
/**
 * 封禁：用户封号 / 禁言（参考子比主题小黑屋）与 IP 封禁
 *
 * 用户 Meta xi_ban：type（mute 禁言 / ban 封号）、reason、until（0 为永久）、by、time
 * 用户 Meta xi_ban_log：最近 20 条封禁 / 解封记录
 * 用户 Meta xi_last_ip / xi_reg_ip：最近登录 IP、注册 IP（封号时可一并封禁 IP）
 * 设置项：ip_ban_s、ip_ban_list、ip_ban_scope（all 整站 / action 仅登录注册与发布）、ip_ban_msg
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------- 用户封禁 ---------- */

function xi_ban_types()
{
    return array(
        'mute' => __('禁言', 'xi'),
        'ban'  => __('封号', 'xi'),
    );
}

/**
 * 当前封禁信息，未封禁或已到期返回 null（到期自动解除）
 */
function xi_get_ban($user_id)
{
    $user_id = (int) $user_id;
    if (!$user_id) {
        return null;
    }
    $ban = get_user_meta($user_id, 'xi_ban', true);
    if (!is_array($ban) || empty($ban['type']) || !isset(xi_ban_types()[$ban['type']])) {
        return null;
    }
    if (!empty($ban['until']) && (int) $ban['until'] <= time()) {
        delete_user_meta($user_id, 'xi_ban');
        return null;
    }
    return $ban;
}

/**
 * 是否处于封禁中：$type = ban 只看封号；mute 禁言（封号也算禁言）；留空任意
 */
function xi_is_banned($user_id, $type = '')
{
    $ban = xi_get_ban($user_id);
    if (!$ban) {
        return false;
    }
    return !$type || $type === 'mute' || $ban['type'] === $type;
}

/**
 * 管理员不能被封禁
 */
function xi_can_be_banned($user_id)
{
    return $user_id && !user_can($user_id, 'manage_options');
}

function xi_ban_until_text($ban)
{
    return empty($ban['until']) ? __('永久', 'xi') : wp_date('Y-m-d H:i', (int) $ban['until']);
}

/**
 * 给被封禁用户看的提示
 */
function xi_ban_message($ban)
{
    $text = $ban['type'] === 'ban' ? __('你的账号已被封禁', 'xi') : __('你已被禁言，暂时不能评论、发帖或投稿', 'xi');
    if (!empty($ban['reason'])) {
        $text .= '，' . sprintf(__('原因：%s', 'xi'), $ban['reason']);
    }
    $text .= '，' . (empty($ban['until']) ? __('永久有效', 'xi') : sprintf(__('解封时间：%s', 'xi'), xi_ban_until_text($ban)));
    return $text;
}

/**
 * 封禁用户
 *
 * @param int    $user_id
 * @param string $type   mute | ban
 * @param float  $days   0 为永久，支持小数（如 0.5 天）
 * @param string $reason
 */
function xi_set_ban($user_id, $type, $days = 0, $reason = '')
{
    if (!xi_can_be_banned($user_id) || !isset(xi_ban_types()[$type])) {
        return false;
    }
    $ban = array(
        'type'   => $type,
        'reason' => mb_substr(sanitize_text_field($reason), 0, 200),
        'until'  => $days > 0 ? time() + (int) round($days * DAY_IN_SECONDS) : 0,
        'by'     => get_current_user_id(),
        'time'   => time(),
    );
    update_user_meta($user_id, 'xi_ban', $ban);
    xi_ban_log($user_id, $ban);

    // 封号立即下线：清掉该用户所有登录会话
    if ($type === 'ban' && class_exists('WP_Session_Tokens')) {
        WP_Session_Tokens::get_instance($user_id)->destroy_all();
    }
    do_action('xi_user_banned', $user_id, $ban);
    return $ban;
}

function xi_remove_ban($user_id)
{
    if (!xi_get_ban($user_id)) {
        return false;
    }
    delete_user_meta($user_id, 'xi_ban');
    xi_ban_log($user_id, array('type' => 'remove', 'by' => get_current_user_id(), 'time' => time()));
    do_action('xi_user_unbanned', $user_id);
    return true;
}

function xi_ban_log($user_id, $entry)
{
    $log   = get_user_meta($user_id, 'xi_ban_log', true);
    $log   = is_array($log) ? $log : array();
    $log[] = $entry;
    update_user_meta($user_id, 'xi_ban_log', array_slice($log, -20));
}

/**
 * 封号用户禁止登录（前台登录、wp-login.php 都会经过这里）
 */
function xi_ban_authenticate($user)
{
    if (!($user instanceof WP_User) || user_can($user, 'manage_options')) {
        return $user;
    }
    if (($ban = xi_get_ban($user->ID)) && $ban['type'] === 'ban') {
        return new WP_Error('xi_banned', xi_ban_message($ban));
    }
    if (xi_ip_banned()) {
        return new WP_Error('xi_ip_banned', xi_ip_ban_message());
    }
    return $user;
}
add_filter('authenticate', 'xi_ban_authenticate', 100);

/**
 * 验证码登录、记住登录等已有会话：封号后下次访问即退出
 */
function xi_ban_logout_check()
{
    $user_id = get_current_user_id();
    if ($user_id && xi_is_banned($user_id, 'ban') && xi_can_be_banned($user_id)) {
        wp_logout();
    }
}
add_action('init', 'xi_ban_logout_check', 1);

/**
 * 禁言、封号或 IP 被封时收回发布类权限
 */
function xi_ban_user_can($can, $action, $user_id)
{
    if (!$can || !in_array($action, array('write', 'publish', 'edit_publish', 'edit_noreview', 'upload', 'comment', 'bbs_post', 'bbs_publish'), true)) {
        return $can;
    }
    if ($user_id && !xi_can_be_banned($user_id)) {
        return $can;
    }
    if ($user_id && xi_is_banned($user_id, 'mute')) {
        return false;
    }
    return !xi_ip_banned();
}
add_filter('xi_user_can', 'xi_ban_user_can', 10, 3);

/**
 * 评论提交兜底（直接 POST 到 wp-comments-post.php 时给出明确原因）
 */
function xi_ban_preprocess_comment($data)
{
    $user_id = get_current_user_id();
    if ($user_id && !xi_can_be_banned($user_id)) {
        return $data;
    }
    if ($user_id && ($ban = xi_get_ban($user_id))) {
        wp_die(esc_html(xi_ban_message($ban)), esc_html__('无法评论', 'xi'), array('response' => 403, 'back_link' => true));
    }
    if (xi_ip_banned()) {
        wp_die(esc_html(xi_ip_ban_message()), esc_html__('无法评论', 'xi'), array('response' => 403, 'back_link' => true));
    }
    return $data;
}
add_filter('preprocess_comment', 'xi_ban_preprocess_comment', 1);

/**
 * 当前用户被禁言时的提示（评论区、发帖页使用），未禁言返回空字符串
 */
function xi_ban_notice($user_id = 0)
{
    $user_id = $user_id ? $user_id : get_current_user_id();
    if ($user_id && !xi_can_be_banned($user_id)) {
        return '';
    }
    if ($user_id && ($ban = xi_get_ban($user_id))) {
        return xi_ban_message($ban);
    }
    return xi_ip_banned() ? xi_ip_ban_message() : '';
}

/* ---------- 记录登录 / 注册 IP ---------- */

function xi_ban_record_login_ip($login, $user)
{
    if ($ip = xi_client_ip()) {
        update_user_meta($user->ID, 'xi_last_ip', $ip);
        update_user_meta($user->ID, 'xi_last_login', time());
    }
}
add_action('wp_login', 'xi_ban_record_login_ip', 10, 2);

function xi_ban_record_reg_ip($user_id)
{
    if ($ip = xi_client_ip()) {
        update_user_meta($user_id, 'xi_reg_ip', $ip);
        update_user_meta($user_id, 'xi_last_ip', $ip);
    }
}
add_action('user_register', 'xi_ban_record_reg_ip');

/**
 * 用户最近使用的 IP：最近登录 > 注册
 */
function xi_user_last_ip($user_id)
{
    $ip = get_user_meta($user_id, 'xi_last_ip', true);
    return $ip ? $ip : (string) get_user_meta($user_id, 'xi_reg_ip', true);
}

/* ---------- 前台一键封禁 ---------- */

function xi_ban_can_manage()
{
    return current_user_can('edit_users');
}

function xi_ajax_ban_user()
{
    if (!check_ajax_referer('xi_ban', 'nonce', false)) {
        wp_send_json(array('ok' => false, 'msg' => __('页面已过期，请刷新后重试', 'xi')));
    }
    if (!xi_ban_can_manage()) {
        wp_send_json(array('ok' => false, 'msg' => __('没有权限', 'xi')));
    }
    $user_id = isset($_POST['user']) ? (int) $_POST['user'] : 0;
    $user    = $user_id ? get_userdata($user_id) : false;
    if (!$user) {
        wp_send_json(array('ok' => false, 'msg' => __('用户不存在', 'xi')));
    }
    if ($user_id === get_current_user_id() || !xi_can_be_banned($user_id)) {
        wp_send_json(array('ok' => false, 'msg' => __('不能封禁管理员或自己', 'xi')));
    }

    $do = isset($_POST['do']) ? sanitize_key($_POST['do']) : 'set';
    if ($do === 'remove') {
        xi_remove_ban($user_id);
        wp_send_json(array('ok' => true, 'msg' => __('已解除封禁', 'xi'), 'reload' => true));
    }

    $type   = isset($_POST['type']) ? sanitize_key($_POST['type']) : 'ban';
    $days   = isset($_POST['days']) ? max(0, min(36500, (float) $_POST['days'])) : 0;
    $reason = isset($_POST['reason']) ? wp_unslash($_POST['reason']) : '';
    if (!xi_set_ban($user_id, $type, $days, $reason)) {
        wp_send_json(array('ok' => false, 'msg' => __('封禁失败', 'xi')));
    }

    $msg = $type === 'ban' ? __('已封号，该用户已被强制下线', 'xi') : __('已禁言', 'xi');
    if (!empty($_POST['ban_ip']) && ($ip = xi_user_last_ip($user_id))) {
        xi_ip_ban_add($ip, sprintf(__('%1$s 于 %2$s 封禁', 'xi'), $user->user_login, wp_date('Y-m-d')));
        $msg .= sprintf(__('，IP %s 已加入黑名单', 'xi'), $ip);
    }
    wp_send_json(array('ok' => true, 'msg' => $msg, 'reload' => true));
}
add_action('wp_ajax_xi_ban_user', 'xi_ajax_ban_user');

/**
 * 个人主页的封禁按钮
 */
function xi_ban_button($user)
{
    if (!xi_ban_can_manage() || (int) $user->ID === get_current_user_id() || !xi_can_be_banned($user->ID)) {
        return '';
    }
    $banned = xi_get_ban($user->ID);
    return '<button type="button" class="xi-btn xi-btn-danger' . ($banned ? ' is-banned' : '') . '" data-xi-modal="xi-ban">' . xi_icon('ban') . ($banned ? esc_html__('管理封禁', 'xi') : esc_html__('封禁', 'xi')) . '</button>';
}

/**
 * 个人主页的封禁弹窗
 */
function xi_ban_modal($user)
{
    if (!xi_ban_button($user)) {
        return;
    }
    $ban = xi_get_ban($user->ID);
    $ip  = xi_user_last_ip($user->ID);
    $durations = array(
        '1'  => __('1 天', 'xi'),
        '3'  => __('3 天', 'xi'),
        '7'  => __('7 天', 'xi'),
        '30' => __('30 天', 'xi'),
        '0'  => __('永久', 'xi'),
    );
    ?>
    <div class="xi-modal" id="xi-ban" aria-hidden="true">
        <div class="xi-modal-mask" data-modal-close></div>
        <form class="xi-modal-box xi-glass xi-ban-box" role="dialog" aria-modal="true" aria-labelledby="xi-ban-title" data-xi-ban>
            <button type="button" class="xi-btn-icon xi-modal-close" data-modal-close aria-label="<?php esc_attr_e('关闭', 'xi'); ?>"><?php echo xi_icon('close'); ?></button>
            <h3 class="xi-modal-title" id="xi-ban-title"><?php printf(esc_html__('封禁 %s', 'xi'), esc_html($user->display_name)); ?></h3>

            <?php if ($ban) : ?>
                <div class="xi-ban-current">
                    <?php echo xi_icon('ban'); ?>
                    <div>
                        <b><?php printf(esc_html__('当前：%1$s，至 %2$s', 'xi'), esc_html(xi_ban_types()[$ban['type']]), esc_html(xi_ban_until_text($ban))); ?></b>
                        <?php if (!empty($ban['reason'])) : ?><small><?php printf(esc_html__('原因：%s', 'xi'), esc_html($ban['reason'])); ?></small><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <input type="hidden" name="user" value="<?php echo (int) $user->ID; ?>">

            <div class="xi-ban-label"><?php esc_html_e('封禁方式', 'xi'); ?></div>
            <div class="xi-ban-types">
                <label><input type="radio" name="type" value="mute"<?php checked(!$ban || $ban['type'] === 'mute'); ?>><span><b><?php esc_html_e('禁言', 'xi'); ?></b><small><?php esc_html_e('可登录浏览，不能评论、发帖、投稿', 'xi'); ?></small></span></label>
                <label><input type="radio" name="type" value="ban"<?php checked($ban && $ban['type'] === 'ban'); ?>><span><b><?php esc_html_e('封号', 'xi'); ?></b><small><?php esc_html_e('无法登录，立即强制下线', 'xi'); ?></small></span></label>
            </div>

            <div class="xi-ban-label"><?php esc_html_e('封禁时长', 'xi'); ?></div>
            <div class="xi-ban-days">
                <?php foreach ($durations as $days => $label) : ?>
                    <label><input type="radio" name="days" value="<?php echo esc_attr($days); ?>"<?php checked($days, '7'); ?>><span><?php echo esc_html($label); ?></span></label>
                <?php endforeach; ?>
                <label class="xi-ban-custom"><input type="radio" name="days" value="custom"><span><input type="number" name="custom_days" min="0.1" max="36500" step="0.1" placeholder="<?php esc_attr_e('自定义', 'xi'); ?>" aria-label="<?php esc_attr_e('自定义天数', 'xi'); ?>"><?php esc_html_e('天', 'xi'); ?></span></label>
            </div>

            <label class="xi-ban-label" for="xi-ban-reason"><?php esc_html_e('封禁原因', 'xi'); ?></label>
            <div class="xi-field-box">
                <input type="text" id="xi-ban-reason" name="reason" maxlength="200" placeholder="<?php esc_attr_e('会展示给该用户，可留空', 'xi'); ?>" value="<?php echo $ban && !empty($ban['reason']) ? esc_attr($ban['reason']) : ''; ?>">
            </div>

            <?php if ($ip) : ?>
                <label class="xi-ban-ip">
                    <input type="checkbox" name="ban_ip" value="1">
                    <span><?php printf(esc_html__('同时封禁 TA 最近使用的 IP：%s', 'xi'), '<code>' . esc_html($ip) . '</code>'); ?></span>
                </label>
            <?php endif; ?>

            <div class="xi-ban-actions">
                <?php if ($ban) : ?>
                    <button type="button" class="xi-btn" data-xi-unban><?php echo xi_icon('undo'); ?><?php esc_html_e('解除封禁', 'xi'); ?></button>
                <?php endif; ?>
                <button type="submit" class="xi-btn xi-btn-danger is-solid"><?php echo xi_icon('ban'); ?><?php echo $ban ? esc_html__('更新封禁', 'xi') : esc_html__('确认封禁', 'xi'); ?></button>
            </div>
        </form>
    </div>
    <?php
}

function xi_ban_enqueue()
{
    if (!is_author() || !xi_ban_can_manage()) {
        return;
    }
    wp_enqueue_script('xi-ban', get_template_directory_uri() . '/assets/js/ban.js', array('xi-main', 'xi-interact'), XI_VERSION, true);
    wp_localize_script('xi-ban', 'xiBan', array(
        'ajax'    => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('xi_ban'),
        'confirm' => __('确定解除对该用户的封禁吗？', 'xi'),
    ));
}
add_action('wp_enqueue_scripts', 'xi_ban_enqueue');

/* ---------- 后台用户列表 ---------- */

function xi_ban_users_column($columns)
{
    $columns['xi_ban'] = __('封禁状态', 'xi');
    return $columns;
}
add_filter('manage_users_columns', 'xi_ban_users_column');

function xi_ban_users_column_value($value, $column, $user_id)
{
    if ($column !== 'xi_ban') {
        return $value;
    }
    $ban = xi_get_ban($user_id);
    $ip  = xi_user_last_ip($user_id);
    $html = $ban
        ? '<span style="color:#d63638;font-weight:600">' . esc_html(xi_ban_types()[$ban['type']]) . '</span><br><small>' . esc_html(sprintf(__('至 %s', 'xi'), xi_ban_until_text($ban))) . '</small>'
        : '<span style="color:#8c8f94">' . esc_html__('正常', 'xi') . '</span>';
    if ($ip) {
        $html .= '<br><small title="' . esc_attr__('最近使用的 IP', 'xi') . '">' . esc_html($ip) . (xi_ip_banned($ip) ? ' <b style="color:#d63638">' . esc_html__('已封', 'xi') . '</b>' : '') . '</small>';
    }
    return $html;
}
add_filter('manage_users_custom_column', 'xi_ban_users_column_value', 10, 3);

/* ---------- IP 封禁 ---------- */

/**
 * 黑名单规则：每行一条，支持单个 IP、通配符（1.2.3.*）、CIDR（1.2.3.0/24、IPv6 同理），# 后为备注
 */
function xi_ip_ban_rules()
{
    static $rules = null;
    if ($rules !== null) {
        return $rules;
    }
    $rules = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) _xz('ip_ban_list', '')) as $line) {
        $line = trim(preg_replace('/#.*$/', '', $line));
        if ($line !== '') {
            $rules[] = strtolower($line);
        }
    }
    return $rules;
}

function xi_ip_match($ip, $rule)
{
    if ($ip === $rule) {
        return true;
    }
    if (strpos($rule, '*') !== false) {
        $regex = '/^' . str_replace('\*', '[0-9a-f]*', preg_quote($rule, '/')) . '$/i';
        return (bool) preg_match($regex, $ip);
    }
    if (strpos($rule, '/') !== false) {
        list($subnet, $bits) = explode('/', $rule, 2);
        $ip_bin  = @inet_pton($ip);
        $net_bin = @inet_pton($subnet);
        if ($ip_bin === false || $net_bin === false || strlen($ip_bin) !== strlen($net_bin) || !is_numeric($bits)) {
            return false;
        }
        $bits = max(0, min(strlen($ip_bin) * 8, (int) $bits));
        $bytes = intdiv($bits, 8);
        if ($bytes && substr($ip_bin, 0, $bytes) !== substr($net_bin, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if (!$rest) {
            return true;
        }
        $mask = chr((0xff << (8 - $rest)) & 0xff);
        return ($ip_bin[$bytes] & $mask) === ($net_bin[$bytes] & $mask);
    }
    return false;
}

/**
 * IP 是否在黑名单中（默认当前访客）
 */
function xi_ip_banned($ip = null)
{
    // 安全模式下不拦截，避免管理员误封自己后无法进入
    if (!_xz('ip_ban_s', false) || ($ip === null && function_exists('xi_safe_active') && xi_safe_active())) {
        return false;
    }
    $ip = strtolower($ip === null ? xi_client_ip() : (string) $ip);
    if ($ip === '') {
        return false;
    }
    static $cache = array();
    if (!isset($cache[$ip])) {
        $cache[$ip] = false;
        foreach (xi_ip_ban_rules() as $rule) {
            if (xi_ip_match($ip, $rule)) {
                $cache[$ip] = true;
                break;
            }
        }
    }
    return $cache[$ip];
}

function xi_ip_ban_message()
{
    $msg = trim((string) _xz('ip_ban_msg', ''));
    return $msg !== '' ? $msg : __('你的 IP 已被本站限制访问，如有疑问请联系站长', 'xi');
}

/**
 * 加入黑名单并开启 IP 封禁
 */
function xi_ip_ban_add($ip, $note = '')
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    $list = trim((string) _xz('ip_ban_list', ''));
    foreach (xi_ip_ban_rules() as $rule) {
        if ($rule === strtolower($ip)) {
            return true;
        }
    }
    _sxz('ip_ban_list', ($list !== '' ? $list . "\n" : '') . $ip . ($note !== '' ? ' # ' . str_replace(array("\r", "\n"), ' ', $note) : ''));
    if (!_xz('ip_ban_s', false)) {
        _sxz('ip_ban_s', true);
    }
    return true;
}

/**
 * 整站模式：黑名单 IP 访问任何页面都显示拦截页
 * 已登录的管理员与 wp-login.php 放行，避免误封自己后无法登录后台解除
 */
function xi_ip_ban_block()
{
    if (_xz('ip_ban_scope', 'action') !== 'all' || !xi_ip_banned() || current_user_can('manage_options')) {
        return;
    }
    global $pagenow;
    if ($pagenow === 'wp-login.php' || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) {
        return;
    }
    if (wp_doing_ajax()) {
        wp_send_json(array('ok' => false, 'msg' => xi_ip_ban_message()), 403);
    }
    status_header(403);
    nocache_headers();
    wp_die(
        '<h1 style="font-size:22px;margin:0 0 12px">' . esc_html__('访问受限', 'xi') . '</h1><p>' . esc_html(xi_ip_ban_message()) . '</p><p style="color:#8c8f94;font-size:13px">IP：' . esc_html(xi_client_ip()) . '</p>',
        esc_html__('访问受限', 'xi'),
        array('response' => 403)
    );
}
add_action('init', 'xi_ip_ban_block', 2);

/**
 * wp-login.php 的注册入口
 */
function xi_ip_ban_registration_errors($errors)
{
    if (xi_ip_banned()) {
        $errors->add('xi_ip_banned', xi_ip_ban_message());
    }
    return $errors;
}
add_filter('registration_errors', 'xi_ip_ban_registration_errors');
