<?php
/**
 * 帖子详情：楼主信息 + 标题 + 正文 + 话题 + 互动 + 回复（评论）
 */

get_header();
?>

<main class="xi-main xi-bbs">
    <?php xi_content_widgets('top'); ?>

    <?php
    while (have_posts()) :
        the_post();
        $post_id = get_the_ID();
        $author  = get_userdata((int) get_post_field('post_author', $post_id));
        $plate   = xi_bbs_post_plate($post_id);
        $flags   = xi_bbs_flags($post_id);
        ?>
        <?php xi_breadcrumb(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('xi-card xi-article xi-bbs-post'); ?>>
            <header class="xi-article-head xi-bbs-post-head">
                <div class="xi-bbs-item-head">
                    <?php if ($author) : ?>
                        <a class="xi-bbs-avatar" href="<?php echo esc_url(get_author_posts_url($author->ID)); ?>" tabindex="-1" aria-hidden="true"><?php echo get_avatar($author->ID, 48); ?></a>
                    <?php endif; ?>
                    <div class="xi-bbs-item-user">
                        <?php if ($author) : ?>
                            <a class="xi-bbs-name" href="<?php echo esc_url(get_author_posts_url($author->ID)); ?>"><?php echo esc_html($author->display_name); ?></a>
                            <span class="xi-badges"><?php echo xi_user_badges($author); ?></span>
                        <?php endif; ?>
                        <small>
                            <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(xi_bbs_time(get_post_time('U', true))); ?></time>
                            <?php if ($plate) : ?>
                                <a class="xi-bbs-plate-chip" href="<?php echo esc_url(get_term_link($plate)); ?>"><?php echo xi_plate_icon($plate, 'is-xs'); ?><?php echo esc_html($plate->name); ?></a>
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if ($author && get_current_user_id() !== $author->ID && function_exists('xi_follow_button') && xi_follow_enabled()) {
                        echo xi_follow_button($author->ID);
                    } ?>
                    <?php echo xi_bbs_manage_menu($post_id); ?>
                </div>
                <h1><?php if ($flags) : ?><span class="xi-bbs-flags"><?php echo $flags; ?></span><?php endif; ?><?php the_title(); ?></h1>
                <div class="xi-article-meta">
                    <div class="xi-meta">
                        <?php if (_xz('views_s', true)) : ?>
                            <span><?php echo xi_icon('eye'); ?><span data-xi-views><?php echo esc_html(xi_number(xi_get_views($post_id))); ?></span></span>
                        <?php endif; ?>
                        <span><?php echo xi_icon('comment'); ?><?php echo (int) get_comments_number(); ?></span>
                        <?php if (get_the_modified_time('U') - get_the_time('U') > 300) : ?>
                            <span><?php echo xi_icon('edit'); ?><?php printf(esc_html__('编辑于 %s', 'xi'), esc_html(xi_bbs_time(get_post_modified_time('U', true)))); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <?php if (function_exists('xi_ai_summary_box')) xi_ai_summary_box(); ?>
            <div class="xi-content">
                <?php the_content(); ?>
            </div>

            <?php echo xi_bbs_topics_html($post_id); ?>

            <?php xi_post_actions(); ?>
        </article>

        <?php
        if (comments_open() || get_comments_number()) {
            comments_template();
        }
    endwhile;
    ?>

    <?php xi_content_widgets('bottom'); ?>
</main>

<?php if (xi_has_sidebar()) get_sidebar(); ?>
<?php get_footer(); ?>
