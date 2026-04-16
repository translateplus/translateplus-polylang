=== TranslatePlus for Polylang ===
Contributors: translateplus
Tags: translation, polylang, multilingual, ai translation, translation api
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Translate WordPress posts and related content with the TranslatePlus API, with Polylang-linked draft translations in one click.

== Description ==

TranslatePlus for Polylang adds a TranslatePlus-powered translation workflow to the post editor and optional bulk tools in Settings.

After connecting your API key in Settings, editors can pick a target language in the TranslatePlus metabox and create a translated draft that is automatically linked by Polylang.

= What this plugin translates =

**From the post editor (“Translate with TranslatePlus”)** — creates a new **draft** in the target language and links it in Polylang. The following are sent to TranslatePlus and written into that draft where applicable:

* **Post title** and **excerpt** (plain text, batch API)
* **Post content** (HTML via the HTML translation endpoint; block markup and typical HTML in the editor are preserved as far as the API allows)
* **Custom fields / post meta** — values are copied from the source post and translated recursively (strings and serialized PHP structures). Internal, technical, and Polylang keys are skipped (for example `_thumbnail_id`, locks, `_pll*`, many WooCommerce SKU/stock keys, and others). You can narrow this further with the `tppl_excluded_post_meta_keys` filter.
* **URL slug** — `post_name` is generated from the **translated title** and made unique (`wp_unique_post_slug`).
* **Featured image** — the file is copied to a new media library item; the attachment’s title, caption, description, and alt text are translated.

**Taxonomies** — term IDs from the source post are attached to the new draft (same taxonomy structure). Term *names* are not automatically translated.

**Settings → Polylang strings (optional)** — bulk-updates **Polylang string translations** stored for a chosen language (the same strings Polylang uses for many widgets and registered strings). This does **not** walk every navigation menu item or every arbitrary site option.

**Not** translated automatically (out of scope for this plugin):

* Navigation menus as a whole, arbitrary widgets/options, or SEO plugin fields unless they live in post content or in Polylang’s string storage as above.
* Polylang “Strings” that are never registered in Polylang’s string storage.

Features:

* One-click translation draft creation from the post editor (public post types)
* Uses Polylang language mapping and translation linking
* Extended draft cloning: translated meta, slug, and duplicated featured image (can be disabled with the `tppl_translate_post_extended_enabled` filter)
* Optional bulk Polylang string translation from Settings
* Settings page with connection status and account metrics
* AJAX-based API key validation, save, and disconnect flows

Notes:

* WordPress 6.5+ enforces the **Polylang** dependency (same as core “Requires Plugins”): activation is blocked until Polylang is installed and active.
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

The plugin sends the source post’s translatable fields (title, excerpt, content, meta, etc.) to TranslatePlus, creates a **draft** in the selected target language, applies a translated slug and duplicated featured image when applicable, and links the new post to the source via Polylang’s translation group.

= What does the plugin translate vs. not translate? =

See **What this plugin translates** in the Description above. In short: post fields, HTML body, most post meta (with exclusions), slug, featured image media fields, and optional Polylang string packs. It does not replace a full site localization or menu builder workflow.

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
* Post meta and other text fields included in translation or bulk string operations
* Source and target language codes

Data is sent only when you use plugin features (for example, saving API key, refreshing account status, or translating content).

Service provider:

* TranslatePlus (`https://translateplus.io`)

Service policy pages:

* Privacy Policy: `https://translateplus.io/privacy-policy`
* Terms: `https://translateplus.io/terms-of-service`
