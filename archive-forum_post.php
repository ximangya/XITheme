<?php
/**
 * 论坛首页：概览 + 版块（按分区）+ 帖子列表
 */

get_header();

$stats  = xi_bbs_stats();
$groups = is_paged() ? array() : xi_bbs_plate_groups();
$desc   = (string) _xz('bbs_desc', '');
?>

<main class="xi-main xi-bbs">
    <?php xi_content_widgets('top'); ?>

    <?php if (!is_paged()) : ?>
        <section class="xi-card xi-bbs-hero">
            <div class="xi-bbs-hero-text">
                <h1><?php echo esc_html(xi_bbs_name()); ?></h1>
                <p><?php echo esc_html($desc !== '' ? $desc : __('交流、分享、提问，在这里遇见同好', 'xi')); ?></p>
            </div>
            <div class="xi-bbs-hero-stats">
                <div><b><?php echo esc_html(xi_number($stats['posts'])); ?></b><span><?php esc_html_e('帖子', 'xi'); ?></span></div>
                <div><b><?php echo esc_html(xi_number($stats['today'])); ?></b><span><?php esc_html_e('今日', 'xi'); ?></span></div>
                <div><b><?php echo (int) $stats['plates']; ?></b><span><?php esc_html_e('版块', 'xi'); ?></span></div>
                <div><b><?php echo esc_html(xi_number($stats['users'])); ?></b><span><?php esc_html_e('成员', 'xi'); ?></span></div>
            </div>
            <?php if (xi_bbs_can_post_in()) : ?>
                <a class="xi-btn xi-btn-accent xi-bbs-hero-btn" href="<?php echo esc_url(xi_bbs_new_url()); ?>"><?php echo xi_icon('edit'); ?><?php esc_html_e('发布帖子', 'xi'); ?></a>
            <?php endif; ?>
        </section>

        <?php foreach ($groups as $group) : ?>
            <section class="xi-card xi-bbs-group">
                <h2 class="xi-bbs-group-title">
                    <?php if ($group['section']) : ?>
                        <a href="<?php echo esc_url(get_term_link($group['section'])); ?>"><?php echo esc_html($group['section']->name); ?></a>
                    <?php else : ?>
                        <?php esc_html_e('版块', 'xi'); ?>
                    <?php endif; ?>
                </h2>
                <div class="xi-bbs-plates">
                    <?php foreach ($group['plates'] as $plate) :
                        $today = xi_plate_today_count($plate->term_id); ?>
                        <a class="xi-bbs-plate" href="<?php echo esc_url(get_term_link($plate)); ?>">
                            <?php echo xi_plate_icon($plate); ?>
                            <span class="xi-bbs-plate-text">
                                <b><span><?php echo esc_html($plate->name); ?></span><?php if ($today) : ?><em>+<?php echo (int) $today; ?></em><?php endif; ?></b>
                                <small><?php echo esc_html($plate->description ? wp_strip_all_tags($plate->description) : sprintf(__('%d 个帖子', 'xi'), xi_plate_post_count($plate))); ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="xi-card xi-bbs-bar">
        <?php xi_bbs_sort_tabs(xi_bbs_url()); ?>
    </div>

    <?php get_template_part('template-parts/bbs-list'); ?>

    <?php xi_content_widgets('bottom'); ?>
</main>

<?php if (xi_has_sidebar()) get_sidebar(); ?>
<?php get_footer(); ?>
