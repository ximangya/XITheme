<?php
/**
 * 小型音乐播放器
 *
 * 全站右下角（位置可配）弹出的小播放器，支持本地上传 MP3 或在线地址。
 * 设置位于「站点美化 → 音乐播放器」。
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 读取媒体字段，兼容上传返回数组 / URL 字符串
 */
function xi_music_media_url($value)
{
    if (is_array($value)) {
        if (!empty($value['url'])) {
            $value = $value['url'];
        } elseif (!empty($value['id'])) {
            $value = wp_get_attachment_url((int) $value['id']);
        } else {
            $value = '';
        }
    }
    $value = trim((string) $value);
    return $value ? set_url_scheme(esc_url_raw($value)) : '';
}

/**
 * 播放器配置；未开启或没有音源时返回空数组
 */
function xi_music_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = array();
    if (is_admin() || is_feed() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
        return $config;
    }
    if (!_xz('music_s', false)) {
        return $config;
    }

    // 本地文件优先，其次在线地址
    $src   = xi_music_media_url(_xz('music_file', ''));
    $url   = trim((string) _xz('music_url', ''));
    $src   = $src ? $src : ($url ? set_url_scheme(esc_url_raw($url)) : '');
    if (!$src) {
        return $config;
    }

    $cover = xi_music_media_url(_xz('music_cover', ''));
    if (!$cover) {
        $cover = xi_music_media_url(_xz('logo', ''));
    }

    $positions = array('bottom-right', 'bottom-left', 'top-right', 'top-left', 'bottom-center', 'top-center', 'center-left', 'center-right', 'center');
    $position  = (string) _xz('music_pos', 'bottom-left');
    if (!in_array($position, $positions, true)) {
        $position = 'bottom-left';
    }

    $name = trim((string) _xz('music_name', ''));
    $config = array(
        'src'      => $src,
        'cover'    => $cover,
        'name'     => $name !== '' ? $name : __('曦主题 · 背景音乐', 'xi'),
        'artist'   => trim((string) _xz('music_artist', '')),
        'position' => $position,
        'x'        => max(-400, min(400, (int) _xz('music_x', 0))),
        'y'        => max(-400, min(400, (int) _xz('music_y', 0))),
        'volume'   => max(0, min(100, (int) _xz('music_volume', 55))),
        'loop'     => (bool) _xz('music_loop', true),
        'autoplay' => (bool) _xz('music_autoplay', false),
    );

    return $config;
}

/**
 * 只在有音源时加载播放器资源
 */
function xi_music_enqueue()
{
    if (!xi_music_config()) {
        return;
    }
    $uri = get_template_directory_uri();
    wp_enqueue_style('xi-music', $uri . '/assets/css/music.css', array('xi-main'), XI_VERSION);
    wp_enqueue_script('xi-music', $uri . '/assets/js/music.js', array(), XI_VERSION, true);
}
add_action('wp_enqueue_scripts', 'xi_music_enqueue', 30);

/**
 * 页脚输出播放器
 */
function xi_music_player()
{
    $music = xi_music_config();
    if (!$music) {
        return;
    }

    $style = '--xi-music-x:' . $music['x'] . 'px;--xi-music-y:' . $music['y'] . 'px;';
    $cover = $music['cover']
        ? '<img src="' . esc_url($music['cover']) . '" alt="" loading="eager" decoding="async">'
        : '<span class="xi-music-cover-icon">' . xi_icon('music') . '</span>';
    ?>
    <div class="xi-music" id="xi-music"
         data-position="<?php echo esc_attr($music['position']); ?>"
         data-volume="<?php echo esc_attr($music['volume']); ?>"
         data-autoplay="<?php echo $music['autoplay'] ? '1' : '0'; ?>">
        <div class="xi-music-inner" style="<?php echo esc_attr($style); ?>">
            <div class="xi-music-card xi-glass" role="region" aria-label="<?php esc_attr_e('音乐播放器', 'xi'); ?>">
                <button type="button" class="xi-music-close" data-music-close aria-label="<?php esc_attr_e('收起播放器', 'xi'); ?>"><?php echo xi_icon('close'); ?></button>
                <div class="xi-music-cover"><?php echo $cover; ?></div>
                <div class="xi-music-meta">
                    <b><?php echo esc_html($music['name']); ?></b>
                    <?php if ($music['artist'] !== '') : ?>
                        <small><?php echo esc_html($music['artist']); ?></small>
                    <?php endif; ?>
                </div>
                <button type="button" class="xi-music-toggle" data-music-toggle aria-label="<?php esc_attr_e('播放', 'xi'); ?>">
                    <svg class="xi-music-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                    <svg class="xi-music-pause" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>
                </button>
                <div class="xi-music-progress" data-music-progress role="slider" tabindex="0"
                     aria-label="<?php esc_attr_e('播放进度', 'xi'); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <span class="xi-music-fill"></span>
                </div>
                <div class="xi-music-time">
                    <span data-music-current>0:00</span>
                    <span data-music-duration>0:00</span>
                </div>
            </div>
            <button type="button" class="xi-music-mini" data-music-mini aria-label="<?php esc_attr_e('打开音乐播放器', 'xi'); ?>">
                <?php echo $cover; ?>
            </button>
            <audio class="xi-music-audio" preload="metadata" src="<?php echo esc_url($music['src']); ?>"<?php echo $music['loop'] ? ' loop' : ''; ?>></audio>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'xi_music_player', 15);
