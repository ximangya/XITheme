<?php
/**
 * 页脚社交图标
 * QQ、微信、微博、GitHub、邮箱、RSS 图形取自 Font Awesome 4.7（SIL OFL 1.1），B站图标为主题自绘
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_brand_icon($name)
{
    static $glyphs = array(
        'qq' => array(1792, 'M270 730q-8 19 -8 52q0 20 11 49t24 45q-1 22 7.5 53t22.5 43q0 139 92.5 288.5t217.5 209.5q139 66 324 66q133 0 266 -55q49 -21 90 -48t71 -56t55 -68t42 -74t32.5 -84.5t25.5 -89.5t22 -98l1 -5q55 -83 55 -150q0 -14 -9 -40t-9 -38q0 -1 1.5 -3.5t3.5 -5t2 -3.5 q77 -114 120.5 -214.5t43.5 -208.5q0 -43 -19.5 -100t-55.5 -57q-9 0 -19.5 7.5t-19 17.5t-19 26t-16 26.5t-13.5 26t-9 17.5q-1 1 -3 1l-5 -4q-59 -154 -132 -223q20 -20 61.5 -38.5t69 -41.5t35.5 -65q-2 -4 -4 -16t-7 -18q-64 -97 -302 -97q-53 0 -110.5 9t-98 20 t-104.5 30q-15 5 -23 7q-14 4 -46 4.5t-40 1.5q-41 -45 -127.5 -65t-168.5 -20q-35 0 -69 1.5t-93 9t-101 20.5t-74.5 40t-32.5 64q0 40 10 59.5t41 48.5q11 2 40.5 13t49.5 12q4 0 14 2q2 2 2 4l-2 3q-48 11 -108 105.5t-73 156.5l-5 3q-4 0 -12 -20q-18 -41 -54.5 -74.5 t-77.5 -37.5h-1q-4 0 -6 4.5t-5 5.5q-23 54 -23 100q0 275 252 466z'),
        'wechat' => array(2048, 'M580 1075q0 41 -25 66t-66 25q-43 0 -76 -25.5t-33 -65.5q0 -39 33 -64.5t76 -25.5q41 0 66 24.5t25 65.5zM1323 568q0 28 -25.5 50t-65.5 22q-27 0 -49.5 -22.5t-22.5 -49.5q0 -28 22.5 -50.5t49.5 -22.5q40 0 65.5 22t25.5 51zM1087 1075q0 41 -24.5 66t-65.5 25 q-43 0 -76 -25.5t-33 -65.5q0 -39 33 -64.5t76 -25.5q41 0 65.5 24.5t24.5 65.5zM1722 568q0 28 -26 50t-65 22q-27 0 -49.5 -22.5t-22.5 -49.5q0 -28 22.5 -50.5t49.5 -22.5q39 0 65 22t26 51zM1456 965q-31 4 -70 4q-169 0 -311 -77t-223.5 -208.5t-81.5 -287.5 q0 -78 23 -152q-35 -3 -68 -3q-26 0 -50 1.5t-55 6.5t-44.5 7t-54.5 10.5t-50 10.5l-253 -127l72 218q-290 203 -290 490q0 169 97.5 311t264 223.5t363.5 81.5q176 0 332.5 -66t262 -182.5t136.5 -260.5zM2048 404q0 -117 -68.5 -223.5t-185.5 -193.5l55 -181l-199 109 q-150 -37 -218 -37q-169 0 -311 70.5t-223.5 191.5t-81.5 264t81.5 264t223.5 191.5t311 70.5q161 0 303 -70.5t227.5 -192t85.5 -263.5z'),
        'weibo' => array(1792, 'M675 252q21 34 11 69t-45 50q-34 14 -73 1t-60 -46q-22 -34 -13 -68.5t43 -50.5t74.5 -2.5t62.5 47.5zM769 373q8 13 3.5 26.5t-17.5 18.5q-14 5 -28.5 -0.5t-21.5 -18.5q-17 -31 13 -45q14 -5 29 0.5t22 18.5zM943 266q-45 -102 -158 -150t-224 -12 q-107 34 -147.5 126.5t6.5 187.5q47 93 151.5 139t210.5 19q111 -29 158.5 -119.5t2.5 -190.5zM1255 426q-9 96 -89 170t-208.5 109t-274.5 21q-223 -23 -369.5 -141.5t-132.5 -264.5q9 -96 89 -170t208.5 -109t274.5 -21q223 23 369.5 141.5t132.5 264.5zM1563 422 q0 -68 -37 -139.5t-109 -137t-168.5 -117.5t-226 -83t-270.5 -31t-275 33.5t-240.5 93t-171.5 151t-65 199.5q0 115 69.5 245t197.5 258q169 169 341.5 236t246.5 -7q65 -64 20 -209q-4 -14 -1 -20t10 -7t14.5 0.5t13.5 3.5l6 2q139 59 246 59t153 -61q45 -63 0 -178 q-2 -13 -4.5 -20t4.5 -12.5t12 -7.5t17 -6q57 -18 103 -47t80 -81.5t34 -116.5zM1489 1046q42 -47 54.5 -108.5t-6.5 -117.5q-8 -23 -29.5 -34t-44.5 -4q-23 8 -34 29.5t-4 44.5q20 63 -24 111t-107 35q-24 -5 -45 8t-25 37q-5 24 8 44.5t37 25.5q60 13 119 -5.5t101 -65.5z M1670 1209q87 -96 112.5 -222.5t-13.5 -241.5q-9 -27 -34 -40t-52 -4t-40 34t-5 52q28 82 10 172t-80 158q-62 69 -148 95.5t-173 8.5q-28 -6 -52 9.5t-30 43.5t9.5 51.5t43.5 29.5q123 26 244 -11.5t208 -134.5z'),
        'github' => array(1536, 'M768 1408q209 0 385.5 -103t279.5 -279.5t103 -385.5q0 -251 -146.5 -451.5t-378.5 -277.5q-27 -5 -40 7t-13 30q0 3 0.5 76.5t0.5 134.5q0 97 -52 142q57 6 102.5 18t94 39t81 66.5t53 105t20.5 150.5q0 119 -79 206q37 91 -8 204q-28 9 -81 -11t-92 -44l-38 -24 q-93 26 -192 26t-192 -26q-16 11 -42.5 27t-83.5 38.5t-85 13.5q-45 -113 -8 -204q-79 -87 -79 -206q0 -85 20.5 -150t52.5 -105t80.5 -67t94 -39t102.5 -18q-39 -36 -49 -103q-21 -10 -45 -15t-57 -5t-65.5 21.5t-55.5 62.5q-19 32 -48.5 52t-49.5 24l-20 3q-21 0 -29 -4.5 t-5 -11.5t9 -14t13 -12l7 -5q22 -10 43.5 -38t31.5 -51l10 -23q13 -38 44 -61.5t67 -30t69.5 -7t55.5 3.5l23 4q0 -38 0.5 -88.5t0.5 -54.5q0 -18 -13 -30t-40 -7q-232 77 -378.5 277.5t-146.5 451.5q0 209 103 385.5t279.5 279.5t385.5 103zM291 305q3 7 -7 12 q-10 3 -13 -2q-3 -7 7 -12q9 -6 13 2zM322 271q7 5 -2 16q-10 9 -16 3q-7 -5 2 -16q10 -10 16 -3zM352 226q9 7 0 19q-8 13 -17 6q-9 -5 0 -18t17 -7zM394 184q8 8 -4 19q-12 12 -20 3q-9 -8 4 -19q12 -12 20 -3zM451 159q3 11 -13 16q-15 4 -19 -7t13 -15q15 -6 19 6z M514 154q0 13 -17 11q-16 0 -16 -11q0 -13 17 -11q16 0 16 11zM572 164q-2 11 -18 9q-16 -3 -14 -15t18 -8t14 14z'),
        'email' => array(1792, 'M1792 826v-794q0 -66 -47 -113t-113 -47h-1472q-66 0 -113 47t-47 113v794q44 -49 101 -87q362 -246 497 -345q57 -42 92.5 -65.5t94.5 -48t110 -24.5h1h1q51 0 110 24.5t94.5 48t92.5 65.5q170 123 498 345q57 39 100 87zM1792 1120q0 -79 -49 -151t-122 -123 q-376 -261 -468 -325q-10 -7 -42.5 -30.5t-54 -38t-52 -32.5t-57.5 -27t-50 -9h-1h-1q-23 0 -50 9t-57.5 27t-52 32.5t-54 38t-42.5 30.5q-91 64 -262 182.5t-205 142.5q-62 42 -117 115.5t-55 136.5q0 78 41.5 130t118.5 52h1472q65 0 112.5 -47t47.5 -113z'),
        'rss' => array(1408, 'M384 192q0 -80 -56 -136t-136 -56t-136 56t-56 136t56 136t136 56t136 -56t56 -136zM896 69q2 -28 -17 -48q-18 -21 -47 -21h-135q-25 0 -43 16.5t-20 41.5q-22 229 -184.5 391.5t-391.5 184.5q-25 2 -41.5 20t-16.5 43v135q0 29 21 47q17 17 43 17h5q160 -13 306 -80.5 t259 -181.5q114 -113 181.5 -259t80.5 -306zM1408 67q2 -27 -18 -47q-18 -20 -46 -20h-143q-26 0 -44.5 17.5t-19.5 42.5q-12 215 -101 408.5t-231.5 336t-336 231.5t-408.5 102q-25 1 -42.5 19.5t-17.5 43.5v143q0 28 20 46q18 18 44 18h3q262 -13 501.5 -120t425.5 -294 q187 -186 294 -425.5t120 -501.5z'),
    );
    if ($name === 'bilibili') {
        return '<svg class="xi-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7.2 3.2 9.6 5.6h4.8l2.4-2.4a.9.9 0 0 1 1.3 1.3L17 5.6h1.4A3.6 3.6 0 0 1 22 9.2v7.2a3.6 3.6 0 0 1-3.6 3.6H5.6A3.6 3.6 0 0 1 2 16.4V9.2a3.6 3.6 0 0 1 3.6-3.6H7L5.9 4.5a.9.9 0 0 1 1.3-1.3zM5.6 7.6a1.6 1.6 0 0 0-1.6 1.6v7.2a1.6 1.6 0 0 0 1.6 1.6h12.8a1.6 1.6 0 0 0 1.6-1.6V9.2a1.6 1.6 0 0 0-1.6-1.6zm3 3.2a1 1 0 0 1 1 1v1.2a1 1 0 0 1-2 0v-1.2a1 1 0 0 1 1-1zm6.8 0a1 1 0 0 1 1 1v1.2a1 1 0 0 1-2 0v-1.2a1 1 0 0 1 1-1z"/></svg>';
    }
    if (!isset($glyphs[$name])) {
        return xi_icon('link');
    }
    // 字体坐标 y 轴向上，基线在 1536，这里翻转为 SVG 坐标
    return '<svg class="xi-icon" viewBox="0 0 ' . $glyphs[$name][0] . ' 1792" fill="currentColor" aria-hidden="true"><path transform="matrix(1 0 0 -1 0 1536)" d="' . $glyphs[$name][1] . '"/></svg>';
}

/* ---------- 页脚 ---------- */

/**
 * 处理后台填写的链接：邮箱补 mailto:，站内相对路径补全域名
 */
function xi_footer_url($link)
{
    $link = trim((string) $link);
    if ($link === '') {
        return '';
    }
    if (is_email($link)) {
        return 'mailto:' . $link;
    }
    if ($link[0] === '/' && substr($link, 0, 2) !== '//') {
        return home_url($link);
    }
    return $link;
}

function xi_footer_social()
{
    $items = (array) _xz('footer_social', array());
    $out   = '';
    foreach ($items as $item) {
        if (empty($item['icon']) || (empty($item['link']) && empty($item['qr']))) {
            continue;
        }
        $title = !empty($item['title']) ? $item['title'] : $item['icon'];
        $url   = xi_footer_url(isset($item['link']) ? $item['link'] : '');
        $inner = xi_brand_icon($item['icon']);
        if (!empty($item['qr'])) {
            $inner .= '<span class="xi-footer-qrpop xi-glass"><img src="' . esc_url($item['qr']) . '" alt="" loading="lazy"><small>' . esc_html($title) . '</small></span>';
        }
        $attr = ' class="xi-footer-social-item is-' . esc_attr($item['icon']) . '" aria-label="' . esc_attr($title) . '" title="' . esc_attr($title) . '"';
        $out .= $url
            ? '<a href="' . esc_url($url) . '"' . $attr . (strpos($url, 'mailto:') === 0 ? '' : ' target="_blank" rel="noopener nofollow"') . '>' . $inner . '</a>'
            : '<span tabindex="0"' . $attr . '>' . $inner . '</span>';
    }
    if ($out) {
        echo '<div class="xi-footer-social">' . $out . '</div>';
    }
}

/**
 * 链接栏目：每行「名称|链接」
 */
function xi_footer_cols()
{
    foreach ((array) _xz('footer_cols', array()) as $col) {
        $lines = preg_split('/\r\n|\r|\n/', isset($col['links']) ? $col['links'] : '');
        $links = '';
        foreach ($lines as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            if ($parts[0] === '') {
                continue;
            }
            $url    = xi_footer_url(isset($parts[1]) ? $parts[1] : '');
            $target = $url && strpos($url, home_url()) !== 0 && $url[0] !== '#' ? ' target="_blank" rel="noopener"' : '';
            $links .= '<li>' . ($url ? '<a href="' . esc_url($url) . '"' . $target . '>' . esc_html($parts[0]) . '</a>' : esc_html($parts[0])) . '</li>';
        }
        if (!$links && empty($col['title'])) {
            continue;
        }
        echo '<nav class="xi-footer-col">';
        if (!empty($col['title'])) {
            echo '<h4>' . esc_html($col['title']) . '</h4>';
        }
        echo '<ul>' . $links . '</ul></nav>';
    }
}

function xi_footer_qrs()
{
    $out = '';
    foreach ((array) _xz('footer_qrs', array()) as $qr) {
        if (empty($qr['image'])) {
            continue;
        }
        $out .= '<figure><img src="' . esc_url($qr['image']) . '" alt="' . esc_attr(isset($qr['title']) ? $qr['title'] : '') . '" loading="lazy">';
        $out .= !empty($qr['title']) ? '<figcaption>' . esc_html($qr['title']) . '</figcaption>' : '';
        $out .= '</figure>';
    }
    if ($out) {
        echo '<div class="xi-footer-qrs">' . $out . '</div>';
    }
}

/**
 * 从某日期（Y-m-d，站点时区）到今天的运行天数，建站当天算第 1 天；日期无效时返回 0
 */
function xi_days_since($date)
{
    $since = strtotime((string) $date);
    if (!$since) {
        return 0;
    }
    return max(1, (int) floor((current_time('timestamp') - $since) / DAY_IN_SECONDS) + 1);
}

/**
 * 运行天数，未设置建站日期时返回 0
 */
function xi_footer_days()
{
    return xi_days_since(_xz('footer_since', ''));
}
