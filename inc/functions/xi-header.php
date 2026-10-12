<?php
/**
 * 顶部多功能组件：幻灯片 + 叠加搜索 + 叠加图标卡片
 * 对应子比 zib_header_slide() / zib_header_slide_is_show()
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_header_slide_is_show()
{
    if (!_xz('header_slider_s', true) || is_paged()) {
        return false;
    }
    if (is_singular() && xi_post_meta('hide_header_slider', false, get_queried_object_id())) {
        return false;
    }
    $type = xi_page_type();
    return $type && in_array($type, (array) _xz('header_slider_show', array('home')), true);
}

/**
 * 未添加幻灯片时的默认展示
 */
function xi_header_slide_default()
{
    return array(
        array(
            'title'    => get_bloginfo('name'),
            'desc'     => get_bloginfo('description'),
            'text_pos' => 'center',
            'mask'     => 0,
        ),
    );
}

function xi_header_slide()
{
    if (!xi_header_slide_is_show()) {
        return;
    }

    $slides = _xz('header_slider', array());
    $slides = $slides ? $slides : xi_header_slide_default();

    $option = wp_parse_args((array) _xz('header_slider_option', array()), xi_slider_defaults());
    $option['slides']  = $slides;
    $option['class']   = 'xi-slider-header';
    $btn_pos           = _xz('header_slider_btn_pos', 'below') === 'overlay' ? 'overlay' : 'below';
    $option['overlay'] = xi_header_slide_search() . ($btn_pos === 'overlay' ? xi_header_slide_buttons('overlay') : '') . xi_header_slide_cards();

    $layout    = _xz('header_slider_layout', 'full') === 'box' ? 'is-box' : 'is-full';
    $show_type = _xz('header_slider_show_type', 'all');
    $class     = 'xi-header-slide ' . $layout;
    if ($show_type === 'only_pc') {
        $class .= ' xi-hide-sm';
    } elseif ($show_type === 'only_sm') {
        $class .= ' xi-hide-pc';
    }

    echo '<section class="' . esc_attr($class) . '">';
    echo $layout === 'is-box' ? '<div class="xi-container">' : '';
    xi_new_slider($option);
    if ($btn_pos === 'below' && ($btns = xi_header_slide_buttons('below'))) {
        echo $layout === 'is-box' ? $btns : '<div class="xi-container">' . $btns . '</div>';
    }
    echo $layout === 'is-box' ? '</div>' : '';
    echo '</section>';
}

function xi_header_slide_search()
{
    if (!_xz('header_slider_search_s', false)) {
        return '';
    }
    $opt = wp_parse_args((array) _xz('header_slider_search_option', array()), array(
        'placeholder' => __('搜索你感兴趣的内容…', 'xi'),
        'keywords'    => '',
        'before_html' => '',
        'after_html'  => '',
    ));

    $html = '<div class="xi-hs-search">';
    if ($opt['before_html']) {
        $html .= '<div class="xi-hs-html">' . wp_kses_post($opt['before_html']) . '</div>';
    }
    $html .= '<form role="search" method="get" class="xi-search-form xi-glass" action="' . esc_url(home_url('/')) . '">';
    $html .= '<input type="search" name="s" placeholder="' . esc_attr($opt['placeholder']) . '" aria-label="' . esc_attr__('搜索', 'xi') . '">';
    $html .= '<button type="submit" aria-label="' . esc_attr__('搜索', 'xi') . '">' . xi_icon('search') . '</button>';
    $html .= '</form>';

    $keywords = array_filter(array_map('trim', preg_split('/[,，]/u', (string) $opt['keywords'])));
    if ($keywords) {
        $html .= '<div class="xi-hs-keywords xi-hide-sm">';
        foreach ($keywords as $word) {
            $html .= '<a href="' . esc_url(home_url('/?s=' . rawurlencode($word))) . '">' . esc_html($word) . '</a>';
        }
        $html .= '</div>';
    }
    if ($opt['after_html']) {
        $html .= '<div class="xi-hs-html">' . wp_kses_post($opt['after_html']) . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * 按钮组：$place = below 幻灯片下方 / overlay 叠加在幻灯片上
 */
function xi_header_slide_buttons($place)
{
    if (!_xz('header_slider_btn_s', false)) {
        return '';
    }
    $html   = '';
    $logged = is_user_logged_in();
    $styles = array('main', 'soft', 'glass', 'line', 'gradient', 'neon', 'card');
    $anims  = array('none', 'shine', 'pulse', 'breath', 'float', 'shake');
    foreach ((array) _xz('header_slider_btns', array()) as $btn) {
        if (!is_array($btn) || empty($btn['text'])) {
            continue;
        }
        // 显示对象：所有人 / 仅游客 / 仅登录用户
        $show = isset($btn['show']) ? $btn['show'] : 'all';
        if (($show === 'guest' && $logged) || ($show === 'user' && !$logged)) {
            continue;
        }
        $style  = isset($btn['style']) && in_array($btn['style'], $styles, true) ? $btn['style'] : 'main';
        $size   = isset($btn['size']) && in_array($btn['size'], array('sm', 'md', 'lg'), true) ? $btn['size'] : 'md';
        $anim   = isset($btn['anim']) && in_array($btn['anim'], $anims, true) ? $btn['anim'] : 'none';
        $color  = !empty($btn['color']) ? sanitize_hex_color($btn['color']) : '';
        $color2 = !empty($btn['color2']) ? sanitize_hex_color($btn['color2']) : '';
        $icon   = isset($btn['icon']) ? trim((string) $btn['icon']) : '';
        $desc   = isset($btn['desc']) ? trim((string) $btn['desc']) : '';
        $action = isset($btn['action']) ? $btn['action'] : 'link';

        // 点击动作：跳转链接，或打开搜索、登录、签到、切换深浅色、回到顶部
        $tag  = 'button';
        $attr = ' type="button"';
        switch ($action) {
            case 'search':
                $attr .= ' data-xi-hs-action="search"';
                break;
            case 'login':
                if ($logged) {
                    continue 2; // 已登录时不显示登录按钮
                }
                $attr .= ' data-xi-sign="login"';
                break;
            case 'checkin':
                if (!$logged) {
                    $attr .= ' data-xi-sign="login"';
                } elseif (function_exists('xi_points_enabled') && xi_points_enabled()) {
                    $done  = function_exists('xi_checkin_status') ? xi_checkin_status(get_current_user_id()) : array('done' => false);
                    $attr .= ' data-xi-checkin' . (!empty($done['done']) ? ' disabled' : '');
                }
                break;
            case 'theme':
                $attr .= ' data-xi-hs-action="theme"';
                break;
            case 'top':
                $attr .= ' data-xi-hs-action="top"';
                break;
            default:
                if (empty($btn['link'])) {
                    $tag  = 'span';
                    $attr = '';
                } else {
                    $tag  = 'a';
                    $attr = ' href="' . esc_url($btn['link']) . '"' . (!empty($btn['blank']) ? ' target="_blank" rel="noopener"' : '');
                }
        }

        $vars = ($color ? '--btn:' . $color . ';' : '') . ($color2 ? '--btn2:' . $color2 . ';' : '');
        $class = 'xi-hs-btn is-' . $style . ' is-' . $size . ($anim !== 'none' ? ' is-anim-' . $anim : '') . ($desc !== '' ? ' has-desc' : '');
        $html .= '<' . $tag . ' class="' . esc_attr($class) . '"' . $attr . ($vars ? ' style="' . esc_attr($vars) . '"' : '') . '>';
        $badge = isset($btn['badge']) ? trim((string) $btn['badge']) : '';
        $html .= ($icon !== '' && function_exists('xi_menu_icon_html') ? xi_menu_icon_html($icon) : '');
        $html .= '<span class="xi-hs-btn-text"><span>' . esc_html($btn['text']) . '</span>' . ($desc !== '' ? '<small>' . esc_html($desc) . '</small>' : '') . '</span>';
        if ($style === 'card') {
            $html .= '<span class="xi-hs-btn-arrow">' . xi_icon('chevron') . '</span>';
        }
        $html .= ($badge !== '' ? '<em class="xi-hs-btn-badge">' . esc_html(mb_substr($badge, 0, 6)) . '</em>' : '') . '</' . $tag . '>';
    }
    if ($html === '') {
        return '';
    }
    $shape = _xz('header_slider_btn_shape', 'pill');
    $shape = in_array($shape, array('pill', 'round', 'square'), true) ? $shape : 'pill';
    $align = _xz('header_slider_btn_align', 'center') === 'left' ? 'left' : 'center';
    return '<div class="xi-hs-btns is-' . esc_attr($place) . ' is-shape-' . $shape . ' is-align-' . $align . '">' . $html . '</div>';
}

function xi_header_slide_cards()
{
    if (!_xz('header_slider_card_s', false)) {
        return '';
    }
    $cards = (array) _xz('header_slider_cards', array());
    $html  = '';
    foreach ($cards as $card) {
        if (!is_array($card) || empty($card['title'])) {
            continue;
        }
        $tag   = !empty($card['link']) ? 'a' : 'div';
        $href  = !empty($card['link']) ? ' href="' . esc_url($card['link']) . '"' : '';
        $html .= '<' . $tag . ' class="xi-hs-card xi-glass"' . $href . '>';
        $html .= '<span class="xi-hs-card-icon">';
        $html .= !empty($card['image']) ? '<img src="' . esc_url($card['image']) . '" alt="" loading="lazy">' : xi_icon(!empty($card['icon']) ? $card['icon'] : 'leaf');
        $html .= '</span><span class="xi-hs-card-text"><b>' . esc_html($card['title']) . '</b>';
        if (!empty($card['desc'])) {
            $html .= '<small>' . esc_html($card['desc']) . '</small>';
        }
        $html .= '</span></' . $tag . '>';
    }
    return $html ? '<div class="xi-hs-cards xi-container">' . $html . '</div>' : '';
}

function xi_header_slide_body_class($classes)
{
    if (xi_header_slide_is_show() && _xz('header_slider_layout', 'full') !== 'box') {
        $classes[] = 'xi-has-header-slide';
        $show_type = _xz('header_slider_show_type', 'all');
        if ($show_type !== 'all') {
            $classes[] = 'xi-header-slide-' . $show_type;
        }
    }
    return $classes;
}
add_filter('body_class', 'xi_header_slide_body_class');
