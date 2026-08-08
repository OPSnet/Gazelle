<?php

declare(strict_types=1);

namespace Gazelle\Top10;

class Torrent extends \Gazelle\Base {
    private string $baseQuery = "
        SELECT
            t.ID AS torrent_id,
            g.ID AS tgroup_id,
            ((t.Size * tls.Snatched) + (t.Size * 0.5 * tls.Leechers)) AS score
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

    public function getTopTorrents(
        array  $getParameters,
        string $details = 'all',
        int    $limit   = 10,
    ): array {
        // Should this cache shape change, ensure Top10Test.php follow suit.
        $cacheKey = "T10_{$details}_{$limit}_"
            . trim(signature(implode('|', $getParameters), TOP10_SALT), '=');
        $topTorrents = self::$cache->get_value($cacheKey);
        if ($topTorrents !== false) {
            return $topTorrents;
        }
        if (self::$cache->get_value("{$cacheKey}_lock")) {
            return [];
        }
        self::$cache->cache_value("{$cacheKey}_lock", true, 3600);

        $where = [];
        if (isset($getParameters['format'])) {
            $where[] = $this->formatWhere($getParameters['format']);
        }
        if (isset($getParameters['tags'])) {
            $where[] = $this->tagWhere(
                trim($getParameters['tags']),
                ($getParameters['anyall'] ?? '') == 'any',
            );
        }

        $where[] = $this->freeleechWhere($getParameters);
        $where[] = $this->detailsWhere($details);
        $where[] = ["parameters" => null, "where" => "tls.Seeders > 0"];

        $filteredWhere = array_filter(
            array_map(fn ($value) => $value["where"] ?? null, $where),
            fn ($v) => !is_null($v),
        );
        $parameters = $this->flatten(
            array_filter(
                array_map(fn ($value) => $value["parameters"] ?? null, $where),
                fn ($v) => !is_null($v),
            )
        );

        $innerQuery = '';
        $joinParameters = [];

        if (isset($getParameters['excluded_artists'])) {
            [$clause, $artists] = $this->excludedArtistClause($getParameters['excluded_artists']);
            $innerQuery .= $clause;
            $joinParameters[] = $artists;
            $filteredWhere[] = "ta.ArtistCount IS NULL";
        }

        if (count($joinParameters)) {
            $joinParameters = $this->flatten($joinParameters);
            $parameters = array_merge($joinParameters, $parameters);
        }

        $innerQuery .= " WHERE " . implode(" AND ", $filteredWhere)
            . (($getParameters['groups'] ?? '') == 'show' ? ' GROUP BY g.ID ' : '');

        $query = sprintf($this->baseQuery,
            $innerQuery,
            ($getParameters['groups'] ?? 'hide') == 'show' ? 'g.ID' : 't.ID, g.ID',
            $this->orderBy($details),
            $limit
        );

        self::$db->prepared_query($query, ...$parameters);
        $topTorrents = [];
        foreach (self::$db->to_array(false, MYSQLI_ASSOC) as $row) {
            $row['score'] = (float)$row['score']; // wtf
            $topTorrents[] = $row;
        }

        self::$cache->cache_value($cacheKey, $topTorrents, 3600 * 6);
        self::$cache->delete_value("{$cacheKey}_lock");
        return $topTorrents;
    }

    protected function orderBy(string $details): string {
        return match ($details) {
            'snatched' => 'tls.Snatched DESC',
            'seeded'   => 'tls.Seeders DESC',
            'data'     => 'score DESC',
            default    => '(tls.Seeders + tls.Leechers) DESC',
        };
    }

    protected function detailsWhere(string $detailsParameters): array {
        return match ($detailsParameters) {
            'day'   => ["parameters" => null, "where" => "t.created > now() - INTERVAL 1 DAY"],
            'week'  => ["parameters" => null, "where" => "t.created > now() - INTERVAL 1 WEEK"],
            'month' => ["parameters" => null, "where" => "t.created > now() - INTERVAL 1 MONTH"],
            'year'  => ["parameters" => null, "where" => "t.created > now() - INTERVAL 1 YEAR"],
            default => [],
        };
    }

    protected function excludedArtistClause(string $artistParameter): array {
        $artists = preg_split('/\r\n?|\n/', trim($artistParameter));
        if ($artists) {
            return [
                " LEFT JOIN (
                    SELECT COUNT(*) AS ArtistCount, ta.GroupID
                    FROM torrents_artists    ta
                    INNER JOIN artists_alias aa ON (ta.AliasID = aa.AliasID)
                    INNER JOIN artist_role   ar USING (artist_role_id)
                    WHERE ar.slug != 'guest' AND aa.Name IN (" . placeholders($artists) . ")
                    GROUP BY ta.GroupID
                ) AS ta ON (g.ID = ta.GroupID)",
                array_map(trim(...), $artists)
            ];
        }
        return ['', []];
    }

    protected function formatWhere(string $formatParameters): array {
        if (in_array($formatParameters, $this->formats)) {
            return ["parameters" => $formatParameters, "where" => "t.Format = ?"];
        }
        return [];
    }

    protected function freeleechWhere(array $getParameters): array {
        return ($getParameters['freeleech'] ?? '') === 'hide' || (bool)$this->viewer->option('DisableFreeTorrentTop10')
            ? ["parameters" => null, "where" => "t.FreeTorrent = '0'"]
            : [];
    }

    protected function tagWhere(string $getParameters, bool $any = false): array {
        if ($getParameters === '') {
            return [];
        }
        $tags = array_filter(
            array_map(
                fn ($t) => preg_replace('/[^a-z0-9.]/', '', $t),
                explode(',', $getParameters),
            ),
            fn ($t) => strlen($t) > 0,
        );

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

    protected function flatten(array $array): array {
        $return = [];
        array_walk_recursive(
            $array,
            function ($a) use (&$return) { $return[] = $a; }
        );
        return $return;
    }
}
