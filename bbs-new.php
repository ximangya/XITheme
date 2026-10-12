<?php
/**
 * 论坛发帖 / 编辑帖子（复用前台投稿编辑器 assets/js/editor.js）
 */

get_header();

$user    = wp_get_current_user();
$post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
$error   = '';
$post    = null;

if ($user->exists() && function_exists('xi_ban_notice') && ($ban_notice = xi_ban_notice($user->ID))) {
    $error = $ban_notice;
} elseif ($user->exists() && $post_id) {
    $can = xi_bbs_can_edit($post_id, $user->ID);
    if (is_wp_error($can)) {
        $error = $can->get_error_message();
    } else {
        $post = get_post($post_id);
    }
} elseif ($user->exists() && !xi_user_can('bbs_post', $user->ID)) {
    $error = __('你所在的用户组暂无发帖权限', 'xi');
}

$plates  = $user->exists() ? xi_bbs_postable_plates($user->ID) : array();
$current = isset($_GET['plate']) ? (int) $_GET['plate'] : 0;
$content = '';
$topics  = array();
if ($post) {
    $content = $post->post_content;
    if (!has_blocks($content) && strpos($content, '<p') === false) {
        $content = wpautop($content);
    }
    $content = function_exists('xi_blocks_for_writer') ? xi_blocks_for_writer($content) : $content;
    $old     = xi_bbs_post_plate($post->ID);
    $current = $old ? $old->term_id : 0;
    $topics  = wp_get_post_terms($post->ID, 'forum_topic', array('fields' => 'names'));
    // 帖子所在版块即使当前用户无权在其中发新帖，编辑时也要能选中
    if ($old && !in_array($old->term_id, array_map(function ($row) {
        return $row['plate']->term_id;
    }, $plates), true)) {
        $plates[] = array('group' => '', 'plate' => $old);
    }
}
if (!$error && $user->exists() && !$plates) {
    $error = __('暂时没有可以发帖的版块', 'xi');
}
$need_review = $user->exists() && !xi_user_can('bbs_publish', $user->ID) && !($post && xi_bbs_can_manage($post->ID, $user->ID));
?>

<main class="xi-main xi-write">
    <?php xi_breadcrumb(); ?>
    <?php if (!$user->exists()) : ?>
        <div class="xi-card xi-uc-guest">
            <span class="xi-user-guest is-lg"><?php echo xi_icon('chat'); ?></span>
            <h1><?php esc_html_e('登录后即可发帖', 'xi'); ?></h1>
            <p><?php esc_html_e('提问、分享、交流，和大家一起讨论', 'xi'); ?></p>
            <div class="xi-uc-actions"><?php echo xi_login_buttons(); ?></div>
        </div>
    <?php elseif ($error) : ?>
        <div class="xi-card xi-uc-guest">
            <span class="xi-user-guest is-lg"><?php echo xi_icon('lock'); ?></span>
            <h1><?php echo esc_html($error); ?></h1>
            <div class="xi-uc-actions"><a class="xi-btn" href="<?php echo esc_url(xi_bbs_url()); ?>"><?php esc_html_e('返回论坛', 'xi'); ?></a></div>
        </div>
    <?php else : ?>
        <form class="xi-writer xi-bbs-writer" data-post-id="<?php echo (int) $post_id; ?>" autocomplete="off" novalidate>
            <div class="xi-writer-main">
                <div class="xi-card xi-writer-paper">
                    <textarea class="xi-writer-title" name="title" rows="1" maxlength="80" placeholder="<?php esc_attr_e('帖子标题', 'xi'); ?>"><?php echo $post ? esc_textarea($post->post_title) : ''; ?></textarea>

                    <?php get_template_part('template-parts/writer-toolbar'); ?>

                    <div class="xi-writer-content xi-content" contenteditable="true" spellcheck="false" data-placeholder="<?php esc_attr_e('说说你的想法，可直接粘贴或拖入图片…', 'xi'); ?>"><?php echo $content; ?></div>

                    <div class="xi-writer-status">
                        <span class="xi-writer-count">0 <?php esc_html_e('字', 'xi'); ?></span>
                        <span class="xi-writer-saved"></span>
                    </div>
                </div>
            </div>

            <aside class="xi-writer-side">
                <div class="xi-card xi-writer-card">
                    <div class="xi-writer-card-head">
                        <b><?php echo $post ? esc_html__('编辑帖子', 'xi') : esc_html__('发布帖子', 'xi'); ?></b>
                        <?php if ($post && $post->post_status === 'pending') : ?>
                            <span class="xi-uc-post-status is-pending"><?php esc_html_e('审核中', 'xi'); ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="xi-writer-hint"><?php echo $need_review ? esc_html__('提交后需审核，通过后公开显示', 'xi') : esc_html__('发布后立即公开显示', 'xi'); ?></p>
                    <div class="xi-writer-actions">
                        <button type="button" class="xi-btn xi-btn-accent" data-save="publish"><?php echo $need_review ? esc_html__('提交审核', 'xi') : ($post ? esc_html__('保存', 'xi') : esc_html__('发布', 'xi')); ?></button>
                    </div>
                    <?php if ($post && $post->post_status === 'publish') : ?>
                        <a class="xi-writer-view" href="<?php echo esc_url(get_permalink($post)); ?>"><?php esc_html_e('查看帖子', 'xi'); ?><?php echo xi_icon('arrow'); ?></a>
                    <?php endif; ?>
                </div>

                <div class="xi-card xi-writer-card">
                    <label class="xi-writer-label" for="xi-writer-cat"><?php esc_html_e('版块', 'xi'); ?></label>
                    <select id="xi-writer-cat" name="cat" class="xi-writer-input">
                        <option value=""><?php esc_html_e('选择版块', 'xi'); ?></option>
                        <?php
                        $group = null;
                        foreach ($plates as $row) {
                            if ($row['group'] !== $group) {
                                echo $group !== null ? '</optgroup>' : '';
                                $group = $row['group'];
                                echo '<optgroup label="' . esc_attr($group !== '' ? $group : __('版块', 'xi')) . '">';
                            }
                            echo '<option value="' . (int) $row['plate']->term_id . '"' . selected($current, $row['plate']->term_id, false) . '>' . esc_html($row['plate']->name) . '</option>';
                        }
                        echo $group !== null ? '</optgroup>' : '';
                        ?>
                    </select>

                    <label class="xi-writer-label"><?php esc_html_e('话题', 'xi'); ?> <small><?php esc_html_e('最多 3 个，回车添加', 'xi'); ?></small></label>
                    <div class="xi-writer-tags xi-writer-input" data-tags="<?php echo esc_attr(implode(',', $topics)); ?>">
                        <input type="text" maxlength="20" placeholder="<?php esc_attr_e('添加话题', 'xi'); ?>" list="xi-bbs-topic-list">
                    </div>
                    <?php
                    $hot = get_terms(array('taxonomy' => 'forum_topic', 'orderby' => 'count', 'order' => 'DESC', 'number' => 30, 'hide_empty' => false));
                    if ($hot && !is_wp_error($hot)) {
                        echo '<datalist id="xi-bbs-topic-list">';
                        foreach ($hot as $t) {
                            echo '<option value="' . esc_attr($t->name) . '">';
                        }
                        echo '</datalist>';
                    }
                    ?>
                </div>

                <?php if ($notice = _xz('bbs_notice', '')) : ?>
                    <div class="xi-card xi-writer-card xi-writer-notice">
                        <label class="xi-writer-label"><?php echo xi_icon('info'); ?><?php esc_html_e('发帖须知', 'xi'); ?></label>
                        <div><?php echo wp_kses_post(wpautop($notice)); ?></div>
                    </div>
                <?php endif; ?>
            </aside>

            <input type="file" class="xi-writer-file" accept="image/jpeg,image/png,image/gif,image/webp" hidden multiple>
        </form>

        <div class="xi-flyout xi-glass" hidden></div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
