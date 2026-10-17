# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress block theme (`cotrimdev-console`, child of **Twenty Twenty-Five**) for [cotrim.dev](https://cotrim.dev). The visual direction follows the **Cotrim design system** (the `cotrim-design` skill): a neutral charcoal surface (`#0D0D0D` bg, Geist + JetBrains Mono), hairline borders, a single phosphor-green accent (`#3FE07B`) used sparingly. Terminal voice (`~ $` prompts, mono labels, numbered posts) without fake-terminal chrome. Dark-only, no theme toggle.

No build tooling: copy/symlink into `wp-content/themes/` and activate.

## Architecture

Visual + content rendering is split across four systems — understand their division of labor before editing:

1. **`theme.json`** — FSE tokens. Palette slugs (`bg`, `surface`, `ink`, `mute`, `dim`, `faint`, `line`, `line-bold`, `green`, `blue`, `amber`, `pink` — `blue` holds cyan, `pink` holds violet; slugs kept for back-compat), Geist + JetBrains Mono font families (loaded via `wp_enqueue_style` in PHP, also registered via `fontFace`), 4px-based spacing scale, and block-level defaults. Colors flow out as `var(--wp--preset--color--*)` custom properties. Registers `customTemplates` for `page-projects` and `page-now`.

2. **`assets/css/terminal.css`** — all visual system rules. Scoped under `.cotrimdev-retro-theme` (body class added by `cotrimdev_body_class()` in `functions.php`) so nothing leaks into wp-admin. Defines CSS custom properties that alias the theme.json presets (`--bg: var(--wp--preset--color--bg)` …) plus derived tokens (`--green-wash`, `--glow`, radii, motion) — use these inside CSS, use `var(--wp--preset--color--*)` inside block markup. Contains all the chrome/layout classes: `.con-shell`, `.con-status`, `.con-prompt`, `.con-eyebrow`, `.con-postlist`, `.con-badge`, `.con-card`, `.con-kv`, `.con-project-card`, `.con-now-grid`, `.con-404-*`, plus `@keyframes con-pulse` and `con-blink`. The filename is historical — do not rename without also bumping the enqueue handle in `functions.php`.

3. **`assets/js/console.js`** — vanilla IIFE, no framework. Four behaviors: live `uptime` tick (any `[data-con-uptime]` node, driven by `data-since`), active-nav marker via `is-current` class (single posts mark `blog`), blog tag filter (click-toggle `.con-chip[data-filter]` → hide `[data-tag]` rows → hide empty `[data-year-group]`), and reading-progress bar (`[data-con-progress]`). Pulse and caret are CSS-only.

4. **`functions.php`** — enqueue order (parent style → child style → terminal.css → console.js + Google Fonts) **and** the shortcode layer. The block templates defer post-list rendering to shortcodes instead of Query Loop blocks, because the Console row (post number, excerpt, year grouping, primary tag badge) cannot be expressed in stock Post Template children. Shortcodes live here:
    - `[con_postlist count="N"]` — flat recent posts (home)
    - `[con_archive_header]`, `[con_archive_list]` — blog archive with year groups
    - `[con_post_breadcrumbs]` (renders the `~ $ cd ..` back link), `[con_post_meta]`, `[con_post_dek]`, `[con_post_tags]`, `[con_post_prevnext]` — single-post chrome
  
   The shared helpers `cotrimdev_render_row()`, `cotrimdev_post_number()`, `cotrimdev_reading_time()`, `cotrimdev_primary_tag()` feed all of these. Post numbers are assigned by ascending publish date (oldest = 1); they renumber if posts are reordered.

### Templates & parts

- `templates/front-page.html` — home (whoami + ASCII portrait + recent posts shortcode + project cards)
- `templates/home.html` — blog archive page (prompt + H1 shortcode + filter pills + year-grouped list shortcode)
- `templates/archive.html` — category/tag archives (same list shortcode)
- `templates/single.html` — post reading view (shortcodes for back link, meta, dek, tags, prev/next; `[data-con-progress]` bar sits between header and article)
- `templates/page-projects.html`, `templates/page-now.html` — custom page templates, registered via `customTemplates` in theme.json
- `templates/404.html` — giant `404` numeral + `cd` chips
- `parts/header.html` — sticky bar: `~ $ cotrim.dev` mark with blinking caret + WP navigation block (no overlay menu)
- `parts/footer.html` — `● online` + live uptime + `v3.0 · 2026.10`

### Post taxonomy expectations

Posts must be tagged with one of **`essay`**, **`note`**, or **`link`** — the row colors and filter pills depend on those exact slugs. `cotrimdev_primary_tag()` picks the first matching one, falling back to `essay`.

## Conventions

- **Don't reintroduce Query Loop blocks for the post list.** The current row grid (number, reading time, tag color, year-grouped) is not expressible without post-processing. Extend the shortcodes instead, or wrap in a block binding if a future need warrants it.
- **Colors**: reference palette slugs through `var(--wp--preset--color--<slug>)` in block markup, and the matching `var(--<slug>)` in `terminal.css`. Do not hardcode hex.
- **No drop shadows** — separation comes from hairlines (`--line`, `--line-bold`) and background contrast. The only `box-shadow`s allowed are the phosphor `--glow` (interactive card hover) and the `--focus` ring.
- **Pulse dots** must animate `opacity` only (`@keyframes con-pulse`), never `transform: scale` — it causes baseline jitter next to monospace text.
- **Fonts**: currently Google Fonts. If Bernardo asks to go local, bundle woff2 under `assets/fonts/` and keep the `fontFace` block in `theme.json` in sync (remove the `wp_enqueue_style` for Google).
- **Editor experience**: most content is in raw `wp:html` blocks or shortcodes, so the Site Editor shows placeholder text for those sections — this is intentional. The reading view (`single.html`) is still block-editable via `wp:post-content`.

## Commands

No npm, no composer, no build step:

- **Deploy locally**: symlink repo into `wp-content/themes/cotrimdev-console/` (directory name must match `style.css`'s `Text Domain` if you slug-match, otherwise any name), make sure **Twenty Twenty-Five** is installed, activate.
- **Cache-bust CSS/JS**: bump `Version:` in `style.css` — assets inherit that via `$theme->get('Version')` in `functions.php`.
- **Lint PHP**: `php -l functions.php` from the theme root.
