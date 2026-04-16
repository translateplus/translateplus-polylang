=== TranslatePlus for Polylang ===
Contributors: translateplus
Tags: translation, polylang, multilingual, ai translation, translation api
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Translate WordPress posts with TranslatePlus API and create Polylang-linked draft translations in one click.

== Description ==

TranslatePlus for Polylang adds a TranslatePlus-powered translation workflow to the post editor.

After connecting your API key in Settings, editors can pick a target language in the TranslatePlus metabox and create a translated draft that is automatically linked by Polylang.

Features:

* One-click translation draft creation from the post editor
* Uses Polylang language mapping and translation linking
* Translates post title, excerpt, and content via TranslatePlus API
* Settings page with connection status and account metrics
* AJAX-based API key validation and save flow

Notes:

* This plugin requires Polylang to be active.
* This plugin requires a TranslatePlus account and API key.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it via the WordPress Plugins screen.
2. Activate **TranslatePlus for Polylang**.
3. Ensure **Polylang** is installed and active.
4. Go to **Settings > TranslatePlus (Polylang)**.
5. Enter your TranslatePlus API key and click **Save & Connect**.

== Frequently Asked Questions ==

= Do I need Polylang? =

Yes. This addon depends on Polylang APIs to manage language relationships and translated post linking.

= Where do I get an API key? =

Create an account at `https://app.translateplus.io` and copy your API key from the dashboard.

= What happens when I click “Translate with TranslatePlus”? =

The plugin sends source content to TranslatePlus, creates a draft translation in the selected target language, and links it to the source post via Polylang.

== Screenshots ==

1. Settings page with API key connection and account summary
2. Post editor metabox with target language selector and translate button
3. Sidebar signup promotion card and account status metrics

== Changelog ==

= 0.1.0 =

* Initial public release
* Settings page with API key connect/disconnect and account summary
* TranslatePlus metabox workflow for Polylang posts
* Language normalization layer for API-compatible language codes
* Improved API validation feedback and HTTP error handling

== Upgrade Notice ==

= 0.1.0 =

Initial release.

== External Services ==

This plugin connects to TranslatePlus APIs in order to validate API keys and translate content.

It sends requests to:

* `https://api.translateplus.io/v2/account/summary`
* `https://api.translateplus.io/v2/translate`
* `https://api.translateplus.io/v2/translate/html`
* `https://api.translateplus.io/v2/translate/batch`

Data sent may include:

* The API key you provide (in request header `X-API-KEY`)
* Post title, excerpt, and content being translated
* Source and target language codes

Data is sent only when you use plugin features (for example, saving API key, refreshing account status, or translating content).

Service provider:

* TranslatePlus (`https://translateplus.io`)

Service policy pages:

* Privacy Policy: `https://translateplus.io/privacy-policy`
* Terms: `https://translateplus.io/terms-of-service`
