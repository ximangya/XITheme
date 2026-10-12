<?php
/**
 * 版块页：封面 + 图标 + 简介 + 统计 + 关注 / 发帖 + 版主 + 公告 + 下级版块 + 帖子列表
 */

get_header();

$plate   = get_queried_object();
$meta    = xi_plate_meta($plate->term_id);
$color   = xi_plate_color($plate->term_id);
$cover   = !empty($meta['cover']) ? $meta['cover'] : '';
$mods    = xi_plate_mods($plate->term_id);
$is_mod  = xi_bbs_is_mod($plate->term_id);
$kids    = get_terms(array('taxonomy' => 'plate', 'parent' => $plate->term_id, 'hide_empty' => false));
$follows = (int) get_term_meta($plate->term_id, 'xi_follow_count', true);
$style   = $color ? '--plate:' . $color . ';' : '';
if ($cover) {
    $style .= 'background-image:url(' . esc_url($cover) . ');';
}
$pending = 0;
if ($is_mod) {
    $pq      = new WP_Query(array('post_type' => 'forum_post', 'post_status' => 'pending', 'fields' => 'ids', 'posts_per_page' => 1, 'tax_query' => array(array('taxonomy' => 'plate', 'terms' => $plate->term_id))));
    $pending = (int) $pq->found_posts;
}
?>

<main class="xi-main xi-bbs">
    <?php xi_breadcrumb(); ?>

    <section class="xi-card xi-plate-hero">
        <div class="xi-plate-cover<?php echo $cover ? ' has-image' : ''; ?>"<?php echo $style ? ' style="' . esc_attr($style) . '"' : ''; ?>></div>
        <div class="xi-plate-head">
            <?php echo xi_plate_icon($plate, 'is-lg'); ?>
            <div class="xi-plate-info">
                <h1><?php single_term_title(); ?></h1>
                <p><?php echo esc_html($plate->description ? wp_strip_all_tags($plate->description) : __('暂无版块简介', 'xi')); ?></p>
            </div>
            <div class="xi-plate-actions">
                <?php if (is_user_logged_in()) : ?>
                    <?php echo xi_bbs_follow_button($plate->term_id); ?>
                <?php endif; ?>
                <?php if (xi_bbs_can_post_in($plate->term_id)) : ?>
                    <a class="xi-btn xi-btn-accent" href="<?php echo esc_url(xi_bbs_new_url($plate->term_id)); ?>"><?php echo xi_icon('edit'); ?><?php esc_html_e('发帖', 'xi'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <div class="xi-plate-stats">
            <div><b><?php echo esc_html(xi_number(xi_plate_post_count($plate))); ?></b><span><?php esc_html_e('帖子', 'xi'); ?></span></div>
            <div><b><?php echo (int) xi_plate_today_count($plate->term_id); ?></b><span><?php esc_html_e('今日', 'xi'); ?></span></div>
            <div><b data-xi-plate-followers><?php echo (int) $follows; ?></b><span><?php esc_html_e('关注', 'xi'); ?></span></div>
            <?php if ($mods) : ?>
                <div class="xi-plate-mods">
                    <span><?php esc_html_e('版主', 'xi'); ?></span>
                    <?php foreach ($mods as $uid) :
                        $mod = get_userdata($uid);
                        if (!$mod) {
                            continue;
                        } ?>
                        <a href="<?php echo esc_url(get_author_posts_url($uid)); ?>" title="<?php echo esc_attr($mod->display_name); ?>"><?php echo get_avatar($uid, 28); ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($meta['notice'])) : ?>
        <div class="xi-card xi-plate-notice"><?php echo xi_icon('bell'); ?><div class="xi-plate-notice-text"><?php echo wp_kses_post(wpautop($meta['notice'])); ?></div></div>
    <?php endif; ?>

    <?php if ($kids && !is_wp_error($kids) && !is_paged()) : ?>
        <section class="xi-card xi-bbs-group">
            <div class="xi-bbs-plates">
                <?php foreach ($kids as $kid) : ?>
                    <a class="xi-bbs-plate" href="<?php echo esc_url(get_term_link($kid)); ?>">
                        <?php echo xi_plate_icon($kid); ?>
                        <span class="xi-bbs-plate-text">
                            <b><span><?php echo esc_html($kid->name); ?></span></b>
                            <small><?php printf(esc_html__('%d 个帖子', 'xi'), (int) xi_plate_post_count($kid)); ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="xi-card xi-bbs-bar">
        <?php xi_bbs_sort_tabs(get_term_link($plate), $is_mod && $pending ? array('pending' => sprintf(__('待审核 %d', 'xi'), $pending)) : array()); ?>
    </div>

    <?php get_template_part('template-parts/bbs-list', null, array('plate' => $plate->term_id, 'empty' => xi_bbs_is_pending_view() ? __('没有待审核的帖子', 'xi') : __('这个版块还没有帖子，来发布第一个吧', 'xi'))); ?>
</main>

<?php if (xi_has_sidebar()) get_sidebar(); ?>
<?php get_footer(); ?>
