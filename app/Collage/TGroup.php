<?php

namespace Gazelle\Collage;

class TGroup extends AbstractCollage {

    protected array $groupIds = [];
    protected array $sequence = [];
    protected array $torrentTags = [];

    public function entryTable(): string { return 'collages_torrents'; }
    public function entryColumn(): string { return 'GroupID'; }

    public function groupIdList(): array {
        return $this->groupIds;
    }

    public function torrentTagList(): array {
        return $this->torrentTags;
    }

    public function load(): int {
        // in case of a tie in tag usage counts, order by first past the post
        self::$db->prepared_query("
            SELECT count(*) AS \"count\",
                tag.name AS tag
            FROM collages_torrents   AS ct
            INNER JOIN torrents_tags AS tt USING (groupid)
            INNER JOIN tags          AS tag ON (tag.id = tt.tagid)
            WHERE ct.collageid = ?
            GROUP BY tag.name
            ORDER BY 1 DESC, ct.AddedOn
            ", $this->holder->id()
        );
        $this->torrentTags = self::$db->to_array('tag', MYSQLI_ASSOC, false);

        $order = $this->holder->sortNewest() ? 'DESC' : 'ASC';
        self::$db->prepared_query("
            SELECT ct.GroupID,
                ct.UserID,
                ct.Sort as sequence
            FROM collages_torrents AS ct
            INNER JOIN torrents_group AS tg ON (tg.ID = ct.GroupID)
            WHERE ct.CollageID = ?
            ORDER BY ct.Sort $order
            ", $this->holder->id()
        );
        $groupContribIds = self::$db->to_array('GroupID', MYSQLI_ASSOC, false);
        $groupIds = array_keys($groupContribIds);

        $tgMan = new \Gazelle\Manager\TGroup;
        foreach ($groupIds as $groupId) {
            $tgroup = $tgMan->findById($groupId);
            if (is_null($tgroup)) {
                continue;
            }
            $this->groupIds[] = $groupId;
            $artistRole = $tgroup->artistRole();
            if ($artistRole) {
                $roleList = $artistRole->roleList();
                foreach (['main', 'conductor', 'composer', 'dj'] as $role) {
                    foreach ($roleList[$role] as $artistInfo) {
                        if (!isset($this->artists[$artistInfo['id']])) {
                            $this->artists[$artistInfo['id']] = [
                                'count' => 0,
                                'id'    => $artistInfo['id'],
                                'name'  => $artistInfo['name'],
                            ];
                        }
                        $this->artists[$artistInfo['id']]['count']++;
                    }
                }
            }

            $contribUserId = $groupContribIds[$groupId]['UserID'];
            if (!isset($this->contributors[$contribUserId])) {
                $this->contributors[$contribUserId] = 0;
            }
            $this->contributors[$contribUserId]++;
            $this->sequence[$groupId] = $groupContribIds[$groupId]['sequence'];
        }
        uasort($this->artists, fn ($x, $y) => $y['count'] <=> $x['count']);
        arsort($this->contributors);
        return count($this->groupIds);
    }

    public function entryList(): array {
        return $this->groupIds;
    }

    public function sequence(int $entryId): int {
        return $this->sequence[$entryId] ?? 0;
    }

    protected function flushTarget(int $tgroupId): void {
        $this->flushAll([
            "torrent_collages_$tgroupId",
            "torrent_collages_personal_$tgroupId",
            "torrents_details_$tgroupId"
        ]);
    }

    public function remove(): int {
        self::$db->prepared_query("
            SELECT GroupID FROM collages_torrents WHERE CollageID = ?
            ", $this->id
        );
        self::$cache->deleteMulti(array_merge(...array_map(
            fn ($id) => ["torrents_details_$id", "torrent_collages_$id", "torrent_collages_personal_$id"],
            self::$db->collect(0, false)
        )));
        if (!$this->holder->isPersonal()) {
            $rows = parent::remove();
        } else {
            (new \Gazelle\Manager\Comment)->remove('collages', $this->id);
            self::$db->prepared_query("
                DELETE FROM collages_torrents WHERE CollageID = ?
                ", $this->id
            );
            self::$db->prepared_query("
                DELETE FROM collages WHERE ID = ?
                ", $this->id
            );
            $rows = self::$db->affected_rows();
        }
        $this->flushAll();
        return $rows;
    }
}
