<?php

namespace Gazelle\ArtistRole;

class TGroup extends \Gazelle\ArtistRole {

    protected array $roleList;
    protected array $idList;

    public function roleList(): array {
        if (!isset($this->roleList)) {
            $this->init();
        }
        return $this->roleList;
    }

    public function idList(): array {
        if (!isset($this->idList)) {
            $this->init();
        }
        return $this->idList;
    }

    protected function init() {
        $this->db->prepared_query("
            SELECT ta.Importance,
                ta.ArtistID,
                aa.Name,
                ta.AliasID
            FROM torrents_artists AS ta
            INNER JOIN artists_alias AS aa ON (ta.AliasID = aa.AliasID)
            WHERE ta.GroupID = ?
            ORDER BY ta.GroupID, ta.Importance ASC, aa.Name ASC
            ", $this->id
        );
        $map = [
            1 => 'main',
            2 => 'guest',
            3 => 'remixer',
            4 => 'composer',
            5 => 'conductor',
            6 => 'dj',
            7 => 'producer',
            8 => 'arranger',
        ];
        $this->roleList = [
            'main'      => [],
            'guest'     => [],
            'remixer'   => [],
            'composer'  => [],
            'conductor' => [],
            'dj'        => [],
            'producer'  => [],
            'arranger'  => [],
        ];
        $this->idList = [];
        while ([$role, $artistId, $artistName, $aliasId] = $this->db->next_record(MYSQLI_NUM, false)) {
            $this->idList[$role][] = [
                'id'      => $artistId,
                'name'    => $artistName,
            ];
            $this->roleList[$map[$role]][] = [
                'id'      => $artistId,
                'aliasid' => $aliasId,
                'name'    => $artistName,
            ];
        }
    }
}
