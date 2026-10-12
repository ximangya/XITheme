<?php
/**
 * 小工具注册层，对应子比 Zib_CFSwidget
 *
 * XI_CFSwidget::create() 在 after_setup_theme 收集配置，
 * widgets_init 时统一实例化 XI_CSF_Widget 并注册
 */

if (!defined('ABSPATH')) {
    exit;
}

class XI_CFSwidget
{
    public static $widgets = array();
    public static $objects = array();

    public static function create($id, $args = array())
    {
        self::$widgets[$id] = $args;

        // 让 CSF 知道这些字段类型，在小工具页加载对应资源
        if (class_exists('CSF')) {
            $defaults = get_class_vars('XI_CSF_Widget');
            CSF::set_used_fields(XI_CSF_Widget::params(wp_parse_args($args, $defaults['args'])));
        }
    }

    public static function register()
    {
        foreach (self::$widgets as $id => $args) {
            self::$objects[$id] = new XI_CSF_Widget($id, $args);
            register_widget(self::$objects[$id]);
        }
    }

    /**
     * 直接渲染某个模块（用于未添加小工具时的默认侧栏）
     */
    public static function render($id, $instance = array(), $args = array())
    {
        if (empty(self::$objects[$id])) {
            return;
        }
        $args = wp_parse_args($args, array(
            'before_widget' => '<div class="xi-widget xi-module">',
            'after_widget'  => '</div>',
        ));
        self::$objects[$id]->widget($args, $instance);
    }
}
add_action('widgets_init', array('XI_CFSwidget', 'register'), 20);

/**
 * 全宽位置的模块额外包一层容器
 */
function xi_cfswidget_dynamic_sidebar_params($params)
{
    $id = $params[0]['id'];
    if (strpos($id, 'fluid') !== false) {
        $params[0]['before_widget'] = '<div class="xi-container">' . $params[0]['before_widget'];
        $params[0]['after_widget']  = $params[0]['after_widget'] . '</div>';
    }
    return $params;
}
add_filter('dynamic_sidebar_params', 'xi_cfswidget_dynamic_sidebar_params');
