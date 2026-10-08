<?php

declare(strict_types=1);

namespace Ruvelo\FilamentWiki\Support;

use Ruvelo\Wiki\Models\Page;
use Ruvelo\Wiki\Support\WikiLinks;

/**
 * Red links, counted: pages that are linked to but don't exist yet.
 */
final class WantedPages
{
    /**
     * Most-linked first, keyed by slug.
     *
     * @return array<string, array{slug: string, title: string, links: int}>
     */
    public static function top(int $limit = 10): array
    {
        $page = new Page;
        $links = Page::linksTable();

        $rows = $page->getConnection()->table($links)
            ->select('target_slug')
            ->selectRaw('count(distinct page_id) as links')
            ->whereNotIn('target_slug', Page::query()->select('slug')->toBase())
            ->groupBy('target_slug')
            ->orderByDesc('links')
            ->orderBy('target_slug')
            ->limit($limit)
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row->target_slug] = (int) $row->links;
        }

        $titles = self::titlesAsWritten(array_keys($counts));

        $wanted = [];
        foreach ($counts as $slug => $links) {
            $slug = (string) $slug;
            $wanted[$slug] = ['slug' => $slug, 'title' => $titles[$slug] ?? PanelLinks::titleFor($slug), 'links' => $links];
        }

        return $wanted;
    }

    /**
     * Titles as the linking pages spell them: [[On-call rota]] rather than
     * "On call rota" guessed from the slug.
     *
     * @param  list<int|string>  $slugs
     * @return array<string, string>
     */
    private static function titlesAsWritten(array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $bodies = Page::query()
            ->whereIn('id', (new Page)->getConnection()->table(Page::linksTable())->whereIn('target_slug', $slugs)->select('page_id'))
            ->pluck('body');

        $titles = [];
        foreach ($bodies as $body) {
            preg_match_all('/'.WikiLinks::PATTERN.'/u', (string) $body, $matches);
            foreach ($matches[1] as $target) {
                $title = WikiLinks::parseTarget($target)['page'];
                $titles[Page::slugFor($title)] ??= $title;
            }
        }

        return array_intersect_key($titles, array_flip($slugs));
    }
}
