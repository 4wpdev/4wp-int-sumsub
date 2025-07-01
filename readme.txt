# 4wp Sumsub Integration

A WordPress plugin for integrating SumSub verification with Contact Form 7.

## Features

- Adds a custom SumSub verification checkbox to Contact Form 7 forms.
- Stores API keys and settings in the WordPress admin.
- Ready for further integrations and customization.

## Installation

1. Upload the plugin to your `/wp-content/plugins/` directory.
2. Activate the plugin through the WordPress admin panel.
3. Go to **Settings → 4wp Sumsub Integration** to configure your API keys.

## Integration with Contact Form 7

To enable SumSub verification in your Contact Form 7 form:

1. Edit your desired CF7 form.
2. Add the following shortcode where you want the SumSub checkbox to appear:

   ```
   [sumsub-checkbox]
   ```

3. Save the form.

When a user checks this box and submits the form, SumSub verification logic will be triggered (if configured).

---

*For more details and updates, visit [4wp.dev](https://4wp.dev).*