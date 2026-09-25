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
                const state = window.__focus = { pending: 0, frames: 0, alpine: false };
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
}
