/**
 * Brand assets, generated from one SVG.
 *
 * Deterministic on purpose: CI regenerates these into a temp directory and
 * byte-compares them against what is committed, so a changed logo cannot leave
 * a stale favicon behind. That guarantee only holds if every input is pinned —
 * hence resvg and png-to-ico from package-lock.json rather than whatever
 * ImageMagick the runner image happens to ship, and `loadSystemFonts: false`
 * so the machine's font set cannot leak into the output.
 *
 * The assets are committed rather than built on deploy because
 * `public/favicon.ico` is fetched by path and `public/build` is gitignored — a
 * build-time-only favicon does not exist in a fresh checkout.
 */
import { Resvg } from '@resvg/resvg-js';
import pngToIco from 'png-to-ico';
import { decompress } from 'wawoff2';
import { mkdir, mkdtemp, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const out = process.argv[2] ? resolve(process.argv[2]) : resolve(root, 'public');

/**
 * The exact fonts the app itself serves, pinned by package-lock.json.
 *
 * System fonts are refused because the runner's font set differs from a
 * developer's, which would break the byte comparison for reasons unrelated to
 * the logo. But refusing them without supplying replacements renders text as
 * *nothing at all* — the first OG card came out with an empty space where the
 * product name should be.
 *
 * resvg reads TTF and OTF, not woff2 (verified: a woff2-only run produced a
 * 436-byte PNG containing just the background). @fontsource ships woff/woff2
 * only, so the pinned woff2 is decompressed to TTF here. Same input bytes in,
 * same TTF out — determinism is preserved and no binary is committed.
 */
async function loadFonts() {
    // A fresh directory per run. A shared one is reused across runs, so a
    // partially-written font from an interrupted run would silently change the
    // output of every later one.
    const dir = await mkdtemp(join(tmpdir(), 'lorapok-brand-'));
    const files = [];

    // Written one at a time, deliberately. Writing the three concurrently let
    // resvg occasionally read a file before it was fully flushed, and it
    // degrades silently: no error, just a slightly different PNG. That showed
    // up as a generator which produced three different outputs before settling.
    for (const weight of ['400', '500', '700']) {
        const woff2 = resolve(
            root,
            `node_modules/@fontsource/dm-sans/files/dm-sans-latin-${weight}-normal.woff2`,
        );
        const ttf = join(dir, `dm-sans-${weight}.ttf`);

        await writeFile(ttf, await decompress(await readFile(woff2)));
        files.push(ttf);
    }

    return files;
}

const fontFiles = await loadFonts();

async function render(svgPath, width) {
    const svg = await readFile(svgPath);

    return new Resvg(svg, {
        fitTo: { mode: 'width', value: width },
        font: {
            fontFiles,
            loadSystemFonts: false,
            defaultFontFamily: 'DM Sans',
        },
    })
        .render()
        .asPng();
}

async function emit(relativePath, buffer) {
    const target = resolve(out, relativePath);
    await mkdir(dirname(target), { recursive: true });
    await writeFile(target, buffer);
    console.log(`  ${relativePath.padEnd(30)} ${String(buffer.length).padStart(7)} bytes`);
}

const mark = resolve(root, 'resources/brand/mark.svg');

console.log(`Generating brand assets into ${out}`);

for (const [name, size] of [
    ['favicon-16x16.png', 16],
    ['favicon-32x32.png', 32],
    ['apple-touch-icon.png', 180],
    ['android-chrome-192x192.png', 192],
    ['android-chrome-512x512.png', 512],
    // Play Store requires exactly 512 with no transparency; the mark is
    // full-bleed so it already satisfies that.
    ['brand/play-store-512.png', 512],
]) {
    await emit(name, await render(mark, size));
}

// sharp cannot write .ico, and Windows taskbar pins actually use the 48px
// entry — so all three sizes go in.
await emit(
    'favicon.ico',
    await pngToIco([await render(mark, 16), await render(mark, 32), await render(mark, 48)]),
);

await emit('brand/lockup.png', await render(resolve(root, 'resources/brand/lockup.svg'), 880));

// The Open Graph card. Every social platform crops from 1200x630.
await emit('og-image.png', await render(resolve(root, 'resources/brand/og-card.svg'), 1200));

console.log('Done.');
