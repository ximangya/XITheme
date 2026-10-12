<?php
/**
 * 前台账号设置：资料、头像、邮箱、密码（用户中心「账号设置」标签）
 * 取代后台 profile.php，没有后台权限的用户完全不进入 wp-admin
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 个人资料地址：有后台权限的用户保留后台资料页，其余指向前台
 */
function xi_edit_profile_url($url, $user_id)
{
    return xi_user_can('admin', $user_id) ? $url : xi_uc_url('settings');
}
add_filter('edit_profile_url', 'xi_edit_profile_url', 10, 2);

/**
 * 邮箱脱敏显示：ab***@qq.com
 */
function xi_mask_email($email)
{
    $parts = explode('@', (string) $email, 2);
    if (count($parts) < 2) {
        return $email;
    }
    return mb_substr($parts[0], 0, 2) . '***@' . $parts[1];
}

/* ---------- 自定义头像 ---------- */

/**
 * 从 get_avatar 的各种参数里取出用户 ID
 */
function xi_avatar_user_id($id_or_email)
{
    if (is_numeric($id_or_email)) {
        return (int) $id_or_email;
    }
    if ($id_or_email instanceof WP_User) {
        return $id_or_email->ID;
    }
    if ($id_or_email instanceof WP_Post) {
        return (int) $id_or_email->post_author;
    }
    if ($id_or_email instanceof WP_Comment) {
        return (int) $id_or_email->user_id;
    }
    if (is_string($id_or_email) && is_email($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
        return $user ? $user->ID : 0;
    }
    return 0;
}

/**
 * 头像来源：cravatar / weavatar / gravatar / local（不请求任何外部头像服务）
 * 旧版只有「头像加速」开关：没有保存过新选项时按开关决定
 */
function xi_avatar_source()
{
    $source = _xz('avatar_source', '');
    if (!in_array($source, array('cravatar', 'weavatar', 'gravatar', 'local'), true)) {
        $source = _xz('gravatar_cn_s', true) ? 'cravatar' : 'gravatar';
    }
    return $source;
}

/**
 * 默认头像：后台上传的图片，未设置时用主题自带的图形
 */
function xi_default_avatar_url()
{
    $custom = _xz('avatar_default_img', '');
    if (is_array($custom)) {
        $custom = isset($custom['url']) ? $custom['url'] : '';
    }
    return $custom ? set_url_scheme($custom) : get_template_directory_uri() . '/assets/img/avatar.svg';
}

/**
 * 第三方登录带回的远程头像：一律走 https，避免 https 站点因混合内容被浏览器拦截
 */
function xi_remote_avatar_url($user_id)
{
    $url = (string) get_user_meta($user_id, 'xi_avatar_url', true);
    return $url ? set_url_scheme($url, 'https') : '';
}

/**
 * get_avatar 参数里的邮箱（评论访客、只有邮箱的情况）
 */
function xi_avatar_email($id_or_email, $user_id)
{
    if ($user_id) {
        $user = get_userdata($user_id);
        return $user ? $user->user_email : '';
    }
    if ($id_or_email instanceof WP_Comment) {
        return $id_or_email->comment_author_email;
    }
    return is_string($id_or_email) && is_email($id_or_email) ? $id_or_email : '';
}

/**
 * 头像地址
 * 1. 用户上传的头像；2. 第三方登录头像；3. 头像服务（自行用 MD5 拼地址，
 *    WordPress 6.8 起默认改为 SHA-256，部分国内镜像不认，导致一部分用户头像加载失败）；4. 默认头像
 */
function xi_avatar_data($args, $id_or_email)
{
    if (!empty($args['url'])) {
        return $args;
    }
    $user_id = xi_avatar_user_id($id_or_email);

    if ($user_id && empty($args['force_default'])) {
        $att_id = (int) get_user_meta($user_id, 'xi_avatar', true);
        $url    = $att_id ? wp_get_attachment_image_url($att_id, 'thumbnail') : '';
        $url    = $url ? $url : xi_remote_avatar_url($user_id);
        if ($url) {
            $args['url']          = $url;
            $args['found_avatar'] = true;
            return $args;
        }
    }

    // 只处理文章作者、用户、普通评论；pingback 等类型交给 WordPress
    if ($id_or_email instanceof WP_Comment && !in_array(get_comment_type($id_or_email), (array) apply_filters('get_avatar_comment_types', array('comment')), true)) {
        return $args;
    }

    $source = xi_avatar_source();
    $email  = strtolower(trim(xi_avatar_email($id_or_email, $user_id)));
    if ($source === 'local' || $email === '') {
        $args['url'] = xi_default_avatar_url();
        return $args;
    }

    $hosts = array(
        'cravatar' => 'https://cravatar.cn/avatar/',
        'weavatar' => 'https://weavatar.com/avatar/',
        'gravatar' => 'https://secure.gravatar.com/avatar/',
    );
    // 上传了默认头像时，头像服务找不到该邮箱的头像就回落到它（服务商只接受公网可访问的 JPG/PNG/GIF）
    $default = _xz('avatar_default_img', '') ? xi_default_avatar_url() : $args['default'];
    $query = array(
        's' => (int) $args['size'],
        'd' => $default ? $default : 'mm',
        'r' => $args['rating'] ? $args['rating'] : 'g',
    );
    if (!empty($args['force_default'])) {
        $query['f'] = 'y';
    }
    $args['url'] = add_query_arg(rawurlencode_deep($query), $hosts[$source] . md5($email));
    return $args;
}
add_filter('pre_get_avatar_data', 'xi_avatar_data', 20, 2);

/**
 * 其他插件或旧代码直接拼出的 Gravatar 地址也换成所选镜像
 */
function xi_avatar_mirror_url($url)
{
    $source = xi_avatar_source();
    if ($source === 'gravatar' || !is_string($url)) {
        return $url;
    }
    if ($source === 'local') {
        return preg_match('#^https?://(?:[a-z0-9-]+\.)?gravatar\.com/avatar/#i', $url) ? xi_default_avatar_url() : $url;
    }
    $host = $source === 'weavatar' ? 'https://weavatar.com/avatar/' : 'https://cravatar.cn/avatar/';
    return preg_replace('#^https?://(?:[a-z0-9-]+\.)?gravatar\.com/avatar/#i', $host, $url);
}
add_filter('get_avatar_url', 'xi_avatar_mirror_url', 20);

/**
 * 头像、封面变化后让缓存的侧栏片段（最新评论、作者卡片等）失效，
 * 否则片段里仍引用已被删除的旧图片，表现为「换了头像反而不显示」
 */
function xi_avatar_meta_changed($meta_id, $user_id, $meta_key)
{
    if (in_array($meta_key, array('xi_avatar', 'xi_cover', 'xi_avatar_url'), true)) {
        xi_cache_flush();
    }
}
add_action('added_user_meta', 'xi_avatar_meta_changed', 10, 3);
add_action('updated_user_meta', 'xi_avatar_meta_changed', 10, 3);
add_action('deleted_user_meta', 'xi_avatar_meta_changed', 10, 3);

/**
 * 头像图片加载失败（服务被墙、旧图被删、外链失效）时前端换成默认头像
 */
function xi_avatar_fallback_script()
{
    echo '<script>document.addEventListener("error",function(e){var t=e.target;if(t.tagName==="IMG"&&/(^|\s)avatar(\s|$)/.test(t.className)&&!t.dataset.xiFallback){t.dataset.xiFallback=1;t.removeAttribute("srcset");t.src=' . wp_json_encode(xi_default_avatar_url()) . ';}},true);</script>' . "\n";
}
add_action('wp_head', 'xi_avatar_fallback_script', 2);

/* ---------- Ajax ---------- */

/**
 * 基本资料：昵称、简介、网站
 */
function xi_ajax_profile_save()
{
    $user_id = xi_user_ajax_check();
    $name    = isset($_POST['display_name']) ? trim(sanitize_text_field(wp_unslash($_POST['display_name']))) : '';
    $desc    = isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '';
    $url     = isset($_POST['user_url']) ? esc_url_raw(wp_unslash($_POST['user_url'])) : '';

    if (mb_strlen($name) < 2 || mb_strlen($name) > 20) {
        xi_user_ajax_result(new WP_Error('name', __('昵称需为 2-20 个字', 'xi')));
    }
    if (mb_strlen($desc) > 200) {
        xi_user_ajax_result(new WP_Error('desc', __('个人简介不能超过 200 字', 'xi')));
    }

    $result = wp_update_user(array(
        'ID'           => $user_id,
        'display_name' => $name,
        'nickname'     => $name,
        'description'  => $desc,
        'user_url'     => $url,
    ));
    xi_user_ajax_result(is_wp_error($result) ? $result : __('资料已保存', 'xi'));
}
add_action('wp_ajax_xi_profile_save', 'xi_ajax_profile_save');

/**
 * 上传头像 / 个人主页封面：替换后删除旧图片
 */
function xi_ajax_avatar_upload()
{
    $user_id = xi_user_ajax_check();
    $cover   = isset($_POST['kind']) && $_POST['kind'] === 'cover';
    $max     = $cover ? 5 : 2;
    $label   = $cover ? __('封面', 'xi') : __('头像', 'xi');
    if (empty($_FILES['file']) || !empty($_FILES['file']['error'])) {
        xi_user_ajax_result(new WP_Error('file', __('上传失败，请重试', 'xi')));
    }
    if ($_FILES['file']['size'] > $max * MB_IN_BYTES) {
        xi_user_ajax_result(new WP_Error('size', sprintf(__('%1$s不能超过 %2$d MB', 'xi'), $label, $max)));
    }
    $check = wp_check_filetype_and_ext($_FILES['file']['tmp_name'], $_FILES['file']['name']);
    if (!in_array($check['type'], array('image/jpeg', 'image/png', 'image/gif', 'image/webp'), true)) {
        xi_user_ajax_result(new WP_Error('type', __('仅支持 JPG、PNG、GIF、WebP 图片', 'xi')));
    }
    $key = 'xi_avatar_up_' . $user_id;
    if ((int) get_transient($key) >= 10) {
        xi_user_ajax_result(new WP_Error('frequent', __('今日更换图片次数过多，请明天再试', 'xi')));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $att_id = media_handle_upload('file', 0, array('post_title' => sprintf(__('用户 %1$d 的%2$s', 'xi'), $user_id, $label)));
    if (is_wp_error($att_id)) {
        xi_user_ajax_result($att_id);
    }
    set_transient($key, (int) get_transient($key) + 1, DAY_IN_SECONDS);

    $url = wp_get_attachment_image_url($att_id, $cover ? 'large' : 'thumbnail');
    if (!$url) {
        wp_delete_attachment($att_id, true);
        xi_user_ajax_result(new WP_Error('file', __('图片处理失败，请换一张图片再试', 'xi')));
    }

    $meta = $cover ? 'xi_cover' : 'xi_avatar';
    $old  = (int) get_user_meta($user_id, $meta, true);
    // 先写入新头像（同时清除缓存片段），再删旧图，避免页面引用已删除的文件
    update_user_meta($user_id, $meta, $att_id);
    if ($old && $old !== $att_id && (int) get_post_field('post_author', $old) === $user_id) {
        wp_delete_attachment($old, true);
    }
    clean_user_cache($user_id);

    xi_user_ajax_result(sprintf(__('%s已更新', 'xi'), $label), array('url' => $url));
}
add_action('wp_ajax_xi_avatar_upload', 'xi_ajax_avatar_upload');

/**
 * 修改邮箱：开启「注册邮箱验证码」时需要新邮箱的验证码
 */
function xi_email_need_code()
{
    return (bool) _xz('sign_reg_email_s', false);
}

/**
 * 第三方登录自动创建的账号没有设置过密码：改邮箱、换手机、设置密码时不要求输入当前密码
 */
function xi_user_pass_unset($user_id)
{
    return (bool) get_user_meta($user_id, 'xi_pass_unset', true);
}

/**
 * 校验当前密码；未设置过密码的账号直接通过
 */
function xi_check_current_pass($user, $pass)
{
    return xi_user_pass_unset($user->ID) || wp_check_password($pass, $user->user_pass, $user->ID);
}

function xi_ajax_email_code()
{
    xi_user_ajax_check();
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    if (!is_email($email)) {
        xi_user_ajax_result(new WP_Error('email', __('请输入正确的邮箱地址', 'xi')));
    }
    $owner = email_exists($email);
    if ($owner) {
        xi_user_ajax_result(new WP_Error('email', (int) $owner === get_current_user_id() ? __('新邮箱与当前邮箱相同', 'xi') : __('该邮箱已被其他账号使用', 'xi')));
    }
    $sent = xi_send_email_code('email', $email);
    if (is_wp_error($sent)) {
        xi_user_ajax_result($sent);
    }
    wp_send_json(array('ok' => true, 'msg' => __('验证码已发送，请查收新邮箱', 'xi'), 'countdown' => 60));
}
add_action('wp_ajax_xi_email_code', 'xi_ajax_email_code');

function xi_ajax_email_save()
{
    $user_id = xi_user_ajax_check();
    $user    = get_userdata($user_id);
    $email   = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $pass    = isset($_POST['pass']) ? (string) wp_unslash($_POST['pass']) : '';

    if (!is_email($email)) {
        xi_user_ajax_result(new WP_Error('email', __('请输入正确的邮箱地址', 'xi')));
    }
    if (strtolower($email) === strtolower($user->user_email)) {
        xi_user_ajax_result(new WP_Error('email', __('新邮箱与当前邮箱相同', 'xi')));
    }
    if (email_exists($email)) {
        xi_user_ajax_result(new WP_Error('email', __('该邮箱已被其他账号使用', 'xi')));
    }
    if (!xi_check_current_pass($user, $pass)) {
        xi_user_ajax_result(new WP_Error('pass', __('当前密码错误', 'xi')));
    }
    $need_code = xi_email_need_code();
    if ($need_code) {
        $code   = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
        $verify = xi_verify_email_code('email', $email, $code, false);
        if (is_wp_error($verify)) {
            xi_user_ajax_result($verify);
        }
    }

    $result = wp_update_user(array('ID' => $user_id, 'user_email' => $email));
    if (is_wp_error($result)) {
        xi_user_ajax_result($result);
    }
    if ($need_code) {
        xi_consume_code('email', $email);
        update_user_meta($user_id, 'xi_email_verified', 1);
    } else {
        delete_user_meta($user_id, 'xi_email_verified');
    }
    xi_user_ajax_result(__('邮箱已修改', 'xi'));
}
add_action('wp_ajax_xi_email_save', 'xi_ajax_email_save');

/**
 * 修改密码：改后其他设备上的登录全部失效，当前设备保持登录
 */
function xi_ajax_password_save()
{
    $user_id = xi_user_ajax_check();
    $user    = get_userdata($user_id);
    $old     = isset($_POST['old_pass']) ? (string) wp_unslash($_POST['old_pass']) : '';
    $new     = isset($_POST['new_pass']) ? (string) wp_unslash($_POST['new_pass']) : '';
    $again   = isset($_POST['new_pass2']) ? (string) wp_unslash($_POST['new_pass2']) : '';

    $unset = xi_user_pass_unset($user_id);
    if (!$unset && !wp_check_password($old, $user->user_pass, $user_id)) {
        xi_user_ajax_result(new WP_Error('pass', __('当前密码错误', 'xi')));
    }
    if (strlen($new) < 6) {
        xi_user_ajax_result(new WP_Error('pass', __('新密码至少 6 位', 'xi')));
    }
    if ($new !== $again) {
        xi_user_ajax_result(new WP_Error('pass', __('两次输入的新密码不一致', 'xi')));
    }
    if (!$unset && $new === $old) {
        xi_user_ajax_result(new WP_Error('pass', __('新密码不能与当前密码相同', 'xi')));
    }

    // wp_update_user 会沿用当前会话令牌重写登录 Cookie，其他设备的会话在这里清除
    $result = wp_update_user(array('ID' => $user_id, 'user_pass' => $new));
    if (is_wp_error($result)) {
        xi_user_ajax_result($result);
    }
    delete_user_meta($user_id, 'xi_pass_unset');
    WP_Session_Tokens::get_instance($user_id)->destroy_others(wp_get_session_token());
    xi_user_ajax_result($unset ? __('密码已设置，之后可用用户名或邮箱 + 密码登录', 'xi') : __('密码已修改，其他设备需重新登录', 'xi'));
}
add_action('wp_ajax_xi_password_save', 'xi_ajax_password_save');
