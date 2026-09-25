---
title: CLI
description: Run Focus from the command line, filter captures, debug in a visible browser, and manage output files.
---

# CLI

```bash
vendor/bin/focus [options]
vendor/bin/focus init [--skip-browsers]
```

Running `vendor/bin/focus` with no command generates every screenshot in the manifest. `composer focus` does the same without Composer's process timeout (see [Installation](installation.md#the-composer-script)).

## Options

| Option | Purpose |
|---|---|
| `--config=PATH`, `-c` | Manifest path. Defaults to `focus.php`. |
| `--only=NAMES` | Only capture the named screenshots. Comma-separated or repeated. |
| `--theme=THEME` | Only capture one theme: `light` or `dark`. |
| `--headed` | Show the browser while capturing. |
| `--base-url=URL` | Use a running application instead of starting Workbench. |
| `--prune` | Delete orphaned assets after an unfiltered run. |
| `--force`, `-f` | Prune without asking for confirmation. |
| `--list` | Show the planned captures without opening a browser. |
| `-v` | Also print each capture as it starts. |

## Filtering

```bash
vendor/bin/focus --only=editor
vendor/bin/focus --only=editor,brick-picker
vendor/bin/focus --theme=dark
```

Filters narrow what the manifest defines; they never add captures. An unknown `--only` name is an error, so a typo does not silently capture nothing.

`--list` prints each capture's theme, mode, viewport, scale, and output path, which is a quick way to check a manifest without starting a browser.

## Headed mode

```bash
vendor/bin/focus --headed --only=brick-picker
```

Opens a visible Chromium window. Use it while writing a screenshot definition to see what the browser sees. Headless is the default.

## Output files

Each capture is written as:

```text
{name}-{theme}.png
```

so `Screenshot::make('editor')` produces `docs/assets/editor-light.png` and `docs/assets/editor-dark.png`. Names contain no timestamps, hashes, or package prefix, and each run overwrites the previous files. The form `{name}-{viewport}-{theme}.png` is reserved for a future feature that captures one screenshot at several viewports, so existing names will not change.

### Using screenshots in documentation

The awcodes documentation hub serves images from `docs/assets/`, so the default output path needs no configuration. Reference both theme variants from a page, scoped with the `#gh-light-mode-only` and `#gh-dark-mode-only` fragments, and the hub shows the one matching the reader's theme:

```markdown
![The brick picker](assets/brick-picker-light.png#gh-light-mode-only)
![The brick picker](assets/brick-picker-dark.png#gh-dark-mode-only)
```

From a page in a subdirectory, such as `docs/usage/editor.md`, the path is `../assets/brick-picker-light.png`. The fragment convention comes from GitHub, which has since deprecated it, so a page viewed on GitHub may show both images.

### Atomic writes

Each capture is written to a temporary file in the output directory and moved into place only once it succeeds. A failed capture never replaces or deletes the previous file, and the failure report says when an existing file was left over from an earlier run, so it is not mistaken for a fresh one.

### Orphaned assets

Renaming or removing a screenshot leaves its old files behind. After an unfiltered run, Focus lists files in the output directory that match the naming scheme (`*-light.png`, `*-dark.png`) but were not produced by the manifest.

To delete them:

```bash
vendor/bin/focus --prune          # lists the files and asks for confirmation
vendor/bin/focus --prune --force  # deletes without asking
```

Pruning never touches files that do not match the scheme, so other images in `docs/assets` are safe. Orphans are not reported when `--only` or `--theme` is used, and `--prune` cannot be combined with them.

## Exit codes

Focus exits with `0` when every capture succeeds and `1` when the manifest is invalid, the server or browser cannot start, authentication fails, any capture fails, or pruning fails. One failed capture does not stop the others.

## Reading a failure

```text
   ✗  brick-picker (dark) Selector not found
      focus([data-focus="brick-picker"]) matched no elements.
      Screenshot brick-picker
      Theme      dark
      Viewport   1440x1000
      URL        http://127.0.0.1:53211/admin/pages/1/edit
      Selector   [data-focus="brick-picker"]
      Not updated: docs/assets/brick-picker-dark.png is from a previous run.
```

The first line gives the failure category. See [Troubleshooting](troubleshooting.md) for what each one means.
