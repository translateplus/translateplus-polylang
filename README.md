# TranslatePlus for Polylang

TranslatePlus for Polylang adds a TranslatePlus-powered translation workflow to WordPress content managed with Polylang.

## What this plugin translates

### Post editor: “Translate with TranslatePlus”

Creates a new **draft** in the target language and links it in Polylang. Content sent to TranslatePlus includes:

- **Post title** and **excerpt** (plain text)
- **Post content** as HTML (blocks / typical editor HTML, via the HTML translation API)
- **Custom fields / post meta** — copied from the source post and translated recursively where values are text or serialized structures; technical keys are excluded (thumbnail, locks, `_pll*`, many WooCommerce ID/price fields, etc.). Extend exclusions with the `tppl_excluded_post_meta_keys` filter.
- **URL slug** — derived from the translated title and made unique
- **Featured image** — file duplicated in the media library; attachment title, caption, description, and alt text translated

**Taxonomies:** term IDs from the source post are attached to the draft (structure preserved). Term names are not auto-translated.

Disable the meta/slug/featured-image pass with the `tppl_translate_post_extended_enabled` filter (return `false`) if you only want title, excerpt, and content.

### Settings: Polylang strings (bulk)

Optionally bulk-translate **Polylang string translations** for a selected language (many widget strings and other strings Polylang stores). This is not a full site or menu translation tool.

### Not covered

Whole navigation menus, arbitrary widgets/options, or SEO fields unless they appear in post HTML or in Polylang’s string storage as above.

## Features

- One-click draft creation from the editor with Polylang linking
- Extended cloning: translated meta, slug, duplicated featured image (optional via filter)
- Bulk Polylang string updates from Settings
- API key connect/disconnect and account summary
- AJAX-powered settings UX

## Requirements

- WordPress 6.5+ (uses core plugin dependencies for Polylang)
- PHP 7.4+
- Polylang plugin active
- TranslatePlus API key

## Setup

1. Install and activate this plugin.
2. Ensure Polylang is installed and active.
3. Go to `Settings > TranslatePlus (Polylang)`.
4. Enter your API key and click **Save & Connect**.

## Translation flow

1. Open a post or page in the editor.
2. In the TranslatePlus metabox, select a target language.
3. Click **Translate with TranslatePlus**.
4. The plugin creates a translated draft, applies extended fields when enabled, and links it via Polylang.

## External API usage

This plugin calls TranslatePlus API endpoints:

- `https://api.translateplus.io/v2/account/summary`
- `https://api.translateplus.io/v2/translate`
- `https://api.translateplus.io/v2/translate/html`
- `https://api.translateplus.io/v2/translate/batch`

Data sent may include:

- API key (`X-API-KEY` request header)
- Source and target language codes
- Post title, excerpt, content, post meta text, attachment text fields, and Polylang string batches when you use those features

For policy details:

- Privacy: https://translateplus.io/privacy-policy
- Terms: https://translateplus.io/terms-of-service
