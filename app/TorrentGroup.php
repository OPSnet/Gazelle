<?php

namespace Gazelle;

class TorrentGroup extends Base {

    protected $id;
    protected $revision;
    protected $group;
    protected $torrent;

    public function __construct(int $id, $revision = null) {
        parent::__construct();
        $this->id = $id;
        $this->revision = $revision;
    }

    public function setInfo(array $info) {
        [$this->group, $this->torrent] = $info;
        return $this;
    }

    public function id(): int {
        return $this->id;
    }

    public function name(): string {
        return $this->group['Name'];
    }

    public function revisionList(): array {
         $this->db->prepared_query("
            SELECT RevisionID AS revision,
                Summary       AS summary,
                Time          AS time,
                UserID        AS user_id
            FROM wiki_torrents
            WHERE PageID = ?
            ORDER BY RevisionID DESC
            ", $this->id
        );
        return $this->db->to_array('revision', MYSQLI_ASSOC, false);
    }
}
