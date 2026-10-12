<?php
/**
 * 论坛帖子列表项：作者 + 时间 + 版块 / 标题 + 摘要 / 图片 / 话题 / 浏览、回复、点赞、最后回复
 */
$post_id = get_the_ID();
$author  = get_userdata((int) get_post_field('post_author', $post_id));
$plate   = xi_bbs_post_plate($post_id);
$images  = xi_bbs_post_images($post_id, 3);
$replies = (int) get_comments_number();
$last    = (int) get_post_meta($post_id, 'xi_last_reply', true);
$excerpt = xi_get_excerpt();
$flags   = xi_bbs_flags($post_id);
$top     = (int) get_post_meta($post_id, 'xi_bbs_top', true);
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('xi-card xi-bbs-item' . ($top ? ' is-top' : '') . (get_post_meta($post_id, 'xi_bbs_essence', true) ? ' is-essence' : '')); ?>>
    <header class="xi-bbs-item-head">
        <?php if ($author) : ?>
            <a class="xi-bbs-avatar" href="<?php echo esc_url(get_author_posts_url($author->ID)); ?>" tabindex="-1" aria-hidden="true"><?php echo get_avatar($author->ID, 40); ?></a>
        <?php endif; ?>
        <div class="xi-bbs-item-user">
            <?php if ($author) : ?>
                <a class="xi-bbs-name" href="<?php echo esc_url(get_author_posts_url($author->ID)); ?>"><?php echo esc_html($author->display_name); ?></a>
                <?php if (xi_vip_enabled()) {
                    echo xi_vip_badge($author->ID);
                } ?>
            <?php endif; ?>
            <small>
                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(xi_bbs_time(get_post_time('U', true))); ?></time>
                <?php if ($plate && !is_tax('plate')) : ?>
                    <a class="xi-bbs-plate-chip" href="<?php echo esc_url(get_term_link($plate)); ?>"><?php echo xi_plate_icon($plate, 'is-xs'); ?><?php echo esc_html($plate->name); ?></a>
                <?php endif; ?>
            </small>
        </div>
        <?php if ($flags) : ?>
            <span class="xi-bbs-flags"><?php echo $flags; ?></span>
        <?php endif; ?>
    </header>

    <h2 class="xi-bbs-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
    <?php if ($excerpt !== '') : ?>
        <p class="xi-bbs-excerpt"><?php echo esc_html($excerpt); ?></p>
    <?php endif; ?>

    <?php if ($images) : ?>
        <a class="xi-bbs-images is-<?php echo count($images); ?>" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
            <?php foreach ($images as $src) : ?>
                <span><img src="<?php echo esc_url($src); ?>" alt="" loading="lazy"></span>
            <?php endforeach; ?>
        </a>
    <?php endif; ?>

    <?php echo xi_bbs_topics_html($post_id); ?>

    <footer class="xi-bbs-item-foot">
        <span title="<?php esc_attr_e('浏览', 'xi'); ?>"><?php echo xi_icon('eye'); ?><?php echo esc_html(xi_number(xi_get_views($post_id))); ?></span>
        <span title="<?php esc_attr_e('回复', 'xi'); ?>"><?php echo xi_icon('comment'); ?><?php echo (int) $replies; ?></span>
        <span title="<?php esc_attr_e('点赞', 'xi'); ?>"><?php echo xi_icon('heart'); ?><?php echo esc_html(xi_number(xi_get_likes($post_id))); ?></span>
        <?php if ($replies && $last && ($who = xi_bbs_last_reply_name($post_id))) : ?>
            <span class="xi-bbs-last"><?php printf(esc_html__('%1$s 回复于 %2$s', 'xi'), esc_html($who), esc_html(xi_bbs_time($last))); ?></span>
        <?php endif; ?>
    </footer>
</article>
