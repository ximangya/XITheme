<?php
/**
 * The template for displaying comments
 *
 * This is the template that displays the area of the page that contains both the current comments
 * and the comment form.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package xi
 */

/*
 * If the current post is protected by a password and
 * the visitor has not yet entered the password we will
 * return early without loading the comments.
 */
if (post_password_required()) {
    return;
}

$commenter = wp_get_current_commenter();
$req = get_option('require_name_email');
$req_mark = $req ? ' *' : '';
$denied = xi_comment_denied();
?>
<section id="comments" class="xi-card xi-comments">
    <?php if (have_comments()): ?>
        <h3 class="xi-comments-title">
            <?php printf(esc_html__('%s 条评论', 'xi'), number_format_i18n(get_comments_number())); ?>
        </h3>

        <ol class="xi-comment-list">
            <?php
            wp_list_comments(array(
                'style' => 'ol',
                'short_ping' => true,
                'avatar_size' => 40,
            ));
            ?>
        </ol>

        <?php the_comments_pagination(array('prev_text' => '‹', 'next_text' => '›')); ?>
    <?php endif; ?>

    <?php if ($denied): ?>
        <div class="xi-comments-denied">
            <?php echo xi_icon('lock'); ?>
            <?php if ($ban_notice = (function_exists('xi_ban_notice') ? xi_ban_notice() : '')): ?>
                <span><?php echo esc_html($ban_notice); ?></span>
            <?php elseif (is_user_logged_in()): ?>
                <span><?php esc_html_e('评论失败权限似乎不足喵~', 'xi'); ?></span>
            <?php else: ?>
                <span><?php esc_html_e('登录后才能发表评论喵~', 'xi'); ?></span>
                <a class="xi-btn xi-btn-accent xi-btn-sm" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>"
                    data-xi-sign="login"><?php esc_html_e('登录', 'xi'); ?></a>
            <?php endif; ?>
        </div>
    <?php elseif (!comments_open() && get_comments_number()): ?>
        <p class="xi-comments-closed"><?php esc_html_e('作者已关闭评论喵~', 'xi'); ?></p>
    <?php endif; ?>

    <?php
    comment_form(array(
        'title_reply' => __('发表评论', 'xi'),
        'title_reply_before' => '<h3 id="reply-title" class="comment-reply-title">',
        'title_reply_after' => '</h3>',
        'cancel_reply_link' => __('取消评论', 'xi'),
        'label_submit' => __('提交评论', 'xi'),
        'class_submit' => 'xi-btn xi-btn-accent',
        'comment_notes_before' => '',
        'comment_notes_after' => '',
        'logged_in_as' => '',
        'comment_field' => '<p class="comment-form-comment"><label class="screen-reader-text" for="comment">' . esc_html__('评论', 'xi') . '</label><textarea id="comment" name="comment" rows="4" required placeholder="' . esc_attr__('写下你的想法…', 'xi') . '"></textarea></p>'
            . (_xz('comment_emoji_s', true) ? '<div class="xi-comment-tools"><button type="button" class="xi-emoji-btn" data-xi-emoji aria-haspopup="true" aria-expanded="false"><span aria-hidden="true">😊</span>' . esc_html__('表情', 'xi') . '</button></div>' : ''),
        'fields' => array(
            'author' => '<div class="xi-comment-fields"><p class="comment-form-author"><label class="screen-reader-text" for="author">' . esc_html__('昵称', 'xi') . '</label><input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" placeholder="' . esc_attr__('昵称', 'xi') . $req_mark . '"' . ($req ? ' required' : '') . '></p>',
            'email' => '<p class="comment-form-email"><label class="screen-reader-text" for="email">' . esc_html__('邮箱', 'xi') . '</label><input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_author_email']) . '" placeholder="' . esc_attr__('邮箱', 'xi') . $req_mark . '"' . ($req ? ' required' : '') . '></p>',
            'url' => '<p class="comment-form-url"><label class="screen-reader-text" for="url">' . esc_html__('网站', 'xi') . '</label><input id="url" name="url" type="url" value="' . esc_attr($commenter['comment_author_url']) . '" placeholder="' . esc_attr__('网站（选填）', 'xi') . '"></p></div>',
            'cookies' => '',
        ),
    ));
    ?>
</section>