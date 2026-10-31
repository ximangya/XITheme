<?php
/**
 * 曦主题 - 入口文件
 * 入口只负责加载，具体功能拆分到 inc 目录
 */

if (!defined('ABSPATH')) {
    exit;
}

define('XI_VERSION', wp_get_theme(get_template())->get('Version'));
// 内部版本号：每次小修改 +5（本版起始 3000），用于资源缓存刷新与问题排查
define('XI_BUILD', '3005');

require_once get_theme_file_path('inc/inc.php');
