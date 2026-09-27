=== StoreWhy ===
Contributors: lukystile
Tags: woocommerce, analytics, reports, sales, activity log
Requires at least: 6.3
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce numbers with context: daily sales, views and add-to-cart next to what changed on your site that day.

== Description ==

Most reports tell you *what* happened: sales went down on Tuesday. StoreWhy puts your numbers next to *what changed on your site* that day, so the likely reason is right there: a promo banner was removed, a free-shipping bar was switched off, a product went out of stock, a price changed, a plugin was updated.

* **Two simple charts** for the last 30 days: revenue and product views per day, with a marker on every day something changed on the site.
* **Day-by-day table** with orders, revenue, product views, add-to-cart, and the list of changes for that day.
* **Top products** with views, add-to-cart, units sold, revenue and a views-to-purchase rate.
* **A change timeline, recorded automatically:** products published or taken offline, price, sale price and stock status changes, plugin and theme activations, switches and updates, pages published or taken offline.
* **More detail with other CognitoLab plugins:** Heralda bars switched on or off or rescheduled, ProofBlocks banners, counters and pricing tables added to or removed from live pages, Watermark Guru setting changes, and changes AI agents made through Tillkeeper. Nothing extra to set up: if those plugins are active, their changes show up.
* **Always up to date.** A new order, a status change or a refund recounts that day in the background. On first use StoreWhy fills in the last 30 days from your existing orders.

= Private by design =

Product views are counted by a tiny script on the product page, so page caching does not hide them. The request carries only the product ID: no cookies, no IP addresses, nothing stored about the visitor. Bots, requests from other sites and bursts of repeated requests are ignored, and store staff are not counted. You can switch view counting off at any time.

The change timeline stores IDs, types and before/after values of settings only - never customer names, emails, addresses or order contents.

= Correlation, not blame =

StoreWhy shows what changed *around* a change in your numbers. It does not claim one caused the other - that is for you to judge.

== External services ==

This plugin does not connect to any external service. All numbers are calculated on your own site.

== Installation ==

1. Install and activate WooCommerce, then StoreWhy.
2. Open **WooCommerce → StoreWhy**. The last 30 days are filled in from your orders within a few minutes; product views start counting from the moment you activate the plugin.

== Frequently Asked Questions ==

= Where do the order numbers come from? =

From your WooCommerce orders with a paid or on-hold status, minus refunds, grouped by the day the order was placed in your site's time zone.

= Does it support High-Performance Order Storage (HPOS)? =

Yes.

= Will it slow my shop down? =

No. Order numbers are counted in the background by WooCommerce's scheduler, never while a page loads. On product pages StoreWhy adds one small deferred script that sends a single request.

= Do I need a cookie banner for the view counter? =

StoreWhy sets no cookies and stores nothing on the visitor's device or about the visitor. Whether your site needs a consent banner depends on everything else you run; StoreWhy adds nothing to it.

= What happens to my data if I uninstall? =

StoreWhy's daily numbers are removed. The change timeline is shared with other CognitoLab plugins that use it and is removed when the last of them is uninstalled.

== Screenshots ==

1. Revenue and product views per day, with markers on days something changed on the site.
2. The day-by-day table: a sales dip next to the changes made that day.
3. Top products with the views-to-purchase rate, and settings.

== Changelog ==

= 1.0.0 =
* First public release.
* Revenue and product-view charts for the last 30 days with change markers.
* Daily orders, revenue, product views and add-to-cart; top products.
* Automatic change timeline for WordPress, WooCommerce and CognitoLab plugins.
* Anonymous, cache-friendly product view counter with bot, cross-site and burst filtering.
* Background recount on order changes; 30-day backfill on first use.
