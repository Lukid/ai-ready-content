# AI-Ready Content

WordPress plugin that generates markdown versions of posts/pages/CPT for LLM and AI agent consumption.

## Architecture

- **Target:** WordPress 6.4+ / PHP 8.0+
- **Converter:** `league/html-to-markdown` ^5.1 via Composer
- **Autoloading:** PSR-4 via Composer (`AIRC\` → `src/`)
- **Pattern:** Singleton orchestrator (`Plugin.php`) with dependency injection
- **Prefix:** `airc_` for options, transients, hooks; `AIRC_` for constants; `AIRC\` for namespace

## Coding Standards

- WordPress Coding Standards for PHP (WPCS)
- PSR-4 autoloading for plugin classes
- ABSPATH check at the top of every PHP file
- Prefix all options, transients, hooks with `airc_`
- All user-facing strings must be translatable via `__()` / `esc_html__()` with text domain `ai-ready-content`
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`
- Sanitize all input in settings callbacks
- Use WordPress APIs (Settings API, Transients API, Rewrite API)

## Key Paths

- Bootstrap: `ai-ready-content.php`
- Orchestrator: `src/Plugin.php`
- Converter pipeline: `src/Converter/`
- Routing: `src/Router/`
- Endpoints: `src/Endpoint/`
- Caching: `src/Cache/`
- Admin: `src/Admin/` + `templates/admin/`
- Integrations: `src/Integration/`

## Constants

- `AIRC_VERSION` - Plugin version
- `AIRC_PLUGIN_DIR` - Plugin directory path
- `AIRC_PLUGIN_URL` - Plugin directory URL
- `AIRC_PLUGIN_FILE` - Main plugin file path

## Filters (public API)

- `airc_prepared_html` - After HTML preparation, before conversion
- `airc_converter_options` - HTMLToMarkdown converter options
- `airc_converted_markdown` - After markdown conversion
- `airc_frontmatter_fields` - Frontmatter YAML fields array
- `airc_markdown_output` - Final markdown output (frontmatter + content)
- `airc_response_headers` - HTTP response headers
- `airc_llms_txt_output` - Final llms.txt content
- `airc_llms_txt_post_query_args` - WP_Query args for llms.txt
