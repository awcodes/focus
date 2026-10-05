<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\Enums\Theme;

/**
 * JavaScript evaluated in the browser. Kept framework-agnostic: the Filament-specific pieces
 * (the `theme` localStorage key) are harmless in any other application.
 */
final class Scripts
{
    public const STYLE_ID = 'focus-stabilize';

    public const MASK_TAG = 'focus-mask';

    public const HIDE_STYLE_ID = 'focus-hide';

    private const STABILIZE_CSS = <<<'CSS'
        *, *::before, *::after {
            animation-delay: 0s !important;
            animation-duration: 0s !important;
            animation-iteration-count: 1 !important;
            transition-delay: 0s !important;
            transition-duration: 0s !important;
            caret-color: transparent !important;
            scroll-behavior: auto !important;
        }
        CSS;

    /**
     * Runs in every frame before any page script.
     */
    public static function init(Theme $theme, bool $animations): string
    {
        $config = json_encode([
            'theme' => $theme->value,
            'css' => $animations ? null : self::STABILIZE_CSS,
            'styleId' => self::STYLE_ID,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return <<<JS
            (() => {
                const config = {$config};

                // Filament persists its theme switcher choice here; force it to match the capture theme.
                try { window.localStorage.setItem('theme', config.theme); } catch (e) {}

                // Track in-flight requests and DOM activity so readiness can wait for the UI to settle.
                // Quiet time is counted in animation frames, not clock time, because the clock may be frozen.
                const state = window.__focus = { pending: 0, frames: 0, alpine: false, resources: [] };
                const touch = () => { state.frames = 0; };

                if (window.fetch) {
                    const fetch = window.fetch;
                    window.fetch = function (...args) {
                        state.pending++; touch();
                        return fetch.apply(window, args).finally(() => { state.pending--; touch(); });
                    };
                }

                const send = XMLHttpRequest.prototype.send;
                XMLHttpRequest.prototype.send = function (...args) {
                    state.pending++; touch();
                    this.addEventListener('loadend', () => { state.pending--; touch(); }, { once: true });
                    return send.apply(this, args);
                };

                new MutationObserver((records) => {
                    if (records.some((record) => ! record.target.closest?.('focus-mask'))) touch();
                }).observe(document, { subtree: true, childList: true, attributes: true, characterData: true });

                document.addEventListener('alpine:initialized', () => { state.alpine = true; });

                // Record every resource the frame loads, for the remote request report. An observer rather than
                // performance.getEntriesByType(), because a frozen clock replaces window.performance with a fake.
                try {
                    new PerformanceObserver((list) => {
                        for (const entry of list.getEntries()) state.resources.push(entry.name);
                    }).observe({ type: 'resource', buffered: true });
                } catch (e) {}

                const tick = () => { state.frames++; requestAnimationFrame(tick); };
                requestAnimationFrame(tick);

                if (config.css) {
                    const addStyle = () => {
                        if (document.getElementById(config.styleId)) return;
                        const style = document.createElement('style');
                        style.id = config.styleId;
                        style.textContent = config.css;
                        (document.head || document.documentElement).appendChild(style);
                    };

                    if (document.documentElement) addStyle();
                    document.addEventListener('DOMContentLoaded', addStyle);
                }
            })();
            JS;
    }

    /**
     * A `waitForFunction` predicate: true once the document, fonts, images, Alpine, and network are settled
     * and nothing has changed for a few animation frames.
     */
    public static function ready(): string
    {
        return <<<'JS'
            (quietFrames) => {
                const settled = (win) => {
                    const doc = win.document;
                    const state = win.__focus;

                    if (doc.readyState !== 'complete') return false;
                    if (doc.fonts && doc.fonts.status !== 'loaded') return false;

                    for (const img of doc.images) {
                        if (img.loading !== 'lazy' && ! img.complete) return false;
                    }

                    if (win.Alpine && state && ! state.alpine && doc.querySelector('[x-cloak]')) return false;

                    if (state && (state.pending > 0 || state.frames < quietFrames)) return false;

                    for (const frame of doc.querySelectorAll('iframe')) {
                        let child = null;
                        try { child = frame.contentWindow && frame.contentWindow.document ? frame.contentWindow : null; } catch (e) {}
                        if (child && ! settled(child)) return false;
                    }

                    return true;
                };

                return settled(window);
            }
            JS;
    }

    /**
     * Cover matching elements with absolutely positioned blocks. Playwright's native `mask` option is
     * unavailable through playwright-php, so this reproduces it.
     */
    public static function mask(): string
    {
        return <<<'JS'
            ({ selectors, color, tag }) => {
                let count = 0;

                for (const selector of selectors) {
                    for (const el of document.querySelectorAll(selector)) {
                        const rect = el.getBoundingClientRect();
                        if (rect.width === 0 || rect.height === 0) continue;

                        const block = document.createElement(tag);
                        Object.assign(block.style, {
                            display: 'block',
                            position: 'absolute',
                            left: (rect.left + window.scrollX) + 'px',
                            top: (rect.top + window.scrollY) + 'px',
                            width: rect.width + 'px',
                            height: rect.height + 'px',
                            background: color,
                            opacity: '1',
                            margin: '0',
                            zIndex: '2147483647',
                            pointerEvents: 'none',
                        });
                        document.documentElement.appendChild(block);
                        count++;
                    }
                }

                return count;
            }
            JS;
    }

    /**
     * Load lazy images now, in the page and every same-origin iframe. Readiness waits for eager images, so a lazy
     * image inside the capture (a remote avatar, say) is loaded rather than captured half-way or not at all.
     */
    public static function eagerImages(): string
    {
        return <<<'JS'
            () => {
                const eager = (doc) => {
                    for (const img of doc.querySelectorAll('img[loading="lazy"]')) {
                        img.loading = 'eager';
                    }

                    for (const frame of doc.querySelectorAll('iframe')) {
                        try {
                            if (frame.contentDocument) eager(frame.contentDocument);
                        } catch (e) {}
                    }
                };

                eager(document);
            }
            JS;
    }

    /**
     * Hide elements without affecting layout. One rule per selector, so an invalid selector only drops itself.
     */
    public static function hide(): string
    {
        return <<<'JS'
            ({ selectors, id }) => {
                const style = document.createElement('style');
                style.id = id;
                document.documentElement.appendChild(style);

                for (const selector of selectors) {
                    try {
                        style.sheet.insertRule(`${selector} { visibility: hidden !important; }`, style.sheet.cssRules.length);
                    } catch (e) {}
                }
            }
            JS;
    }

    public static function unhide(): string
    {
        return '(id) => document.getElementById(id)?.remove()';
    }

    /**
     * Blur the focused element in the page and every same-origin iframe, so no focus ring is captured.
     */
    public static function blur(): string
    {
        return <<<'JS'
            () => {
                const blur = (doc) => {
                    if (doc.activeElement && doc.activeElement !== doc.body) {
                        doc.activeElement.blur();
                    }

                    for (const frame of doc.querySelectorAll('iframe')) {
                        try {
                            if (frame.contentDocument) blur(frame.contentDocument);
                        } catch (e) {}
                    }
                };

                blur(document);
            }
            JS;
    }

    /**
     * Every http(s) URL the page and its same-origin iframes loaded from another origin, as recorded by `init()`,
     * including the src of cross-origin iframes, whose own requests cannot be seen.
     */
    public static function remoteRequests(): string
    {
        return <<<'JS'
            (origin) => {
                const urls = new Set();
                const remote = (url) => {
                    try {
                        const parsed = new URL(url, location.href);
                        return /^https?:$/.test(parsed.protocol) && parsed.origin !== origin ? parsed.href : null;
                    } catch (e) {
                        return null;
                    }
                };

                const collect = (win) => {
                    for (const name of (win.__focus && win.__focus.resources) || []) {
                        const url = remote(name);
                        if (url) urls.add(url);
                    }

                    for (const frame of win.document.querySelectorAll('iframe')) {
                        let child = null;
                        try { child = frame.contentWindow && frame.contentWindow.document ? frame.contentWindow : null; } catch (e) {}

                        if (child) {
                            collect(child);
                        } else {
                            const url = remote(frame.src);
                            if (url) urls.add(url);
                        }
                    }
                };

                collect(window);

                return [...urls];
            }
            JS;
    }

    public static function unmask(): string
    {
        return '(tag) => document.querySelectorAll(tag).forEach((el) => el.remove())';
    }

    /**
     * Document dimensions in CSS pixels, for constraining focus crops.
     */
    public static function documentSize(): string
    {
        return <<<'JS'
            () => {
                const doc = document.documentElement;
                const body = document.body || doc;

                return {
                    width: Math.max(doc.scrollWidth, body.scrollWidth, doc.clientWidth),
                    height: Math.max(doc.scrollHeight, body.scrollHeight, doc.clientHeight),
                    scrollX: window.scrollX,
                    scrollY: window.scrollY,
                };
            }
            JS;
    }

    /**
     * Fill a card template's `data-focus` elements once the DOM is parsed, before its images load. Runs in the top frame
     * only. Values are set as text, never parsed as HTML. What the template asked for is recorded in `window.__focusCard`.
     *
     * @param  array{theme: string, size: string, values: array<string, string>, screenshots: array<string, string>, missing: array<string, string>}  $config
     */
    public static function cardInit(array $config): string
    {
        $config = json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);

        return <<<JS
            (() => {
                if (window !== window.top) return;

                const config = {$config};
                const report = window.__focusCard = { used: [], unknown: [], invalid: [], missing: [] };
                const has = (object, key) => Object.prototype.hasOwnProperty.call(object, key);
                const note = (list, item) => { if (! list.includes(item)) list.push(item); };

                const apply = () => {
                    const root = document.documentElement;

                    root.dataset.focusTheme = config.theme;
                    root.dataset.focusSize = config.size;

                    for (const [key, url] of Object.entries(config.screenshots)) {
                        root.style.setProperty('--focus-' + key.replaceAll('.', '-'), 'url("' + url + '")');
                    }

                    for (const element of document.querySelectorAll('[data-focus]')) {
                        const key = element.dataset.focus.trim();

                        note(report.used, key);

                        if (has(config.missing, key)) {
                            note(report.missing, config.missing[key]);
                        } else if (has(config.screenshots, key)) {
                            if (! (element instanceof HTMLImageElement)) {
                                note(report.invalid, key + ' on <' + element.tagName.toLowerCase() + '>');
                                continue;
                            }

                            // A <picture>'s <source> elements would win over the injected src.
                            if (element.parentElement instanceof HTMLPictureElement) {
                                element.parentElement.querySelectorAll('source').forEach((source) => source.remove());
                            }

                            element.removeAttribute('srcset');
                            element.removeAttribute('sizes');
                            element.src = config.screenshots[key];
                        } else if (has(config.values, key)) {
                            element.textContent = config.values[key];
                        } else {
                            note(report.unknown, key);
                        }
                    }
                };

                document.addEventListener('DOMContentLoaded', apply, { once: true, capture: true });
            })();
            JS;
    }

    /**
     * The injection report, which screenshot slots stylesheets reference through `--focus-screenshot-N`,
     * and whether the page overflows the viewport.
     */
    public static function cardReport(): string
    {
        return <<<'JS'
            (slots) => {
                // Head and body only: the root element's style holds the injected variables themselves.
                let css = (document.head ? document.head.outerHTML : '') + (document.body ? document.body.outerHTML : '');

                for (const sheet of document.styleSheets) {
                    try {
                        for (const rule of sheet.cssRules) css += rule.cssText;
                    } catch (e) {}
                }

                const cssSlots = [];

                for (let slot = 1; slot <= slots; slot++) {
                    if (new RegExp('--focus-screenshot-' + slot + '(?![0-9])').test(css)) cssSlots.push(slot);
                }

                const doc = document.documentElement;
                const body = document.body || doc;

                return {
                    ...(window.__focusCard || { used: [], unknown: [], invalid: [], missing: [] }),
                    cssSlots,
                    width: Math.max(doc.scrollWidth, body.scrollWidth),
                    height: Math.max(doc.scrollHeight, body.scrollHeight),
                    viewportWidth: window.innerWidth,
                    viewportHeight: window.innerHeight,
                };
            }
            JS;
    }
}
