<?php

namespace Gazelle\Manager;

class Artist extends \Gazelle\Base {
    protected const ID_KEY = 'zz_a_%d';
    protected const ROLE_KEY = 'artist_role';

    protected $role;

    protected $groupId; // torrent or request context
    protected $userId; // who is manipulating the torrents_artists or requests_artists tables

    public function __construct() {
        parent::__construct();
        if (($this->role = $this->cache->get_value(self::ROLE_KEY)) === false) {
            $this->db->prepared_query("
                SELECT slug, artist_role_id, sequence, name, title, collection
                FROM artist_role
                ORDER BY artist_role_id
            ");
            $this->role = $this->db->to_array('slug', MYSQLI_ASSOC, false);
            $this->cache->cache_value(self::ROLE_KEY, $this->role, 86400 * 30);
        }
    }

    public function findById(int $artistId): ?\Gazelle\Artist {
        $key = sprintf(self::ID_KEY, $artistId);
        $id = $this->cache->get_value($key);
        if ($id === false) {
            $id = $this->db->scalar("
                SELECT ArtistID FROM artists_group WHERE ArtistID = ?
                ", $artistId
            );
            if (!is_null($id)) {
                $this->cache->cache_value($key, $id, 0);
            }
        }
        return $id ? new \Gazelle\Artist($id) : null;
    }

    public function findByIdAndRevision(int $artistId, int $revisionId): ?\Gazelle\Artist {
        $id = $this->db->scalar("
            SELECT ArtistID
            FROM artists_group
            WHERE ArtistID = ?
                AND RevisionID = ?
            ", $artistId, $revisionId
        );
        return $id ? new \Gazelle\Artist($id, $revisionId) : null;
    }

    public function findByName(string $name): ?\Gazelle\Artist {
        return $this->findById((int)$this->db->scalar("
            SELECT ArtistID FROM artists_group WHERE Name = ?
            ", trim($name)
        ));
    }

    public function findByNameAndRevision(string $name, int $revisionId): ?\Gazelle\Artist {
        $id = $this->db->scalar("
            SELECT ArtistID
            FROM artists_group
            WHERE Name = ?
                AND RevisionID = ?
            ", trim($name), $revisionId
        );
        return $id ? new \Gazelle\Artist($id, $revisionId): null;
    }

    public function fetchArtistIdAndAliasId(string $name): ?array {
        $this->db->prepared_query('
            SELECT AliasID, ArtistID, Redirect, Name
            FROM artists_alias
            WHERE Name = ?
            ', $name
        );
        while ([$aliasId, $artistId, $redirect, $foundAliasName] = $this->db->next_record(MYSQLI_NUM, false)) {
            if (!strcasecmp($name, $foundAliasName)) {
                if ($redirect) {
                    $aliasId = $redirect;
                }
                break;
            }
        }
        return $aliasId ? [$artistId, $aliasId] : $this->create($name);
    }

    public function create($name) {
        $this->db->begin_transaction();
        $this->db->prepared_query('
            INSERT INTO artists_group (Name)
            VALUES (?)
            ', $name
        );
        $artistId = $this->db->inserted_id();

        $this->db->prepared_query('
            INSERT INTO artists_alias (ArtistID, Name)
            VALUES (?, ?)
            ', $artistId, $name
        );
        $aliasId = $this->db->inserted_id();
        $this->db->commit();

        $this->cache->increment('stats_artist_count');

        return [$artistId, $aliasId];
    }

    public function setGroupId(int $groupId) {
        $this->groupId = $groupId;
        return $this;
    }

    public function setUserId(int $userId) {
        $this->userId = $userId;
        return $this;
    }

    public function sectionName(int $sectionId): ?string {
        return (new \Gazelle\ReleaseType)->findExtendedNameById($sectionId);
    }

    public function sectionLabel(int $sectionId): string {
        return strtolower(str_replace(' ', '_', $this->sectionName($sectionId)));
    }

    public function sectionTitle(int $sectionId): string {
        return (new \Gazelle\ReleaseType)->sectionTitle($sectionId);
    }

    public function addToRequest(int $artistId, int $aliasId, int $role): int {
        $this->db->prepared_query("
            INSERT IGNORE INTO requests_artists
                   (RequestID, ArtistID, AliasID, artist_role_id, Importance)
            VALUES (?,         ?,        ?,       ?,              ?)
            ", $this->groupId, $artistId, $aliasId, $role, (string)$role
        );
        return $this->db->affected_rows();
    }
}
