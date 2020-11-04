<?php

namespace Gazelle\Manager;

class ReportV2 extends \Gazelle\Base {

    protected $categories = [
        'master' => 'General',
        '1' => 'Music',
        '2' => 'Application',
        '3' => 'E-Book',
        '4' => 'Audiobook',
        '5' => 'E-Learning Video',
        '6' => 'Comedy',
        '7' => 'Comics',
    ];

    protected $types;

    public function types(): array {
        if (!$this->types) {
            $this->types = require('ReportV2Types.php');
        }
        return $this->types;
    }

    public function categories(): array {
        return $this->categories;
    }

    public function type(string $type): array {
        $types = $this->types();
        if (array_key_exists($type, $types['master'])) {
            return $types['master'][$type];
        }
        foreach ($this->categories as $category => $name) {
            if (array_key_exists($type, $types[$category])) {
                return $types[$category][$type];
            }
        }
        return $this->categories['master']['other'];
    }

    public function typeName(string $type): string {
        $types = $this->types();
        if (array_key_exists($type, $types['master'])) {
            return $this->categories['master'] . ' &rsaquo; ' . $types['master'][$type]['title'];
        }
        foreach ($this->categories as $category => $name) {
            if (array_key_exists($type, $types[$category])) {
                return $name . ' &rsaquo; ' . $types[$category][$type]['title'];
            }
        }
        return $this->categories['master']['other']['title'];
    }

    public function inProgressSummary(): array {
        $this->db->prepared_query("
            SELECT r.ResolverID,
                um.Username,
                count(*) AS Count
            FROM reportsv2 AS r
            INNER JOIN users_main AS um ON (um.ID = r.ResolverID)
            WHERE r.Status = 'InProgress'
            GROUP BY r.ResolverID
            ORDER By Count DESC
        ");
        return $this->db->to_array(MYSQLI_NUM);
    }

    public function newSummary(): array {
        $this->db->prepared_query("
            SELECT Type,
                count(*) AS Count
            FROM reportsv2
            WHERE Status = 'New'
            GROUP BY Type
            ORDER BY Type
        ");
        return $this->db->to_array();
    }

    public function resolvedSummary(): array {
        $this->db->prepared_query("
            SELECT r.ResolverID,
                um.Username,
                count(*) AS Reports
            FROM reportsv2 AS r
            INNER JOIN users_main AS um ON (um.ID = r.ResolverID)
            GROUP BY r.ResolverID
            ORDER BY Reports DESC
        ");
        return $this->db->to_array(MYSQLI_NUM);
    }

    protected function resolvedLastInterval(string $interval): array {
        $this->db->prepared_query("
            SELECT r.ResolverID,
                um.Username,
                count(*) AS Reports
            FROM reportsv2 AS r
            INNER JOIN users_main AS um ON (um.ID = r.ResolverID)
            WHERE r.LastChangeTime > now() - INTERVAL $interval
            GROUP BY r.ResolverID
            ORDER BY Reports DESC
        ");
        return $this->db->to_array(MYSQLI_NUM);
    }

    public function resolvedLastDay(): array {
        return $this->resolvedLastInterval('1 DAY');
    }

    public function resolvedLastWeek(): array {
        return $this->resolvedLastInterval('1 WEEK');
    }

    public function resolvedLastMonth(): array {
        return $this->resolvedLastInterval('1 MONTH');
    }

    /**
     * How many open reports exist for this group
     *
     * @param int Group ID
     * @return number of reports
     */
    public function totalReportsGroup(int $groupId): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.GroupID = ?
            ", $groupId
        );
    }

    /**
     * How many open reports exist for this uploader
     *
     * @param int User ID of uploader
     * @return number of reports
     */
    public function totalReportsUploader(int $userId): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.UserID = ?
            ", $userId
        );
    }
}
