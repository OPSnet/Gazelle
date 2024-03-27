<?php

namespace Gazelle\Manager;

class Tag extends \Gazelle\BaseManager {
    protected const ID_KEY    = 'zz_tag_%d';
    protected const ALIAS_KEY = 'tag_aliases_search';
    protected const GENRE_KEY = 'tag_genre';

    /**
     * Create a tag. If the tag already exists its usage is incremented.
     */
    public function create(string $name, \Gazelle\User $user): int {
        self::$db->prepared_query("
            INSERT INTO tags
                   (Name, UserID)
            VALUES (?,    ?)
            ON DUPLICATE KEY UPDATE
                Uses = Uses + 1
            ", $this->resolve($this->sanitize($name)), $user->id()
        );
        return self::$db->inserted_id();
    }

    public function findById(int $tagId): ?\Gazelle\Tag {
        $key = sprintf(self::ID_KEY, $tagId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = (int)self::$db->scalar("
                SELECT ID FROM tags WHERE ID = ?
                ", $tagId
            );
            if ($id) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\Tag($id) : null;
    }

    public function findByName(string $name): ?\Gazelle\Tag {
        return $this->findById((int)self::$db->scalar("
            SELECT ID FROM tags WHERE Name = ?
            ", $name
        ));
    }

    /**
     * Get a tag ready for database input and display.
     * Trim whitespace, force to lower case, internal spaces and dashes become dots,
     * remove all that is not alphanumeric + dot,
     * remove leading and trailing dots and remove doubled-up dots.
     */
    public function sanitize(string $name): string {
        return preg_replace('/\.+/', '.',         // remove doubled-up dots
            trim(                                 // trim leading, trailing dots
                preg_replace('/[^a-z0-9.]+/', '', // remove non alphanum, dot
                    str_replace([' ', '-'], '.',  // dash and internal space to dot
                        strtolower(               // lowercase
                            trim($name)            // whitespace
                        )
                    )
                ), '.' // trim-a-dot
            )
        );
    }

    /**
     * Normalize a list of tags (sanitize them and remove duplicates)
     *
     * $tagList space-separated list of tags
     */
    public function normalize(string $tagList): string {
        $tags = preg_split('/[\s]+/', trim($tagList));
        if ($tags === false) {
            return '';
        }
        $clean = [];
        foreach ($tags as $t) {
            $clean[$this->sanitize($t)] = 1;
        }
        return implode(' ', array_keys($clean));
    }

    /**
     * Get the ID of a tag, or null if no such tag
     */
    public function lookup(string $name): ?int {
        $tagId = self::$db->scalar("
            SELECT ID FROM tags WHERE Name = ?
            ", $name
        );
        return is_null($tagId) ? null : (int)$tagId;
    }

    /**
     * Get the name of an ID, or null if no such tag
     */
    public function name(int $tagId): ?string {
        $name = self::$db->scalar("
            SELECT Name FROM tags WHERE ID = ?
            ", $tagId
        );
        return is_null($name) ? null : (string)$name;
    }

    /**
     * Resolve the alias of a tag.
     *
     * @see lookupBad()
     */
    public function resolve(string $name): ?string {
        $resolved = self::$db->scalar("
            SELECT AliasTag FROM tag_aliases WHERE BadTag = ?
            ", $name
        );
        return is_null($resolved) ? $name : (string)$resolved;
    }

    /**
     * See if a tag is marked as bad (would be replaced by an alias)
     *
     * @see resolve()
     * return ID of tag, or null if no such tag
     */
    public function lookupBad(string $name): ?int {
        return (int)self::$db->scalar("
            SELECT ID FROM tag_aliases WHERE BadTag = ?
            ", $name
        );
    }

    /**
     * Make a tag official
     *
     * return id of the officialized tag.
     */
    public function officialize(string $name, \Gazelle\User $user): int {
        $name = $this->sanitize($name);
        $tagId = $this->lookup($name);
        if ($tagId) {
            // Tag already exists
            self::$db->prepared_query("
                UPDATE tags SET
                    TagType = 'genre'
                WHERE ID = ?
                ", $tagId
            );
        } else {
            // Tag doesn't exist yet: create it
            self::$db->prepared_query("
                INSERT INTO tags
                       (Name, UserID, TagType, Uses)
                VALUES (?,    ?,      'genre', 0)
                ", $name, $user->id()
            );
            $tagId = self::$db->inserted_id();
        }
        self::$cache->delete_value(self::GENRE_KEY);
        return $tagId;
    }

    /**
     * Make a list of tags unofficial
     *
     * $tagId list of ids to unofficialize
     * return number of tags that were actually unofficialized
     */
    public function unofficialize(array $tagId): int {
        self::$db->prepared_query("
            UPDATE tags SET
                TagType = 'other'
            WHERE ID IN (" . placeholders($tagId) . ")
            ", ...$tagId
        );
        self::$cache->delete_value(self::GENRE_KEY);
        return self::$db->affected_rows();
    }

    /**
     * Return the list of all official tags
     *
     * return array [id, name, uses]
     */
    public function officialList($order = 'name'): array {
        $orderBy = $order == 'name' ? '2, 3 DESC' : '3 DESC, 2';
        self::$db->prepared_query("
            SELECT ID AS id, Name AS name, Uses AS uses
            FROM tags
            WHERE TagType = ?
            ORDER BY $orderBy
            ", 'genre'
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Get the names of all genre tags
     *
     * @return array List of names
     */
    public function genreList(): array {
        $list = self::$cache->get_value(self::GENRE_KEY);
        if (!$list) {
            self::$db->prepared_query("
                SELECT Name AS name
                FROM tags
                WHERE TagType = 'genre'
                ORDER BY Name
            ");
            $list = self::$db->collect('name', false);
            self::$cache->cache_value(self::GENRE_KEY, $list, 3600 * 24);
        }
        return $list;
    }

    public function rename(int $tagId, string $name, \Gazelle\User $user): int {
        self::$db->prepared_query("
            UPDATE tags SET
                Name = ?,
                UserID = ?
            WHERE ID = ?
            ", $name, $user->id(), $tagId
        );
        return self::$db->affected_rows();
    }

    public function merge(int $currentId, array $replacement, \Gazelle\User $user): int {
        $totalChanged = 0;
        $totalRenamed = 0;
        foreach ($replacement as $r) {
            $replacementId = $this->lookup($r);
            if (is_null($replacementId)) {
                // if we are splitting to more than one tag that does not exist
                // then we can rename the first one but after that we have to
                // begin creating additional tags.
                if ($totalRenamed == 0) {
                    $totalRenamed += $this->rename($currentId, $r, $user);
                } else {
                    $replacementId = $this->create($r, $user);
                    ++$totalRenamed;
                }
                continue;
            }

            // If the torrent has the old tag, but not the replacement, add it,
            $changed = 0;
            self::$db->prepared_query("
                INSERT INTO torrents_tags (TagID, UserID, GroupID, PositiveVotes, NegativeVotes)
                    SELECT ?, ?, curr.GroupID, curr.PositiveVotes, curr.NegativeVotes
                    FROM torrents_tags curr
                    LEFT JOIN torrents_tags merge ON (merge.GroupID = curr.GroupID AND merge.TagID = ?)
                    WHERE curr.TagID = ? AND merge.TagID IS NULL
                ", $replacementId, $user->id(), $replacementId, $currentId
            );
            $changed += self::$db->affected_rows();

            // same for artists,
            self::$db->prepared_query('
                INSERT INTO artists_tags (TagID, UserID, ArtistID, PositiveVotes, NegativeVotes)
                    SELECT ?, ?, curr.ArtistID, curr.PositiveVotes, curr.NegativeVotes
                    FROM artists_tags curr
                    LEFT JOIN artists_tags merge ON (merge.ArtistID = curr.ArtistID AND merge.TagID = ?)
                    WHERE curr.TagID = ? AND merge.TagID IS NULL
                ', $replacementId, $user->id(), $replacementId, $currentId
            );
            $changed += self::$db->affected_rows();

            // and requests.
            self::$db->prepared_query("
                INSERT INTO requests_tags (TagID, RequestID)
                    SELECT ?, curr.RequestID
                    FROM requests_tags curr
                    LEFT JOIN requests_tags merge ON (merge.RequestID = curr.RequestID AND merge.TagID = ?)
                    WHERE curr.TagID = ? AND merge.TagID IS NULL
                ", $replacementId, $replacementId, $currentId
            );
            $changed += self::$db->affected_rows();

            // update usage count for replacement tag
            self::$db->prepared_query("
                UPDATE tags SET
                    Uses = Uses + ?
                WHERE ID = ?
                ", $changed, $replacementId
            );
            $totalChanged += $changed;
        }

        // Kill the old tag everywhere
        self::$db->prepared_query("
            DELETE t, at, rt, tt
            FROM tags t
            LEFT JOIN artists_tags  at ON (at.TagID = t.ID)
            LEFT JOIN requests_tags rt ON (rt.TagID = t.ID)
            LEFT JOIN torrents_tags tt ON (tt.TagID = t.ID)
            WHERE t.ID = ?
            ", $currentId
        );
        return $totalChanged + $totalRenamed;
    }

    /**
     * Add a mapping of a bad tag alias to a acceptable alias
     * Returns the alias ID
     */
    public function createAlias(string $bad, string $good): int {
        self::$db->prepared_query("
            INSERT INTO tag_aliases
                   (BadTag, AliasTag)
            VALUES (?,     ?)
            ", $this->sanitize($bad), $this->sanitize($good)
        );
        $id = self::$db->inserted_id();
        self::$cache->delete_value(self::ALIAS_KEY);
        return $id;
    }

    /**
     * Modify the mapping of a bad tag alias to a acceptable alias
     */
    public function modifyAlias(int $aliasId, string $bad, string $good): int {
        self::$db->prepared_query("
            UPDATE tag_aliases SET
                BadTag = ?,
                AliasTag = ?
            WHERE ID = ?
            ", $this->sanitize($bad), $this->sanitize($good), $aliasId
        );
        $affected = self::$db->affected_rows();
        self::$cache->delete_value(self::ALIAS_KEY);
        return $affected;
    }

    /**
     * Remove the mapping of a bad tag alias.
     */
    public function removeAlias(int $aliasId): int {
        self::$db->prepared_query("
            DELETE FROM tag_aliases WHERE ID = ?
            ", $aliasId
        );
        $affected = self::$db->affected_rows();
        self::$cache->delete_value(self::ALIAS_KEY);
        return $affected;
    }

    public function aliasList(): array {
        $aliasList = self::$cache->get_value(self::ALIAS_KEY);
        if ($aliasList === false) {
            self::$db->prepared_query("
                SELECT ID, BadTag, AliasTag
                FROM tag_aliases
                ORDER BY BadTag
            ");
            $aliasList = self::$db->to_array(false, MYSQLI_ASSOC, false);
            // Unify tag aliases to be in_this_format as tags not in.this.format
            array_walk_recursive($aliasList, function (&$val, $key) {
                $val = strtr($val, '.', '_');
            });
            // Clean up the array for smaller cache size
            foreach ($aliasList as &$TagAlias) {
                foreach (array_keys($TagAlias) as $Key) {
                    if (is_numeric($Key)) {
                        unset($TagAlias[$Key]);
                    }
                }
            }
            self::$cache->cache_value(self::ALIAS_KEY, $aliasList, 3600 * 24 * 7); // cache for 7 days
        }
        return $aliasList;
    }

    /**
     * Replace bad tags with tag aliases
     */
    public function replaceAliasList(array $Tags): array {
        $TagAliases = $this->aliasList();

        if (isset($Tags['include'])) {
            $End = count($Tags['include']);
            for ($i = 0; $i < $End; $i++) {
                foreach ($TagAliases as $TagAlias) {
                    if ($Tags['include'][$i] === $TagAlias['BadTag']) {
                        $Tags['include'][$i] = $TagAlias['AliasTag'];
                        break;
                    }
                }
            }
            // Only keep unique entries after unifying tag standard
            $Tags['include'] = array_unique($Tags['include']);
        }

        if (isset($Tags['exclude'])) {
            $End = count($Tags['exclude']);
            for ($i = 0; $i < $End; $i++) {
                foreach ($TagAliases as $TagAlias) {
                    if (substr($Tags['exclude'][$i], 1) === $TagAlias['BadTag']) {
                        $Tags['exclude'][$i] = '!' . $TagAlias['AliasTag'];
                        break;
                    }
                }
            }
            // Only keep unique entries after unifying tag standard
            $Tags['exclude'] = array_unique($Tags['exclude']);
        }
        return $Tags;
    }

    /**
     * Return the list of aliases
     *
     * @param bool $orderByBad true to order by bad, otherwise alias
     * @return array list of [id, bad, alias]
     */
    public function listAlias(bool $orderByBad): array {
        $column = $orderByBad ? 2 : 3;
        self::$db->prepared_query("
            SELECT ID AS id, BadTag AS bad, AliasTag AS alias
            FROM tag_aliases
            ORDER BY $column
        ");
        return self::$db->to_array('id', MYSQLI_ASSOC, false);
    }

    /**
     * Get the list of torrents matched by a tag
     *
     * @return array [artistId, artistName, torrentGroupId, torrentGroupName]
     * (artist elements may be null)
     */
    public function torrentLookup(int $tagId): array {
        self::$db->prepared_query("
            SELECT
                ag.ArtistID AS artistId,
                aa.Name     AS artistName,
                tg.ID       AS torrentGroupId,
                tg.Name     AS torrentGroupName
            FROM torrents_group        AS tg
            INNER JOIN torrents_tags   AS t  ON (t.GroupID = tg.ID)
            LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID)
            LEFT JOIN artists_group    AS ag ON (ag.ArtistID = ta.ArtistID)
            INNER JOIN artists_alias      aa ON (ag.PrimaryAlias = aa.AliasID)
            WHERE t.TagID = ?
            ", $tagId
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Get the list of torrents matched by a tag
     *
     * @return array [artistId, artistName, requestId, requestName]
     * (artist elements may be null)
     */
    public function requestLookup(int $tagId): array {
        self::$db->prepared_query("
            SELECT
                ag.ArtistID  AS artistId,
                aa.Name      AS artistName,
                ra.RequestID AS requestId,
                r.Title      AS requestName
            FROM requests              AS r
            INNER JOIN requests_tags   AS t  ON (t.RequestID = r.ID)
            LEFT JOIN requests_artists AS ra ON (r.ID = ra.RequestID)
            LEFT JOIN artists_group    AS ag ON (ag.ArtistID = ra.ArtistID)
            INNER JOIN artists_alias      aa ON (ag.PrimaryAlias = aa.AliasID)
            WHERE t.TagID = ?
            ", $tagId
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function createTorrentTag(int $tagId, \Gazelle\TGroup $tgroup, \Gazelle\User $user, int $weight): int {
        self::$db->prepared_query("
            INSERT INTO torrents_tags
                   (TagID, GroupID, UserID, PositiveVotes)
            VALUES (?,     ?,       ?,      ?)
            ON DUPLICATE KEY UPDATE
                PositiveVotes = PositiveVotes + 2
            ", $tagId, $tgroup->id(), $user->id(), $weight
        );
        return self::$db->affected_rows();
    }

    public function createTorrentTagVote(int $tagId, \Gazelle\TGroup $tgroup, \Gazelle\User $user, string $vote): int {
        self::$db->prepared_query("
            INSERT INTO torrents_tags_votes
                   (TagID, GroupID, UserID, Way)
            VALUES (?,     ?,       ?,      ?)
            ", $tagId, $tgroup->id(), $user->id(), $vote
        );
        return self::$db->affected_rows();
    }

    public function torrentTagHasVote(int $tagId, \Gazelle\TGroup $tgroup, \Gazelle\User $user): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM torrents_tags_votes
            WHERE TagID = ?
                AND GroupID = ?
                AND UserID = ?
            ", $tagId, $tgroup->id(), $user->id()
        );
    }

    /**
     * Get some autocomplete tags encoded in JSON
     *
     * @param string $word the stem of the tags to search for
     * @return array of array of JSON key=>value names
     *      [['value' => 'tag1'], ['value' => 'tag2'], ...]]
     */
    public function autocompleteAsJson(string $word): array {
        $maxKeySize = 4;
        $keySize = min($maxKeySize, max(1, strlen($word)));
        $letters = strtolower(substr($word, 0, $keySize));
        $key = "autocomplete_tags_{$keySize}_$letters";

        if (($suggestions = self::$cache->get($key)) == false) {
            self::$db->prepared_query("
                SELECT Name
                FROM tags
                WHERE (Uses > 700 OR TagType = 'genre')
                    AND Name REGEXP concat('^', ?)
                ORDER BY TagType = 'genre' DESC, Uses DESC
                LIMIT ?
                ", $word, 10
            );
            $suggestions = self::$db->to_array(false, MYSQLI_NUM, false);
            self::$cache->cache_value($key, $suggestions, 1800 + 7200 * ($maxKeySize - $keySize)); // Can't cache things for too long in case names are edited
        }
        return array_map(fn($v) => ['value' => $v[0]], $suggestions);
    }

    public function userTopTagList(\Gazelle\User $user): array {
        self::$db->prepared_query("
            SELECT tags.Name
            FROM xbt_snatched AS s
            INNER JOIN torrents AS t ON (t.ID = s.fid)
            INNER JOIN torrents_group AS g ON (t.GroupID = g.ID)
            INNER JOIN torrents_tags AS tt ON (tt.GroupID = g.ID)
            INNER JOIN tags ON (tags.ID = tt.TagID)
            WHERE g.CategoryID = 1
                AND tags.Uses > 10
                AND s.uid = ?
            GROUP BY tt.TagID
            ORDER BY ((count(tags.Name) - 2) * (sum(tt.PositiveVotes) - sum(tt.NegativeVotes))) / (tags.Uses * 0.8) DESC
            LIMIT 8
            ", $user->id()
        );
        return self::$db->collect(0, false);
    }

    /**
     * Filters a list of include and exclude tags to be used in a Sphinx search
     * $Tags An array of tags with sub-arrays 'include' and 'exclude'
     * $EnableNegation Sphinx needs at least one positive search condition to support the NOT operator
     * $TagType Search for Any or All of these tags.
     * return array Array keys predicate and input
     *               Predicate for a Sphinx 'taglist' query
     *               Input contains clean, aliased tags. Use it in a form instead of the user submitted string
     */
    public function sphinxFilter(array $Tags, bool $EnableNegation, bool $allTags): array {
        $QueryParts = [];
        $Tags = $this->replaceAliasList($Tags);
        $TagList = str_replace('_', '.', implode(', ', array_merge($Tags['include'], $Tags['exclude'])));

        if (!$EnableNegation && !empty($Tags['exclude'])) {
            $Tags['include'] = array_merge($Tags['include'], $Tags['exclude']);
            unset($Tags['exclude']);
        }

        foreach ($Tags['include'] as &$Tag) {
            $Tag = \Sphinxql::sph_escape_string($Tag);
        }

        if (!empty($Tags['exclude'])) {
            foreach ($Tags['exclude'] as &$Tag) {
                $Tag = '!' . \Sphinxql::sph_escape_string(substr($Tag, 1));
            }
        }

        if ($allTags) {
            $QueryParts[] = implode(' ', array_merge($Tags['include'], $Tags['exclude']));
        } else {
            // Any
            if (!empty($Tags['include'])) {
                $QueryParts[] = '( ' . implode(' | ', $Tags['include']) . ' )';
            }
            if (!empty($Tags['exclude'])) {
                $QueryParts[] = implode(' ', $Tags['exclude']);
            }
        }

        return ['input' => $TagList, 'predicate' => implode(' ', $QueryParts)];
    }

    protected function topList(string $key, int $limit, string $query): array {
        $top = self::$cache->get_value($key);
        if ($top === false) {
            self::$db->prepared_query($query, $limit);
            $top = [];
            foreach (self::$db->to_array(false, MYSQLI_ASSOC, false) as $row) {
                $top[] = [
                    'name'     => $row['name'],
                    'uses'     => $row['uses'],
                    'posVotes' => (int)$row['posVotes'], // sum() returns a string
                    'negVotes' => (int)$row['negVotes'],
                ];
            }
            self::$cache->cache_value($key, $top, 3600 * 12);
        }
        return $top;
    }

    public function topTGroupList(int $limit): array {
        return $this->topList(
            "toptaguse_$limit",
            $limit,
            "
                SELECT t.Name                 AS name,
                    count(*)                  AS uses,
                    sum(tt.PositiveVotes - 1) AS posVotes,
                    sum(tt.NegativeVotes - 1) AS negVotes
                FROM tags AS t
                INNER JOIN torrents_tags AS tt ON (tt.TagID = t.ID)
                GROUP BY tt.TagID
                ORDER BY Uses DESC
                LIMIT ?
            ",
        );
    }

    public function topRequestList(int $limit): array {
        return $this->topList(
            "toptagreq_$limit",
            $limit,
            "
                SELECT t.Name AS name,
                    count(*)  AS uses,
                    0         AS posVotes,
                    0         AS netVotes
                FROM tags AS t
                INNER JOIN requests_tags AS r ON (r.TagID = t.ID)
                GROUP BY r.TagID
                ORDER BY Uses DESC
                LIMIT ?
            ",
        );
    }

    public function topVotedList(int $limit): array {
        return $this->topList(
            "toptagvote_$limit",
            $limit,
            "
                SELECT t.Name                 AS name,
                    count(*)                  AS uses,
                    sum(tt.PositiveVotes - 1) AS posVotes,
                    sum(tt.NegativeVotes - 1) AS negVotes
                FROM tags AS t
                INNER JOIN torrents_tags AS tt ON (tt.TagID = t.ID)
                GROUP BY tt.TagID
                ORDER BY PosVotes DESC
                LIMIT ?
            ",
        );
    }
}
