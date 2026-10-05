---
title: Troubleshooting
description: Diagnose common Focus failures by category, from an unavailable Workbench to unstable screenshots.
---

# Troubleshooting

Every failure names a category. Start with the category, then the message beneath it.

## Workbench unavailable

Focus could not reach the application.

- **"vendor/bin/testbench was not found"**: run `composer install`, or pass `--base-url` to use an application that is already running.
- **"Workbench exited before it was ready"**: the Workbench failed to boot. Run `composer serve` to see the error directly; the output Focus captured is printed below the message.
- **"Nothing responded at …"**: the `--base-url` or `baseUrl()` server is not running, or is on a different port.

## Authentication failed

The run stops before any capture.

- **"The login page returned HTTP 404"**: the application has no login at the configured path. Use `->login(path: '/your/login')`, or `->withoutLogin()` for public pages.
- **"Signing in as … did not leave the login page"**: the credentials were rejected. The Workbench is usually not seeded with the development account, often because a Composer command reset it; run `composer build`. For other credentials, configure `->login(...)`.
- **"The application is rate-limiting sign-in attempts"**: too many sign-ins in a short time. Wait a minute. Session reuse normally prevents this; check the suite does not set `->reuseSession(false)`.
- **"The login page … has no … field"**: the form does not have a standard email field, password field, or submit button. Use `->authenticateUsing(...)` to sign in yourself.

## Navigation failed

A `visit()` returned an HTTP error. Check the URL against the Workbench routes and make sure the records it refers to are seeded. A `500` usually means the Workbench needs rebuilding with `composer build`.

## Selector not found, ambiguous, or hidden

- **Not found**: nothing matched within the timeout. Check the selector in the browser's developer tools. If the element is inside an iframe, wrap the steps in [`within()`](screenshots/interactions.md#inside-iframes).
- **Matched more than one element**: add a more specific `data-focus` hook. If the other matches are hidden, such as closed modals, add `:visible`. Focus never guesses which match you meant.
- **Hidden**: the element exists but is not visible or has no size. It may appear only after an interaction; add the `click()` or `hover()` that reveals it, followed by `waitFor()`.

Run the screenshot with `--headed --only=name` to watch what happens.

## Timed out

An interaction, `waitFor()`, or Playwright call inside a callback did not finish within the timeout. The default is 15 seconds; raise it for slow pages:

```php
return ScreenshotSuite::make()
    ->timeout(30_000)
    ->screenshots([...]);
```

## Callback failed

A `before()`, `beforeCapture()`, `after()`, `beforeEach()`, `ready()`, or custom step threw an exception. The message is the exception's own message.

## Fixture failed

A `fixture()` file does not exist, or a fixture closure threw. The message names the request and the path Focus looked for. Paths are relative to the repository root unless absolute. Focus fails the capture rather than writing an image with a broken remote asset.

## Browser error

Playwright or Chromium failed.

- **"Could not start Playwright"** or **"Could not launch Chromium"**: the Playwright server or browser is not installed, which is normal after a fresh `composer install`. Run `vendor/bin/focus init` or `vendor/bin/playwright-install chromium`. Focus needs Node.js 20 or later.

## Template error

A card could not be rendered from its template. The message lists every problem:

- **"Card template [name] not found"**: the template directory has neither `name.html` nor `name/index.html`. Check the name against the built files, and rebuild the templates if the page is new.
- **"[key] is not a value this card provides"**: the template has a `data-focus` key the card does not supply. Fix the typo, or pass the value with `->with(['key' => '…'])`.
- **"the card passes 1 screenshot, so there is no screenshot 2"**: the template has more screenshot slots than the card's `screenshots()`.
- **"screenshot keys only work on <img>"**: use an `<img>`, or the `--focus-screenshot-N` CSS variables for backgrounds.
- **"screenshot [name] has no dark capture at …"**: the card uses a screenshot file that does not exist yet, usually in a `--cards-only` or `--only` run. Run the screenshot first.
- **"The focus:canvas meta tag must look like …"**: the canvas must be `{width}x{height}`, such as `2400x1260`.

See [Card templates](cards/templates.md).

## Screenshot failed

A card was not rendered because a screenshot it uses failed in the same run. Fix the screenshot; its failure is reported above the card.

## Card templates cannot be loaded

The run stops before any browser starts.

- **"has no tag or branch named"**: the ref in the GitHub reference does not exist. Check the tag was pushed.
- **"was not found. If it is private, set GITHUB_TOKEN"**: the repository does not exist, or it is private and no token is set.
- **"is not in the … archive"**: the directory is not in the downloaded repository. Check it is committed at that ref and not marked `export-ignore` in `.gitattributes`.
- **"Could not resolve … No cached copy is available"**: GitHub could not be reached and the templates have never been downloaded. Run again with network access once.
- **"needs git on PATH"**: install git, or pin the reference to a full commit SHA.

See [Template sources](cards/sources.md).

## Could not write output

Focus could not create the output directory or move the finished file into place. Check the `outputPath()` directory is writable.

## Warnings

Warnings do not fail a capture, but they are worth reading:

- **"The page was still changing … captured anyway"**: something kept changing the page, such as polling or a looping animation with `allowAnimations()`. Add a `waitFor()` or `ready()` step for the state you want.
- **"The subject or the minimum size is larger than the document"**: the subject or `minSize()` is bigger than the page, so the capture is smaller than requested.
- **"Images were still loading … before capture"**: an image, often a remote one, did not finish loading within ten seconds. Serve it with `fixture()`, or check the network.
- **"Loaded … over the network"**: the page loaded a remote URL that no fixture answered, so the capture can change between runs. Serve it with `fixture()`, or accept it with `allowRemote()`. See [Remote requests](screenshots/themes-and-stability.md#remote-requests).
- **"Every theme produced an identical image"**: the page has no dark mode. Restrict it with `->themes([Theme::Light])`.
- **"The template was cropped to fit …"**: the card's aspect ratio differs from the template's canvas. Keep content away from the named edges, or use a template designed for that ratio; see [Fixed canvases](cards/templates.md#fixed-canvases).
- **"content overflows"**: the template is larger than its canvas or card, usually because of a long title or description.
- **"Blocked a request outside the template directory"**: the template loads something from the network, such as a web font. Bundle it with the template.
- **"The template requested …, which is not in the template directory"**: a broken asset path in the template.
- **"is not used by template"**: a `with()` value or screenshot the template never shows.
- **"Card templates follow the … branch"**: the GitHub reference is a branch, so cards can change without warning. Pin a tag or commit.

## Screenshots look wrong

- **Unstyled or outdated UI**: the Workbench's published assets are stale. Run `composer build` in the package repository after pulling changes.
- **Content changes on every run**: fixtures use random Faker data or `now()`. Make them deterministic, or `mask()` the affected elements. See [Workbench integration](workbench.md#deterministic-fixtures).
- **A broken or missing avatar in the top bar**: Focus answers Filament's default `ui-avatars.com` avatar locally. A custom avatar provider loads from somewhere else; serve it with `fixture()`.
- **Tooltips, hover styles, or focus rings**: Focus clears these before capture unless the last pointer step is a `hover()` or the screenshot uses `keepInteractionState()`. Anything set in a `beforeCapture()` callback is kept.
- **Wrong theme inside an embedded preview**: the iframe controls its own colour scheme. The browser reports the requested theme, but the embedded document decides how to render it.
- **Small differences between machines**: font rendering differs across operating systems. Regenerate from one consistent environment; see [Themes and rendering stability](screenshots/themes-and-stability.md#cross-platform-differences).
- **Small differences between runs on one machine**: often a page styled with the system font. See [System fonts](screenshots/themes-and-stability.md#system-fonts).
