---
title: Card templates
description: Write card templates as HTML pages with data-focus attributes, fixed canvases, and bundled assets.
---

# Card templates

A card template is an HTML page in a template directory. Focus loads it in a browser, fills its `data-focus` elements, and captures it. Focus does not care how the page was made: write it by hand, build it with Tailwind, or generate it with a static site generator such as Astro (see [Building templates with Astro](astro.md)).

## The template directory

`cardTemplates()` points at a directory of built files. A template named `two-up` is either of:

```text
two-up.html
two-up/index.html
```

If both exist, Focus reports the name as ambiguous. Names are lowercase kebab-case and may be nested, such as `social/two-up`.

Focus serves the directory from the root of a local origin, `http://focus.localhost/`, so root-relative URLs such as `/_astro/card.css` and relative URLs such as `card.css` both work. Every other file in the directory, including stylesheets, fonts, and images, is available to the page.

## A minimal template

```html
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="focus:canvas" content="2400x1260">
    <style>
        body { margin: 0; width: 2400px; height: 1260px; display: grid; place-content: center;
               background: #0f172a; color: #fff; font-family: sans-serif; text-align: center; }
        h1 { font-size: 160px; margin: 0; }
        p { font-size: 56px; max-width: 1800px; }
        code { font-size: 44px; }
    </style>
</head>
<body>
    <h1 data-focus="title">Project Title</h1>
    <p data-focus="description">A sample description, so the page previews realistically in a browser.</p>
    <code data-focus="install">composer require vendor/package</code>
</body>
</html>
```

Save it as `default.html` in the template directory and `Card::make('social')` renders it. The text inside each `data-focus` element is sample content that Focus replaces, so you can open the page in a browser to design it.

This template uses a system font for brevity. Bundle fonts in real templates; see [Assets and fonts](#assets-and-fonts).

## data-focus values

| Key | Filled with |
|---|---|
| `title`, `description`, `package`, `install` | The card's values (see [Cards](overview.md#values)) |
| any `with()` key | The card's custom value |
| `screenshot.1`, `screenshot.2`, … | The card's screenshots, in the theme being rendered |
| `screenshot.1.light`, `screenshot.1.dark`, … | A screenshot in that theme, whatever the card's theme |

Values are set as text, never parsed as HTML, so a description containing `<b>` shows the characters `<b>`. There is nothing to escape.

A key the card does not provide fails the card and names the key, so a typo such as `data-focus="titel"` is caught. A `with()` value or screenshot the template never uses is reported as a warning.

### Screenshots

Screenshot keys go on `<img>` elements. Focus replaces the image's `src` and removes `srcset` and `sizes`; inside a `<picture>`, it also removes the `<source>` elements so the screenshot is shown:

```html
<div class="frame">
    <img data-focus="screenshot.1" src="/samples/primary.png" alt="">
</div>
```

Screenshots vary in shape, so make them fill their frame and anchor the crop at the top left, where a UI screenshot's page chrome and headings are:

```css
.frame { width: 1400px; aspect-ratio: 1400 / 816; overflow: hidden; }
.frame img { width: 100%; height: 100%; object-fit: cover; object-position: top left; }
```

For backgrounds, each screenshot is also available as a CSS custom property on the root element:

```css
.hero { background: var(--focus-screenshot-1) top left / cover; }
```

`--focus-screenshot-N` is the variant for the theme being rendered; `--focus-screenshot-N-light` and `--focus-screenshot-N-dark` are the specific variants.

A template that shows both variants in one image uses the explicit keys:

```html
<img data-focus="screenshot.1.light" alt="">
<img data-focus="screenshot.1.dark" alt="">
```

The screenshot must be captured in both themes; otherwise the card fails and names the missing variant.

### Theme and size

While rendering, the root element has `data-focus-theme` (`light` or `dark`) and `data-focus-size` (such as `open-graph` or `1920x1080`), and the browser's colour scheme matches the theme, so both of these work:

```css
html[data-focus-theme="light"] body { background: #fff; color: #0f172a; }

@media (prefers-color-scheme: light) {
    body { background: #fff; }
}
```

## Fixed canvases

Declare the size a template is designed at with a meta tag:

```html
<meta name="focus:canvas" content="2400x1260">
```

Focus lays the page out at exactly that size, then draws it at whatever scale fits each card size. The image is drawn directly at the output resolution, not resized afterwards, so text stays sharp at every size. Design in plain pixels at the canvas size; the template does not need to be responsive.

When a card's aspect ratio differs from the canvas, Focus scales the canvas to cover the card and crops the centre. For example, a 2400×1260 canvas (Open Graph's ratio) rendered at GitHub's 1280×640 loses 30px from its top and bottom. A crop of more than 1% in either direction is reported:

```text
! The 2400x1260 template was cropped to fit 1280x640: 30px from the top and bottom. Keep important content away from those edges.
```

Keep a margin around important content, and keep one template per aspect ratio you publish, such as a 2560×1440 template for 16:9 video thumbnails and a 2400×1260 one for Open Graph and GitHub. Choose the template for each card explicitly:

```php
Card::make('social')->template('two-up-wide')->sizes([Size::OpenGraph, Size::GitHubSocial]),
Card::make('video')->template('two-up')->sizes([[1920, 1080]]),
```

Without the meta tag, the page is laid out at the card size itself, so it must adapt to each size with its own CSS.

## Assets and fonts

While rendering, the page can load only files from the template directory. Requests anywhere else, such as a font from a CDN, are blocked and reported:

```text
! Blocked a request outside the template directory: https://fonts.bunny.net/css?family=fira-sans:400. Bundle remote assets such as fonts with the template.
```

This keeps cards identical from run to run and independent of the network. Bundle every asset with the template, and bundle fonts in particular: a system font differs between machines and changes the output. Font packages such as [Fontsource](https://fontsource.org) make this straightforward.

A file the page asks for that is not in the directory is answered with a 404 and reported, so a broken path shows up as a warning rather than a missing image you have to spot.

Scripts run, so a template can adjust itself before capture, but nothing is needed: Focus fills the values before the page's images load, then waits for fonts, images, and layout to settle before capturing.

## Overflow

If the page is larger than its canvas, or than the card when there is no canvas, Focus warns that the content overflows. Long titles and descriptions are the usual cause; test templates with your longest package description.
