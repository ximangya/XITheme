<?php
/**
 * 商城后台：商品设置面板、商品列表列、订单管理、主题设置字段
 * 免费版 CSF 不含 Metabox 模块，商品设置用原生 add_meta_box 实现
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ---------- 商品设置面板 ---------- */

function xi_product_meta_box()
{
    add_meta_box('xi_product_box', __('商品设置', 'xi'), 'xi_product_meta_box_html', 'xi_product', 'normal', 'high');
}
add_action('add_meta_boxes', 'xi_product_meta_box');

function xi_product_meta_box_html($post)
{
    $d     = xi_product_data($post->ID, true);
    $cards = get_post_meta($post->ID, 'xi_product_cards', true);
    $cards = is_array($cards) ? $cards : array();
    wp_nonce_field('xi_product_save', 'xi_product_nonce');
    $f = function ($key) {
        return 'xi_product[' . $key . ']';
    };
    ?>
    <style>
        .xi-pm{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:14px 18px;margin:6px 0 4px}
        .xi-pm label{display:block;font-weight:600;margin-bottom:4px}
        .xi-pm input[type=text],.xi-pm input[type=number],.xi-pm select,.xi-pm textarea{width:100%}
        .xi-pm .is-wide{grid-column:1/-1}
        .xi-pm .description{margin:4px 0 0;color:#646970;font-weight:400}
        .xi-pm-h{grid-column:1/-1;margin:10px 0 0;padding-top:12px;border-top:1px solid #e2e4e7;font-size:14px}
        .xi-specs{width:100%;border-collapse:collapse}
        .xi-specs th{text-align:left;font-weight:600;padding:4px 6px}
        .xi-specs td{padding:4px 6px;vertical-align:top}
        .xi-specs input{width:100%}
        .xi-specs textarea{width:100%;min-height:34px}
    </style>
    <div class="xi-pm">
        <div>
            <label><?php esc_html_e('价格类型', 'xi'); ?></label>
            <select name="<?php echo esc_attr($f('price_type')); ?>">
                <option value="money" <?php selected($d['price_type'], 'money'); ?>><?php esc_html_e('现金（元）', 'xi'); ?></option>
                <option value="points" <?php selected($d['price_type'], 'points'); ?>><?php echo esc_html(xi_points_name()); ?></option>
                <option value="free" <?php selected($d['price_type'], 'free'); ?>><?php esc_html_e('免费领取', 'xi'); ?></option>
            </select>
        </div>
        <div>
            <label><?php esc_html_e('售价', 'xi'); ?></label>
            <input type="number" step="0.01" min="0" name="<?php echo esc_attr($f('price')); ?>" value="<?php echo esc_attr($d['price']); ?>">
            <p class="description"><?php esc_html_e('设置了规格时以规格价格为准', 'xi'); ?></p>
        </div>
        <div>
            <label><?php esc_html_e('划线原价', 'xi'); ?></label>
            <input type="number" step="0.01" min="0" name="<?php echo esc_attr($f('original')); ?>" value="<?php echo esc_attr($d['original']); ?>">
        </div>
        <div>
            <label><?php esc_html_e('库存', 'xi'); ?></label>
            <input type="number" min="0" name="<?php echo esc_attr($f('stock')); ?>" value="<?php echo esc_attr($d['stock']); ?>" placeholder="<?php esc_attr_e('留空不限', 'xi'); ?>">
            <p class="description"><?php esc_html_e('自动发卡商品以剩余卡密数量为库存', 'xi'); ?></p>
        </div>
        <div>
            <label><?php esc_html_e('每人限购', 'xi'); ?></label>
            <input type="number" min="0" name="<?php echo esc_attr($f('limit')); ?>" value="<?php echo esc_attr($d['limit']); ?>">
            <p class="description"><?php esc_html_e('0 为不限', 'xi'); ?></p>
        </div>
        <div>
            <label><?php esc_html_e('虚拟销量', 'xi'); ?></label>
            <input type="number" min="0" name="<?php echo esc_attr($f('sales_base')); ?>" value="<?php echo esc_attr($d['sales_base']); ?>">
            <p class="description"><?php printf(esc_html__('实际销量 %d，前台显示两者之和', 'xi'), (int) get_post_meta($post->ID, 'xi_sales', true)); ?></p>
        </div>
        <div>
            <label><?php esc_html_e('角标', 'xi'); ?></label>
            <input type="text" name="<?php echo esc_attr($f('badge')); ?>" value="<?php echo esc_attr($d['badge']); ?>" placeholder="<?php esc_attr_e('如：热卖、新品', 'xi'); ?>">
        </div>
        <div>
            <label><input type="checkbox" name="<?php echo esc_attr($f('vip_s')); ?>" value="1" <?php checked($d['vip_s']); ?>> <?php esc_html_e('参与会员折扣', 'xi'); ?></label>
            <label><input type="checkbox" name="<?php echo esc_attr($f('refund')); ?>" value="1" <?php checked($d['refund']); ?>> <?php esc_html_e('允许申请售后', 'xi'); ?></label>
        </div>

        <h4 class="xi-pm-h"><?php esc_html_e('限时折扣', 'xi'); ?></h4>
        <div>
            <label><?php esc_html_e('折扣', 'xi'); ?></label>
            <input type="number" step="0.1" min="0" max="9.9" name="<?php echo esc_attr($f('sale_discount')); ?>" value="<?php echo esc_attr($d['sale_discount']); ?>" placeholder="8.5">
            <p class="description"><?php esc_html_e('8.5 表示 85 折，留空不打折', 'xi'); ?></p>
        </div>
        <div>
            <label><?php esc_html_e('开始时间', 'xi'); ?></label>
            <input type="datetime-local" name="<?php echo esc_attr($f('sale_start')); ?>" value="<?php echo esc_attr($d['sale_start'] ? str_replace(' ', 'T', $d['sale_start']) : ''); ?>">
        </div>
        <div>
            <label><?php esc_html_e('结束时间', 'xi'); ?></label>
            <input type="datetime-local" name="<?php echo esc_attr($f('sale_end')); ?>" value="<?php echo esc_attr($d['sale_end'] ? str_replace(' ', 'T', $d['sale_end']) : ''); ?>">
            <p class="description"><?php esc_html_e('留空则一直有效；前台显示倒计时', 'xi'); ?></p>
        </div>

        <h4 class="xi-pm-h"><?php esc_html_e('规格', 'xi'); ?></h4>
        <div class="is-wide">
            <table class="xi-specs" data-xi-specs>
                <thead><tr>
                    <th style="width:22%"><?php esc_html_e('规格名称', 'xi'); ?></th>
                    <th style="width:12%"><?php esc_html_e('售价', 'xi'); ?></th>
                    <th style="width:12%"><?php esc_html_e('原价', 'xi'); ?></th>
                    <th style="width:12%"><?php esc_html_e('库存', 'xi'); ?></th>
                    <th><?php esc_html_e('卡密（自动发卡时每行一个）', 'xi'); ?></th>
                    <th style="width:40px"></th>
                </tr></thead>
                <tbody>
                <?php
                $rows = $d['specs'] ? $d['specs'] : array();
                foreach ($rows as $i => $row) {
                    xi_product_spec_row($i, $row, isset($cards[$i]) ? $cards[$i] : array());
                }
                ?>
                </tbody>
            </table>
            <p><button type="button" class="button" data-xi-spec-add><?php esc_html_e('添加规格', 'xi'); ?></button>
                <span class="description"><?php esc_html_e('不需要规格时留空，使用上方的售价和库存', 'xi'); ?></span></p>
            <script type="text/html" id="xi-spec-tpl"><?php xi_product_spec_row('__i__', array(), array()); ?></script>
        </div>

        <h4 class="xi-pm-h"><?php esc_html_e('发货', 'xi'); ?></h4>
        <div>
            <label><?php esc_html_e('发货方式', 'xi'); ?></label>
            <select name="<?php echo esc_attr($f('delivery')); ?>">
                <?php foreach (xi_delivery_types() as $k => $label) : ?>
                    <option value="<?php echo esc_attr($k); ?>" <?php selected($d['delivery'], $k); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label><?php esc_html_e('运费（元）', 'xi'); ?></label>
            <input type="number" step="0.01" min="0" name="<?php echo esc_attr($f('shipping_fee')); ?>" value="<?php echo esc_attr($d['shipping_fee']); ?>" placeholder="0">
            <p class="description"><?php esc_html_e('仅实物商品；同一订单取最高运费，满额包邮在商城设置中配置', 'xi'); ?></p>
        </div>
        <div>
            <label><input type="checkbox" name="<?php echo esc_attr($f('need_contact')); ?>" value="1" <?php checked($d['need_contact']); ?>> <?php esc_html_e('下单需填写联系方式', 'xi'); ?></label>
            <label><input type="checkbox" name="<?php echo esc_attr($f('need_remark')); ?>" value="1" <?php checked($d['need_remark']); ?>> <?php esc_html_e('下单需填写备注', 'xi'); ?></label>
        </div>
        <div class="is-wide">
            <label><?php esc_html_e('自动发货内容', 'xi'); ?></label>
            <textarea rows="3" name="<?php echo esc_attr($f('delivery_text')); ?>" placeholder="<?php esc_attr_e('下载地址、提取码、使用说明等，付款后自动发给买家', 'xi'); ?>"><?php echo esc_textarea($d['delivery_text']); ?></textarea>
            <p class="description"><?php esc_html_e('发货方式为「自动发货（固定内容）」时使用', 'xi'); ?></p>
        </div>
        <div class="is-wide">
            <label><?php esc_html_e('卡密库存（无规格时）', 'xi'); ?></label>
            <textarea rows="4" name="xi_product_cards[0]" placeholder="<?php esc_attr_e('每行一个卡密，售出后自动移除', 'xi'); ?>"><?php echo esc_textarea(implode("\n", isset($cards[0]) && !$d['specs'] ? (array) $cards[0] : array())); ?></textarea>
            <p class="description"><?php esc_html_e('发货方式为「自动发卡」时使用；有规格时在规格表中填写', 'xi'); ?></p>
        </div>

        <h4 class="xi-pm-h"><?php esc_html_e('展示', 'xi'); ?></h4>
        <div class="is-wide">
            <label><?php esc_html_e('商品图集', 'xi'); ?></label>
            <input type="text" name="<?php echo esc_attr($f('gallery')); ?>" value="<?php echo esc_attr($d['gallery']); ?>" data-xi-gallery placeholder="<?php esc_attr_e('图片附件 ID，逗号分隔', 'xi'); ?>">
            <p><button type="button" class="button" data-xi-gallery-pick><?php esc_html_e('从媒体库选择', 'xi'); ?></button> <span class="description"><?php esc_html_e('特色图片会作为第一张', 'xi'); ?></span></p>
        </div>
        <div class="is-wide">
            <label><?php esc_html_e('购买须知', 'xi'); ?></label>
            <textarea rows="2" name="<?php echo esc_attr($f('buy_notice')); ?>" placeholder="<?php esc_attr_e('只对本商品显示，留空使用商城设置中的通用购买须知', 'xi'); ?>"><?php echo esc_textarea($d['buy_notice']); ?></textarea>
        </div>
        <div class="is-wide">
            <label><?php esc_html_e('商品参数', 'xi'); ?></label>
            <textarea rows="4" name="<?php echo esc_attr($f('params')); ?>" placeholder="<?php esc_attr_e("每行一个，例如：\n版本：v2.0\n授权：永久", 'xi'); ?>"><?php echo esc_textarea($d['params']); ?></textarea>
        </div>
    </div>
    <script>
    (function () {
        var table = document.querySelector('[data-xi-specs] tbody');
        var tpl = document.getElementById('xi-spec-tpl').innerHTML;
        var next = table.querySelectorAll('tr').length;
        document.querySelector('[data-xi-spec-add]').addEventListener('click', function () {
            table.insertAdjacentHTML('beforeend', tpl.replace(/__i__/g, next++));
        });
        table.addEventListener('click', function (e) {
            if (e.target.closest('[data-xi-spec-del]')) e.target.closest('tr').remove();
        });
        var pick = document.querySelector('[data-xi-gallery-pick]');
        pick.addEventListener('click', function () {
            if (!window.wp || !wp.media) return;
            var frame = wp.media({ title: '<?php echo esc_js(__('选择商品图片', 'xi')); ?>', multiple: true, library: { type: 'image' } });
            frame.on('select', function () {
                var ids = frame.state().get('selection').map(function (a) { return a.id; });
                document.querySelector('[data-xi-gallery]').value = ids.join(',');
            });
            frame.open();
        });
    })();
    </script>
    <?php
}

function xi_product_spec_row($i, $row, $cards)
{
    $row  = wp_parse_args($row, array('name' => '', 'price' => '', 'original' => '', 'stock' => ''));
    $name = 'xi_product[specs][' . $i . ']';
    echo '<tr>';
    echo '<td><input type="text" name="' . esc_attr($name) . '[name]" value="' . esc_attr($row['name']) . '"></td>';
    echo '<td><input type="number" step="0.01" min="0" name="' . esc_attr($name) . '[price]" value="' . esc_attr($row['price']) . '"></td>';
    echo '<td><input type="number" step="0.01" min="0" name="' . esc_attr($name) . '[original]" value="' . esc_attr($row['original']) . '"></td>';
    echo '<td><input type="number" min="0" name="' . esc_attr($name) . '[stock]" value="' . esc_attr($row['stock']) . '" placeholder="' . esc_attr__('不限', 'xi') . '"></td>';
    echo '<td><textarea name="xi_product_cards[' . esc_attr($i) . ']" rows="1">' . esc_textarea(implode("\n", (array) $cards)) . '</textarea></td>';
    echo '<td><button type="button" class="button-link button-link-delete" data-xi-spec-del>' . esc_html__('删除', 'xi') . '</button></td>';
    echo '</tr>';
}

function xi_product_admin_assets($hook)
{
    global $post_type;
    if (in_array($hook, array('post.php', 'post-new.php'), true) && $post_type === 'xi_product') {
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'xi_product_admin_assets');

function xi_product_save($post_id)
{
    if (!isset($_POST['xi_product_nonce']) || !wp_verify_nonce(sanitize_key($_POST['xi_product_nonce']), 'xi_product_save')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id) || get_post_type($post_id) !== 'xi_product') {
        return;
    }
    $in  = isset($_POST['xi_product']) && is_array($_POST['xi_product']) ? wp_unslash($_POST['xi_product']) : array();
    $num = function ($v, $int = false) {
        if ($v === '' || $v === null) {
            return '';
        }
        return $int ? max(0, (int) $v) : max(0, round((float) $v, 2));
    };
    $time = function ($v) {
        $v = trim(str_replace('T', ' ', (string) $v));
        return $v !== '' && strtotime($v) ? date('Y-m-d H:i', strtotime($v)) : '';
    };

    $data = array(
        'price_type'    => in_array(isset($in['price_type']) ? $in['price_type'] : '', array('money', 'points', 'free'), true) ? $in['price_type'] : 'money',
        'price'         => $num(isset($in['price']) ? $in['price'] : ''),
        'original'      => $num(isset($in['original']) ? $in['original'] : ''),
        'stock'         => $num(isset($in['stock']) ? $in['stock'] : '', true),
        'limit'         => (int) $num(isset($in['limit']) ? $in['limit'] : 0, true),
        'sales_base'    => (int) $num(isset($in['sales_base']) ? $in['sales_base'] : 0, true),
        'badge'         => isset($in['badge']) ? mb_substr(sanitize_text_field($in['badge']), 0, 10) : '',
        'vip_s'         => empty($in['vip_s']) ? 0 : 1,
        'refund'        => empty($in['refund']) ? 0 : 1,
        'sale_discount' => isset($in['sale_discount']) && (float) $in['sale_discount'] > 0 && (float) $in['sale_discount'] < 10 ? round((float) $in['sale_discount'], 1) : '',
        'sale_start'    => $time(isset($in['sale_start']) ? $in['sale_start'] : ''),
        'sale_end'      => $time(isset($in['sale_end']) ? $in['sale_end'] : ''),
        'delivery'      => isset($in['delivery']) && array_key_exists($in['delivery'], xi_delivery_types()) ? $in['delivery'] : 'auto_text',
        'delivery_text' => isset($in['delivery_text']) ? sanitize_textarea_field($in['delivery_text']) : '',
        'need_contact'  => empty($in['need_contact']) ? 0 : 1,
        'need_remark'   => empty($in['need_remark']) ? 0 : 1,
        'gallery'       => isset($in['gallery']) ? implode(',', array_filter(array_map('intval', explode(',', $in['gallery'])))) : '',
        'params'        => isset($in['params']) ? sanitize_textarea_field($in['params']) : '',
        'shipping_fee'  => $num(isset($in['shipping_fee']) ? $in['shipping_fee'] : ''),
        'buy_notice'    => isset($in['buy_notice']) ? sanitize_textarea_field($in['buy_notice']) : '',
        'specs'         => array(),
    );

    // 规格：重新编号，卡密跟随规格行
    $raw_cards = isset($_POST['xi_product_cards']) && is_array($_POST['xi_product_cards']) ? wp_unslash($_POST['xi_product_cards']) : array();
    $cards     = array();
    if (!empty($in['specs']) && is_array($in['specs'])) {
        foreach ($in['specs'] as $key => $row) {
            $name = isset($row['name']) ? mb_substr(sanitize_text_field($row['name']), 0, 40) : '';
            if ($name === '') {
                continue;
            }
            $i                 = count($data['specs']);
            $data['specs'][$i] = array(
                'name'     => $name,
                'price'    => (float) $num(isset($row['price']) ? $row['price'] : 0),
                'original' => $num(isset($row['original']) ? $row['original'] : ''),
                'stock'    => $num(isset($row['stock']) ? $row['stock'] : '', true),
            );
            $cards[$i] = isset($raw_cards[$key]) ? xi_product_parse_cards($raw_cards[$key]) : array();
        }
    }
    if (!$data['specs']) {
        $cards = array(0 => isset($raw_cards[0]) ? xi_product_parse_cards($raw_cards[0]) : array());
    }

    update_post_meta($post_id, 'xi_product', $data);
    update_post_meta($post_id, 'xi_product_cards', $cards);
    xi_product_data($post_id, true);
    xi_product_sync_sort_meta($post_id);
}
add_action('save_post_xi_product', 'xi_product_save');

function xi_product_parse_cards($text)
{
    return array_values(array_unique(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', sanitize_textarea_field($text))), 'strlen')));
}

/* ---------- 商品列表列 ---------- */

function xi_product_columns($columns)
{
    $new = array();
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['xi_price'] = __('价格', 'xi');
            $new['xi_stock'] = __('库存', 'xi');
            $new['xi_sales'] = __('销量', 'xi');
        }
    }
    return $new;
}
add_filter('manage_xi_product_posts_columns', 'xi_product_columns');

function xi_product_column_value($column, $post_id)
{
    if ($column === 'xi_price') {
        $p = xi_product_display_price($post_id, 0);
        echo $p ? esc_html((!empty($p['from']) ? __('起', 'xi') . ' ' : '') . xi_price_text($p['price'], $p['type'])) : '—';
    } elseif ($column === 'xi_stock') {
        $s = xi_product_total_stock($post_id);
        echo $s < 0 ? esc_html__('不限', 'xi') : ($s === 0 ? '<b style="color:#d63638">' . esc_html__('售罄', 'xi') . '</b>' : (int) $s);
    } elseif ($column === 'xi_sales') {
        echo (int) get_post_meta($post_id, 'xi_sales', true);
    }
}
add_action('manage_xi_product_posts_custom_column', 'xi_product_column_value', 10, 2);

/**
 * 商品列表「复制」：复制标题、正文、分类与商品设置（不含卡密与销量），生成草稿
 */
function xi_product_row_actions($actions, $post)
{
    if ($post->post_type === 'xi_product' && current_user_can('edit_posts')) {
        $url = wp_nonce_url(admin_url('admin-post.php?action=xi_product_copy&post=' . $post->ID), 'xi_product_copy_' . $post->ID);
        $actions['xi_copy'] = '<a href="' . esc_url($url) . '">' . esc_html__('复制', 'xi') . '</a>';
    }
    return $actions;
}
add_filter('post_row_actions', 'xi_product_row_actions', 10, 2);

function xi_product_copy_action()
{
    $id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    check_admin_referer('xi_product_copy_' . $id);
    $post = get_post($id);
    if (!$post || $post->post_type !== 'xi_product' || !current_user_can('edit_post', $id)) {
        wp_die(esc_html__('没有权限', 'xi'), 403);
    }
    $new = wp_insert_post(array(
        'post_type'    => 'xi_product',
        'post_status'  => 'draft',
        'post_title'   => $post->post_title . ' ' . __('（副本）', 'xi'),
        'post_content' => $post->post_content,
        'post_excerpt' => $post->post_excerpt,
        'menu_order'   => $post->menu_order,
        'comment_status' => $post->comment_status,
    ), true);
    if (is_wp_error($new)) {
        wp_die(esc_html($new->get_error_message()));
    }
    update_post_meta($new, 'xi_product', get_post_meta($id, 'xi_product', true));
    if ($thumb = get_post_thumbnail_id($id)) {
        set_post_thumbnail($new, $thumb);
    }
    foreach (array('product_cat', 'product_tag') as $tax) {
        wp_set_object_terms($new, wp_get_object_terms($id, $tax, array('fields' => 'ids')), $tax);
    }
    xi_product_data($new, true);
    xi_product_sync_sort_meta($new);
    wp_safe_redirect(admin_url('post.php?action=edit&post=' . $new));
    exit;
}
add_action('admin_post_xi_product_copy', 'xi_product_copy_action');

/* ---------- 订单统计与导出 ---------- */

/**
 * 成交统计：今日、近 7 天、近 30 天的现金成交额与订单数，以及近 30 天销量前 5 的商品
 */
function xi_shop_stats()
{
    global $wpdb;
    $table = xi_shop_orders_table();
    $now   = current_time('timestamp');
    $out   = array();
    foreach (array('today' => wp_date('Y-m-d 00:00:00'), 'week' => date('Y-m-d 00:00:00', $now - 6 * DAY_IN_SECONDS), 'month' => date('Y-m-d 00:00:00', $now - 29 * DAY_IN_SECONDS)) as $k => $from) {
        $row = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN price_type = 'money' AND pay_type <> 'manual' THEN total ELSE 0 END), 0) AS money FROM {$table} WHERE status IN (1,2,3,5) AND paid_at >= %s", $from));
        $out[$k] = array('orders' => (int) $row->n, 'money' => (float) $row->money);
    }
    $top  = array();
    $rows = $wpdb->get_col($wpdb->prepare("SELECT items FROM {$table} WHERE status IN (1,2,3,5) AND paid_at >= %s ORDER BY id DESC LIMIT 3000", date('Y-m-d 00:00:00', $now - 29 * DAY_IN_SECONDS)));
    foreach ($rows as $json) {
        foreach ((array) json_decode($json, true) as $it) {
            $pid = (int) $it['pid'];
            if (!isset($top[$pid])) {
                $top[$pid] = array('title' => $it['title'], 'qty' => 0);
            }
            $top[$pid]['qty'] += (int) $it['qty'];
        }
    }
    uasort($top, function ($a, $b) {
        return $b['qty'] - $a['qty'];
    });
    $out['top'] = array_slice($top, 0, 5, true);
    return $out;
}

/**
 * 导出订单 CSV（按当前筛选条件，最多 5000 条）
 */
function xi_shop_export_action()
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('没有权限', 'xi'), 403);
    }
    check_admin_referer('xi_shop_export');
    global $wpdb;
    $table  = xi_shop_orders_table();
    $status = isset($_GET['status']) && $_GET['status'] !== '' ? (int) $_GET['status'] : null;
    $rows   = array_map('xi_shop_decode_order', $status === null
        ? $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 5000")
        : $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE status = %d ORDER BY id DESC LIMIT 5000", $status)));

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=shop-orders-' . wp_date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fwrite($out, "ï»¿"); // Excel 识别 UTF-8
    fputcsv($out, array('订单号', '用户', '商品', '数量', '计价', '商品金额', '优惠', '运费', '实付', '付款方式', '状态', '收货信息', '联系方式', '备注', '物流', '下单时间', '付款时间'), ",", "\"", "\\");
    foreach ($rows as $o) {
        $u     = get_userdata($o->user_id);
        $names = array();
        $qty   = 0;
        foreach ($o->items as $it) {
            $names[] = $it['title'] . ($it['spec_name'] ? '(' . $it['spec_name'] . ')' : '') . '×' . $it['qty'];
            $qty    += (int) $it['qty'];
        }
        $row = array(
            $o->order_no,
            $u ? $u->user_login : '#' . $o->user_id,
            implode('；', $names),
            $qty,
            $o->price_type === 'points' ? xi_points_name() : '元',
            $o->goods_total,
            $o->discount,
            isset($o->shipping) ? $o->shipping : 0,
            $o->total,
            $o->pay_type ? xi_shop_pay_type_name($o->pay_type) : '',
            xi_shop_status_name($o->status),
            $o->address ? xi_shop_address_text($o->address) : '',
            $o->contact,
            $o->remark,
            !empty($o->express['number']) ? $o->express['company'] . ' ' . $o->express['number'] : '',
            $o->created_at,
            $o->paid_at,
        );
        // 防止 CSV 注入：以 = + - @ 开头的单元格前加单引号
        $row = array_map(function ($v) {
            $v = (string) $v;
            return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
        }, $row);
        fputcsv($out, $row, ",", "\"", "\\");
    }
    fclose($out);
    exit;
}
add_action('admin_post_xi_shop_export', 'xi_shop_export_action');

/* ---------- 订单管理 ---------- */

function xi_shop_admin_menu()
{
    if (!xi_shop_enabled()) {
        return;
    }
    $pending = 0;
    if (get_option('xi_shop_db_ver') === XI_SHOP_DB_VER) {
        global $wpdb;
        $pending = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . xi_shop_orders_table() . ' WHERE status IN (1,5)');
    }
    $title = __('商城订单', 'xi') . ($pending ? ' <span class="awaiting-mod">' . $pending . '</span>' : '');
    add_submenu_page('edit.php?post_type=xi_product', __('商城订单', 'xi'), $title, 'manage_options', 'xi_shop_orders', 'xi_shop_orders_page');
}
add_action('admin_menu', 'xi_shop_admin_menu');

function xi_shop_orders_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }
    xi_shop_install();
    global $wpdb;
    $table = xi_shop_orders_table();

    // 操作
    if (!empty($_POST['xi_shop_do']) && !empty($_POST['order_no']) && check_admin_referer('xi_shop_orders')) {
        $no    = sanitize_text_field(wp_unslash($_POST['order_no']));
        $do    = sanitize_key($_POST['xi_shop_do']);
        $reply = isset($_POST['reply']) ? sanitize_textarea_field(wp_unslash($_POST['reply'])) : '';
        $res   = new WP_Error('do', __('未知操作', 'xi'));
        switch ($do) {
            case 'ship':
                $res = xi_shop_order_ship($no, array(
                    'company' => isset($_POST['company']) ? wp_unslash($_POST['company']) : '',
                    'number'  => isset($_POST['number']) ? wp_unslash($_POST['number']) : '',
                    'content' => isset($_POST['content']) ? sanitize_textarea_field(wp_unslash($_POST['content'])) : '',
                ));
                break;
            case 'paid':
                $res = xi_shop_order_paid($no, 'manual') ? true : new WP_Error('status', __('订单不是待付款或已关闭状态', 'xi'));
                break;
            case 'close':
                $res = xi_shop_order_close($no, __('管理员关闭', 'xi')) ? true : new WP_Error('status', __('只能关闭待付款订单', 'xi'));
                break;
            case 'refund_ok':
                $res = xi_shop_refund_approve($no, $reply);
                break;
            case 'refund_no':
                $res = xi_shop_refund_reject($no, $reply);
                break;
            case 'return_ok':
                $res = xi_shop_refund_agree_return($no, $reply);
                break;
            case 'note_save':
                $wpdb->update($table, array('admin_note' => mb_substr(isset($_POST['admin_note']) ? sanitize_text_field(wp_unslash($_POST['admin_note'])) : '', 0, 250)), array('order_no' => $no));
                $res = true;
                break;
            case 'finish':
                $res = $wpdb->query($wpdb->prepare("UPDATE {$table} SET status = 3, finished_at = %s WHERE order_no = %s AND status = 2", current_time('mysql'), $no)) ? true : new WP_Error('status', __('只能完成待收货订单', 'xi'));
                break;
        }
        echo '<div class="notice notice-' . (is_wp_error($res) ? 'error' : 'success') . ' is-dismissible"><p>' . esc_html(is_wp_error($res) ? $res->get_error_message() : __('操作成功', 'xi')) . '</p></div>';
    }

    $status = isset($_GET['status']) && $_GET['status'] !== '' ? (int) $_GET['status'] : null;
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $paged  = max(1, isset($_GET['paged']) ? (int) $_GET['paged'] : 1);
    $per    = 20;
    $where  = array('1=1');
    $args   = array();
    if ($status !== null) {
        $where[] = 'status = %d';
        $args[]  = $status;
    }
    if ($search !== '') {
        $like    = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(order_no LIKE %s OR pay_no LIKE %s OR title LIKE %s OR contact LIKE %s)';
        array_push($args, $like, $like, $like, $like);
    }
    $sql_where = implode(' AND ', $where);
    $total     = (int) $wpdb->get_var($args ? $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$sql_where}", $args) : "SELECT COUNT(*) FROM {$table} WHERE {$sql_where}");
    $rows      = array_map('xi_shop_decode_order', $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$sql_where} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge($args, array($per, ($paged - 1) * $per)))));
    $income    = (float) $wpdb->get_var("SELECT SUM(total) FROM {$table} WHERE status IN (1,2,3,5) AND price_type = 'money' AND pay_type <> 'manual'");
    $counts    = array();
    foreach ($wpdb->get_results("SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status") as $c) {
        $counts[(int) $c->status] = (int) $c->n;
    }
    $base     = admin_url('edit.php?post_type=xi_product&page=xi_shop_orders');
    $colors   = array(0 => '#e2a33b', 1 => '#d63638', 2 => '#2271b1', 3 => '#0f9d58', 4 => '#999', 5 => '#d63638', 6 => '#999');
    $express  = array_filter(array_map('trim', preg_split('/[\r\n,，]+/u', (string) _xz('shop_express', '顺丰速运,中通快递,圆通速递,韵达快递,申通快递,京东物流,邮政EMS,极兔速递'))));
    ?>
    <div class="wrap xi-shop-admin">
        <h1><?php esc_html_e('商城订单', 'xi'); ?></h1>
        <?php $stats = xi_shop_stats(); ?>
        <div class="xi-shop-stats">
            <?php foreach (array('today' => __('今日', 'xi'), 'week' => __('近 7 天', 'xi'), 'month' => __('近 30 天', 'xi')) as $k => $label) : ?>
                <div><span><?php echo esc_html($label); ?></span><b>¥<?php echo esc_html(xi_money($stats[$k]['money'])); ?></b><small><?php printf(esc_html__('%d 笔订单', 'xi'), (int) $stats[$k]['orders']); ?></small></div>
            <?php endforeach; ?>
            <div><span><?php esc_html_e('累计成交', 'xi'); ?></span><b>¥<?php echo esc_html(xi_money($income)); ?></b><small><?php esc_html_e('不含退款、补单', 'xi'); ?></small></div>
            <?php if ($stats['top']) : ?>
                <div class="is-wide"><span><?php esc_html_e('近 30 天热销', 'xi'); ?></span>
                    <?php foreach ($stats['top'] as $pid => $t) : ?><small><?php echo esc_html($t['title']); ?> × <?php echo (int) $t['qty']; ?></small><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <p><a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg(array_filter(array('action' => 'xi_shop_export', 'status' => $status === null ? '' : (string) $status), 'strlen'), admin_url('admin-post.php')), 'xi_shop_export')); ?>"><?php esc_html_e('导出 CSV', 'xi'); ?></a></p>
        <ul class="subsubsub">
            <?php
            $links = array('<li><a href="' . esc_url($base) . '"' . ($status === null ? ' class="current"' : '') . '>' . esc_html__('全部', 'xi') . '</a></li>');
            foreach (xi_shop_statuses() as $k => $label) {
                $links[] = '<li><a href="' . esc_url(add_query_arg('status', $k, $base)) . '"' . ($status === $k ? ' class="current"' : '') . '>' . esc_html($label) . ' <span class="count">(' . (isset($counts[$k]) ? $counts[$k] : 0) . ')</span></a></li>';
            }
            echo implode(' | ', $links);
            ?>
        </ul>
        <form method="get" style="float:right;margin:8px 0">
            <input type="hidden" name="post_type" value="xi_product">
            <input type="hidden" name="page" value="xi_shop_orders">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('订单号 / 商品 / 联系方式', 'xi'); ?>">
            <?php submit_button(__('搜索', 'xi'), '', '', false); ?>
        </form>
        <style>
            .xi-shop-admin details{margin:0}
            .xi-shop-admin summary{cursor:pointer;color:#2271b1}
            .xi-shop-admin .xi-od{margin:8px 0;padding:10px 12px;border-radius:6px;background:#f6f7f7;line-height:1.7}
            .xi-shop-admin .xi-od form{margin:8px 0 0;display:flex;flex-wrap:wrap;gap:6px;align-items:flex-start}
            .xi-shop-admin .xi-od textarea{width:100%;min-height:54px}
            .xi-shop-admin .xi-od code{white-space:pre-wrap;word-break:break-all}
            .xi-shop-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin:12px 0}
            .xi-shop-stats>div{display:flex;flex-direction:column;gap:2px;padding:12px 14px;border:1px solid #dcdcde;border-radius:8px;background:#fff}
            .xi-shop-stats .is-wide{grid-column:1/-1}
            .xi-shop-stats span{color:#646970;font-size:12px}
            .xi-shop-stats b{font-size:20px}
            .xi-shop-stats small{color:#646970}
        </style>
        <table class="widefat striped" style="clear:both">
            <thead><tr>
                <th style="width:170px"><?php esc_html_e('订单号', 'xi'); ?></th>
                <th style="width:120px"><?php esc_html_e('用户', 'xi'); ?></th>
                <th><?php esc_html_e('商品与详情', 'xi'); ?></th>
                <th style="width:110px"><?php esc_html_e('金额', 'xi'); ?></th>
                <th style="width:80px"><?php esc_html_e('状态', 'xi'); ?></th>
                <th style="width:130px"><?php esc_html_e('时间', 'xi'); ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows) : ?>
                <tr><td colspan="6"><?php esc_html_e('暂无订单', 'xi'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $o) :
                $u  = get_userdata($o->user_id);
                $st = (int) $o->status;
                ?>
                <tr>
                    <td><code><?php echo esc_html($o->order_no); ?></code><?php if ($o->pay_no) : ?><br><small><?php echo esc_html($o->pay_no); ?></small><?php endif; ?></td>
                    <td><?php echo $u ? '<a href="' . esc_url(get_edit_user_link($u->ID)) . '">' . esc_html($u->display_name) . '</a>' : '#' . (int) $o->user_id; ?></td>
                    <td>
                        <details<?php echo in_array($st, array(1, 5), true) ? ' open' : ''; ?>>
                            <summary><?php echo esc_html($o->title); ?></summary>
                            <div class="xi-od">
                                <?php foreach ($o->items as $it) : ?>
                                    <div>· <?php echo esc_html($it['title'] . ($it['spec_name'] ? '（' . $it['spec_name'] . '）' : '') . ' × ' . $it['qty'] . ' = ' . xi_price_text($it['subtotal'], $o->price_type)); ?>
                                        <small>[<?php $types = xi_delivery_types(); echo esc_html(isset($types[$it['delivery']]) ? $types[$it['delivery']] : $it['delivery']); ?>]</small>
                                        <?php if (!empty($it['short'])) : ?><b style="color:#d63638"><?php printf(esc_html__('卡密缺 %d 个', 'xi'), (int) $it['short']); ?></b><?php endif; ?>
                                        <?php if (!empty($it['delivered'])) : ?><br><code><?php echo esc_html(implode("\n", (array) $it['delivered'])); ?></code><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($o->address) : ?><div><b><?php esc_html_e('收货：', 'xi'); ?></b><?php echo esc_html(xi_shop_address_text($o->address)); ?></div><?php endif; ?>
                                <?php if ($o->contact) : ?><div><b><?php esc_html_e('联系方式：', 'xi'); ?></b><?php echo esc_html($o->contact); ?></div><?php endif; ?>
                                <?php if ($o->remark) : ?><div><b><?php esc_html_e('备注：', 'xi'); ?></b><?php echo esc_html($o->remark); ?></div><?php endif; ?>
                                <?php if (!empty($o->express['number'])) : ?><div><b><?php esc_html_e('物流：', 'xi'); ?></b><?php echo esc_html($o->express['company'] . ' ' . $o->express['number']); ?></div><?php endif; ?>
                                <?php if (!empty($o->refund['reason'])) : ?><div><b><?php esc_html_e('售后原因：', 'xi'); ?></b><?php echo esc_html($o->refund['reason']); ?><?php if (!empty($o->refund['reply'])) : ?><br><b><?php esc_html_e('处理回复：', 'xi'); ?></b><?php echo esc_html($o->refund['reply']); ?><?php endif; ?><?php if (!empty($o->refund['manual'])) : ?><br><b style="color:#d63638"><?php esc_html_e('站点未开启余额，请线下退款', 'xi'); ?></b><?php endif; ?></div><?php endif; ?>
                                <?php
                                $ex = xi_shop_order_extra($o);
                                $parts = array();
                                if ($ex['promo']) { $parts[] = $ex['promo']['text'] . ' -' . xi_money($ex['promo']['amount']); }
                                if ($ex['coupon']) { $parts[] = sprintf(__('优惠码 %1$s -%2$s', 'xi'), $ex['coupon']['code'], xi_money($ex['coupon']['amount'])); }
                                if (!empty($ex['points']['points'])) { $parts[] = sprintf(__('%1$d %2$s抵扣 -%3$s', 'xi'), $ex['points']['points'], xi_points_name(), xi_money($ex['points']['value'])) . (!empty($ex['points_released']) ? __('（已退回）', 'xi') : ''); }
                                if ((float) $ex['shipping'] > 0) { $parts[] = sprintf(__('运费 +%s', 'xi'), xi_money($ex['shipping'])); }
                                if ($parts) : ?><div><b><?php esc_html_e('金额明细：', 'xi'); ?></b><?php echo esc_html(implode('；', $parts)); ?></div><?php endif; ?>
                                <?php if (!empty($o->refund['type']) && in_array($st, array(5, 6), true)) :
                                    $rtypes  = xi_shop_refund_types();
                                    $stages  = array('apply' => __('待处理', 'xi'), 'returning' => __('等待买家寄回', 'xi'), 'returned' => __('买家已寄回，待确认收货', 'xi'));
                                    $stage   = isset($o->refund['stage']) ? $o->refund['stage'] : '';
                                    ?>
                                    <div><b><?php esc_html_e('售后类型：', 'xi'); ?></b><?php echo esc_html($rtypes[$o->refund['type']]); ?><?php if ($st === 5 && isset($stages[$stage])) : ?> · <?php echo esc_html($stages[$stage]); ?><?php endif; ?>
                                        <?php if (!empty($o->refund['return_express']['number'])) : ?><br><b><?php esc_html_e('退货物流：', 'xi'); ?></b><?php echo esc_html($o->refund['return_express']['company'] . ' ' . $o->refund['return_express']['number']); ?><?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($o->note) : ?><div style="color:#d63638"><?php echo esc_html($o->note); ?></div><?php endif; ?>
                                <form method="post">
                                    <?php wp_nonce_field('xi_shop_orders'); ?>
                                    <input type="hidden" name="order_no" value="<?php echo esc_attr($o->order_no); ?>">
                                    <input type="text" name="admin_note" value="<?php echo esc_attr(isset($o->admin_note) ? $o->admin_note : ''); ?>" placeholder="<?php esc_attr_e('商家备注（仅后台可见）', 'xi'); ?>" style="flex:1;min-width:200px">
                                    <button class="button" name="xi_shop_do" value="note_save"><?php esc_html_e('保存备注', 'xi'); ?></button>
                                </form>

                                <?php if ($st === 1) :
                                    $physical = (bool) array_filter($o->items, function ($it) { return $it['delivery'] === 'physical'; });
                                    $virtual  = (bool) array_filter($o->items, function ($it) { return $it['delivery'] !== 'physical' && (empty($it['delivered']) || !empty($it['short'])); });
                                    ?>
                                    <form method="post">
                                        <?php wp_nonce_field('xi_shop_orders'); ?>
                                        <input type="hidden" name="order_no" value="<?php echo esc_attr($o->order_no); ?>">
                                        <?php if ($physical) : ?>
                                            <select name="company"><?php foreach ($express as $c) : ?><option><?php echo esc_html($c); ?></option><?php endforeach; ?></select>
                                            <input type="text" name="number" placeholder="<?php esc_attr_e('快递单号', 'xi'); ?>">
                                        <?php endif; ?>
                                        <?php if ($virtual) : ?>
                                            <textarea name="content" placeholder="<?php esc_attr_e('发货内容：卡密、下载地址、账号等，会发送给买家', 'xi'); ?>"></textarea>
                                        <?php endif; ?>
                                        <button class="button button-primary" name="xi_shop_do" value="ship"><?php esc_html_e('确认发货', 'xi'); ?></button>
                                    </form>
                                <?php elseif ($st === 5) : ?>
                                    <form method="post">
                                        <?php wp_nonce_field('xi_shop_orders'); ?>
                                        <input type="hidden" name="order_no" value="<?php echo esc_attr($o->order_no); ?>">
                                        <textarea name="reply" placeholder="<?php esc_attr_e('处理说明（选填，会通知买家）', 'xi'); ?>"></textarea>
                                        <?php if (isset($o->refund['type'], $o->refund['stage']) && $o->refund['type'] === 'return' && $o->refund['stage'] === 'apply') : ?>
                                            <button class="button button-primary" name="xi_shop_do" value="return_ok"><?php esc_html_e('同意退货', 'xi'); ?></button>
                                        <?php endif; ?>
                                        <button class="button<?php echo isset($o->refund['type']) && $o->refund['type'] === 'return' && (!isset($o->refund['stage']) || $o->refund['stage'] !== 'returned') ? '' : ' button-primary'; ?>" name="xi_shop_do" value="refund_ok" data-confirm="<?php esc_attr_e('确认退款？款项会退回买家的余额 / 积分', 'xi'); ?>"><?php echo isset($o->refund['stage']) && $o->refund['stage'] === 'returned' ? esc_html__('确认收到退货并退款', 'xi') : esc_html__('同意退款', 'xi'); ?></button>
                                        <button class="button" name="xi_shop_do" value="refund_no"><?php esc_html_e('驳回', 'xi'); ?></button>
                                    </form>
                                <?php elseif ($st === 0 || $st === 4 || $st === 2) : ?>
                                    <form method="post">
                                        <?php wp_nonce_field('xi_shop_orders'); ?>
                                        <input type="hidden" name="order_no" value="<?php echo esc_attr($o->order_no); ?>">
                                        <?php if ($st === 2) : ?>
                                            <button class="button" name="xi_shop_do" value="finish"><?php esc_html_e('标记为已完成', 'xi'); ?></button>
                                        <?php else : ?>
                                            <button class="button" name="xi_shop_do" value="paid" data-confirm="<?php esc_attr_e('确认买家已线下付款？订单会进入发货流程', 'xi'); ?>"><?php esc_html_e('补单', 'xi'); ?></button>
                                            <?php if ($st === 0) : ?><button class="button button-link-delete" name="xi_shop_do" value="close"><?php esc_html_e('关闭订单', 'xi'); ?></button><?php endif; ?>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </details>
                    </td>
                    <td><?php echo esc_html(xi_price_text($o->total, $o->price_type)); ?>
                        <?php if ((float) $o->discount > 0) : ?><br><small><?php printf(esc_html__('优惠 %s', 'xi'), esc_html(xi_money($o->discount))); ?></small><?php endif; ?>
                        <?php if ($o->pay_type) : ?><br><small><?php echo esc_html($o->pay_type === 'manual' ? __('后台补单', 'xi') : xi_shop_pay_type_name($o->pay_type)); ?></small><?php endif; ?>
                    </td>
                    <td><b style="color:<?php echo esc_attr($colors[$st]); ?>"><?php echo esc_html(xi_shop_status_name($st)); ?></b></td>
                    <td><?php echo esc_html(mysql2date('Y-m-d H:i', $o->created_at)); ?><?php if ($o->paid_at) : ?><br><small><?php printf(esc_html__('付款 %s', 'xi'), esc_html(mysql2date('m-d H:i', $o->paid_at))); ?></small><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $pages = (int) ceil($total / $per);
        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links(array('base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $paged, 'total' => $pages)) . '</div></div>';
        }
        ?>
        <script>
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-confirm]');
            if (btn && !confirm(btn.getAttribute('data-confirm'))) e.preventDefault();
        });
        </script>
    </div>
    <?php
}

/* ---------- 主题设置字段 ---------- */

function xi_shop_option_fields()
{
    $fields = array(
        array(
            'id'      => 'shop_s',
            'type'    => 'switcher',
            'title'   => __('启用商城', 'xi'),
            'desc'    => __('开启后后台出现「商城」菜单，可发布虚拟 / 实物商品。保存后访问一次前台即可生效', 'xi'),
            'default' => false,
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_name',
            'type'       => 'text',
            'title'      => __('商城名称', 'xi'),
            'default'    => __('商城', 'xi'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_slug',
            'type'       => 'text',
            'title'      => __('地址前缀', 'xi'),
            'default'    => 'shop',
            'desc'       => sprintf(__('商城首页：%s', 'xi'), '<code>' . esc_html(home_url('/' . xi_shop_slug() . '/')) . '</code>'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_per_page',
            'type'       => 'number',
            'title'      => __('每页商品数', 'xi'),
            'default'    => 12,
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_header_cart',
            'type'       => 'switcher',
            'title'      => __('顶部购物车按钮', 'xi'),
            'default'    => true,
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'type'       => 'subheading',
            'content'    => __('付款与优惠', 'xi'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_balance_pay',
            'type'       => 'switcher',
            'title'      => __('允许余额支付', 'xi'),
            'desc'       => __('在线支付在「在线支付」中配置；积分商品使用积分支付', 'xi'),
            'default'    => true,
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_vip_discount_1',
            'type'       => 'number',
            'title'      => __('一级会员折扣', 'xi'),
            'desc'       => __('9 表示 9 折，留空或 0 不打折；可在商品中单独关闭', 'xi'),
            'attributes' => array('step' => '0.1', 'min' => 0, 'max' => 9.9),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_vip_discount_2',
            'type'       => 'number',
            'title'      => __('二级会员折扣', 'xi'),
            'attributes' => array('step' => '0.1', 'min' => 0, 'max' => 9.9),
        ),
        array(
            'dependency'   => array('shop_s', '!=', ''),
            'id'           => 'shop_coupons',
            'type'         => 'group',
            'title'        => __('优惠码', 'xi'),
            'desc'         => __('仅现金订单可用', 'xi'),
            'button_title' => __('添加优惠码', 'xi'),
            'fields'       => array(
                array('id' => 'code', 'type' => 'text', 'title' => __('优惠码', 'xi')),
                array(
                    'id'      => 'type',
                    'type'    => 'button_set',
                    'title'   => __('类型', 'xi'),
                    'default' => 'reduce',
                    'options' => array('reduce' => __('立减（元）', 'xi'), 'discount' => __('折扣', 'xi')),
                ),
                array('id' => 'value', 'type' => 'number', 'title' => __('面额', 'xi'), 'desc' => __('立减填金额；折扣填 8.5 表示 85 折', 'xi'), 'attributes' => array('step' => '0.01')),
                array('id' => 'min', 'type' => 'number', 'title' => __('最低消费', 'xi'), 'default' => 0, 'attributes' => array('step' => '0.01')),
                array('id' => 'limit', 'type' => 'number', 'title' => __('可用次数', 'xi'), 'desc' => __('0 为不限', 'xi'), 'default' => 0),
                array('id' => 'expire', 'type' => 'date', 'title' => __('有效期至', 'xi'), 'settings' => array('dateFormat' => 'yy-mm-dd')),
                array('id' => 'vip', 'type' => 'switcher', 'title' => __('仅限会员', 'xi'), 'default' => false),
            ),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'type'       => 'subheading',
            'content'    => __('满减活动', 'xi'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_promo_s',
            'type'       => 'switcher',
            'title'      => __('开启满减', 'xi'),
            'desc'       => __('全店现金商品参与，在优惠码之前计算', 'xi'),
            'default'    => false,
        ),
        array(
            'dependency' => array(array('shop_s', '!=', ''), array('shop_promo_s', '!=', '')),
            'id'         => 'shop_promo_name',
            'type'       => 'text',
            'title'      => __('活动名称', 'xi'),
            'default'    => __('满减活动', 'xi'),
        ),
        array(
            'dependency' => array(array('shop_s', '!=', ''), array('shop_promo_s', '!=', '')),
            'id'         => 'shop_promo_tiers',
            'type'       => 'textarea',
            'title'      => __('满减档位', 'xi'),
            'desc'       => __('每行一档，格式「满多少-减多少」，例如：<br>100-10<br>200-30', 'xi'),
            'default'    => "100-10\n200-30",
        ),
        array(
            'dependency' => array(array('shop_s', '!=', ''), array('shop_promo_s', '!=', '')),
            'id'         => 'shop_promo_start',
            'type'       => 'date',
            'title'      => __('开始日期', 'xi'),
            'settings'   => array('dateFormat' => 'yy-mm-dd'),
        ),
        array(
            'dependency' => array(array('shop_s', '!=', ''), array('shop_promo_s', '!=', '')),
            'id'         => 'shop_promo_end',
            'type'       => 'date',
            'title'      => __('结束日期', 'xi'),
            'desc'       => __('留空则长期有效', 'xi'),
            'settings'   => array('dateFormat' => 'yy-mm-dd'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'type'       => 'subheading',
            'content'    => __('积分抵扣与运费', 'xi'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_points_deduct_s',
            'type'       => 'switcher',
            'title'      => __('积分抵扣现金', 'xi'),
            'desc'       => __('现金订单结算时可用积分抵扣部分金额，取消或退款时退回', 'xi'),
            'default'    => false,
        ),
        array(
            'dependency' => array(array('shop_s', '!=', ''), array('shop_points_deduct_s', '!=', '')),
            'id'         => 'shop_points_rate',
            'type'       => 'number',
            'title'      => __('抵扣比例', 'xi'),
            'desc'       => __('多少积分抵 1 元', 'xi'),
            'default'    => 100,
        ),
        array(
            'dependency' => array(array('shop_s', '!=', ''), array('shop_points_deduct_s', '!=', '')),
            'id'         => 'shop_points_max_pct',
            'type'       => 'number',
            'title'      => __('最多抵扣', 'xi'),
            'unit'       => '%',
            'desc'       => __('占订单金额（不含运费）的最高比例', 'xi'),
            'default'    => 20,
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_free_shipping',
            'type'       => 'number',
            'title'      => __('满额包邮', 'xi'),
            'desc'       => __('实物订单优惠后金额达到此数免运费，0 为不设置；运费在商品中单独设置', 'xi'),
            'default'    => 0,
            'attributes' => array('step' => '0.01'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_vip_free_shipping',
            'type'       => 'switcher',
            'title'      => __('会员包邮', 'xi'),
            'default'    => false,
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'type'       => 'subheading',
            'content'    => __('联系商家', 'xi'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_contact_qq',
            'type'       => 'text',
            'title'      => __('客服 QQ', 'xi'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_contact_wechat',
            'type'       => 'upload',
            'title'      => __('客服微信二维码', 'xi'),
            'library'    => 'image',
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_contact_text',
            'type'       => 'textarea',
            'title'      => __('其他联系方式', 'xi'),
            'desc'       => __('如邮箱、工作时间等，显示在联系商家弹窗中', 'xi'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'type'       => 'subheading',
            'content'    => __('订单与售后', 'xi'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_unpaid_minutes',
            'type'       => 'number',
            'title'      => __('未付款自动关闭', 'xi'),
            'unit'       => __('分钟', 'xi'),
            'default'    => 30,
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_auto_confirm_days',
            'type'       => 'number',
            'title'      => __('发货后自动确认收货', 'xi'),
            'unit'       => __('天', 'xi'),
            'default'    => 10,
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_refund_s',
            'type'       => 'switcher',
            'title'      => __('允许申请售后', 'xi'),
            'desc'       => __('同意退款后，余额 / 在线支付的订单退回到买家站内余额，积分订单退回积分', 'xi'),
            'default'    => true,
        ),
        array(
            'dependency' => array(array('shop_s', '!=', ''), array('shop_refund_s', '!=', '')),
            'id'         => 'shop_refund_days',
            'type'       => 'number',
            'title'      => __('售后期限', 'xi'),
            'unit'       => __('天', 'xi'),
            'desc'       => __('付款后多少天内可申请，0 为不限', 'xi'),
            'default'    => 7,
        ),
        array(
            'dependency' => array(array('shop_s', '!=', ''), array('shop_refund_s', '!=', '')),
            'id'         => 'shop_return_address',
            'type'       => 'textarea',
            'title'      => __('退货地址', 'xi'),
            'desc'       => __('买家申请「退货退款」并经你同意后显示给买家', 'xi'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_stock_alert',
            'type'       => 'number',
            'title'      => __('库存预警', 'xi'),
            'desc'       => __('付款后剩余库存（含卡密）不超过此数时邮件提醒站长，0 为关闭', 'xi'),
            'default'    => 3,
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_express',
            'type'       => 'textarea',
            'title'      => __('快递公司', 'xi'),
            'desc'       => __('发货时可选的快递公司，逗号或换行分隔', 'xi'),
            'default'    => '顺丰速运,中通快递,圆通速递,韵达快递,申通快递,京东物流,邮政EMS,极兔速递',
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_notify_admin_s',
            'type'       => 'switcher',
            'title'      => __('新订单邮件提醒', 'xi'),
            'desc'       => __('有待发货订单、售后申请时发邮件给站长', 'xi'),
            'default'    => true,
        ),
        array(
            'dependency'  => array(array('shop_s', '!=', ''), array('shop_notify_admin_s', '!=', '')),
            'id'          => 'shop_notify_email',
            'type'        => 'text',
            'title'       => __('提醒邮箱', 'xi'),
            'placeholder' => get_option('admin_email'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'id'         => 'shop_notice',
            'type'       => 'textarea',
            'title'      => __('购买须知', 'xi'),
            'desc'       => __('显示在商品页和确认订单页', 'xi'),
        ),
        array(
            'dependency' => array('shop_s', '!=', ''),
            'type'       => 'submessage',
            'style'      => 'info',
            'content'    => __('在任意页面插入短代码 <code>[xi_products cat="分类别名" count="8" sort="sales"]</code> 可展示商品列表；sort 可选 sales、new、price_asc、price_desc', 'xi'),
        ),
    );
    return $fields;
}
