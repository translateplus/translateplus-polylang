# TranslatePlus for Polylang

TranslatePlus for Polylang adds a TranslatePlus-powered translation workflow to WordPress posts managed with Polylang.

## Features

- Translate post title, excerpt, and content from the editor
- Create target-language drafts and auto-link them in Polylang
- Validate and save API key from a dedicated settings page
- View account status and usage summary
- AJAX-powered settings interactions for fast admin UX

## Requirements

- WordPress 6.5+ (uses core plugin dependencies for Polylang)
- PHP 7.4+
- Polylang plugin active
- TranslatePlus API key

## Setup

1. Install and activate this plugin.
2. Ensure Polylang is active.
3. Go to `Settings > TranslatePlus (Polylang)`.
4. Enter your API key and click **Save & Connect**.

## Translation Flow

1. Open a post in the editor.
2. In the TranslatePlus metabox, select a target language.
3. Click **Translate with TranslatePlus**.
4. The plugin creates a translated draft and links it with Polylang translations.

## External API Usage

This plugin calls TranslatePlus API endpoints:

- `https://api.translateplus.io/v2/account/summary`
- `https://api.translateplus.io/v2/translate`
- `https://api.translateplus.io/v2/translate/html`
- `https://api.translateplus.io/v2/translate/batch`

Data sent may include:

- API key (`X-API-KEY` request header)
- Source and target language codes
- Post title, excerpt, and content for translation actions

For policy details:

- Privacy: https://translateplus.io/privacy
- Terms: https://translateplus.io/terms
