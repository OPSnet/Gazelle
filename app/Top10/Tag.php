<?php

namespace Gazelle\Top10;

class Tag extends \Gazelle\Base {

    public function getTopUsedTags($limit) {
        if (!$topUsedTags = $this->cache->get_value('topusedtag_' . $limit)) {
            $topUsedTags = $this->db->prepared_query("
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

            $topUsedTags = $this->db->to_array();
            $this->cache->cache_value('topusedtag_' . $limit, $topUsedTags, 3600 * 12);
        }

        return $topUsedTags;
    }

    public function getTopRequestTags($limit) {
        if (!$topRequestTags = $this->cache->get_value('toprequesttag_' . $limit)) {
            $this->db->prepared_query("
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

            $topRequestTags = $this->db->to_array();
            $this->cache->cache_value('toprequesttag_' . $limit, $topRequestTags, 3600 * 12);
        }

        return $topRequestTags;
    }

    public function getTopVotedTags($limit) {
        if (!$topVotedTags = $this->cache->get_value('topvotedtag_' . $limit)) {
            $topVotedTags = $this->db->prepared_query("
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

            $topVotedTags = $this->db->to_array();
            $this->cache->cache_value('topvotedtag_' . $limit, $topVotedTags, 3600 * 12);
        }

        return $topVotedTags;
    }
}
