<?php

namespace Gazelle\Manager;

class Tag extends \Gazelle\Base {

    protected const ID_KEY = 'zz_tag_%d';

    public function findById(int $tagId): ?\Gazelle\Tag {
        $key = sprintf(self::ID_KEY, $tagId);
        $tagId = self::$cache->get_value($key);
        if ($tagId === false) {
            $tagId = self::$db->scalar("
                SELECT ID FROM tags WHERE ID = ?
                ", $tagId
            );
            if ($tagId) {
                self::$cache->cache_value($key, $tagId, 0);
            }
        }
        return $tagId ? new \Gazelle\Tag($tagId) : null;
    }

    public function findByName(string $name) {
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
     * @param string $tagList space-separated list of tags
     */
    public function normalize(string $tagList): string {
        $tags = preg_split('/[\s]+/', $tagList);
        $clean = [];
        foreach ($tags as $t) {
            $clean[$this->sanitize($t)] = 1;
        }
        return implode(' ', array_keys($clean));
    }

    /**
     * Get the ID of a tag
     *
     * @return int ID of tag, or null if no such tag
     */
    public function lookup(string $name): ?int {
        return self::$db->scalar("
            SELECT ID FROM tags WHERE Name = ?
            ", $name
        );
    }

    /**
     * Get the name of an ID
     *
     * @param int $tagId ID of the tag
     */
    public function name(int $tagId): ?string {
        return self::$db->scalar("
            SELECT Name FROM tags WHERE ID = ?
            ", $tagId
        );
    }

    /**
     * Create a tag. If the tag already exists its usage is incremented.
     *
     * @param string $name
     * @param int $userId The id of the user creating the tag.
     */
    public function create(string $name, int $userId): int {
        self::$db->prepared_query("
            INSERT INTO tags
                   (Name, UserID)
            VALUES (?,    ?)
            ON DUPLICATE KEY UPDATE
                Uses = Uses + 1
            ", $this->resolve($this->sanitize($name)), $userId
        );
        return self::$db->inserted_id();
    }

    /**
     * See if a tag is marked as bad (would be replaced by an alias)
     *
     * @see resolve()
     * @return int ID of tag, or null if no such tag
     */
    public function lookupBad(string $name): ?int {
        return self::$db->scalar("
            SELECT ID FROM tag_aliases WHERE BadTag = ?
            ", $name
        );
    }

    /**
     * Make a tag official
     *
     * @param string $name
     * @param int $userId Who is doing the officializing/
     * @return int $tagId id of the officialized tag.
     */
    public function officialize(string $name, int $userId): int {
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
                ", $name, $userId
            );
            $tagId = self::$db->inserted_id();
        }
        self::$cache->delete_value('genre_tags');
        return $tagId;
    }

    /**
     * Make a list of tags unofficial
     *
     * @param array $tagId list of ids to unofficialize
     * @return int Number of tags that were actually unofficialized
     */
    public function unofficialize(array $tagId): int {
        self::$db->prepared_query("
            UPDATE tags SET
                TagType = 'other'
            WHERE ID IN (" . placeholders($tagId) . ")
            ", ...$tagId
        );
        self::$cache->delete_value('genre_tags');
        return self::$db->affected_rows();
    }

    /**
     * Return the list of all official tags
     *
     * @return array [id, name, uses]
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
        $list = self::$cache->get_value('genre_tags');
        if (!$list) {
            self::$db->prepared_query("
                SELECT Name
                FROM tags
                WHERE TagType = 'genre'
                ORDER BY Name
            ");
            $list = self::$db->collect('Name');
            self::$cache->cache_value('genre_tags', $list, 3600 * 24);
        }
        return $list;
    }

    public function rename(int $tagId, string $name, int $userId) {
        self::$db->prepared_query("
            UPDATE tags SET
                Name = ?,
                UserID = ?
            WHERE ID = ?
            ", $name, $userId, $tagId
        );
        return self::$db->affected_rows();
    }

    public function merge(int $currentId, array $replacement, int $userId): int {
        $totalChanged = 0;
        $totalRenamed = 0;
        foreach ($replacement as $r) {
            $replacementId = $this->lookup($r);
            if (is_null($replacementId)) {
                // if we are splitting to more than one tag that does not exist
                // then we can rename the first one but after that we have to
                // begin creating additional tags.
                if ($totalRenamed == 0) {
                    $totalRenamed += $this->rename($currentId, $r, $userId);
                } else {
                    $replacementId = $this->create($r, $userId);
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
                ", $replacementId, $userId, $replacementId, $currentId
            );
            $changed += self::$db->affected_rows();

            // same for artists,
            self::$db->prepared_query('
                INSERT INTO artists_tags (TagID, UserID, ArtistID, PositiveVotes, NegativeVotes)
                    SELECT ?, ?, curr.ArtistID, curr.PositiveVotes, curr.NegativeVotes
                    FROM artists_tags curr
                    LEFT JOIN artists_tags merge ON (merge.ArtistID = curr.ArtistID AND merge.TagID = ?)
                    WHERE curr.TagID = ? AND merge.TagID IS NULL
                ', $replacementId, $userId, $replacementId, $currentId
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
     */
    public function createAlias(string $bad, string $good): int {
        self::$db->prepared_query("
            INSERT INTO tag_aliases
                   (BadTag, AliasTag)
            VALUES (?,     ?)
            ", $this->sanitize($bad), $this->sanitize($good)
        );
        return self::$db->affected_rows();
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
        return self::$db->affected_rows();
    }

    /**
     * Remove the mapping of a bad tag alias.
     */
    public function removeAlias(int $aliasId): int {
        self::$db->prepared_query("
            DELETE FROM tag_aliases WHERE ID = ?
            ", $aliasId
        );
        return self::$db->affected_rows();
    }

    /**
     * Resolve the alias of a tag.
     *
     * @see lookupBad()
     */
    public function resolve($name): string {
        $QueryID = self::$db->get_query_id();
        $resolved = self::$db->scalar("
            SELECT AliasTag FROM tag_aliases WHERE BadTag = ?
            ", $name
        );
        self::$db->set_query_id($QueryID);
        return $resolved ?: $name;
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
                ag.Name     AS artistName,
                tg.ID       AS torrentGroupId,
                tg.Name     AS torrentGroupName
            FROM torrents_group        AS tg
            INNER JOIN torrents_tags   AS t  ON (t.GroupID = tg.ID)
            LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID)
            LEFT JOIN artists_group    AS ag ON (ag.ArtistID = ta.ArtistID)
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
                ag.Name      AS artistName,
                ra.RequestID AS requestId,
                r.Title      AS requestName
            FROM requests              AS r
            INNER JOIN requests_tags   AS t  ON (t.RequestID = r.ID)
            LEFT JOIN requests_artists AS ra ON (r.ID = ra.RequestID)
            LEFT JOIN artists_group    AS ag ON (ag.ArtistID = ra.ArtistID)
            WHERE t.TagID = ?
            ", $tagId
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function createRequestTag(int $tagId, int $requestId): int {
        self::$db->prepared_query("
            INSERT IGNORE INTO requests_tags
                   (TagID, RequestID)
            VALUES (?,     ?)
            ", $tagId, $requestId
        );
        return self::$db->affected_rows();
    }

    public function createTorrentTag(int $tagId, int $groupId, int $userId, int $weight): int {
        self::$db->prepared_query("
            INSERT INTO torrents_tags
                   (TagID, GroupID, UserID, PositiveVotes)
            VALUES (?,     ?,       ?,      ?)
            ON DUPLICATE KEY UPDATE
                PositiveVotes = PositiveVotes + 2
            ", $tagId, $groupId, $userId, $weight
        );
        return self::$db->affected_rows();
    }

    public function createTorrentTagVote(int $tagId, int $groupId, int $userId, string $vote): int {
        self::$db->prepared_query("
            INSERT INTO torrents_tags_votes
                   (TagID, GroupID, UserID, Way)
            VALUES (?,     ?,       ?,      ?)
            ", $tagId, $groupId, $userId, $vote
        );
        return self::$db->affected_rows();
    }

    public function torrentTagHasVote(int $tagId, int $groupId, int $userId): bool {
        self::$db->prepared_query("
            SELECT 1
            FROM torrents_tags_votes
            WHERE TagID = ?
                AND GroupID = ?
                AND UserID = ?
            ", $tagId, $groupId, $userId
        );
        return self::$db->has_results();
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

    public function userTopTagList(int $userId): array {
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
            ", $userId
        );
        return self::$db->collect(0, false);
    }
}
