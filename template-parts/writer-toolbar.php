<?php
/**
 * 前台编辑器工具栏（投稿页、发帖页共用），按钮行为见 assets/js/editor.js
 * 常用格式放在工具栏；引言、代码、提醒框等区块统一收进「插入」面板（空行输入 / 也能打开）
 */
?>
<div class="xi-writer-toolbar xi-glass" role="toolbar" aria-label="<?php esc_attr_e('格式工具栏', 'xi'); ?>">
    <div class="xi-tb-group">
        <?php echo xi_writer_btn('undo', 'undo', __('撤销', 'xi')); ?>
        <?php echo xi_writer_btn('redo', 'redo', __('重做', 'xi')); ?>
    </div>
    <div class="xi-tb-group">
        <select class="xi-tb-select" data-cmd="block" title="<?php esc_attr_e('段落样式', 'xi'); ?>" aria-label="<?php esc_attr_e('段落样式', 'xi'); ?>">
            <option value="p"><?php esc_html_e('正文', 'xi'); ?></option>
            <option value="h2"><?php esc_html_e('大标题', 'xi'); ?></option>
            <option value="h3"><?php esc_html_e('小标题', 'xi'); ?></option>
        </select>
    </div>
    <div class="xi-tb-group">
        <?php echo xi_writer_btn('bold', 'bold', __('加粗 Ctrl+B', 'xi')); ?>
        <?php echo xi_writer_btn('italic', 'italic', __('斜体 Ctrl+I', 'xi')); ?>
        <?php echo xi_writer_btn('underline', 'underline', __('下划线 Ctrl+U', 'xi')); ?>
        <?php echo xi_writer_btn('strikeThrough', 'strike', __('删除线', 'xi')); ?>
        <?php echo xi_writer_btn('inlineCode', 'code', __('行内代码', 'xi')); ?>
    </div>
    <div class="xi-tb-group">
        <?php echo xi_writer_btn('insertUnorderedList', 'list', __('无序列表', 'xi')); ?>
        <?php echo xi_writer_btn('insertOrderedList', 'listol', __('有序列表', 'xi')); ?>
        <?php echo xi_writer_btn('link', 'link', __('插入链接 Ctrl+K', 'xi')); ?>
        <?php if (xi_user_can('upload')) {
            echo xi_writer_btn('image', 'image', __('插入图片', 'xi'));
        } ?>
    </div>
    <div class="xi-tb-group">
        <button type="button" class="xi-tb-btn xi-tb-insert" data-cmd="insert" title="<?php esc_attr_e('插入引言、代码、提醒框等（空行输入 / 也可打开）', 'xi'); ?>" aria-haspopup="true"><?php echo xi_icon('plus'); ?><span><?php esc_html_e('插入', 'xi'); ?></span></button>
    </div>
    <div class="xi-tb-group is-end">
        <?php echo xi_writer_btn('removeFormat', 'eraser', __('清除格式', 'xi')); ?>
        <?php echo xi_writer_btn('focus', 'expand', __('专注模式', 'xi')); ?>
    </div>
</div>
