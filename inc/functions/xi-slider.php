<?php
/**
 * 幻灯片统一渲染，对应子比 zib_new_slider()
 * 顶部多功能组件、幻灯片小工具都走这里，不要另写一套
 *
 * @param array $args 显示设置（见 xi_slider_defaults()）+ slides + class + overlay
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_new_slider($args = array(), $echo = true)
{
    $args = wp_parse_args($args, array_merge(xi_slider_defaults(), array(
        'slides'  => array(),
        'class'   => '',
        'overlay' => '',
    )));

    $slides = array_values(array_filter((array) $args['slides'], 'is_array'));
    if (!$slides) {
        return '';
    }

    $count  = count($slides);
    $effect = in_array($args['effect'], array('slide', 'fade', 'zoom'), true) ? $args['effect'] : 'slide';

    $style = $args['scale_height']
        ? '--scale:' . max(15, (int) $args['scale']) . ';'
        : '--pc-h:' . (int) $args['pc_height'] . 'px;--m-h:' . (int) $args['m_height'] . 'px;';

    $attr  = ' data-effect="' . esc_attr($effect) . '"';
    $attr .= ' data-autoplay="' . ($args['autoplay'] && $count > 1 ? (int) $args['interval'] * 1000 : 0) . '"';
    $attr .= ' data-loop="' . ($args['loop'] ? 1 : 0) . '"';

    $class = 'xi-slider' . ($args['scale_height'] ? ' is-scale' : '') . ($args['class'] ? ' ' . $args['class'] : '');

    $html = '<div class="' . esc_attr($class) . '" style="' . esc_attr($style) . '"' . $attr . '>';
    $html .= '<div class="xi-slider-track">';

    foreach ($slides as $i => $slide) {
        $html .= xi_get_slide_html($slide, $i);
    }

    $html .= '</div>';

    if ($count > 1 && $args['button']) {
        $html .= '<button class="xi-slider-btn is-prev" aria-label="' . esc_attr__('上一张', 'xi') . '">' . xi_icon('chevron') . '</button>';
        $html .= '<button class="xi-slider-btn is-next" aria-label="' . esc_attr__('下一张', 'xi') . '">' . xi_icon('chevron') . '</button>';
    }
    if ($count > 1 && $args['pagination']) {
        $html .= '<div class="xi-slider-dots">';
        for ($i = 0; $i < $count; $i++) {
            $html .= '<button' . ($i === 0 ? ' class="is-active"' : '') . ' aria-label="' . esc_attr(sprintf(__('第 %d 张', 'xi'), $i + 1)) . '"></button>';
        }
        $html .= '</div>';
    }

    if ($args['overlay']) {
        $html .= '<div class="xi-slider-overlay">' . $args['overlay'] . '</div>';
    }

    $html .= '</div>';

    if ($echo) {
        echo $html;
    }
    return $html;
}

function xi_get_slide_html($slide, $index)
{
    $slide = wp_parse_args($slide, array(
        'title'            => '',
        'desc'             => '',
        'background'       => '',
        'background_video' => '',
        'link'             => '',
        'text_pos'         => 'left',
        'btn_text'         => '',
        'btn_url'          => '',
        'mask'             => 20,
        'hide'             => '',
    ));

    $class = 'xi-slide';
    if ($index === 0) {
        $class .= ' is-active';
    }
    if ($slide['hide'] === 'pc') {
        $class .= ' xi-hide-pc';
    } elseif ($slide['hide'] === 'sm') {
        $class .= ' xi-hide-sm';
    }

    $html = '<div class="' . esc_attr($class) . '"' . ($index === 0 ? ' data-pos="active"' : '') . '>';

    // 背景：首张直接加载，其余由脚本切换时加载
    if ($slide['background']) {
        $src_attr = $index === 0 ? 'src="' . esc_url($slide['background']) . '" fetchpriority="high"' : 'data-src="' . esc_url($slide['background']) . '"';
        $html .= '<img class="xi-slide-bg' . ($slide['background_video'] ? ' xi-hide-pc' : '') . '" ' . $src_attr . ' alt="' . esc_attr($slide['title']) . '">';
    } else {
        $html .= '<div class="xi-slide-bg is-gradient"></div>';
    }
    if ($slide['background_video']) {
        $html .= '<video class="xi-slide-bg xi-hide-sm" muted loop playsinline preload="none" data-src="' . esc_url($slide['background_video']) . '"' . ($slide['background'] ? ' poster="' . esc_url($slide['background']) . '"' : '') . '></video>';
    }

    $mask = max(0, min(80, (int) $slide['mask']));
    if ($mask) {
        $html .= '<div class="xi-slide-mask" style="--mask:' . ($mask / 100) . '"></div>';
    }

    if ($slide['link']) {
        $html .= '<a class="xi-slide-link" href="' . esc_url($slide['link']) . '" aria-label="' . esc_attr($slide['title']) . '"></a>';
    }

    if ($slide['title'] || $slide['desc'] || $slide['btn_text']) {
        $pos   = in_array($slide['text_pos'], array('left', 'center', 'right'), true) ? $slide['text_pos'] : 'left';
        $html .= '<div class="xi-slide-text is-' . $pos . '"><div class="xi-slide-text-inner">';
        if ($slide['title']) {
            $html .= '<h2>' . esc_html($slide['title']) . '</h2>';
        }
        if ($slide['desc']) {
            $html .= '<p>' . nl2br(esc_html($slide['desc'])) . '</p>';
        }
        if ($slide['btn_text'] && $slide['btn_url']) {
            $html .= '<a class="xi-btn xi-btn-accent" href="' . esc_url($slide['btn_url']) . '">' . esc_html($slide['btn_text']) . xi_icon('arrow') . '</a>';
        }
        $html .= '</div></div>';
    }

    $html .= '</div>';
    return $html;
}
