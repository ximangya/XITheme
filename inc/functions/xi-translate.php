<?php
/**
 * 多语言翻译：前台整页机翻（默认微软 Edge 免费翻译接口，浏览器端直接请求，不经过服务器）
 * 导航栏明暗切换左侧的语言按钮；后台「全局&功能 → 多语言翻译」增删可选语言
 * 前端脚本 assets/js/translate.js，配置 window.xiTranslate
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 微软翻译支持的常用语言：代码 => 该语言自己的名称
 */
function xi_translate_catalog()
{
    return array(
        'zh-Hans' => '简体中文',
        'zh-Hant' => '繁體中文',
        'yue'     => '粵語',
        'lzh'     => '文言文',
        'en'      => 'English',
        'ja'      => '日本語',
        'ko'      => '한국어',
        'fr'      => 'Français',
        'de'      => 'Deutsch',
        'es'      => 'Español',
        'pt'      => 'Português',
        'it'      => 'Italiano',
        'ru'      => 'Русский',
        'uk'      => 'Українська',
        'pl'      => 'Polski',
        'nl'      => 'Nederlands',
        'sv'      => 'Svenska',
        'tr'      => 'Türkçe',
        'ar'      => 'العربية',
        'fa'      => 'فارسی',
        'he'      => 'עברית',
        'hi'      => 'हिन्दी',
        'bn'      => 'বাংলা',
        'th'      => 'ไทย',
        'vi'      => 'Tiếng Việt',
        'id'      => 'Bahasa Indonesia',
        'ms'      => 'Bahasa Melayu',
        'fil'     => 'Filipino',
        'mn-Cyrl' => 'Монгол',
        'bo'      => 'བོད་ཡིག',
        'ug'      => 'ئۇيغۇرچە',
    );
}

/**
 * 后台下拉框用：「English（en）」
 */
function xi_translate_catalog_options()
{
    $options = array();
    foreach (xi_translate_catalog() as $code => $name) {
        $options[$code] = $name . '（' . $code . '）';
    }
    return $options;
}

function xi_translate_default_langs()
{
    return array(
        array('code' => 'en', 'name' => ''),
        array('code' => 'zh-Hant', 'name' => ''),
        array('code' => 'ja', 'name' => ''),
        array('code' => 'ko', 'name' => ''),
    );
}

function xi_translate_enabled()
{
    return (bool) _xz('translate_s', false) && count(xi_translate_langs()) > 1;
}

function xi_translate_source()
{
    $source = (string) _xz('translate_source', 'zh-Hans');
    return isset(xi_translate_catalog()[$source]) ? $source : 'zh-Hans';
}

/**
 * 前台可选语言：网站原文语言固定排第一，其后为后台添加的语言（去重）
 *
 * @return array[] 每项 code、name
 */
function xi_translate_langs()
{
    static $langs = null;
    if ($langs !== null) {
        return $langs;
    }
    $catalog = xi_translate_catalog();
    $source  = xi_translate_source();
    $langs   = array(array('code' => $source, 'name' => $catalog[$source]));
    $seen    = array($source => true);
    foreach ((array) _xz('translate_langs', xi_translate_default_langs()) as $row) {
        $code = is_array($row) && isset($row['code']) ? (string) $row['code'] : '';
        if (!isset($catalog[$code]) || isset($seen[$code])) {
            continue;
        }
        $seen[$code] = true;
        $name        = isset($row['name']) ? trim((string) $row['name']) : '';
        $langs[]     = array('code' => $code, 'name' => $name !== '' ? $name : $catalog[$code]);
    }
    return $langs;
}

/**
 * 导航栏语言按钮（放在明暗切换左边）
 */
function xi_translate_button()
{
    if (!xi_translate_enabled()) {
        return;
    }
    // notranslate：语言名称始终显示为各自的文字，不参与翻译
    echo '<div class="xi-lang notranslate" translate="no" data-xi-lang>';
    echo '<button type="button" class="xi-btn-icon xi-lang-toggle" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr__('切换语言', 'xi') . '" title="' . esc_attr__('切换语言', 'xi') . '">' . xi_icon('globe') . '<span class="xi-lang-code" aria-hidden="true"></span></button>';
    echo '<div class="xi-lang-menu xi-glass" role="menu">';
    foreach (xi_translate_langs() as $i => $lang) {
        echo '<button type="button" class="xi-lang-item" role="menuitemradio" aria-checked="' . ($i === 0 ? 'true' : 'false') . '" data-lang="' . esc_attr($lang['code']) . '" lang="' . esc_attr($lang['code']) . '">';
        echo '<span>' . esc_html($lang['name']) . '</span>' . ($i === 0 ? '<small>' . esc_html__('原文', 'xi') . '</small>' : '') . xi_icon('check') . '</button>';
    }
    echo '</div></div>';
}

/**
 * 不参与翻译的元素：代码、站点 LOGO、语言菜单，以及后台填写的选择器
 */
function xi_translate_ignore()
{
    $base   = 'script, style, noscript, template, textarea, code, pre, kbd, samp, svg, math, iframe, [contenteditable="true"], .notranslate, [translate="no"], #wpadminbar';
    $custom = trim((string) _xz('translate_ignore', '.xi-logo'));
    return $custom !== '' ? $base . ', ' . $custom : $base;
}

function xi_translate_enqueue()
{
    if (!xi_translate_enabled()) {
        return;
    }
    wp_enqueue_script('xi-translate', get_template_directory_uri() . '/assets/js/translate.js', array('xi-main'), XI_VERSION, true);
    wp_localize_script('xi-translate', 'xiTranslate', array(
        'source' => xi_translate_source(),
        'langs'  => array_column(xi_translate_langs(), 'code'),
        'names'  => array_column(xi_translate_langs(), 'name', 'code'),
        'auto'   => (bool) _xz('translate_auto', true),
        'ignore' => xi_translate_ignore(),
        'msg'    => array(
            'done' => __('已切换为 %s', 'xi'),
            'back' => __('已恢复原文', 'xi'),
            'fail' => __('翻译服务暂时不可用，请稍后再试', 'xi'),
        ),
    ));
}
add_action('wp_enqueue_scripts', 'xi_translate_enqueue');

/**
 * 在页面渲染前标记要翻译的语言，翻译完成前先隐藏正文，避免中文闪一下再变成外文
 */
function xi_translate_head_script()
{
    if (!xi_translate_enabled()) {
        return;
    }
    $langs = wp_json_encode(array_column(xi_translate_langs(), 'code'));
    $src   = esc_js(xi_translate_source());
    echo "<script>(function(){try{var l=localStorage.getItem('xi-lang');if(l&&l!=='" . $src . "'&&" . $langs . ".indexOf(l)>0){document.documentElement.classList.add('xi-translating');setTimeout(function(){document.documentElement.classList.remove('xi-translating')},1500)}}catch(e){}})();</script>\n";
}
add_action('wp_head', 'xi_translate_head_script', 1);
