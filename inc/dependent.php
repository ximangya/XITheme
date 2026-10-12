<?php
/**
 * 主题设置读写
 */

if (!defined('ABSPATH')) {
    exit;
}

define('XI_OPTIONS', 'xi_options');

/**
 * 读取主题设置
 *
 * @param string $name    字段 id
 * @param mixed  $default 默认值
 * @param string $subname 子字段
 */
function _xz($name, $default = false, $subname = '')
{
    static $options = null;
    if ($options === null) {
        $options = get_option(XI_OPTIONS);
        $options = is_array($options) ? $options : array();
    }

    if (isset($options[$name])) {
        if ($subname) {
            return isset($options[$name][$subname]) ? $options[$name][$subname] : $default;
        }
        return $options[$name];
    }
    return $default;
}

/**
 * 单独写入某个设置键，不要整包覆盖 xi_options
 */
function _sxz($name, $value)
{
    $get_option        = get_option(XI_OPTIONS);
    $get_option        = is_array($get_option) ? $get_option : array();
    $get_option[$name] = $value;
    return update_option(XI_OPTIONS, $get_option);
}

/**
 * 读取文章 Meta（xi_post_meta 序列化保存）
 */
function xi_post_meta($key, $default = '', $post_id = null)
{
    $post_id = $post_id ? $post_id : get_the_ID();
    $meta    = get_post_meta($post_id, 'xi_post_meta', true);
    return (is_array($meta) && isset($meta[$key]) && $meta[$key] !== '') ? $meta[$key] : $default;
}

/**
 * 主题色预设：后台调色板与前台共用
 */
function xi_theme_color_presets()
{
    return array(
        'mint'     => '#2bb39a',
        'sky'      => '#2f8fe8',
        'indigo'   => '#5b6cf0',
        'violet'   => '#9a5cf0',
        'sakura'   => '#f0659b',
        'coral'    => '#f2704e',
        'amber'    => '#e9a321',
        'graphite' => '#5f6f6b',
    );
}

/**
 * 当前主题色
 *
 * @param string $mode light 浅色 / dark 深色（未单独设置时返回空，沿用浅色）
 */
function xi_theme_color($mode = 'light')
{
    if ($mode === 'dark') {
        return _xz('theme_color_dark_s', false) ? (string) sanitize_hex_color(_xz('theme_color_dark', '')) : '';
    }
    $presets = xi_theme_color_presets();
    $preset  = _xz('theme_color_preset', '');
    // 旧版只有 theme_color：没有保存过预设时沿用它
    if ($preset && $preset !== 'custom' && isset($presets[$preset])) {
        return $presets[$preset];
    }
    $color = sanitize_hex_color(_xz('theme_color', '#2bb39a'));
    return $color ? $color : '#2bb39a';
}
