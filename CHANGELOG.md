# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] - 2026-10-08

### Added

- `WikiPlugin` for Filament 4 and 5 panels, with fluent options: navigation group, label, icon and sort; the URL slug; who can edit (`canEdit()`, the wiki's `wiki-edit` gate by default); the reader page; links to the public wiki; and each dashboard widget.
- A Pages resource: title, last edited, last editor, revision and backlink counts; search over titles and text; sorting; delete and bulk delete with confirmation.
- Create and edit pages with Filament's Markdown editor beside a live preview rendered by the wiki itself, so `[[links]]` and red links show as you type. An edit summary goes into the history.
- Saving only through the wiki's `Page::draft()` and `commit()`: every save records a revision, re-indexes links and fires `PageSaved`. The editor remembers the revision it started from and refuses to overwrite a newer save, with a clear notification and the editor's text kept.
- A History page: every revision with its author and summary, a line diff for each, and Restore, which saves the old version as a new revision.
- A Reader page: the page rendered with the wiki's renderer, links kept inside the panel, red links opening the create form with the title filled in, an outline, and what links here.
- Dashboard widgets: Recent changes, and Wanted pages (red links, most linked first, with a button to write them).
- Laravel Boost guidelines (`resources/boost/guidelines/core.blade.php`).
- Screenshots of a Halyard admin panel, and a gallery site on GitHub Pages.

[Unreleased]: https://github.com/Ruvelo/filament-wiki/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/Ruvelo/filament-wiki/releases/tag/v1.0.0
