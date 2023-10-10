<?php

namespace Gazelle\Top10;

class Tag extends \Gazelle\Base {
    public function getTopUsedTags(int $limit): array {
        $topUsedTags = self::$cache->get_value('topusedtag_' . $limit);
        if ($topUsedTags === false) {
            $topUsedTags = self::$db->prepared_query("
                SELECT t.ID,
                    t.Name,
                    count(tt.GroupID) AS Uses,
                    sum(tt.PositiveVotes - 1) AS PositiveVotes,
                    sum(tt.NegativeVotes - 1) AS NegativeVotes
                FROM tags AS t
                INNER JOIN torrents_tags AS tt ON (tt.TagID = t.ID)
                GROUP BY tt.TagID
                ORDER BY Uses DESC, t.name
                LIMIT ?
                ", $limit
            );
            $topUsedTags = self::$db->to_array(); // FIXME
            self::$cache->cache_value('topusedtag_' . $limit, $topUsedTags, 3600 * 12);
        }
        return $topUsedTags;
    }

    public function getTopRequestTags(int $limit): array {
        $topRequestTags = self::$cache->get_value('toprequesttag_' . $limit);
        if ($topRequestTags === false) {
            self::$db->prepared_query("
                SELECT t.ID,
                    t.Name,
                    count(r.RequestID) AS Uses,
                    '',''
                FROM tags AS t
                INNER JOIN requests_tags AS r ON (r.TagID = t.ID)
                GROUP BY r.TagID
                ORDER BY Uses DESC, t.name
                LIMIT ?
                ", $limit
            );
            $topRequestTags = self::$db->to_array();
            self::$cache->cache_value('toprequesttag_' . $limit, $topRequestTags, 3600 * 12);
        }
        return $topRequestTags;
    }

    public function getTopVotedTags(int $limit): array {
        $topVotedTags = self::$cache->get_value('topvotedtag_' . $limit);
        if ($topVotedTags === false) {
            $topVotedTags = self::$db->prepared_query("
                SELECT t.ID,
                    t.Name,
                    count(tt.GroupID) AS Uses,
                    sum(tt.PositiveVotes - 1) AS PositiveVotes,
                    sum(tt.NegativeVotes - 1) AS NegativeVotes
                FROM tags AS t
                INNER JOIN torrents_tags AS tt ON (tt.TagID = t.ID)
                GROUP BY tt.TagID
                ORDER BY PositiveVotes DESC, t.name
                LIMIT ?
                ", $limit
            );
            $topVotedTags = self::$db->to_array();
            self::$cache->cache_value('topvotedtag_' . $limit, $topVotedTags, 3600 * 12);
        }
        return $topVotedTags;
    }
}
