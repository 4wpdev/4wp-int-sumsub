# 4wp SumSub Integration

WordPress plugin for integrating SumSub identity verification with Gravity Forms and Contact Form 7.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your SumSub credentials in the admin settings

## Configuration

1. Go to **Admin Dashboard → SumSub Integ.**
2. Enter your SumSub API credentials:
   - **App Token** (e.g., `sbx:xxxxx` for sandbox)
   - **Secret Key**
   - **Level Name** (default: `basic-kyc-level`)
3. Click **Save Changes**

## Usage with Gravity Forms

1. Create or edit a Gravity Form
2. Add the SumSub verification shortcode in any field:
   ```
   [sumsub_verification]
   ```
3. The SDK will initialize automatically when the form loads
4. Users must complete verification before form submission

### Multi-step Forms (Recommended)

For better user experience, use multi-step forms:

1. Create a multi-step Gravity Form
2. Place the `[sumsub_verification]` shortcode on a dedicated step
3. Users will complete verification on that step
4. Form submission is blocked until verification is completed

## Features

- ✅ **Automatic SDK initialization** only for forms with shortcode
- ✅ **Multi-step form support** with dynamic SDK loading
- ✅ **Form submission blocking** until verification is completed
- ✅ **Popup and modal compatibility**
- ✅ **User-friendly interface** with status messages
- ⏳ Contact Form 7 support (near future...)

## Requirements

- WordPress 5.0+
- Gravity Forms plugin
- Valid SumSub account and API credentials

## Support

For issues and feature requests, contact [4wp.dev](https://4wp.dev)

---

**Author:** 4wp  
**Version:** 0.1.1
