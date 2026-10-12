<?php
/**
 * 话题页
 */

get_header();

$topic = get_queried_object();
?>

<main class="xi-main xi-bbs">
    <?php xi_breadcrumb(); ?>

    <section class="xi-card xi-topic-hero">
        <span class="xi-topic-mark"><?php echo xi_icon('hash'); ?></span>
        <div>
            <h1>#<?php single_term_title(); ?>#</h1>
            <p><?php echo $topic->description ? esc_html(wp_strip_all_tags($topic->description)) : sprintf(esc_html__('共 %d 个帖子参与讨论', 'xi'), (int) $topic->count); ?></p>
        </div>
    </section>

    <div class="xi-card xi-bbs-bar">
        <?php xi_bbs_sort_tabs(get_term_link($topic)); ?>
    </div>

    <?php get_template_part('template-parts/bbs-list', null, array('empty' => __('这个话题下还没有帖子', 'xi'))); ?>
</main>

<?php if (xi_has_sidebar()) get_sidebar(); ?>
<?php get_footer(); ?>
