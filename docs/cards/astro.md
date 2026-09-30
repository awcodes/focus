---
title: Building templates with Astro
description: Build a repository of card templates with Astro and Tailwind, preview them with sample content, and publish the build for Focus.
---

# Building templates with Astro

Any tool that produces static HTML can build card templates. This page uses [Astro](https://astro.build) with Tailwind: pages share a layout, the dev server previews templates with sample content, and the build is a directory Focus reads directly.

Keep templates in their own repository when several packages share them. Each package then points `cardTemplates()` at that repository on GitHub (see [Template sources](sources.md)).

[awcodes/focus-templates](https://github.com/awcodes/focus-templates) is a complete example built this way: a shared layout, templates at two canvases (2560×1440 for 16:9 and 2400×1260 for Open Graph and GitHub), bundled fonts, and a committed `dist/`. It holds the awcodes brand, so use it as a reference rather than as your own templates.

## Set up

```bash
npm create astro@latest card-templates -- --template minimal
cd card-templates
npm install tailwindcss @tailwindcss/vite @fontsource/fira-sans
```

```js
// astro.config.mjs
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'astro/config';

export default defineConfig({
    vite: {
        plugins: [tailwindcss()],
    },
});
```

Astro's default output puts each page at `{name}/index.html`, which Focus finds by name: `src/pages/two-up.astro` becomes the `two-up` template.

## A layout with a fixed canvas

Put the canvas meta tag and the shared styles in a layout:

```astro
---
// src/layouts/Card.astro
import '../styles/card.css';

interface Props {
    width?: number;
    height?: number;
}

const { width = 2400, height = 1260 } = Astro.props;
---

<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="focus:canvas" content={`${width}x${height}`} />
    </head>
    <body class="m-0 bg-slate-950 font-sans text-white">
        <div class="relative overflow-hidden" style={{ width: `${width}px`, height: `${height}px` }}>
            <slot />
        </div>
    </body>
</html>
```

```css
/* src/styles/card.css */
@import '@fontsource/fira-sans/400.css';
@import '@fontsource/fira-sans/700.css';
@import 'tailwindcss';

@theme {
    --font-sans: 'Fira Sans', ui-sans-serif, sans-serif;
}
```

Fontsource bundles the font files with the build. Focus blocks requests outside the template directory, so a font loaded from a CDN would be missing from the card.

## A template

Mark each element Focus fills with `data-focus`, and put realistic sample content inside it:

```astro
---
// src/pages/one-up.astro
import Card from '../layouts/Card.astro';
---

<Card>
    <h1 data-focus="title" class="absolute top-17.5 inset-x-17.5 text-center text-9xl font-bold">
        Project Title
    </h1>

    <div class="absolute inset-x-0 bottom-40 flex justify-center">
        <div class="aspect-1400/816 w-[1400px] overflow-hidden rounded-3xl shadow-2xl">
            <img data-focus="screenshot.1" src="/samples/primary.png" alt=""
                 class="h-full w-full object-cover object-top-left" />
        </div>
    </div>
</Card>
```

Astro treats `{ … }` in markup as an expression, so values are never written into the markup as placeholders; `data-focus` attributes are plain HTML and pass through the build unchanged.

Put sample images in `public/samples/`. Run `npm run dev` and open `/one-up/` to see the template as it will look, and design at the canvas size in ordinary pixels.

For another aspect ratio, add a page with a different canvas, such as `<Card width={2560} height={1440}>` in `one-up-video.astro`.

## Publish the build

Focus reads the built files, not the Astro source, so the build must be committed:

1. Remove `dist/` from `.gitignore`.
2. Run `npm run build`.
3. Commit `src/` and `dist/` together.
4. Tag a release, such as `v1.0.0`, and push the tag.

Then point packages at the tag:

```php
->cardTemplates('https://github.com/acme/card-templates/tree/v1.0.0/dist')
```

> [!WARNING]
> Do not mark `dist/` as `export-ignore` in `.gitattributes`. Focus downloads templates as a GitHub archive, and GitHub leaves export-ignored paths out of archives.

Pinning a tag keeps every package's cards the same until you choose to update. While working on the templates themselves, point a package at the local build instead, such as `->cardTemplates('../card-templates/dist')`, and rebuild to see changes.
