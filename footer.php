</div><!-- .xi-layout -->

<?php xi_fluid_widgets('bottom'); ?>

<footer class="xi-footer<?php echo xi_footer_wave_on() ? ' has-wave' : ''; ?>">
    <?php xi_footer_wave(); ?>
    <div class="xi-container">
        <div class="xi-footer-inner xi-glass">
            <?php
            $brand = _xz('footer_brand_s', true);
            ob_start();
            xi_footer_cols();
            $cols = ob_get_clean();
            ob_start();
            xi_footer_qrs();
            $qrs = ob_get_clean();
            ?>
            <?php if ($brand || $cols || $qrs) : ?>
                <div class="xi-footer-top">
                    <?php if ($brand) : ?>
                        <div class="xi-footer-brand">
                            <?php xi_logo(); ?>
                            <?php if ($about = _xz('footer_about', '') ?: get_bloginfo('description')) : ?>
                                <p class="xi-footer-about"><?php echo esc_html($about); ?></p>
                            <?php endif; ?>
                            <?php xi_footer_social(); ?>
                        </div>
                    <?php endif; ?>
                    <?php echo $cols . $qrs; // 已在函数内转义 ?>
                </div>
            <?php endif; ?>

            <?php if (is_active_sidebar('all_footer')) : ?>
                <div class="xi-footer-widgets"><?php dynamic_sidebar('all_footer'); ?></div>
            <?php endif; ?>

            <div class="xi-footer-bottom">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'footer',
                    'container'      => false,
                    'menu_class'     => 'xi-footer-menu',
                    'depth'          => 1,
                    'fallback_cb'    => false,
                ));
                ?>
                <div class="xi-footer-meta">
                    <span class="xi-copyright"><?php xi_footer_copyright(); ?></span>
                    <?php if ($days = xi_footer_days()) : ?>
                        <span class="xi-footer-days"><?php echo xi_icon('clock'); ?><?php printf(esc_html__('本站已运行 %s 天', 'xi'), '<b>' . number_format_i18n($days) . '</b>'); ?></span>
                    <?php endif; ?>
                    <?php if ($icp = _xz('footer_icp', '')) : ?>
                        <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener nofollow"><?php echo esc_html($icp); ?></a>
                    <?php endif; ?>
                    <?php if ($gongan = _xz('footer_gongan')) : ?>
                        <a href="https://beian.mps.gov.cn/" target="_blank" rel="noopener nofollow"><?php echo esc_html($gongan); ?></a>
                    <?php endif; ?>
                </div>
                <?php if ($footer_html = _xz('footer_html')) : ?>
                    <div class="xi-footer-html"><?php echo wp_kses_post($footer_html); ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

<div class="xi-float-btns">
    <button class="xi-float-btn xi-glass xi-to-top" aria-label="<?php esc_attr_e('回到顶部', 'xi'); ?>"><?php echo xi_icon('up'); ?></button>
</div>

<?php wp_footer(); ?>
</body>
</html>
