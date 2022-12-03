<?php

namespace Gazelle;

class Artist extends Base {
    protected int $id = 0;
    protected int $revisionId = 0;
    protected $artistRole;
    protected int $nrGroups = 0;

    /** All the groups */
    protected array $group = [];

    /** The roles an artist holds in a release */
    protected array $groupRole = [];

    /** Their groups, gathered into sections */
    protected array $section = [];

    /** attributes */
    protected array $attr;

    protected $discogsId;
    protected $discogsName;
    protected $discogsStem;
    /** @var string|int */
    protected $discogsSequence;
    protected $discogsIsPreferred;
    protected $homonyms;

    protected string $name = '';
    protected $image;
    protected $body;
    /** @var bool|int */
    protected $vanity;
    protected $similar = [];

    protected int $nrLeechers = 0;
    protected int $nrSnatches = 0;
    protected int $nrSeeders = 0;
    protected int $nrTorrents = 0;

    protected const CACHE_PREFIX = 'artist_';
    protected const DISCOGS_API_URL = 'https://api.discogs.com/artists/%d';

    /**
     * Artist constructor.
     * @param  int  $id
     * @param  int|null  $revisionId
     */
    public function __construct(int $id, $revisionId = null) {
        $this->id = $id;
        $this->revisionId = $revisionId ?? 0;

        $cacheKey = $this->cacheKey();
        if (($info = self::$cache->get_value($cacheKey)) !== false) {
            [$this->name, $this->image, $this->body, $this->vanity, $this->similar,
                $this->discogsId, $this->discogsName, $this->discogsStem, $this->discogsSequence, $this->discogsIsPreferred, $this->homonyms, $attr
            ] = $info;
            $this->attr = is_array($attr) ? $attr : $this->loadAttr();
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
            ] = self::$db->row("
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
            $this->attr = $this->loadAttr();

            self::$cache->cache_value($cacheKey, [
                    $this->name, $this->image, $this->body, $this->vanity, $this->similar,
                    $this->discogsId, $this->discogsName, $this->discogsStem, $this->discogsSequence, $this->discogsIsPreferred, $this->homonyms, $this->attr
                ], 3600
            );
        }
    }

    public function location(): string {
        return 'artist.php?id=' . $this->id;
    }

    public function url(): string {
        return htmlentities($this->location());
    }

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->name));
    }

    protected function loadAttr(): array {
        self::$db->prepared_query("
            SELECT aa.name, aa.artist_attr_id
            FROM artist_attr aa
            INNER JOIN artist_has_attr aha USING (artist_attr_id)
            WHERE aha.artist_id = ?
            ", $this->id
        );
        return self::$db->to_pair('name', 'artist_attr_id');
    }

    public function hasAttr(string $name): ?int {
        if (!isset($this->attr)) {
            $this->attr = $this->loadAttr();
        }
        return $this->attr[$name] ?? null;
    }

    protected function toggleAttr(string $attr, bool $flag): bool {
        $attrId = $this->hasAttr($attr);
        $found = !is_null($attrId);
        $toggled = false;
        if (!$flag && $found) {
            self::$db->prepared_query('
                DELETE FROM artist_has_attr WHERE artist_id = ? AND artist_attr_id = ?
                ', $this->id, $attrId
            );
            $toggled = self::$db->affected_rows() === 1;
        }
        elseif ($flag && !$found) {
            self::$db->prepared_query('
                INSERT INTO artist_has_attr (artist_id, artist_attr_id)
                    SELECT ?, artist_attr_id FROM artist_attr WHERE name = ?
                ', $this->id, $attr
            );
            $toggled = self::$db->affected_rows() === 1;
        }
        if ($toggled) {
            $this->flushCache();
        }
        return $toggled;
    }

    public function isLocked(): bool {
        return $this->hasAttr('locked') ?? false;
    }

    public function setLocked(): bool {
        return $this->toggleAttr('locked', true);
    }

    public function setUnlocked(): bool {
        return $this->toggleAttr('locked', false);
    }

    public function loadArtistRole() {
        self::$db->prepared_query("
            SELECT ta.GroupID AS group_id,
                ta.Importance as artist_role,
                rt.ID as release_type_id
            FROM torrents_artists AS ta
            INNER JOIN torrents_group AS tg ON (tg.ID = ta.GroupID)
            INNER JOIN release_type AS rt ON (rt.ID = tg.ReleaseType)
            WHERE ta.ArtistID = ?
            ORDER BY tg.Year DESC, tg.Name, rt.ID
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

        while ([$groupId, $role, $releaseTypeId] = self::$db->next_record(MYSQLI_NUM, false)) {
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
            $this->section[$sectionId][$groupId] = true;
            if (!isset($this->groupRole[$groupId])) {
                $this->groupRole[$groupId] = [];
            }
            $this->groupRole[$groupId][] = $role;
            ++$this->artistRole[$role];
        }
        return $this;
    }

    public function artistRole(): array {
        return $this->artistRole;
    }

    public function groupIds(): array {
        return array_keys($this->groupRole);
    }

    public function group(int $groupId): array {
        return $this->group[$groupId] ?? []; // FIXME
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

    public function tagLeaderboard(): array {
        self::$db->prepared_query("
            SELECT t.Name AS name,
                count(*)  AS total
            FROM torrents_artists tga
            INNER JOIN torrents_group tg ON (tg.ID = tga.GroupID)
            INNER JOIN torrents_tags ta USING (GroupID)
            INNER JOIN tags t ON (t.ID = ta.TagID)
            WHERE tg.CategoryID NOT IN (3, 7)
                AND tga.ArtistID = ?
            GROUP BY t.Name
            ORDER By 2 desc, t.Name
            LIMIT 10
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function rename(int $userId, int $aliasId, string $name): void {
        self::$db->prepared_query("
            INSERT INTO artists_alias
                   (ArtistID, Name, UserID, Redirect)
            VALUES (?,        ?,    ?,      0)
            ", $this->id, $name, $userId
        );
        $targetId = self::$db->inserted_id();
        self::$db->prepared_query("
            UPDATE artists_alias SET Redirect = ? WHERE AliasID = ?
            ", $targetId, $aliasId
        );
        self::$db->prepared_query("
            UPDATE artists_group SET Name = ? WHERE ArtistID = ?
            ", $name, $this->id
        );

        // process artists in torrents
        self::$db->prepared_query("
            SELECT GroupID FROM torrents_artists WHERE AliasID = ?
            ", $aliasId
        );
        $groups = self::$db->collect('GroupID');
        self::$db->prepared_query("
            UPDATE IGNORE torrents_artists SET AliasID = ?  WHERE AliasID = ?
            ", $targetId, $aliasId
        );
        $tgroupMan = new Manager\TGroup;
        foreach ($groups as $groupId) {
            $tgroupMan->refresh($groupId);
        }

        // process artists in requests
        self::$db->prepared_query("
            SELECT RequestID FROM requests_artists WHERE AliasID = ?
            ", $aliasId
        );
        $requests = self::$db->collect('RequestID');
        self::$db->prepared_query("
            UPDATE IGNORE requests_artists SET AliasID = ? WHERE AliasID = ?
            ", $targetId, $aliasId
        );
        foreach ($requests as $requestId) {
            self::$cache->delete_value("request_artists_$requestId"); // Delete request artist cache
            \Requests::update_sphinx_requests($requestId);
        }
    }

    public function cacheKey(): string {
        // TODO: change to protected when sections/ajax/artist.php is refactored
        return self::CACHE_PREFIX . $this->id
            . ($this->revisionId ? '_r' . $this->revisionId : '');
    }

    public function flushCache(): int {
        self::$db->prepared_query("
            SELECT DISTINCT concat('groups_artists_', GroupID)
            FROM torrents_artists
            WHERE ArtistID = ?
            ", $this->id
        );
        $cacheKeys = self::$db->collect(0, false);
        self::$cache->deleteMulti($cacheKeys);
        self::$cache->delete_value($this->cacheKey());
        return count($cacheKeys);
    }

    /**
     * @throws \Gazelle\Exception\ResourceNotFoundException
     * @throws \Gazelle\Exception\ArtistRedirectException
     */
    public function resolveRedirect(int $redirectId): int {
        [$foundId, $foundRedirectId] = self::$db->row("
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
        return $foundRedirectId > 0 ? (int)$foundRedirectId : $redirectId;
    }

    /**
     * @param  int  $userId
     * @param  string  $name
     * @param  int  $redirect
     * @return int|void
     */
    public function addAlias(int $userId, string $name, int $redirect) {
        self::$db->prepared_query("
            INSERT INTO artists_alias
                   (ArtistID, Name, Redirect, UserID)
            VALUES (?,        ?,    ?,        ?)
            ", $this->id, $name, $redirect, $userId
        );
        return self::$db->inserted_id();
    }

    public function removeAlias(int $aliasId): void {
        self::$db->prepared_query("
            UPDATE artists_alias SET
                ArtistID = ?,
                Redirect = 0
            WHERE AliasID = ?
            ", $this->id, $aliasId
        );
    }

    public function getAlias($name): int {
        $alias = self::$db->scalar('
            SELECT AliasID
            FROM artists_alias
            WHERE ArtistID = ?
                AND ArtistID != AliasID
                AND Name = ?
            ', $this->id, $name
        );
        return $alias ?: $this->id;
    }

    public function aliasNameList(): array {
        self::$db->prepared_query("
            SELECT Name
            FROM artists_alias
            WHERE Redirect = 0 AND ArtistID = ?
            ORDER BY Name
            ", $this->id
        );
        return self::$db->collect('Name');
    }

    public function editableInformation(): array {
        return self::$db->row("
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

    public function aliasInfo(): array {
    /**
     * Build the alias info. We want all the non-redirecting aliases at the top
     * level, and gather their aliases together, and having everything sorted
     * alphabetically. This is harder than it seems.
     *  +---------+-----------+------------+
     *  | aliasId | aliasName | redirectId |
     *  +---------+-----------+------------+
     *  |     136 | alpha     |          0 |
     *  |      82 | bravo     |          0 |
     *  |     120 | charlie   |          0 |
     *  |     122 | delta     |          0 |
     *  |     134 | echo      |         82 |
     *  |     135 | foxtrot   |        122 |
     *  |      36 | golf      |        133 |
     *  |     133 | hotel     |        134 |
     *  |     140 | india     |        136 |
     *  +---------+-----------+------------+
     * alpha..delta are non-redirecting aliases. echo is an alias of bravo.
     * golf is an alias of hotel, which is an alias of echo, which is an alias of bravo.
     * This chaining will happen over time as aliases are added and removed and artists
     * are merged or renamed. The golf-hotel-echo-bravo chain is a worst case example of
     * an alias that points to another name that didn't exist when it was created.
     * This means that the chains cannot be resolved in a single pass. I think the
     * algorithm below covers all the edge cases.
     * In the end, the result is:
     *    alpha
     *      - india
     *    bravo
     *      - echo
     *      - golf
     *      - hotel
     *    charlie
     *    delta
     *      - foxtrot
     */
        self::$db->prepared_query("
            SELECT AliasID as aliasId, Name as aliasName, UserID as userId,  Redirect as redirectId
            FROM artists_alias
            WHERE ArtistID = ?
            ORDER BY Redirect, Name
            ", $this->id
        );
        $result = self::$db->to_array('aliasId', MYSQLI_ASSOC, false);

        // create the first level of redirections
        $map = [];
        foreach ($result as $aliasId => $info) {
            $map[$aliasId] = $info['redirectId'];
        }

        // go through the list again, and resolve the redirect chains
        foreach ($result as $aliasId => $info) {
            $redirect = $info['redirectId'];
            while (isset($map[$redirect]) && $map[$redirect] > 0) {
                $redirect = $map[$redirect];
            }
            $map[$aliasId] = $redirect;
        }

        // go through the list and tie the alias to its non-redirecting ancestor
        $userMan = new Manager\User;
        $alias = [];
        foreach ($result as $aliasId => $info) {
            if ($info['redirectId']) {
                $redirect = $map[$aliasId];
                $alias[$redirect]['alias'][] = [
                    'alias_id' => $aliasId,
                    'name'     => $info['aliasName'],
                    'user'     => $userMan->findById($info['userId']),
               ];
            } else {
                $alias[$aliasId] = [
                    'alias'    => [],
                    'alias_id' => $aliasId,
                    'name'     => $info['aliasName'],
                    'user'     => $userMan->findById($info['userId']),
                ];
            }
        }

        // the aliases may need to be sorted
        foreach ($alias as &$a) {
            if ($a['alias']) {
                uksort($a['alias'], fn ($x, $y) => strtolower($a['alias'][$x]['name']) <=> strtolower($a['alias'][$y]['name']));
            }
        }
        return $alias;
    }

    public function requests(): array {
        $requests = self::$cache->get_value("artists_requests_" . $this->id);
        if (empty($requests)) {
            self::$db->prepared_query('
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
            $requests = self::$db->has_results() ? self::$db->to_array('ID', MYSQLI_ASSOC, false) : [];
            self::$cache->cache_value("artists_requests_" . $this->id, $requests, 3600);
        }
        return $requests;
    }

    public function requestIdUsage(): array {
        self::$db->prepared_query("
            SELECT r.ID
            FROM requests AS r
            INNER JOIN requests_artists AS ra ON (ra.RequestID = r.ID)
            WHERE ra.ArtistID = ?
            ", $this->id
        );
        return self::$db->collect(0, false);
    }

    public function tgroupIdUsage(): array {
        self::$db->prepared_query("
            SELECT tg.ID
            FROM torrents_group AS tg
            INNER JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID)
            WHERE ta.ArtistID = ?
            ", $this->id
        );
        return self::$db->collect(0, false);
    }

    public function usageTotal(): int {
        return count($this->requestIdUsage()) + count($this->tgroupIdUsage());
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
        if (HTTP_PROXY) {
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
        self::$db->prepared_query("
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
            ", $this->discogsId, $this->id, (int)($this->homonymCount() == 0),
            $this->discogsSequence, $this->discogsStem, $this->discogsName, $userId
        );
        $this->flushCache();
        return self::$db->affected_rows();
    }

    public function homonymCount(): int {
        return self::$db->scalar('
            SELECT count(*) FROM artist_discogs WHERE stem = ?
            ', $this->discogsStem
        );
    }

    public function removeDiscogsRelation(): int {
        self::$db->prepared_query('
            DELETE FROM artist_discogs WHERE artist_id = ?
            ', $this->id
        );
        $this->flushCache();
        return self::$db->affected_rows();
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
         self::$db->prepared_query("
            SELECT RevisionID AS revision,
                Summary       AS summary,
                Time          AS time,
                UserID        AS user_id
            FROM wiki_artists
            WHERE PageID = ?
            ORDER BY RevisionID DESC
            ", $this->id
        );
        return self::$db->to_array('revision', MYSQLI_ASSOC, false);
    }

    public function addSimilar(Artist $similar, int $userId) {
        $artistId = $this->id;
        $similarArtistId = $similar->id();
        // Let's see if there's already a similar artists field for these two
        $similarId = self::$db->scalar("
            SELECT s1.SimilarID
            FROM artists_similar AS s1
            INNER JOIN artists_similar AS s2 ON (s1.SimilarID = s2.SimilarID)
            WHERE s1.ArtistID = ?
                AND s2.ArtistID = ?
            ", $this->id, $similar->id()
        );
        self::$db->begin_transaction();
        if ($similarId) { // The similar artists field already exists, just update the score
            self::$db->prepared_query("
                UPDATE artists_similar_scores SET
                    Score = Score + 200
                WHERE SimilarID = ?
                ", $similarId
            );
        } else { // No, it doesn't exist - create it
            self::$db->prepared_query("
                INSERT INTO artists_similar_scores (Score) VALUES (200)
            ");
            $similarId = self::$db->inserted_id();
            self::$db->prepared_query("
                INSERT INTO artists_similar
                       (ArtistID, SimilarID)
                VALUES (?, ?), (?, ?)
                ", $artistId, $similarId, $similarArtistId, $similarId
            );
        }

        self::$db->prepared_query("
            INSERT IGNORE INTO artists_similar_votes
                   (SimilarID, UserID, way)
            VALUES (?,         ?,      'up')
            ", $similarId, $userId
        );
        self::$db->commit();

        $this->flushCache();
        $similar->flushCache();
        self::$cache->deleteMulti(["similar_positions_$artistId", "similar_positions_$similarArtistId"]);
    }

    public function removeSimilar(int $similarId): bool {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            SELECT ArtistID FROM artists_similar WHERE SimilarID = ?
            ", $similarId
        );
        $artistIds = self::$db->collect(0);
        if (!in_array($this->id, $artistIds)) {
            self::$db->rollback();
            return false;
        }
        self::$db->prepared_query("
            DELETE FROM artists_similar_votes WHERE SimilarID = ?
            ", $similarId
        );
        self::$db->prepared_query("
            DELETE FROM artists_similar_scores WHERE SimilarID = ?
            ", $similarId
        );
        self::$db->prepared_query("
            DELETE FROM artists_similar WHERE SimilarID = ?
            ", $similarId
        );
        $manager = new Manager\Artist;
        foreach ($artistIds as $id) {
            $manager->findById($id)->flushCache();
            self::$cache->delete_value("similar_positions_$id");
        }
        self::$db->commit();
        return true;
    }

    public function loadSimilar(): array {
        self::$db->prepared_query("
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
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function similarGraph(int $width, int $height): array {
        // find the similar artists of this one
        self::$db->prepared_query("
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
        $artistIds = self::$db->collect('artist_id') ?: [0];
        $similar   = self::$db->to_array('artist_id', MYSQLI_ASSOC, false);
        if (!$similar) {
            return [];
        }
        $nrSimilar = count($similar);

        // of these similar artists, see if any are similar to each other
        self::$db->prepared_query("
            SELECT s1.artistid AS source,
                group_concat(s2.artistid) AS target
            FROM artists_similar s1
            INNER JOIN artists_similar s2 ON (s1.similarid = s2.similarid and s1.artistid != s2.artistid)
            WHERE s1.artistid in (" . placeholders($artistIds) . ")
                AND s2.artistid in (" . placeholders($artistIds) . ")
            GROUP BY s1.artistid
            ", ...array_merge($artistIds, $artistIds)
        );
        $relation = self::$db->to_array('source', MYSQLI_ASSOC, false);

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
            usort($related, fn ($a, $b) => $similar[$a]['nrRelated'] <=> $similar[$b]['nrRelated']);
            $s['related'] = $related;
        }
        unset($s);

        // Now sort the artists by most relations first
        uksort($similar, fn ($a, $b)
            => $similar[$b]['nrRelated'] <=> $similar[$a]['nrRelated']
            ?: $similar[$b]['score']     <=> $similar[$a]['score']
            ?: $similar[$b]['artist_id'] <=> $similar[$a]['artist_id']
        );

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
        if (self::$db->scalar("
            SELECT 1
            FROM artists_similar_votes
            WHERE SimilarID = ?
                AND UserID = ?
                AND Way = ?
            ", $similarId, $userId, $upvote ? 'up' : 'down'
        )) {
            return false;
        }
        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE artists_similar_scores SET
                Score = Score + ?
            WHERE SimilarID = ?
            ", $upvote ? 100 : -100, $similarId
        );
        self::$db->prepared_query("
            INSERT INTO artists_similar_votes
                   (SimilarID, UserID, Way)
            VALUES (?,         ?,      ?)
            ", $similarId, $userId, $upvote ? 'up' : 'down'
        );
        self::$db->commit();
        $similarArtistId = self::$db->scalar("
            SELECT ArtistID
            FROM artists_similar
            WHERE SimilarID = ?
                AND ArtistID != ?
            ", $similarId, $this->id
        );
        $similarArtist = new Artist($similarArtistId, 0);
        $this->flushCache();
        $similarArtist->flushCache();
        self::$cache->deleteMulti(["similar_positions_" . $this->id, "similar_positions_$similarArtistId"]);
        return true;
    }

    /**
     * Deletes an artist and their wiki and tags.
     * Does NOT delete their requests or torrents.
     */
    public function remove(User $user, Log $logger): int {
        $qid  = self::$db->get_query_id();
        $id   = $this->id;
        $name = $this->name();

        self::$db->begin_transaction();
        self::$db->prepared_query("DELETE FROM artists_alias WHERE ArtistID = ?", $id);
        self::$db->prepared_query("DELETE FROM artists_group WHERE ArtistID = ?", $id);
        self::$db->prepared_query("DELETE FROM artists_tags WHERE ArtistID = ?", $id);
        self::$db->prepared_query("DELETE FROM wiki_artists WHERE PageID = ?", $id);

        (new \Gazelle\Manager\Comment)->remove('artist', $id);
        $logger->general("Artist $id ($name) was deleted by " . $user->username());
        self::$db->commit();

        self::$cache->delete_value('zz_a_' . $id);
        self::$cache->delete_value('artist_' . $id);
        self::$cache->delete_value('artist_groups_' . $id);
        self::$cache->decrement('stats_artist_count');

        self::$db->set_query_id($qid);
        return 1;
    }
}
