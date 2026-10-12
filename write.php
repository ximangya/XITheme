<?php
/**
 * 前台投稿 / 编辑
 */

get_header();

$user    = wp_get_current_user();
$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
$error   = '';
$post    = null;

if (!xi_write_enabled()) {
    $error = __('前台投稿暂未开放', 'xi');
} elseif ($user->exists() && function_exists('xi_ban_notice') && ($ban_notice = xi_ban_notice($user->ID))) {
    $error = $ban_notice;
} elseif ($user->exists() && $post_id) {
    $can = xi_write_can_edit($post_id, $user->ID);
    if (is_wp_error($can)) {
        $error = $can->get_error_message();
    } else {
        $post = get_post($post_id);
    }
} elseif ($user->exists() && !xi_user_can('write', $user->ID)) {
    $error = __('你所在的用户组暂无投稿权限', 'xi');
}

$content = '';
$cat     = 0;
$tags    = array();
$thumb   = 0;
$status  = 'new';
if ($post) {
    $content = $post->post_content;
    if (!has_blocks($content) && strpos($content, '<p') === false) {
        $content = wpautop($content);
    }
    $content = function_exists('xi_blocks_for_writer') ? xi_blocks_for_writer($content) : $content;
    $cats   = wp_get_post_categories($post->ID);
    $cat    = $cats ? (int) $cats[0] : 0;
    $tags   = wp_get_post_tags($post->ID, array('fields' => 'names'));
    $thumb  = (int) get_post_thumbnail_id($post->ID);
    $status = $post->post_status;
}

$status_names = array(
    'new'     => __('新文章', 'xi'),
    'draft'   => __('草稿', 'xi'),
    'pending' => __('审核中', 'xi'),
    'publish' => __('已发布', 'xi'),
    'future'  => __('定时发布', 'xi'),
    'private' => __('私密', 'xi'),
);
$can_publish = $user->exists() && xi_write_can_publish($user->ID);
$need_review = !$can_publish && !($status === 'publish' && $user->exists() && xi_user_can('edit_noreview', $user->ID));
?>

<main class="xi-main xi-write">
    <?php if (!$user->exists()) : ?>
        <div class="xi-card xi-uc-guest">
            <span class="xi-user-guest is-lg"><?php echo xi_icon('edit'); ?></span>
            <h1><?php esc_html_e('登录后即可投稿', 'xi'); ?></h1>
            <p><?php esc_html_e('分享你的见闻与心得', 'xi'); ?></p>
            <div class="xi-uc-actions"><?php echo xi_login_buttons(); ?></div>
        </div>
    <?php elseif ($error) : ?>
        <div class="xi-card xi-uc-guest">
            <span class="xi-user-guest is-lg"><?php echo xi_icon('lock'); ?></span>
            <h1><?php echo esc_html($error); ?></h1>
            <div class="xi-uc-actions"><a class="xi-btn" href="<?php echo esc_url(xi_uc_url('posts')); ?>"><?php esc_html_e('返回我的文章', 'xi'); ?></a></div>
        </div>
    <?php else : ?>
        <form class="xi-writer" data-post-id="<?php echo (int) $post_id; ?>" autocomplete="off" novalidate>
            <div class="xi-writer-main">
                <div class="xi-card xi-writer-paper">
                    <textarea class="xi-writer-title" name="title" rows="1" maxlength="100" placeholder="<?php esc_attr_e('输入文章标题', 'xi'); ?>"><?php echo $post ? esc_textarea($post->post_title) : ''; ?></textarea>

                    <?php get_template_part('template-parts/writer-toolbar'); ?>

                    <div class="xi-writer-content xi-content" contenteditable="true" spellcheck="false" data-placeholder="<?php esc_attr_e('从这里开始写作，可直接粘贴或拖入图片…', 'xi'); ?>"><?php echo $content; ?></div>

                    <div class="xi-writer-status">
                        <span class="xi-writer-count">0 <?php esc_html_e('字', 'xi'); ?></span>
                        <span class="xi-writer-saved"></span>
                    </div>
                </div>
            </div>

            <aside class="xi-writer-side">
                <div class="xi-card xi-writer-card">
                    <div class="xi-writer-card-head">
                        <b><?php esc_html_e('发布', 'xi'); ?></b>
                        <span class="xi-uc-post-status is-<?php echo esc_attr($status); ?>"><?php echo esc_html(isset($status_names[$status]) ? $status_names[$status] : $status); ?></span>
                    </div>
                    <p class="xi-writer-hint">
                        <?php echo $need_review ? esc_html__('提交后需管理员审核，通过后公开显示', 'xi') : esc_html__('发布后立即公开显示', 'xi'); ?>
                    </p>
                    <div class="xi-writer-actions">
                        <?php if ($status !== 'publish') : ?>
                            <button type="button" class="xi-btn" data-save="draft"><?php esc_html_e('存草稿', 'xi'); ?></button>
                        <?php endif; ?>
                        <button type="button" class="xi-btn xi-btn-accent" data-save="publish"><?php echo $need_review ? esc_html__('提交审核', 'xi') : ($status === 'publish' ? esc_html__('更新', 'xi') : esc_html__('发布', 'xi')); ?></button>
                    </div>
                    <?php if ($post && $status === 'publish') : ?>
                        <a class="xi-writer-view" href="<?php echo esc_url(get_permalink($post)); ?>" target="_blank" rel="noopener"><?php esc_html_e('查看文章', 'xi'); ?><?php echo xi_icon('arrow'); ?></a>
                    <?php endif; ?>
                </div>

                <div class="xi-card xi-writer-card">
                    <label class="xi-writer-label" for="xi-writer-cat"><?php esc_html_e('分类', 'xi'); ?></label>
                    <select id="xi-writer-cat" name="cat" class="xi-writer-input">
                        <option value=""><?php esc_html_e('选择分类', 'xi'); ?></option>
                        <?php foreach (xi_write_categories() as $c) : ?>
                            <option value="<?php echo (int) $c->term_id; ?>" <?php selected($cat, $c->term_id); ?>><?php echo esc_html($c->name); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="xi-writer-label"><?php esc_html_e('标签', 'xi'); ?> <small><?php esc_html_e('最多 5 个，回车添加', 'xi'); ?></small></label>
                    <div class="xi-writer-tags xi-writer-input" data-tags="<?php echo esc_attr(implode(',', $tags)); ?>">
                        <input type="text" maxlength="20" placeholder="<?php esc_attr_e('添加标签', 'xi'); ?>">
                    </div>
                </div>

                <div class="xi-card xi-writer-card">
                    <label class="xi-writer-label"><?php esc_html_e('封面', 'xi'); ?></label>
                    <div class="xi-writer-cover<?php echo $thumb ? ' has-image' : ''; ?>" data-thumb="<?php echo (int) $thumb; ?>" role="button" tabindex="0">
                        <?php if ($thumb) : ?>
                            <img src="<?php echo esc_url(wp_get_attachment_image_url($thumb, 'post-thumbnail')); ?>" alt="">
                        <?php endif; ?>
                        <span class="xi-writer-cover-empty"><?php echo xi_icon('image'); ?><?php esc_html_e('点击上传封面', 'xi'); ?></span>
                        <button type="button" class="xi-writer-cover-remove" aria-label="<?php esc_attr_e('移除封面', 'xi'); ?>"><?php echo xi_icon('close'); ?></button>
                    </div>
                    <button type="button" class="xi-writer-cover-auto"><?php esc_html_e('使用正文第一张图片', 'xi'); ?></button>

                    <label class="xi-writer-label" for="xi-writer-excerpt"><?php esc_html_e('摘要', 'xi'); ?> <small><?php esc_html_e('选填', 'xi'); ?></small></label>
                    <textarea id="xi-writer-excerpt" name="excerpt" class="xi-writer-input" rows="3" maxlength="300" placeholder="<?php esc_attr_e('留空自动截取正文', 'xi'); ?>"><?php echo $post ? esc_textarea($post->post_excerpt) : ''; ?></textarea>
                </div>

                <?php if ($notice = _xz('write_notice', '')) : ?>
                    <div class="xi-card xi-writer-card xi-writer-notice">
                        <label class="xi-writer-label"><?php echo xi_icon('info'); ?><?php esc_html_e('投稿须知', 'xi'); ?></label>
                        <div><?php echo wp_kses_post(wpautop($notice)); ?></div>
                    </div>
                <?php endif; ?>
            </aside>

            <input type="file" class="xi-writer-file" accept="image/jpeg,image/png,image/gif,image/webp" hidden multiple>
            <input type="file" class="xi-writer-cover-file" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
        </form>

        <div class="xi-flyout xi-glass" hidden></div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
