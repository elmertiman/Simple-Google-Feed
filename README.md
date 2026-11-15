# Simple Google Feed Generator (6h Auto Refresh)

**Current Version:** `1.5.1` | **Requires WordPress:** `5.0+` | **Requires WooCommerce:** `3.0+` | **Requires PHP:** `7.4+` | **License:** `GPL2`

A lightweight WooCommerce plugin that generates a Google Shopping XML feed (`google-feed.xml`) compatible with **Google Merchant Center**. It automatically refreshes the feed every 6 hours via WP-Cron, excludes out-of-stock products, supports both Simple and Variable products, and provides a one-click manual regeneration button in the WooCommerce admin menu.

---

## Features

- **Google Merchant Center Compatible**: Generates a standard RSS 2.0 XML feed using the `http://base.google.com/ns/1.0` (`g:`) namespace.
- **6-Hour Auto-Refresh**: Automatically regenerates the XML feed every 6 hours using a custom WP-Cron schedule (`six_hour` / `google_feed_six_hour_event`).
- **In-Stock Filtering**: Automatically excludes out-of-stock products and variations (checks both `_stock_status = instock` and stock quantity `> 0` when stock management is enabled).
- **Simple & Variable Product Support**:
  - **Simple Products**: Exports directly as individual `<item>` entries.
  - **Variable Products**: Loops through all in-stock variations and exports each variation with parent title context (`Parent Name - Variation Title`) and fallback to parent image/brand when needed.
- **Static XML File + Clean Rewrite URL**:
  - Saves the generated XML to `wp-content/uploads/google-feed.xml`.
  - Serves the feed cleanly at `https://yourdomain.com/google-feed.xml`.
- **One-Click Manual Regeneration**: Includes a **WooCommerce → Google Feed** admin page with a **Regenerate Feed Now** button.
- **Safe XML Escaping**: Strips invalid UTF-8 / control characters and uses `CDATA` and XML escaping for product titles, descriptions, links, and attributes.

---

## Installation

1. Upload the `Simple-Google-Feed` folder to your `/wp-content/plugins/` directory.
2. Activate **Simple Google Feed Generator (6h Auto Refresh)** through the **Plugins** menu in WordPress.
3. Ensure **WooCommerce** is installed and active.
4. Upon activation, the plugin automatically:
   - Generates the initial `google-feed.xml` file in `/wp-content/uploads/`
   - Schedules the 6-hour WP-Cron event (`google_feed_six_hour_event`)
   - Registers and flushes the `/google-feed.xml` rewrite rule

> **Note:** If `/google-feed.xml` returns a 404 after installation, go to **Settings → Permalinks** and click **Save Changes** to flush rewrite rules.

---

## Usage

### Accessing Your Feed URL

Once activated, your Google Shopping XML feed is available at:

```text
https://yourdomain.com/google-feed.xml
```

*(It is also stored directly at `https://yourdomain.com/wp-content/uploads/google-feed.xml`.)*

You can submit this URL directly to **Google Merchant Center** as a scheduled fetch feed.

### Manually Regenerating the Feed

1. In your WordPress admin dashboard, navigate to **WooCommerce → Google Feed**.
2. Click the **Regenerate Feed Now** button.
3. A confirmation notice (`Google feed regenerated successfully!`) will appear once the XML file is updated.

---

## XML Feed Specification

Each product or variation `<item>` includes the following Google Merchant Center fields:

| XML Tag | Source / Logic | Required to Include Item? |
| --- | --- | --- |
| `<g:id>` | Product SKU (falls back to Product/Variation ID if SKU is empty) | Yes |
| `<title>` | Product title (or `Parent Name - Variation Title` for variations) | Yes |
| `<link>` / `<g:link>` | Product permalink (parent permalink for variations) | Yes |
| `<description>` | Product short description / excerpt (falls back to full description, stripped of HTML tags) | Optional (omitted if empty) |
| `<g:availability>` | `in stock` (only in-stock items are exported) | Yes |
| `<g:price>` | Active price + store currency (e.g., `99.00 USD`) | **Yes** (skipped if empty or `<= 0`) |
| `<g:sale_price>` | Sale price + store currency | Optional (included when on sale) |
| `<g:brand>` | `pa_brand` product attribute or `_brand` post meta (falls back to parent on variations) | Optional |
| `<g:condition>` | Hardcoded to `new` | Yes |
| `<g:image_link>` | Featured image URL (falls back to parent product image for variations) | **Yes** (skipped if no image) |
| `<g:mpn>` | Product SKU (or ID fallback) | Yes |
| `<g:gtin>` | `_gtin` post meta | Optional |
| `<g:quantity>` | Stock quantity (defaults to `999` if in stock without quantity management) | Yes |

### Product Filtering Rules

A product or variation is **excluded** from the feed if any of the following are true:
- Post status is not `publish`
- Product type is neither `simple` nor `variable`
- Stock status is not `instock` (or stock management is enabled and stock quantity is `<= 0`)
- Product has no price or price is `<= 0`
- Product (and its parent, if a variation) has no featured image

---

## File Structure

```text
Simple-Google-Feed/
├── simple-google-feed.php    # Main plugin file (feed generator, cron schedule, rewrite rules, admin UI)
└── README.md                 # Plugin documentation
```

---

## Requirements

- **WordPress:** 5.0 or higher
- **WooCommerce:** 3.0 or higher
- **PHP:** 7.4 or higher (with `mbstring` enabled for UTF-8 sanitization)
- Writable `/wp-content/uploads/` directory

---

## Author & License

- **Author:** [Elmer Timan](https://elmertiman.com)
- **License:** GPL v2 or later

