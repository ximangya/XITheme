<?php
/**
 * CSF 小工具类
 *
 * 免费版 CSF 不含 Widget 模块，这里参照子比 inc/csf-framework 扩展层实现：
 * - 字段渲染统一走 CSF::field()，与主题设置页同一套字段
 * - params() 统一注入标题、卡片样式、动画、显示规则等公共字段
 * - 前台通过 show_title() / animation_class() / show_class() 输出统一结构
 */

if (!defined('ABSPATH')) {
    exit;
}

class XI_CSF_Widget extends WP_Widget
{
    public $unique = '';

    public $args = array(
        'title'          => '',
        'classname'      => '',
        'description'    => '',
        'callback'       => '',
        'is_show_filter' => '',
        'size'           => '',
        'defaults'       => array(),
        'fields'         => array(),
        'xi_title'       => true,
        'xi_layout'      => true,
        'xi_animation'   => true,
        'xi_show'        => true,
    );

    public function __construct($key, $params = array())
    {
        $this->unique = $key;
        $this->args   = self::params(wp_parse_args($params, $this->args));

        parent::__construct($key, __('曦 · ', 'xi') . $this->args['title'], array(
            'classname'                   => trim('xi-module ' . $this->args['classname']),
            'description'                 => $this->args['description'],
            'customize_selective_refresh' => true,
        ));
    }

    /**
     * 在模块字段前注入公共字段
     */
    public static function params($args)
    {
        $common = array();

        if ($args['description']) {
            $common[] = array(
                'type'    => 'submessage',
                'style'   => 'info',
                'content' => esc_html($args['description']),
            );
        }

        if ($args['xi_title']) {
            $common[] = array(
                'id'    => 'title',
                'type'  => 'text',
                'title' => __('模块标题', 'xi'),
            );
            $common[] = array(
                'id'    => 'subtitle',
                'type'  => 'text',
                'title' => __('副标题', 'xi'),
            );
            $common[] = array(
                'id'    => 'title_link',
                'type'  => 'text',
                'title' => __('标题右侧链接', 'xi'),
                'desc'  => __('填写后标题右侧显示「更多」', 'xi'),
            );
        }

        if ($args['xi_layout']) {
            $common[] = array(
                'id'      => 'card_style',
                'type'    => 'button_set',
                'title'   => __('卡片样式', 'xi'),
                'default' => 'glass',
                'options' => array(
                    'glass' => __('液态玻璃', 'xi'),
                    'solid' => __('实色', 'xi'),
                    'none'  => __('无背景', 'xi'),
                ),
            );
        }

        if ($args['xi_animation']) {
            $common[] = array(
                'id'      => 'animation_in',
                'type'    => 'select',
                'title'   => __('模块入场动画', 'xi'),
                'default' => '',
                'options' => array(
                    ''          => __('无动画', 'xi'),
                    'fade'      => __('淡入', 'xi'),
                    'slideup'   => __('从下往上滑出', 'xi'),
                    'slideleft' => __('从右往左滑出', 'xi'),
                    'zoomin'    => __('由小变大', 'xi'),
                ),
            );
        }

        if ($args['xi_show']) {
            $common[] = array(
                'id'      => 'show_type',
                'type'    => 'button_set',
                'title'   => __('显示设备', 'xi'),
                'default' => 'all',
                'options' => array(
                    'all'     => __('全部', 'xi'),
                    'only_pc' => __('仅PC端', 'xi'),
                    'only_sm' => __('仅移动端', 'xi'),
                ),
            );
            $common[] = array(
                'id'      => 'show_id_type',
                'type'    => 'select',
                'title'   => __('按页面限制', 'xi'),
                'default' => '',
                'options' => array(
                    ''     => __('不限制', 'xi'),
                    'show' => __('仅在指定文章/页面显示', 'xi'),
                    'hide' => __('在指定文章/页面隐藏', 'xi'),
                ),
            );
            $common[] = array(
                'dependency' => array('show_id_type', '!=', ''),
                'id'         => 'show_ids',
                'type'       => 'text',
                'title'      => __('文章/页面ID', 'xi'),
                'desc'       => __('多个ID用逗号分隔', 'xi'),
            );
        }

        if ($common && $args['fields']) {
            $common[] = array(
                'type'    => 'subheading',
                'content' => __('模块内容配置', 'xi'),
            );
        }

        $args['fields'] = array_merge($common, $args['fields']);
        return $args;
    }

    public function get_defaults()
    {
        $defaults = array();
        foreach ($this->args['fields'] as $field) {
            if (!empty($field['id'])) {
                $defaults[$field['id']] = isset($field['default']) ? $field['default'] : '';
            }
        }
        return array_merge($defaults, (array) $this->args['defaults']);
    }

    /* ---------- 后台 ---------- */

    public function form($instance)
    {
        if (!class_exists('CSF')) {
            echo '<p>' . esc_html__('缺少 Codestar Framework，无法编辑模块。', 'xi') . '</p>';
            return;
        }
        if (empty($this->args['fields'])) {
            echo '<p>' . esc_html__('此模块无需设置。', 'xi') . '</p>';
            return;
        }

        $defaults = $this->get_defaults();

        echo '<div class="csf csf-widgets csf-fields">';
        foreach ($this->args['fields'] as $field) {
            $unique = '';
            $value  = '';
            if (!empty($field['id'])) {
                $unique = 'widget-' . $this->id_base . '[' . $this->number . ']';
                if ($field['id'] === 'title') {
                    $field['attributes']['id'] = $this->get_field_id('title');
                }
                $value = isset($instance[$field['id']]) ? $instance[$field['id']] : $defaults[$field['id']];
            }
            CSF::field($field, $value, $unique);
        }
        echo '</div>';
    }

    public function update($new_instance, $old_instance)
    {
        $data = array();
        foreach ($this->args['fields'] as $field) {
            if (empty($field['id'])) {
                continue;
            }
            $id  = $field['id'];
            $val = isset($new_instance[$id]) ? $new_instance[$id] : '';

            if (!empty($field['sanitize']) && is_callable($field['sanitize'])) {
                $val = call_user_func($field['sanitize'], $val);
            } elseif ($field['type'] === 'text') {
                $val = sanitize_text_field($val);
            } elseif ($field['type'] === 'textarea') {
                $val = current_user_can('unfiltered_html') ? $val : wp_kses_post($val);
            }
            $data[$id] = $val;
        }
        return $data;
    }

    /* ---------- 前台 ---------- */

    public function widget($args, $instance)
    {
        $instance = wp_parse_args((array) $instance, $this->get_defaults());

        if (!$this->size_allowed($args)) {
            return;
        }

        $show = $this->is_show($args, $instance);
        if (!$show) {
            return;
        }

        $class = ($this->args['xi_layout'] ? self::layout_class($instance) : 'xi-card-none' . self::animation_class($instance)) . (is_string($show) ? ' ' . $show : '');
        $args['before_widget'] = self::add_class(isset($args['before_widget']) ? $args['before_widget'] : '<div class="xi-widget">', $class);
        $args['after_widget']  = isset($args['after_widget']) ? $args['after_widget'] : '</div>';

        if (is_callable($this->args['callback'])) {
            call_user_func($this->args['callback'], $args, $instance, $this);
        }
    }

    /**
     * mini 模块只放窄区域，big 模块不进侧边栏
     */
    public function size_allowed($args)
    {
        if (empty($args['id']) || !$this->args['size']) {
            return true;
        }
        $narrow = strpos($args['id'], 'sidebar') !== false;
        if ($this->args['size'] === 'big' && $narrow) {
            return false;
        }
        if ($this->args['size'] === 'mini' && strpos($args['id'], 'fluid') !== false) {
            return false;
        }
        return true;
    }

    public function is_show($args, $instance)
    {
        $show = self::show_class($instance);

        if ($show && $this->args['is_show_filter'] && is_callable($this->args['is_show_filter'])) {
            $show = call_user_func($this->args['is_show_filter'], $show, $args, $instance);
        } elseif ($show && function_exists($this->unique . '_is_show')) {
            $show = call_user_func($this->unique . '_is_show', $show, $args, $instance);
        }

        return apply_filters('widget_is_show_' . $this->unique, $show, $args, $instance);
    }

    /**
     * 显示规则：返回 false 不显示，true 正常显示，字符串为附加 class
     */
    public static function show_class($instance)
    {
        if (!empty($instance['show_id_type']) && !empty($instance['show_ids'])) {
            $ids = array_filter(array_map('intval', preg_split('/[,，\s]+/u', $instance['show_ids'])));
            $in  = is_singular() && in_array((int) get_queried_object_id(), $ids, true);
            if (($instance['show_id_type'] === 'show' && !$in) || ($instance['show_id_type'] === 'hide' && $in)) {
                return false;
            }
        }

        $type = isset($instance['show_type']) ? $instance['show_type'] : 'all';
        if ($type === 'only_pc') {
            return 'xi-hide-sm';
        }
        if ($type === 'only_sm') {
            return 'xi-hide-pc';
        }
        return true;
    }

    public static function animation_class($instance)
    {
        return !empty($instance['animation_in']) ? ' xi-ani ani-' . sanitize_html_class($instance['animation_in']) : '';
    }

    public static function layout_class($instance)
    {
        $style = !empty($instance['card_style']) ? $instance['card_style'] : 'glass';
        return 'xi-card-' . sanitize_html_class($style) . self::animation_class($instance);
    }

    public static function show_title($instance)
    {
        if (empty($instance['title'])) {
            return;
        }
        echo '<div class="xi-widget-head">';
        echo '<h3 class="xi-widget-title">' . esc_html($instance['title']);
        if (!empty($instance['subtitle'])) {
            echo '<small>' . esc_html($instance['subtitle']) . '</small>';
        }
        echo '</h3>';
        if (!empty($instance['title_link'])) {
            echo '<a class="xi-widget-more" href="' . esc_url($instance['title_link']) . '">' . esc_html__('更多', 'xi') . xi_icon('chevron') . '</a>';
        }
        echo '</div>';
    }

    private static function add_class($html, $class)
    {
        $class = trim($class);
        if (!$class) {
            return $html;
        }
        if (strpos($html, 'class="') === false) {
            return preg_replace('/^<(\w+)/', '<$1 class="' . esc_attr($class) . '"', $html, 1);
        }
        // 全宽位置外层还有一个 xi-container，样式类要加在模块本身（xi-widget）上
        if (preg_match('/class="xi-widget\b/', $html)) {
            return preg_replace('/class="xi-widget\b/', 'class="xi-widget ' . esc_attr($class), $html, 1);
        }
        return preg_replace('/class="/', 'class="' . esc_attr($class) . ' ', $html, 1);
    }
}
