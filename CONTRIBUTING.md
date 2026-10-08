# Contributing to Filament Wiki

Thanks for helping! Bug reports, docs fixes and features are all welcome.

## Before you start

- **Bugs:** [open an issue](https://github.com/Ruvelo/filament-wiki/issues/new/choose) with the steps to reproduce and your Filament version, or go straight to a pull request with a failing test.
- **Features:** open an issue first if it's more than a small change, so we can agree on the shape before you spend time on it.
- **The wiki itself** (rendering, links, history, the PHP API) lives in [ruvelo/laravel-wiki](https://github.com/Ruvelo/laravel-wiki). Changes there come first; this plugin only builds on them.
- **Security issues:** don't open an issue. See [SECURITY.md](https://github.com/Ruvelo/.github/blob/main/SECURITY.md).

## Setup

You need PHP 8.3+ and Composer. No database server: tests run on in-memory SQLite.

```bash
git clone https://github.com/<you>/filament-wiki && cd filament-wiki
composer install
composer check
```

`composer check` runs exactly what CI runs:

| Command | What it does |
|---|---|
| `composer test` | PHPUnit with Filament's Livewire testing helpers, through Orchestra Testbench |
| `composer lint` | Code style check (Laravel Pint) |
| `composer format` | Fix code style |
| `composer analyse` | Static analysis (PHPStan level 8 with Larastan) |
| `composer demo` | Build the screenshot gallery into `build/` |

CI runs the tests on PHP 8.3 and 8.4, Laravel 12 and 13, and Filament 4 and 5, with both the lowest and the latest dependencies. To try the lowest locally, copy the repo somewhere else and run `composer update --prefer-lowest` after requiring the versions you want.

## Seeing your change

`demo/screenshots.sh` seeds a Halyard admin panel, serves it with `php -S` on port 8940 and takes the screenshots in `art/`. To click around instead:

```bash
php demo/app.php seed
php -S 127.0.0.1:8940 -t build/demo/public demo/server.php
```

Then open http://127.0.0.1:8940/admin. You're signed in as Maya, an editor.

## Making a change

1. Branch from `main`.
2. Write a test that fails without your change. Feature tests live in `tests/Feature`, unit tests in `tests/Unit`.
3. Keep the public API stable: `WikiPlugin` and its options, the resource, page and widget class names, the action names (`changes`, `restore`, `write`, `openWiki`, `openOnWiki`) and the view names. If you must change one, say so in the PR.
4. Run `composer format` and `composer check`.
5. Add a line under **Unreleased** in [CHANGELOG.md](CHANGELOG.md).
6. Open the pull request. Screenshots help for anything visual: run `demo/screenshots.sh`.

## Where things live

```
src/WikiPlugin.php                       The plugin and its options
src/Resources/PageResource.php           Table, form, authorisation, rendering for the panel
src/Resources/PageResource/Pages/        List, create, edit, read and history pages
src/Widgets/                             Recent changes and Wanted pages
src/Support/                             Panel links for [[wiki links]], the wanted-pages query
resources/views/                         The diff and the few styles Filament has no class for
resources/boost/guidelines/              Laravel Boost guidelines for coding agents; keep them in step
demo/                                    The Halyard panel, screenshots, banner and gallery site
```

## Style

- `declare(strict_types=1)` everywhere, typed properties and return types.
- No `@phpstan-ignore` or baseline entries: fix the cause.
- Comments explain *why*, not *what*.
- UI is Filament's own: use Filament components, schemas and colour variables. Don't add Tailwind classes (they aren't in Filament's compiled CSS) or styles that fight the theme. Anything that must be styled goes in `resources/views/styles.blade.php`, scoped to `fi-wiki-*`.
- Writes go through the wiki's `Page::draft()` / `commit()`, never straight to the model or the tables.

By contributing you agree that your work is released under the [MIT license](LICENSE) and that you'll follow the [code of conduct](https://github.com/Ruvelo/.github/blob/main/CODE_OF_CONDUCT.md).
