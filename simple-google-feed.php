<?php
/*
Plugin Name: Simple Google Feed Generator (6h Auto Refresh)
Plugin URI:  https://elmertiman.com
Description: Generates a Google Shopping XML feed compatible with Google Merchant Center. Auto-refreshes every 6 hours, excludes out-of-stock products, and includes a manual regeneration button in WooCommerce admin.
Version:     1.5.1
Author:      Elmer Timan
Author URI:  https://elmertiman.com
License:     GPL2
*/

if (!defined('ABSPATH')) exit;

// --- Helper: Escape XML safely ---
if (!function_exists('esc_xml')) {
    function esc_xml($text) {
        $text = (string) $text;
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text);
        return htmlspecialchars($text, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}

// --- Generate Feed ---
function generate_and_save_google_feed() {
    if (!class_exists('WooCommerce')) return;

    $upload_dir = wp_upload_dir();
    $file = trailingslashit($upload_dir['basedir']) . 'google-feed.xml';

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
    $xml .= '<channel>' . "\n";
    $xml .= '<title>' . esc_xml(get_bloginfo('name')) . '</title>' . "\n";
    $xml .= '<link>' . esc_url(home_url('/')) . '</link>' . "\n";
    $xml .= '<description>Google Shopping feed generated from WooCommerce products.</description>' . "\n";

    $args = [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => '_stock_status',
                'value'   => 'instock',
                'compare' => '='
            ]
        ]
    ];

    $products = new WP_Query($args);
    if ($products->have_posts()) {
        while ($products->have_posts()) {
            $products->the_post();
            $product = wc_get_product(get_the_ID());
            if (!$product) continue;

            // Handle variable products - process variations instead
            if ($product->is_type('variable')) {
                $variation_ids = $product->get_children();
                foreach ($variation_ids as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if (!$variation || !$variation->is_in_stock()) continue;
                    
                    // Check stock quantity if stock management is enabled
                    if ($variation->managing_stock()) {
                        $stock_qty = $variation->get_stock_quantity();
                        if ($stock_qty !== null && $stock_qty <= 0) continue;
                    }
                    
                    $variation_xml = generate_product_xml($variation, true);
                    if ($variation_xml) $xml .= $variation_xml;
                }
                continue;
            }

            // Handle simple products
            if (!$product->is_type('simple')) continue;
            
            // Check stock quantity if stock management is enabled
            if ($product->managing_stock()) {
                $stock_qty = $product->get_stock_quantity();
                if ($stock_qty !== null && $stock_qty <= 0) continue;
            }
            
            $product_xml = generate_product_xml($product, false);
            if ($product_xml) $xml .= $product_xml;
        }
    }
    wp_reset_postdata();

    $xml .= '</channel></rss>';

    if (is_writable(trailingslashit($upload_dir['basedir']))) {
        file_put_contents($file, $xml);
    }
}

// --- Generate XML for a single product ---
function generate_product_xml($product, $is_variation = false) {
    if (!$product || !$product->is_in_stock()) return '';

    $title        = get_the_title($product->get_id());
    if ($is_variation) {
        $parent = wc_get_product($product->get_parent_id());
        $title = $parent ? $parent->get_name() . ' - ' . $title : $title;
        $link = $parent ? get_permalink($parent->get_id()) : get_permalink($product->get_id());
    } else {
        $link = get_permalink($product->get_id());
    }
    
    // Get description - use product description if excerpt is empty
    $desc = get_the_excerpt($product->get_id());
    if (empty($desc)) {
        $desc = wp_strip_all_tags($product->get_description());
    } else {
        $desc = wp_strip_all_tags($desc);
    }
    // Clean up any remaining HTML entities and special characters
    $desc = str_replace('&hellip;', '...', $desc);
    $desc = str_replace('[&hellip;]', '...', $desc);
    
    $price        = $product->get_price();
    if (empty($price) || $price <= 0) return ''; // Skip products without price
    
    $currency     = get_woocommerce_currency();
    $availability = $product->is_in_stock() ? 'in stock' : 'out of stock';
    $brand        = $product->get_attribute('pa_brand');
    if (empty($brand)) {
        $brand = get_post_meta($product->get_id(), '_brand', true);
    }
    if (empty($brand) && $is_variation) {
        $parent = wc_get_product($product->get_parent_id());
        if ($parent) {
            $brand = $parent->get_attribute('pa_brand');
            if (empty($brand)) {
                $brand = get_post_meta($parent->get_id(), '_brand', true);
            }
        }
    }
    
    $sku          = $product->get_sku();
    if (empty($sku)) {
        $sku = $product->get_id();
    }
    
    $image        = wp_get_attachment_url($product->get_image_id());
    if (empty($image) && $is_variation) {
        $parent = wc_get_product($product->get_parent_id());
        if ($parent) {
            $image = wp_get_attachment_url($parent->get_image_id());
        }
    }
    if (empty($image)) return ''; // Skip products without image
    
    $quantity     = $product->get_stock_quantity();
    if ($quantity === null || $quantity === false) {
        $quantity = $product->is_in_stock() ? 999 : 0;
    }
    
    $sale_price   = $product->get_sale_price();
    $gtin         = get_post_meta($product->get_id(), '_gtin', true);

    $xml = '<item>';
    $xml .= '<g:id>' . esc_html($sku) . '</g:id>';
    $xml .= '<title><![CDATA[' . $title . ']]></title>';
    $xml .= '<link><![CDATA[' . esc_url($link) . ']]></link>';
    if (!empty($desc)) {
        $xml .= '<description><![CDATA[' . $desc . ']]></description>';
    }
    $xml .= '<g:availability>' . esc_html($availability) . '</g:availability>';
    $xml .= '<g:price>' . esc_html($price) . ' ' . esc_html($currency) . '</g:price>';
    if ($sale_price) $xml .= '<g:sale_price>' . esc_html($sale_price) . ' ' . esc_html($currency) . '</g:sale_price>';
    if ($brand) $xml .= '<g:brand><![CDATA[' . $brand . ']]></g:brand>';
    $xml .= '<g:condition>new</g:condition>';
    $xml .= '<g:image_link><![CDATA[' . esc_url($image) . ']]></g:image_link>';
    $xml .= '<g:link><![CDATA[' . esc_url($link) . ']]></g:link>';
    $xml .= '<g:mpn><![CDATA[' . esc_html($sku) . ']]></g:mpn>';
    if ($gtin) $xml .= '<g:gtin><![CDATA[' . esc_html($gtin) . ']]></g:gtin>';
    $xml .= '<g:quantity>' . esc_html($quantity) . '</g:quantity>';
    $xml .= '</item>';

    return $xml;
}

// --- WP Cron: 6-hour Feed Refresh ---
function google_feed_cron_schedule_6h() {
    if (!wp_next_scheduled('google_feed_six_hour_event')) {
        wp_schedule_event(time(), 'six_hour', 'google_feed_six_hour_event');
    }
}
add_action('wp', 'google_feed_cron_schedule_6h');
add_action('google_feed_six_hour_event', 'generate_and_save_google_feed');

add_filter('cron_schedules', function($schedules) {
    $schedules['six_hour'] = [
        'interval' => 6 * 60 * 60,
        'display'  => 'Every 6 Hours'
    ];
    return $schedules;
});

// --- Activation / Deactivation ---
function google_feed_plugin_activate_6h() {
    generate_and_save_google_feed();
    google_feed_cron_schedule_6h();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'google_feed_plugin_activate_6h');

function google_feed_plugin_deactivate_6h() {
    $timestamp = wp_next_scheduled('google_feed_six_hour_event');
    if ($timestamp) wp_unschedule_event($timestamp, 'google_feed_six_hour_event');
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'google_feed_plugin_deactivate_6h');

// --- Serve Static Feed ---
add_action('init', function() {
    add_rewrite_rule('^google-feed\.xml$', 'index.php?custom_google_feed_static=1', 'top');
});
add_filter('query_vars', function($vars) {
    $vars[] = 'custom_google_feed_static';
    return $vars;
});
add_action('template_redirect', function() {
    if (get_query_var('custom_google_feed_static')) {
        $upload_dir = wp_upload_dir();
        $file = trailingslashit($upload_dir['basedir']) . 'google-feed.xml';
        if (file_exists($file)) {
            header('Content-Type: application/xml; charset=UTF-8');
            readfile($file);
            exit;
        } else {
            wp_die('Feed file not found.');
        }
    }
});

// --- Admin Regenerate Button ---
add_action('admin_menu', function() {
    if (!class_exists('WooCommerce')) return;
    add_submenu_page(
        'woocommerce',
        'Google Feed',
        'Google Feed',
        'manage_options',
        'google-feed-admin',
        'google_feed_admin_page'
    );
});

function google_feed_admin_page() {
    if (isset($_POST['regenerate_google_feed'])) {
        generate_and_save_google_feed();
        echo '<div class="updated notice"><p>Google feed regenerated successfully!</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Google Shopping Feed</h1>
        <form method="post">
            <input type="hidden" name="regenerate_google_feed" value="1">
            <button type="submit" class="button button-primary">Regenerate Feed Now</button>
        </form>
        <p>Feed URL: <code><?php echo esc_url(home_url('/google-feed.xml')); ?></code></p>
    </div>
    <?php
}
