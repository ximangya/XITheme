<?php
/**
 * Template Name: 幸运大转盘
 * Template Post Type: page
 */

get_header();

$wheel = xi_wheel_config();
?>
<main class="xi-main xi-wheel-page">
    <div class="xi-wheel-shell">
        <header class="xi-wheel-hero">
            <p class="xi-wheel-eyebrow">LUCKY WHEEL</p>
            <h1><?php echo esc_html($wheel['title']); ?></h1>
            <p><?php echo esc_html($wheel['subtitle']); ?></p>
        </header>

        <div class="xi-wheel-layout">
            <section class="xi-wheel-stage xi-glass" aria-label="<?php esc_attr_e('幸运转盘', 'xi'); ?>">
                <div class="xi-wheel-box">
                    <canvas id="xi-wheel-canvas" width="920" height="920"></canvas>
                    <div class="xi-wheel-pointer" aria-hidden="true">
                        <svg viewBox="0 0 100 100">
                            <defs>
                                <linearGradient id="xiWheelPointer" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stop-color="#ffd166"/>
                                    <stop offset="1" stop-color="#f59e0b"/>
                                </linearGradient>
                            </defs>
                            <path d="M50 8 L18 82 L50 66 L82 82 Z" fill="url(#xiWheelPointer)" stroke="#fff" stroke-width="4" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <button type="button" class="xi-wheel-spin" id="xi-wheel-spin" aria-label="<?php esc_attr_e('开始抽奖', 'xi'); ?>">
                        <span id="xi-wheel-spin-label"><?php esc_html_e('开始', 'xi'); ?></span>
                        <small>GO</small>
                    </button>
                </div>
            </section>

            <aside class="xi-wheel-panel xi-glass">
                <div class="xi-wheel-panel-head">
                    <h2><?php esc_html_e('抽奖选项', 'xi'); ?></h2>
                    <p><?php esc_html_e('自定义名称与颜色，保存在当前浏览器', 'xi'); ?></p>
                </div>
                <div class="xi-wheel-options" id="xi-wheel-options"></div>
                <div class="xi-wheel-actions">
                    <button type="button" class="xi-wheel-btn is-main" id="xi-wheel-add">＋ <?php esc_html_e('添加选项', 'xi'); ?></button>
                    <button type="button" class="xi-wheel-btn" id="xi-wheel-reset"><?php esc_html_e('恢复默认', 'xi'); ?></button>
                </div>
                <div class="xi-wheel-foot">
                    <span><?php esc_html_e('默认配置来自主题设置', 'xi'); ?></span>
                    <strong id="xi-wheel-count">0 <?php esc_html_e('项', 'xi'); ?></strong>
                </div>
            </aside>
        </div>
    </div>
</main>

<div class="xi-wheel-modal" id="xi-wheel-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="xi-wheel-prize">
    <div class="xi-wheel-modal-card xi-glass">
        <div class="xi-wheel-emoji">🎉</div>
        <p class="xi-wheel-modal-title"><?php esc_html_e('恭喜中奖', 'xi'); ?></p>
        <p class="xi-wheel-prize" id="xi-wheel-prize"></p>
        <button type="button" class="xi-wheel-btn is-main" id="xi-wheel-again"><?php esc_html_e('再来一次', 'xi'); ?></button>
    </div>
</div>

<noscript><p class="xi-wheel-noscript"><?php esc_html_e('转盘需要浏览器启用 JavaScript。', 'xi'); ?></p></noscript>

<?php get_footer(); ?>
