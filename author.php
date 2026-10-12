<?php
/**
 * 个人主页：/author/用户名/
 * 封面与资料卡 + 文章 / 评论标签 + 右侧身份信息
 */

get_header();

$author = get_queried_object();
$is_self = is_user_logged_in() && get_current_user_id() === (int) $author->ID;
$stats = xi_user_stats($author->ID);
$cover = xi_user_cover_url($author->ID);
$bio = get_the_author_meta('description', $author->ID);
$bbs_on = function_exists('xi_bbs_enabled') && xi_bbs_enabled();
$tab = isset($_GET['tab']) && in_array($_GET['tab'], $bbs_on ? array('comments', 'bbs') : array('comments'), true) ? $_GET['tab'] : 'posts';
$base = get_author_posts_url($author->ID);
$level = xi_vip_enabled() ? xi_get_vip_level($author->ID) : 0;
$ban = function_exists('xi_get_ban') ? xi_get_ban($author->ID) : null;
?>

<section class="xi-card xi-author-hero">
    <div class="xi-author-cover" <?php echo $cover ? ' style="background-image:url(' . esc_url($cover) . ')"' : ''; ?>>
        <?php if ($is_self): ?>
            <a class="xi-author-cover-edit"
                href="<?php echo esc_url(xi_uc_url('settings')); ?>"><?php echo xi_icon('camera'); ?><?php esc_html_e('更换封面', 'xi'); ?></a>
        <?php endif; ?>
    </div>
    <div class="xi-author-head">
        <div class="xi-author-avatar<?php echo $level ? ' is-vip' : ''; ?>" <?php echo $level ? ' style="--vip:' . esc_attr(sanitize_hex_color(xi_vip_level_config($level)['color'])) . '"' : ''; ?>>
            <?php echo get_avatar($author->ID, 112); ?>
        </div>
        <div class="xi-author-name">
            <h1><?php echo esc_html($author->display_name); ?></h1>
            <span class="xi-badges"><?php echo xi_user_badges($author); ?><?php if ($ban): ?><span class="xi-ban-badge"><?php echo xi_icon('ban') . esc_html($ban['type'] === 'ban' ? __('已封号', 'xi') : __('禁言中', 'xi')); ?></span><?php endif; ?></span>
        </div>
        <div class="xi-author-actions">
            <?php if ($is_self): ?>
                <a class="xi-btn"
                    href="<?php echo esc_url(xi_uc_url('settings')); ?>"><?php echo xi_icon('edit'); ?><?php esc_html_e('编辑资料', 'xi'); ?></a>
                <a class="xi-btn xi-btn-accent"
                    href="<?php echo esc_url(xi_uc_url()); ?>"><?php echo xi_icon('grid'); ?><?php esc_html_e('用户中心', 'xi'); ?></a>
            <?php else: ?>
                <?php echo xi_follow_button($author->ID); ?>
                <?php echo function_exists('xi_ban_button') ? xi_ban_button($author) : ''; ?>
            <?php endif; ?>
            <?php if (!$is_self && $author->user_url): ?>
                <a class="xi-btn" href="<?php echo esc_url($author->user_url); ?>" target="_blank"
                    rel="noopener nofollow ugc"><?php echo xi_icon('link'); ?><?php esc_html_e('个人网站', 'xi'); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($ban && ($is_self || current_user_can('edit_users'))): ?>
        <div class="xi-ban-notice">
            <?php echo xi_icon('ban'); ?>
            <span><?php echo esc_html($is_self ? xi_ban_message($ban) : sprintf(__('该用户%1$s中，至 %2$s%3$s', 'xi'), xi_ban_types()[$ban['type']], xi_ban_until_text($ban), !empty($ban['reason']) ? '，' . sprintf(__('原因：%s', 'xi'), $ban['reason']) : '')); ?></span>
        </div>
    <?php endif; ?>
    <p class="xi-author-bio"><?php echo esc_html($bio ? $bio : __('这个人很懒，还没有写简介喵~', 'xi')); ?></p>
    <div class="xi-author-stats">
        <a
            href="<?php echo esc_url($base); ?>"><b><?php echo (int) $stats['posts']; ?></b><span><?php esc_html_e('文章', 'xi'); ?></span></a>
        <a
            href="<?php echo esc_url(add_query_arg('tab', 'comments', $base)); ?>"><b><?php echo (int) $stats['comments']; ?></b><span><?php esc_html_e('评论', 'xi'); ?></span></a>
        <?php if (xi_follow_enabled()): ?>
            <div><b
                    data-xi-followers><?php echo (int) xi_get_followers_count($author->ID); ?></b><span><?php esc_html_e('粉丝', 'xi'); ?></span>
            </div>
            <div><b><?php echo count(xi_get_following($author->ID)); ?></b><span><?php esc_html_e('关注', 'xi'); ?></span>
            </div>
        <?php endif; ?>
        <?php if (xi_points_enabled()): ?>
            <div>
                <b><?php echo (int) xi_get_points($author->ID); ?></b><span><?php echo esc_html(xi_points_name()); ?></span>
            </div>
        <?php endif; ?>
        <div><b><?php echo (int) $stats['days']; ?></b><span><?php esc_html_e('加入天数', 'xi'); ?></span></div>
    </div>
</section>

<main class="xi-main">
    <nav class="xi-card xi-author-tabs">
        <a href="<?php echo esc_url($base); ?>"
            class="<?php echo $tab === 'posts' ? 'is-active' : ''; ?>"><?php echo xi_icon('book'); ?><?php echo $is_self ? esc_html__('我的文章', 'xi') : esc_html__('TA 的文章', 'xi'); ?></a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'comments', $base)); ?>"
            class="<?php echo $tab === 'comments' ? 'is-active' : ''; ?>"><?php echo xi_icon('comment'); ?><?php echo $is_self ? esc_html__('我的评论', 'xi') : esc_html__('TA 的评论', 'xi'); ?></a>
        <?php if ($bbs_on): ?>
            <a href="<?php echo esc_url(add_query_arg('tab', 'bbs', $base)); ?>"
                class="<?php echo $tab === 'bbs' ? 'is-active' : ''; ?>"><?php echo xi_icon('chat'); ?><?php echo $is_self ? esc_html__('我的帖子', 'xi') : esc_html__('TA 的帖子', 'xi'); ?></a>
        <?php endif; ?>
        <?php if ($is_self && xi_write_enabled()): ?>
            <a class="xi-author-tabs-end"
                href="<?php echo esc_url(xi_uc_url('posts')); ?>"><?php esc_html_e('草稿与审核中', 'xi'); ?><?php echo xi_icon('chevron'); ?></a>
        <?php endif; ?>
    </nav>

    <?php if ($tab === 'posts'): ?>
        <?php if (have_posts()): ?>
            <div class="<?php echo esc_attr(xi_post_list_class()); ?>">
                <?php
                while (have_posts()):
                    the_post();
                    get_template_part('template-parts/content');
                endwhile;
                ?>
            </div>
            <?php the_posts_pagination(array('mid_size' => 1, 'prev_text' => '‹', 'next_text' => '›')); ?>
        <?php else: ?>
            <div class="xi-card xi-empty">
                <p><?php echo $is_self ? esc_html__('你还没有发布文章', 'xi') : esc_html__('TA 还没有发布文章', 'xi'); ?></p>
                <?php if ($is_self && xi_write_enabled() && xi_user_can('write')): ?>
                    <a class="xi-btn xi-btn-accent"
                        href="<?php echo esc_url(xi_write_url()); ?>"><?php echo xi_icon('edit'); ?><?php esc_html_e('写文章', 'xi'); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($tab === 'bbs'):
        $bpage = isset($_GET['bp']) ? max(1, (int) $_GET['bp']) : 1;
        $bbs = new WP_Query(array(
            'post_type' => 'forum_post',
            'author' => $author->ID,
            'post_status' => $is_self ? array('publish', 'pending') : 'publish',
            'posts_per_page' => 10,
            'paged' => $bpage,
        ));
        ?>
        <?php if ($bbs->have_posts()): ?>
            <div class="xi-bbs-list">
                <?php
                while ($bbs->have_posts()):
                    $bbs->the_post();
                    get_template_part('template-parts/bbs-item');
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
            <?php if ($bbs->max_num_pages > 1): ?>
                <nav class="navigation pagination">
                    <div class="nav-links">
                        <?php for ($i = 1; $i <= $bbs->max_num_pages; $i++): ?>
                            <a class="page-numbers<?php echo $i === $bpage ? ' current' : ''; ?>"
                                href="<?php echo esc_url(add_query_arg(array('tab' => 'bbs', 'bp' => $i), $base)); ?>"><?php echo (int) $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="xi-card xi-empty">
                <p><?php echo $is_self ? esc_html__('你还没有发布帖子', 'xi') : esc_html__('TA 还没有发布帖子', 'xi'); ?></p>
                <?php if ($is_self && xi_bbs_can_post_in()): ?>
                    <a class="xi-btn xi-btn-accent"
                        href="<?php echo esc_url(xi_bbs_new_url()); ?>"><?php echo xi_icon('edit'); ?><?php esc_html_e('发布帖子', 'xi'); ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else:
        $cpage = isset($_GET['cp']) ? max(1, (int) $_GET['cp']) : 1;
        $comments = xi_author_comments_query($author->ID, $cpage);
        ?>
        <?php if ($comments['list']): ?>
            <ul class="xi-card xi-author-comments">
                <?php foreach ($comments['list'] as $comment): ?>
                    <li>
                        <p><?php echo esc_html(wp_trim_words(wp_strip_all_tags($comment->comment_content), 80, '…')); ?></p>
                        <small>
                            <time
                                datetime="<?php echo esc_attr(get_comment_date('c', $comment)); ?>"><?php echo esc_html(get_comment_date('Y-m-d H:i', $comment)); ?></time>
                            <?php esc_html_e('评论于', 'xi'); ?>
                            <a
                                href="<?php echo esc_url(get_comment_link($comment)); ?>"><?php echo esc_html(get_the_title($comment->comment_post_ID)); ?></a>
                        </small>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($comments['pages'] > 1): ?>
                <nav class="navigation pagination">
                    <div class="nav-links">
                        <?php for ($i = 1; $i <= $comments['pages']; $i++): ?>
                            <a class="page-numbers<?php echo $i === $cpage ? ' current' : ''; ?>"
                                href="<?php echo esc_url(add_query_arg(array('tab' => 'comments', 'cp' => $i), $base)); ?>"><?php echo (int) $i; ?></a>
                        <?php endfor; ?>
                    </div>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="xi-card xi-empty">
                <p><?php echo $is_self ? esc_html__('你还没有发表评论', 'xi') : esc_html__('TA 还没有发表评论', 'xi'); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<aside class="xi-sidebar xi-author-side">
    <div class="xi-card xi-author-info">
        <h3 class="xi-section-title"><?php esc_html_e('个人信息', 'xi'); ?></h3>
        <ul>
            <li><span><?php esc_html_e('站点身份', 'xi'); ?></span><b><?php echo esc_html(xi_user_role_name($author)); ?></b>
            </li>
            <?php if ($identity = get_user_meta($author->ID, 'xi_identity', true)): ?>
                <li><span><?php esc_html_e('认证', 'xi'); ?></span><b
                        class="xi-author-verified"><?php echo xi_icon('check') . esc_html($identity); ?></b></li>
            <?php endif; ?>
            <?php if (xi_vip_enabled()): ?>
                <li>
                    <span><?php esc_html_e('会员', 'xi'); ?></span>
                    <?php if ($level): ?>
                        <b><?php echo esc_html(xi_vip_level_config($level)['name']); ?><?php if ($is_self): ?><em><?php echo esc_html(xi_vip_exp_text($author->ID)); ?></em><?php endif; ?></b>
                    <?php else: ?>
                        <b class="is-muted"><?php esc_html_e('普通用户', 'xi'); ?></b>
                    <?php endif; ?>
                </li>
            <?php endif; ?>
            <li><span><?php esc_html_e('加入时间', 'xi'); ?></span><b><?php echo esc_html(wp_date('Y-m-d', strtotime($author->user_registered))); ?></b>
            </li>
            <?php if ($author->user_url): ?>
                <li><span><?php esc_html_e('个人网站', 'xi'); ?></span><b><a href="<?php echo esc_url($author->user_url); ?>"
                            target="_blank"
                            rel="noopener nofollow ugc"><?php echo esc_html(preg_replace('#^https?://#', '', untrailingslashit($author->user_url))); ?></a></b>
                </li>
            <?php endif; ?>
        </ul>
        <?php if ($is_self && xi_vip_enabled() && xi_vip_levels() && !$level && xi_user_can('vip_buy')): ?>
            <a class="xi-btn xi-btn-accent xi-author-vip"
                href="<?php echo esc_url(xi_uc_url('vip')); ?>"><?php echo xi_icon('crown'); ?><?php esc_html_e('开通会员', 'xi'); ?></a>
        <?php endif; ?>
    </div>
</aside>

<?php if (function_exists('xi_ban_modal')) xi_ban_modal($author); ?>
<?php get_footer(); ?>