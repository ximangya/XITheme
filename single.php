<?php get_header(); ?>

<main class="xi-main">
    <?php xi_content_widgets('top'); ?>

    <?php
    while (have_posts()) :
        the_post();
        $subtitle = xi_post_meta('subtitle');
        ?>
        <?php xi_breadcrumb(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('xi-card xi-article'); ?>>
            <?php if (xi_post_hero_on()) :
                ob_start();
                xi_write_edit_button();
                $tools = ob_get_clean();
                echo $tools ? '<div class="xi-hero-tools">' . $tools . '</div>' : '';
            else : ?>
            <header class="xi-article-head">
                <?php xi_entry_category(); ?>
                <h1><?php the_title(); ?></h1>
                <?php if ($subtitle) : ?>
                    <p class="xi-subtitle"><?php echo esc_html($subtitle); ?></p>
                <?php endif; ?>
                <div class="xi-article-meta">
                    <?php xi_entry_meta(true); ?>
                    <?php xi_write_edit_button(); ?>
                </div>
            </header>
            <?php endif; ?>

            <?php if (function_exists('xi_ai_summary_box')) xi_ai_summary_box(); ?>
            <div class="xi-content">
                <?php
                the_content();
                wp_link_pages(array(
                    'before' => '<div class="xi-page-links">',
                    'after'  => '</div>',
                ));
                ?>
            </div>

            <?php the_tags('<div class="xi-tags">', '', '</div>'); ?>

            <?php if (_xz('post_copyright_s', true)) : ?>
                <div class="xi-copyright-box">
                    <?php echo xi_icon('leaf'); ?>
                    <p><?php echo esc_html(_xz('post_copyright', '本文为原创内容，转载请注明出处。')); ?></p>
                </div>
            <?php endif; ?>

            <?php xi_post_actions(); ?>
        </article>

        <?php if (_xz('post_nav_s', true)) : ?>
            <nav class="xi-post-nav">
                <div><?php previous_post_link('%link', '<small>' . esc_html__('上一篇', 'xi') . '</small>%title'); ?></div>
                <div><?php next_post_link('%link', '<small>' . esc_html__('下一篇', 'xi') . '</small>%title'); ?></div>
            </nav>
        <?php endif; ?>

        <?php xi_related_posts(); ?>

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
