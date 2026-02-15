# AI-Ready Content

WordPress plugin that makes your content accessible to LLMs and AI agents. Every post, page, or custom post type gets a clean markdown version with structured YAML frontmatter, available at a simple `.md` URL.

- **Requires:** WordPress 6.4+ / PHP 8.0+
- **License:** GPL-2.0-or-later
- **Author:** Luca Baroncini

## What it does

When you publish a post at `https://example.com/hello-world/`, the plugin automatically makes it available as markdown at `https://example.com/hello-world.md`. The output includes YAML frontmatter with metadata (title, date, author, categories, tags) followed by the content converted to clean markdown.

The plugin also generates:

- `/llms.txt` -- a curated index of your most relevant content, following the [llms.txt specification](https://llmstxt.org/)
- `/llms-full.txt` -- a comprehensive index of all published content, organized by post type
- `/airc-sitemap.json` -- a structured JSON sitemap for programmatic discovery of all markdown endpoints

All of this works out of the box with zero configuration. The settings page lets you fine-tune behavior for advanced use cases.

## Installation

```bash
composer install
```

Then activate the plugin from the WordPress admin. On activation, the plugin registers its rewrite rules and sets default options.

## How it works

### Markdown endpoints

Every published post in an enabled post type gets a `.md` endpoint. The plugin intercepts requests ending in `.md`, resolves them to the original post, and serves a markdown version.

The conversion pipeline:

1. The post HTML is rendered through WordPress `the_content` filter (Gutenberg blocks, shortcodes, embeds all resolved)
2. Residual unresolved shortcodes are stripped
3. Images are processed according to the configured handling mode
4. Script, style, and nav elements are removed
5. The cleaned HTML is converted to markdown using `league/html-to-markdown`
6. YAML frontmatter is prepended with post metadata
7. The result is cached using WordPress transients

Example output:

```markdown
---
title: "Hello World"
date: "2026-01-15T10:30:00+00:00"
modified: "2026-02-10T14:00:00+00:00"
author: "Luca Baroncini"
excerpt: "A brief introduction to the plugin..."
url: "https://example.com/hello-world/"
post_type: "post"
categories:
  - "WordPress"
  - "AI"
tags:
  - "markdown"
  - "llm"
---

# Hello World

Your post content converted to clean markdown...
```

Response headers include `Content-Type: text/markdown; charset=utf-8`, `X-Robots-Tag: noindex`, and `Content-Security-Policy: default-src 'none'`.

### Content negotiation

Clients can also request markdown by sending an `Accept: text/markdown` header to the regular post URL. The plugin intercepts the request and serves the markdown version without needing the `.md` suffix. This can be disabled in settings.

### llms.txt

The `/llms.txt` endpoint generates a curated index following the [llmstxt.org specification](https://llmstxt.org/). Content is split into a main section with the most relevant posts and an **Optional** section with additional entries. Sticky posts are prioritized for the `post` type. When enabled, top categories and tags (by post count) are included as separate sections.

```
# My Site

> Site description

## Posts

- [Hello World](https://example.com/hello-world.md): A brief introduction...
- [Another Post](https://example.com/another-post.md): More content here...

## Pages

- [About](https://example.com/about.md): About this site...

## Categories

- [WordPress](https://example.com/category/wordpress/): 15 posts

## Optional

- [Older Post](https://example.com/older-post.md): Supplementary content...

---

See also: [Full content index](https://example.com/llms-full.txt)
```

The curated and optional limits are configurable (default 10 each, max 50). Categories/tags link to their archive pages (plain URLs, not `.md`). Posts whose permalink resolves to the site root (front page, blog page) are automatically excluded.

### llms-full.txt

The `/llms-full.txt` endpoint provides a comprehensive listing of all published content, organized by post type. Unlike `llms.txt`, it has no curated/optional split and no taxonomy sections -- it simply lists everything up to the configured limit.

```
# My Site — Full Index

> Site description

> This is the comprehensive content index. For a curated summary, see [llms.txt](https://example.com/llms.txt).

## Posts

- [Hello World](https://example.com/hello-world.md): A brief introduction...
- [Another Post](https://example.com/another-post.md): More content here...
- ...all published posts up to the limit...

## Pages

- [About](https://example.com/about.md): About this site...
```

Requires `llms.txt` to be enabled. The per-type post limit defaults to 100 (max 500).

### JSON sitemap

The `/airc-sitemap.json` endpoint returns a machine-readable JSON index of all enabled content:

```json
{
  "generated": "2026-02-15T10:00:00+00:00",
  "site": "My Site",
  "count": 42,
  "posts": [
    {
      "title": "Hello World",
      "url": "https://example.com/hello-world.md",
      "post_type": "post",
      "date_published": "2026-01-15T10:30:00+00:00",
      "date_modified": "2026-02-10T14:00:00+00:00"
    }
  ]
}
```

### robots.txt and alternate links

When enabled, the plugin:

- Adds a `Llms-txt: https://example.com/llms.txt` directive to `robots.txt`
- Outputs `<link rel="alternate" type="text/markdown" href="...">` tags in the HTML `<head>` for each post, helping crawlers discover the markdown version

Both features can be toggled independently in settings.

## API endpoints for RAG / AI agents

The plugin exposes all content through simple HTTP GET requests. No authentication, no REST API registration, no API keys. Every endpoint is a plain URL that returns cacheable content.

### Endpoint summary

| Endpoint | Content-Type | Purpose |
|----------|-------------|---------|
| `/{slug}.md` | `text/markdown` | Single post/page as YAML frontmatter + markdown |
| `/llms.txt` | `text/plain` | Curated content index (llmstxt.org spec) |
| `/llms-full.txt` | `text/plain` | Comprehensive content index |
| `/airc-sitemap.json` | `application/json` | Structured sitemap with all `.md` URLs and dates |

All endpoints return `X-Robots-Tag: noindex` and `Content-Security-Policy: default-src 'none'`.

### Typical RAG ingestion flow

**1. Discover content**

```bash
curl https://example.com/airc-sitemap.json
```

Returns a JSON array of all available `.md` URLs with `date_published` and `date_modified` timestamps. Use `date_modified` for incremental updates.

**2. Fetch individual documents**

```bash
curl https://example.com/hello-world.md
```

Returns YAML frontmatter (title, date, author, categories, tags, custom fields) followed by clean markdown content. The frontmatter provides structured metadata for chunking and filtering.

**3. Content negotiation (alternative)**

```bash
curl -H "Accept: text/markdown" https://example.com/hello-world/
```

Same response as the `.md` URL. Useful when you already have the canonical URL and want the markdown version without URL manipulation.

**4. Browse the index**

```bash
curl https://example.com/llms.txt        # curated highlights
curl https://example.com/llms-full.txt    # everything
```

Plain text indexes with markdown links. Useful for LLM context stuffing or as a starting point for selective crawling.

### Response caching

All endpoints are cached via WordPress transients (default TTL: 24h). Cache is automatically invalidated when content changes. The `Cache-Control: public, max-age=3600` header is set on `.md` and sitemap responses. For bulk ingestion, use the WP-CLI `wp airc generate` command to pre-warm the cache.

## Settings

The settings page is at **Settings > AI-Ready Content** in the WordPress admin.

### Post types

Choose which post types get markdown endpoints. All public post types (except attachments) are available. Default: posts and pages.

### Features

| Setting | Default | Description |
|---------|---------|-------------|
| Content negotiation | On | Serve markdown when client sends `Accept: text/markdown` |
| llms.txt | On | Enable the `/llms.txt` endpoint |
| llms-full.txt | On | Enable the `/llms-full.txt` endpoint (requires llms.txt) |
| Alternate links | On | Output `<link rel="alternate">` in HTML head |
| robots.txt | On | Add `Llms-txt` directive to robots.txt |
| Show teaser for protected posts | Off | Return metadata for password-protected posts instead of 404 |

### llms.txt / llms-full.txt

| Setting | Default | Description |
|---------|---------|-------------|
| Curated limit | 10 | Posts per type in the main llms.txt sections (1-50) |
| Optional limit | 10 | Posts per type in the Optional section (1-50) |
| Show taxonomies | On | Include top categories/tags in llms.txt |
| Full index post limit | 100 | Posts per type in llms-full.txt (1-500) |

### Cache

| Setting | Default | Description |
|---------|---------|-------------|
| Cache TTL | 24 hours | How long to cache generated markdown (0 to disable) |

The cache uses WordPress transients and is automatically invalidated when a post is saved, deleted, changes status, or has its categories/tags modified.

The "Clear All Cache" button on the settings page flushes everything. It has a 10-second cooldown to prevent abuse.

### Image handling

Three modes for how images appear in the markdown output:

- **Keep with alt text** (default) -- Images are preserved as standard markdown: `![alt text](url)`
- **Alt text only** -- Images are replaced with their alt text in parentheses: `(alt text)`. Images without alt text are removed entirely
- **Remove** -- All images are stripped from the output

In all modes, `<figcaption>` text is preserved as plain text.

### Custom frontmatter fields

A textarea where you enter meta key names (one per line). If a post has a non-empty value for that meta key, it will be included in the YAML frontmatter. Useful for ACF fields, custom meta, or any `post_meta` you want exposed to AI consumers.

### Password-protected posts

When "Show teaser for protected posts" is enabled, password-protected posts return a 200 response with basic frontmatter (title, date, post type, `protected: true`) and the body text "Contenuto protetto da password" instead of a 404. This lets AI agents know the content exists without revealing it.

## Editor preview

### Classic editor

A meta box appears on the post edit screen for enabled post types. It shows:

- Direct link to the `.md` endpoint
- Cache status (cached or not)
- A button to clear the cache for that specific post
- A button to load the markdown preview in a read-only textarea

### Block editor (Gutenberg)

A panel appears in the document sidebar with the same functionality: markdown URL, cache status, and cache invalidation button.

## WP-CLI commands

For sites with many posts, the plugin provides CLI commands.

### Flush cache

```bash
# Flush all markdown cache
wp airc flush

# Flush cache for a specific post
wp airc flush --post_id=42

# Flush cache for all pages
wp airc flush --post_type=page
```

### Generate markdown

Pre-generate and cache markdown for all published posts in enabled types.

```bash
# Generate for all enabled post types
wp airc generate

# Generate only for posts
wp airc generate --post_type=post

# Force regeneration (ignore existing cache)
wp airc generate --force
```

### Status

```bash
wp airc status
```

Displays: enabled post types, total published posts, cache entry count, total cache size, and cache TTL setting.

## Filters for developers

The plugin provides filters at every stage of the pipeline. All filters follow the `airc_` prefix convention.

### Content preparation

**`airc_strip_residual_shortcodes`** `(bool $strip)`

Enable or disable the removal of unresolved shortcode tags from the HTML before conversion. Default: `true`.

```php
// Keep residual shortcodes in the output
add_filter( 'airc_strip_residual_shortcodes', '__return_false' );
```

**`airc_prepared_html`** `(string $html, WP_Post $post)`

Modify the HTML after preparation and before markdown conversion. This is where the HTML has been cleaned, images processed, and shortcodes stripped.

```php
add_filter( 'airc_prepared_html', function ( $html, $post ) {
    // Remove a specific div before conversion
    return preg_replace( '/<div class="ad-block">.*?<\/div>/s', '', $html );
}, 10, 2 );
```

### Conversion

**`airc_converter_options`** `(array $options)`

Customize the options passed to the HTML-to-Markdown converter.

```php
add_filter( 'airc_converter_options', function ( $options ) {
    $options['header_style'] = 'setext'; // Use === and --- for h1/h2
    $options['hard_break']   = true;     // Convert <br> to line breaks
    return $options;
} );
```

Default options:

```php
[
    'header_style'    => 'atx',
    'strip_tags'      => true,
    'remove_nodes'    => 'script style nav footer',
    'hard_break'      => false,
    'list_item_style' => '-',
]
```

**`airc_converted_markdown`** `(string $markdown)`

Modify the markdown after conversion, before frontmatter is added.

### Frontmatter

**`airc_frontmatter_fields`** `(array $fields, WP_Post $post)`

Add, remove, or modify the YAML frontmatter fields. The `$fields` array is associative: keys become YAML field names, values become field values.

```php
add_filter( 'airc_frontmatter_fields', function ( $fields, $post ) {
    $fields['reading_time'] = ceil( str_word_count( $post->post_content ) / 200 );
    $fields['language']     = 'it';
    unset( $fields['author'] ); // Remove author from frontmatter
    return $fields;
}, 10, 2 );
```

### Output

**`airc_markdown_output`** `(string $output, WP_Post $post)`

Final filter on the complete markdown output (frontmatter + content), right before caching and serving.

**`airc_response_headers`** `(array $headers, WP_Post $post)`

Modify the HTTP headers sent with the markdown response. Header names must contain only alphanumeric characters and hyphens. Values containing `\r` or `\n` are silently discarded for security.

```php
add_filter( 'airc_response_headers', function ( $headers, $post ) {
    $headers['X-Custom-Header'] = 'value';
    return $headers;
}, 10, 2 );
```

### llms.txt / llms-full.txt

**`airc_llms_txt_output`** `(string $output)`

Filter the complete llms.txt content before serving.

**`airc_llms_txt_post_query_args`** `(array $args, string $post_type)`

Customize the `WP_Query` arguments used to fetch posts for each post type section in llms.txt.

```php
add_filter( 'airc_llms_txt_post_query_args', function ( $args, $post_type ) {
    if ( $post_type === 'post' ) {
        $args['category_name'] = 'featured'; // Only include featured posts
    }
    return $args;
}, 10, 2 );
```

**`airc_llms_full_txt_output`** `(string $output)`

Filter the complete llms-full.txt content before serving.

**`airc_llms_full_txt_post_query_args`** `(array $args, string $post_type)`

Same as above, for the llms-full.txt endpoint.

## Security

- All admin AJAX actions are protected by nonce verification and capability checks (`manage_options`)
- Response headers from `airc_response_headers` are validated: names must be alphanumeric with hyphens, values must not contain CRLF characters
- `$_SERVER` superglobals are sanitized with `sanitize_text_field()` and `wp_unslash()` / `esc_url_raw()`
- All markdown and llms.txt endpoints include `Content-Security-Policy: default-src 'none'` to prevent XSS
- Cache flush has a 10-second rate limit cooldown
- All output is escaped following WordPress coding standards

## Uninstall

When the plugin is deleted from WordPress admin, it removes all its data:

- The `airc_settings` option
- All `airc_*` transients from the database

No data is left behind.

## Architecture

```
ai-ready-content.php          # Bootstrap, constants, activation/deactivation hooks
src/
  Plugin.php                   # Singleton orchestrator, wires all components
  Admin/
    SettingsPage.php           # Settings page, AJAX cache flush
    PreviewMetaBox.php         # Classic editor meta box, Gutenberg panel, AJAX preview
  Cache/
    TransientCache.php         # WordPress transients cache with auto-invalidation
  CLI/
    Commands.php               # WP-CLI commands (flush, generate, status)
  Converter/
    ContentPreparer.php        # HTML cleanup, image handling, shortcode stripping
    FrontmatterGenerator.php   # YAML frontmatter from post metadata
    MarkdownConverter.php      # HTML to markdown via league/html-to-markdown
  Endpoint/
    PostEndpoint.php           # Serves individual .md requests
    LlmsTxtEndpoint.php        # Serves /llms.txt
    LlmsFullTxtEndpoint.php    # Serves /llms-full.txt
    SitemapEndpoint.php        # Serves /airc-sitemap.json
  Helpers/
    PostTypeHelper.php         # Post type eligibility checks
  Integration/
    AlternateLink.php          # <link rel="alternate"> in HTML head
    RobotsTxt.php              # Llms-txt directive in robots.txt
  Router/
    ContentNegotiator.php      # Accept: text/markdown header handling
    RewriteHandler.php         # URL rewriting and .md interception
assets/js/
  gutenberg-panel.js           # Block editor sidebar panel
  metabox.js                   # Classic editor meta box JavaScript
templates/admin/
  settings-page.php            # Settings page HTML template
```

## Dependencies

- [league/html-to-markdown](https://github.com/thephpleague/html-to-markdown) ^5.1

Installed via Composer. PSR-4 autoloading maps the `AIRC\` namespace to `src/`.
