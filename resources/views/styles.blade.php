<style>
    /* ruvelo/filament-wiki: only what Filament's theme has no class for. */
    .fi-wiki-prose .heading-permalink { margin-inline-start: .4em; color: var(--gray-400); font-weight: 400; text-decoration: none; opacity: 0; }
    .fi-wiki-prose :is(h1, h2, h3, h4, h5, h6):hover .heading-permalink,
    .fi-wiki-prose .heading-permalink:focus-visible { opacity: 1; }
    .fi-wiki-prose a.wiki-link--new { color: var(--danger-600); text-decoration-color: var(--danger-400); text-decoration-style: dashed; }
    .dark .fi-wiki-prose a.wiki-link--new { color: var(--danger-400); }
    .fi-wiki-prose .wiki-toc { list-style: none; padding-inline-start: 0; }
    .fi-wiki-empty, .fi-wiki-muted { color: var(--gray-500); font-style: italic; }
    .fi-wiki-preview .fi-section-content-ctn { max-height: 40rem; overflow-y: auto; }

    .fi-wiki-list { display: grid; gap: .375rem; font-size: .875rem; line-height: 1.4; }
    .fi-wiki-list a { color: var(--gray-700); }
    .fi-wiki-list a:hover, .fi-wiki-list a:focus-visible { color: var(--primary-600); text-decoration: underline; text-underline-offset: 3px; }
    .fi-wiki-list .fi-wiki-sub { padding-inline-start: .875rem; }
    .dark .fi-wiki-list a { color: var(--gray-300); }
    .dark .fi-wiki-list a:hover { color: var(--primary-400); }

    .fi-wiki-changes { display: grid; gap: .75rem; font-size: .875rem; }
    .fi-wiki-changes-head { display: flex; gap: .5rem; font-weight: 600; color: var(--gray-950); }
    .dark .fi-wiki-changes-head { color: var(--gray-50); }
    .fi-wiki-add { color: var(--success-600); }
    .fi-wiki-del { color: var(--danger-600); }
    .fi-wiki-diff { overflow: hidden; border: 1px solid var(--gray-200); border-radius: .5rem; font: .8125rem/1.6 var(--mono-font-family, ui-monospace, SFMono-Regular, Menlo, monospace); }
    .fi-wiki-diff > div { min-height: 1.6em; padding: 0 .75rem; white-space: pre-wrap; overflow-wrap: anywhere; }
    .fi-wiki-diff-add { background: color-mix(in oklab, var(--success-500) 12%, transparent); color: var(--success-800); }
    .fi-wiki-diff-del { background: color-mix(in oklab, var(--danger-500) 12%, transparent); color: var(--danger-800); }
    .fi-wiki-diff-eq { color: var(--gray-500); }
    .fi-wiki-diff-skip { padding-block: .25rem !important; background: var(--gray-50); color: var(--gray-500); font-family: inherit; font-size: .75rem; }
    .dark .fi-wiki-diff { border-color: var(--gray-700); }
    .dark .fi-wiki-diff-add { color: var(--success-300); }
    .dark .fi-wiki-diff-del { color: var(--danger-300); }
    .dark .fi-wiki-diff-skip { background: var(--gray-800); }
</style>
