<?php

namespace Gazelle;

class Artist extends BaseObject {
    final const CACHE_REQUEST_ARTIST = 'artists_requests_%d';
    final const CACHE_TGROUP_ARTIST  = 'artists_groups_%d';

    protected const CACHE_PREFIX    = 'artistv3_%d';
    protected const DISCOGS_API_URL = 'https://api.discogs.com/artists/%d';

    protected array $artistRole;

    /** All the groups */
    protected array $group = [];

    /** The roles an artist holds in a release */
    protected array $groupRole = [];

    /** Their groups, gathered into sections */
    protected array $section = [];

    protected Stats\Artist $stats;

    public function __construct(
        protected int $id,
        protected int $revisionId = 0
    ) {}

    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->name())); }
    public function location(): string { return 'artist.php?id=' . $this->id; }
    public function pkName(): string { return 'ArtistID'; }
    public function tableName(): string { return 'artists_group'; }

    protected function cacheKey(): string {
        return sprintf(self::CACHE_PREFIX, $this->id)
            . ($this->revisionId ? '_r' . $this->revisionId : '');
    }

    public function info(): array {
        $cacheKey = $this->cacheKey();
        $info = self::$cache->get_value($cacheKey);
        if ($info !== false) {
            $this->info = $info;
        } else {
            $sql = "
            SELECT ag.Name           AS name,
                wa.Image             AS image,
                wa.body              AS body,
                ag.VanityHouse       AS showcase,
                dg.artist_discogs_id AS discogs_id,
                dg.name              AS discogs_name,
                dg.stem              AS discogs_stem,
                dg.sequence,
                dg.is_preferred
            FROM artists_group AS ag
            LEFT JOIN artist_discogs AS dg ON (dg.artist_id = ag.ArtistID)
            ";
            if ($this->revisionId) {
                $sql .= "LEFT JOIN wiki_artists AS wa ON (wa.PageID = ag.ArtistID)";
                $cond = 'wa.RevisionID = ?';
                $args = [$this->revisionId];
            } else {
                $sql .= "LEFT JOIN wiki_artists AS wa USING (RevisionID)";
                $cond = 'ag.ArtistID = ?';
                $args = [$this->id];
            }
            $sql .= " WHERE $cond";
            $info = self::$db->rowAssoc($sql, ...$args);

            self::$db->prepared_query("
                SELECT AliasID AS alias_id,
                    Redirect   AS redirect_id,
                    Name       AS name
                FROM artists_alias
                WHERE ArtistID = ?
                ", $this->id
            );
            $info['alias'] = self::$db->to_array('alias_id', MYSQLI_ASSOC, false);

            self::$db->prepared_query("
                SELECT aa.name, aa.artist_attr_id
                FROM artist_attr aa
                INNER JOIN artist_has_attr aha USING (artist_attr_id)
                WHERE aha.artist_id = ?
                ", $this->id
            );
            $info['attr'] = self::$db->to_pair('name', 'artist_attr_id', false);

            self::$db->prepared_query("
                SELECT s2.ArtistID,
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
            $info['similar'] = self::$db->to_array(false, MYSQLI_ASSOC, false);

            $info['homonyms'] = (int)self::$db->scalar('
                SELECT count(*) FROM artist_discogs WHERE stem = ?
                ', $info['discogs_stem']
            );

            self::$cache->cache_value($cacheKey, $info, 3600);
            $this->info = $info;
        }
        return $this->info;
    }

    public function loadArtistRole(): Artist {
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
            $role = (int)$role;
            $sectionId = match ($role) {
                ARTIST_ARRANGER => ARTIST_SECTION_ARRANGER,
                ARTIST_PRODUCER => ARTIST_SECTION_PRODUCER,
                ARTIST_COMPOSER => ARTIST_SECTION_COMPOSER,
                ARTIST_REMIXER => ARTIST_SECTION_REMIXER,
                ARTIST_GUEST => ARTIST_SECTION_GUEST,
                default => $releaseTypeId,
            };
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


    public function flush(): Artist {
        self::$db->prepared_query("
            SELECT DISTINCT concat('groups_artists_', GroupID)
            FROM torrents_artists
            WHERE ArtistID = ?
            ", $this->id
        );
        self::$cache->delete_multi([
            $this->cacheKey(),
            sprintf(self::CACHE_REQUEST_ARTIST, $this->id),
            sprintf(self::CACHE_TGROUP_ARTIST, $this->id),
            ...self::$db->collect(0, false)
        ]);
        unset($this->info);
        return $this;
    }

    public function artistRole(): array {
        if (!isset($this->artistRole)) {
            $this->loadArtistRole();
        }
        return $this->artistRole;
    }

    public function body(): ?string {
        return $this->info()['body'];
    }

    public function discogsId(): ?int {
        return $this->info()['discogs_id'];
    }

    public function discogsName(): ?string {
        return $this->info()['discogs_name'];
    }

    public function discogsIsPreferred(): bool {
        return $this->info()['is_preferred'];
    }

    public function groupIds(): array {
        if (!isset($this->groupIds)) {
            $this->loadArtistRole();
        }
        return array_keys($this->groupRole);
    }

    public function group(int $groupId): array {
        if (!isset($this->group)) {
            $this->loadArtistRole();
        }
        return $this->group[$groupId] ?? []; // FIXME
    }

    public function homonymCount(): int {
        return $this->info()['homonyms'];
    }

    public function image(): ?string {
        return $this->info()['image'];
    }

    public function isLocked(): bool {
        return $this->hasAttr('locked');
    }

    public function isShowcase(): bool {
        return $this->info()['showcase'] == 1;
    }

    public function label(): string {
        return "{$this->id} ({$this->name()})";
    }

    public function name(): string {
        return $this->info()['name'];
    }

    public function sections(): array {
        if (!isset($this->section)) {
            $this->loadArtistRole();
        }
        return $this->section;
    }

    public function similarArtists(): array {
        return $this->info()['similar'];
    }

    public function stats(): Stats\Artist{
        if (!isset($this->stats)) {
            $this->stats = new Stats\Artist($this->id);
        }
        return $this->stats;
    }

    public function hasAttr(string $name): bool {
        return isset($this->info()['attr'][$name]);
    }

    public function toggleAttr(string $attr, bool $flag): bool {
        $hasAttr = $this->hasAttr($attr);
        $toggled = false;
        if (!$flag && $hasAttr) {
            self::$db->prepared_query("
                DELETE FROM artist_has_attr
                WHERE artist_id = ?
                    AND artist_attr_id = (SELECT artist_attr_id FROM artist_attr WHERE name = ?)
                ", $this->id, $attr
            );
            $toggled = self::$db->affected_rows() === 1;
        } elseif ($flag && !$hasAttr) {
            self::$db->prepared_query("
                INSERT INTO artist_has_attr (artist_id, artist_attr_id)
                    SELECT ?, artist_attr_id FROM artist_attr WHERE name = ?
                ", $this->id, $attr
            );
            $toggled = self::$db->affected_rows() === 1;
        }
        if ($toggled) {
            $this->flush();
        }
        return $toggled;
    }

    public function createRevision(
        ?string $body,
        ?string $image,
        array   $summary,
        User    $user,
    ): int {
        self::$db->prepared_query("
            INSERT INTO wiki_artists
                   (PageID, Body, Image, UserID, Summary)
            VALUES (?,      ?,    ?,     ?,      ?)
            ", $this->id, $body, $image, $user->id(),
                implode(', ', array_filter($summary, fn($s) => !empty($s)))
        );
        $revisionId = self::$db->inserted_id();
        self::$db->prepared_query("
            UPDATE artists_group SET
                RevisionID = ?
            WHERE ArtistID = ?
            ", $revisionId, $this->id
        );
        $this->flush();
        return $revisionId;
    }

    /**
     * Revert to a prior revision of the artist metadata
     * (Which also creates a new revision).
     */
    public function revertRevision(int $revisionId, \Gazelle\User $user): int {
        self::$db->prepared_query("
            INSERT INTO wiki_artists
                  (Body, Image, PageID, UserID, Summary)
            SELECT Body, Image, ?,      ?,      ?
            FROM wiki_artists
            WHERE RevisionID = ?
            ", $this->id, $user->id(), "Reverted to revision $revisionId",
                $revisionId
        );
        $newRevId = self::$db->inserted_id();
        self::$db->prepared_query("
            UPDATE artists_group SET
                RevisionID = ?
            WHERE ArtistID = ?
            ", $newRevId, $this->id
        );
        $this->flush();
        return $newRevId;
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
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
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

    public function rename(int $aliasId, string $name, Manager\Request $reqMan, User $user): int {
        self::$db->prepared_query("
            INSERT INTO artists_alias
                   (ArtistID, Name, UserID, Redirect)
            VALUES (?,        ?,    ?,      0)
            ", $this->id, $name, $user->id()
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
            $tgroupMan->findById($groupId)?->refresh();
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
            $reqMan->findById($requestId)->updateSphinx();
        }
        $this->flush();
        return $targetId;
    }

    public function addAlias(string $name, int $redirect, User $user, Log $logger): int {
        self::$db->prepared_query("
            INSERT INTO artists_alias
                   (ArtistID, Name, Redirect, UserID)
            VALUES (?,        ?,    ?,        ?)
            ", $this->id, $name, $redirect, $user->id()
        );
        $aliasId = self::$db->inserted_id();
        $logger->general(
            "The alias $aliasId ($name) was added to the artist {$this->label()} by user {$user->label()}"
        );
        $this->flush();
        return $aliasId;
    }

    public function getAlias($name): int {
        $alias = array_keys(
            array_filter(
                $this->aliasList(),
                fn($a) => (strcasecmp($a['name'], $name) == 0)
            )
        );
        return empty($alias) ? $this->id : current($alias);
    }

    public function clearAliasFromArtist(int $aliasId, User $user, Log $logger): int {
        $alias = $this->aliasList()[$aliasId];
        self::$db->prepared_query("
            UPDATE artists_alias SET
                ArtistID = ?,
                Redirect = 0
            WHERE AliasID = ?
            ", $this->id, $aliasId
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            $this->flush();
            $logger->general(
                "Redirection from the alias $aliasId ({$alias['name']}) for the artist {$this->label()} was removed by user {$user->label()}"
            );
        }
        return $affected;
    }

    public function removeAlias(int $aliasId, User $user, Log $logger): int {
        self::$db->begin_transaction();
        $alias = $this->aliasList()[$aliasId];
        self::$db->prepared_query("
            DELETE FROM artists_alias WHERE AliasID = ?
            ", $aliasId
        );
        $affected = self::$db->affected_rows();
        if ($affected) {
            $this->flush();
            $logger->general(
                "The alias $aliasId ({$alias['name']}) for the artist {$this->label()}  was removed by user {$user->label()}"
            );
        }
        self::$db->commit();
        return $affected;
    }

    public function aliasList(): array {
        return $this->info()['alias'];
    }

    public function aliasNameList(): array {
        return array_values(array_map(fn($a) => $a['name'], $this->aliasList()));
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

    public function addSimilar(Artist $similar, User $user): int {
        $artistId = $this->id;
        $similarArtistId = $similar->id();
        // Let's see if there's already a similar artists field for these two
        $similarId = (int)self::$db->scalar("
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
            ", $similarId, $user->id()
        );
        $affected = self::$db->affected_rows();
        self::$db->commit();

        self::$cache->delete_multi(["similar_positions_$artistId", "similar_positions_$similarArtistId"]);
        $this->flush();
        $similar->flush();
        return $affected;
    }

    public function voteSimilar(User $user, int $similarId, bool $upvote): bool {
        if ($this->id === $similarId) {
            return false;
        }
        if (self::$db->scalar("
            SELECT 1
            FROM artists_similar_votes
            WHERE SimilarID = ?
                AND UserID = ?
                AND Way = ?
            ", $similarId, $user->id(), $upvote ? 'up' : 'down'
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
            ", $similarId, $user->id(), $upvote ? 'up' : 'down'
        );
        self::$db->commit();
        $similarArtistId = (int)self::$db->scalar("
            SELECT ArtistID
            FROM artists_similar
            WHERE SimilarID = ?
                AND ArtistID != ?
            ", $similarId, $this->id
        );
        $similarArtist = new Artist($similarArtistId, 0);
        $this->flush();
        $similarArtist->flush();
        self::$cache->delete_multi(["similar_positions_" . $this->id, "similar_positions_$similarArtistId"]);
        return true;
    }

    public function removeSimilar(int $similarId, Manager\Artist $artMan, Manager\Request $reqMan): bool {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            SELECT ArtistID FROM artists_similar WHERE SimilarID = ?
            ", $similarId
        );
        $artistIds = self::$db->collect(0, false);
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
        foreach ($artistIds as $id) {
            self::$cache->delete_value("similar_positions_$id");
            $artMan->findById($id)?->flush();
            $reqMan->findById($id)?->updateSphinx();
        }
        self::$db->commit();
        return true;
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
            => ($similar[$b]['nrRelated'] <=> $similar[$a]['nrRelated'] ?: $similar[$b]['score']     <=> $similar[$a]['score'])
            ?: $similar[$b]['artist_id'] <=> $similar[$a]['artist_id']
        );

        // Place the artists with the most relations first, and place
        // their relations near them, alternating on each side.
        $xOrigin = $width / 2;
        $yOrigin = $height / 2;
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
                $layout = [...array_slice($layout, $move, NULL, true), ...array_slice($layout, 0, $move, true)];
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
            $s['proportion'] = ($s['score'] / ($totalScore + 1)) ** 1.0;

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
                $similar[$r]['proportion'] = ($similar[$r]['score'] / ($totalScore + 1)) ** 1.0;
            }

        }
        return $similar;
    }

    /**
     * Modify an artist. If the body or image fields are edited, or any other
     * change that has to appear in the history, a revision is created.
     * Since a revision requires the user who made the edit to be recorded,
     * the user is passed in as another field to update.
     * The body, image, summary and updater  fields are then cleared so that
     * the BaseObject method can do its job.
     */

    public function modify(): bool {
        // handle the revision of body and image
        $revisionData = [];
        $summary      = [];
        if ($this->field('body') !== null) {
            $body = $this->clearField('body');
            if (is_string($body)) {
                $revisionData['body'] = $body;
                $summary[] = 'description changed (len=' . mb_strlen($body) . ')';
            }
        }
        if ($this->field('image') !== null) {
            $image = $this->clearField('image');
            if (is_string($image)) {
                $revisionData['image'] = $image;
                $summary[] = "image changed to '$image'";
            }
        }
        $notes = $this->clearField('summary');
        if (is_array($notes)) {
            $summary = array_merge($summary, $notes);
        }
        $updated = false;
        if ($revisionData || $summary) {
            $this->setField('RevisionID',
                $this->createRevision(
                    body:    $revisionData['body'] ?? $this->body(),
                    image:   $revisionData['image'] ?? $this->image(),
                    summary: $summary,
                    user:    $this->updateUser,
                )
            );
            $updated = true;
        }

        // handle Discogs
        $discogsId = $this->clearField('discogs_id');
        if (is_int($discogsId)) {
            if ($discogsId > 0) {
                $this->setDiscogsRelation($discogsId);
            } else {
                $this->removeDiscogsRelation();
            }
            $updated = true;
        }
        $parentUpdated = parent::modify();
        $this->flush();
        return $parentUpdated || $updated;
    }

    /**
     * Sets the Discogs ID for the artist and returns the number of affected rows.
     */
    public function setDiscogsRelation(int $discogsId): int {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => sprintf(self::DISCOGS_API_URL, $discogsId),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_USERAGENT      => FAKE_USERAGENT,
        ]);
        $proxy = httpProxy();
        if ($proxy) {
            curl_setopt_array($curl, [
                CURLOPT_HTTPPROXYTUNNEL => true,
                CURLOPT_PROXY           => $proxy,
            ]);
        }

        $result = curl_exec($curl);
        if (!is_string($result) || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
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
        $discogsName = $payload->name;
        if (preg_match('/^(.*) \((\d+)\)$/', $discogsName, $match)) {
            $discogsStem = $match[1];
            $discogsSequence = (int)$match[2];
        } else {
            $discogsStem = $discogsName;
            $discogsSequence = 1;
        }

        // We only run this query when artist_discogs_id has changed, so the collision
        // should only happen on the UNIQUE(artist_id) index
        self::$db->prepared_query("
            INSERT INTO artist_discogs
                   (artist_discogs_id, artist_id, is_preferred, sequence, stem, name, user_id)
            VALUES (?,                 ?,         ?,            ?,        ?,    ?,    ?)
            ON DUPLICATE KEY UPDATE
                artist_discogs_id = VALUES(artist_discogs_id),
                is_preferred      = VALUES(is_preferred),
                sequence          = VALUES(sequence),
                stem              = VALUES(stem),
                name              = VALUES(name),
                user_id           = VALUES(user_id)
            ", $discogsId, $this->id, (int)($this->homonymCount() == 0),
            $discogsSequence, $discogsStem, $discogsName, $this->updateUser->id()
        );
        return self::$db->affected_rows();
    }

    public function removeDiscogsRelation(): int {
        self::$db->prepared_query('
            DELETE FROM artist_discogs WHERE artist_id = ?
            ', $this->id
        );
        return self::$db->affected_rows();
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

    /* STATIC METHODS - for when you do not yet have an ID, e.g. during creation */
    /**
     * Collapse whitespace and directional markers, because people copypaste carelessly.
     * TODO: make stricter, e.g. on all whitespace characters or Unicode normalisation
     */
    public static function sanitize(string $name): ?string {
        // \u200e is &lrm;
        $name = preg_replace('/^(?:\xE2\x80\x8E|\s)+/', '', $name);
        $name = preg_replace('/(?:\xE2\x80\x8E|\s)+$/', '', $name);
        return preg_replace('/ +/', ' ', $name);
    }
}
