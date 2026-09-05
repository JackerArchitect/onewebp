=== OneWebP ===
Contributors: JackerArchitect
Tags: webp, image optimization, lazy load, performance, speed, free, local converter
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

100% Free, Unlimited, Zero API. Convert your images to WebP locally.

== Description ==

OneWebP is the ultimate, truly free image optimization plugin for WordPress. No monthly fees, no image limits, and no third-party APIs. Your images never leave your server.

= Core Features =

* **100% Local Processing**: Zero API calls. All conversion happens on your server.
* **Unlimited & Free**: Convert as many images as you want, no restrictions.
* **Smart Memory Management**: Automatically detects available PHP memory and adjusts batch size.
* **Smart Queue Lazy Loading**: Critical images load instantly, others use queue-based lazy load.
* **Automatic Downscaling**: Images larger than 3000px are automatically resized.
* **One-Click Dashboard**: Real-time progress dashboard to optimize your entire media library.
* **Server Health Monitor**: Built-in warnings for low disk space and low RAM.
* **Image Manager**: Detailed list of converted images with individual actions.
* **External Image Support**: Download and convert external images to WebP.

= Supported Formats =

* JPEG/JPG → WebP (Fully supported)
* PNG → WebP (Preserves transparency)
* GIF → WebP (Static only)

== Installation ==

1. Upload the `onewebp` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to OneWebP in your WordPress dashboard.
4. Review the settings and click "Start Optimization".

== Frequently Asked Questions ==

= Does this work with animated GIFs? =

No. The GD library used for conversion does not support animated GIF conversion. Animated GIFs will be skipped.

= Will this slow down my site? =

The conversion process runs in the background. Frontend lazy loading ensures optimal performance.

= What happens to my original images? =

Original images are preserved unless you enable the "Delete Original After Conversion" option.

== Screenshots ==

1. OneWebP Dashboard: One-click optimization with real-time progress.
2. Settings Page: Granular control over quality, max resolution, and supported image types.
3. Image Manager: Detailed list of converted images with individual actions.

== Changelog ==

= 1.0.0 =
* Initial release
* Local WebP conversion
* Smart lazy loading
* Dashboard with real-time stats
* Image manager with bulk actions
* External image support
* Server health monitoring

== Upgrade Notice ==

= 1.0.0 =
Initial release. Please read documentation before use.
