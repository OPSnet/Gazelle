<?php

namespace Gazelle;

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
     */
    public function __construct(int $id, $revisionId = null) {
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
            [$this->name, $this->image, $this->body, $this->vanity, $this->discogsId, $this->discogsName,
                $this->discogsStem, $this->discogsSequence, $this->discogsIsPreferred
            ] = $this->db->row("
                SELECT ag.Name, wa.Image, wa.body, ag.VanityHouse, dg.artist_discogs_id, dg.name,
                    dg.stem, dg.sequence, dg.is_preferred
                FROM artists_group AS ag
                $join
                LEFT JOIN artist_discogs AS dg ON (dg.artist_id = ag.ArtistID)
                WHERE $cond
                ", $args
            );
            $this->similar = $this->loadSimilar();
            $this->homonyms = $this->homonymCount();
            $this->cache->cache_value($cacheKey, [
                    $this->name, $this->image, $this->body, $this->vanity, $this->similar,
                    $this->discogsId, $this->discogsName, $this->discogsStem, $this->discogsSequence, $this->discogsIsPreferred, $this->homonyms
                ], 3600
            );
        }
    }

    public function loadArtistRole() {
        $this->db->prepared_query("
            SELECT ta.GroupID AS group_id,
                ta.Importance as artist_role,
                rt.ID as release_type_id
            FROM torrents_artists AS ta
            INNER JOIN torrents_group AS tg ON (tg.ID = ta.GroupID)
            INNER JOIN release_type AS rt ON (rt.ID = tg.ReleaseType)
            WHERE ta.ArtistID = ?
            ORDER BY rt.ID, tg.Year DESC, tg.Name DESC
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
            ARTIST_ARRANGER => 0,
        ];

        while ([$groupId, $role, $releaseTypeId] = $this->db->next_record(MYSQLI_NUM, false)) {
            switch($role) {
                case ARTIST_ARRANGER:
                    $sectionId = ARTIST_SECTION_ARRANGER;
                    break;
                case ARTIST_PRODUCER:
                    $sectionId = ARTIST_SECTION_PRODUCER;
                    break;
                case ARTIST_COMPOSER:
                    $sectionId = ARTIST_SECTION_COMPOSER;
                    break;
                case ARTIST_REMIXER:
                    $sectionId = ARTIST_SECTION_REMIXER;
                    break;
                case ARTIST_GUEST:
                    $sectionId = ARTIST_SECTION_GUEST;
                    break;
                default:
                    $sectionId = $releaseTypeId;
            }
            if (!isset($this->section[$sectionId])) {
                $this->section[$sectionId] = [];
            }
            $this->section[$sectionId][$groupId] = true;;
            if (!isset($this->groupRole[$groupId])) {
                $this->groupRole[$groupId] = [];
            }
            $this->groupRole[$groupId][] = $role;
            ++$this->artistRole[$role];
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
        }
        $this->nrGroups = count($groupIds);
        return $this;
    }

    public function artistRole(): array {
        return $this->artistRole;
    }

    public function groupIds(): array {
        return array_keys($this->groupRole);
    }

    public function group(int $groupId): array {
        return $this->group[$groupId];
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
        return $this->vanity == 1;
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
        $tgroupMan = new Manager\TGroup;
        foreach ($groups as $groupId) {
            $tgroupMan->refresh($groupId);
        }

        // process artists in requests
        $this->db->prepared_query("
            SELECT RequestID FROM requests_artists WHERE AliasID = ?
            ", $aliasId
        );
        $requests = $this->db->collect('RequestID');
        $this->db->prepared_query("
            UPDATE IGNORE requests_artists SET AliasID = ? WHERE AliasID = ?
            ", $targetId, $aliasId
        );
        foreach ($requests as $requestId) {
            $this->cache->delete_value("request_artists_$requestId"); // Delete request artist cache
            \Requests::update_sphinx_requests($requestId);
        }
    }

    public function cacheKey(): string {
        // TODO: change to protected when sections/ajax/artist.php is refactored
        return self::CACHE_PREFIX . $this->id
            . ($this->revisionId ? '_r' . $this->revisionId : '');
    }

    public function flushCache(): int {
        $this->db->prepared_query("
            SELECT DISTINCT concat('groups_artists_', GroupID)
            FROM torrents_artists
            WHERE ArtistID = ?
            ", $this->id
        );
        $cacheKeys = $this->db->collect(0, false);
        $this->cache->deleteMulti($cacheKeys);
        $this->cache->delete_value($this->cacheKey());
        return count($cacheKeys);
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
            throw new Exception\ArtistRedirectException();
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
            CURLOPT_USERAGENT      => FAKE_USERAGENT,
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

    public function id(): int {
        return $this->id;
    }

    public function revisionList(): array {
         $this->db->prepared_query("
            SELECT RevisionID AS revision,
                Summary       AS summary,
                Time          AS time,
                UserID        AS user_id
            FROM wiki_artists
            WHERE PageID = ?
            ORDER BY RevisionID DESC
            ", $this->id
        );
        return $this->db->to_array('revision', MYSQLI_ASSOC, false);
    }

    public function addSimilar(Artist $similar, int $userId) {
        $artistId = $this->id();
        $similarArtistId = $similar->id();
        // Let's see if there's already a similar artists field for these two
        $similarId = $this->db->scalar("
            SELECT s1.SimilarID
            FROM artists_similar AS s1
            INNER JOIN artists_similar AS s2 ON (s1.SimilarID = s2.SimilarID)
            WHERE s1.ArtistID = ?
                AND s2.ArtistID = ?
            ", $this->id(), $similar->id()
        );
        $this->db->begin_transaction();
        if ($similarId) { // The similar artists field already exists, just update the score
            $this->db->prepared_query("
                UPDATE artists_similar_scores SET
                    Score = Score + 200
                WHERE SimilarID = ?
                ", $similarId
            );
        } else { // No, it doesn't exist - create it
            $this->db->prepared_query("
                INSERT INTO artists_similar_scores (Score) VALUES (200)
            ");
            $similarId = $this->db->inserted_id();
            $this->db->prepared_query("
                INSERT INTO artists_similar
                       (ArtistID, SimilarID)
                VALUES (?, ?), (?, ?)
                ", $artistId, $similarId, $similarArtistId, $similarId
            );
        }

        $this->db->prepared_query("
            INSERT IGNORE INTO artists_similar_votes
                   (SimilarID, UserID, way)
            VALUES (?,         ?,      'up')
            ", $similarId, $userId
        );
        $this->db->commit();

        $this->flushCache();
        $similar->flushCache();
        $this->cache->deleteMulti(["similar_positions_$artistId", "similar_positions_$similarArtistId"]);
    }

    public function removeSimilar(int $similarId): bool {
        $this->db->begin_transaction();
        $this->db->prepared_query("
            SELECT ArtistID FROM artists_similar WHERE SimilarID = ?
            ", $similarId
        );
        $artistIds = $this->db->collect(0);
        if (!in_array($this->id, $artistIds)) {
            $this->db->rollback();
            return false;
        }
        $this->db->prepared_query("
            DELETE FROM artists_similar_votes WHERE SimilarID = ?
            ", $similarId
        );
        $this->db->prepared_query("
            DELETE FROM artists_similar_scores WHERE SimilarID = ?
            ", $similarId
        );
        $this->db->prepared_query("
            DELETE FROM artists_similar WHERE SimilarID = ?
            ", $similarId
        );
        $manager = new Manager\Artist;
        foreach ($artistIds as $id) {
            $manager->findById($id, 0)->flushCache();
            $this->cache->delete_value("similar_positions_$id");
        }
        $this->db->commit();
        return true;
    }

    public function loadSimilar(): array {
        $this->db->prepared_query("
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
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function similarGraph(int $width, int $height): array {
        // find the similar artists of this one
        $this->db->prepared_query("
            SELECT s2.ArtistID       AS artist_id,
                a.Name               AS artist_name,
                ass.Score            AS score,
                count(asv.SimilarID) AS votes
            FROM artists_similar s1
            INNER join artists_similar s2 ON (s1.SimilarID = s2.SimilarID AND s1.ArtistID != s2.ArtistID)
            INNER JOIN artists_group AS a ON (a.ArtistID = s2.ArtistID)
            INNER JOIN artists_similar_scores ass ON (ass.SimilarID = s1.SimilarID)
            INNER JOIN artists_similar_votes asv ON (asv.SimilarID = s1.SimilarID)
            WHERE s1.ArtistID = ?
            GROUP BY s1.SimilarID
            ORDER BY score DESC,
                votes DESC
            LIMIT 30
            ", $this->id
        );
        $artistIds = $this->db->collect('artist_id') ?: [0];
        $similar   = $this->db->to_array('artist_id', MYSQLI_ASSOC, false);
        if (!$similar) {
            return [];
        }
        $nrSimilar = count($similar);

        // of these similar artists, see if any are similar to each other
        $this->db->prepared_query("
            SELECT s1.artistid AS source,
                group_concat(s2.artistid) AS target
            FROM artists_similar s1
            INNER JOIN artists_similar s2 ON (s1.similarid = s2.similarid and s1.artistid != s2.artistid)
            WHERE s1.artistid in (" . placeholders($artistIds) . ")
                AND s2.artistid in (" . placeholders($artistIds) . ")
            GROUP BY s1.artistid
            ", ...array_merge($artistIds, $artistIds)
        );
        $relation = $this->db->to_array('source', MYSQLI_ASSOC, false);

        // calculate some minimax stuff to figure out line lengths
        $max = 0;
        $min = null;
        $totalScore = 0;
        foreach ($similar as &$s) {
            $s['related'] = [];
            $s['nrRelated'] = 0;
            $max = max($max, $s['score']);
            if (is_null($min)) {
                $min = $s['score'];
            } else {
                $min = min($min, $s['score']);
            }
            $totalScore += $s['score'];
        }
        unset($s);

        // Use the golden ratio formula to generate the angles where the
        // artists will be placed (to avoid drawing a line through the
        // origin for a relation when there are an even number of artists).
        // Sort the results because a) the order will be vaguely chaotic,
        // and b) we have a guarantee that two adjacent angles will be
        // at the beginning and end of the array (as long as we alternate
        // between shifting and popping the array).
        $layout = [];
        $angle = fmod($this->id, 2 * M_PI);
        $golden = M_PI * (3 - sqrt(5));
        foreach (range(0, $nrSimilar-1) as $r) {
            $layout[] = $angle;
            $angle = fmod($angle + $golden, 2 * M_PI);
        }
        sort($layout);

        // Thread all the similar artists with their related artists
        // and sort those with the most relations first.
        foreach ($relation as $source => $targetList) {
            $t = explode(',', $targetList['target']);
            foreach ($t as $target) {
                $similar[$source]['related'][] = (int)$target;
                $similar[$source]['nrRelated']++;
            }
        }

        // For all artists with relations, sort their relations list by least relations first.
        // The idea is to have other artists that are only related to this one close by.
        foreach ($similar as &$s) {
            if ($s['nrRelated'] < 2)  {
                // trivial case
                continue;
            }
            $related = $s['related'];
            usort($related, function ($a, $b) use ($similar) {
                return $similar[$a]['nrRelated'] <=> $similar[$b]['nrRelated'];
            });
            $s['related'] = $related;
        }
        unset($s);

        // Now sort the artists by most relations first
        uksort($similar, function ($a, $b) use ($similar) {
            $cmp = $similar[$b]['nrRelated'] <=> $similar[$a]['nrRelated'];
            if ($cmp != 0) {
                return $cmp;
            }
            $cmp = $similar[$b]['score'] <=> $similar[$a]['score'];
            if ($cmp != 0) {
                return $cmp;
            }
            return $similar[$b]['artist_id'] <=> $similar[$a]['artist_id'];
        });

        // Place the artists with the most relations first, and place
        // their relations near them, alternating on each side.
        $xOrigin = (int)$width / 2;
        $yOrigin = (int)$height / 2;
        $range = ($max === $min) ? $max : $max - $min;
        $placed = array_fill_keys(array_keys($similar), false);
        $seen = 0;
        foreach ($similar as &$s) {
            $id = $s['artist_id'];
            if ($placed[$id] !== false) {
                continue;
            }
            $relatedToPlace = 0;
            $relatedTotal = 0;
            foreach ($s['related'] as $r) {
                $relatedTotal++;
                if ($placed[$r] === false) {
                    $relatedToPlace++;
                }
            }
            if ($relatedToPlace > 0) {
                // Rotate the layout angles to fit this artist in, so that we can
                // pick the first and last angles off the layout list below.
                $move = (int)ceil(($relatedToPlace + 1) / 2);
                $layout = array_merge(array_slice($layout, $move, NULL, true), array_slice($layout, 0, $move, true));
            }
            if (!($relatedTotal > 0 && $seen > 1)) {
                $angle = array_shift($layout);
                $up = false;
            } else {
                // By now we have already placed two artists and we are here because the
                // current artist has a related artist to place. Have a look at the previously
                // placed artists, and if this artist is related to them, then choose first
                // or last angle in the layout list to place this artist close to them.
                $nextAngle = reset($layout);
                $prevAngle = end($layout);
                $bestNextAngle = 2 * M_PI;
                $bestPrevAngle = 2 * M_PI;
                foreach ($s['related'] as $r) {
                    if ($placed[$r] === false) {
                        continue;
                    }
                    $nextAngleDistance = fmod($nextAngle + $placed[$r], 2 * M_PI);
                    $prevAngleDistance = fmod($prevAngle + $placed[$r], 2 * M_PI);
                    if ($nextAngleDistance <= $prevAngleDistance) {
                        $bestNextAngle = min($bestNextAngle, $nextAngleDistance);
                    } else {
                        $bestPrevAngle = min($bestPrevAngle, $prevAngleDistance);
                    }
                }
                if (fmod($bestNextAngle, 2 * M_PI) < fmod($bestPrevAngle, 2 * M_PI))  {
                    $angle = array_shift($layout);
                    $up = false;
                } else {
                    $angle = array_pop($layout);
                    $up = true;
                }
            }
            $placed[$id] = $angle;
            ++$seen;

            // place this artist
            $distance = 0.9 - (($s['score'] - $min) * 0.4 / $range);
            $s['x'] = (int)(cos($angle) * $distance * $xOrigin) + $xOrigin;
            $s['y'] = (int)(sin($angle) * $distance * $yOrigin) + $yOrigin;
            $s['proportion'] = pow($s['score'] / ($totalScore + 1), 1.0);

            // Place their related close by, first anti-clockwise (angle
            // increasing: array_shift(), next clockwise (angle decreasing:
            // array_pop() and repeat until done.
            // There might be a way to refactor this to avoid repetition.
            foreach ($s['related'] as $r) {
                if ($placed[$r] !== false) {
                    continue;
                }
                $angle = $up ? array_shift($layout) : array_pop($layout);
                $up = !$up;
                $placed[$r] = $angle;
                ++$seen;

                // place this related artist
                $distance = 0.9 - (($similar[$r]['score'] - $min) * 0.45 / $range);
                $similar[$r]['x'] = (int)(cos($angle) * $distance * $xOrigin) + $xOrigin;
                $similar[$r]['y'] = (int)(sin($angle) * $distance * $yOrigin) + $yOrigin;
                $similar[$r]['proportion'] = pow($similar[$r]['score'] / ($totalScore + 1), 1.0);
            }

        }
        return $similar;
    }

    public function voteSimilar(int $userId, int $similarId, bool $upvote): bool {
        if ($this->db->scalar("
            SELECT 1
            FROM artists_similar_votes
            WHERE SimilarID = ?
                AND UserID = ?
                AND Way = ?
            ", $similarId, $userId, $upvote ? 'up' : 'down'
        )) {
            return false;
        }
        $this->db->begin_transaction();
        $this->db->prepared_query("
            UPDATE artists_similar_scores SET
                Score = Score + ?
            WHERE SimilarID = ?
            ", $upvote ? 100 : -100, $similarId
        );
        $this->db->prepared_query("
            INSERT INTO artists_similar_votes
                   (SimilarID, UserID, Way)
            VALUES (?,         ?,      ?)
            ", $similarId, $userId, $upvote ? 'up' : 'down'
        );
        $this->db->commit();
        $similarArtistId = $this->db->scalar("
            SELECT ArtistID
            FROM artists_similar
            WHERE SimilarID = ?
                AND ArtistID != ?
            ", $similarId, $this->id
        );
        $similarArtist = new Artist($similarArtistId, 0);
        $this->flushCache();
        $similarArtist->flushCache();
        $this->cache->deleteMulti(["similar_positions_" . $this->id, "similar_positions_$similarArtistId"]);
        return true;
    }
}
