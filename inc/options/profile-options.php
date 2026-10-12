<?php
/**
 * 后台用户编辑：积分、余额、会员状态
 * 管理员可修改，普通用户在个人资料页只读查看
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_profile_assets_fields($user)
{
    if (!xi_points_enabled() && !xi_balance_enabled() && !xi_vip_enabled()) {
        return;
    }
    $can_edit = current_user_can('manage_options');
    $level    = (int) get_user_meta($user->ID, 'xi_vip_level', true);
    $exp      = xi_get_vip_exp($user->ID);
    $readonly = $can_edit ? '' : ' readonly disabled';
    ?>
    <h2><?php esc_html_e('曦主题 · 会员与资产', 'xi'); ?></h2>
    <?php wp_nonce_field('xi_profile_assets', 'xi_profile_assets_nonce'); ?>
    <table class="form-table" role="presentation">
        <?php if (xi_vip_enabled()) : ?>
            <tr>
                <th><label for="xi_vip_level"><?php esc_html_e('会员等级', 'xi'); ?></label></th>
                <td>
                    <select name="xi_vip_level" id="xi_vip_level"<?php echo $readonly; ?>>
                        <option value="0"><?php esc_html_e('普通用户', 'xi'); ?></option>
                        <?php foreach (array(1, 2) as $lv) : ?>
                            <option value="<?php echo $lv; ?>" <?php selected($level, $lv); ?>><?php echo esc_html(xi_vip_level_config($lv)['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($level && !xi_get_vip_level($user->ID)) : ?>
                        <span class="description" style="color:#d13438"><?php esc_html_e('已过期', 'xi'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="xi_vip_exp"><?php esc_html_e('会员到期', 'xi'); ?></label></th>
                <td>
                    <input type="date" name="xi_vip_exp" id="xi_vip_exp" value="<?php echo $exp ? esc_attr(wp_date('Y-m-d', $exp)) : ''; ?>"<?php echo $readonly; ?>>
                    <label style="margin-left:10px"><input type="checkbox" name="xi_vip_forever" value="1" <?php checked($level && !$exp); ?><?php echo $readonly; ?>> <?php esc_html_e('永久', 'xi'); ?></label>
                    <p class="description"><?php esc_html_e('选择会员等级后需设置到期日期或勾选永久', 'xi'); ?></p>
                </td>
            </tr>
        <?php endif; ?>
        <?php if (xi_points_enabled()) : ?>
            <tr>
                <th><label for="xi_points"><?php echo esc_html(xi_points_name()); ?></label></th>
                <td><input type="number" min="0" step="1" name="xi_points" id="xi_points" value="<?php echo (int) xi_get_points($user->ID); ?>" class="regular-text"<?php echo $readonly; ?>></td>
            </tr>
        <?php endif; ?>
        <?php if (xi_balance_enabled()) : ?>
            <tr>
                <th><label for="xi_balance"><?php esc_html_e('余额', 'xi'); ?></label></th>
                <td><input type="number" min="0" step="0.01" name="xi_balance" id="xi_balance" value="<?php echo esc_attr(xi_format_balance(xi_get_balance($user->ID))); ?>" class="regular-text"<?php echo $readonly; ?>> <?php echo esc_html(xi_balance_unit()); ?></td>
            </tr>
        <?php endif; ?>
        <?php if ($can_edit && (xi_points_enabled() || xi_balance_enabled())) : ?>
            <tr>
                <th><label for="xi_assets_note"><?php esc_html_e('调整备注', 'xi'); ?></label></th>
                <td>
                    <input type="text" name="xi_assets_note" id="xi_assets_note" class="regular-text" placeholder="<?php esc_attr_e('管理员调整', 'xi'); ?>">
                    <p class="description"><?php esc_html_e('修改积分或余额时写入用户明细，用户可在用户中心看到', 'xi'); ?></p>
                </td>
            </tr>
        <?php endif; ?>
    </table>
    <?php
}
add_action('show_user_profile', 'xi_profile_assets_fields');
add_action('edit_user_profile', 'xi_profile_assets_fields');

function xi_profile_assets_save($user_id)
{
    if (!current_user_can('manage_options') || empty($_POST['xi_profile_assets_nonce']) || !wp_verify_nonce(sanitize_key($_POST['xi_profile_assets_nonce']), 'xi_profile_assets')) {
        return;
    }
    $note = !empty($_POST['xi_assets_note']) ? sanitize_text_field(wp_unslash($_POST['xi_assets_note'])) : __('管理员调整', 'xi');

    if (xi_points_enabled() && isset($_POST['xi_points'])) {
        $delta = max(0, (int) $_POST['xi_points']) - xi_get_points($user_id);
        if ($delta) {
            xi_change_points($user_id, $delta, $note);
        }
    }
    if (xi_balance_enabled() && isset($_POST['xi_balance'])) {
        $delta = round(max(0, (float) $_POST['xi_balance']) - xi_get_balance($user_id), 2);
        if ($delta) {
            xi_change_balance($user_id, $delta, $note);
        }
    }
    if (xi_vip_enabled() && isset($_POST['xi_vip_level'])) {
        $level = (int) $_POST['xi_vip_level'];
        if (!in_array($level, array(1, 2), true)) {
            xi_remove_vip($user_id);
        } else {
            $exp = 0;
            if (empty($_POST['xi_vip_forever'])) {
                $date = isset($_POST['xi_vip_exp']) ? sanitize_text_field(wp_unslash($_POST['xi_vip_exp'])) : '';
                $time = $date ? strtotime($date . ' 23:59:59 ' . wp_timezone_string()) : 0;
                $exp  = $time ? $time : time() + 30 * DAY_IN_SECONDS;
            }
            update_user_meta($user_id, 'xi_vip_level', $level);
            update_user_meta($user_id, 'xi_vip_exp', $exp);
        }
    }
}
add_action('personal_options_update', 'xi_profile_assets_save');
add_action('edit_user_profile_update', 'xi_profile_assets_save');

/* ---------- 认证身份 ---------- */

/**
 * 认证身份：显示在个人主页与用户卡片上，仅管理员可设置
 */
function xi_profile_identity_field($user)
{
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <h2><?php esc_html_e('曦主题 · 手机与认证', 'xi'); ?></h2>
    <?php wp_nonce_field('xi_profile_identity', 'xi_profile_identity_nonce'); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="xi_phone"><?php esc_html_e('绑定手机', 'xi'); ?></label></th>
            <td>
                <input type="text" name="xi_phone" id="xi_phone" class="regular-text" value="<?php echo esc_attr(get_user_meta($user->ID, 'xi_phone', true)); ?>">
                <p class="description"><?php esc_html_e('用户在前台账号设置中绑定，用于手机号登录和找回密码；留空即解绑', 'xi'); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="xi_identity"><?php esc_html_e('认证身份', 'xi'); ?></label></th>
            <td>
                <input type="text" name="xi_identity" id="xi_identity" class="regular-text" maxlength="30" value="<?php echo esc_attr(get_user_meta($user->ID, 'xi_identity', true)); ?>" placeholder="<?php esc_attr_e('例如：官方认证作者', 'xi'); ?>">
                <p class="description"><?php esc_html_e('留空则不显示。会在个人主页、搜索结果的用户卡片上显示为认证标识', 'xi'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'xi_profile_identity_field');
add_action('edit_user_profile', 'xi_profile_identity_field');

function xi_profile_identity_save($user_id)
{
    if (!current_user_can('manage_options') || empty($_POST['xi_profile_identity_nonce']) || !wp_verify_nonce(sanitize_key($_POST['xi_profile_identity_nonce']), 'xi_profile_identity')) {
        return;
    }
    $phone = isset($_POST['xi_phone']) ? sanitize_text_field(wp_unslash($_POST['xi_phone'])) : '';
    if ($phone === '') {
        delete_user_meta($user_id, 'xi_phone');
    } elseif (xi_is_phone($phone)) {
        $owner = xi_get_user_by_phone($phone);
        if (!$owner || $owner->ID === (int) $user_id) {
            update_user_meta($user_id, 'xi_phone', $phone);
        }
    }
    $identity = isset($_POST['xi_identity']) ? mb_substr(sanitize_text_field(wp_unslash($_POST['xi_identity'])), 0, 30) : '';
    if ($identity) {
        update_user_meta($user_id, 'xi_identity', $identity);
    } else {
        delete_user_meta($user_id, 'xi_identity');
    }
}
add_action('personal_options_update', 'xi_profile_identity_save');
add_action('edit_user_profile_update', 'xi_profile_identity_save');

/* ---------- 用户列表 ---------- */

function xi_users_columns($columns)
{
    if (xi_vip_enabled()) {
        $columns['xi_vip'] = __('会员', 'xi');
    }
    if (xi_points_enabled()) {
        $columns['xi_points'] = xi_points_name();
    }
    if (xi_balance_enabled()) {
        $columns['xi_balance'] = __('余额', 'xi');
    }
    return $columns;
}
add_filter('manage_users_columns', 'xi_users_columns');

function xi_users_column_value($value, $column, $user_id)
{
    switch ($column) {
        case 'xi_vip':
            $level = xi_get_vip_level($user_id);
            return $level ? esc_html(xi_vip_level_config($level)['name']) . '<br><small>' . esc_html(xi_vip_exp_text($user_id)) . '</small>' : '—';
        case 'xi_points':
            return (int) xi_get_points($user_id);
        case 'xi_balance':
            return esc_html(xi_format_balance(xi_get_balance($user_id)));
    }
    return $value;
}
add_filter('manage_users_custom_column', 'xi_users_column_value', 10, 3);

/* ---------- 卡密管理：用户 → 充值卡密 ---------- */

function xi_card_admin_menu()
{
    add_users_page(__('充值卡密', 'xi'), __('充值卡密', 'xi'), 'manage_options', 'xi_cards', 'xi_card_admin_page');
}
add_action('admin_menu', 'xi_card_admin_menu');

function xi_card_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $new = array();
    if (!empty($_POST['xi_card_action']) && check_admin_referer('xi_cards')) {
        if ($_POST['xi_card_action'] === 'generate') {
            $type   = isset($_POST['type']) && $_POST['type'] === 'points' ? 'points' : 'balance';
            $amount = $type === 'points' ? max(1, (int) $_POST['amount']) : max(0.01, round((float) $_POST['amount'], 2));
            $count  = min(500, max(1, (int) $_POST['count']));
            $new    = xi_generate_cards($type, $amount, $count, isset($_POST['prefix']) ? sanitize_text_field(wp_unslash($_POST['prefix'])) : '');
            echo '<div class="notice notice-success"><p>' . sprintf(esc_html__('已生成 %d 张卡密', 'xi'), count($new)) . '</p></div>';
        } elseif ($_POST['xi_card_action'] === 'clean') {
            $cards = array_filter(xi_get_cards(), function ($card) {
                return !$card['used_by'];
            });
            update_option('xi_cards', $cards, false);
            echo '<div class="notice notice-success"><p>' . esc_html__('已清理已使用的卡密', 'xi') . '</p></div>';
        } elseif ($_POST['xi_card_action'] === 'delete' && !empty($_POST['code'])) {
            $cards = xi_get_cards();
            unset($cards[sanitize_text_field(wp_unslash($_POST['code']))]);
            update_option('xi_cards', $cards, false);
        }
    }

    $cards  = array_reverse(xi_get_cards(), true);
    $unused = array_filter($cards, function ($card) {
        return !$card['used_by'];
    });
    $type_name = function ($card) {
        return $card['type'] === 'points' ? xi_points_name() : __('余额', 'xi');
    };
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('充值卡密', 'xi'); ?></h1>
        <p><?php esc_html_e('生成后将卡密发给用户（例如通过发卡平台出售），用户在「用户中心 → 余额」兑换。', 'xi'); ?></p>

        <form method="post" class="card" style="max-width:640px;padding:16px 20px">
            <?php wp_nonce_field('xi_cards'); ?>
            <input type="hidden" name="xi_card_action" value="generate">
            <h2 style="margin-top:0"><?php esc_html_e('生成卡密', 'xi'); ?></h2>
            <p>
                <label><?php esc_html_e('类型', 'xi'); ?>
                    <select name="type">
                        <option value="balance"><?php esc_html_e('余额', 'xi'); ?></option>
                        <option value="points"><?php echo esc_html(xi_points_name()); ?></option>
                    </select>
                </label>
                <label style="margin-left:12px"><?php esc_html_e('面值', 'xi'); ?> <input type="number" name="amount" min="0.01" step="0.01" value="10" style="width:90px"></label>
                <label style="margin-left:12px"><?php esc_html_e('数量', 'xi'); ?> <input type="number" name="count" min="1" max="500" value="10" style="width:80px"></label>
                <label style="margin-left:12px"><?php esc_html_e('前缀', 'xi'); ?> <input type="text" name="prefix" maxlength="6" style="width:80px"></label>
            </p>
            <?php submit_button(__('生成', 'xi'), 'primary', 'submit', false); ?>
        </form>

        <?php if ($new) : ?>
            <h2><?php esc_html_e('本次生成', 'xi'); ?></h2>
            <textarea readonly rows="8" style="width:100%;max-width:640px;font-family:monospace" onclick="this.select()"><?php echo esc_textarea(implode("\n", $new)); ?></textarea>
        <?php endif; ?>

        <h2><?php printf(esc_html__('全部卡密（未使用 %1$d / 共 %2$d）', 'xi'), count($unused), count($cards)); ?></h2>
        <?php if ($unused) : ?>
            <details style="margin-bottom:12px">
                <summary><?php esc_html_e('导出未使用的卡密', 'xi'); ?></summary>
                <textarea readonly rows="8" style="width:100%;max-width:640px;font-family:monospace;margin-top:8px" onclick="this.select()"><?php
                foreach ($unused as $code => $card) {
                    echo esc_textarea($code . "\t" . $type_name($card) . "\t" . $card['amount']) . "\n";
                }
                ?></textarea>
            </details>
        <?php endif; ?>

        <table class="widefat striped" style="max-width:960px">
            <thead><tr><th><?php esc_html_e('卡密', 'xi'); ?></th><th><?php esc_html_e('类型', 'xi'); ?></th><th><?php esc_html_e('面值', 'xi'); ?></th><th><?php esc_html_e('生成时间', 'xi'); ?></th><th><?php esc_html_e('状态', 'xi'); ?></th><th></th></tr></thead>
            <tbody>
            <?php if (!$cards) : ?>
                <tr><td colspan="6"><?php esc_html_e('暂无卡密', 'xi'); ?></td></tr>
            <?php endif; ?>
            <?php foreach (array_slice($cards, 0, 200, true) as $code => $card) :
                $used_user = $card['used_by'] ? get_userdata($card['used_by']) : null;
                ?>
                <tr>
                    <td><code><?php echo esc_html($code); ?></code></td>
                    <td><?php echo esc_html($type_name($card)); ?></td>
                    <td><?php echo esc_html($card['amount']); ?></td>
                    <td><?php echo esc_html(wp_date('Y-m-d H:i', $card['created'])); ?></td>
                    <td><?php echo $card['used_by'] ? esc_html(sprintf(__('%1$s 于 %2$s 使用', 'xi'), $used_user ? $used_user->display_name : '#' . $card['used_by'], wp_date('Y-m-d H:i', $card['used_at']))) : '<span style="color:#0f9d58">' . esc_html__('未使用', 'xi') . '</span>'; ?></td>
                    <td>
                        <?php if (!$card['used_by']) : ?>
                            <form method="post" onsubmit="return confirm('<?php echo esc_js(__('确定删除？', 'xi')); ?>')">
                                <?php wp_nonce_field('xi_cards'); ?>
                                <input type="hidden" name="xi_card_action" value="delete">
                                <input type="hidden" name="code" value="<?php echo esc_attr($code); ?>">
                                <button class="button-link" style="color:#d13438"><?php esc_html_e('删除', 'xi'); ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($cards) > 200) : ?>
            <p class="description"><?php esc_html_e('仅显示最近 200 张', 'xi'); ?></p>
        <?php endif; ?>

        <form method="post" style="margin-top:12px" onsubmit="return confirm('<?php echo esc_js(__('确定清理所有已使用的卡密记录？', 'xi')); ?>')">
            <?php wp_nonce_field('xi_cards'); ?>
            <input type="hidden" name="xi_card_action" value="clean">
            <?php submit_button(__('清理已使用的卡密', 'xi'), 'secondary', 'submit', false); ?>
        </form>
    </div>
    <?php
}
