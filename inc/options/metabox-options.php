<?php
/**
 * 文章 / 页面 Meta Box
 * 免费版 CSF 不含 Metabox 模块，这里用原生 add_meta_box 实现，
 * 数据仍以数组形式保存在 xi_post_meta，读取统一走 xi_post_meta()
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_post_meta_fields()
{
    return array(
        'subtitle'     => array(
            'type'  => 'text',
            'title' => __('副标题', 'xi'),
        ),
        'description'  => array(
            'type'  => 'textarea',
            'title' => __('SEO描述', 'xi'),
            'desc'  => __('留空则自动截取摘要', 'xi'),
        ),
        'hide_sidebar' => array(
            'type'  => 'checkbox',
            'title' => __('隐藏侧边栏', 'xi'),
        ),
        'hide_header_slider' => array(
            'type'  => 'checkbox',
            'title' => __('隐藏顶部幻灯片', 'xi'),
        ),
    );
}

function xi_add_post_meta_box()
{
    add_meta_box('xi_post_meta', __('曦主题 · 文章设置', 'xi'), 'xi_post_meta_box_html', array('post', 'page'), 'side');
}
add_action('add_meta_boxes', 'xi_add_post_meta_box');

function xi_post_meta_box_html($post)
{
    $value = get_post_meta($post->ID, 'xi_post_meta', true);
    $value = is_array($value) ? $value : array();

    wp_nonce_field('xi_post_meta_save', 'xi_post_meta_nonce');

    foreach (xi_post_meta_fields() as $id => $field) {
        $name = 'xi_post_meta[' . $id . ']';
        $val  = isset($value[$id]) ? $value[$id] : '';

        echo '<div class="xi-meta-field">';
        if ($field['type'] === 'checkbox') {
            echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($val, '1', false) . '> ' . esc_html($field['title']) . '</label>';
        } else {
            echo '<label for="xi-meta-' . esc_attr($id) . '"><strong>' . esc_html($field['title']) . '</strong></label>';
            if ($field['type'] === 'textarea') {
                echo '<textarea id="xi-meta-' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="3">' . esc_textarea($val) . '</textarea>';
            } else {
                echo '<input type="text" id="xi-meta-' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '">';
            }
        }
        if (!empty($field['desc'])) {
            echo '<p class="description">' . esc_html($field['desc']) . '</p>';
        }
        echo '</div>';
    }
    echo '<style>.xi-meta-field{margin:0 0 12px}.xi-meta-field input[type=text],.xi-meta-field textarea{width:100%;margin-top:4px}.xi-meta-field .description{margin:4px 0 0}</style>';
}

function xi_save_post_meta_box($post_id)
{
    if (empty($_POST['xi_post_meta_nonce']) || !wp_verify_nonce(sanitize_key($_POST['xi_post_meta_nonce']), 'xi_post_meta_save')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $input = isset($_POST['xi_post_meta']) ? wp_unslash((array) $_POST['xi_post_meta']) : array();
    $data  = array();
    foreach (xi_post_meta_fields() as $id => $field) {
        $val = isset($input[$id]) ? $input[$id] : '';
        if ($field['type'] === 'checkbox') {
            $data[$id] = $val ? '1' : '';
        } elseif ($field['type'] === 'textarea') {
            $data[$id] = sanitize_textarea_field($val);
        } else {
            $data[$id] = sanitize_text_field($val);
        }
    }
    update_post_meta($post_id, 'xi_post_meta', $data);
}
add_action('save_post', 'xi_save_post_meta_box');
