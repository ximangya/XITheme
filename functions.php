<?php
/**
 * 曦主题 - 入口文件
 * 入口只负责加载，具体功能拆分到 inc 目录
 */

if (!defined('ABSPATH')) {
    exit;
}

define('XI_VERSION', wp_get_theme(get_template())->get('Version'));
// 内部版本号：同一大版本内的构建序号，用于资源缓存刷新与问题排查
define('XI_BUILD', '2610110');

require_once get_theme_file_path('inc/inc.php');
