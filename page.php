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
            <?php if (!xi_post_hero_on()) : ?>
            <header class="xi-article-head">
                <h1><?php the_title(); ?></h1>
                <?php if ($subtitle) : ?>
                    <p class="xi-subtitle"><?php echo esc_html($subtitle); ?></p>
                <?php endif; ?>
            </header>
            <?php endif; ?>

            <div class="xi-content">
                <?php the_content(); ?>
            </div>
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
