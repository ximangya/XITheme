<form role="search" method="get" class="xi-search-form" action="<?php echo esc_url(home_url('/')); ?>">
    <input type="search" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="<?php echo esc_attr(xi_search_placeholder()); ?>" aria-label="<?php esc_attr_e('搜索', 'xi'); ?>">
    <?php if (is_search() && xi_search_type() === 'user') : ?><input type="hidden" name="stype" value="user"><?php endif; ?>
    <button type="submit" aria-label="<?php esc_attr_e('搜索', 'xi'); ?>"><?php echo xi_icon('search'); ?></button>
</form>
