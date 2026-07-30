# Bloom branding

Brand color: #FFD65B
Icon Origin: [The Noun Project - Energy](https://thenounproject.com/icon/energy-5215977/)
Brand Icon: resources/branding/alchemistic.svg

Apple Icon Composer file: (none — this app has no `resources/branding/bloom.icon` bundle, so
`apple-touch-icon.png` falls back to a flat render of the brand glyph on the background color
instead of the squircle/gradient/specular composer treatment.)

## Icon source of truth

### Source assets

* Clean icon source (non-Apple): resources/branding/alchemistic.svg
* Apple composer source: resources/branding/alchemistic.png

### Non-Apple icon outputs

These files must be generated from the clean SVG source, not from the Apple touch icon:

* resources/icons/favicon.svg
* resources/icons/favicon-96x96.png
* resources/icons/favicon.ico
* resources/icons/web-app-manifest-192x192.png
* resources/icons/web-app-manifest-512x512.png
* resources/icons/logo-standard.svg
* resources/icons/logo-standard.png
* resources/icons/logo-on-white.png

### Regeneration command

Icons are regenerated automatically by the Vite plugin (`bin/icons/vite-plugin.js`) on every
`npm run dev` or `npm run build`, reading `resources/branding/icon-config.json` for the glyph
source and background color. There's no separate script to invoke manually.


### Color treatment rules

* Standard favicon/app icons: brand color background with the glyph as drawn in the source SVG (currently black).
* On-white variant: white background with the same glyph, unchanged.

## Core palette - these aren't used automatically yet

### Neutrals

* white: #FFFFFF
* gray-lightest: #F9F8FA
* gray-lighter: #E5E4E6
* gray-light: #D1D0D2
* gray: #BEBEBF
* gray-dark: #979698
* gray-darker: #6F6E70
* gray-darkest: #494849
* black: #212022

### Brand

* brand-light: #DCC8F9
* brand: #FFD65B
* brand-dark: #604a08

### CTA

* cta-light: #FDE9CA
* cta: #E1AA25
* cta-dark: #6B521B

### Info

* info-light: #DBEEFE
* info: #4FBBFA
* info-dark: #305975

### Warning

* warning-light: #FEEDD6
* warning: #ECB85B
* warning-dark: #705830

### Success

* success-light: #DBF2DB
* success: #66C971
* success-dark: #366039

### Danger

* danger-light: #FFD0D6
* danger: #E92A65
* danger-dark: #702133

## Semantic token mapping

### Light mode

* primary: #6A2AAC (brand)
* primary-foreground: #FFFFFF
* secondary: #E5E4E6 (gray-lighter)
* secondary-foreground: #494849 (gray-darkest)
* accent: #E1AA25 (cta)
* accent-foreground: #212022 (black)
* destructive: #E92A65 (danger)
* destructive-foreground: #FFFFFF

### Dark mode

* primary: #DCC8F9 (brand-light)
* primary-foreground: #2F196A (brand-dark)
* secondary: #6F6E70 (gray-darker)
* secondary-foreground: #F9F8FA (gray-lightest)
* accent: #FDE9CA (cta-light)
* accent-foreground: #6B521B (cta-dark)
* destructive: #FFD0D6 (danger-light)
* destructive-foreground: #702133 (danger-dark)

## Implementation notes

* Tailwind color tokens are defined in resources/css/app.css under the @theme block using --color-* variables.
* Semantic theme variables (for example, --primary and --accent) are mapped in :root and .dark in the same file.
