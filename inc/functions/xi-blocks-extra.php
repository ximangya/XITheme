<?php
/**
 * 扩展区块（21 个）：商城 8 个 + 内容 13 个
 *
 * 每个区块只在 xi_bx_defs() 里定义一次字段，然后：
 * - 后台：注册为动态区块，编辑器按字段自动生成设置面板，用 ServerSideRender 实时预览（assets/js/blocks-extra.js）
 * - 前台编辑器：按字段自动生成插入表单，以短代码 [xi_名称 ...] 保存（assets/js/editor.js）
 * - 渲染：区块和短代码共用 xi_bx_render_名称()
 *
 * 多行字段在短代码里用 {br} 表示换行（避免 wpautop 把换行改成 <br>）
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 区块定义
 * 字段类型：text、textarea、lines（每行一项）、number、url、image、select、toggle、color、datetime、product、productcat、user
 */
function xi_bx_defs()
{
    static $defs = null;
    if ($defs !== null) {
        return $defs;
    }
    $sorts = array('sales' => __('销量最高', 'xi'), 'new' => __('最新上架', 'xi'), 'price_asc' => __('价格从低到高', 'xi'), 'price_desc' => __('价格从高到低', 'xi'));
    $btn   = array('main' => __('实色', 'xi'), 'soft' => __('浅色', 'xi'), 'line' => __('描边', 'xi'));
    $defs  = array(
        /* ---------- 商城 ---------- */
        'product'    => array(
            'title'  => __('商品卡片', 'xi'),
            'icon'   => 'cart',
            'group'  => 'shop',
            'desc'   => __('在文章里展示一个商品：图片、价格、销量和购买按钮', 'xi'),
            'fields' => array(
                'id'    => array('type' => 'product', 'label' => __('商品', 'xi')),
                'style' => array('type' => 'select', 'label' => __('样式', 'xi'), 'default' => 'row', 'options' => array('row' => __('横向卡片', 'xi'), 'mini' => __('紧凑条', 'xi'))),
                'btn'   => array('type' => 'text', 'label' => __('按钮文字', 'xi'), 'default' => __('立即购买', 'xi')),
            ),
        ),
        'products'   => array(
            'title'  => __('商品列表', 'xi'),
            'icon'   => 'grid-view',
            'group'  => 'shop',
            'desc'   => __('按分类、排序展示一组商品', 'xi'),
            'fields' => array(
                'cat'   => array('type' => 'productcat', 'label' => __('商品分类', 'xi')),
                'sort'  => array('type' => 'select', 'label' => __('排序', 'xi'), 'default' => 'sales', 'options' => $sorts),
                'count' => array('type' => 'number', 'label' => __('数量', 'xi'), 'default' => 4),
                'cols'  => array('type' => 'select', 'label' => __('每行', 'xi'), 'default' => '4', 'options' => array('2' => '2', '3' => '3', '4' => '4')),
            ),
        ),
        'buy'        => array(
            'title'  => __('购买按钮', 'xi'),
            'icon'   => 'money-alt',
            'group'  => 'shop',
            'desc'   => __('一键跳到确认订单页购买指定商品（有多个规格时打开商品页选择）', 'xi'),
            'fields' => array(
                'id'    => array('type' => 'product', 'label' => __('商品', 'xi')),
                'text'  => array('type' => 'text', 'label' => __('按钮文字', 'xi'), 'default' => __('立即购买', 'xi')),
                'price' => array('type' => 'toggle', 'label' => __('按钮上显示价格', 'xi'), 'default' => true),
                'style' => array('type' => 'select', 'label' => __('样式', 'xi'), 'default' => 'main', 'options' => $btn),
                'align' => array('type' => 'select', 'label' => __('对齐', 'xi'), 'default' => 'left', 'options' => array('left' => __('居左', 'xi'), 'center' => __('居中', 'xi'))),
            ),
        ),
        'pricing'    => array(
            'title'  => __('价格方案', 'xi'),
            'icon'   => 'table-col-after',
            'group'  => 'shop',
            'desc'   => __('多个套餐并排对比，适合会员、服务报价', 'xi'),
            'fields' => array(
                'plans' => array('type' => 'lines', 'label' => __('方案', 'xi'), 'help' => __('每行一个方案：名称|价格|特性（用 ; 分隔）|按钮链接|推荐（填 1 高亮）', 'xi'), 'default' => "基础版|¥29|1 个站点;一年更新|https://|\n专业版|¥99|5 个站点;终身更新;优先支持|https://|1\n企业版|¥299|不限站点;终身更新;一对一支持|https://|"),
                'btn'   => array('type' => 'text', 'label' => __('按钮文字', 'xi'), 'default' => __('选择方案', 'xi')),
            ),
        ),
        'coupon'     => array(
            'title'  => __('优惠券', 'xi'),
            'icon'   => 'tickets-alt',
            'group'  => 'shop',
            'desc'   => __('票券样式的优惠码，点击复制', 'xi'),
            'fields' => array(
                'amount' => array('type' => 'text', 'label' => __('面额', 'xi'), 'default' => '¥10', 'placeholder' => '¥10 / 8.5折'),
                'title'  => array('type' => 'text', 'label' => __('标题', 'xi'), 'default' => __('新人专享券', 'xi')),
                'code'   => array('type' => 'text', 'label' => __('优惠码', 'xi'), 'default' => 'NEW10'),
                'desc'   => array('type' => 'text', 'label' => __('使用条件', 'xi'), 'default' => __('满 50 元可用', 'xi')),
                'expire' => array('type' => 'text', 'label' => __('有效期', 'xi'), 'placeholder' => '2026-12-31'),
            ),
        ),
        'promo'      => array(
            'title'  => __('满减活动', 'xi'),
            'icon'   => 'megaphone',
            'group'  => 'shop',
            'desc'   => __('自动显示商城当前的满减活动与倒计时，活动结束后自动隐藏', 'xi'),
            'fields' => array(
                'btn' => array('type' => 'text', 'label' => __('按钮文字', 'xi'), 'default' => __('去逛逛', 'xi')),
            ),
        ),
        'bought'     => array(
            'title'  => __('购买后可见', 'xi'),
            'icon'   => 'lock',
            'group'  => 'shop',
            'inner'  => true,
            'desc'   => __('只有购买过指定商品的用户才能看到里面的内容（教程、资源、售后说明）', 'xi'),
            'fields' => array(
                'id'  => array('type' => 'product', 'label' => __('需购买的商品', 'xi')),
                'tip' => array('type' => 'text', 'label' => __('未购买时的提示', 'xi'), 'default' => __('购买该商品后可查看此内容', 'xi')),
            ),
        ),
        'shopcats'   => array(
            'title'  => __('商品分类', 'xi'),
            'icon'   => 'category',
            'group'  => 'shop',
            'desc'   => __('商城分类导航', 'xi'),
            'fields' => array(
                'count' => array('type' => 'toggle', 'label' => __('显示商品数', 'xi'), 'default' => true),
            ),
        ),

        /* ---------- 内容 ---------- */
        'linkcard'   => array(
            'title'  => __('链接卡片', 'xi'),
            'icon'   => 'admin-links',
            'group'  => 'content',
            'desc'   => __('网站 / 资源推荐卡片：标题、简介、图标', 'xi'),
            'fields' => array(
                'url'   => array('type' => 'url', 'label' => __('链接', 'xi')),
                'title' => array('type' => 'text', 'label' => __('标题', 'xi')),
                'desc'  => array('type' => 'text', 'label' => __('简介', 'xi')),
                'image' => array('type' => 'image', 'label' => __('图标 / 图片地址', 'xi'), 'help' => __('留空自动使用网站图标', 'xi')),
                'blank' => array('type' => 'toggle', 'label' => __('新窗口打开', 'xi'), 'default' => true),
            ),
        ),
        'postcard'   => array(
            'title'  => __('文章卡片', 'xi'),
            'icon'   => 'media-document',
            'group'  => 'content',
            'desc'   => __('引用站内一篇文章或帖子', 'xi'),
            'fields' => array(
                'id' => array('type' => 'number', 'label' => __('文章 ID', 'xi'), 'help' => __('在文章列表里把鼠标移到标题上即可看到 ID', 'xi')),
            ),
        ),
        'steps'      => array(
            'title'  => __('步骤', 'xi'),
            'icon'   => 'editor-ol',
            'group'  => 'content',
            'desc'   => __('带序号的操作步骤，适合教程', 'xi'),
            'fields' => array(
                'items' => array('type' => 'lines', 'label' => __('步骤', 'xi'), 'help' => __('每行一步：标题|说明', 'xi'), 'default' => "下载安装包|在下方下载最新版本\n上传主题|后台「外观 → 主题 → 上传」\n启用并设置|启用后进入「曦主题设置」"),
            ),
        ),
        'timeline'   => array(
            'title'  => __('时间轴', 'xi'),
            'icon'   => 'backup',
            'group'  => 'content',
            'desc'   => __('更新日志、发展历程', 'xi'),
            'fields' => array(
                'items' => array('type' => 'lines', 'label' => __('节点', 'xi'), 'help' => __('每行一个：日期|标题|说明', 'xi'), 'default' => "2026-10-01|V1.0 发布|正式版上线\n2026-09-01|Beta 测试|开放内测"),
            ),
        ),
        'progress'   => array(
            'title'  => __('进度条', 'xi'),
            'icon'   => 'chart-bar',
            'group'  => 'content',
            'desc'   => __('技能、完成度、投票结果', 'xi'),
            'fields' => array(
                'items' => array('type' => 'lines', 'label' => __('进度', 'xi'), 'help' => __('每行一个：名称|百分比', 'xi'), 'default' => "设计|90\n前端|80\n后端|65"),
                'color' => array('type' => 'color', 'label' => __('颜色', 'xi'), 'help' => __('留空使用主题色', 'xi')),
            ),
        ),
        'countdown'  => array(
            'title'  => __('倒计时', 'xi'),
            'icon'   => 'clock',
            'group'  => 'content',
            'desc'   => __('活动开始 / 结束倒计时', 'xi'),
            'fields' => array(
                'title' => array('type' => 'text', 'label' => __('标题', 'xi'), 'default' => __('距离活动结束还有', 'xi')),
                'time'  => array('type' => 'datetime', 'label' => __('目标时间', 'xi')),
                'done'  => array('type' => 'text', 'label' => __('结束后显示', 'xi'), 'default' => __('活动已结束', 'xi')),
            ),
        ),
        'video'      => array(
            'title'  => __('视频', 'xi'),
            'icon'   => 'video-alt3',
            'group'  => 'content',
            'desc'   => __('支持 B 站、YouTube 链接和 mp4 直链', 'xi'),
            'fields' => array(
                'url'    => array('type' => 'url', 'label' => __('视频地址', 'xi'), 'help' => __('B 站视频页地址、BV 号、YouTube 地址或 .mp4 直链', 'xi')),
                'title'  => array('type' => 'text', 'label' => __('标题', 'xi')),
                'poster' => array('type' => 'image', 'label' => __('封面图（mp4 直链时有效）', 'xi')),
            ),
        ),
        'audio'      => array(
            'title'  => __('音乐', 'xi'),
            'icon'   => 'format-audio',
            'group'  => 'content',
            'desc'   => __('带封面的音乐播放卡片', 'xi'),
            'fields' => array(
                'url'    => array('type' => 'url', 'label' => __('音频地址', 'xi')),
                'title'  => array('type' => 'text', 'label' => __('歌名', 'xi')),
                'artist' => array('type' => 'text', 'label' => __('歌手', 'xi')),
                'cover'  => array('type' => 'image', 'label' => __('封面图', 'xi')),
            ),
        ),
        'chat'       => array(
            'title'  => __('对话', 'xi'),
            'icon'   => 'format-chat',
            'group'  => 'content',
            'desc'   => __('聊天气泡，适合访谈、问答记录', 'xi'),
            'fields' => array(
                'items' => array('type' => 'lines', 'label' => __('对话', 'xi'), 'help' => __('每行一句：名字：内容', 'xi'), 'default' => "小曦：这个主题支持深色模式吗？\n曦芒：支持，右上角一键切换。"),
                'me'    => array('type' => 'text', 'label' => __('显示在右侧的名字', 'xi'), 'default' => '曦芒'),
            ),
        ),
        'checklist'  => array(
            'title'  => __('清单', 'xi'),
            'icon'   => 'yes-alt',
            'group'  => 'content',
            'desc'   => __('待办 / 检查清单，行首写 x 表示已完成', 'xi'),
            'fields' => array(
                'title' => array('type' => 'text', 'label' => __('标题', 'xi')),
                'items' => array('type' => 'lines', 'label' => __('条目', 'xi'), 'help' => __('每行一项，以「x 」开头表示已完成', 'xi'), 'default' => "x 备份网站\nx 上传新版本\n检查前台页面"),
            ),
        ),
        'stats'      => array(
            'title'  => __('数据卡片', 'xi'),
            'icon'   => 'chart-area',
            'group'  => 'content',
            'desc'   => __('一排醒目的数字', 'xi'),
            'fields' => array(
                'items' => array('type' => 'lines', 'label' => __('数据', 'xi'), 'help' => __('每行一个：数字|说明', 'xi'), 'default' => "10万+|用户\n99.9%|可用率\n24h|在线支持"),
            ),
        ),
        'faq'        => array(
            'title'  => __('常见问题', 'xi'),
            'icon'   => 'editor-help',
            'group'  => 'content',
            'desc'   => __('问答列表，自动输出 FAQ 结构化数据，有利于搜索引擎展示', 'xi'),
            'fields' => array(
                'items' => array('type' => 'lines', 'label' => __('问答', 'xi'), 'help' => __('每行一组：问题|答案', 'xi'), 'default' => "支持退款吗？|虚拟商品付款后 7 天内可申请售后\n可以用在多少个站点？|按购买的方案授权"),
            ),
        ),
        'author'     => array(
            'title'  => __('作者卡片', 'xi'),
            'icon'   => 'admin-users',
            'group'  => 'content',
            'desc'   => __('头像、简介、文章数和主页链接', 'xi'),
            'fields' => array(
                'user' => array('type' => 'user', 'label' => __('用户', 'xi'), 'help' => __('留空为当前文章作者', 'xi')),
            ),
        ),
    );
    return apply_filters('xi_bx_defs', $defs);
}

/* ---------- 注册 ---------- */

function xi_bx_attr_type($field)
{
    if ($field['type'] === 'toggle') {
        return 'boolean';
    }
    // 下拉选择的 ID 在编辑器里是字符串，程序生成的区块可能是数字，两种都接受
    if (in_array($field['type'], array('product', 'productcat', 'user'), true)) {
        return array('string', 'number');
    }
    return $field['type'] === 'number' ? 'number' : 'string';
}

function xi_bx_register()
{
    if (!function_exists('register_block_type')) {
        return;
    }
    $uri = get_template_directory_uri();
    wp_register_script('xi-blocks-extra', $uri . '/assets/js/blocks-extra.js', array('xi-blocks-editor', 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render'), XI_VERSION, true);

    foreach (xi_bx_defs() as $name => $def) {
        $attrs = array();
        foreach ($def['fields'] as $key => $f) {
            $default       = isset($f['default']) ? $f['default'] : ($f['type'] === 'toggle' ? false : ($f['type'] === 'number' ? 0 : ''));
            $attrs[$key]   = array('type' => xi_bx_attr_type($f), 'default' => $default);
        }
        register_block_type('xi/' . $name, array(
            'api_version'     => 3,
            'category'        => 'xi',
            'title'           => $def['title'],
            'editor_script'   => 'xi-blocks-extra',
            'editor_style'    => 'xi-blocks-editor',
            'style'           => 'xi-blocks',
            'attributes'      => $attrs,
            'render_callback' => function ($a, $content = '') use ($name) {
                return xi_bx_render($name, $a, $content);
            },
        ));
        add_shortcode('xi_' . $name, function ($atts, $content = '') use ($name) {
            return xi_bx_render($name, xi_bx_shortcode_atts($name, $atts), $content === null ? '' : do_shortcode($content));
        });
    }
}
add_action('init', 'xi_bx_register', 11);

/**
 * 短代码属性 → 区块属性：补默认值、还原 {br} 换行、转换布尔与数字
 */
function xi_bx_shortcode_atts($name, $atts)
{
    $defs = xi_bx_defs();
    $out  = array();
    foreach ($defs[$name]['fields'] as $key => $f) {
        $default = isset($f['default']) ? $f['default'] : '';
        $v       = is_array($atts) && isset($atts[$key]) ? $atts[$key] : null;
        if ($f['type'] === 'toggle') {
            $out[$key] = $v === null ? (bool) $default : in_array(strtolower((string) $v), array('1', 'true', 'yes', 'on'), true);
        } elseif ($f['type'] === 'number' || in_array($f['type'], array('product', 'user'), true)) {
            $out[$key] = $v === null ? $default : (int) $v;
        } else {
            $out[$key] = $v === null ? $default : str_replace('{br}', "\n", (string) $v);
        }
    }
    return $out;
}

function xi_bx_render($name, $a, $content)
{
    $fn = 'xi_bx_render_' . $name;
    if (!function_exists($fn)) {
        return '';
    }
    wp_enqueue_script('xi-blocks-view');
    return (string) call_user_func($fn, $a, $content);
}

/**
 * 编辑器 / 前台编辑器需要的区块定义
 */
function xi_bx_client_defs()
{
    $out = array();
    foreach (xi_bx_defs() as $name => $def) {
        $fields = array();
        foreach ($def['fields'] as $key => $f) {
            $fields[] = array(
                'key'         => $key,
                'type'        => $f['type'],
                'label'       => $f['label'],
                'default'     => isset($f['default']) ? $f['default'] : ($f['type'] === 'toggle' ? false : ''),
                'options'     => isset($f['options']) ? $f['options'] : null,
                'help'        => isset($f['help']) ? $f['help'] : '',
                'placeholder' => isset($f['placeholder']) ? $f['placeholder'] : '',
            );
        }
        $out[] = array(
            'name'   => $name,
            'title'  => $def['title'],
            'icon'   => $def['icon'],
            'group'  => $def['group'],
            'desc'   => $def['desc'],
            'inner'  => !empty($def['inner']),
            'fields' => $fields,
        );
    }
    return $out;
}

/**
 * 可选项：商品、商品分类、用户（编辑器下拉框用，各取前 200 个）
 */
function xi_bx_choices()
{
    $products = array('0' => __('— 选择商品 —', 'xi'));
    $cats     = array('0' => __('全部分类', 'xi'));
    if (function_exists('xi_shop_enabled') && xi_shop_enabled() && post_type_exists('xi_product')) {
        foreach (get_posts(array('post_type' => 'xi_product', 'numberposts' => 200, 'post_status' => 'publish', 'orderby' => 'date')) as $p) {
            $products[(string) $p->ID] = $p->post_title . ' (#' . $p->ID . ')';
        }
        $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 200));
        if (!is_wp_error($terms)) {
            foreach ($terms as $t) {
                $cats[(string) $t->term_id] = $t->name;
            }
        }
    }
    $users = array('0' => __('当前文章作者', 'xi'));
    foreach (get_users(array('number' => 200, 'role__in' => array('administrator', 'editor', 'author', 'contributor'), 'fields' => array('ID', 'display_name'))) as $u) {
        $users[(string) $u->ID] = $u->display_name;
    }
    return array('product' => $products, 'productcat' => $cats, 'user' => $users);
}

function xi_bx_editor_data()
{
    wp_localize_script('xi-blocks-extra', 'xiBlockDefs', array(
        'blocks'  => xi_bx_client_defs(),
        'choices' => xi_bx_choices(),
        'shop'    => function_exists('xi_shop_enabled') && xi_shop_enabled(),
    ));
}
add_action('enqueue_block_editor_assets', 'xi_bx_editor_data');

/**
 * 前台编辑器：区块定义交给 editor.js
 */
function xi_bx_writer_data()
{
    if (!wp_script_is('xi-editor', 'enqueued')) {
        return;
    }
    $data = array(
        'blocks'  => xi_bx_client_defs(),
        'choices' => xi_bx_choices(),
        'shop'    => function_exists('xi_shop_enabled') && xi_shop_enabled(),
    );
    wp_add_inline_script('xi-editor', 'window.xiBlockDefs=' . wp_json_encode($data, JSON_UNESCAPED_UNICODE) . ';', 'before');
}
add_action('wp_enqueue_scripts', 'xi_bx_writer_data', 31);

/* ---------- 辅助 ---------- */

/**
 * 多行字段拆成数组，每行再按 | 拆分
 */
function xi_bx_lines($text, $parts = 2)
{
    $rows = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $cols = array_map('trim', explode('|', $line, $parts));
        $rows[] = array_pad($cols, $parts, '');
    }
    return $rows;
}

/**
 * 编辑器预览里的提示（前台访客看不到）
 */
function xi_bx_notice($text)
{
    if (defined('REST_REQUEST') && REST_REQUEST || current_user_can('edit_posts')) {
        return '<div class="xi-bx-empty">' . esc_html($text) . '</div>';
    }
    return '';
}

function xi_bx_shop_ready()
{
    return function_exists('xi_shop_enabled') && xi_shop_enabled() && function_exists('xi_product_data');
}

function xi_bx_product($id)
{
    $post = $id ? get_post((int) $id) : null;
    return $post && $post->post_type === 'xi_product' && $post->post_status === 'publish' ? $post : null;
}

/**
 * 直接购买的地址：单规格去确认订单页，多规格去商品页选规格
 */
function xi_bx_buy_url($post)
{
    $data = xi_product_data($post->ID);
    return $data['specs'] && count($data['specs']) > 1 ? get_permalink($post) : xi_shop_checkout_url(array('items' => $post->ID . ':0:1'));
}

function xi_bx_price_html($post)
{
    $p = xi_product_display_price($post->ID, get_current_user_id());
    if (!$p) {
        return '';
    }
    $html = xi_price_html($p['price'], $p['type']) . (!empty($p['from']) ? '<small>' . esc_html__('起', 'xi') . '</small>' : '');
    if ($p['original'] > $p['price'] && $p['type'] !== 'free') {
        $html .= '<del>' . esc_html(xi_price_text($p['original'], $p['type'])) . '</del>';
    }
    return $html;
}

function xi_bx_thumb($post_id, $size = 'medium')
{
    return has_post_thumbnail($post_id) ? get_the_post_thumbnail($post_id, $size, array('loading' => 'lazy', 'alt' => '')) : '<span class="xi-bx-thumb-empty">' . xi_icon('bag') . '</span>';
}

/* ---------- 商城区块 ---------- */

function xi_bx_render_product($a)
{
    if (!xi_bx_shop_ready()) {
        return xi_bx_notice(__('商城未开启', 'xi'));
    }
    $post = xi_bx_product($a['id']);
    if (!$post) {
        return xi_bx_notice(__('请选择要展示的商品', 'xi'));
    }
    $mini = $a['style'] === 'mini';
    $html = '<div class="xi-bx-product' . ($mini ? ' is-mini' : '') . '">';
    $html .= '<a class="xi-bx-product-img" href="' . esc_url(get_permalink($post)) . '">' . xi_bx_thumb($post->ID, $mini ? 'thumbnail' : 'medium') . '</a>';
    $html .= '<div class="xi-bx-product-body"><a class="xi-bx-product-title" href="' . esc_url(get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a>';
    if (!$mini && has_excerpt($post)) {
        $html .= '<p>' . esc_html(wp_trim_words(get_the_excerpt($post), 40)) . '</p>';
    }
    $html .= '<div class="xi-bx-product-foot"><span class="xi-bx-price">' . xi_bx_price_html($post) . '</span>';
    $html .= '<small>' . esc_html(sprintf(__('已售 %s', 'xi'), xi_number(xi_product_sales($post->ID)))) . '</small>';
    $html .= '<a class="xi-block-btn is-main" href="' . esc_url(xi_bx_buy_url($post)) . '">' . esc_html($a['btn'] !== '' ? $a['btn'] : __('立即购买', 'xi')) . '</a></div>';
    return $html . '</div></div>';
}

function xi_bx_render_products($a)
{
    if (!xi_bx_shop_ready()) {
        return xi_bx_notice(__('商城未开启', 'xi'));
    }
    $q = array(
        'post_type'           => 'xi_product',
        'posts_per_page'      => max(1, min(24, (int) $a['count'])),
        'ignore_sticky_posts' => true,
        'no_found_rows'       => true,
    );
    if (!empty($a['cat'])) {
        $q['tax_query'] = array(array('taxonomy' => 'product_cat', 'terms' => (int) $a['cat']));
    }
    if ($a['sort'] === 'sales') {
        $q['meta_key'] = 'xi_sales_total';
        $q['orderby']  = array('meta_value_num' => 'DESC', 'date' => 'DESC');
    } elseif ($a['sort'] === 'price_asc' || $a['sort'] === 'price_desc') {
        $q['meta_key'] = 'xi_price_sort';
        $q['orderby']  = array('meta_value_num' => $a['sort'] === 'price_asc' ? 'ASC' : 'DESC');
    }
    $posts = get_posts($q);
    if (!$posts) {
        return xi_bx_notice(__('该分类下还没有商品', 'xi'));
    }
    $cols = in_array((string) $a['cols'], array('2', '3', '4'), true) ? $a['cols'] : '4';
    $html = '<div class="xi-bx-pgrid is-cols-' . esc_attr($cols) . '">';
    foreach ($posts as $post) {
        $html .= '<a class="xi-bx-pcard" href="' . esc_url(get_permalink($post)) . '"><span class="xi-bx-pcard-img">' . xi_bx_thumb($post->ID) . '</span>';
        $html .= '<span class="xi-bx-pcard-title">' . esc_html(get_the_title($post)) . '</span><span class="xi-bx-price">' . xi_bx_price_html($post) . '</span></a>';
    }
    return $html . '</div>';
}

function xi_bx_render_buy($a)
{
    if (!xi_bx_shop_ready()) {
        return xi_bx_notice(__('商城未开启', 'xi'));
    }
    $post = xi_bx_product($a['id']);
    if (!$post) {
        return xi_bx_notice(__('请选择商品', 'xi'));
    }
    $price = '';
    if (!empty($a['price']) && ($p = xi_product_display_price($post->ID, get_current_user_id()))) {
        $price = '<em>' . esc_html(xi_price_text($p['price'], $p['type'])) . '</em>';
    }
    $style = in_array($a['style'], array('main', 'soft', 'line'), true) ? $a['style'] : 'main';
    return '<p class="xi-block-btns is-align-' . ($a['align'] === 'center' ? 'center' : 'left') . '"><a class="xi-block-btn xi-bx-buy is-' . $style . '" href="' . esc_url(xi_bx_buy_url($post)) . '">' . xi_icon('cart') . esc_html($a['text'] !== '' ? $a['text'] : __('立即购买', 'xi')) . $price . '</a></p>';
}

function xi_bx_render_pricing($a)
{
    $rows = xi_bx_lines($a['plans'], 5);
    if (!$rows) {
        return xi_bx_notice(__('请填写方案', 'xi'));
    }
    $html = '<div class="xi-bx-pricing is-cols-' . min(4, count($rows)) . '">';
    foreach ($rows as $r) {
        $hot   = $r[4] === '1';
        $html .= '<div class="xi-bx-plan' . ($hot ? ' is-hot' : '') . '">' . ($hot ? '<em>' . esc_html__('推荐', 'xi') . '</em>' : '');
        $html .= '<b class="xi-bx-plan-name">' . esc_html($r[0]) . '</b><div class="xi-bx-plan-price">' . esc_html($r[1]) . '</div><ul>';
        foreach (array_filter(array_map('trim', preg_split('/[;；]/u', $r[2]))) as $feat) {
            $html .= '<li>' . xi_icon('check') . esc_html($feat) . '</li>';
        }
        $html .= '</ul>';
        if ($r[3] !== '' && $r[3] !== 'https://') {
            $html .= '<a class="xi-block-btn ' . ($hot ? 'is-main' : 'is-soft') . '" href="' . esc_url($r[3]) . '">' . esc_html($a['btn'] !== '' ? $a['btn'] : __('选择方案', 'xi')) . '</a>';
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

function xi_bx_render_coupon($a)
{
    if ($a['code'] === '') {
        return xi_bx_notice(__('请填写优惠码', 'xi'));
    }
    $html = '<div class="xi-bx-coupon"><div class="xi-bx-coupon-amount">' . esc_html($a['amount']) . '</div>';
    $html .= '<div class="xi-bx-coupon-body"><b>' . esc_html($a['title']) . '</b><small>' . esc_html(trim($a['desc'] . ($a['expire'] !== '' ? ' · ' . sprintf(__('有效期至 %s', 'xi'), $a['expire']) : ''), ' ·')) . '</small></div>';
    $html .= '<button type="button" class="xi-bx-coupon-code" data-xi-copy="' . esc_attr($a['code']) . '"><code>' . esc_html($a['code']) . '</code><span>' . esc_html__('点击复制', 'xi') . '</span></button></div>';
    return $html;
}

function xi_bx_render_promo($a)
{
    if (!xi_bx_shop_ready() || !function_exists('xi_shop_promo') || !($promo = xi_shop_promo())) {
        return xi_bx_notice(__('商城当前没有进行中的满减活动，活动开启后这里会自动显示', 'xi'));
    }
    $html = '<div class="xi-bx-promo">' . xi_icon('gift') . '<div><b>' . esc_html($promo['name']) . '</b><span>' . esc_html(xi_shop_promo_text()) . '</span>';
    if ($promo['end']) {
        $html .= '<small data-xi-bx-countdown="' . (int) $promo['end'] . '" data-done="' . esc_attr__('活动已结束', 'xi') . '"></small>';
    }
    return $html . '</div><a class="xi-block-btn is-main" href="' . esc_url(xi_shop_url()) . '">' . esc_html($a['btn'] !== '' ? $a['btn'] : __('去逛逛', 'xi')) . '</a></div>';
}

function xi_bx_render_bought($a, $content)
{
    if (!xi_bx_shop_ready()) {
        return '';
    }
    // 前台编辑器保存的短代码跨段落，wpautop 后内容首尾会多出半截 <p> 标签
    $content = preg_replace(array('#^\s*</p>#i', '#<p>\s*$#i'), '', (string) $content);
    $post = xi_bx_product($a['id']);
    $uid  = get_current_user_id();
    // 编辑、管理员（含编辑器预览）和买过该商品的用户可见；不能按「是否 REST 请求」放行，否则 REST API 会把内容返回给游客
    if ($uid && (current_user_can('edit_others_posts') || ($post && xi_shop_user_bought($uid, $post->ID) > 0))) {
        return '<div class="xi-bx-bought is-open"><div class="xi-bx-bought-label">' . xi_icon('check') . esc_html__('已购买可见的内容', 'xi') . '</div>' . $content . '</div>';
    }
    $html = '<div class="xi-bx-bought"><span class="xi-bx-bought-icon">' . xi_icon('lock') . '</span><div><b>' . esc_html($a['tip'] !== '' ? $a['tip'] : __('购买该商品后可查看此内容', 'xi')) . '</b>';
    if ($post) {
        $html .= '<small>' . esc_html(get_the_title($post)) . '</small>';
    }
    $html .= '</div>';
    if (!$uid) {
        $html .= '<a class="xi-block-btn is-main" href="' . esc_url(wp_login_url(get_permalink())) . '" data-xi-sign="login">' . esc_html__('登录', 'xi') . '</a>';
    } elseif ($post) {
        $html .= '<a class="xi-block-btn is-main" href="' . esc_url(get_permalink($post)) . '">' . esc_html__('去购买', 'xi') . '</a>';
    }
    return $html . '</div>';
}

function xi_bx_render_shopcats($a)
{
    if (!xi_bx_shop_ready()) {
        return xi_bx_notice(__('商城未开启', 'xi'));
    }
    $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0));
    if (!$terms || is_wp_error($terms)) {
        return xi_bx_notice(__('还没有商品分类', 'xi'));
    }
    $html = '<nav class="xi-bx-cats"><a href="' . esc_url(xi_shop_url()) . '">' . xi_icon('bag') . esc_html__('全部商品', 'xi') . '</a>';
    foreach ($terms as $t) {
        $html .= '<a href="' . esc_url(get_term_link($t)) . '">' . esc_html($t->name) . (!empty($a['count']) ? '<em>' . (int) $t->count . '</em>' : '') . '</a>';
    }
    return $html . '</nav>';
}

/* ---------- 内容区块 ---------- */

function xi_bx_render_linkcard($a)
{
    $url = esc_url($a['url']);
    if (!$url) {
        return xi_bx_notice(__('请填写链接', 'xi'));
    }
    $host  = wp_parse_url($url, PHP_URL_HOST);
    $title = $a['title'] !== '' ? $a['title'] : $host;
    $img   = $a['image'] !== '' ? '<img src="' . esc_url($a['image']) . '" alt="" loading="lazy">' : '<img src="' . esc_url('https://' . $host . '/favicon.ico') . '" alt="" loading="lazy" onerror="this.replaceWith(document.createTextNode(\'🔗\'))">';
    return '<a class="xi-bx-link" href="' . $url . '"' . (!empty($a['blank']) ? ' target="_blank" rel="noopener"' : '') . '><span class="xi-bx-link-icon">' . $img . '</span><span class="xi-bx-link-body"><b>' . esc_html($title) . '</b><small>' . esc_html($a['desc'] !== '' ? $a['desc'] : $host) . '</small></span>' . xi_icon('arrow') . '</a>';
}

function xi_bx_render_postcard($a)
{
    $post = $a['id'] ? get_post((int) $a['id']) : null;
    if (!$post || $post->post_status !== 'publish' || !empty($post->post_password)) {
        return xi_bx_notice(__('请填写已发布文章的 ID', 'xi'));
    }
    $thumb = has_post_thumbnail($post) ? get_the_post_thumbnail($post, 'medium', array('loading' => 'lazy', 'alt' => '')) : '';
    $html  = '<a class="xi-bx-post' . ($thumb ? '' : ' no-thumb') . '" href="' . esc_url(get_permalink($post)) . '">' . ($thumb ? '<span class="xi-bx-post-img">' . $thumb . '</span>' : '');
    $html .= '<span class="xi-bx-post-body"><b>' . esc_html(get_the_title($post)) . '</b><small>' . esc_html(wp_trim_words(function_exists('xi_strip_hidden') ? wp_strip_all_tags(xi_strip_hidden(strip_shortcodes($post->post_content))) : get_the_excerpt($post), 50)) . '</small>';
    $html .= '<em>' . esc_html(get_the_date('Y-m-d', $post)) . '</em></span></a>';
    return $html;
}

function xi_bx_render_steps($a)
{
    $rows = xi_bx_lines($a['items'], 2);
    if (!$rows) {
        return xi_bx_notice(__('请填写步骤', 'xi'));
    }
    $html = '<ol class="xi-bx-steps">';
    foreach ($rows as $r) {
        $html .= '<li><b>' . esc_html($r[0]) . '</b>' . ($r[1] !== '' ? '<p>' . esc_html($r[1]) . '</p>' : '') . '</li>';
    }
    return $html . '</ol>';
}

function xi_bx_render_timeline($a)
{
    $rows = xi_bx_lines($a['items'], 3);
    if (!$rows) {
        return xi_bx_notice(__('请填写时间轴节点', 'xi'));
    }
    $html = '<ul class="xi-bx-timeline">';
    foreach ($rows as $r) {
        $html .= '<li><time>' . esc_html($r[0]) . '</time><b>' . esc_html($r[1]) . '</b>' . ($r[2] !== '' ? '<p>' . esc_html($r[2]) . '</p>' : '') . '</li>';
    }
    return $html . '</ul>';
}

function xi_bx_render_progress($a)
{
    $rows = xi_bx_lines($a['items'], 2);
    if (!$rows) {
        return xi_bx_notice(__('请填写进度', 'xi'));
    }
    $color = sanitize_hex_color($a['color']);
    $html  = '<div class="xi-bx-progress"' . ($color ? ' style="--bx:' . esc_attr($color) . '"' : '') . '>';
    foreach ($rows as $r) {
        $pct   = max(0, min(100, (float) $r[1]));
        $html .= '<div class="xi-bx-progress-row"><span><b>' . esc_html($r[0]) . '</b><em>' . esc_html(rtrim(rtrim(number_format($pct, 1), '0'), '.')) . '%</em></span><i><s style="width:' . esc_attr($pct) . '%"></s></i></div>';
    }
    return $html . '</div>';
}

function xi_bx_render_countdown($a)
{
    $ts = $a['time'] ? strtotime(str_replace('T', ' ', $a['time'])) : 0;
    if (!$ts) {
        return xi_bx_notice(__('请设置目标时间', 'xi'));
    }
    // 后台填写的是站点时区时间，换算成真实时间戳
    $ts  -= (int) (get_option('gmt_offset') * HOUR_IN_SECONDS);
    $html = '<div class="xi-bx-countdown" data-xi-bx-countdown="' . (int) $ts . '" data-done="' . esc_attr($a['done']) . '">';
    $html .= '<b>' . esc_html($a['title']) . '</b><div class="xi-bx-countdown-nums">';
    // 服务端先算好初始值：编辑器预览、脚本加载前都显示正确的剩余时间
    $left = max(0, $ts - time());
    $init = array('d' => floor($left / DAY_IN_SECONDS), 'h' => floor($left % DAY_IN_SECONDS / HOUR_IN_SECONDS), 'm' => floor($left % HOUR_IN_SECONDS / MINUTE_IN_SECONDS), 's' => $left % MINUTE_IN_SECONDS);
    foreach (array('d' => __('天', 'xi'), 'h' => __('时', 'xi'), 'm' => __('分', 'xi'), 's' => __('秒', 'xi')) as $k => $label) {
        $html .= '<span><i data-k="' . $k . '">' . sprintf('%02d', $init[$k]) . '</i><small>' . esc_html($label) . '</small></span>';
    }
    return $html . '</div><p class="xi-bx-countdown-done" hidden>' . esc_html($a['done']) . '</p></div>';
}

function xi_bx_render_video($a)
{
    $url = trim((string) $a['url']);
    if ($url === '') {
        return xi_bx_notice(__('请填写视频地址', 'xi'));
    }
    $iframe = '';
    if (preg_match('/(BV[0-9A-Za-z]{10})/', $url, $m)) {
        $page   = preg_match('/[?&]p=(\d+)/', $url, $pm) ? (int) $pm[1] : 1;
        $iframe = 'https://player.bilibili.com/player.html?bvid=' . $m[1] . '&page=' . $page . '&high_quality=1&autoplay=0';
    } elseif (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})#', $url, $m)) {
        $iframe = 'https://www.youtube-nocookie.com/embed/' . $m[1];
    }
    $html = '<figure class="xi-bx-video"><div class="xi-bx-video-box">';
    if ($iframe) {
        $html .= '<iframe src="' . esc_url($iframe) . '" loading="lazy" allowfullscreen allow="fullscreen; picture-in-picture" referrerpolicy="no-referrer-when-downgrade" title="' . esc_attr($a['title']) . '"></iframe>';
    } else {
        $html .= '<video controls preload="metadata" playsinline src="' . esc_url($url) . '"' . ($a['poster'] !== '' ? ' poster="' . esc_url($a['poster']) . '"' : '') . '></video>';
    }
    $html .= '</div>' . ($a['title'] !== '' ? '<figcaption>' . esc_html($a['title']) . '</figcaption>' : '');
    return $html . '</figure>';
}

function xi_bx_render_audio($a)
{
    if (trim((string) $a['url']) === '') {
        return xi_bx_notice(__('请填写音频地址', 'xi'));
    }
    $cover = $a['cover'] !== '' ? '<img src="' . esc_url($a['cover']) . '" alt="" loading="lazy">' : xi_icon('music');
    return '<div class="xi-bx-audio"><span class="xi-bx-audio-cover">' . $cover . '</span><div class="xi-bx-audio-body"><b>' . esc_html($a['title'] !== '' ? $a['title'] : __('未命名音频', 'xi')) . '</b>' . ($a['artist'] !== '' ? '<small>' . esc_html($a['artist']) . '</small>' : '') . '<audio controls preload="none" src="' . esc_url($a['url']) . '"></audio></div></div>';
}

function xi_bx_render_chat($a)
{
    $html = '';
    $me   = trim((string) $a['me']);
    foreach (preg_split('/\r\n|\r|\n/', (string) $a['items']) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = preg_split('/[:：]/u', $line, 2);
        $name  = count($parts) === 2 ? trim($parts[0]) : '';
        $text  = count($parts) === 2 ? trim($parts[1]) : $line;
        $right = $me !== '' && $name === $me;
        $html .= '<div class="xi-bx-chat-row' . ($right ? ' is-me' : '') . '"><span class="xi-bx-chat-avatar">' . esc_html(mb_substr($name !== '' ? $name : '?', 0, 1)) . '</span><div>' . ($name !== '' ? '<small>' . esc_html($name) . '</small>' : '') . '<p>' . esc_html($text) . '</p></div></div>';
    }
    return $html ? '<div class="xi-bx-chat">' . $html . '</div>' : xi_bx_notice(__('请填写对话', 'xi'));
}

function xi_bx_render_checklist($a)
{
    $items = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) $a['items']) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $done = (bool) preg_match('/^(\[x\]|x|✓|√)\s+/iu', $line);
        $text = preg_replace('/^(\[x\]|\[ \]|x|✓|√)\s+/iu', '', $line);
        $items[] = '<li class="' . ($done ? 'is-done' : '') . '"><span class="xi-bx-check">' . ($done ? xi_icon('check') : '') . '</span>' . esc_html($text) . '</li>';
    }
    if (!$items) {
        return xi_bx_notice(__('请填写清单', 'xi'));
    }
    $done = count(array_filter($items, function ($i) {
        return strpos($i, 'is-done') !== false;
    }));
    return '<div class="xi-bx-checklist">' . ($a['title'] !== '' ? '<b>' . esc_html($a['title']) . '<small>' . $done . ' / ' . count($items) . '</small></b>' : '') . '<ul>' . implode('', $items) . '</ul></div>';
}

function xi_bx_render_stats($a)
{
    $rows = xi_bx_lines($a['items'], 2);
    if (!$rows) {
        return xi_bx_notice(__('请填写数据', 'xi'));
    }
    $html = '<div class="xi-bx-stats is-cols-' . min(4, count($rows)) . '">';
    foreach ($rows as $r) {
        $html .= '<div><b>' . esc_html($r[0]) . '</b><span>' . esc_html($r[1]) . '</span></div>';
    }
    return $html . '</div>';
}

function xi_bx_render_faq($a)
{
    $rows = xi_bx_lines($a['items'], 2);
    if (!$rows) {
        return xi_bx_notice(__('请填写问答', 'xi'));
    }
    $html   = '<div class="xi-bx-faq">';
    $schema = array();
    foreach ($rows as $r) {
        $html    .= '<details><summary>' . xi_icon('chevron') . esc_html($r[0]) . '</summary><p>' . esc_html($r[1]) . '</p></details>';
        $schema[] = array('@type' => 'Question', 'name' => $r[0], 'acceptedAnswer' => array('@type' => 'Answer', 'text' => $r[1]));
    }
    $html .= '</div>';
    if (!(defined('REST_REQUEST') && REST_REQUEST)) {
        $html .= '<script type="application/ld+json">' . wp_json_encode(array('@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
    }
    return $html;
}

function xi_bx_render_author($a)
{
    $uid  = (int) $a['user'] ? (int) $a['user'] : (int) get_post_field('post_author', get_the_ID());
    $user = $uid ? get_userdata($uid) : null;
    if (!$user) {
        return xi_bx_notice(__('请选择用户', 'xi'));
    }
    $count = (int) count_user_posts($uid, 'post', true);
    $html  = '<div class="xi-bx-author">' . get_avatar($uid, 64) . '<div><b>' . esc_html($user->display_name) . '</b>';
    $html .= '<p>' . esc_html($user->description !== '' ? wp_trim_words($user->description, 40) : __('这个人很懒，什么都没写', 'xi')) . '</p>';
    $html .= '<small>' . esc_html(sprintf(__('%d 篇文章', 'xi'), $count)) . '</small></div>';
    return $html . '<a class="xi-block-btn is-soft" href="' . esc_url(get_author_posts_url($uid)) . '">' . esc_html__('主页', 'xi') . '</a></div>';
}

/* ---------- 前台编辑器：后台区块转短代码 ---------- */

/**
 * 扩展区块转成前台编辑器里的短代码（编辑器会显示为卡片）
 *
 * @return string|null 不是扩展区块时返回 null
 */
function xi_bx_block_for_writer($block)
{
    $name = substr((string) $block['blockName'], 3);
    $defs = xi_bx_defs();
    if (strpos((string) $block['blockName'], 'xi/') !== 0 || !isset($defs[$name])) {
        return null;
    }
    $attrs = (array) $block['attrs'];
    $pairs = array();
    foreach ($defs[$name]['fields'] as $key => $f) {
        if (!isset($attrs[$key])) {
            continue;
        }
        $v = $attrs[$key];
        if ($f['type'] === 'toggle') {
            $v = $v ? '1' : '0';
        }
        $pairs[$key] = str_replace(array("\r\n", "\n", "\r"), '{br}', (string) $v);
    }
    $open = '<p>' . xi_shortcode_text('xi_' . $name, $pairs) . '</p>';
    if (empty($defs[$name]['inner'])) {
        return $open;
    }
    $inner = '';
    foreach ((array) $block['innerBlocks'] as $child) {
        $inner .= xi_block_for_writer($child);
    }
    return $open . $inner . '<p>[/xi_' . $name . ']</p>';
}
