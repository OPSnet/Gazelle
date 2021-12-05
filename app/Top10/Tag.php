<?php

namespace Gazelle\Top10;

class Tag extends \Gazelle\Base {

    public function getTopUsedTags($limit) {
        if (!$topUsedTags = self::$cache->get_value('topusedtag_' . $limit)) {
            $topUsedTags = self::$db->prepared_query("
                SELECT
                    t.ID,
                    t.Name,
                    COUNT(tt.GroupID) AS Uses,
                    SUM(tt.PositiveVotes - 1) AS PositiveVotes,
                    SUM(tt.NegativeVotes - 1) AS NegativeVotes
                FROM tags AS t
                    JOIN torrents_tags AS tt ON (tt.TagID = t.ID)
                GROUP BY tt.TagID
                ORDER BY Uses DESC, t.name
                LIMIT ?", $limit);

            $topUsedTags = self::$db->to_array();
            self::$cache->cache_value('topusedtag_' . $limit, $topUsedTags, 3600 * 12);
        }

        return $topUsedTags;
    }

    public function getTopRequestTags($limit) {
        if (!$topRequestTags = self::$cache->get_value('toprequesttag_' . $limit)) {
            self::$db->prepared_query("
                SELECT
                    t.ID,
                    t.Name,
                    COUNT(r.RequestID) AS Uses,
                    '',''
                FROM tags AS t
                    JOIN requests_tags AS r ON (r.TagID = t.ID)
                GROUP BY r.TagID
                ORDER BY Uses DESC, t.name
                LIMIT ?", $limit);

            $topRequestTags = self::$db->to_array();
            self::$cache->cache_value('toprequesttag_' . $limit, $topRequestTags, 3600 * 12);
        }

        return $topRequestTags;
    }

    public function getTopVotedTags($limit) {
        if (!$topVotedTags = self::$cache->get_value('topvotedtag_' . $limit)) {
            $topVotedTags = self::$db->prepared_query("
                SELECT
                    t.ID,
                    t.Name,
                    COUNT(tt.GroupID) AS Uses,
                    SUM(tt.PositiveVotes - 1) AS PositiveVotes,
                    SUM(tt.NegativeVotes - 1) AS NegativeVotes
                FROM tags AS t
                    JOIN torrents_tags AS tt ON (tt.TagID = t.ID)
                GROUP BY tt.TagID
                ORDER BY PositiveVotes DESC, t.name
                LIMIT ?", $limit);

            $topVotedTags = self::$db->to_array();
            self::$cache->cache_value('topvotedtag_' . $limit, $topVotedTags, 3600 * 12);
        }

        return $topVotedTags;
    }
}
