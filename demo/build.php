<?php

declare(strict_types=1);

/*
 * Builds the site published at https://ruvelo.github.io/filament-wiki/.
 *
 * Filament pages are Livewire components: they need a server to answer every
 * click, so they can't be exported as static files the way the wiki's own
 * demo is. The site is a gallery of the screenshots in art/ (made from a live
 * panel by demo/screenshots.sh), linking to the wiki's live demo.
 *
 *   php demo/build.php <site-dir>     # then copy art/ into <site-dir>/art
 */

const REPO = 'https://github.com/Ruvelo/filament-wiki';
const WIKI_DEMO = 'https://ruvelo.github.io/laravel-wiki/';

$site = rtrim($argv[1] ?? __DIR__.'/../build/site', '/');
is_dir($site) || mkdir($site, 0777, true);

$shots = [
    ['screenshot-pages', 'Every page at a glance', 'Title, last edit and who made it, revisions and backlinks. Search covers titles and text; every column that should sort, sorts.'],
    ['screenshot-editor', 'Write with a live preview', 'Filament’s Markdown editor on the left, the wiki’s own renderer on the right: [[links]] resolve as you type and missing pages show in red. Saving goes through the wiki, so history, links and events stay right.'],
    ['screenshot-diff', 'See what changed', 'Each revision, its author and summary. Open one for a line diff; restore any older version as a new revision.'],
    ['screenshot-reader', 'Read it like the wiki', 'The page as the wiki renders it, with an outline and what links here. Links stay inside the panel, and red links open a new page with the title filled in.'],
    ['screenshot-dashboard', 'Know what moved', 'Recent changes and the most-wanted missing pages, on the dashboard.'],
];

$e = fn (string $text): string => htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

$figures = '';
foreach ($shots as [$file, $title, $text]) {
    $figures .= <<<HTML
        <section class="shot">
            <div class="shot-copy">
                <h2>{$e($title)}</h2>
                <p>{$e($text)}</p>
            </div>
            <a href="art/{$file}.png"><img src="art/{$file}.png" width="1440" height="900" loading="lazy" alt="{$e($title)}: a screenshot of the Halyard admin panel"></a>
        </section>

    HTML;
}

$html = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Filament Wiki by Ruvelo</title>
    <meta name="description" content="A Filament plugin for ruvelo/laravel-wiki: edit pages with a live preview, read the history, restore old versions, all from your admin panel.">
    <meta property="og:image" content="art/banner.png">
    <link rel="icon" href="ruvelo-mark.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400&display=swap">
    <style>
        :root {
            --fw-bg: #ffffff; --fw-subtle: #f8f8fc; --fw-muted: #f0f0f7; --fw-line: #e4e4ef;
            --fw-text: #16162a; --fw-text-2: #4b4b63; --fw-text-3: #74748b;
            --fw-accent: #3d4eff; --fw-accent-hover: #2b38d6; --fw-accent-soft: #eef0ff; --fw-on-accent: #ffffff; --fw-lilac: #a78bfa;
            --fw-sans: "Geist", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            --fw-mono: "Geist Mono", ui-monospace, "SF Mono", Menlo, Consolas, monospace;
            color-scheme: light dark;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --fw-bg: #11111c; --fw-subtle: #171725; --fw-muted: #1f1f30; --fw-line: #2a2a3f;
                --fw-text: #f1f1f8; --fw-text-2: #b6b6cc; --fw-text-3: #8787a3;
                --fw-accent: #8f9bff; --fw-accent-hover: #b3bbff; --fw-accent-soft: #1e2150; --fw-on-accent: #0b0b1a; --fw-lilac: #c4b5fd;
            }
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; background: var(--fw-bg); color: var(--fw-text); font: 400 16px/1.6 var(--fw-sans); -webkit-font-smoothing: antialiased; }
        a { color: var(--fw-accent); }
        a:focus-visible { outline: 2px solid var(--fw-accent); outline-offset: 3px; border-radius: 4px; }
        .stripe { height: 3px; background: linear-gradient(90deg, #3d4eff, #a78bfa); }
        .notice { background: var(--fw-accent-soft); color: var(--fw-text-2); font-size: .875rem; line-height: 1.5; padding: .6rem 16px; text-align: center; }
        .notice a { font-weight: 600; }
        .wrap { max-width: 72rem; margin: 0 auto; padding: 0 16px; }
        header.top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.25rem 0; }
        .brand { display: flex; align-items: center; gap: .6rem; color: var(--fw-text); text-decoration: none; font-weight: 600; letter-spacing: -.02em; }
        .brand img { width: 28px; height: 28px; }
        .brand span { color: var(--fw-text-3); font-weight: 500; }
        nav.links { display: flex; gap: 1.25rem; font-size: .9375rem; }
        nav.links a { color: var(--fw-text-2); text-decoration: none; }
        nav.links a:hover { color: var(--fw-accent); }
        .hero { position: relative; padding: 4rem 0 3rem; }
        .hero::before { content: ""; position: absolute; inset: 0 -50vw; z-index: -1; background-image: linear-gradient(var(--fw-line) 1px, transparent 1px), linear-gradient(90deg, var(--fw-line) 1px, transparent 1px); background-size: 44px 44px; mask-image: linear-gradient(to bottom, rgb(0 0 0 / .5), transparent 70%); }
        h1 { font-size: clamp(2.4rem, 6vw, 4.25rem); line-height: 1.02; font-weight: 600; letter-spacing: -.045em; margin: 0 0 1.25rem; max-width: 16ch; }
        .lede { font-size: 1.1875rem; color: var(--fw-text-2); max-width: 40rem; margin: 0 0 2rem; }
        .install { display: inline-flex; max-width: 100%; overflow-x: auto; font: 400 .9375rem/1 var(--fw-mono); background: var(--fw-muted); border-radius: 14px; padding: 1rem 1.25rem; white-space: nowrap; }
        .install::before { content: "$\\00a0"; color: var(--fw-text-3); }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1.5rem; }
        .button { display: inline-flex; align-items: center; min-height: 44px; padding: 0 1.1rem; border-radius: 8px; font-weight: 500; text-decoration: none; border: 1px solid var(--fw-line); color: var(--fw-text); }
        .button.primary { background: var(--fw-accent); border-color: var(--fw-accent); color: var(--fw-on-accent); box-shadow: 0 8px 20px -10px color-mix(in srgb, var(--fw-accent) 80%, transparent); }
        .button.primary:hover { background: var(--fw-accent-hover); }
        .shot { display: grid; gap: 1.25rem; padding: 3rem 0; border-top: 1px solid var(--fw-line); }
        @media (min-width: 60rem) { .shot { grid-template-columns: 18rem 1fr; gap: 2.5rem; align-items: start; } }
        .shot h2 { font-size: 1.375rem; font-weight: 600; letter-spacing: -.02em; margin: 0 0 .5rem; }
        .shot p { color: var(--fw-text-2); margin: 0; }
        .shot img, .pair img { display: block; width: 100%; height: auto; border: 1px solid var(--fw-line); border-radius: 14px; }
        .shot img { box-shadow: 0 24px 60px -32px color-mix(in srgb, #3d4eff 60%, transparent); }
        .pair { display: grid; gap: 1.25rem; grid-template-columns: 1fr; padding: 3rem 0; border-top: 1px solid var(--fw-line); }
        @media (min-width: 48rem) { .pair { grid-template-columns: 3fr 1fr; align-items: start; } }
        .pair figcaption { color: var(--fw-text-3); font-size: .875rem; margin-top: .5rem; }
        .pair figure { margin: 0; }
        footer { border-top: 1px solid var(--fw-line); padding: 2rem 0 3rem; color: var(--fw-text-3); font-size: .875rem; }
    </style>
</head>
<body>
    <div class="stripe"></div>
    <div class="notice">These are screenshots: Filament panels need a server, so there’s no live demo of the plugin. <a href="{$e(WIKI_DEMO)}">Try the wiki itself</a>, or <a href="{$e(REPO)}#readme">install the plugin</a>.</div>
    <div class="wrap">
        <header class="top">
            <a class="brand" href="./"><img src="ruvelo-mark.svg" alt="">Filament Wiki <span>by Ruvelo</span></a>
            <nav class="links" aria-label="Project">
                <a href="{$e(REPO)}#readme">Docs</a>
                <a href="{$e(WIKI_DEMO)}">Wiki demo</a>
                <a href="{$e(REPO)}">GitHub</a>
            </nav>
        </header>

        <main>
            <section class="hero">
                <h1>Your Laravel wiki, inside Filament.</h1>
                <p class="lede">A panel plugin for <a href="https://github.com/Ruvelo/laravel-wiki">ruvelo/laravel-wiki</a>. Edit pages with a live preview, read the history, restore old versions and see what’s missing, all from your admin panel. Same pages, same history, same links as the wiki.</p>
                <code class="install">composer require ruvelo/filament-wiki</code>
                <div class="actions">
                    <a class="button primary" href="{$e(REPO)}#readme">Read the docs</a>
                    <a class="button" href="{$e(WIKI_DEMO)}">Try the wiki demo</a>
                </div>
            </section>

{$figures}
            <section class="pair" aria-label="Dark mode and mobile">
                <figure><img src="art/screenshot-dark.png" width="1440" height="900" loading="lazy" alt="The reader in dark mode"><figcaption>Dark mode follows the panel.</figcaption></figure>
                <figure><img src="art/screenshot-mobile.png" width="390" height="844" loading="lazy" alt="The reader on a phone"><figcaption>And it works on a phone.</figcaption></figure>
            </section>
        </main>

        <footer>
            Built by <a href="https://github.com/francoisbultez">François Bultez</a> at <a href="https://github.com/Ruvelo">Ruvelo</a>, and everyone who contributes. MIT licensed.
        </footer>
    </div>
</body>
</html>

HTML;

file_put_contents($site.'/index.html', $html);
copy(__DIR__.'/ruvelo-mark.svg', $site.'/ruvelo-mark.svg');

fwrite(STDOUT, "Built the gallery into {$site}\n");
