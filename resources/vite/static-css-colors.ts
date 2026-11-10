import { transform } from 'lightningcss';
import type { Plugin } from 'vite';

/*
 * Bakes every colour into a plain value every browser understands, so phones
 * render exactly what the desktop renders.
 *
 * Tailwind writes colours as `var(--x)` and opacity modifiers as
 * `color-mix(...)` behind `@supports (color: color-mix(...))`. Browsers
 * without color-mix (older iOS Safari, Samsung Internet, Android WebView)
 * drop those colours or fall back to the fully opaque one. The app has a
 * single (light) theme, so each colour variable defined only on :root is a
 * constant: it is inlined, color-mix() is computed at build time, and the
 * @supports guards around it are removed.
 */

/**
 * Same browsers for Vite's CSS minifier, so it keeps the fallbacks above
 * (`build.cssTarget` in vite.config.ts).
 */
export const cssTarget = [
    'chrome99',
    'edge99',
    'firefox97',
    'safari15.4',
    'ios15.4',
];

// Oldest browsers the output must render correctly (encoded major << 16).
const targets = {
    chrome: 99 << 16,
    safari: (15 << 16) | (4 << 8),
    ios_saf: (15 << 16) | (4 << 8),
    firefox: 97 << 16,
    samsung: 18 << 16,
};

const COLOR_VALUE =
    /^(#|rgba?\(|hsla?\(|oklch\(|oklab\(|lab\(|lch\(|color-mix\(|transparent$)/i;
const COLOR_MIX_SUPPORTS =
    /@supports\s*\(\s*color\s*:\s*color-mix\(\s*in lab,\s*red,\s*red\s*\)\s*\)/g;
// Uncompiled Tailwind source: never touch it, only Tailwind's output.
const TAILWIND_SOURCE =
    /@(import|theme|apply|custom-variant|source|utility|tailwind)\b/;

type StyleRule = {
    value: {
        selectors: { type: string; kind?: string }[][];
        declarations?: {
            declarations?: { property: string; value: { name?: string } }[];
            importantDeclarations?: {
                property: string;
                value: { name?: string };
            }[];
        };
    };
};

/** Names of custom properties defined only on :root / :host, never elsewhere. */
function rootOnlyProperties(css: string, filename: string): Set<string> {
    const onRoot = new Set<string>();
    const elsewhere = new Set<string>();

    transform({
        filename,
        code: Buffer.from(css),
        errorRecovery: true,
        visitor: {
            Rule: {
                style(rule: StyleRule) {
                    const isRoot = rule.value.selectors.every(
                        (selector) =>
                            selector.length === 1 &&
                            selector[0].type === 'pseudo-class' &&
                            (selector[0].kind === 'root' ||
                                selector[0].kind === 'host'),
                    );
                    const block = rule.value.declarations;

                    for (const declaration of [
                        ...(block?.declarations ?? []),
                        ...(block?.importantDeclarations ?? []),
                    ]) {
                        if (
                            declaration.property === 'custom' &&
                            declaration.value.name
                        ) {
                            (isRoot ? onRoot : elsewhere).add(
                                declaration.value.name,
                            );
                        }
                    }
                },
            },
        },
    });

    return new Set([...onRoot].filter((name) => !elsewhere.has(name)));
}

function bakeColors(source: string, filename: string): string {
    // Flatten nesting first (dev output keeps `:root { @supports { … } }`).
    const css = transform({
        filename,
        code: Buffer.from(source),
        targets,
        errorRecovery: true,
    }).code.toString();
    const names = rootOnlyProperties(css, filename);
    const values = new Map<string, string>();

    // The last definition wins: Tailwind writes an opaque fallback first and
    // the real color-mix() value after it, inside @supports.
    for (const match of css.matchAll(/(--[\w-]+)\s*:\s*([^;{}]+?)\s*[;}]/g)) {
        if (names.has(match[1])) {
            values.set(match[1], match[2]);
        }
    }

    const substitute = (text: string): string =>
        text.replace(/var\(\s*(--[\w-]+)\s*\)/g, (whole, name: string) =>
            values.has(name) ? values.get(name)! : whole,
        );

    // Resolve variables that point at other variables.
    for (let pass = 0; pass < 10; pass++) {
        let changed = false;

        for (const [name, value] of values) {
            const resolved = substitute(value);

            if (resolved !== value) {
                values.set(name, resolved);
                changed = true;
            }
        }

        if (!changed) {
            break;
        }
    }

    // Inline only constant colours; leave sizes, fonts and anything dynamic.
    for (const [name, value] of values) {
        if (!COLOR_VALUE.test(value) || value.includes('var(')) {
            values.delete(name);
        }
    }

    // Once lowered, the colours inside need no guard: `@media all` always applies.
    const unguarded = substitute(css).replace(COLOR_MIX_SUPPORTS, '@media all');

    const { code } = transform({
        filename,
        code: Buffer.from(unguarded),
        targets,
        errorRecovery: true,
    });

    return code.toString();
}

export function staticCssColors(): Plugin {
    return {
        name: 'examis:static-css-colors',
        // Runs after Tailwind's own (also "pre") transform has produced CSS.
        enforce: 'pre',
        transform(code, id) {
            if (
                !/\.css(\?|$)/.test(id) ||
                id.includes('node_modules') ||
                TAILWIND_SOURCE.test(code)
            ) {
                return null;
            }

            return { code: bakeColors(code, id), map: null };
        },
    };
}
