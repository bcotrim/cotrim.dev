# cotrim.dev

A dark, terminal-native WordPress block theme for [cotrim.dev](https://cotrim.dev). Child of **Twenty Twenty-Five**.

Built on the Cotrim design system: terminal voice (`~ $` prompts, mono labels, numbered posts) on calm, refined surfaces, with a single phosphor-green accent.

## Design

- **Dark only.** `#0D0D0D` neutral charcoal background, no theme toggle.
- **Type.** Geist (sans) + JetBrains Mono (mono), loaded from Google Fonts.
- **Palette.** Ink, mute, dim, faint on neutrals; green/blue/amber/pink as signal colors.
- **No drop shadows.** Hairlines (`--line`, `--line-bold`) do the separation; a phosphor glow marks hover and focus.
- **Live bits.** Uptime counter in the footer, pulse dots, blinking caret, scroll-progress bar on posts.

## Pages

- **Home** — `~$ whoami` hero, recent posts, projects preview
- **Blog** — year-grouped post list with tag filter (`essay`, `note`, `link`)
- **Post** — reading view with back link, meta strip, tags, prev/next
- **Projects** — card list with HTML-attribute-style metadata (custom page template)
- **Now** — 2×3 grid of "what I'm up to" cards + uptime block (custom page template)
- **404** — giant `404` + `cd` shortcuts back to safety

## Installation

1. Make sure **Twenty Twenty-Five** is installed (this is a child theme).
2. Upload to `wp-content/themes/cotrim.dev/`.
3. Activate under Appearance → Themes.
4. Create two Pages, assign their templates:
   - `/projects` → Template: **Projects**
   - `/now` → Template: **Now**
5. Tag posts with `essay`, `note`, or `link` so the row colors and the blog filter pills work.

## How it's built

Five moving parts — see [`CLAUDE.md`](./CLAUDE.md) for the full architecture notes:

- `theme.json` — palette, font families, spacing scale, custom template registrations.
- `assets/css/terminal.css` — all visual rules, scoped under `.cotrimdev-retro-theme`.
- `assets/js/console.js` — uptime tick, active-nav, blog filter, reading progress.
- `functions.php` — shortcodes (`[con_postlist]`, `[con_archive_list]`, `[con_post_*]`) that render the console-specific row layouts the Query Loop block can't express.
- `templates/` + `parts/` — block-theme templates and shell header/footer.

## Customising

- **Uptime since.** Change `data-since="2024-06-01T00:00:00Z"` in `parts/footer.html`, and on the Now page.
- **Copy.** The Projects, Now, and 404 pages have copy hard-coded in their templates; posts and the home lede are authored in WP.
- **Colors.** All palette values live in `theme.json` (`settings.color.palette`) and `assets/css/terminal.css` (`:root` custom properties). Keep both in sync.

## Requirements

- WordPress 6.4+
- Twenty Twenty-Five parent theme
- PHP 7.4+

## License

GPL v2 or later.

---

Built by [Bernardo Cotrim](https://cotrim.dev).
