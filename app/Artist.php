<?php

namespace Gazelle;

class Artist {
    /** @var \DB_MYSQL */
    protected $db;

    /** @var \CACHE */
    protected $cache;

    protected $id;
    protected $revision;
    protected $artistRole; // what different roles does an artist have
    protected $nrGroups;   // number of distinct groups
    protected $groupRole;  // what role does this artist have in a group
    protected $groupList;  // the release groups ordered by year and name
    protected $sections;   // their groups, gathered into sections

    protected $discogsId;
    protected $discogsName;
    protected $discogsStem;
    protected $discogsSequence;
    protected $discogsIsPreferred;
    protected $homonyms;

    protected $name;
    protected $image;
    protected $body;
    protected $vanity;
    protected $similar;

    protected $nrLeechers;
    protected $nrSnatches;
    protected $nrSeeders;
    protected $nrTorrents;

    const CACHE_PREFIX = 'artist_';
    const DISCOGS_API_URL = 'https://api.discogs.com/artists/%d';

    public function __construct (\DB_MYSQL $db, \CACHE $cache, $id, $revision = false) {
        $this->db = $db;
        $this->cache = $cache;
        $this->id = $id;
        $this->revision = $revision;

        $cacheKey = $this->cacheKey();
        if (($info = $this->cache->get_value($cacheKey)) !== false) {
            list($this->name, $this->image, $this->body, $this->vanity, $this->similar,
                $this->discogsId, $this->discogsName, $this->discogsStem, $this->discogsSequence, $this->discogsIsPreferred, $this->homonyms
            ) = $info;
        } else {
            $sql = 'SELECT ag.Name, wa.Image, wa.body, ag.VanityHouse, dg.artist_discogs_id, dg.name as discogs_name, dg.stem as discogs_stem, dg.sequence, dg.is_preferred
                FROM artists_group AS ag
                LEFT JOIN wiki_artists AS wa USING (RevisionID)
                LEFT JOIN artist_discogs AS dg ON (dg.artist_id = ag.ArtistID)
                WHERE ';
            if ($this->revision) {
                $sql .= 'wa.RevisionID = ?';
                $queryId = $this->revision;
            } else {
                $sql .= 'ag.ArtistID = ?';
                $queryId = $this->id;
            }

            $this->db->prepared_query($sql, $queryId);
            if (!$this->db->has_results()) {
                throw new \Exception("no such artist");
            }
            list($this->name, $this->image, $this->body, $this->vanity,
                $this->discogsId, $this->discogsName, $this->discogsStem, $this->discogsSequence, $this->discogsIsPreferred, $this->homonyms)
                = $this->db->next_record(MYSQLI_NUM, false);

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
                ],
                3600
            );
        }
    }

    public function cacheKey() {
        // TODO: change to protected when sections/ajax/artist.php is refactored
        if ($this->revision) {
            return self::CACHE_PREFIX . $this->id . '_r' . $this->revision;
        } else {
            return self::CACHE_PREFIX . $this->id;
        }
    }

    public function flushCache() {
        $this->cache->delete_value($this->cacheKey());
    }

    public function getAlias($name) {
        $this->db->prepared_query('
            SELECT AliasID
            FROM artists_alias
            WHERE ArtistID = ?
                AND ArtistID != AliasID
                AND Name = ?',
            $this->id, $name);
        list($alias) = $this->db->next_record();
        return empty($alias) ? $this->id : $alias;
    }

    public function editableInformation() {
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
        );
    }

    public function requests() {
        if (($requests = $this->cache->get_value("artists_requests_" . $this->id)) === false) {
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

    public function loadArtistRole() {
        $this->db->prepared_query('
            SELECT ta.GroupID, ta.Importance as artistRole
            FROM torrents_artists AS ta
            INNER JOIN torrents_group AS tg ON (tg.ID = ta.GroupID)
            WHERE ta.ArtistID = ?
            ORDER BY tg.Year DESC, tg.Name DESC
            ', $this->id
        );
        $this->groupRole = [];
        $this->groupList = [];
        $this->artistRole = [
            ARTIST_MAIN => 0,
            ARTIST_GUEST => 0,
            ARTIST_REMIXER => 0,
            ARTIST_COMPOSER => 0,
            ARTIST_CONDUCTOR => 0,
            ARTIST_DJ => 0,
            ARTIST_PRODUCER => 0,
        ];
        $nr = 0;
        while ($row = $this->db->next_record(MYSQLI_ASSOC, false)) {
            ++$nr;
            ++$this->artistRole[$row['artistRole']];
            $this->groupRole[$row['GroupID']] = $row['artistRole'];
            $this->groupList[] = $row['GroupID']; // to retain year ordering
        }
        return $nr;
    }

    public function hasRole($role) {
        return $this->artistRole[$role] > 0;
    }

    public function activeRoles() {
        return array_filter($this->artistRole, function ($role) {return $role > 0;});
    }

    public function groupRole($groupId) {
        return isset($this->groupRole[$groupId]) ? $this->groupRole[$groupId] : false;
    }

    public function groupIds() {
        /* this is needed to call \Torrents::get_groups() */
        return $this->groupList;
    }

    public function loadGroups($torrentGroupList) {
        $this->sections   = [];
        $this->nrGroups   = 0;
        $this->nrLeechers = 0;
        $this->nrSnatches = 0;
        $this->nrSeeders  = 0;
        $this->nrTorrents = 0;
        foreach ($this->groupList as $groupId) {
            if (!isset($torrentGroupList[$groupId])) {
                continue;
            }
            ++$this->nrGroups;
            switch ($this->groupRole[$groupId]) {
                case ARTIST_GUEST:
                    $section = 1024;
                    break;
                case ARTIST_REMIXER:
                    $section = 1023;
                    break;
                case ARTIST_COMPOSER:
                    $section = 1022;
                    break;
                case ARTIST_PRODUCER:
                    $section = 1021;
                    break;
                default:
                    $section = $torrentGroupList[$groupId]['ReleaseType'];
                    break;
            }
            if (!isset($this->sections[$section])) {
                $this->sections[$section] = [];
            }
            $this->sections[$section][] = $torrentGroupList[$groupId];
            foreach ($torrentGroupList[$groupId]['Torrents'] as $t) {
                $this->nrLeechers += $t['Leechers'];
                $this->nrSnatches += $t['Snatched'];
                $this->nrSeeders  += $t['Seeders'];
                ++$this->nrTorrents;
            }
        }
        /* TODO: See if ever $nrGroups < count($this->groupList) */
        return $this->nrGroups;
    }

    public function nrGroups() {
        return $this->nrGroups;
    }

    public function nrLeechers() {
        return $this->nrLeechers;
    }

    public function nrSnatches() {
        return $this->nrSnatches;
    }

    public function nrSeeders() {
        return $this->nrSeeders;
    }

    public function nrTorrents() {
        return $this->nrTorrents;
    }

    public function sections() {
        return $this->sections;
    }

    public function name() {
        return $this->name;
    }

    public function image() {
        return $this->image;
    }

    public function body() {
        return $this->body;
    }

    public function vanityHouse() {
        return $this->vanity;
    }

    public function similarArtists() {
        return $this->similar;
    }

    public function setDiscogsRelation(int $discogsId, int $userId) {
        if ($this->discogsId == $discogsId) {
            // don't blindly set the Discogs ID to something else if it's already set, or doesn't change
            return;
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
            return null;
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
        if (preg_match('/^(.*) \((\d+)\)$/', $this->discogsName, $match) ) {
            $this->discogsStem = $match[1];
            $this->discogsSequence = $match[2];
        }
        else {
            $this->discogsStem = $this->discogsName;
            $this->discogsSequence = 1;
        }
        $this->discogsId = $discogsId;

        $this->db->prepared_query('
            INSERT INTO artist_discogs
                   (artist_discogs_id, artist_id, is_preferred, sequence, stem, name, user_id)
            VALUES (?,                 ?,         ?,            ?,        ?,    ?,    ?)
            ', $this->discogsId, $this->id, $this->homonymCount() == 0, $this->discogsSequence, $this->discogsStem, $this->discogsName, $userId
        );
        $this->flushCache();
        return $this->db->affected_rows();
    }

    public function homonymCount() {
        return $this->db->scalar('
            SELECT count(*) FROM artist_discogs WHERE stem = ?
            ', $this->discogsStem
        );
    }

    public function removeDiscogsRelation() {
        $this->db->prepared_query('
            DELETE FROM artist_discogs WHERE artist_id = ?
            ', $this->id
        );
        $this->flushCache();
        return $this->db->affected_rows();
    }

    public function discogsId() {
        return $this->discogsId;
    }

    public function discogsName() {
        return $this->discogsName;
    }

    public function discogsIsPreferred() {
        return $this->discogsIsPreferred;
    }
}
