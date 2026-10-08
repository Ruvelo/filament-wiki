<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Support;

use Closure;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The wiki renders [[links]] as URLs on the public wiki. Inside a panel they
 * should stay in the panel: this rewrites them, given where a page lives
 * and where a missing one gets created.
 */
final class PanelLinks
{
    private const LINK = '/<a class="(wiki-link(?: wiki-link--new)?)" href="([^"]*)"/u';

    /**
     * @param  Closure(string): ?string  $pageUrl  slug to URL; null keeps the public link
     * @param  Closure(string): ?string  $createUrl  title to URL; null keeps the public link
     */
    public static function rewrite(string $html, Closure $pageUrl, Closure $createUrl): string
    {
        if (! Route::has('wiki.home') || ! str_contains($html, 'wiki-link')) {
            return $html;
        }

        $base = rtrim(route('wiki.home'), '/').'/';

        return preg_replace_callback(self::LINK, function (array $match) use ($base, $pageUrl, $createUrl): string {
            $href = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5);

            if (! str_starts_with($href, $base)) {
                return $match[0];
            }

            $target = self::target(substr($href, strlen($base)));
            if ($target === null) {
                return $match[0];
            }

            $isNew = str_contains($match[1], 'wiki-link--new');
            $url = $isNew
                ? $createUrl($target['title'])
                : (($page = $pageUrl($target['slug'])) === null ? null : $page.$target['fragment']);

            return $url === null ? $match[0] : '<a class="'.$match[1].'" href="'.e($url).'"';
        }, $html) ?? $html;
    }

    /**
     * @return array{slug: string, title: string, fragment: string}|null
     */
    private static function target(string $path): ?array
    {
        // A red link for editors: the wiki's create form, titled.
        if (str_starts_with($path, '_/new')) {
            parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
            $title = is_string($query['title'] ?? null) ? trim($query['title']) : '';

            return $title === '' ? null : ['slug' => Str::slug($title, '-', null), 'title' => $title, 'fragment' => ''];
        }

        [$slug, $fragment] = array_pad(explode('#', $path, 2), 2, null);
        $slug = rawurldecode((string) $slug);

        if ($slug === '' || str_contains($slug, '/')) {
            return null;
        }

        return [
            'slug' => $slug,
            'title' => self::titleFor($slug),
            'fragment' => $fragment === null ? '' : '#'.$fragment,
        ];
    }

    /**
     * A readable title for a page that doesn't exist yet, as the wiki makes one.
     */
    public static function titleFor(string $slug): string
    {
        return Str::ucfirst(str_replace('-', ' ', $slug));
    }
}
