<?php
/**
 * 内容类模块：文章列表、分类、最新评论、文章目录
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_widget_register_posts()
{
    XI_CFSwidget::create('xi_widget_ui_posts', array(
        'title'       => __('文章列表', 'xi'),
        'description' => __('按分类、排序显示文章，支持列表 / 排行 / 卡片三种样式', 'xi'),
        'callback'    => 'xi_widget_ui_posts',
        'defaults'    => array('title' => __('最新文章', 'xi')),
        'fields'      => array(
            array(
                'id'          => 'cat',
                'type'        => 'select',
                'title'       => __('分类', 'xi'),
                'placeholder' => __('全部分类', 'xi'),
                'options'     => 'categories',
            ),
            array(
                'id'      => 'orderby',
                'type'    => 'select',
                'title'   => __('排序', 'xi'),
                'default' => 'date',
                'options' => array(
                    'date'          => __('最新发布', 'xi'),
                    'modified'      => __('最近更新', 'xi'),
                    'comment_count' => __('评论最多', 'xi'),
                    'views'         => __('浏览最多', 'xi'),
                    'rand'          => __('随机', 'xi'),
                ),
            ),
            array(
                'id'      => 'count',
                'type'    => 'spinner',
                'title'   => __('显示数量', 'xi'),
                'min'     => 1,
                'max'     => 24,
                'default' => 5,
            ),
            array(
                'id'      => 'style',
                'type'    => 'button_set',
                'title'   => __('列表样式', 'xi'),
                'default' => 'list',
                'options' => array(
                    'list' => __('缩略图列表', 'xi'),
                    'rank' => __('排行', 'xi'),
                    'card' => __('卡片网格', 'xi'),
                ),
                'desc'    => __('卡片网格适合放在全宽度或主内容区域', 'xi'),
            ),
        ),
    ));

    XI_CFSwidget::create('xi_widget_ui_categories', array(
        'title'       => __('分类列表', 'xi'),
        'description' => __('显示文章分类', 'xi'),
        'callback'    => 'xi_widget_ui_categories',
        'defaults'    => array('title' => __('分类', 'xi')),
        'fields'      => array(
            array(
                'id'      => 'show_count',
                'type'    => 'switcher',
                'title'   => __('显示文章数', 'xi'),
                'default' => true,
            ),
            array(
                'id'      => 'hide_empty',
                'type'    => 'switcher',
                'title'   => __('隐藏空分类', 'xi'),
                'default' => true,
            ),
        ),
    ));

    XI_CFSwidget::create('xi_widget_ui_comments', array(
        'title'       => __('最新评论', 'xi'),
        'description' => __('显示最新通过审核的评论', 'xi'),
        'callback'    => 'xi_widget_ui_comments',
        'size'        => 'mini',
        'defaults'    => array('title' => __('最新评论', 'xi')),
        'fields'      => array(
            array(
                'id'      => 'count',
                'type'    => 'spinner',
                'title'   => __('显示数量', 'xi'),
                'min'     => 1,
                'max'     => 20,
                'default' => 5,
            ),
            array(
                'id'      => 'hide_admin',
                'type'    => 'switcher',
                'title'   => __('不显示管理员评论', 'xi'),
                'default' => true,
            ),
        ),
    ));

    XI_CFSwidget::create('xi_widget_ui_toc', array(
        'title'          => __('文章目录', 'xi'),
        'description'    => __('自动提取正文二、三级标题，仅在文章和页面显示', 'xi'),
        'callback'       => 'xi_widget_ui_toc',
        'is_show_filter' => 'xi_widget_ui_toc_is_show',
        'size'           => 'mini',
        'xi_animation'   => false,
        'defaults'       => array('title' => __('文章目录', 'xi')),
        'fields'         => array(),
    ));
}
add_action('after_setup_theme', 'xi_widget_register_posts');

/* ---------- 输出 ---------- */

function xi_widget_ui_posts($args, $instance)
{
    // 文章页排除当前文章，所以按文章分别缓存
    $key = 'wposts|' . md5(serialize(array($args, $instance))) . '|' . (is_single() ? get_queried_object_id() : 0);
    xi_cache_fragment($key, 10 * MINUTE_IN_SECONDS, function () use ($args, $instance) {
        xi_widget_ui_posts_render($args, $instance);
    });
}

function xi_widget_ui_posts_render($args, $instance)
{
    $query_args = array(
        'posts_per_page'      => (int) $instance['count'],
        'orderby'             => $instance['orderby'],
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    );
    if (!empty($instance['cat'])) {
        $query_args['cat'] = (int) $instance['cat'];
    }
    if (is_single()) {
        $query_args['post__not_in'] = array(get_queried_object_id());
    }

    $query = new WP_Query(apply_filters('xi_widget_posts_query', $query_args, $instance));
    if (!$query->have_posts()) {
        return;
    }

    $style = in_array($instance['style'], array('list', 'rank', 'card'), true) ? $instance['style'] : 'list';

    echo $args['before_widget'];
    XI_CSF_Widget::show_title($instance);
    echo '<ul class="xi-wposts xi-wposts-' . esc_attr($style) . '">';
    $i = 0;
    while ($query->have_posts()) {
        $query->the_post();
        $i++;
        echo '<li><a href="' . esc_url(get_permalink()) . '">';
        if ($style === 'rank') {
            echo '<span class="xi-rank">' . $i . '</span>';
        } else {
            echo '<span class="xi-wposts-thumb">';
            if (has_post_thumbnail()) {
                the_post_thumbnail('post-thumbnail', array('loading' => 'lazy'));
            } else {
                echo xi_icon('leaf');
            }
            echo '</span>';
        }
        echo '<span class="xi-wposts-body"><span class="xi-wposts-title">' . esc_html(get_the_title()) . '</span>';
        echo '<time>' . esc_html(get_the_date('Y-m-d')) . '</time></span>';
        echo '</a></li>';
    }
    echo '</ul>';
    echo $args['after_widget'];
    wp_reset_postdata();
}

function xi_widget_ui_categories($args, $instance)
{
    $cats = get_categories(array(
        'hide_empty' => (bool) $instance['hide_empty'],
        'parent'     => 0,
    ));
    if (!$cats) {
        return;
    }

    echo $args['before_widget'];
    XI_CSF_Widget::show_title($instance);
    echo '<ul class="xi-wlist">';
    foreach ($cats as $cat) {
        $current = is_category($cat->term_id) ? ' class="is-active"' : '';
        echo '<li' . $current . '><a href="' . esc_url(get_category_link($cat)) . '">' . xi_icon('folder') . '<span>' . esc_html($cat->name) . '</span>';
        if ($instance['show_count']) {
            echo '<em>' . (int) $cat->count . '</em>';
        }
        echo '</a></li>';
    }
    echo '</ul>';
    echo $args['after_widget'];
}

function xi_widget_ui_comments($args, $instance)
{
    xi_cache_fragment('wcomments|' . md5(serialize(array($args, $instance))), 10 * MINUTE_IN_SECONDS, function () use ($args, $instance) {
        xi_widget_ui_comments_render($args, $instance);
    });
}

function xi_widget_ui_comments_render($args, $instance)
{
    $query = array(
        'number'      => (int) $instance['count'],
        'status'      => 'approve',
        'type'        => 'comment',
        'post_status' => 'publish',
    );
    if ($instance['hide_admin']) {
        $admins = get_users(array('role' => 'administrator', 'fields' => 'ID'));
        if ($admins) {
            $query['author__not_in'] = $admins;
        }
    }
    $comments = get_comments($query);
    if (!$comments) {
        return;
    }

    echo $args['before_widget'];
    XI_CSF_Widget::show_title($instance);
    echo '<ul class="xi-wcomments">';
    foreach ($comments as $comment) {
        $text = wp_strip_all_tags($comment->comment_content);
        $text = mb_strlen($text) > 48 ? mb_substr($text, 0, 48) . '…' : $text;
        echo '<li><a href="' . esc_url(get_comment_link($comment)) . '">';
        echo get_avatar($comment, 36);
        echo '<span><b>' . esc_html(get_comment_author($comment)) . '</b>';
        echo '<span class="xi-wcomments-text">' . esc_html($text) . '</span></span>';
        echo '</a></li>';
    }
    echo '</ul>';
    echo $args['after_widget'];
}

function xi_widget_ui_toc_is_show($show, $args, $instance)
{
    return is_singular() ? $show : false;
}

function xi_widget_ui_toc($args, $instance)
{
    // 由前台脚本根据 .xi-content 中的 h2/h3 生成，找不到标题时自动隐藏整个模块
    echo str_replace('class="', 'class="xi-toc-widget ', $args['before_widget']);
    XI_CSF_Widget::show_title($instance);
    echo '<nav class="xi-toc" data-target=".xi-content"></nav>';
    echo $args['after_widget'];
}
