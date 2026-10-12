<?php
/**
 * 编辑器区块：提醒框、标题、引言、高亮代码、按钮、文件下载、折叠框、隐藏内容、选项卡
 *
 * 全部为动态区块：编辑器只保存属性与内部区块，前台 HTML 由这里渲染，
 * 以后修改样式或结构不会让旧文章出现「区块验证失败」。
 *
 * 前后台同步：前台编辑器（assets/js/editor.js）插入的 HTML 与这里渲染的结构一致，
 * 共用 blocks.css 样式；需要按登录状态输出的文件下载、隐藏内容在前台编辑器里以短代码保存。
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_blocks_category($categories)
{
    array_unshift($categories, array('slug' => 'xi', 'title' => __('曦主题', 'xi'), 'icon' => null));
    return $categories;
}
add_filter(version_compare($GLOBALS['wp_version'], '5.8', '>=') ? 'block_categories_all' : 'block_categories', 'xi_blocks_category');

function xi_blocks_register()
{
    if (!function_exists('register_block_type')) {
        return;
    }
    $uri = get_template_directory_uri();

    wp_register_style('xi-blocks', $uri . '/assets/css/blocks.css', array(), XI_VERSION);
    // 编辑器里没有前台的 :root 变量，按主题设置补上主题色
    wp_add_inline_style('xi-blocks', '.editor-styles-wrapper{--xi-main:' . xi_theme_color() . '}');
    wp_register_style('xi-blocks-editor', $uri . '/assets/css/blocks-editor.css', array('xi-blocks'), XI_VERSION);
    wp_register_script('xi-blocks-editor', $uri . '/assets/js/blocks-editor.js', array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'), XI_VERSION, true);
    wp_localize_script('xi-blocks-editor', 'xiBlocks', array(
        'langs'  => xi_code_langs(),
        'roles'  => xi_hide_role_options(),
        'points' => function_exists('xi_points_name') ? xi_points_name() : '积分',
        // 等级 => 名称，键是会员等级
        'vips'   => function_exists('xi_vip_levels') ? array_map(function ($l) {
            return isset($l['name']) ? $l['name'] : '';
        }, (array) xi_vip_levels()) : array(),
    ));
    wp_register_script('xi-blocks-view', $uri . '/assets/js/blocks.js', array(), XI_VERSION, true);

    $common = array(
        'api_version'   => 3,
        'category'      => 'xi',
        'editor_script' => 'xi-blocks-editor',
        'editor_style'  => 'xi-blocks-editor',
        'style'         => 'xi-blocks',
    );
    $str  = function ($default = '') {
        return array('type' => 'string', 'default' => $default);
    };
    $bool = function ($default = false) {
        return array('type' => 'boolean', 'default' => $default);
    };
    $int  = function ($default = 0) {
        return array('type' => 'number', 'default' => $default);
    };

    $blocks = array(
        'alert'    => array(
            'title'      => __('提醒框', 'xi'),
            'attributes' => array('type' => $str('info'), 'title' => $str()),
        ),
        'heading'  => array(
            'title'      => __('标题', 'xi'),
            'attributes' => array('content' => $str(), 'level' => $int(2), 'style' => $str('line'), 'align' => $str('left')),
        ),
        'download' => array(
            'title'       => __('文件下载', 'xi'),
            'view_script' => 'xi-blocks-view',
            'attributes'  => array(
                'name'    => $str(),
                'desc'    => $str(),
                'version' => $str(),
                'size'    => $str(),
                'updated' => $str(),
                'login'   => $bool(),
                'links'   => array(
                    'type'    => 'array',
                    'default' => array(array('label' => '立即下载', 'url' => '', 'code' => '')),
                    'items'   => array('type' => 'object'),
                ),
            ),
        ),
        'quote'    => array(
            'title'      => __('引言', 'xi'),
            'attributes' => array('text' => $str(), 'cite' => $str(), 'style' => $str('default')),
        ),
        'code'     => array(
            'title'      => __('高亮代码', 'xi'),
            'attributes' => array('code' => $str(), 'lang' => $str(), 'title' => $str()),
        ),
        'button'   => array(
            'title'      => __('按钮', 'xi'),
            'attributes' => array('text' => $str(), 'url' => $str(), 'style' => $str('main'), 'align' => $str('left'), 'blank' => $bool()),
        ),
        'collapse' => array(
            'title'      => __('折叠框', 'xi'),
            'attributes' => array('title' => $str(), 'open' => $bool()),
        ),
        'hide'     => array(
            'title'      => __('隐藏内容', 'xi'),
            'attributes' => array('type' => $str('login'), 'points' => $int(10), 'vip' => $int(1), 'password' => $str(), 'roles' => $str(), 'time' => $str(), 'tip' => $str()),
        ),
        'tabs'     => array(
            'title'       => __('选项卡', 'xi'),
            'view_script' => 'xi-blocks-view',
            'attributes'  => array(),
        ),
        'tab'      => array(
            'title'      => __('选项卡页', 'xi'),
            'parent'     => array('xi/tabs'),
            'attributes' => array('title' => $str()),
        ),
    );

    foreach ($blocks as $name => $args) {
        $args['render_callback'] = 'xi_block_render_' . $name;
        register_block_type('xi/' . $name, array_merge($common, $args));
    }
}
add_action('init', 'xi_blocks_register');

/* ---------- 渲染 ---------- */

function xi_block_render_alert($a, $content)
{
    $type = in_array($a['type'], array('info', 'success', 'warning', 'danger', 'note'), true) ? $a['type'] : 'info';
    $html = '<div class="xi-block-alert is-' . $type . '"><div class="xi-block-alert-body">';
    if ($a['title'] !== '') {
        $html .= '<p class="xi-block-alert-title">' . wp_kses_post($a['title']) . '</p>';
    }
    return $html . $content . '</div></div>';
}

function xi_block_render_heading($a)
{
    if (trim(wp_strip_all_tags($a['content'])) === '') {
        return '';
    }
    $level = min(4, max(2, (int) $a['level']));
    $style = in_array($a['style'], array('line', 'under', 'pill', 'center', 'hash', 'num'), true) ? $a['style'] : 'line';
    $align = $style === 'center' ? 'center' : (in_array($a['align'], array('left', 'center'), true) ? $a['align'] : 'left');
    return sprintf('<h%1$d class="xi-block-heading is-%2$s is-align-%3$s"><span>%4$s</span></h%1$d>', $level, $style, $align, wp_kses_post($a['content']));
}

/**
 * 按文件名或链接后缀判断类型，用于下载卡片左侧的图标颜色与文字
 */
function xi_file_kind($name, $url)
{
    $ext = '';
    foreach (array($name, (string) wp_parse_url($url, PHP_URL_PATH)) as $s) {
        if (preg_match('/\.([a-z0-9]{1,5})$/i', trim($s), $m)) {
            $ext = strtolower($m[1]);
            break;
        }
    }
    $kinds = array(
        'zip'   => array('zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'),
        'app'   => array('exe', 'msi', 'apk', 'ipa', 'dmg', 'pkg', 'deb', 'rpm', 'appimage'),
        'doc'   => array('pdf', 'doc', 'docx', 'txt', 'md', 'epub', 'ppt', 'pptx', 'xls', 'xlsx', 'csv'),
        'media' => array('mp3', 'flac', 'wav', 'mp4', 'mkv', 'mov', 'avi', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'psd', 'svg'),
        'code'  => array('js', 'css', 'php', 'py', 'json', 'html', 'sql', 'sh', 'bat', 'ps1'),
    );
    foreach ($kinds as $kind => $exts) {
        if (in_array($ext, $exts, true)) {
            return array($kind, strtoupper($ext));
        }
    }
    return array('file', $ext ? strtoupper($ext) : 'FILE');
}

function xi_block_render_download($a)
{
    $links = array_values(array_filter((array) $a['links'], function ($l) {
        return is_array($l) && !empty($l['url']);
    }));
    $name = $a['name'] !== '' ? $a['name'] : __('附件下载', 'xi');
    list($kind, $label) = xi_file_kind($a['name'], $links ? $links[0]['url'] : '');

    $meta = '';
    foreach (array('version' => __('版本', 'xi'), 'size' => __('大小', 'xi'), 'updated' => __('更新', 'xi')) as $key => $text) {
        if ($a[$key] !== '') {
            $meta .= '<span>' . esc_html($text) . ' <b>' . esc_html($a[$key]) . '</b></span>';
        }
    }

    $html  = '<div class="xi-block-down">';
    $html .= '<div class="xi-block-down-head"><span class="xi-block-down-icon is-' . $kind . '">' . xi_icon('download') . '<small>' . esc_html($label) . '</small></span>';
    $html .= '<div class="xi-block-down-info"><b class="xi-block-down-name">' . esc_html($name) . '</b>';
    if ($meta) {
        $html .= '<p class="xi-block-down-meta">' . $meta . '</p>';
    }
    if ($a['desc'] !== '') {
        $html .= '<p class="xi-block-down-desc">' . esc_html($a['desc']) . '</p>';
    }
    $html .= '</div></div><div class="xi-block-down-links">';

    if (!$links) {
        $html .= '<span class="xi-block-down-empty">' . esc_html__('暂无下载地址', 'xi') . '</span>';
    } elseif ($a['login'] && !is_user_logged_in()) {
        $html .= '<span class="xi-block-down-empty">' . xi_icon('lock') . esc_html__('登录后可下载', 'xi') . '</span>';
        $html .= '<a class="xi-btn xi-btn-accent xi-btn-sm" href="' . esc_url(wp_login_url(get_permalink())) . '" data-xi-sign="login">' . xi_icon('login') . esc_html__('登录', 'xi') . '</a>';
    } else {
        foreach ($links as $l) {
            $code   = isset($l['code']) ? trim($l['code']) : '';
            $html  .= '<span class="xi-block-down-item">';
            $html  .= '<a class="xi-btn xi-btn-accent xi-btn-sm" href="' . esc_url($l['url']) . '" target="_blank" rel="noopener nofollow"' . ($code ? ' data-xi-copy="' . esc_attr($code) . '"' : '') . '>' . xi_icon('download') . esc_html(!empty($l['label']) ? $l['label'] : __('立即下载', 'xi')) . '</a>';
            if ($code) {
                $html .= '<button type="button" class="xi-block-down-code" data-xi-copy="' . esc_attr($code) . '" title="' . esc_attr__('点击复制', 'xi') . '">' . esc_html__('提取码', 'xi') . ' <b>' . esc_html($code) . '</b>' . xi_icon('copy') . '</button>';
            }
            $html .= '</span>';
        }
    }
    return $html . '</div></div>';
}

function xi_block_render_collapse($a, $content)
{
    $title = $a['title'] !== '' ? wp_kses_post($a['title']) : esc_html__('展开查看', 'xi');
    return '<details class="xi-block-collapse"' . ($a['open'] ? ' open' : '') . '><summary>' . $title . '</summary><div class="xi-block-collapse-body">' . $content . '</div></details>';
}

function xi_block_render_hide($a, $content)
{
    if (is_feed() || !function_exists('xi_hide_output')) {
        return '';
    }
    $type = in_array($a['type'], array('login', 'reply', 'points', 'vip', 'password', 'role', 'time'), true) ? $a['type'] : 'login';
    return xi_hide_output(array(
        'type'     => $type,
        'points'   => (int) $a['points'],
        'vip'      => (int) $a['vip'],
        'password' => (string) $a['password'],
        'roles'    => (string) $a['roles'],
        'time'     => (string) $a['time'],
        'tip'      => (string) $a['tip'],
    ), $content);
}

function xi_block_render_tab($a, $content)
{
    return $content;
}

function xi_block_render_tabs($a, $content, $block = null)
{
    static $n = 0;
    if (!$block || empty($block->inner_blocks)) {
        return $content;
    }
    $n++;
    $nav    = '';
    $panels = '';
    $i      = 0;
    foreach ($block->inner_blocks as $tab) {
        if ($tab->name !== 'xi/tab') {
            continue;
        }
        $id     = 'xi-tab-' . $n . '-' . $i;
        $title  = isset($tab->attributes['title']) && $tab->attributes['title'] !== '' ? wp_kses_post($tab->attributes['title']) : sprintf(esc_html__('选项 %d', 'xi'), $i + 1);
        $nav   .= '<button type="button" role="tab" id="' . $id . '-t" aria-controls="' . $id . '" aria-selected="' . ($i ? 'false' : 'true') . '"' . ($i ? ' tabindex="-1"' : '') . '>' . $title . '</button>';
        $panels .= '<div class="xi-block-tabs-panel" role="tabpanel" id="' . $id . '" aria-labelledby="' . $id . '-t"' . ($i ? ' hidden' : '') . '>' . $tab->render() . '</div>';
        $i++;
    }
    return '<div class="xi-block-tabs"><div class="xi-block-tabs-nav" role="tablist">' . $nav . '</div>' . $panels . '</div>';
}

/* ---------- 引言、高亮代码、按钮 ---------- */

/**
 * 高亮代码可选语言：值为 highlight.js 的语言名，留空自动识别
 */
function xi_code_langs()
{
    return array(
        ''           => __('自动识别', 'xi'),
        'plaintext'  => __('纯文本', 'xi'),
        'html'       => 'HTML',
        'css'        => 'CSS',
        'scss'       => 'SCSS',
        'javascript' => 'JavaScript',
        'typescript' => 'TypeScript',
        'json'       => 'JSON',
        'php'        => 'PHP',
        'python'     => 'Python',
        'java'       => 'Java',
        'kotlin'     => 'Kotlin',
        'go'         => 'Go',
        'rust'       => 'Rust',
        'c'          => 'C',
        'cpp'        => 'C++',
        'csharp'     => 'C#',
        'swift'      => 'Swift',
        'ruby'       => 'Ruby',
        'lua'        => 'Lua',
        'sql'        => 'SQL',
        'bash'       => 'Shell',
        'yaml'       => 'YAML',
        'ini'        => 'INI',
        'xml'        => 'XML',
        'markdown'   => 'Markdown',
        'diff'       => 'Diff',
    );
}

function xi_block_render_quote($a)
{
    if (trim(wp_strip_all_tags($a['text'])) === '') {
        return '';
    }
    $style = in_array($a['style'], array('default', 'card', 'center'), true) ? $a['style'] : 'default';
    $html  = '<blockquote class="xi-quote is-' . $style . '"><p>' . wp_kses_post($a['text']) . '</p>';
    if (trim($a['cite']) !== '') {
        $html .= '<cite>' . esc_html($a['cite']) . '</cite>';
    }
    return $html . '</blockquote>';
}

function xi_block_render_code($a)
{
    if (trim($a['code']) === '') {
        return '';
    }
    $lang = preg_replace('/[^a-z0-9+#-]/', '', strtolower((string) $a['lang']));
    $attr = ($lang ? ' data-lang="' . esc_attr($lang) . '"' : '') . (trim($a['title']) !== '' ? ' data-title="' . esc_attr($a['title']) . '"' : '');
    return '<pre class="xi-code"' . $attr . '><code' . ($lang ? ' class="language-' . esc_attr($lang) . '"' : '') . '>' . esc_html($a['code']) . '</code></pre>';
}

function xi_block_render_button($a)
{
    if (trim($a['text']) === '' || trim($a['url']) === '') {
        return '';
    }
    $style = in_array($a['style'], array('main', 'line', 'soft'), true) ? $a['style'] : 'main';
    $align = in_array($a['align'], array('left', 'center'), true) ? $a['align'] : 'left';
    return '<p class="xi-block-btns is-align-' . $align . '"><a class="xi-block-btn is-' . $style . '" href="' . esc_url($a['url']) . '"' . ($a['blank'] ? ' target="_blank" rel="noopener"' : '') . '>' . esc_html($a['text']) . '</a></p>';
}

/* ---------- 文件下载短代码（前台编辑器插入，与区块共用渲染） ---------- */

/**
 * [xi_download name="" url="" code="" label="" size="" version="" desc="" login="1"]
 */
function xi_download_shortcode($atts)
{
    $a = shortcode_atts(array(
        'name'    => '',
        'url'     => '',
        'code'    => '',
        'label'   => '',
        'size'    => '',
        'version' => '',
        'updated' => '',
        'desc'    => '',
        'login'   => '',
    ), $atts, 'xi_download');
    wp_enqueue_script('xi-blocks-view');
    return xi_block_render_download(array(
        'name'    => sanitize_text_field($a['name']),
        'desc'    => sanitize_text_field($a['desc']),
        'version' => sanitize_text_field($a['version']),
        'size'    => sanitize_text_field($a['size']),
        'updated' => sanitize_text_field($a['updated']),
        'login'   => in_array(strtolower((string) $a['login']), array('1', 'true', 'yes'), true),
        'links'   => array(array('label' => sanitize_text_field($a['label']), 'url' => $a['url'], 'code' => sanitize_text_field($a['code']))),
    ));
}
add_shortcode('xi_download', 'xi_download_shortcode');

/* ---------- 前台：样式与代码高亮 ---------- */

function xi_blocks_front_assets()
{
    // 前台编辑器插入的内容不是区块，也要有区块样式
    wp_enqueue_style('xi-blocks');
    if (!is_singular() && !(function_exists('xi_is_write_page') && xi_is_write_page()) && !(function_exists('xi_is_bbs_new') && xi_is_bbs_new())) {
        return;
    }
    $uri = get_template_directory_uri();
    wp_enqueue_script('xi-code', $uri . '/assets/js/code.js', array(), XI_VERSION, true);
    wp_localize_script('xi-code', 'xiCode', array(
        'hljs'  => $uri . '/assets/vendor/highlight/highlight.min.js?ver=11.11.1',
        'copy'  => (bool) _xz('code_copy_s', true),
        'langs' => array_filter(xi_code_langs(), function ($k) {
            return $k !== '';
        }, ARRAY_FILTER_USE_KEY),
    ));
}
add_action('wp_enqueue_scripts', 'xi_blocks_front_assets', 20);

/**
 * 前台编辑器：代码语言列表
 */
function xi_blocks_writer_langs()
{
    if (wp_script_is('xi-editor', 'enqueued')) {
        wp_add_inline_script('xi-editor', 'window.xiCodeLangs=' . wp_json_encode(xi_code_langs(), JSON_UNESCAPED_UNICODE) . ';window.xiHideRoles=' . wp_json_encode(xi_hide_role_options(), JSON_UNESCAPED_UNICODE) . ';', 'before');
    }
}
add_action('wp_enqueue_scripts', 'xi_blocks_writer_langs', 30);

/**
 * 在前台编辑器里打开后台写的文章：曦主题区块转成与前台编辑器相同的 HTML / 短代码，
 * 其他区块原样保留（区块注释在编辑器里不可见，保存时原样提交）
 */
function xi_blocks_for_writer($content)
{
    if (!function_exists('parse_blocks') || !has_blocks($content)) {
        return $content;
    }
    $out = '';
    foreach (parse_blocks($content) as $block) {
        $out .= xi_block_for_writer($block);
    }
    return $out;
}

function xi_block_for_writer($block)
{
    $name = (string) $block['blockName'];
    if ($name === '') {
        return $block['innerHTML'];
    }
    if (strpos($name, 'xi/') !== 0 || $name === 'xi/tabs' || $name === 'xi/tab') {
        return serialize_block($block);
    }
    $bx = function_exists('xi_bx_block_for_writer') ? xi_bx_block_for_writer($block) : null;
    if ($bx !== null) {
        return $bx;
    }
    $attrs = (array) $block['attrs'];
    if ($name === 'xi/download') {
        $link = !empty($attrs['links'][0]) && is_array($attrs['links'][0]) ? $attrs['links'][0] : array();
        $pairs = array(
            'name'    => isset($attrs['name']) ? $attrs['name'] : '',
            'url'     => isset($link['url']) ? $link['url'] : '',
            'code'    => isset($link['code']) ? $link['code'] : '',
            'label'   => isset($link['label']) ? $link['label'] : '',
            'size'    => isset($attrs['size']) ? $attrs['size'] : '',
            'version' => isset($attrs['version']) ? $attrs['version'] : '',
            'desc'    => isset($attrs['desc']) ? $attrs['desc'] : '',
            'login'   => !empty($attrs['login']) ? '1' : '',
        );
        return '<p>' . xi_shortcode_text('xi_download', $pairs) . '</p>';
    }
    if ($name === 'xi/hide') {
        $inner = '';
        foreach ((array) $block['innerBlocks'] as $child) {
            $inner .= xi_block_for_writer($child);
        }
        $pairs = array('type' => isset($attrs['type']) ? $attrs['type'] : 'login');
        if ($pairs['type'] === 'points') {
            $pairs['points'] = isset($attrs['points']) ? (int) $attrs['points'] : 10;
        } elseif ($pairs['type'] === 'vip') {
            $pairs['vip'] = isset($attrs['vip']) ? (int) $attrs['vip'] : 1;
        }
        foreach (array('password', 'roles', 'time', 'tip') as $key) {
            if (!empty($attrs[$key])) {
                $pairs[$key] = $attrs[$key];
            }
        }
        return '<p>' . xi_shortcode_text('xi_hide', $pairs) . '</p>' . $inner . '<p>[/xi_hide]</p>';
    }
    return render_block($block);
}

function xi_shortcode_text($tag, $pairs)
{
    $text = '[' . $tag;
    foreach ($pairs as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $text .= ' ' . $key . '="' . str_replace(array('"', '[', ']'), array('”', '【', '】'), (string) $value) . '"';
    }
    return esc_html($text . ']');
}

/**
 * 「指定用户组可见」可选的用户组
 */
function xi_hide_role_options()
{
    $roles = function_exists('xi_role_options') ? xi_role_options() : array();
    $roles = array_merge(array('administrator' => __('管理员', 'xi')), $roles);
    if (isset($roles['xi_vip'])) {
        $roles['xi_vip'] = __('VIP 会员', 'xi');
    }
    return $roles;
}
