# WP Postal Code Gate

A simple WordPress plugin that checks a visitor's postal code before allowing access to a WooCommerce shop.

## Description

WP Postal Code Gate adds a popup that prompts visitors to enter their postal code before they can browse your WooCommerce store. The plugin verifies if the postal code is within your defined delivery zones, and only allows access if delivery is available to that location.

This helps prevent customer frustration by ensuring visitors know immediately whether you can deliver to their location.

## Features

- Clean, responsive popup design
- Integrates with WooCommerce shipping zones
- Validates postal codes against your shipping rules
- Saves validation in a cookie to avoid repeated prompts
- Fully translatable

## Installation

1. Upload the `wp-postal-code-gate` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Make sure your WooCommerce shipping zones are properly configured with postal code restrictions

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher
- WooCommerce 3.0 or higher

## Configuration

The plugin works directly with your existing WooCommerce shipping zones. To set up delivery areas:

1. Go to WooCommerce → Settings → Shipping → Shipping Zones
2. Add or edit a shipping zone
3. Define the zone's regions by adding postal codes
4. The plugin will automatically use these defined zones to validate customer postal codes

## Screenshots

1. Postal code validation popup
2. Error message for non-deliverable areas

## Frequently Asked Questions

### How do I modify the appearance of the popup?

You can customize the appearance by adding custom CSS to your theme or using a custom CSS plugin.

### How long does the validation last?

By default, once a postal code is validated, the plugin sets a cookie that lasts for 1 hour. This means the user won't be prompted again during that time.

### Can I restrict the popup to only appear on certain pages?

Currently, the popup appears across the entire site for new visitors. You can modify the code to restrict it to specific pages by editing the `enqueue_scripts` and `output_popup_html` methods in the plugin.

## Changelog

### 1.0.0
* Initial release

## Author

Developed by [Steven Nkeneng](https://stevennkeneng.com)

## License

This plugin is licensed under the GPL v2 or later.
