<?php
$style = xi_list_style();

// 极简列表：日期 + 标题 + 分类
if ($style === 'mini') :
    $cats = get_the_category();
    $date = get_the_date('Y') === current_time('Y') ? get_the_date('m-d') : get_the_date('Y-m-d');
    ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class('xi-post-mini'); ?>>
        <time class="xi-mini-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html($date); ?></time>
        <h2 class="xi-mini-title">
            <?php if (is_sticky()) : ?><span class="xi-sticky-badge"><?php esc_html_e('置顶', 'xi'); ?></span><?php endif; ?>
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h2>
        <?php if ($cats) : ?>
            <a class="xi-mini-cat" href="<?php echo esc_url(get_category_link($cats[0])); ?>"><?php echo esc_html($cats[0]->name); ?></a>
        <?php endif; ?>
    </article>
    <?php
    return;
endif;

$show_thumb = _xz('list_thumb_s', true) && has_post_thumbnail();
$mac        = xi_list_mac();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class('xi-card xi-post-item' . ($show_thumb ? ' has-thumb' : '') . ($mac ? ' has-mac' : '')); ?>>
    <?php if ($mac) :
        $cats = get_the_category(); ?>
        <div class="xi-mac-bar">
            <span class="xi-mac-dots" aria-hidden="true"><i></i><i></i><i></i></span>
            <?php if ($cats) : ?>
                <a class="xi-mac-title" href="<?php echo esc_url(get_category_link($cats[0])); ?>">~/<?php echo esc_html($cats[0]->name); ?></a>
            <?php else : ?>
                <span class="xi-mac-title">~/</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($show_thumb) : ?>
        <a class="xi-thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
            <?php the_post_thumbnail($style === 'big' ? 'large' : 'post-thumbnail', array('loading' => 'lazy')); ?>
        </a>
    <?php endif; ?>

    <div class="xi-post-body">
        <?php if (!$mac) {
            xi_entry_category();
        } ?>
        <h2 class="xi-post-title">
            <?php if (is_sticky()) : ?><span class="xi-sticky-badge"><?php esc_html_e('置顶', 'xi'); ?></span><?php endif; ?>
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h2>
        <p class="xi-excerpt"><?php echo esc_html(xi_get_excerpt()); ?></p>
        <div class="xi-post-foot">
            <?php xi_entry_meta(); ?>
            <a class="xi-more" href="<?php the_permalink(); ?>"><?php esc_html_e('阅读', 'xi'); ?><?php echo xi_icon('arrow'); ?></a>
        </div>
    </div>
</article>
