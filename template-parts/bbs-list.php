<?php
/**
 * 论坛帖子列表：置顶帖 + 主循环 + 分页 / 空状态
 * $args['empty'] 空状态文字，$args['plate'] 发帖按钮预选的版块
 */
$args  = isset($args) && is_array($args) ? $args : array();
$top   = !empty($GLOBALS['xi_bbs_top_ids']) && !is_paged() ? $GLOBALS['xi_bbs_top_ids'] : array();
$plate = isset($args['plate']) ? (int) $args['plate'] : 0;
?>
<div class="xi-bbs-list">
    <?php
    if ($top) {
        $top_query = new WP_Query(array(
            'post_type'           => 'forum_post',
            'post__in'            => $top,
            'orderby'             => 'post__in',
            'posts_per_page'      => count($top),
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        ));
        while ($top_query->have_posts()) {
            $top_query->the_post();
            get_template_part('template-parts/bbs-item');
        }
        wp_reset_postdata();
    }

    if (have_posts()) {
        while (have_posts()) {
            the_post();
            get_template_part('template-parts/bbs-item');
        }
    } elseif (!$top) {
        echo '<div class="xi-card xi-empty"><p>' . esc_html(isset($args['empty']) ? $args['empty'] : __('还没有帖子，来发布第一个吧', 'xi')) . '</p>';
        if (xi_bbs_can_post_in($plate)) {
            echo '<a class="xi-btn xi-btn-accent" href="' . esc_url(xi_bbs_new_url($plate)) . '">' . xi_icon('edit') . esc_html__('发布帖子', 'xi') . '</a>';
        }
        echo '</div>';
    }
    ?>
</div>
<?php the_posts_pagination(array('mid_size' => 1, 'prev_text' => '‹', 'next_text' => '›')); ?>
