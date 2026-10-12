<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="xi-backdrop" aria-hidden="true"></div>

<?php if (is_single() && _xz('reading_progress_s', true)) : ?>
    <div class="xi-progress" aria-hidden="true"></div>
<?php endif; ?>

<header class="xi-header">
    <div class="xi-container">
        <div class="xi-header-inner xi-glass">
            <?php xi_logo(); ?>

            <nav class="xi-nav" aria-label="<?php esc_attr_e('主导航', 'xi'); ?>" data-more="<?php esc_attr_e('更多', 'xi'); ?>">
                <?php
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'xi-menu',
                    'depth'          => 2,
                    'fallback_cb'    => 'xi_menu_fallback',
                ));
                ?>
            </nav>

            <div class="xi-header-actions">
                <?php if (_xz('header_search', true)) : ?>
                    <button class="xi-btn-icon" data-toggle="search" aria-label="<?php esc_attr_e('搜索', 'xi'); ?>"><?php echo xi_icon('search'); ?></button>
                <?php endif; ?>
                <?php if (function_exists('xi_translate_button')) xi_translate_button(); ?>
                <?php if (_xz('theme_switch_s', true)) : ?>
                    <button class="xi-btn-icon xi-theme-toggle" aria-label="<?php esc_attr_e('切换深浅色', 'xi'); ?>"><?php echo xi_icon('sun') . xi_icon('moon'); ?></button>
                <?php endif; ?>
                <?php do_action('xi_header_actions'); ?>
                <?php xi_header_user(); ?>
                <button class="xi-btn-icon xi-menu-toggle" data-toggle="menu" aria-label="<?php esc_attr_e('菜单', 'xi'); ?>"><?php echo xi_icon('menu'); ?></button>
            </div>
        </div>

        <?php if (_xz('header_search', true)) : ?>
            <div class="xi-search-panel xi-glass">
                <?php get_search_form(); ?>
            </div>
        <?php endif; ?>
    </div>
</header>

<?php xi_header_slide(); ?>

<?php xi_post_hero(); ?>

<?php xi_fluid_widgets('top'); ?>

<div class="xi-container xi-layout">
