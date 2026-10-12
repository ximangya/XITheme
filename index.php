<?php
get_header();

// 搜索：文章 / 用户 两个标签
$show_users = false;
if (is_search() && xi_search_user_enabled()) {
    $keyword    = get_search_query(false);
    $show_users = xi_search_type() === 'user';
    $upage      = $show_users && isset($_GET['upage']) ? max(1, (int) $_GET['upage']) : 1;
    $users      = xi_search_users($keyword, $upage);
    $search_url = add_query_arg('s', rawurlencode($keyword), home_url('/'));
}
?>

<main class="xi-main">
    <?php xi_content_widgets('top'); ?>

    <?php if (is_home()) : ?>
        <h2 class="xi-section-title"><?php echo esc_html(_xz('home_list_title', '最近更新')); ?></h2>
    <?php elseif (is_search()) : ?>
        <?php xi_breadcrumb(); ?>
        <header class="xi-archive-head xi-glass">
            <span class="xi-archive-label"><?php esc_html_e('搜索结果', 'xi'); ?></span>
            <h1><?php echo esc_html(get_search_query()); ?></h1>
            <?php if (xi_search_user_enabled()) : ?>
                <nav class="xi-search-tabs">
                    <a href="<?php echo esc_url($search_url); ?>" class="<?php echo $show_users ? '' : 'is-active'; ?>"><?php echo xi_icon('book'); ?><?php esc_html_e('文章', 'xi'); ?><em><?php echo (int) $wp_query->found_posts; ?></em></a>
                    <a href="<?php echo esc_url(add_query_arg('stype', 'user', $search_url)); ?>" class="<?php echo $show_users ? 'is-active' : ''; ?>"><?php echo xi_icon('user'); ?><?php esc_html_e('用户', 'xi'); ?><em><?php echo (int) $users['total']; ?></em></a>
                </nav>
            <?php endif; ?>
        </header>
    <?php elseif (is_archive()) : ?>
        <?php xi_breadcrumb(); ?>
        <header class="xi-archive-head xi-glass">
            <span class="xi-archive-label"><?php esc_html_e('归档', 'xi'); ?></span>
            <h1><?php echo esc_html(wp_strip_all_tags(get_the_archive_title())); ?></h1>
            <?php the_archive_description('<div class="xi-archive-desc">', '</div>'); ?>
        </header>
    <?php endif; ?>

    <?php if ($show_users) : ?>
        <?php if ($users['users']) : ?>
            <div class="xi-user-grid">
                <?php foreach ($users['users'] as $found) {
                    xi_user_card($found);
                } ?>
            </div>
            <?php if ($users['pages'] > 1) : ?>
                <nav class="navigation pagination"><div class="nav-links">
                    <?php for ($i = 1; $i <= $users['pages']; $i++) : ?>
                        <a class="page-numbers<?php echo $i === $upage ? ' current' : ''; ?>" href="<?php echo esc_url(add_query_arg(array('stype' => 'user', 'upage' => $i), $search_url)); ?>"><?php echo (int) $i; ?></a>
                    <?php endfor; ?>
                </div></nav>
            <?php endif; ?>
        <?php else : ?>
            <div class="xi-card xi-empty">
                <p><?php esc_html_e('没有找到相关用户，换个昵称试试', 'xi'); ?></p>
                <?php get_search_form(); ?>
            </div>
        <?php endif; ?>
    <?php elseif (have_posts()) : ?>
        <div class="<?php echo esc_attr(xi_post_list_class()); ?>">
            <?php
            while (have_posts()) :
                the_post();
                get_template_part('template-parts/content');
            endwhile;
            ?>
        </div>

        <?php
        the_posts_pagination(array(
            'mid_size'  => 1,
            'prev_text' => '‹',
            'next_text' => '›',
        ));
        ?>
    <?php else : ?>
        <div class="xi-card xi-empty">
            <p><?php esc_html_e('这里暂时还没有内容。', 'xi'); ?></p>
            <?php get_search_form(); ?>
        </div>
    <?php endif; ?>

    <?php xi_content_widgets('bottom'); ?>
</main>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
