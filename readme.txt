=== StoreWhy ===
Contributors: lukystile
Tags: woocommerce, analytics, reports, sales, activity log
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 0.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce numbers with context: daily sales, views and add-to-cart next to what changed on your site that day.

== Description ==

Most reports tell you *what* happened: sales went down on Tuesday. StoreWhy puts the numbers next to *what changed* on the site that day, so the likely reason is one click away: a product went out of stock, a price changed, a promo bar was turned off, a banner disappeared from the sale page, a plugin was updated.

* **Daily store metrics:** orders, revenue, product views, add-to-cart.
* **Per-product numbers** and a simple views-to-purchase rate.
* **Change timeline** for WordPress and WooCommerce, plus Heralda bars, ProofBlocks blocks, Watermark Guru and Tillkeeper when they are installed.
* **Anonymous by design.** Views are counted without cookies and without storing anything about the visitor. Store staff are not counted.
* **Works with page caching.** Views are counted by a tiny script, so cached pages are counted too.

StoreWhy shows changes that happened *around* a change in your numbers. It does not claim one caused the other - that is for you to judge.

== Frequently Asked Questions ==

= Where does the order data come from? =

From your WooCommerce orders (paid and on-hold statuses), summarised once a night into one row per day. Use "Rebuild last 30 days" to fill in history.

= Does it support High-Performance Order Storage (HPOS)? =

Yes.

== Changelog ==

= 0.1.0 =
* Skeleton: daily metrics, anonymous view and add-to-cart counters, change timeline next to each day.
