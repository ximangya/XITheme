<?php
/**
 * 可复用的复合字段，对应子比 CFS_Module
 * 主题设置、小工具共用同一套幻灯片字段
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 幻灯片显示设置默认值
 */
function xi_slider_defaults()
{
    return array(
        'effect'       => 'slide',
        'autoplay'     => true,
        'interval'     => 5,
        'loop'         => true,
        'button'       => true,
        'pagination'   => true,
        'scale_height' => false,
        'scale'        => 40,
        'pc_height'    => 420,
        'm_height'     => 240,
    );
}

/**
 * VIP 等级默认配置（主题设置默认值与前台读取共用）
 */
function xi_vip_defaults($level)
{
    $defaults = array(
        1 => array(
            'enable'        => true,
            'name'          => '黄金会员',
            'color'         => '#e2a33b',
            'benefits'      => "专属会员标识\n免费查看积分付费内容\n签到积分翻倍",
            'checkin_multi' => 2,
            'plans'         => array(
                array('name' => '月卡', 'days' => 30, 'price' => 10, 'points' => 1000),
                array('name' => '年卡', 'days' => 365, 'price' => 88, 'points' => 0),
                array('name' => '永久', 'days' => 0, 'price' => 198, 'points' => 0),
            ),
        ),
        2 => array(
            'enable'        => true,
            'name'          => '钻石会员',
            'color'         => '#2bb3c9',
            'benefits'      => "专属会员标识\n查看全部会员内容\n签到积分三倍",
            'checkin_multi' => 3,
            'plans'         => array(
                array('name' => '月卡', 'days' => 30, 'price' => 20, 'points' => 2000),
                array('name' => '年卡', 'days' => 365, 'price' => 168, 'points' => 0),
                array('name' => '永久', 'days' => 0, 'price' => 398, 'points' => 0),
            ),
        ),
    );
    return isset($defaults[$level]) ? $defaults[$level] : array();
}

/**
 * 可选的内置图标（对应 xi_icon() 中的名称）
 */
function xi_icon_options()
{
    return array(
        'leaf'     => __('叶子', 'xi'),
        'book'     => __('书本', 'xi'),
        'code'     => __('代码', 'xi'),
        'folder'   => __('文件夹', 'xi'),
        'download' => __('下载', 'xi'),
        'star'     => __('星标', 'xi'),
        'heart'    => __('喜欢', 'xi'),
        'user'     => __('用户', 'xi'),
        'comment'  => __('评论', 'xi'),
        'search'   => __('搜索', 'xi'),
        'hash'     => __('话题', 'xi'),
        'info'     => __('信息', 'xi'),
        'gear'     => __('设置', 'xi'),
    );
}

/**
 * 权限项可选的用户组：WordPress 角色（不含管理员，管理员始终拥有全部权限）+ VIP 会员
 *
 * @param bool $guest 是否包含「游客」
 */
function xi_role_options($guest = false)
{
    $options = array();
    if ($guest) {
        $options['guest'] = __('游客（未登录）', 'xi');
    }
    foreach (wp_roles()->roles as $key => $role) {
        if ($key !== 'administrator') {
            $options[$key] = translate_user_role($role['name']);
        }
    }
    $options['xi_vip'] = __('VIP 会员（不论角色）', 'xi');
    return $options;
}

/**
 * 各项权限默认允许的用户组（主题设置默认值与前台判断共用）
 */
function xi_auth_defaults($action = '')
{
    $all      = array_keys(xi_role_options());
    $defaults = array(
        'write'         => $all,
        'publish'       => array('editor', 'author'),
        'edit_publish'  => $all,
        'edit_noreview' => array('editor', 'author'),
        'upload'        => $all,
        'nolimit'       => array('editor'),
        'comment'       => array_merge(array('guest'), $all),
        'checkin'       => $all,
        'redeem'        => $all,
        'vip_buy'       => $all,
        'unlock'        => $all,
        'admin'         => array(),
        'bbs_post'      => $all,
        'bbs_publish'   => $all,
    );
    if ($action) {
        return isset($defaults[$action]) ? $defaults[$action] : array();
    }
    return $defaults;
}

class XI_CFS_Module
{
    /**
     * 幻灯片内容（group）
     */
    public static function add_slider($id = 'slides', $title = '')
    {
        return array(
            'id'           => $id,
            'type'         => 'group',
            'title'        => $title ? $title : __('幻灯片', 'xi'),
            'button_title' => __('添加幻灯片', 'xi'),
            'fields'       => array(
                array(
                    'id'    => 'title',
                    'type'  => 'text',
                    'title' => __('标题', 'xi'),
                ),
                array(
                    'id'    => 'desc',
                    'type'  => 'textarea',
                    'title' => __('描述', 'xi'),
                ),
                array(
                    'id'      => 'background',
                    'type'    => 'upload',
                    'title'   => __('背景图片', 'xi'),
                    'desc'    => __('留空使用主题色渐变；设置了视频时作为移动端兜底图', 'xi'),
                    'library' => 'image',
                    'preview' => true,
                ),
                array(
                    'id'    => 'background_video',
                    'type'  => 'text',
                    'title' => __('背景视频', 'xi'),
                    'desc'  => __('mp4 地址，仅 PC 端播放', 'xi'),
                ),
                array(
                    'id'    => 'link',
                    'type'  => 'text',
                    'title' => __('整张链接', 'xi'),
                ),
                array(
                    'id'      => 'text_pos',
                    'type'    => 'button_set',
                    'title'   => __('文字位置', 'xi'),
                    'default' => 'left',
                    'options' => array(
                        'left'   => __('左', 'xi'),
                        'center' => __('中', 'xi'),
                        'right'  => __('右', 'xi'),
                    ),
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
                    'id'      => 'mask',
                    'type'    => 'slider',
                    'title'   => __('遮罩浓度', 'xi'),
                    'min'     => 0,
                    'max'     => 80,
                    'step'    => 5,
                    'unit'    => '%',
                    'default' => 20,
                ),
                array(
                    'id'      => 'hide',
                    'type'    => 'button_set',
                    'title'   => __('隐藏', 'xi'),
                    'default' => '',
                    'options' => array(
                        ''   => __('不隐藏', 'xi'),
                        'pc' => __('PC端隐藏', 'xi'),
                        'sm' => __('移动端隐藏', 'xi'),
                    ),
                ),
            ),
        );
    }

    /**
     * 幻灯片显示设置（fieldset）
     */
    public static function slide($id = 'option', $defaults = array())
    {
        $defaults = wp_parse_args($defaults, xi_slider_defaults());

        return array(
            'id'     => $id,
            'type'   => 'fieldset',
            'title'  => __('幻灯片设置', 'xi'),
            'fields' => array(
                array(
                    'id'      => 'effect',
                    'type'    => 'button_set',
                    'title'   => __('切换效果', 'xi'),
                    'default' => $defaults['effect'],
                    'options' => array(
                        'slide' => __('滑动', 'xi'),
                        'fade'  => __('淡入淡出', 'xi'),
                        'zoom'  => __('缩放', 'xi'),
                    ),
                ),
                array(
                    'id'      => 'autoplay',
                    'type'    => 'switcher',
                    'title'   => __('自动播放', 'xi'),
                    'default' => $defaults['autoplay'],
                ),
                array(
                    'dependency' => array('autoplay', '!=', ''),
                    'id'         => 'interval',
                    'type'       => 'spinner',
                    'title'      => __('切换间隔', 'xi'),
                    'unit'       => __('秒', 'xi'),
                    'min'        => 2,
                    'max'        => 30,
                    'default'    => $defaults['interval'],
                ),
                array(
                    'id'      => 'loop',
                    'type'    => 'switcher',
                    'title'   => __('循环切换', 'xi'),
                    'default' => $defaults['loop'],
                ),
                array(
                    'id'      => 'button',
                    'type'    => 'switcher',
                    'title'   => __('翻页按钮', 'xi'),
                    'default' => $defaults['button'],
                ),
                array(
                    'id'      => 'pagination',
                    'type'    => 'switcher',
                    'title'   => __('指示器', 'xi'),
                    'default' => $defaults['pagination'],
                ),
                array(
                    'id'      => 'scale_height',
                    'type'    => 'switcher',
                    'title'   => __('按比例自动高度', 'xi'),
                    'default' => $defaults['scale_height'],
                ),
                array(
                    'dependency' => array('scale_height', '!=', ''),
                    'id'         => 'scale',
                    'type'       => 'slider',
                    'title'      => __('高度比例', 'xi'),
                    'desc'       => __('高度为宽度的百分比', 'xi'),
                    'min'        => 15,
                    'max'        => 100,
                    'unit'       => '%',
                    'default'    => $defaults['scale'],
                ),
                array(
                    'dependency' => array('scale_height', '==', ''),
                    'id'         => 'pc_height',
                    'type'       => 'slider',
                    'title'      => __('PC端高度', 'xi'),
                    'min'        => 160,
                    'max'        => 900,
                    'step'       => 10,
                    'unit'       => 'px',
                    'default'    => $defaults['pc_height'],
                ),
                array(
                    'dependency' => array('scale_height', '==', ''),
                    'id'         => 'm_height',
                    'type'       => 'slider',
                    'title'      => __('移动端高度', 'xi'),
                    'min'        => 120,
                    'max'        => 600,
                    'step'       => 10,
                    'unit'       => 'px',
                    'default'    => $defaults['m_height'],
                ),
            ),
        );
    }
}
