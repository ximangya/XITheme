<?php
/**
 * 404 页面：后台「页面&显示 → 404 页面」可指定一个页面作为 404 内容，或自定义默认样式的文字与图片
 */

$nf_id   = (int) _xz('notfound_page', 0);
$nf_page = $nf_id ? get_post($nf_id) : null;
$nf_page = $nf_page && $nf_page->post_type === 'page' && $nf_page->post_status === 'publish' ? $nf_page : null;

get_header(); ?>

<main class="xi-main">
    <?php if ($nf_page) :
        // 用指定页面的内容，状态码仍是 404
        $GLOBALS['post'] = $nf_page;
        setup_postdata($nf_page); ?>
        <article class="xi-card xi-article xi-404-page">
            <header class="xi-article-head"><h1><?php echo esc_html(get_the_title($nf_page)); ?></h1></header>
            <div class="xi-content"><?php echo apply_filters('the_content', $nf_page->post_content); ?></div>
        </article>
        <?php wp_reset_postdata(); ?>
    <?php else :
        $img   = _xz('notfound_img', '');
        $title = _xz('notfound_title', '404');
        $text  = _xz('notfound_text', '你要找的页面不见了，也许被风吹走了。'); ?>
        <div class="xi-card xi-404">
            <?php if ($img) : ?>
                <img class="xi-404-img" src="<?php echo esc_url($img); ?>" alt="">
            <?php endif; ?>
            <?php if ($title !== '') : ?>
                <b><?php echo esc_html($title); ?></b>
            <?php endif; ?>
            <p><?php echo wp_kses_post($text); ?></p>
            <?php if (_xz('notfound_search', true)) {
                get_search_form();
            } ?>
            <a class="xi-btn" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('返回首页', 'xi'); ?></a>
        </div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
