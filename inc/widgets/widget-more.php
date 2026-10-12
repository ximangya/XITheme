<?php
/**
 * 展示类模块：用户信息、写在前面、公告、搜索、标签云、链接组、站点统计、幻灯片、图文封面
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_widget_register_more()
{
    XI_CFSwidget::create('xi_widget_ui_user', array(
        'title'       => __('用户信息', 'xi'),
        'description' => __('已登录显示当前用户资料与快捷入口，未登录显示登录注册，建议放在侧边栏', 'xi'),
        'callback'    => 'xi_widget_ui_user',
        'size'        => 'mini',
        'xi_title'    => false,
        'fields'      => array(
            array(
                'id'      => 'cover',
                'type'    => 'upload',
                'title'   => __('顶部背景图', 'xi'),
                'desc'    => __('留空使用主题色渐变', 'xi'),
                'library' => 'image',
                'preview' => true,
            ),
            array(
                'id'      => 'guest_text',
                'type'    => 'textarea',
                'title'   => __('未登录时的欢迎语', 'xi'),
                'default' => __('登录后可以发表评论、管理你的文章', 'xi'),
            ),
        ),
    ));

    XI_CFSwidget::create('xi_widget_ui_intro', array(
        'title'       => __('写在前面', 'xi'),
        'description' => __('一段大字引言，可附带文章数统计，适合放在首页主内容上面', 'xi'),
        'callback'    => 'xi_widget_ui_intro',
        'size'        => 'big',
        'xi_title'    => false,
        'fields'      => array(
            array(
                'id'      => 'label',
                'type'    => 'text',
                'title'   => __('小标签', 'xi'),
                'default' => __('写在前面', 'xi'),
            ),
            array(
                'id'       => 'text',
                'type'     => 'textarea',
                'title'    => __('引言内容', 'xi'),
                'desc'     => __('支持 HTML，用 &lt;em&gt; 包裹的文字以主题色高亮，&lt;br&gt; 换行', 'xi'),
                'default'  => '学过的东西，<br>要<em>写下来</em>才算真正学会。',
                'sanitize' => false,
            ),
            array(
                'id'      => 'show_count',
                'type'    => 'switcher',
                'title'   => __('显示文章数', 'xi'),
                'default' => true,
            ),
        ),
    ));

    XI_CFSwidget::create('xi_widget_ui_notice', array(
        'title'       => __('公告提示', 'xi'),
        'description' => __('WinUI 风格的信息栏，可放任意位置', 'xi'),
        'callback'    => 'xi_widget_ui_notice',
        'xi_title'    => false,
        'fields'      => array(
            array(
                'id'      => 'style',
                'type'    => 'button_set',
                'title'   => __('类型', 'xi'),
                'default' => 'info',
                'options' => array(
                    'info'    => __('信息', 'xi'),
                    'success' => __('成功', 'xi'),
                    'warning' => __('警告', 'xi'),
                    'error'   => __('错误', 'xi'),
                ),
            ),
            array(
                'id'    => 'heading',
                'type'  => 'text',
                'title' => __('标题', 'xi'),
            ),
            array(
                'id'       => 'content',
                'type'     => 'textarea',
                'title'    => __('内容', 'xi'),
                'desc'     => __('支持 HTML', 'xi'),
                'sanitize' => false,
            ),
        ),
    ));

    XI_CFSwidget::create('xi_widget_ui_search', array(
        'title'       => __('搜索框', 'xi'),
        'description' => __('站内搜索', 'xi'),
        'callback'    => 'xi_widget_ui_search',
        'fields'      => array(
            array(
                'id'      => 'placeholder',
                'type'    => 'text',
                'title'   => __('占位文字', 'xi'),
                'default' => __('搜索文章…', 'xi'),
            ),
        ),
    ));

    XI_CFSwidget::create('xi_widget_ui_tags', array(
        'title'       => __('标签云', 'xi'),
        'description' => __('显示文章标签', 'xi'),
        'callback'    => 'xi_widget_ui_tags',
        'defaults'    => array('title' => __('标签', 'xi')),
        'fields'      => array(
            array(
                'id'      => 'count',
                'type'    => 'spinner',
                'title'   => __('显示数量', 'xi'),
                'min'     => 5,
                'max'     => 100,
                'default' => 20,
            ),
            array(
                'id'      => 'orderby',
                'type'    => 'button_set',
                'title'   => __('排序', 'xi'),
                'default' => 'count',
                'options' => array(
                    'count' => __('文章数', 'xi'),
                    'name'  => __('名称', 'xi'),
                ),
            ),
            array(
                'id'      => 'show_count',
                'type'    => 'switcher',
                'title'   => __('显示文章数', 'xi'),
                'default' => false,
            ),
        ),
    ));

    XI_CFSwidget::create('xi_widget_ui_links', array(
        'title'       => __('链接列表', 'xi'),
        'description' => __('友情链接、常用入口等', 'xi'),
        'callback'    => 'xi_widget_ui_links',
        'defaults'    => array('title' => __('链接', 'xi')),
        'fields'      => array(
            array(
                'id'      => 'columns',
                'type'    => 'button_set',
                'title'   => __('每行数量', 'xi'),
                'default' => '1',
                'options' => array('1' => '1', '2' => '2', '3' => '3', '4' => '4'),
            ),
            array(
                'id'      => 'new_tab',
                'type'    => 'switcher',
                'title'   => __('新窗口打开', 'xi'),
                'default' => true,
            ),
            array(
                'id'           => 'items',
                'type'         => 'group',
                'title'        => __('链接', 'xi'),
                'button_title' => __('添加链接', 'xi'),
                'fields'       => array(
                    array('id' => 'title', 'type' => 'text', 'title' => __('名称', 'xi')),
                    array('id' => 'url', 'type' => 'text', 'title' => __('链接', 'xi')),
                    array('id' => 'desc', 'type' => 'text', 'title' => __('简介', 'xi')),
                ),
            ),
        ),
    ));

    XI_CFSwidget::create('xi_widget_ui_stats', array(
        'title'       => __('站点统计', 'xi'),
        'description' => __('文章数、评论数、标签数、运行天数', 'xi'),
        'callback'    => 'xi_widget_ui_stats',
        'defaults'    => array('title' => __('站点统计', 'xi')),
        'fields'      => array(
            array(
                'id'       => 'start_date',
                'type'     => 'date',
                'title'    => __('建站日期', 'xi'),
                'desc'     => __('用于计算运行天数，留空依次使用主题设置中的页脚建站日期、第一篇文章的发布日期', 'xi'),
                'settings' => array('dateFormat' => 'yy-mm-dd'),
            ),
        ),
    ));

    XI_CFSwidget::create('xi_widget_ui_slider', array(
        'title'       => __('幻灯片', 'xi'),
        'description' => __('与顶部幻灯片同一套渲染，适合放在全宽度或主内容区域', 'xi'),
        'callback'    => 'xi_widget_ui_slider',
        'size'        => 'big',
        'xi_title'    => false,
        'xi_layout'   => false,
        'fields'      => array(
            XI_CFS_Module::add_slider('slides'),
            XI_CFS_Module::slide('option', array('pc_height' => 320, 'm_height' => 200)),
        ),
    ));

    XI_CFSwidget::create('xi_widget_ui_banner', array(
        'title'       => __('图文封面', 'xi'),
        'description' => __('大图 + 玻璃文字面板，适合放在全宽度或主内容区域', 'xi'),
        'callback'    => 'xi_widget_ui_banner',
        'size'        => 'big',
        'xi_title'    => false,
        'xi_layout'   => false,
        'fields'      => array(
            array(
                'id'      => 'image',
                'type'    => 'upload',
                'title'   => __('背景图', 'xi'),
                'desc'    => __('留空使用主题色渐变', 'xi'),
                'library' => 'image',
                'preview' => true,
            ),
            array(
                'id'    => 'heading',
                'type'  => 'text',
                'title' => __('标题', 'xi'),
            ),
            array(
                'id'    => 'desc',
                'type'  => 'textarea',
                'title' => __('描述', 'xi'),
            ),
            array(
                'id'    => 'btn_text',
                'type'  => 'text',
                'title' => __('按钮文字', 'xi'),
            ),
            array(
                'id'    => 'btn_url',
                'type'  => 'text',
                'title' => __('按钮链接', 'xi'),
            ),
            array(
                'id'      => 'height',
                'type'    => 'slider',
                'title'   => __('高度', 'xi'),
                'min'     => 160,
                'max'     => 600,
                'step'    => 10,
                'unit'    => 'px',
                'default' => 320,
            ),
        ),
    ));
}
add_action('after_setup_theme', 'xi_widget_register_more');

/* ---------- 输出 ---------- */

function xi_widget_ui_user($args, $instance)
{
    $user  = wp_get_current_user();
    $cover = $user->exists() && xi_user_cover_url($user->ID) ? xi_user_cover_url($user->ID) : $instance['cover'];
    $cover = $cover ? ' style="background-image:url(' . esc_url($cover) . ')"' : '';

    echo $args['before_widget'];
    echo '<div class="xi-about">';
    echo '<div class="xi-about-cover"' . $cover . '></div>';

    if ($user->exists()) {
        $stats = xi_user_stats($user->ID);
        echo '<a href="' . esc_url(get_author_posts_url($user->ID)) . '">' . get_avatar($user->ID, 72, '', '', array('class' => 'xi-about-avatar')) . '</a>';
        $badge = xi_vip_badge($user->ID);
        echo '<div class="xi-about-name">' . esc_html($user->display_name) . ($badge ? $badge : '<span class="xi-role">' . esc_html(xi_user_role_name($user)) . '</span>') . '</div>';
        $bio = get_the_author_meta('description', $user->ID);
        echo '<p class="xi-about-desc">' . esc_html($bio ? $bio : __('这个人很懒，什么都没写', 'xi')) . '</p>';
        echo '<div class="xi-about-stats">';
        echo '<div><b>' . (int) $stats['posts'] . '</b><span>' . esc_html__('文章', 'xi') . '</span></div>';
        if (xi_points_enabled()) {
            echo '<div><b>' . (int) xi_get_points($user->ID) . '</b><span>' . esc_html(xi_points_name()) . '</span></div>';
        } else {
            echo '<div><b>' . (int) $stats['comments'] . '</b><span>' . esc_html__('评论', 'xi') . '</span></div>';
        }
        if (xi_balance_enabled()) {
            echo '<div><b>' . esc_html(xi_format_balance(xi_get_balance($user->ID))) . '</b><span>' . esc_html__('余额', 'xi') . '</span></div>';
        } else {
            echo '<div><b>' . (int) $stats['days'] . '</b><span>' . esc_html__('加入天数', 'xi') . '</span></div>';
        }
        echo '</div>';
        echo '<div class="xi-about-links">';
        foreach (array_slice(xi_user_menu_links($user), 0, 3) as $link) {
            echo '<a class="xi-btn xi-btn-sm" href="' . esc_url($link['url']) . '">' . xi_icon($link['icon']) . esc_html($link['title']) . '</a>';
        }
        echo '</div>';
    } else {
        echo '<span class="xi-about-avatar is-guest">' . xi_icon('user') . '</span>';
        echo '<div class="xi-about-name">' . esc_html__('你好，访客', 'xi') . '</div>';
        if ($instance['guest_text']) {
            echo '<p class="xi-about-desc">' . esc_html($instance['guest_text']) . '</p>';
        }
        echo '<div class="xi-about-links">' . xi_login_buttons() . '</div>';
    }

    echo '</div>';
    echo $args['after_widget'];
}

function xi_widget_ui_intro($args, $instance)
{
    if (!$instance['text']) {
        return;
    }

    echo $args['before_widget'];
    echo '<div class="xi-intro">';
    if ($instance['label']) {
        echo '<p class="xi-intro-label">' . esc_html($instance['label']) . '</p>';
    }
    echo '<blockquote>' . wp_kses_post($instance['text']) . '</blockquote>';
    if ($instance['show_count']) {
        echo '<div class="xi-intro-count"><b>' . esc_html(str_pad((string) wp_count_posts()->publish, 2, '0', STR_PAD_LEFT)) . '</b>' . esc_html__('篇文章', 'xi') . '</div>';
    }
    echo '</div>';
    echo $args['after_widget'];
}

function xi_widget_ui_notice_is_show($show, $args, $instance)
{
    return (empty($instance['content']) && empty($instance['heading'])) ? false : $show;
}

function xi_widget_ui_notice($args, $instance)
{
    $icons = array('info' => 'info', 'success' => 'check', 'warning' => 'alert', 'error' => 'close');
    $style = isset($icons[$instance['style']]) ? $instance['style'] : 'info';

    echo $args['before_widget'];
    echo '<div class="xi-infobar xi-infobar-' . esc_attr($style) . '">';
    echo '<span class="xi-infobar-icon">' . xi_icon($icons[$style]) . '</span>';
    echo '<div class="xi-infobar-body">';
    if ($instance['heading']) {
        echo '<strong>' . esc_html($instance['heading']) . '</strong>';
    }
    if ($instance['content']) {
        echo '<div>' . wp_kses_post($instance['content']) . '</div>';
    }
    echo '</div></div>';
    echo $args['after_widget'];
}

function xi_widget_ui_search($args, $instance)
{
    echo $args['before_widget'];
    XI_CSF_Widget::show_title($instance);
    echo '<form role="search" method="get" class="xi-search-form" action="' . esc_url(home_url('/')) . '">';
    echo '<input type="search" name="s" placeholder="' . esc_attr($instance['placeholder']) . '" aria-label="' . esc_attr__('搜索', 'xi') . '">';
    echo '<button type="submit" aria-label="' . esc_attr__('搜索', 'xi') . '">' . xi_icon('search') . '</button>';
    echo '</form>';
    echo $args['after_widget'];
}

function xi_widget_ui_tags($args, $instance)
{
    xi_cache_fragment('wtags|' . md5(serialize(array($args, $instance))), HOUR_IN_SECONDS, function () use ($args, $instance) {
        xi_widget_ui_tags_render($args, $instance);
    });
}

function xi_widget_ui_tags_render($args, $instance)
{
    $tags = get_terms(array(
        'taxonomy'   => 'post_tag',
        'number'     => (int) $instance['count'],
        'orderby'    => $instance['orderby'] === 'name' ? 'name' : 'count',
        'order'      => $instance['orderby'] === 'name' ? 'ASC' : 'DESC',
        'hide_empty' => true,
    ));
    if (empty($tags) || is_wp_error($tags)) {
        return;
    }

    echo $args['before_widget'];
    XI_CSF_Widget::show_title($instance);
    echo '<div class="xi-tag-cloud">';
    foreach ($tags as $tag) {
        echo '<a href="' . esc_url(get_term_link($tag)) . '">' . esc_html($tag->name);
        if ($instance['show_count']) {
            echo '<span>' . (int) $tag->count . '</span>';
        }
        echo '</a>';
    }
    echo '</div>';
    echo $args['after_widget'];
}

function xi_widget_ui_links_is_show($show, $args, $instance)
{
    return empty($instance['items']) ? false : $show;
}

function xi_widget_ui_links($args, $instance)
{
    $target = $instance['new_tab'] ? ' target="_blank" rel="noopener"' : '';

    echo $args['before_widget'];
    XI_CSF_Widget::show_title($instance);
    echo '<div class="xi-links" style="--cols:' . max(1, min(4, (int) $instance['columns'])) . '">';
    foreach ((array) $instance['items'] as $item) {
        if (empty($item['title']) || empty($item['url'])) {
            continue;
        }
        echo '<a class="xi-link-item" href="' . esc_url($item['url']) . '"' . $target . '>';
        echo '<span class="xi-link-badge">' . esc_html(mb_substr($item['title'], 0, 1)) . '</span>';
        echo '<span class="xi-link-text"><b>' . esc_html($item['title']) . '</b>';
        if (!empty($item['desc'])) {
            echo '<small>' . esc_html($item['desc']) . '</small>';
        }
        echo '</span></a>';
    }
    echo '</div>';
    echo $args['after_widget'];
}

function xi_widget_ui_stats($args, $instance)
{
    // 小工具日期 > 主题设置「页脚 · 建站日期」> 第一篇文章日期，与页脚算法一致
    $days = xi_days_since(empty($instance['start_date']) ? _xz('footer_since', '') : $instance['start_date']);
    if (!$days) {
        $first = get_posts(array('numberposts' => 1, 'order' => 'ASC', 'orderby' => 'date', 'fields' => 'ids'));
        $days  = $first ? xi_days_since(get_the_date('Y-m-d', $first[0])) : 1;
    }

    $items = array(
        __('文章', 'xi')     => (int) wp_count_posts()->publish,
        __('评论', 'xi')     => (int) wp_count_comments()->approved,
        __('标签', 'xi')     => (int) wp_count_terms(array('taxonomy' => 'post_tag')),
        __('运行天数', 'xi') => $days,
    );

    echo $args['before_widget'];
    XI_CSF_Widget::show_title($instance);
    echo '<div class="xi-stats">';
    foreach ($items as $label => $num) {
        echo '<div><b>' . esc_html(number_format_i18n($num)) . '</b><span>' . esc_html($label) . '</span></div>';
    }
    echo '</div>';
    echo $args['after_widget'];
}

function xi_widget_ui_slider_is_show($show, $args, $instance)
{
    return empty($instance['slides']) ? false : $show;
}

function xi_widget_ui_slider($args, $instance)
{
    $option           = wp_parse_args((array) $instance['option'], xi_slider_defaults());
    $option['slides'] = $instance['slides'];
    $option['class']  = 'xi-slider-widget';

    echo $args['before_widget'];
    xi_new_slider($option);
    echo $args['after_widget'];
}

function xi_widget_ui_banner_is_show($show, $args, $instance)
{
    return (empty($instance['heading']) && empty($instance['image'])) ? false : $show;
}

function xi_widget_ui_banner($args, $instance)
{
    $style = '--h:' . (int) $instance['height'] . 'px;';
    if ($instance['image']) {
        $style .= 'background-image:url(' . esc_url($instance['image']) . ');';
    }

    echo $args['before_widget'];
    echo '<div class="xi-banner" style="' . esc_attr($style) . '">';
    echo '<div class="xi-banner-panel">';
    if ($instance['heading']) {
        echo '<h2>' . esc_html($instance['heading']) . '</h2>';
    }
    if ($instance['desc']) {
        echo '<p>' . esc_html($instance['desc']) . '</p>';
    }
    if ($instance['btn_text'] && $instance['btn_url']) {
        echo '<a class="xi-btn xi-btn-accent" href="' . esc_url($instance['btn_url']) . '">' . esc_html($instance['btn_text']) . xi_icon('arrow') . '</a>';
    }
    echo '</div></div>';
    echo $args['after_widget'];
}
