# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress block theme (`cotrimdev-console`, child of **Twenty Twenty-Five**) for [cotrim.dev](https://cotrim.dev). The visual direction is **"Console"** — a dark terminal-native surface (`#0A0C0F` bg, Manrope + JetBrains Mono), hairlines instead of shadows, signal-green accents for live elements. Dark-only, no theme toggle.

No build tooling: copy/symlink into `wp-content/themes/` and activate.

## Architecture

Visual + content rendering is split across four systems — understand their division of labor before editing:

1. **`theme.json`** — FSE tokens. Palette slugs (`bg`, `surface`, `ink`, `mute`, `dim`, `faint`, `line`, `line-bold`, `green`, `blue`, `amber`, `pink`), Manrope + JetBrains Mono font families (loaded via `wp_enqueue_style` in PHP, also registered via `fontFace`), 4px-based spacing scale, and block-level defaults. Colors flow out as `var(--wp--preset--color--*)` custom properties. Registers `customTemplates` for `page-projects` and `page-now`.

2. **`assets/css/terminal.css`** — all visual system rules. Scoped under `.cotrimdev-retro-theme` (body class added by `cotrimdev_body_class()` in `functions.php`) so nothing leaks into wp-admin. Defines its own CSS custom properties (`--bg`, `--ink`, `--green`, `--mono`, `--sans`…) that parallel the theme.json palette — use these inside CSS, use `var(--wp--preset--color--*)` inside block markup. Contains all the chrome/layout classes: `.con-shell`, `.con-status`, `.con-prompt`, `.con-postlist`, `.con-project-card`, `.con-now-grid`, `.con-404-*`, `.con-title` (sweep underline), plus `@keyframes con-pulse` and `con-blink`. The filename is historical — do not rename without also bumping the enqueue handle in `functions.php`.

3. **`assets/js/console.js`** — vanilla IIFE, no framework. Five behaviors: live `uptime` tick (any `[data-con-uptime]` node, driven by `data-since`), current-path injection into the top chrome (`[data-con-path]`), active-nav underline via `is-current` class, blog tag filter (click-toggle `.con-chip[data-filter]` → hide `[data-tag]` rows → hide empty `[data-year-group]`), and reading-progress bar (`[data-con-progress]`). Pulse and caret are CSS-only.

4. **`functions.php`** — enqueue order (parent style → child style → terminal.css → console.js + Google Fonts) **and** the shortcode layer. The block templates defer post-list rendering to shortcodes instead of Query Loop blocks, because the Console row (post number, reading time, year grouping, primary tag) cannot be expressed in stock Post Template children. Shortcodes live here:
    - `[con_postlist count="N"]` — flat recent posts (home)
    - `[con_archive_header]`, `[con_archive_list]` — blog archive with year groups
    - `[con_post_breadcrumbs]`, `[con_post_meta]`, `[con_post_dek]`, `[con_post_tags]`, `[con_post_prevnext]` — single-post chrome
  
   The shared helpers `cotrimdev_render_row()`, `cotrimdev_post_number()`, `cotrimdev_reading_time()`, `cotrimdev_primary_tag()` feed all of these. Post numbers are assigned by ascending publish date (oldest = 1); they renumber if posts are reordered.

### Templates & parts

- `templates/front-page.html` — home (whoami + recent posts shortcode + projects preview + status block)
- `templates/home.html` — blog archive page (prompt + H1 shortcode + filter pills + year-grouped list shortcode)
- `templates/archive.html` — category/tag archives (same list shortcode)
- `templates/single.html` — post reading view (shortcodes for breadcrumbs, meta, dek, tags, prev/next; `[data-con-progress]` bar sits between header and article)
- `templates/page-projects.html`, `templates/page-now.html` — custom page templates, registered via `customTemplates` in theme.json
- `templates/404.html` — giant `404` numeral + `cd` chips
- `parts/header.html` — traffic lights (decorative) + `bernardo@cotrim.dev — [path]` label + WP navigation block
- `parts/footer.html` — `● online` + live uptime + `v2.0 · 2025.12`

### Post taxonomy expectations

Posts must be tagged with one of **`essay`**, **`note`**, or **`link`** — the row colors and filter pills depend on those exact slugs. `cotrimdev_primary_tag()` picks the first matching one, falling back to `essay`.

## Conventions

- **Don't reintroduce Query Loop blocks for the post list.** The current row grid (number, reading time, tag color, year-grouped) is not expressible without post-processing. Extend the shortcodes instead, or wrap in a block binding if a future need warrants it.
- **Colors**: reference palette slugs through `var(--wp--preset--color--<slug>)` in block markup, and the matching `var(--<slug>)` in `terminal.css`. Do not hardcode hex.
- **No shadows anywhere** — the design uses hairlines (`--line`, `--line-bold`) and background contrast. Don't add `box-shadow`.
- **Pulse dots** must animate `opacity` only (`@keyframes con-pulse`), never `transform: scale` — it causes baseline jitter next to monospace text.
- **Fonts**: currently Google Fonts. If Bernardo asks to go local, bundle woff2 under `assets/fonts/` and keep the `fontFace` block in `theme.json` in sync (remove the `wp_enqueue_style` for Google).
- **Editor experience**: most content is in raw `wp:html` blocks or shortcodes, so the Site Editor shows placeholder text for those sections — this is intentional. The reading view (`single.html`) is still block-editable via `wp:post-content`.

## Commands

No npm, no composer, no build step:

- **Deploy locally**: symlink repo into `wp-content/themes/cotrimdev-console/` (directory name must match `style.css`'s `Text Domain` if you slug-match, otherwise any name), make sure **Twenty Twenty-Five** is installed, activate.
- **Cache-bust CSS/JS**: bump `Version:` in `style.css` — assets inherit that via `$theme->get('Version')` in `functions.php`.
- **Lint PHP**: `php -l functions.php` from the theme root.
