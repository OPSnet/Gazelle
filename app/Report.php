<?php

namespace Gazelle;

class Report extends Base {
    public function openCount(): int {
        if (($count = $this->cache->get_value('num_torrent_reportsv2')) === false) {
            $count = $this->db->scalar("
                SELECT count(*)
                FROM reportsv2
                WHERE Status = 'New'
            ");
            $this->cache->cache_value('num_torrent_reportsv2', $count, 3600 * 6);
        }
        return $count;
    }

    public function otherCount(): int {
        if (($count = $this->cache->get_value('num_other_reports')) === false) {
            $count = $this->db->scalar("
                SELECT count(*)
                FROM reports
                WHERE Status = 'New'
            ");
            $this->cache->cache_value('num_other_reports', $count, 3600 * 6);
        }
        return $count;
    }

    public function forumCount(): int {
        if (($count = $this->cache->get_value('num_forum_reports')) === false) {
            $count = $this->db->scalar("
                SELECT count(*)
                FROM reports
                WHERE Status = 'New'
                    AND Type IN ('artist_comment', 'collages_comment', 'post', 'requests_comment', 'thread', 'torrents_comment')
            ");
            $this->cache->cache_value('num_forum_reports', $count, 3600 * 6);
        }
        return $count;
    }
}
