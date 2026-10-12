<?php
/**
 * AI 文章摘要：调用站长自己配置的大模型接口，为文章生成摘要并显示在正文上方
 *
 * 接口格式：
 *   openai    OpenAI 兼容的 /chat/completions（DeepSeek、通义千问、Kimi、智谱、OpenAI 等）
 *   anthropic Anthropic Messages API（Claude）
 * 文章 Meta：xi_ai_summary 摘要、xi_ai_hash 生成时的内容指纹、xi_ai_manual 是否手动改过、xi_ai_model 生成用的模型
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_ai_enabled()
{
    return (bool) _xz('ai_summary_s', false) && trim((string) _xz('ai_api_key', '')) !== '';
}

function xi_ai_post_types()
{
    return array_filter((array) _xz('ai_post_types', array('post')));
}

function xi_ai_config()
{
    $format = _xz('ai_api_format', 'openai') === 'anthropic' ? 'anthropic' : 'openai';
    $url    = trim((string) _xz('ai_api_url', ''));
    $model  = trim((string) _xz('ai_model', ''));
    if ($format === 'anthropic') {
        $url   = $url !== '' ? $url : 'https://api.anthropic.com/v1/messages';
        $model = $model !== '' ? $model : 'claude-opus-5-5';
    }
    return array(
        'format' => $format,
        'url'    => $url,
        'key'    => trim((string) _xz('ai_api_key', '')),
        'model'  => $model,
    );
}

function xi_ai_default_prompt()
{
    return "你是一名中文编辑，负责为博客文章写摘要。\n要求：用简体中文写一段 {length} 字左右的摘要，概括文章的核心内容和结论；客观陈述，不要评价，不要出现「本文」「这篇文章」「作者」等字样；只输出摘要正文，不要标题、引号、Markdown 或换行。";
}

/**
 * 调用接口，返回生成的文字或 WP_Error
 */
function xi_ai_request($system, $user)
{
    $c = xi_ai_config();
    if ($c['key'] === '' || $c['url'] === '' || $c['model'] === '') {
        return new WP_Error('config', __('请先填写接口地址、API Key 和模型名称', 'xi'));
    }

    if ($c['format'] === 'anthropic') {
        $headers = array(
            'x-api-key'         => $c['key'],
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        );
        // 思考与正文共用 max_tokens，留足余量，避免摘要被截断
        $body = array(
            'model'      => $c['model'],
            'max_tokens' => 8000,
            'system'     => $system,
            'messages'   => array(array('role' => 'user', 'content' => $user)),
        );
        // 官方接口上的新模型：被安全分类器拒绝时自动换模型重试
        $host = (string) wp_parse_url($c['url'], PHP_URL_HOST);
        if ($host === 'api.anthropic.com' && in_array($c['model'], array('claude-opus-5-5', 'claude-opus-5', 'claude-fable-5-1', 'claude-sonnet-5-5'), true)) {
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
            $body['fallbacks']         = 'default';
        }
    } else {
        $headers = array(
            'Authorization' => 'Bearer ' . $c['key'],
            'Content-Type'  => 'application/json',
        );
        $body = array(
            'model'    => $c['model'],
            'messages' => array(
                array('role' => 'system', 'content' => $system),
                array('role' => 'user', 'content' => $user),
            ),
            'stream'   => false,
        );
    }

    $res = wp_remote_post($c['url'], array(
        'timeout' => (int) apply_filters('xi_ai_timeout', 90),
        'headers' => $headers,
        'body'    => wp_json_encode($body, JSON_UNESCAPED_UNICODE),
    ));
    if (is_wp_error($res)) {
        return new WP_Error('http', sprintf(__('请求失败：%s', 'xi'), $res->get_error_message()));
    }
    $code = (int) wp_remote_retrieve_response_code($res);
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if ($code < 200 || $code >= 300) {
        $msg = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : (is_array($data) && isset($data['message']) ? $data['message'] : wp_remote_retrieve_body($res));
        return new WP_Error('api', sprintf(__('接口返回 %1$d：%2$s', 'xi'), $code, mb_substr(wp_strip_all_tags((string) $msg), 0, 200)));
    }

    $text = '';
    if ($c['format'] === 'anthropic') {
        if (isset($data['stop_reason']) && $data['stop_reason'] === 'refusal') {
            return new WP_Error('refusal', __('模型拒绝为这篇文章生成摘要', 'xi'));
        }
        foreach ((array) (isset($data['content']) ? $data['content'] : array()) as $block) {
            if (is_array($block) && isset($block['type'], $block['text']) && $block['type'] === 'text') {
                $text .= $block['text'];
            }
        }
    } elseif (isset($data['choices'][0]['message']['content'])) {
        $text = (string) $data['choices'][0]['message']['content'];
    }
    // 部分推理模型会把思考过程放在 <think> 标签里
    $text = trim(preg_replace('#<think>.*?</think>#s', '', $text));
    if ($text === '') {
        return new WP_Error('empty', __('接口没有返回内容，请检查模型名称', 'xi'));
    }
    return $text;
}

/**
 * 发给模型的文章正文：去掉隐藏 / 付费内容，超长时只取前面一段（在设置里说明）
 */
function xi_ai_post_text($post)
{
    $text = function_exists('xi_strip_hidden') ? xi_strip_hidden($post->post_content) : $post->post_content;
    // 区块编辑器写的内容（引言、代码等）只存了属性，先渲染成 HTML 才有文字
    if (function_exists('has_blocks') && has_blocks($text)) {
        $text = do_blocks($text);
    }
    $text = preg_replace('#</(p|div|h[1-6]|li|blockquote|pre|td|th)>|<br\s*/?>#i', "$0\n", $text);
    $text = wp_strip_all_tags(strip_shortcodes($text));
    $text = trim((string) preg_replace("/[ \t]+/u", ' ', preg_replace("/\n{3,}/", "\n\n", $text)));
    $max  = max(1000, (int) _xz('ai_max_input', 8000));
    return mb_strlen($text) > $max ? mb_substr($text, 0, $max) : $text;
}

function xi_ai_content_hash($post)
{
    return md5($post->post_title . '|' . $post->post_content);
}

/**
 * 生成并保存摘要，返回摘要文字或 WP_Error
 */
function xi_ai_generate($post_id)
{
    $post = get_post($post_id);
    if (!$post || !in_array($post->post_type, xi_ai_post_types(), true)) {
        return new WP_Error('post', __('文章不存在或不在生成范围内', 'xi'));
    }
    $body = xi_ai_post_text($post);
    if (mb_strlen($body) < 50) {
        return new WP_Error('short', __('正文太短，不需要摘要', 'xi'));
    }
    $length = max(30, (int) _xz('ai_length', 150));
    $prompt = trim((string) _xz('ai_prompt', ''));
    $prompt = str_replace('{length}', (string) $length, $prompt !== '' ? $prompt : xi_ai_default_prompt());
    $text   = xi_ai_request($prompt, "文章标题：" . $post->post_title . "\n\n文章正文：\n" . $body);
    if (is_wp_error($text)) {
        update_post_meta($post_id, 'xi_ai_error', $text->get_error_message());
        return $text;
    }
    $text = trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags($text)), " \"'“”「」");
    update_post_meta($post_id, 'xi_ai_summary', $text);
    update_post_meta($post_id, 'xi_ai_hash', xi_ai_content_hash($post));
    update_post_meta($post_id, 'xi_ai_model', xi_ai_config()['model']);
    delete_post_meta($post_id, 'xi_ai_manual');
    delete_post_meta($post_id, 'xi_ai_error');
    return $text;
}

function xi_ai_get_summary($post_id)
{
    return (string) get_post_meta($post_id, 'xi_ai_summary', true);
}

/* ---------- 发布时自动生成（后台计划任务，不拖慢保存） ---------- */

function xi_ai_on_publish($new, $old, $post)
{
    if ($new !== 'publish' || !xi_ai_enabled() || !_xz('ai_auto', true) || !in_array($post->post_type, xi_ai_post_types(), true)) {
        return;
    }
    $summary = xi_ai_get_summary($post->ID);
    $changed = get_post_meta($post->ID, 'xi_ai_hash', true) !== xi_ai_content_hash($post);
    // 没有摘要，或内容改过且摘要不是手动写的
    if ($summary === '' || ($changed && !get_post_meta($post->ID, 'xi_ai_manual', true))) {
        if (!wp_next_scheduled('xi_ai_summary_event', array($post->ID))) {
            wp_schedule_single_event(time() + 5, 'xi_ai_summary_event', array($post->ID));
        }
    }
}
add_action('transition_post_status', 'xi_ai_on_publish', 10, 3);

function xi_ai_summary_event($post_id)
{
    if (xi_ai_enabled()) {
        xi_ai_generate((int) $post_id);
    }
}
add_action('xi_ai_summary_event', 'xi_ai_summary_event');

/* ---------- 后台：文章编辑页的摘要框 ---------- */

function xi_ai_meta_box()
{
    if (!_xz('ai_summary_s', false)) {
        return;
    }
    foreach (xi_ai_post_types() as $type) {
        add_meta_box('xi_ai_summary', __('AI 摘要', 'xi'), 'xi_ai_meta_box_html', $type, 'side', 'default');
    }
}
add_action('add_meta_boxes', 'xi_ai_meta_box');

function xi_ai_meta_box_html($post)
{
    wp_nonce_field('xi_ai_box', 'xi_ai_box_nonce');
    $summary = xi_ai_get_summary($post->ID);
    $error   = get_post_meta($post->ID, 'xi_ai_error', true);
    echo '<textarea name="xi_ai_summary" id="xi-ai-summary" rows="6" style="width:100%" placeholder="' . esc_attr__('发布后自动生成，也可以手动填写', 'xi') . '">' . esc_textarea($summary) . '</textarea>';
    if ($error) {
        echo '<p style="color:#d63638;margin:6px 0">' . esc_html(sprintf(__('上次生成失败：%s', 'xi'), $error)) . '</p>';
    }
    echo '<p style="display:flex;align-items:center;gap:8px;margin:8px 0 0"><button type="button" class="button" id="xi-ai-gen" data-post="' . (int) $post->ID . '" data-nonce="' . esc_attr(wp_create_nonce('xi_ai_gen')) . '"' . (xi_ai_enabled() ? '' : ' disabled') . '>' . esc_html($summary ? __('重新生成', 'xi') : __('立即生成', 'xi')) . '</button><span id="xi-ai-msg" style="color:#646970"></span></p>';
    echo '<p style="color:#646970;margin:6px 0 0">' . esc_html__('基于已保存的内容生成；手动改过的摘要在修改文章时不会被自动覆盖', 'xi') . '</p>';
    ?>
    <script>
    (function () {
        var btn = document.getElementById('xi-ai-gen');
        if (!btn) return;
        btn.addEventListener('click', function () {
            var msg = document.getElementById('xi-ai-msg');
            var data = new FormData();
            data.append('action', 'xi_ai_generate');
            data.append('post', btn.getAttribute('data-post'));
            data.append('nonce', btn.getAttribute('data-nonce'));
            btn.disabled = true;
            msg.textContent = '<?php echo esc_js(__('生成中…', 'xi')); ?>';
            fetch(ajaxurl, { method: 'POST', body: data, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .catch(function () { return { ok: false, msg: '<?php echo esc_js(__('网络错误', 'xi')); ?>' }; })
                .then(function (res) {
                    btn.disabled = false;
                    msg.textContent = res.msg;
                    msg.style.color = res.ok ? '#008a20' : '#d63638';
                    if (res.ok) document.getElementById('xi-ai-summary').value = res.summary;
                });
        });
    })();
    </script>
    <?php
}

function xi_ai_save_box($post_id)
{
    if (!isset($_POST['xi_ai_box_nonce']) || !wp_verify_nonce(sanitize_key($_POST['xi_ai_box_nonce']), 'xi_ai_box') || !current_user_can('edit_post', $post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    $new = isset($_POST['xi_ai_summary']) ? sanitize_textarea_field(wp_unslash($_POST['xi_ai_summary'])) : '';
    if ($new === xi_ai_get_summary($post_id)) {
        return;
    }
    if ($new === '') {
        delete_post_meta($post_id, 'xi_ai_summary');
        delete_post_meta($post_id, 'xi_ai_manual');
    } else {
        update_post_meta($post_id, 'xi_ai_summary', $new);
        update_post_meta($post_id, 'xi_ai_manual', 1);
    }
}
add_action('save_post', 'xi_ai_save_box');

function xi_ajax_ai_generate()
{
    $post_id = isset($_POST['post']) ? (int) $_POST['post'] : 0;
    if (!check_ajax_referer('xi_ai_gen', 'nonce', false) || !$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json(array('ok' => false, 'msg' => __('没有权限', 'xi')));
    }
    if (!xi_ai_enabled()) {
        wp_send_json(array('ok' => false, 'msg' => __('请先在主题设置中开启 AI 摘要并填写接口', 'xi')));
    }
    $result = xi_ai_generate($post_id);
    if (is_wp_error($result)) {
        wp_send_json(array('ok' => false, 'msg' => $result->get_error_message()));
    }
    wp_send_json(array('ok' => true, 'msg' => __('已生成', 'xi'), 'summary' => $result));
}
add_action('wp_ajax_xi_ai_generate', 'xi_ajax_ai_generate');

/* ---------- 设置页：测试接口、批量生成 ---------- */

function xi_ajax_ai_test()
{
    if (!check_ajax_referer('xi_ai_tools', 'nonce', false) || !current_user_can('manage_options')) {
        wp_send_json(array('ok' => false, 'msg' => __('没有权限', 'xi')));
    }
    $text = xi_ai_request('你是接口连通性测试助手。', '请只回复：连接成功');
    if (is_wp_error($text)) {
        wp_send_json(array('ok' => false, 'msg' => $text->get_error_message()));
    }
    wp_send_json(array('ok' => true, 'msg' => sprintf(__('接口可用，模型回复：%s', 'xi'), mb_substr($text, 0, 60))));
}
add_action('wp_ajax_xi_ai_test', 'xi_ajax_ai_test');

function xi_ajax_ai_pending()
{
    if (!check_ajax_referer('xi_ai_tools', 'nonce', false) || !current_user_can('manage_options')) {
        wp_send_json(array('ok' => false, 'msg' => __('没有权限', 'xi')));
    }
    $ids = get_posts(array(
        'post_type'      => xi_ai_post_types(),
        'post_status'    => 'publish',
        'posts_per_page' => 500,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(array('key' => 'xi_ai_summary', 'compare' => 'NOT EXISTS')),
    ));
    wp_send_json(array('ok' => true, 'ids' => array_map('intval', $ids), 'nonce' => wp_create_nonce('xi_ai_gen')));
}
add_action('wp_ajax_xi_ai_pending', 'xi_ajax_ai_pending');

/**
 * 设置页里的「测试接口」「批量生成」按钮（CSF callback 字段）
 */
function xi_ai_tools_field()
{
    $nonce = wp_create_nonce('xi_ai_tools');
    echo '<p style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0">';
    echo '<button type="button" class="button" id="xi-ai-test">' . esc_html__('测试接口', 'xi') . '</button>';
    echo '<button type="button" class="button button-primary" id="xi-ai-bulk">' . esc_html__('为还没有摘要的文章批量生成', 'xi') . '</button>';
    echo '<span id="xi-ai-tools-msg" style="color:#646970"></span></p>';
    echo '<p style="color:#646970;margin:8px 0 0">' . esc_html__('请先保存设置再测试。批量生成会逐篇调用接口，期间请勿关闭页面；每篇都会消耗接口额度', 'xi') . '</p>';
    ?>
    <script>
    (function () {
        var msg = document.getElementById('xi-ai-tools-msg');
        var post = function (data) {
            var fd = new FormData();
            Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
            return fetch(ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).catch(function () { return { ok: false, msg: '网络错误' }; });
        };
        var say = function (text, ok) { msg.textContent = text; msg.style.color = ok === false ? '#d63638' : (ok ? '#008a20' : '#646970'); };
        document.getElementById('xi-ai-test').addEventListener('click', function () {
            say('测试中…');
            post({ action: 'xi_ai_test', nonce: '<?php echo esc_js($nonce); ?>' }).then(function (r) { say(r.msg, r.ok); });
        });
        document.getElementById('xi-ai-bulk').addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            say('正在查找…');
            post({ action: 'xi_ai_pending', nonce: '<?php echo esc_js($nonce); ?>' }).then(function (r) {
                if (!r.ok) { say(r.msg, false); btn.disabled = false; return; }
                var ids = r.ids, done = 0, fail = 0;
                if (!ids.length) { say('所有文章都已有摘要', true); btn.disabled = false; return; }
                var next = function () {
                    if (!ids.length) { say('完成：成功 ' + done + ' 篇' + (fail ? '，失败 ' + fail + ' 篇（可在文章编辑页查看原因）' : ''), !fail); btn.disabled = false; return; }
                    var id = ids.shift();
                    say('生成中… 已完成 ' + (done + fail) + '，剩余 ' + (ids.length + 1));
                    post({ action: 'xi_ai_generate', post: id, nonce: r.nonce }).then(function (res) { if (res.ok) done++; else fail++; next(); });
                };
                next();
            });
        });
    })();
    </script>
    <?php
}

/* ---------- 前台显示 ---------- */

function xi_ai_summary_box($post_id = 0)
{
    $post_id = $post_id ? $post_id : get_the_ID();
    if (!_xz('ai_summary_s', false) || !_xz('ai_show', true) || !in_array(get_post_type($post_id), xi_ai_post_types(), true)) {
        return;
    }
    $summary = xi_ai_get_summary($post_id);
    if ($summary === '') {
        return;
    }
    $model = get_post_meta($post_id, 'xi_ai_model', true);
    $manual = get_post_meta($post_id, 'xi_ai_manual', true);
    echo '<div class="xi-ai-summary">';
    echo '<div class="xi-ai-head"><span class="xi-ai-badge">' . xi_icon('star') . esc_html(_xz('ai_title', 'AI 摘要')) . '</span>';
    if (!$manual && $model && _xz('ai_show_model', false)) {
        echo '<small>' . esc_html(sprintf(__('由 %s 生成', 'xi'), $model)) . '</small>';
    }
    echo '</div><p class="xi-ai-text"' . (_xz('ai_typing', true) ? ' data-xi-typing' : '') . '>' . esc_html($summary) . '</p></div>';
}

/**
 * 没有手写摘要时，用 AI 摘要作为文章摘要与 SEO 描述
 */
function xi_ai_excerpt($excerpt, $post)
{
    if ($excerpt !== '' || !_xz('ai_summary_s', false) || !_xz('ai_as_excerpt', true) || !$post) {
        return $excerpt;
    }
    return xi_ai_get_summary($post->ID);
}
add_filter('xi_ai_excerpt', 'xi_ai_excerpt', 10, 2);
