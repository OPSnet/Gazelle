<?php

namespace Gazelle\Top10;

class Torrent extends \Gazelle\Base {
    private string $baseQuery = "
        SELECT
            t.ID,
            g.ID,
            ((t.Size * tls.Snatched) + (t.Size * 0.5 * tls.Leechers)) AS Data
        FROM torrents AS t
        INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
        INNER JOIN torrents_group AS g ON (g.ID = t.GroupID)
        %s
        GROUP BY %s
        ORDER BY %s
        LIMIT %s";

    public function __construct(
        protected readonly array $formats,
        protected readonly \Gazelle\User $viewer,
    ) {}

    public function getTopTorrents($getParameters, $details = 'all', $limit = 10): array {
        $cacheKey = 'top10_v2_' . $details . '_' . md5(implode('', $getParameters)) . '_' . $limit;
        $topTorrents = self::$cache->get_value($cacheKey);

        if ($topTorrents !== false) {
            return $topTorrents;
        }
        if (self::$cache->get_value("{$cacheKey}_lock")) {
            return [];
        }
        self::$cache->cache_value("{$cacheKey}_lock", true, 3600);

        $where = [];
        $anyTags = isset($getParameters['anyall']) && $getParameters['anyall'] == 'any';
        if (isset($getParameters['format'])) {
            $where[] = $this->formatWhere($getParameters['format']);
        }
        if (isset($getParameters['tags'])) {
            $where[] = $this->tagWhere($getParameters['tags'], $anyTags);
        }

        $where[] = $this->freeleechWhere($getParameters);
        $where[] = $this->detailsWhere($details);

        $where[] = ["parameters" => null, "where" => "tls.Seeders > 0"];

        $whereFilter = fn($value) => $value["where"] ?? null;

        $parameterFilter = fn($value) => $value["parameters"] ?? null;

        $filteredWhere = array_filter(array_map($whereFilter, $where));
        $parameters = $this->flatten(array_filter(array_map($parameterFilter, $where)));

        $innerQuery = '';
        $joinParameters = [];

        if (!empty($getParameters['excluded_artists'])) {
            [$clause, $artists] = $this->excludedArtistClause($getParameters['excluded_artists']);
            $innerQuery .= $clause;
            $joinParameters[] = $artists;
            $filteredWhere[] = "ta.ArtistCount IS NULL";
        }

        if (count($joinParameters)) {
            $joinParameters = $this->flatten($joinParameters);
            $parameters = array_merge($joinParameters, $parameters);
        }

        $innerQuery .= " WHERE " . implode(" AND ", $filteredWhere);
        $innerQuery = $innerQuery . (isset($getParameters['groups']) && $getParameters['groups'] == 'show' ? ' GROUP BY g.ID ' : '');
        $this->orderBy($details);

        $query = sprintf($this->baseQuery,
            $innerQuery,
            ($getParameters['groups'] ?? 'hide') == 'show' ? 'g.ID' : 't.ID, g.ID',
            $this->orderBy($details) . ' DESC',
            $limit
        );

        self::$db->prepared_query($query, ...$parameters);
        $topTorrents = self::$db->to_array();

        self::$cache->cache_value($cacheKey, $topTorrents, 3600 * 6);
        self::$cache->delete_value("{$cacheKey}_lock");
        return $topTorrents;
    }

    public function showFreeleechTorrents($freeleechParameters): bool {
        if (isset($freeleechParameters)) {
            return $freeleechParameters == 'hide';
        }
        return (bool)$this->viewer->option('DisableFreeTorrentTop10');
    }

    private function orderBy($details): string {
        return match ($details) {
            'snatched' => 'tls.Snatched',
            'seeded'   => 'tls.Seeders',
            'data'     => 'Data',
            default    => '(tls.Seeders + tls.Leechers)',
        };
    }

    private function detailsWhere(string $detailsParameters): array {
        return match ($detailsParameters) {
            'day'   => ["parameters" => null, "where" => "t.created > now() - INTERVAL 1 DAY"],
            'week'  => ["parameters" => null, "where" => "t.created > now() - INTERVAL 1 WEEK"],
            'month' => ["parameters" => null, "where" => "t.created > now() - INTERVAL 1 MONTH"],
            'year'  => ["parameters" => null, "where" => "t.created > now() - INTERVAL 1 YEAR"],
            default => [],
        };
    }

    private function excludedArtistClause(string $artistParameter): array {
        $artists = preg_split('/\r\n|\r|\n/', trim($artistParameter));
        if ($artists) {
            return [
                " LEFT JOIN (
                    SELECT COUNT(*) AS ArtistCount, ta.GroupID
                    FROM torrents_artists AS ta
                    INNER JOIN artists_alias AS aa ON (ta.AliasID = aa.AliasID)
                    WHERE ta.Importance != '2' AND aa.Name IN (" . placeholders($artists) . ")
                    GROUP BY ta.GroupID
                ) AS ta ON (g.ID = ta.GroupID)",
                array_map('trim', $artists)
            ];
        }
        return ['', []];
    }

    private function formatWhere(string $formatParameters): array {
        if (in_array($formatParameters, $this->formats)) {
            return ["parameters" => $formatParameters, "where" => "t.Format = ?"];
        }
        return [];
    }

    private function freeleechWhere(array $getParameters): array {
        return ($getParameters['freeleech'] ?? '') == 'hide' || (bool)$this->viewer->option('DisableFreeTorrentTop10')
            ? ["parameters" => null, "where" => "t.FreeTorrent = '0'"]
            : [];
    }

    private function tagWhere(string $getParameters, bool $any = false): array {
        if (!empty($getParameters)) {
            $tags = explode(',', trim($getParameters));
            $replace = fn($tag) => preg_replace('/[^a-z0-9.]/', '', $tag);
            $tags = array_map($replace, $tags);
            $tags = array_filter($tags);

            // This is to make the prepared query work.

            $where = implode(' OR ', array_fill(0,  count($tags), "t.Name = ?"));
            $clause = "
        g.ID IN (
            SELECT tt.GroupID
            FROM torrents_tags tt
            INNER JOIN tags t ON (t.ID = tt.TagID)
            WHERE $where
            GROUP BY tt.GroupID
            HAVING count(*) >= ?
        )";
            $tags[] = $any ? 1 : count($tags);
            return ['parameters' => $tags, 'where' => $clause];
        }

        return [];
    }

    private function flatten(array $array): array {
        $return = [];
        array_walk_recursive($array, function ($a) use (&$return) { $return[] = $a; });
        return $return;
    }
}
