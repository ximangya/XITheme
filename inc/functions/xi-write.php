<?php
/**
 * 前台投稿 / 编辑：/write/（未开启固定链接时为 ?xi_write=1），编辑时追加 ?post=ID
 * 可投稿、免审核、上传等权限按用户组在「权限管理」设置，可选分类、每日上限等在「前台投稿」设置
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_write_enabled()
{
    return (bool) _xz('write_s', true);
}

function xi_write_url($post_id = 0)
{
    $url = get_option('permalink_structure') ? home_url('/write/') : add_query_arg('xi_write', 1, home_url('/'));
    return $post_id ? add_query_arg('post', (int) $post_id, $url) : $url;
}

function xi_is_write_page()
{
    return (bool) get_query_var('xi_write');
}

function xi_write_query_vars($vars)
{
    $vars[] = 'xi_write';
    return $vars;
}
add_filter('query_vars', 'xi_write_query_vars');

function xi_write_template($template)
{
    if (xi_is_write_page()) {
        status_header(200);
        return get_theme_file_path('write.php');
    }
    return $template;
}
add_filter('template_include', 'xi_write_template');

function xi_write_title($title)
{
    if (xi_is_write_page()) {
        $title['title'] = !empty($_GET['post']) ? __('编辑文章', 'xi') : __('写文章', 'xi');
    }
    return $title;
}
add_filter('document_title_parts', 'xi_write_title');

function xi_write_body_class($classes)
{
    if (xi_is_write_page()) {
        $classes[] = 'xi-no-sidebar';
        $classes[] = 'xi-write-page';
    }
    return $classes;
}
add_filter('body_class', 'xi_write_body_class', 20);

function xi_write_noindex()
{
    if (xi_is_write_page()) {
        echo '<meta name="robots" content="noindex, nofollow">' . "\n";
    }
}
add_action('wp_head', 'xi_write_noindex', 1);

/* ---------- 权限 ---------- */

/**
 * 当前用户是否可直接发布（不经审核）
 */
function xi_write_can_publish($user_id = 0)
{
    return xi_user_can('publish', $user_id ? $user_id : get_current_user_id());
}

/**
 * 能否在前台发布新文章
 */
function xi_write_can_new($user_id = 0)
{
    return xi_write_enabled() && xi_user_can('write', $user_id ? $user_id : get_current_user_id());
}

/**
 * 能否在前台编辑某篇文章，返回 true 或错误
 */
function xi_write_can_edit($post_id, $user_id = 0)
{
    $user_id = $user_id ? $user_id : get_current_user_id();
    $post    = get_post($post_id);
    if (!$post || $post->post_type !== 'post' || $post->post_status === 'trash') {
        return new WP_Error('post', __('文章不存在', 'xi'));
    }
    // 编辑及以上可编辑他人文章的角色不受前台权限限制
    if (user_can($user_id, 'edit_others_posts') && user_can($user_id, 'edit_post', $post->ID)) {
        return true;
    }
    if ((int) $post->post_author !== (int) $user_id) {
        return new WP_Error('author', __('只能编辑自己的文章', 'xi'));
    }
    if ($post->post_status === 'publish') {
        if (!xi_user_can('edit_publish', $user_id)) {
            return new WP_Error('published', __('你所在的用户组不能修改已发布的文章', 'xi'));
        }
    } elseif (!xi_user_can('write', $user_id)) {
        return new WP_Error('write', __('你所在的用户组暂无投稿权限', 'xi'));
    }
    return true;
}

/**
 * 可选分类
 */
function xi_write_categories()
{
    $allow = array_filter(array_map('intval', (array) _xz('write_cats', array())));
    $args  = array('hide_empty' => false);
    if ($allow) {
        $args['include'] = $allow;
    }
    return get_categories($args);
}

/* ---------- Ajax ---------- */

function xi_write_ajax_check()
{
    if (!is_user_logged_in()) {
        wp_send_json(array('ok' => false, 'msg' => __('请先登录', 'xi')));
    }
    if (!xi_write_enabled()) {
        wp_send_json(array('ok' => false, 'msg' => __('前台投稿已关闭', 'xi')));
    }
    if (!check_ajax_referer('xi_write', 'nonce', false)) {
        wp_send_json(array('ok' => false, 'msg' => __('页面已过期，请刷新后重试（内容已自动保存在本地）', 'xi')));
    }
    return get_current_user_id();
}

function xi_ajax_write_save()
{
    $user_id = xi_write_ajax_check();
    // 被禁言 / IP 被封时连已有内容也不能修改
    if (function_exists('xi_ban_notice') && ($ban_notice = xi_ban_notice($user_id))) {
        wp_send_json(array('ok' => false, 'msg' => $ban_notice));
    }
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    $mode    = isset($_POST['mode']) && $_POST['mode'] === 'draft' ? 'draft' : 'publish';
    $old     = $post_id ? get_post($post_id) : null;

    if ($post_id) {
        $can = xi_write_can_edit($post_id, $user_id);
        if (is_wp_error($can)) {
            wp_send_json(array('ok' => false, 'msg' => $can->get_error_message()));
        }
    } elseif (!xi_user_can('write', $user_id)) {
        wp_send_json(array('ok' => false, 'msg' => __('你所在的用户组暂无投稿权限', 'xi')));
    } elseif (!xi_user_can('nolimit', $user_id)) {
        // 每日新投稿上限
        $limit = (int) _xz('write_daily_limit', 5);
        if ($limit > 0) {
            $today = get_posts(array(
                'author'      => $user_id,
                'post_status' => array('publish', 'pending', 'draft', 'future'),
                'date_query'  => array(array('after' => current_time('Y-m-d') . ' 00:00:00', 'inclusive' => true)),
                'fields'      => 'ids',
                'numberposts' => $limit + 1,
            ));
            if (count($today) >= $limit) {
                wp_send_json(array('ok' => false, 'msg' => sprintf(__('每天最多投稿 %d 篇', 'xi'), $limit)));
            }
        }
    }

    $title   = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
    $excerpt = isset($_POST['excerpt']) ? sanitize_textarea_field(wp_unslash($_POST['excerpt'])) : '';
    $plain   = trim((string) preg_replace('/\s+/u', '', wp_strip_all_tags($content)));

    if ($mode === 'publish') {
        if (mb_strlen($title) < 4) {
            wp_send_json(array('ok' => false, 'msg' => __('标题至少 4 个字', 'xi'), 'field' => 'title'));
        }
        $min = (int) _xz('write_min_length', 20);
        if (mb_strlen($plain) < $min && !preg_match('/<img\s/i', $content)) {
            wp_send_json(array('ok' => false, 'msg' => sprintf(__('正文至少 %d 个字', 'xi'), $min), 'field' => 'content'));
        }
    } elseif (!$title && !$plain) {
        wp_send_json(array('ok' => false, 'msg' => __('写点什么再保存吧', 'xi')));
    }

    // 分类必须在允许范围内
    $cat      = isset($_POST['cat']) ? (int) $_POST['cat'] : 0;
    $allowed  = wp_list_pluck(xi_write_categories(), 'term_id');
    $cat      = in_array($cat, array_map('intval', $allowed), true) ? $cat : 0;
    if ($mode === 'publish' && !$cat && $allowed) {
        wp_send_json(array('ok' => false, 'msg' => __('请选择分类', 'xi'), 'field' => 'cat'));
    }

    // 状态
    if ($mode === 'draft') {
        if ($old && $old->post_status === 'publish') {
            wp_send_json(array('ok' => false, 'msg' => __('已发布的文章不能存为草稿', 'xi')));
        }
        $status = 'draft';
    } elseif (xi_write_can_publish($user_id)) {
        $status = 'publish';
    } elseif ($old && $old->post_status === 'publish' && xi_user_can('edit_noreview', $user_id)) {
        $status = 'publish';
    } else {
        $status = 'pending';
    }

    $data = array(
        'post_title'   => $title,
        'post_content' => $content,
        'post_excerpt' => $excerpt,
        'post_status'  => $status,
        'post_type'    => 'post',
    );
    if ($cat) {
        $data['post_category'] = array($cat);
    }
    if ($post_id) {
        $data['ID'] = $post_id;
        $result     = wp_update_post(wp_slash($data), true);
    } else {
        $data['post_author'] = $user_id;
        $result              = wp_insert_post(wp_slash($data), true);
    }
    if (is_wp_error($result)) {
        wp_send_json(array('ok' => false, 'msg' => $result->get_error_message()));
    }
    $post_id = (int) $result;

    // 标签：最多 5 个，每个不超过 20 字
    $tags = isset($_POST['tags']) ? array_slice(array_filter(array_map(function ($tag) {
        return mb_substr(trim(sanitize_text_field($tag)), 0, 20);
    }, explode(',', wp_unslash($_POST['tags'])))), 0, 5) : array();
    wp_set_post_tags($post_id, $tags, false);

    // 封面：只能用自己上传的图片
    $thumb = isset($_POST['thumb']) ? (int) $_POST['thumb'] : 0;
    if ($thumb && wp_attachment_is_image($thumb) && ((int) get_post_field('post_author', $thumb) === $user_id || user_can($user_id, 'edit_others_posts'))) {
        set_post_thumbnail($post_id, $thumb);
    } elseif (!$thumb) {
        delete_post_thumbnail($post_id);
    }

    // 把本次上传但未关联的图片挂到文章下
    if (preg_match_all('/wp-image-(\d+)/', $content, $m)) {
        foreach (array_unique(array_map('intval', $m[1])) as $att_id) {
            $att = get_post($att_id);
            if ($att && $att->post_type === 'attachment' && !$att->post_parent && (int) $att->post_author === $user_id) {
                wp_update_post(array('ID' => $att_id, 'post_parent' => $post_id));
            }
        }
    }

    if ($status === 'pending' && (!$old || $old->post_status !== 'pending')) {
        xi_write_notify_admin($post_id);
    }

    $messages = array(
        'draft'   => __('草稿已保存', 'xi'),
        'pending' => __('已提交，审核通过后将公开显示', 'xi'),
        'publish' => __('发布成功', 'xi'),
    );
    wp_send_json(array(
        'ok'       => true,
        'msg'      => $messages[$status],
        'post_id'  => $post_id,
        'status'   => $status,
        'link'     => $status === 'publish' ? get_permalink($post_id) : '',
        'edit_url' => xi_write_url($post_id),
        'uc_url'   => xi_uc_url('posts'),
    ));
}
add_action('wp_ajax_xi_write_save', 'xi_ajax_write_save');

/**
 * 图片上传
 */
function xi_ajax_write_upload()
{
    $user_id = xi_write_ajax_check();
    if (!xi_user_can('upload', $user_id)) {
        wp_send_json(array('ok' => false, 'msg' => __('你所在的用户组暂无上传图片权限', 'xi')));
    }
    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if ($post_id && is_wp_error(xi_write_can_edit($post_id, $user_id))) {
        $post_id = 0;
    }
    $result = xi_upload_image($user_id, $post_id);
    wp_send_json(is_wp_error($result) ? array('ok' => false, 'msg' => $result->get_error_message()) : array('ok' => true) + $result);
}
add_action('wp_ajax_xi_write_upload', 'xi_ajax_write_upload');

/**
 * 处理 $_FILES['file'] 图片上传（投稿与论坛发帖共用），返回 id / url / thumb 或错误
 * 调用前需自行校验登录、nonce 与上传权限
 */
function xi_upload_image($user_id, $post_id = 0)
{
    if (empty($_FILES['file']) || !empty($_FILES['file']['error'])) {
        return new WP_Error('upload', __('上传失败，请重试', 'xi'));
    }

    $max = max(1, (int) _xz('write_upload_size', 5)) * MB_IN_BYTES;
    if ($_FILES['file']['size'] > $max) {
        return new WP_Error('size', sprintf(__('图片不能超过 %d MB', 'xi'), $max / MB_IN_BYTES));
    }
    $check = wp_check_filetype_and_ext($_FILES['file']['tmp_name'], $_FILES['file']['name']);
    if (!in_array($check['type'], array('image/jpeg', 'image/png', 'image/gif', 'image/webp'), true)) {
        return new WP_Error('type', __('仅支持 JPG、PNG、GIF、WebP 图片', 'xi'));
    }

    // 每日上传上限
    $key   = 'xi_upload_' . $user_id . '_' . current_time('Ymd');
    $count = (int) get_transient($key);
    if ($count >= 50 && !user_can($user_id, 'upload_files')) {
        return new WP_Error('limit', __('今日上传次数已达上限', 'xi'));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $att_id = media_handle_upload('file', (int) $post_id);
    if (is_wp_error($att_id)) {
        return $att_id;
    }
    set_transient($key, $count + 1, DAY_IN_SECONDS);

    $large = wp_get_attachment_image_src($att_id, 'large');
    return array(
        'id'    => $att_id,
        'url'   => $large ? $large[0] : wp_get_attachment_url($att_id),
        'thumb' => wp_get_attachment_image_url($att_id, 'post-thumbnail'),
    );
}


function xi_write_notify_admin($post_id)
{
    if (!_xz('write_notify', true)) {
        return;
    }
    $post   = get_post($post_id);
    $author = get_userdata($post->post_author);
    xi_send_mail(
        get_option('admin_email'),
        __('有新的待审核投稿', 'xi'),
        __('新的待审核投稿', 'xi'),
        '<p>' . sprintf(esc_html__('%1$s 提交了文章《%2$s》，等待审核。', 'xi'), esc_html($author ? $author->display_name : ''), esc_html($post->post_title)) . '</p>'
        . '<p><a href="' . esc_url(admin_url('edit.php?post_status=pending&post_type=post')) . '">' . esc_html__('前往审核', 'xi') . '</a></p>'
    );
}

/**
 * 编辑器工具栏按钮（投稿页、发帖页共用）
 */
function xi_writer_btn($cmd, $icon, $title, $extra = '')
{
    return '<button type="button" class="xi-tb-btn" data-cmd="' . esc_attr($cmd) . '" title="' . esc_attr($title) . '" aria-label="' . esc_attr($title) . '"' . $extra . '>' . xi_icon($icon) . '</button>';
}

/* ---------- 文章页编辑入口 ---------- */

function xi_write_edit_button()
{
    if (!is_user_logged_in() || !xi_write_enabled() || is_wp_error(xi_write_can_edit(get_the_ID()))) {
        return;
    }
    echo '<a class="xi-edit-link" href="' . esc_url(xi_write_url(get_the_ID())) . '">' . xi_icon('edit') . esc_html__('编辑', 'xi') . '</a>';
}

/**
 * 前台编辑器脚本
 */
function xi_write_enqueue()
{
    if (!xi_is_write_page() || !is_user_logged_in()) {
        return;
    }
    wp_enqueue_script('xi-editor', get_template_directory_uri() . '/assets/js/editor.js', array(), XI_VERSION, true);
    wp_localize_script('xi-editor', 'xiWrite', array(
        'ajax'      => admin_url('admin-ajax.php'),
        'nonce'     => wp_create_nonce('xi_write'),
        'upload'    => xi_user_can('upload'),
        'maxSize'   => max(1, (int) _xz('write_upload_size', 5)),
        'points'    => xi_points_enabled(),
        'vip'       => xi_vip_enabled(),
        'pointName' => xi_points_name(),
    ));
}
add_action('wp_enqueue_scripts', 'xi_write_enqueue');
