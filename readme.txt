=== OneWebP ===
Contributors: Jacker Architect
Donate link: https://jackerteo.com/plugin/onewebp
Tags: webp, image optimization, lazy load, performance, speed, free, local converter
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

100% Free, Zero API, Unlimited Local Image Optimizer. One click to make your site fly.

== Description ==

OneWebP is the ultimate, truly free image optimization plugin for WordPress. No monthly fees, no image limits, and no third-party APIs. Your images never leave your server.

**Core Features:**

* **100% Local Processing:** Zero API calls. Your data stays on your server.
* **Unlimited & Free:** Convert as many images as you want. No quotas.
* **Smart Memory Engine:** Automatically detects available PHP memory and adjusts batch size to prevent 502 errors.
* **Smart Queue Lazy Loading:** Critical images load instantly, others use a queue-based lazy load to boost Core Web Vitals.
* **Automatic Downscaling:** Images larger than 3000px are automatically resized to save bandwidth.
* **One-Click Dashboard:** Real-time progress dashboard to optimize your entire media library safely.
* **Server Health Monitor:** Built-in warnings for low disk space (<500MB) and low RAM (<64MB).

**Why Choose OneWebP?**

| Feature | Traditional API Plugins | OneWebP (Local Engine) |
| :--- | :---: | :---: |
| **Cost** | Monthly limits & subscriptions | 100% Free, Unlimited |
| **Privacy** | Images sent to external servers | 100% Private (stays on server) |
| **Speed** | Slow due to network latency | Instant local processing |
| **Frontend Bloat** | Heavy external scripts | Ultra-lightweight, zero bloat |

== Installation ==

1. Upload the `onewebp` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **OneWebP** in your WordPress dashboard sidebar.
4. Review the settings and click "Start Optimization".

== Frequently Asked Questions ==

= Is it really 100% free with no limits? =
Yes. OneWebP is open-source and uses your server's native PHP GD library. There are no API keys, no monthly quotas, and no hidden fees.

= Will it crash my shared hosting server? =
No. Our Smart Memory Engine calculates the exact RAM needed for each image and dynamically adjusts the batch size to prevent memory exhaustion.

= Does it support GIF or SVG conversion? =
GIF is supported, but only as static images (animated GIFs will become static). SVG is not supported because it is a vector format and cannot be processed by the PHP GD library.

= What happens if I uninstall the plugin? =
We include a complete cleanup routine in the Settings tab. You can choose to delete all database logs, settings, and generated WebP files to free up space.

== Screenshots ==

1. OneWebP Dashboard: One-click optimization with real-time progress and statistics.
2. Settings Page: Granular control over quality, max resolution, conversion scope, and supported image types.
3. Image Manager: Detailed list of converted images with individual actions and savings comparison.
4. Custom Settings: Individual image editing page with custom quality and resolution controls for granular optimization.

== Changelog ==

= 1.0.0 =
* Initial release.
* Added local WebP conversion for JPEG, PNG, and GIF.
* Added Smart Queue Lazy Load for better Core Web Vitals.
* Added automatic image downscaling for oversized images.
* Added Server Health Monitor for disk space and RAM.
* Added complete cleanup routine for uninstallation.

== Upgrade Notice ==

= 1.0.0 =
Initial release of OneWebP. Enjoy 100% free, unlimited local WebP optimization for your WordPress site!
