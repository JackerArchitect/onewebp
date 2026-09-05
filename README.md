# OneWebP

**100% Free, Unlimited Local WebP Optimizer & SEO Booster for WordPress.**

OneWebP is a lightweight, open-source WordPress plugin designed to boost your website's performance and Core Web Vitals. It converts your images to the modern WebP format locally on your server, ensuring maximum privacy, zero API limits, and faster loading speeds.

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![License](https://img.shields.io/badge/license-GPL--2.0-green)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)
![Downloads](https://img.shields.io/badge/downloads-0-orange)

---

## ✨ Features

| Feature | Description |
|---------|-------------|
| **100% Local Processing** | Zero API calls. Images never leave your server. Maximum privacy guaranteed. |
| **Unlimited & Free** | No monthly limits, no quotas, no hidden fees. Convert as many images as you want. |
| **Smart Memory Engine** | Dynamically calculates memory requirements and adjusts batch sizes. Prevents 502 errors on shared hosting. |
| **Smart Queue Lazy Loading** | Critical images load instantly with `fetchpriority="high"`, others lazy-load via smart queue. Boosts LCP and Core Web Vitals. |
| **Automatic Downscaling** | Automatically resizes images larger than your defined max resolution (default: 3000px). Saves bandwidth and improves load times. |
| **One-Click Dashboard** | Beautiful, native WordPress UI with real-time stats, progress bars, and bulk optimization. |
| **Server Health Monitor** | Proactive warnings for low disk space (<500MB) and low RAM (<64MB). Prevents disasters before they happen. |
| **Image Manager** | Detailed list of all converted images with individual actions: Edit, Reoptimize, Remove, Copy URL. |
| **Bulk Actions** | Delete or reoptimize multiple images at once. |
| **Search & Filter** | Quickly find images in the manager. |

---

## 🔥 Why OneWebP?

| Feature | Traditional API Plugins | OneWebP (Local Engine) |
| :--- | :---: | :---: |
| **Cost** | 💸 Monthly limits & subscriptions | 🆓 100% Free, Unlimited |
| **Privacy** | 🔓 Images sent to external servers | 🔒 100% Private (stays on server) |
| **Speed** | 🐢 Slow due to network latency | ⚡ Instant local processing |
| **Frontend Bloat** | 🏋️ Heavy external scripts | 🪶 Ultra-lightweight, zero bloat |
| **Data Limits** | 📊 Monthly quotas | ♾️ Unlimited conversions |
| **Processing** | ☁️ Cloud-based (third-party) | 🖥️ Local (your own server) |

---

## 📸 Supported Formats

| Format | Support | Notes |
|--------|---------|-------|
| **JPEG/JPG → WebP** | ✅ Fully supported | Best compression results. Up to 80% smaller. |
| **PNG → WebP** | ✅ Fully supported | Preserves transparent background. |
| **GIF → WebP** | ⚠️ Static only | Animated GIFs become static (first frame only). GD library limitation. |
| **WebP** | ✅ Already WebP | No conversion needed. |
| **SVG** | ❌ Not supported | Vector format. Cannot be converted by GD library. |
| **BMP/TIFF** | ⚠️ Not recommended | Very large file sizes may cause memory issues. |

---

## 🗑️ Image Deletion Modes

OneWebP offers three deletion modes to give you full control over your images:

| Mode | Behavior | Best For |
|------|----------|----------|
| **Sync Delete (Recommended)** | When original images are deleted from Media Library, WebP files are automatically deleted too. | Most users. Keeps your server clean. |
| **Keep WebP After Deletion** | WebP files remain on server even when original images are deleted. | When you want to keep WebP versions for future use. |
| **Delete Original After Conversion** | Automatically delete original images after WebP conversion. ⚠️ **Cannot re-convert!** | Maximum space savings. Advanced users only. |

---

## 📥 Installation

### Quick Install (60 seconds)

1. Download the latest release ZIP file from the [Releases page](https://github.com/JackerArchitect/onewebp/releases).
2. Log in to your WordPress admin dashboard.
3. Navigate to **Plugins → Add New → Upload Plugin**.
4. Upload the `onewebp.zip` file and click **Install Now**.
5. Click **Activate Plugin**.
6. Go to the **OneWebP** menu in your sidebar.
7. Review settings and click **Start Optimization**.

### Manual Installation

1. Upload the `onewebp` folder to `/wp-content/plugins/` via FTP.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure settings and start optimizing.

---

## 🚀 Quick Start

1. After activation, navigate to OneWebP in your WordPress admin
2. Check the Settings tab and adjust:
   - WebP Quality (default: 82)
   - Max Resolution (default: 3000px)
   - Image Types to Convert
   - Deletion Mode
3. Go to Dashboard and click "Start Optimization"
4. Monitor real-time progress

---

## 📋 Requirements

| Requirement | Minimum Version |
|-------------|-----------------|
| **WordPress** | 5.8+ |
| **PHP** | 7.4+ |
| **PHP Extension** | GD Library (required) |
| **Memory Limit** | 128MB+ (recommended) |
| **Disk Space** | 500MB+ free (recommended) |

---

## 📊 Performance Impact

| Metric | Before | After OneWebP | Improvement |
|--------|--------|---------------|-------------|
| Average Image Size | 1.2 MB | 320 KB | **-73%** |
| LCP Score | 3.4s | 1.2s | **-65%** |
| Page Load Time | 2.8s | 1.1s | **-61%** |
| Page Weight | 4.5 MB | 1.6 MB | **-64%** |
| Server CPU Usage | Normal | +5-10% | Minimal |

*Results may vary based on image content, server configuration, and hosting environment.*

---

## 🔒 Privacy Guarantee

OneWebP is **100% private**:

- ❌ No external API calls
- ❌ No data sent to third-party servers
- ❌ No tracking or analytics
- ❌ No user data collection
- ✅ All processing happens on your own server
- ✅ Your images never leave your hosting environment
- ✅ Your privacy is 100% protected

---

## 🐛 Known Issues

| Issue | Status | Workaround |
|-------|--------|------------|
| **Animated GIF conversion** | ❌ Not supported | Static GIF only. Use a dedicated GIF optimizer. |
| **SVG conversion** | ❌ Not supported | SVG is vector format. Keep as SVG for best results. |
| **BMP/TIFF conversion** | ⚠️ Not recommended | Large file sizes may cause memory issues. Convert manually first. |
| **Memory exhaustion** | ⚠️ Possible | Increase PHP memory_limit or reduce batch size. |

---

## 🤝 Contributing

We welcome contributions! Here's how you can help:

1. 🐛 **Report bugs** - Open an issue on GitHub
2. 💡 **Suggest features** - Share your ideas
3. 🔧 **Submit PRs** - Fix bugs or add features
4. 📝 **Improve documentation** - Help others understand the plugin
5. 🌍 **Translate** - Help make OneWebP available in more languages

---

## 💬 Support & Feedback

OneWebP is built with passion. If you find this plugin useful, consider supporting the project!

| Channel | Contact |
|---------|---------|
| **Email** | [support@jackerteo.com](mailto:support@jackerteo.com) |
| **Website** | [jackerteo.com/plugin/onewebp](https://jackerteo.com/plugin/onewebp) |
| **GitHub Issues** | [Create an issue](https://github.com/JackerArchitect/onewebp/issues) |

### ☕ Buy Me a Coffee

If you'd like to support open-source development, you can donate via Solana (SOL):

```
Wallet Address: EHHPsci6pKbfL71t73KNCXrtanM1TWPrWYJZ1ik1b5FH
Network: Solana Mainnet
```

---

## 📄 License

This project is licensed under the **GNU General Public License v2.0** (GPL-2.0+).

```
Copyright © 2026 Jacker Architect. All rights reserved.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

## ⭐ Star Us

If you find OneWebP useful, please consider giving us a ⭐ on GitHub! It helps others discover the project and motivates us to keep improving it.

---

## 🙏 Acknowledgments

- WordPress for the amazing CMS
- PHP GD Library for image processing
- All contributors and supporters
- The open source community

---

**Made with ❤️ by Jacker Architect**

---

*OneWebP - 100% Free, Unlimited, Zero API. Convert your images to WebP locally.*
