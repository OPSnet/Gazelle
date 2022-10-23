<?php

namespace Gazelle\Manager;

class Artist extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_a_%d';
    protected const ROLE_KEY = 'artist_role';

    protected $role;

    protected $groupId; // torrent or request context
    protected $userId; // who is manipulating the torrents_artists or requests_artists tables

    public function __construct() {
        if (($this->role = self::$cache->get_value(self::ROLE_KEY)) === false) {
            self::$db->prepared_query("
                SELECT slug, artist_role_id, sequence, name, title, collection
                FROM artist_role
                ORDER BY artist_role_id
            ");
            $this->role = self::$db->to_array('slug', MYSQLI_ASSOC, false);
            self::$cache->cache_value(self::ROLE_KEY, $this->role, 86400 * 30);
        }
    }

    public function findById(int $artistId): ?\Gazelle\Artist {
        $key = sprintf(self::ID_KEY, $artistId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ArtistID FROM artists_group WHERE ArtistID = ?
                ", $artistId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\Artist($id) : null;
    }

    public function findByIdAndRevision(int $artistId, int $revisionId): ?\Gazelle\Artist {
        $id = self::$db->scalar("
            SELECT ArtistID
            FROM artists_group
            WHERE ArtistID = ?
                AND RevisionID = ?
            ", $artistId, $revisionId
        );
        return $id ? new \Gazelle\Artist($id, $revisionId) : null;
    }

    public function findByName(string $name): ?\Gazelle\Artist {
        return $this->findById((int)self::$db->scalar("
            SELECT ArtistID FROM artists_group WHERE Name = ?
            ", trim($name)
        ));
    }

    public function findByNameAndRevision(string $name, int $revisionId): ?\Gazelle\Artist {
        $id = self::$db->scalar("
            SELECT ArtistID
            FROM artists_group
            WHERE Name = ?
                AND RevisionID = ?
            ", trim($name), $revisionId
        );
        return $id ? new \Gazelle\Artist($id, $revisionId): null;
    }

    public function fetchArtistIdAndAliasId(string $name): ?array {
        self::$db->prepared_query('
            SELECT AliasID, ArtistID, Redirect, Name
            FROM artists_alias
            WHERE Name = ?
            ', $name
        );
        while ([$aliasId, $artistId, $redirect, $foundAliasName] = self::$db->next_record(MYSQLI_NUM, false)) {
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
        self::$db->begin_transaction();
        self::$db->prepared_query('
            INSERT INTO artists_group (Name)
            VALUES (?)
            ', $name
        );
        $artistId = self::$db->inserted_id();

        self::$db->prepared_query('
            INSERT INTO artists_alias (ArtistID, Name)
            VALUES (?, ?)
            ', $artistId, $name
        );
        $aliasId = self::$db->inserted_id();
        self::$db->commit();

        self::$cache->increment('stats_artist_count');

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
        self::$db->prepared_query("
            INSERT IGNORE INTO requests_artists
                   (RequestID, ArtistID, AliasID, artist_role_id, Importance)
            VALUES (?,         ?,        ?,       ?,              ?)
            ", $this->groupId, $artistId, $aliasId, $role, (string)$role
        );
        return self::$db->affected_rows();
    }
}
