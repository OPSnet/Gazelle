<?php

namespace Gazelle;

use Requests;
use Torrents;
use UnexpectedValueException;

class Artist extends Base {
    protected $id = 0;
    protected $revisionId = 0;
    protected $artistRole;
    protected $nrGroups = 0;

    /** All the groups */
    protected $group = [];

    /** The roles an artist holds in a release */
    protected $groupRole = [];

    /** Their groups, gathered into sections */
    protected $section = [];

    protected $discogsId;
    protected $discogsName;
    protected $discogsStem;
    /** @var string|int */
    protected $discogsSequence;
    protected $discogsIsPreferred;
    protected $homonyms;

    protected $name = '';
    protected $image;
    protected $body;
    /** @var bool|int */
    protected $vanity;
    protected $similar = [];

    protected $nrLeechers = 0;
    protected $nrSnatches = 0;
    protected $nrSeeders = 0;
    protected $nrTorrents = 0;

    protected const CACHE_PREFIX = 'artist_';
    protected const DISCOGS_API_URL = 'https://api.discogs.com/artists/%d';

    /**
     * Artist constructor.
     * @param  int  $id
     * @param  int|null  $revisionId
     * @throws Gazelle\Exception\ResourceNotFoundException
     */
    public function __construct (int $id, $revisionId = null) {
        parent::__construct();
        $this->id = $id;
        $this->revisionId = $revisionId ?? 0;

        $cacheKey = $this->cacheKey();
        if (($info = $this->cache->get_value($cacheKey)) !== false) {
            [$this->name, $this->image, $this->body, $this->vanity, $this->similar,
                $this->discogsId, $this->discogsName, $this->discogsStem, $this->discogsSequence, $this->discogsIsPreferred, $this->homonyms
            ] = $info;
        } else {
            if ($this->revisionId) {
                $join = "LEFT JOIN wiki_artists AS wa ON (wa.PageID = ag.ArtistID)";
                $cond = 'wa.RevisionID = ?';
                $args = $this->revisionId;
            } else {
                $join = "LEFT JOIN wiki_artists AS wa USING (RevisionID)";
                $cond = 'ag.ArtistID = ?';
                $args = $this->id;
            }
            $sql = "SELECT ag.Name, wa.Image, wa.body, ag.VanityHouse,
                    dg.artist_discogs_id, dg.name as discogs_name, dg.stem as discogs_stem, dg.sequence, dg.is_preferred
                FROM artists_group AS ag
                $join
                LEFT JOIN artist_discogs AS dg ON (dg.artist_id = ag.ArtistID)
                WHERE $cond";

            $this->db->prepared_query($sql, $args);
            if (!$this->db->has_results()) {
                throw new Exception\ResourceNotFoundException($id);
            }
            [$this->name, $this->image, $this->body, $this->vanity, $this->discogsId, $this->discogsName,
                $this->discogsStem, $this->discogsSequence, $this->discogsIsPreferred
            ] = $this->db->next_record(MYSQLI_NUM, false);

            $this->homonyms = $this->homonymCount();

            $this->db->prepared_query('
                SELECT
                    s2.ArtistID,
                    a.Name,
                    ass.Score,
                    ass.SimilarID
                FROM artists_similar AS s1
                INNER JOIN artists_similar AS s2 ON (s1.SimilarID = s2.SimilarID AND s1.ArtistID != s2.ArtistID)
                INNER JOIN artists_similar_scores AS ass ON (ass.SimilarID = s1.SimilarID)
                INNER JOIN artists_group AS a ON (a.ArtistID = s2.ArtistID)
                WHERE s1.ArtistID = ?
                ORDER BY ass.Score DESC
                LIMIT 30
                ', $this->id
            );
            $this->similar = $this->db->to_array();
            $this->cache->cache_value($cacheKey, [
                    $this->name, $this->image, $this->body, $this->vanity, $this->similar,
                    $this->discogsId, $this->discogsName, $this->discogsStem, $this->discogsSequence, $this->discogsIsPreferred, $this->homonyms
                ], 3600
            );
        }
    }

    public function loadArtistRole() {
        $this->db->prepared_query("
            SELECT ta.GroupID, ta.Importance as artistRole
            FROM torrents_artists AS ta
            INNER JOIN torrents_group AS tg ON (tg.ID = ta.GroupID)
            WHERE ta.ArtistID = ?
            ORDER BY tg.Year DESC, tg.Name DESC
            ", $this->id
        );
        $this->artistRole = [
            ARTIST_MAIN => 0,
            ARTIST_GUEST => 0,
            ARTIST_REMIXER => 0,
            ARTIST_COMPOSER => 0,
            ARTIST_CONDUCTOR => 0,
            ARTIST_DJ => 0,
            ARTIST_PRODUCER => 0,
        ];

        while ([$groupId, $role] = $this->db->next_record(MYSQLI_NUM, false)) {
            ++$this->artistRole[$role];
            if (!isset($this->groupRole[$groupId])) {
                $this->groupRole[$groupId] = [];
            }
            $this->groupRole[$groupId][] = $role;
        }

        $groupIds = array_keys($this->groupRole);
        $torrentGroupList = \Torrents::get_groups($groupIds, true, true);
        foreach ($groupIds as $groupId) {
            $this->group[$groupId] = $torrentGroupList[$groupId];
            foreach ($this->group[$groupId]['Torrents'] as $t) {
                $this->nrLeechers += $t['Leechers'];
                $this->nrSnatches += $t['Snatched'];
                $this->nrSeeders  += $t['Seeders'];
                ++$this->nrTorrents;
            }
            $section = [$this->group[$groupId]['ReleaseType']];
            foreach ($this->groupRole[$groupId] as $role) {
                switch ($role) {
                    case ARTIST_GUEST:
                        $section[] = 1024;
                        break;
                    case ARTIST_REMIXER:
                        $section[] = 1023;
                        break;
                    case ARTIST_COMPOSER:
                        $section[] = 1022;
                        break;
                    case ARTIST_PRODUCER:
                        $section[] = 1021;
                        break;
                }
            }
            foreach ($section as $s) {
                if (!isset($this->section[$s])) {
                    $this->section[$s] = [];
                }
                $this->section[$s][] = $groupId;
            }
        }
        $this->nrGroups = count($groupIds);
        return $this;
    }

    public function artistRole(): array {
        return $this->artistRole;
    }

    public function hasRole($role): bool {
        return $this->artistRole[$role] > 0;
    }

    public function group(int $id): array {
        return $this->group[$id];
    }

    public function nrGroups(): int {
        return $this->nrGroups;
    }

    public function sections(): array {
        return $this->section;
    }

    public function nrLeechers(): int {
        return $this->nrLeechers;
    }

    public function nrSnatches(): int {
        return $this->nrSnatches;
    }

    public function nrSeeders(): int {
        return $this->nrSeeders;
    }

    public function nrTorrents(): int {
        return $this->nrTorrents;
    }

    public function name(): ?string {
        return $this->name;
    }

    public function image(): ?string {
        return $this->image;
    }

    public function body(): ?string {
        return $this->body;
    }

    public function vanityHouse(): bool {
        return $this->vanity;
    }

    public function similarArtists(): array {
        return $this->similar;
    }

    public function url(): string {
        return sprintf('<a href="artist.php?id=%d">%s</a>', $this->id, $this->name);
    }

    public function rename(int $userId, int $aliasId, string $name): void {
        $this->db->prepared_query("
            INSERT INTO artists_alias
                   (ArtistID, Name, UserID, Redirect)
            VALUES (?,        ?,    ?,      0)
            ", $this->id, $name, $userId
        );
        $targetId = $this->db->inserted_id();
        $this->db->prepared_query("
            UPDATE artists_alias SET Redirect = ? WHERE AliasID = ?
            ", $targetId, $aliasId
        );
        $this->db->prepared_query("
            UPDATE artists_group SET Name = ? WHERE ArtistID = ?
            ", $name, $this->id
        );

        // process artists in torrents
        $this->db->prepared_query("
            SELECT GroupID FROM torrents_artists WHERE AliasID = ?
            ", $aliasId
        );
        $groups = $this->db->collect('GroupID');
        $this->db->prepared_query("
            UPDATE IGNORE torrents_artists SET AliasID = ?  WHERE AliasID = ?
            ", $targetId, $aliasId
        );
        foreach ($groups as $groupId) {
            $this->cache->delete_value("groups_artists_$groupId"); // Delete group artist cache
            \Torrents::update_hash($groupId);
        }

        // process artists in requests
        $this->db->prepared_query("
            SELECT RequestID
            FROM requests_artists
            WHERE AliasID = ?
            ", $aliasId
        );
        $requests = $this->db->collect('RequestID');
        $this->db->prepared_query("
            UPDATE IGNORE requests_artists SET AliasID = ? WHERE AliasID = ?
            ", $targetId, $aliasId
        );
        foreach ($requests as $requestId) {
            $this->cache->delete_value("request_artists_$requestId"); // Delete request artist cache
            Requests::update_sphinx_requests($requestId);
        }
    }

    public function cacheKey(): string {
        // TODO: change to protected when sections/ajax/artist.php is refactored
        return self::CACHE_PREFIX . $this->id
            . ($this->revisionId ? '_r' . $this->revisionId : '');
    }

    public function flushCache(): void {
        $this->cache->delete_value($this->cacheKey());
    }

    /**
     * @param  int  $redirectId
     * @return int
     * @throws Gazelle\Exception\ResourceNotFoundException
     * @throws UnexpectedValueException
     */
    public function resolveRedirect(int $redirectId): int {
        [$foundId, $foundRedirectId] = $this->db->row("
            SELECT ArtistID, Redirect
            FROM artists_alias
            WHERE AliasID = ?
            ", $redirectId
        );
        if (!$foundId) {
            throw new Exception\ResourceNotFoundException($redirectId);
        }
        if ($this->id !== $foundId) {
            throw new UnexpectedValueException("Artist:not-redirected");
        }
        return $foundRedirectId > 0 ? (int) $foundRedirectId : $redirectId;
    }

    /**
     * @param  int  $userId
     * @param  string  $name
     * @param  int  $redirect
     * @return int|void
     */
    public function addAlias(int $userId, string $name, int $redirect) {
        $this->db->prepared_query("
            INSERT INTO artists_alias
                   (ArtistID, Name, Redirect, UserID)
            VALUES (?,        ?,    ?,        ?)
            ", $this->id, $name, $redirect, $userId
        );
        return $this->db->inserted_id();
    }

    public function removeAlias(int $aliasId): void {
        $this->db->prepared_query("
            UPDATE artists_alias SET
                ArtistID = ?,
                Redirect = 0
            WHERE AliasID = ?
            ", $this->id, $aliasId
        );
    }

    public function getAlias($name): int {
        $alias = $this->db->scalar('
            SELECT AliasID
            FROM artists_alias
            WHERE ArtistID = ?
                AND ArtistID != AliasID
                AND Name = ?
            ', $this->id, $name
        );
        return $alias ?: $this->id;
    }

    public function aliasList(): array {
        $this->db->prepared_query("
            SELECT Name
            FROM artists_alias
            WHERE Redirect = 0 AND ArtistID = ?
            ", $this->id
        );
        return $this->db->collect('Name');
    }

    public function editableInformation(): array {
        return $this->db->row("
            SELECT
                ag.Name,
                wa.Image,
                wa.Body,
                ag.VanityHouse,
                dg.artist_discogs_id
            FROM artists_group       AS ag
            LEFT JOIN wiki_artists   AS wa USING (RevisionID)
            LEFT JOIN artist_discogs AS dg ON (dg.artist_id = ag.ArtistID)
            WHERE ag.ArtistID = ?
            ", $this->id
        ) ?? [];
    }

    public function redirects(): array {
        $this->db->prepared_query("
            SELECT AliasID as aliasId, Name as aliasName, UserID as userId,  Redirect as redirectId
            FROM artists_alias
            WHERE ArtistID = ?
            ", $this->id
        );
        return $this->db->to_array('aliasId', MYSQLI_ASSOC);
    }

    public function requests(): array {
        $requests = $this->cache->get_value("artists_requests_" . $this->id);
        if (empty($requests)) {
            $this->db->prepared_query('
                SELECT
                    r.ID,
                    r.CategoryID,
                    r.Title,
                    r.Year,
                    r.TimeAdded,
                    count(rv.UserID) AS Votes,
                    sum(rv.Bounty) AS Bounty
                FROM requests AS r
                LEFT JOIN requests_votes AS rv ON (rv.RequestID = r.ID)
                LEFT JOIN requests_artists AS ra ON (r.ID = ra.RequestID)
                WHERE r.TorrentID = 0
                    AND ra.ArtistID = ?
                GROUP BY r.ID
                ORDER BY Votes DESC
                ', $this->id
            );
            $requests = $this->db->has_results() ? $this->db->to_array('ID', MYSQLI_ASSOC, false) : [];
            $this->cache->cache_value("artists_requests_" . $this->id, $requests, 3600);
        }
        return $requests;
    }

    /**
     * Sets the Discogs ID for the artist and returns the number of affected rows.
     */
    public function setDiscogsRelation(int $discogsId, int $userId): int {
        if ($this->discogsId === $discogsId) {
            // don't blindly set the Discogs ID to something else if it's already set, or doesn't change
            return 0;
        }
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => sprintf(self::DISCOGS_API_URL, $discogsId),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.1 Safari/537.11',
        ]);
        if (defined('HTTP_PROXY')) {
            curl_setopt_array($curl, [
                CURLOPT_HTTPPROXYTUNNEL => true,
                CURLOPT_PROXY => HTTP_PROXY,
            ]);
        }

        $result = curl_exec($curl);
        if ($result === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
            return 0;
        }

        /* Discogs names are e.g. "Spectrum (4)"
         * This is split into "Spectrum" and 4 to detect and handle homonyms.
         * First come, first served. The first homonym is considered preferred,
         * so the artist page will show "Spectrum". Subsequent artists will
         * be shown as "Spectrum (2)", "Spectrum (1)", ...
         * This can be adjusted via a control panel afterwards.
         */
        $payload = json_decode($result);
        $this->discogsName = $payload->name;
        if (preg_match('/^(.*) \((\d+)\)$/', $this->discogsName, $match)) {
            $this->discogsStem = $match[1];
            $this->discogsSequence = $match[2];
        } else {
            $this->discogsStem = $this->discogsName;
            $this->discogsSequence = 1;
        }
        $this->discogsId = $discogsId;

        // We only run this query when artist_discogs_id has changed, so the collision
        // should only happen on the UNIQUE(artist_id) index
        $this->db->prepared_query('
            INSERT INTO artist_discogs
                   (artist_discogs_id, artist_id, is_preferred, sequence, stem, name, user_id)
            VALUES (?,                 ?,         ?,            ?,        ?,    ?,    ?)
            ON DUPLICATE KEY UPDATE
                artist_discogs_id = VALUES(artist_discogs_id),
                is_preferred = VALUES(is_preferred),
                sequence = VALUES(sequence),
                stem = VALUES(stem),
                name = VALUES(name),
                user_id = VALUES(user_id)
            ', $this->discogsId, $this->id, $this->homonymCount() == 0,
            $this->discogsSequence, $this->discogsStem, $this->discogsName, $userId
        );
        $this->flushCache();
        return $this->db->affected_rows();
    }

    public function homonymCount(): int {
        return $this->db->scalar('
            SELECT count(*) FROM artist_discogs WHERE stem = ?
            ', $this->discogsStem
        );
    }

    public function removeDiscogsRelation(): int {
        $this->db->prepared_query('
            DELETE FROM artist_discogs WHERE artist_id = ?
            ', $this->id
        );
        $this->flushCache();
        return $this->db->affected_rows();
    }

    public function discogsId(): ?int {
        return $this->discogsId;
    }

    public function discogsName(): ?string {
        return $this->discogsName;
    }

    public function discogsIsPreferred(): bool {
        return $this->discogsIsPreferred;
    }

    /* STATIC METHODS - for when you do not yet have an ID, e.g. during creation */

    /**
     * Collapse whitespace and directional markers, because people copypaste carelessly.
     * TODO: make stricter, e.g. on all whitespace characters or Unicode normalisation
     *
     * @param  string  $name
     * @return string|null
     */
    public static function sanitize(string $name): ?string {
        // \u200e is &lrm;
        $name = preg_replace('/^(?:\xE2\x80\x8E|\s)+/', '', $name);
        $name = preg_replace('/(?:\xE2\x80\x8E|\s)+$/', '', $name);
        return preg_replace('/ +/', ' ', $name);
    }

    public function id() {
        return $this->id;
    }
}
