<?php
/**
 * 外链安全跳转：文章、评论里的站外链接先经过提示页 /?golink=… 再离开本站
 * 链接带签名，只有本站生成的跳转地址有效，不会被别人拿来当跳板
 * 设置项：go_s、go_scope（content / comment / author）、go_whitelist、go_wait
 */

if (!defined('ABSPATH')) {
    exit;
}

function xi_go_enabled()
{
    return (bool) _xz('go_s', false);
}

function xi_go_sign($url)
{
    return substr(hash_hmac('sha256', $url, wp_salt('nonce')), 0, 12);
}

/**
 * 站外跳转地址
 */
function xi_go_url($url)
{
    $code = rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    return add_query_arg(array('golink' => $code, 'sig' => xi_go_sign($url)), home_url('/'));
}

/**
 * 白名单：本站域名 + 后台填写的域名（含子域名）
 */
function xi_go_whitelist()
{
    static $list = null;
    if ($list !== null) {
        return $list;
    }
    $list = array(strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST)));
    foreach (preg_split('/[\s,，]+/', (string) _xz('go_whitelist', '')) as $host) {
        $host = strtolower(trim(preg_replace('#^https?://#i', '', $host), " /\t"));
        if ($host !== '') {
            $list[] = $host;
        }
    }
    return $list;
}

function xi_go_is_external($url)
{
    if (!preg_match('#^(https?:)?//#i', $url)) {
        return false; // 站内相对地址、锚点、mailto、tel 等
    }
    $host = strtolower((string) wp_parse_url(strpos($url, '//') === 0 ? 'http:' . $url : $url, PHP_URL_HOST));
    if ($host === '') {
        return false;
    }
    foreach (xi_go_whitelist() as $allow) {
        if ($host === $allow || substr($host, -strlen('.' . $allow)) === '.' . $allow) {
            return false;
        }
    }
    return true;
}

/**
 * 把一段 HTML 里的站外链接换成跳转地址，并补上 nofollow
 */
function xi_go_filter_html($html)
{
    if (!xi_go_enabled() || is_feed() || is_admin() || stripos($html, '<a') === false) {
        return $html;
    }
    return preg_replace_callback('#<a\s[^>]*>#i', function ($m) {
        $tag = $m[0];
        if (!preg_match('#\shref=(["\'])(.*?)\1#i', $tag, $h)) {
            return $tag;
        }
        $url = html_entity_decode($h[2], ENT_QUOTES, 'UTF-8');
        if (strpos($tag, 'data-no-go') !== false || !xi_go_is_external($url)) {
            return $tag;
        }
        $tag = str_replace($h[0], ' href="' . esc_url(xi_go_url($url)) . '"', $tag);
        if (preg_match('#\srel=(["\'])(.*?)\1#i', $tag, $r)) {
            if (stripos($r[2], 'nofollow') === false) {
                $tag = str_replace($r[0], ' rel="' . esc_attr(trim($r[2] . ' nofollow')) . '"', $tag);
            }
        } else {
            $tag = substr($tag, 0, -1) . ' rel="nofollow"' . '>';
        }
        return $tag;
    }, $html);
}

function xi_go_scope($key)
{
    return in_array($key, (array) _xz('go_scope', array('content', 'comment', 'author')), true);
}

function xi_go_the_content($html)
{
    return xi_go_scope('content') ? xi_go_filter_html($html) : $html;
}
add_filter('the_content', 'xi_go_the_content', 99);

function xi_go_comment_text($html)
{
    return xi_go_scope('comment') ? xi_go_filter_html($html) : $html;
}
add_filter('comment_text', 'xi_go_comment_text', 99);

/**
 * 评论者填写的网站
 */
function xi_go_comment_author_url($url)
{
    return $url && xi_go_enabled() && xi_go_scope('author') && !is_admin() && xi_go_is_external($url) ? xi_go_url($url) : $url;
}
add_filter('get_comment_author_url', 'xi_go_comment_author_url', 20);

/**
 * 跳转提示页
 */
function xi_go_page()
{
    if (!isset($_GET['golink'])) {
        return;
    }
    $code = sanitize_text_field(wp_unslash($_GET['golink']));
    $url  = (string) base64_decode(strtr($code, '-_', '+/'), true);
    $sig  = isset($_GET['sig']) ? sanitize_text_field(wp_unslash($_GET['sig'])) : '';
    $ok   = $url !== '' && preg_match('#^https?://#i', $url) && hash_equals(xi_go_sign($url), $sig);

    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow');
    status_header($ok ? 200 : 400);

    $name  = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $color = function_exists('xi_theme_color') ? xi_theme_color() : '#2bb39a';
    $wait  = max(0, min(30, (int) _xz('go_wait', 0)));
    $host  = $ok ? (string) wp_parse_url($url, PHP_URL_HOST) : '';
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<?php wp_site_icon(); ?>
<title><?php echo esc_html(($ok ? __('即将离开', 'xi') : __('链接无效', 'xi')) . ' - ' . $name); ?></title>
<style>
:root{--m:<?php echo esc_html($color); ?>;--bg:#edf4f2;--card:rgba(255,255,255,.8);--t:#17221f;--t2:#4a5955;--t3:#84928e;--line:rgba(15,45,40,.1)}
@media (prefers-color-scheme:dark){:root{--bg:#0f1614;--card:rgba(30,40,37,.75);--t:#e8f0ee;--t2:#b3c1bd;--t3:#7f8f8a;--line:rgba(255,255,255,.08)}}
*{box-sizing:border-box}
body{display:grid;place-items:center;min-height:100vh;margin:0;padding:20px;background:radial-gradient(60vw 60vw at 90% -10%,color-mix(in srgb,var(--m) 28%,transparent),transparent 70%),radial-gradient(50vw 50vw at -10% 110%,color-mix(in srgb,var(--m) 18%,transparent),transparent 70%),var(--bg);color:var(--t);font:15px/1.7 "Segoe UI",system-ui,-apple-system,"PingFang SC","Microsoft YaHei",sans-serif}
.box{width:min(460px,100%);padding:32px 28px 26px;border:1px solid var(--line);border-radius:20px;background:var(--card);-webkit-backdrop-filter:blur(24px) saturate(180%);backdrop-filter:blur(24px) saturate(180%);box-shadow:0 30px 80px -30px rgba(0,0,0,.35);text-align:center}
.ic{display:inline-grid;place-items:center;width:56px;height:56px;margin-bottom:14px;border-radius:16px;background:linear-gradient(145deg,color-mix(in srgb,var(--m) 80%,#fff),var(--m));color:#fff;box-shadow:0 10px 24px -10px var(--m)}
.ic.err{background:#e5484d;box-shadow:0 10px 24px -10px #e5484d}
h1{margin:0 0 6px;font-size:20px}
p{margin:0;color:var(--t2);font-size:14px}
.url{margin:16px 0 6px;padding:10px 14px;border:1px solid var(--line);border-radius:10px;background:color-mix(in srgb,var(--m) 7%,transparent);color:var(--t);font:13px/1.6 Consolas,monospace;text-align:left;word-break:break-all}
.tip{font-size:12px;color:var(--t3)}
.act{display:flex;justify-content:center;gap:10px;margin-top:22px}
.btn{display:inline-flex;align-items:center;justify-content:center;min-width:110px;height:38px;padding:0 18px;border:1px solid var(--line);border-radius:999px;color:var(--t);font-size:14px;font-weight:600;text-decoration:none;cursor:pointer;background:transparent}
.btn.go{border-color:transparent;background:var(--m);color:#fff;box-shadow:0 8px 20px -10px var(--m)}
.btn:hover{filter:brightness(1.05)}
</style>
</head>
<body>
<main class="box">
<?php if ($ok) : ?>
    <span class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg></span>
    <h1><?php printf(esc_html__('即将离开 %s', 'xi'), esc_html($name)); ?></h1>
    <p><?php printf(esc_html__('你将前往 %s，该网站不受本站控制，请注意账号与财产安全', 'xi'), '<b>' . esc_html($host) . '</b>'); ?></p>
    <div class="url"><?php echo esc_html($url); ?></div>
    <?php if ($wait) : ?><p class="tip" id="go-tip"><?php printf(esc_html__('%s 秒后自动跳转', 'xi'), '<span id="go-sec">' . (int) $wait . '</span>'); ?></p><?php endif; ?>
    <div class="act">
        <a class="btn" href="<?php echo esc_url(home_url('/')); ?>" onclick="if(history.length>1&&document.referrer){history.back();return false}"><?php esc_html_e('返回', 'xi'); ?></a>
        <a class="btn go" id="go-btn" href="<?php echo esc_url($url); ?>" rel="nofollow noopener noreferrer"><?php esc_html_e('继续访问', 'xi'); ?></a>
    </div>
    <?php if ($wait) : ?>
    <script>(function(){var s=<?php echo (int) $wait; ?>,e=document.getElementById('go-sec');var t=setInterval(function(){s--;if(e)e.textContent=s;if(s<=0){clearInterval(t);location.href=document.getElementById('go-btn').href}},1000)})();</script>
    <?php endif; ?>
<?php else : ?>
    <span class="ic err"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 7v6M12 17h.01"/></svg></span>
    <h1><?php esc_html_e('链接无效', 'xi'); ?></h1>
    <p><?php esc_html_e('这个跳转链接不完整或已失效，请返回原页面重新点击', 'xi'); ?></p>
    <div class="act"><a class="btn go" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('返回首页', 'xi'); ?></a></div>
<?php endif; ?>
</main>
</body>
</html>
    <?php
    exit;
}
add_action('template_redirect', 'xi_go_page', 1);
