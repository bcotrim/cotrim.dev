# Cotrim.dev Retro Terminal Theme

A WordPress block theme with a retro terminal/DOS aesthetic for personal blogs, featuring Catppuccin color schemes.

## Features

- **Dual Theme Support**: Dark mode (Catppuccin Mocha) and light mode (Catppuccin Latte)
- **Theme Toggle**: One-click switching between themes with localStorage persistence
- Command-line inspired design elements
- Fully responsive and mobile-friendly
- Block editor (FSE) compatible
- Catppuccin color palette integration
- Subtle scanline effects for retro CRT feel
- Professional yet playful terminal styling

## Color Palettes

### Catppuccin Mocha (Dark - Default)
- **Base**: `#1e1e2e` - Background
- **Crust**: `#11111b` - Darker backgrounds (code blocks)
- **Green**: `#a6e3a1` - Primary text
- **Peach**: `#fab387` - Accents and highlights
- **Surface 2**: `#585b70` - Borders and muted elements
- **Text**: `#cdd6f4` - High contrast text
- **Sky**: `#89dceb` - Links
- **Red**: `#f38ba8` - Errors

### Catppuccin Latte (Light)
- **Base**: `#eff1f5` - Background
- **Surface 0**: `#ccd0da` - Darker backgrounds
- **Green**: `#40a02b` - Primary text
- **Peach**: `#fe640b` - Accents
- **Text**: `#4c4f69` - Main text
- **Subtext 1**: `#5c5f77` - Secondary text
- **Blue**: `#1e66f5` - Links
- **Red**: `#d20f39` - Errors

## Typography

- **Font Family**: JetBrains Mono, Fira Code, Consolas, Monaco, Courier New, monospace
- All text uses monospace fonts for authentic terminal feel

## Templates

- `front-page.html` - Single-page home layout with about, blog, and projects sections
- `home.html` - Blog archive page
- `single.html` - Individual blog post
- `archive.html` - Category/tag archives

## Template Parts

- `header.html` - Site header with terminal prompt and navigation
- `footer.html` - Site footer with social links and system info

## Installation

1. Ensure you have **Twenty Twenty-Five** theme installed (this is a child theme)
2. Upload the `cotrimdev-retro` folder to `wp-content/themes/`
3. Activate the theme from WordPress admin
4. The theme toggle button will appear automatically in the navigation menu

## Customization

The theme can be customized through:
- `theme.json` - Global styles and WordPress block settings
- `assets/css/terminal.css` - Custom CSS effects and color variables
- `assets/js/theme-toggle.js` - Theme switching functionality
- Site Editor in WordPress admin for content and layout

## Credits

- Built as a child theme of **Twenty Twenty-Five**
- Color scheme: [Catppuccin](https://catppuccin.com/) (Mocha and Latte flavors)
- Designed for [cotrim.dev](https://cotrim.dev) personal blog
- Created by Bernardo Cotrim

## Requirements

- WordPress 6.4 or higher
- Twenty Twenty-Five parent theme
- PHP 7.4 or higher

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile responsive design
- Graceful degradation for older browsers

## License

GPL v2 or later

---

Made with ❤️ using Catppuccin colors
