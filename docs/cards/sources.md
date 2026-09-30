---
title: Template sources
description: Load card templates from a local directory or a GitHub repository, with caching, private repositories, and offline runs.
---

# Template sources

`cardTemplates()` accepts a local path or a GitHub reference.

## A local directory

```php
->cardTemplates('../card-templates/dist')
```

A relative path is resolved from the repository root. The directory is used as it is, with no copying or caching, which makes a local path the right choice while you are working on templates.

## A GitHub repository

```php
->cardTemplates('https://github.com/acme/card-templates/tree/v1.0.0/dist')
->cardTemplates('github:acme/card-templates/dist@v1.0.0')
```

Both forms mean the `dist` directory of `acme/card-templates` at the `v1.0.0` tag. The ref can be a tag, a branch, or a full 40-character commit SHA. Leave out the path to use the repository root:

```php
->cardTemplates('github:acme/card-templates@v1.0.0')
```

In the URL form, the segment after `tree/` is the ref, so a ref that contains a slash, such as `release/v1`, needs the `github:` form, where the ref comes last.

### Resolution and caching

1. Focus resolves the ref to a commit with `git ls-remote`. An annotated tag resolves to the commit it points to. A full commit SHA is used directly and does not need `git`.
2. It downloads the repository archive at that commit, keeps only the requested directory, and caches it by commit.
3. Later runs with the same commit use the cache without downloading anything.

The cache is `~/.cache/focus`, or `$XDG_CACHE_HOME/focus` when `XDG_CACHE_HOME` is set. Set `FOCUS_CACHE_DIR` to use another directory.

`--refresh-templates` downloads the templates again, ignoring the cache.

### Pin a tag or commit

A tag or commit always resolves to the same files, so cards only change when you change the reference. A branch follows new commits, so cards can change without any change in your repository; Focus warns on every run that uses one:

```text
! Card templates follow the main branch of acme/card-templates, so cards can change without any change in this repository. Pin a tag or commit for reproducible output.
```

### Private repositories

Set `GITHUB_TOKEN`, or `FOCUS_GITHUB_TOKEN` to use a separate token, to a token that can read the repository. Focus uses it both to resolve the ref and to download the archive.

### Offline runs

If GitHub cannot be reached, Focus uses the commit it last resolved for that ref, from the cache, and says so. With nothing cached, cards fail with a message explaining why, and screenshots are still captured.

## What gets downloaded

GitHub builds archives from the repository's committed files, and leaves out anything marked `export-ignore` in `.gitattributes`. If the requested directory is not in the archive, Focus reports it and points at `export-ignore` as the likely cause. Built templates must be committed, and not export-ignored; see [Building templates with Astro](astro.md#publish-the-build).

`--list` never touches the network: it shows the template source as written in the manifest.
