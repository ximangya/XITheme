<?php
/**
 * 用户中心
 */

get_header();

$user = wp_get_current_user();
$tabs = array('overview' => array(__('概览', 'xi'), 'grid'));
if (xi_write_enabled()) {
    $tabs['posts'] = array(__('我的文章', 'xi'), 'book');
}
if (_xz('fav_s', true)) {
    $tabs['favs'] = array(__('我的收藏', 'xi'), 'star');
}
if (xi_vip_enabled() && xi_vip_levels()) {
    $tabs['vip'] = array(__('会员', 'xi'), 'crown');
}
if (xi_points_enabled()) {
    $tabs['points'] = array(xi_points_name(), 'coin');
}
if (xi_balance_enabled()) {
    $tabs['balance'] = array(__('余额', 'xi'), 'wallet');
}
if (xi_pay_enabled() || xi_user_orders($user->ID, 1)) {
    $tabs['orders'] = array(__('我的订单', 'xi'), 'list');
}
$tabs = apply_filters('xi_uc_tabs', $tabs, $user);
$tabs['settings'] = array(__('账号设置', 'xi'), 'gear');
$active = isset($_GET['tab']) && isset($tabs[$_GET['tab']]) ? sanitize_key($_GET['tab']) : 'overview';
?>

<main class="xi-main xi-uc">
    <?php if (!$user->exists()) : ?>
        <div class="xi-card xi-uc-guest">
            <span class="xi-user-guest is-lg"><?php echo xi_icon('user'); ?></span>
            <h1><?php esc_html_e('登录后进入用户中心', 'xi'); ?></h1>
            <p><?php esc_html_e('查看会员状态、积分与余额，每日签到领取奖励', 'xi'); ?></p>
            <div class="xi-uc-actions"><?php echo xi_login_buttons(); ?></div>
        </div>
    <?php else :
        $level  = xi_get_vip_level($user->ID);
        $stats  = xi_user_stats($user->ID);
        $points = xi_get_points($user->ID);
        $money  = xi_get_balance($user->ID);
        ?>
        <div class="xi-uc-layout">
            <aside class="xi-uc-side">
                <div class="xi-card xi-uc-profile">
                    <?php echo get_avatar($user->ID, 72); ?>
                    <b><a href="<?php echo esc_url(get_author_posts_url($user->ID)); ?>"><?php echo esc_html($user->display_name); ?></a></b>
                    <?php echo xi_vip_badge($user->ID); ?>
                    <small><?php echo esc_html(xi_user_role_name($user)); ?> · <?php printf(esc_html__('加入 %d 天', 'xi'), $stats['days']); ?></small>
                </div>
                <nav class="xi-card xi-uc-nav" role="tablist">
                    <?php foreach ($tabs as $key => $tab) : ?>
                        <a href="<?php echo esc_url(xi_uc_url($key)); ?>" data-uc-tab="<?php echo esc_attr($key); ?>" class="<?php echo $key === $active ? 'is-active' : ''; ?>" role="tab"><?php echo xi_icon($tab[1]); ?><span><?php echo esc_html($tab[0]); ?></span></a>
                    <?php endforeach; ?>
                </nav>
            </aside>

            <div class="xi-uc-body">
                <div class="xi-uc-msg" role="alert"></div>

                <!-- 概览 -->
                <section class="xi-uc-panel<?php echo $active === 'overview' ? ' is-active' : ''; ?>" data-uc-panel="overview">
                    <div class="xi-uc-cards">
                        <?php if (xi_vip_enabled() && xi_vip_levels()) : ?>
                            <a class="xi-card xi-uc-stat" href="<?php echo esc_url(xi_uc_url('vip')); ?>" data-uc-tab="vip">
                                <span class="xi-uc-stat-icon is-vip"><?php echo xi_icon('crown'); ?></span>
                                <span><small><?php esc_html_e('会员', 'xi'); ?></small>
                                <b><?php echo $level ? esc_html(xi_vip_level_config($level)['name']) : esc_html__('未开通', 'xi'); ?></b>
                                <?php if ($level) : ?><em><?php echo esc_html(xi_vip_exp_text($user->ID)); ?></em><?php endif; ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if (xi_points_enabled()) : ?>
                            <a class="xi-card xi-uc-stat" href="<?php echo esc_url(xi_uc_url('points')); ?>" data-uc-tab="points">
                                <span class="xi-uc-stat-icon"><?php echo xi_icon('coin'); ?></span>
                                <span><small><?php echo esc_html(xi_points_name()); ?></small><b><?php echo (int) $points; ?></b></span>
                            </a>
                        <?php endif; ?>
                        <?php if (xi_balance_enabled()) : ?>
                            <a class="xi-card xi-uc-stat" href="<?php echo esc_url(xi_uc_url('balance')); ?>" data-uc-tab="balance">
                                <span class="xi-uc-stat-icon"><?php echo xi_icon('wallet'); ?></span>
                                <span><small><?php esc_html_e('余额', 'xi'); ?></small><b><?php echo esc_html(xi_format_balance($money)); ?></b><em><?php echo esc_html(xi_balance_unit()); ?></em></span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (xi_points_enabled() && xi_user_can('checkin', $user->ID)) :
                        $checkin = xi_checkin_status($user->ID);
                        ?>
                        <div class="xi-card xi-uc-checkin">
                            <span class="xi-uc-stat-icon"><?php echo xi_icon('calendar'); ?></span>
                            <div>
                                <b><?php esc_html_e('每日签到', 'xi'); ?></b>
                                <small>
                                    <?php
                                    if ($checkin['done']) {
                                        printf(esc_html__('今日已签到，已连续签到 %d 天', 'xi'), $checkin['streak']);
                                    } else {
                                        printf(esc_html__('今日签到可得 %1$d %2$s，连续签到奖励更多', 'xi'), xi_checkin_points($user->ID, $checkin['streak']), esc_html(xi_points_name()));
                                    }
                                    ?>
                                </small>
                            </div>
                            <button type="button" class="xi-btn xi-btn-accent" data-xi-checkin <?php disabled($checkin['done']); ?>><?php echo $checkin['done'] ? esc_html__('已签到', 'xi') : esc_html__('签到', 'xi'); ?></button>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- 我的文章 -->
                <?php if (isset($tabs['posts'])) :
                    $paged    = isset($_GET['pg']) ? max(1, (int) $_GET['pg']) : 1;
                    $my_posts = new WP_Query(array(
                        'author'         => $user->ID,
                        'post_type'      => 'post',
                        'post_status'    => array('publish', 'pending', 'draft', 'future', 'private'),
                        'posts_per_page' => 10,
                        'paged'          => $paged,
                    ));
                    $status_names = array(
                        'publish' => __('已发布', 'xi'),
                        'pending' => __('审核中', 'xi'),
                        'draft'   => __('草稿', 'xi'),
                        'future'  => __('定时', 'xi'),
                        'private' => __('私密', 'xi'),
                    );
                    ?>
                    <section class="xi-uc-panel<?php echo $active === 'posts' ? ' is-active' : ''; ?>" data-uc-panel="posts">
                        <div class="xi-card xi-uc-box">
                            <div class="xi-uc-box-head">
                                <h3 class="xi-section-title"><?php printf(esc_html__('我的文章（%d）', 'xi'), (int) $my_posts->found_posts); ?></h3>
                                <?php if (xi_user_can('write', $user->ID)) : ?>
                                    <a class="xi-btn xi-btn-accent xi-btn-sm" href="<?php echo esc_url(xi_write_url()); ?>"><?php echo xi_icon('edit'); ?><?php esc_html_e('写文章', 'xi'); ?></a>
                                <?php endif; ?>
                            </div>
                            <?php if (!$my_posts->have_posts()) : ?>
                                <p class="xi-uc-empty"><?php esc_html_e('还没有文章，写下第一篇吧', 'xi'); ?></p>
                            <?php else : ?>
                                <ul class="xi-uc-posts">
                                    <?php while ($my_posts->have_posts()) :
                                        $my_posts->the_post();
                                        $st = get_post_status();
                                        ?>
                                        <li>
                                            <span class="xi-uc-post-status is-<?php echo esc_attr($st); ?>"><?php echo esc_html(isset($status_names[$st]) ? $status_names[$st] : $st); ?></span>
                                            <span class="xi-uc-post-main">
                                                <b><?php echo esc_html(get_the_title() ? get_the_title() : __('（无标题）', 'xi')); ?></b>
                                                <small><?php echo esc_html(get_the_modified_date('Y-m-d H:i')); ?></small>
                                            </span>
                                            <span class="xi-uc-post-actions">
                                                <?php if ($st === 'publish') : ?>
                                                    <a href="<?php the_permalink(); ?>" title="<?php esc_attr_e('查看', 'xi'); ?>"><?php echo xi_icon('arrow'); ?></a>
                                                <?php endif; ?>
                                                <?php if (!is_wp_error(xi_write_can_edit(get_the_ID(), $user->ID))) : ?>
                                                    <a href="<?php echo esc_url(xi_write_url(get_the_ID())); ?>" title="<?php esc_attr_e('编辑', 'xi'); ?>"><?php echo xi_icon('edit'); ?></a>
                                                <?php endif; ?>
                                            </span>
                                        </li>
                                    <?php endwhile;
                                    wp_reset_postdata(); ?>
                                </ul>
                                <?php if ($my_posts->max_num_pages > 1) : ?>
                                    <div class="nav-links xi-uc-pager">
                                        <?php for ($i = 1; $i <= $my_posts->max_num_pages; $i++) : ?>
                                            <a class="page-numbers<?php echo $i === $paged ? ' current' : ''; ?>" href="<?php echo esc_url(add_query_arg('pg', $i, xi_uc_url('posts'))); ?>"><?php echo (int) $i; ?></a>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- 我的收藏 -->
                <?php if (isset($tabs['favs'])) :
                    $fpage   = isset($_GET['fp']) ? max(1, (int) $_GET['fp']) : 1;
                    $fav_q   = xi_favorites_query($user->ID, $fpage);
                    ?>
                    <section class="xi-uc-panel<?php echo $active === 'favs' ? ' is-active' : ''; ?>" data-uc-panel="favs">
                        <div class="xi-card xi-uc-box">
                            <h3 class="xi-section-title"><?php printf(esc_html__('我的收藏（%d）', 'xi'), count(xi_get_favorites($user->ID))); ?></h3>
                            <?php if (!$fav_q || !$fav_q->have_posts()) : ?>
                                <p class="xi-uc-empty"><?php esc_html_e('还没有收藏文章，看到喜欢的文章点一下「收藏」吧', 'xi'); ?></p>
                            <?php else : ?>
                                <ul class="xi-uc-posts">
                                    <?php while ($fav_q->have_posts()) :
                                        $fav_q->the_post();
                                        ?>
                                        <li>
                                            <span class="xi-uc-post-main">
                                                <b><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></b>
                                                <small><?php echo esc_html(get_the_author()); ?> · <?php echo esc_html(get_the_date('Y-m-d')); ?></small>
                                            </span>
                                            <span class="xi-uc-post-actions">
                                                <button type="button" class="is-active" data-xi-fav="<?php the_ID(); ?>" data-remove title="<?php esc_attr_e('取消收藏', 'xi'); ?>"><?php echo xi_icon('star'); ?></button>
                                            </span>
                                        </li>
                                    <?php endwhile;
                                    wp_reset_postdata(); ?>
                                </ul>
                                <?php if ($fav_q->max_num_pages > 1) : ?>
                                    <div class="nav-links xi-uc-pager">
                                        <?php for ($i = 1; $i <= $fav_q->max_num_pages; $i++) : ?>
                                            <a class="page-numbers<?php echo $i === $fpage ? ' current' : ''; ?>" href="<?php echo esc_url(add_query_arg('fp', $i, xi_uc_url('favs'))); ?>"><?php echo (int) $i; ?></a>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- 会员 -->
                <?php if (isset($tabs['vip'])) : ?>
                    <section class="xi-uc-panel<?php echo $active === 'vip' ? ' is-active' : ''; ?>" data-uc-panel="vip">
                        <div class="xi-card xi-uc-vip-now">
                            <?php if ($level) : ?>
                                <?php echo xi_vip_badge($user->ID); ?>
                                <span><?php echo esc_html(xi_vip_exp_text($user->ID)); ?></span>
                            <?php else : ?>
                                <span><?php esc_html_e('你还不是会员，开通后享受专属权益', 'xi'); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php $can_buy = xi_user_can('vip_buy', $user->ID); ?>
                        <?php if (!$can_buy) : ?>
                            <div class="xi-card xi-uc-notice"><?php esc_html_e('你所在的用户组暂不支持开通会员，如有需要请联系管理员', 'xi'); ?></div>
                        <?php endif; ?>
                        <div class="xi-uc-vips">
                            <?php foreach (xi_vip_levels() as $lv => $config) :
                                $color = sanitize_hex_color($config['color']);
                                ?>
                                <div class="xi-card xi-vip-card"<?php echo $color ? ' style="--vip:' . esc_attr($color) . '"' : ''; ?>>
                                    <div class="xi-vip-card-head"><?php echo xi_icon('crown'); ?><b><?php echo esc_html($config['name']); ?></b></div>
                                    <ul class="xi-vip-benefits">
                                        <?php foreach (array_filter(array_map('trim', explode("\n", (string) $config['benefits']))) as $benefit) : ?>
                                            <li><?php echo xi_icon('check') . esc_html($benefit); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="xi-vip-plans">
                                        <?php foreach ($config['plans'] as $i => $plan) :
                                            $price  = (float) $plan['price'];
                                            $ppoint = (int) $plan['points'];
                                            ?>
                                            <div class="xi-vip-plan">
                                                <div class="xi-vip-plan-info">
                                                    <b><?php echo esc_html($plan['name']); ?></b>
                                                    <small><?php echo (int) $plan['days'] ? sprintf(esc_html__('%d 天', 'xi'), (int) $plan['days']) : esc_html__('永久', 'xi'); ?></small>
                                                </div>
                                                <div class="xi-vip-plan-buy">
                                                    <?php if (!$can_buy) : ?>
                                                        <?php if ($price > 0 && xi_balance_enabled()) : ?><span class="xi-vip-plan-price"><?php echo esc_html(xi_format_balance($price) . ' ' . xi_balance_unit()); ?></span><?php endif; ?>
                                                        <?php if ($ppoint > 0 && xi_points_enabled()) : ?><span class="xi-vip-plan-price"><?php echo esc_html($ppoint . ' ' . xi_points_name()); ?></span><?php endif; ?>
                                                    <?php else : ?>
                                                    <?php if (xi_balance_enabled() && $price > 0) : ?>
                                                        <button type="button" class="xi-btn xi-btn-sm xi-btn-accent" data-xi-vip="<?php echo (int) $lv; ?>" data-plan="<?php echo (int) $i; ?>" data-pay="balance"><?php echo esc_html(xi_format_balance($price) . ' ' . xi_balance_unit()); ?></button>
                                                    <?php endif; ?>
                                                    <?php if (xi_points_enabled() && $ppoint > 0) : ?>
                                                        <button type="button" class="xi-btn xi-btn-sm" data-xi-vip="<?php echo (int) $lv; ?>" data-plan="<?php echo (int) $i; ?>" data-pay="points"><?php echo esc_html($ppoint . ' ' . xi_points_name()); ?></button>
                                                    <?php endif; ?>
                                                    <?php if (xi_pay_enabled() && _xz('pay_vip_s', true) && $price > 0) : ?>
                                                        <button type="button" class="xi-btn xi-btn-sm xi-btn-accent" data-xi-pay="vip" data-level="<?php echo (int) $lv; ?>" data-plan="<?php echo (int) $i; ?>" data-amount="<?php echo esc_attr(xi_money($price)); ?>" data-title="<?php echo esc_attr($config['name'] . ' · ' . $plan['name']); ?>"><?php echo esc_html('¥' . xi_money($price) . ' ' . __('在线付', 'xi')); ?></button>
                                                    <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- 积分 -->
                <?php if (isset($tabs['points'])) : ?>
                    <section class="xi-uc-panel<?php echo $active === 'points' ? ' is-active' : ''; ?>" data-uc-panel="points">
                        <div class="xi-card xi-uc-box">
                            <h3 class="xi-section-title"><?php printf(esc_html__('获取%s', 'xi'), esc_html(xi_points_name())); ?></h3>
                            <ul class="xi-uc-rules">
                                <li><?php echo xi_icon('calendar'); ?><?php printf(esc_html__('每日签到 +%1$d，连续签到每天额外 +%2$d（最多 7 天）', 'xi'), (int) _xz('checkin_points', 5), (int) _xz('checkin_bonus', 1)); ?></li>
                                <li><?php echo xi_icon('gift'); ?><?php printf(esc_html__('注册账号 +%d', 'xi'), (int) _xz('points_register', 20)); ?></li>
                                <li><?php echo xi_icon('edit'); ?><?php printf(esc_html__('发布文章 +%1$d，每日最多 %2$d 次', 'xi'), (int) _xz('points_post', 10), (int) _xz('points_post_limit', 3)); ?></li>
                                <li><?php echo xi_icon('comment'); ?><?php printf(esc_html__('发表评论 +%1$d，每日最多 %2$d 次', 'xi'), (int) _xz('points_comment', 2), (int) _xz('points_comment_limit', 10)); ?></li>
                            </ul>
                        </div>
                        <?php if (xi_pay_enabled() && _xz('pay_points_s', false)) {
                            xi_uc_pay_card('points');
                        } ?>
                        <?php xi_uc_logs($user->ID, 'points'); ?>
                    </section>
                <?php endif; ?>

                <!-- 余额 -->
                <?php if (isset($tabs['balance'])) : ?>
                    <section class="xi-uc-panel<?php echo $active === 'balance' ? ' is-active' : ''; ?>" data-uc-panel="balance">
                        <?php if (xi_pay_enabled() && _xz('pay_recharge_s', true)) {
                            xi_uc_pay_card('recharge');
                        } ?>
                        <?php if (_xz('card_s', true) && xi_user_can('redeem', $user->ID)) : ?>
                            <div class="xi-card xi-uc-box">
                                <h3 class="xi-section-title"><?php esc_html_e('卡密充值', 'xi'); ?></h3>
                                <form class="xi-uc-redeem" data-xi-redeem>
                                    <input type="text" name="code" placeholder="<?php esc_attr_e('输入充值卡密', 'xi'); ?>" autocomplete="off" required>
                                    <button type="submit" class="xi-btn xi-btn-accent"><?php esc_html_e('兑换', 'xi'); ?></button>
                                </form>
                                <?php if ($tip = _xz('card_tip', '')) : ?>
                                    <div class="xi-uc-tip"><?php echo wp_kses_post($tip); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php xi_uc_logs($user->ID, 'balance'); ?>
                    </section>
                <?php endif; ?>
                <!-- 我的订单 -->
                <?php if (isset($tabs['orders'])) :
                    $orders = xi_user_orders($user->ID);
                    ?>
                    <section class="xi-uc-panel<?php echo $active === 'orders' ? ' is-active' : ''; ?>" data-uc-panel="orders">
                        <?php if (!empty($_GET['xi_pay_return'])) :
                            $ret = xi_get_order(sanitize_text_field(wp_unslash($_GET['xi_pay_return'])));
                            if ($ret && (int) $ret->user_id === $user->ID) :
                                $paid = (int) $ret->status === 1;
                                ?>
                                <div class="xi-card xi-pay-result<?php echo $paid ? ' is-paid' : ''; ?>">
                                    <?php echo xi_icon($paid ? 'check' : 'clock'); ?>
                                    <div>
                                        <b><?php echo $paid ? esc_html__('支付成功', 'xi') : esc_html__('正在确认支付结果', 'xi'); ?></b>
                                        <small><?php echo esc_html($ret->title); ?> · ¥<?php echo esc_html(xi_money($ret->amount)); ?><?php echo $paid ? '' : esc_html__('，如已付款请稍后刷新页面', 'xi'); ?></small>
                                    </div>
                                </div>
                            <?php endif;
                        endif; ?>
                        <div class="xi-card xi-uc-box">
                            <h3 class="xi-section-title"><?php esc_html_e('我的订单', 'xi'); ?></h3>
                            <?php if (!$orders) : ?>
                                <p class="xi-uc-empty"><?php esc_html_e('还没有订单', 'xi'); ?></p>
                            <?php else : ?>
                                <ul class="xi-uc-orders">
                                    <?php foreach ($orders as $order) :
                                        $st = xi_order_status_text($order);
                                        ?>
                                        <li>
                                            <span class="xi-uc-post-status is-<?php echo esc_attr($st[0]); ?>"><?php echo esc_html($st[1]); ?></span>
                                            <span class="xi-uc-post-main">
                                                <b><?php echo esc_html($order->title); ?></b>
                                                <small><?php echo esc_html($order->order_no); ?> · <?php echo esc_html(xi_pay_method_name($order->method)); ?> · <?php echo esc_html(mysql2date('Y-m-d H:i', $order->created_at)); ?></small>
                                            </span>
                                            <em class="xi-uc-order-amount">¥<?php echo esc_html(xi_money($order->amount)); ?></em>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($user->exists()) do_action('xi_uc_panels', $user, $active); ?>

                <!-- 账号设置 -->
                <section class="xi-uc-panel<?php echo $active === 'settings' ? ' is-active' : ''; ?>" data-uc-panel="settings">
                    <div class="xi-card xi-uc-box">
                        <h3 class="xi-section-title"><?php esc_html_e('基本资料', 'xi'); ?></h3>
                        <?php $uc_cover = xi_user_cover_url($user->ID); ?>
                        <div class="xi-uc-cover"<?php echo $uc_cover ? ' style="background-image:url(' . esc_url($uc_cover) . ')"' : ''; ?>>
                            <button type="button" class="xi-btn xi-btn-sm" data-xi-upload="cover"><?php echo xi_icon('camera'); ?><?php esc_html_e('更换主页封面', 'xi'); ?></button>
                            <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden data-xi-upload-file="cover">
                        </div>
                        <div class="xi-uc-avatar">
                            <button type="button" class="xi-uc-avatar-pick" data-xi-upload="avatar" aria-label="<?php esc_attr_e('更换头像', 'xi'); ?>">
                                <?php echo get_avatar($user->ID, 80); ?>
                                <span><?php echo xi_icon('camera'); ?></span>
                            </button>
                            <div>
                                <b><?php echo esc_html($user->display_name); ?></b>
                                <small><?php esc_html_e('点击头像更换，不超过 2 MB；封面建议横图，不超过 5 MB', 'xi'); ?></small>
                            </div>
                            <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" hidden data-xi-upload-file="avatar">
                        </div>
                        <form class="xi-uc-form" data-xi-form="xi_profile_save">
                            <?php
                            xi_uc_field(array('label' => __('用户名', 'xi'), 'icon' => 'user', 'value' => $user->user_login, 'attrs' => 'disabled', 'hint' => __('用于登录，不能修改', 'xi')));
                            xi_uc_field(array('label' => __('昵称', 'xi'), 'icon' => 'edit', 'name' => 'display_name', 'value' => $user->display_name, 'attrs' => 'minlength="2" maxlength="20" required', 'hint' => __('2-20 个字，显示在文章和评论中', 'xi')));
                            xi_uc_field(array('label' => __('个人网站', 'xi'), 'icon' => 'link', 'name' => 'user_url', 'type' => 'url', 'value' => $user->user_url, 'attrs' => 'placeholder="https://"', 'full' => true));
                            xi_uc_field(array('label' => __('个人简介', 'xi'), 'name' => 'description', 'type' => 'textarea', 'value' => $user->description, 'attrs' => 'rows="3" maxlength="200" placeholder="' . esc_attr__('介绍一下自己吧', 'xi') . '"', 'full' => true));
                            ?>
                            <div class="xi-form-actions"><button type="submit" class="xi-btn xi-btn-accent"><?php esc_html_e('保存资料', 'xi'); ?></button></div>
                        </form>
                    </div>

                    <div class="xi-card xi-uc-box">
                        <h3 class="xi-section-title"><?php esc_html_e('绑定邮箱', 'xi'); ?></h3>
                        <?php $pass_unset = xi_user_pass_unset($user->ID); ?>
                        <p class="xi-uc-current"><?php echo xi_icon('mail'); ?><?php esc_html_e('当前邮箱', 'xi'); ?><b><?php echo $user->user_email ? esc_html(xi_mask_email($user->user_email)) : esc_html__('未绑定', 'xi'); ?></b></p>
                        <form class="xi-uc-form" data-xi-form="xi_email_save">
                            <?php
                            $need_code = xi_email_need_code();
                            xi_uc_field(array(
                                'label'  => __('新邮箱', 'xi'),
                                'icon'   => 'mail',
                                'name'   => 'email',
                                'type'   => 'email',
                                'attrs'  => 'required autocomplete="email" placeholder="name@example.com"',
                                'suffix' => $need_code ? '<button type="button" class="xi-field-btn" data-xi-email-code>' . esc_html__('发送验证码', 'xi') . '</button>' : '',
                            ));
                            if ($need_code) {
                                xi_uc_field(array('label' => __('邮箱验证码', 'xi'), 'icon' => 'check', 'name' => 'code', 'attrs' => 'inputmode="numeric" maxlength="6" required autocomplete="one-time-code" placeholder="' . esc_attr__('6 位数字', 'xi') . '"'));
                            }
                            if (!$pass_unset) {
                                xi_uc_field(array('label' => __('当前密码', 'xi'), 'icon' => 'lock', 'name' => 'pass', 'type' => 'password', 'attrs' => 'required autocomplete="current-password"', 'full' => true));
                            }
                            ?>
                            <div class="xi-form-actions"><button type="submit" class="xi-btn xi-btn-accent"><?php echo $user->user_email ? esc_html__('修改邮箱', 'xi') : esc_html__('绑定邮箱', 'xi'); ?></button></div>
                        </form>
                    </div>

                    <?php if (xi_sms_enabled()) :
                        $cur_phone = xi_get_user_phone($user->ID);
                        ?>
                        <div class="xi-card xi-uc-box">
                            <h3 class="xi-section-title"><?php esc_html_e('绑定手机', 'xi'); ?></h3>
                            <p class="xi-uc-current"><?php echo xi_icon('key'); ?><?php esc_html_e('当前手机', 'xi'); ?><b><?php echo $cur_phone ? esc_html(xi_mask_phone($cur_phone)) : esc_html__('未绑定', 'xi'); ?></b></p>
                            <form class="xi-uc-form" data-xi-form="xi_phone_save">
                                <?php
                                xi_uc_field(array(
                                    'label'  => $cur_phone ? __('新手机号', 'xi') : __('手机号', 'xi'),
                                    'icon'   => 'user',
                                    'name'   => 'phone',
                                    'type'   => 'tel',
                                    'attrs'  => 'required inputmode="tel" autocomplete="tel"',
                                    'suffix' => '<button type="button" class="xi-field-btn" data-xi-email-code data-action="xi_phone_code" data-field="phone">' . esc_html__('发送验证码', 'xi') . '</button>',
                                ));
                                xi_uc_field(array('label' => __('短信验证码', 'xi'), 'icon' => 'check', 'name' => 'code', 'attrs' => 'inputmode="numeric" maxlength="6" required autocomplete="one-time-code" placeholder="' . esc_attr__('6 位数字', 'xi') . '"'));
                                if ($cur_phone && !$pass_unset) {
                                    xi_uc_field(array('label' => __('当前密码', 'xi'), 'icon' => 'lock', 'name' => 'pass', 'type' => 'password', 'attrs' => 'required autocomplete="current-password"', 'full' => true));
                                }
                                ?>
                                <div class="xi-form-actions"><button type="submit" class="xi-btn xi-btn-accent"><?php echo $cur_phone ? esc_html__('更换手机', 'xi') : esc_html__('绑定手机', 'xi'); ?></button></div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <?php do_action('xi_uc_settings_after_contact', $user); ?>

                    <div class="xi-card xi-uc-box">
                        <h3 class="xi-section-title"><?php echo $pass_unset ? esc_html__('设置密码', 'xi') : esc_html__('修改密码', 'xi'); ?></h3>
                        <?php if ($pass_unset) : ?>
                            <p class="xi-uc-current"><?php echo xi_icon('key'); ?><?php esc_html_e('你通过第三方账号登录，尚未设置密码。设置后也可以用用户名或邮箱登录', 'xi'); ?></p>
                        <?php endif; ?>
                        <form class="xi-uc-form" data-xi-form="xi_password_save">
                            <input type="text" value="<?php echo esc_attr($user->user_login); ?>" autocomplete="username" hidden>
                            <?php
                            if (!$pass_unset) {
                                xi_uc_field(array('label' => __('当前密码', 'xi'), 'icon' => 'lock', 'name' => 'old_pass', 'type' => 'password', 'attrs' => 'required autocomplete="current-password"', 'full' => true));
                            }
                            xi_uc_field(array('label' => __('新密码', 'xi'), 'icon' => 'key', 'name' => 'new_pass', 'type' => 'password', 'attrs' => 'minlength="6" required autocomplete="new-password"', 'hint' => __('至少 6 位', 'xi')));
                            xi_uc_field(array('label' => __('确认新密码', 'xi'), 'icon' => 'key', 'name' => 'new_pass2', 'type' => 'password', 'attrs' => 'minlength="6" required autocomplete="new-password"'));
                            ?>
                            <div class="xi-form-actions">
                                <button type="submit" class="xi-btn xi-btn-accent"><?php echo $pass_unset ? esc_html__('设置密码', 'xi') : esc_html__('修改密码', 'xi'); ?></button>
                                <small><?php esc_html_e('修改后其他设备需重新登录。忘记当前密码可退出登录，在登录窗口「找回密码」', 'xi'); ?></small>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php
get_footer();

/**
 * 明细列表
 */
function xi_uc_logs($user_id, $type)
{
    $logs = xi_get_logs($user_id, $type, 30);
    echo '<div class="xi-card xi-uc-box">';
    echo '<h3 class="xi-section-title">' . esc_html($type === 'points' ? sprintf(__('%s明细', 'xi'), xi_points_name()) : __('余额明细', 'xi')) . '</h3>';
    if (!$logs) {
        echo '<p class="xi-uc-empty">' . esc_html__('暂无记录', 'xi') . '</p>';
    } else {
        echo '<ul class="xi-uc-logs">';
        foreach ($logs as $log) {
            $delta = $type === 'points' ? (int) $log['d'] : xi_format_balance($log['d']);
            $after = $type === 'points' ? (int) $log['a'] : xi_format_balance($log['a']);
            echo '<li><span><b>' . esc_html($log['n']) . '</b><small>' . esc_html(wp_date('Y-m-d H:i', $log['t'])) . '</small></span>';
            echo '<em class="' . ($log['d'] > 0 ? 'is-plus' : 'is-minus') . '">' . ($log['d'] > 0 ? '+' : '') . esc_html($delta) . '</em>';
            echo '<i>' . esc_html__('余', 'xi') . ' ' . esc_html($after) . '</i></li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

/**
 * 账号设置表单项：标签在上，输入框带前置图标，可选后缀按钮；密码框自动带显示/隐藏切换
 *
 * @param array $f label, name, type, value, icon, attrs, hint, suffix, full
 */
function xi_uc_field($f)
{
    $f = wp_parse_args($f, array('label' => '', 'name' => '', 'type' => 'text', 'value' => '', 'icon' => '', 'attrs' => '', 'hint' => '', 'suffix' => '', 'full' => false));

    $name  = $f['name'] ? ' name="' . esc_attr($f['name']) . '"' : '';
    $attrs = $name . ($f['attrs'] ? ' ' . $f['attrs'] : '');
    if ($f['type'] === 'textarea') {
        $control = '<textarea' . $attrs . '>' . esc_textarea($f['value']) . '</textarea>';
    } else {
        $control = '<input type="' . esc_attr($f['type']) . '" value="' . esc_attr($f['value']) . '"' . $attrs . '>';
    }
    if ($f['type'] === 'password') {
        $f['suffix'] .= '<button type="button" class="xi-field-eye" data-xi-eye aria-label="' . esc_attr__('显示密码', 'xi') . '" aria-pressed="false">' . xi_icon('eye') . '</button>';
    }
    $count = $f['type'] === 'textarea' && preg_match('/maxlength="(\d+)"/', $f['attrs'], $m)
        ? '<small class="xi-field-count" data-xi-count>' . mb_strlen((string) $f['value']) . ' / ' . (int) $m[1] . '</small>'
        : '';

    echo '<label class="xi-field' . ($f['full'] ? ' is-full' : '') . ($f['type'] === 'textarea' ? ' is-textarea' : '') . '">';
    echo '<span class="xi-field-label">' . esc_html($f['label']) . $count . '</span>';
    echo '<span class="xi-field-box">' . ($f['icon'] ? xi_icon($f['icon']) : '') . $control . $f['suffix'] . '</span>';
    if ($f['hint']) {
        echo '<em class="xi-field-hint">' . esc_html($f['hint']) . '</em>';
    }
    echo '</label>';
}

/**
 * 在线充值卡片：余额 recharge / 积分 points
 */
function xi_uc_pay_card($type)
{
    $presets = xi_pay_presets($type === 'points' ? 'pay_points_presets' : 'pay_recharge_presets', $type === 'points' ? '1,5,10,30,50' : '10,30,50,100,200');
    $rate    = max(1, (int) _xz('pay_points_rate', 100));
    $min     = max(0.01, (float) _xz('pay_recharge_min', 1));
    $max     = max($min, (float) _xz('pay_recharge_max', 5000));
    ?>
    <div class="xi-card xi-uc-box xi-uc-pay" data-pay-card="<?php echo esc_attr($type); ?>" data-rate="<?php echo (int) $rate; ?>">
        <h3 class="xi-section-title"><?php echo $type === 'points' ? sprintf(esc_html__('购买%s', 'xi'), esc_html(xi_points_name())) : esc_html__('在线充值', 'xi'); ?></h3>
        <div class="xi-pay-presets">
            <?php foreach ($presets as $i => $amount) : ?>
                <button type="button" class="xi-pay-preset<?php echo $i === 0 ? ' is-active' : ''; ?>" data-amount="<?php echo esc_attr(xi_money($amount)); ?>">
                    <b><?php echo esc_html(rtrim(rtrim(xi_money($amount), '0'), '.')); ?></b><small><?php esc_html_e('元', 'xi'); ?></small>
                    <?php if ($type === 'points') : ?><em><?php echo (int) floor($amount * $rate) . ' ' . esc_html(xi_points_name()); ?></em><?php endif; ?>
                </button>
            <?php endforeach; ?>
            <label class="xi-pay-custom"><input type="number" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" step="0.01" placeholder="<?php esc_attr_e('自定义金额', 'xi'); ?>"></label>
        </div>
        <div class="xi-pay-foot">
            <span class="xi-pay-summary"></span>
            <button type="button" class="xi-btn xi-btn-accent" data-xi-pay="<?php echo esc_attr($type); ?>"><?php echo $type === 'points' ? esc_html__('立即购买', 'xi') : esc_html__('立即充值', 'xi'); ?></button>
        </div>
    </div>
    <?php
}

