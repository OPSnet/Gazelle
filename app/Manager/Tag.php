<?php

namespace Gazelle\Manager;

class Tag extends \Gazelle\Base {
    /**
     * Get a tag ready for database input and display.
     * Trim whitespace, force to lower case, internal spaces and dashes become dots,
     * remove all that is not alphanumeric + dot,
     * remove leading and trailing dots and remove doubled-up dots.
     *
     * @param string $tag
     * @return string cleaned-up version of $tag
     */
    public function sanitize($tag) {
        return preg_replace('/\.+/', '.',         // remove doubled-up dots
            trim(                                 // trim leading, trailing dots
                preg_replace('/[^a-z0-9.]+/', '', // remove non alphanum, dot
                    str_replace([' ', '-'], '.',  // dash and internal space to dot
                        strtolower(               // lowercase
                            trim($tag)            // whitespace
                        )
                    )
                ), '.' // trim-a-dot
            )
        );
    }

    /**
     * Normalize a list of tags (sanitize them and remove duplicates)
     *
     * @param string space-separated list of tags
     * @param string tidy list of space-separated tags
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
     * @param string $tag
     * @return int ID of tag, or null if no such tag
     */
    public function lookup(string $tag): int {
        return $this->db->scalar("
            SELECT ID FROM tags WHERE Name = ?
            ", $tag
        );
    }

    /**
     * Get the name of an ID
     *
     * @param int $id ID of the tag
     * @return string $name Name of the tag
     */
    public function name(int $id): ?string {
        return $this->db->scalar("
            SELECT Name FROM tags WHERE ID = ?
            ", $id
        );
    }

    /**
     * Create a tag. If the tag already exists its usage is incremented.
     *
     * @param string $tag
     * @param int $userId The id of the user creating the tag.
     * @return int ID of tag
     */
    public function create(string $name, int $userId): int {
        $this->db->prepared_query("
            INSERT INTO tags
                   (Name, UserID)
            VALUES (?,    ?)
            ON DUPLICATE KEY UPDATE
                Uses = Uses + 1
            ", $this->resolve($this->sanitize($name)), $userId
        );
        return $this->db->inserted_id();
    }

    /**
     * See if a tag is marked as bad (would be replaced by an alias)
     *
     * @see resolve()
     * @param string $tag
     * @return int ID of tag, or null if no such tag
     */
    public function lookupBad(string $tag): ?int {
        return $this->db->scalar("
            SELECT ID FROM tag_aliases WHERE BadTag = ?
            ", $tag
        );
    }

    /**
     * Make a tag official
     *
     * @param string $tag
     * @param int $userId Who is doing the officializing/
     * @return int $tagId id of the officialized tag.
     */
    public function officialize(string $tag, int $userId): int {
        $tag = $this->sanitize($tag);
        $id = $this->lookup($tag);
        if ($id) {
            // Tag already exists
            $this->db->prepared_query("
                UPDATE tags SET
                    TagType = 'genre'
                WHERE ID = ?
                ", $id
            );
        } else {
            // Tag doesn't exist yet: create it
            $this->db->prepared_query("
                INSERT INTO tags
                       (Name, UserID, TagType, Uses)
                VALUES (?,    ?,      'genre', 0)
                ", $tag, $userId
            );
            $id = $this->db->inserted_id();
        }
        return $id;
    }

    /**
     * Make a list of tags unofficial
     *
     * @param array $id list of ids to unofficialize
     * @return int Number of tags that were actually unofficialized
     */
    public function unofficialize(array $id): int {
        $this->db->prepared_query("
            UPDATE tags SET
                TagType = 'other'
            WHERE ID IN (" . placeholders($id) . ")
            ", ...$id
        );
        return $this->db->affected_rows();
    }

    /**
     * Return an iterator to loop over all the official tags.
     *
     * @param int $gather number of rows to gather per iteration
     * @return array [id, name, uses]
     */
    public function listOfficial($columns, $order = 'name'): array {
        $orderBy = $order == 'name' ? '2, 3 DESC' : '3 DESC, 2';
        $this->db->prepared_query("
            SELECT ID AS id, Name AS name, Uses AS uses
            FROM tags
            WHERE TagType = ?
            ORDER BY $orderBy
            ", 'genre'
        );
        $list = $this->db->to_array('id', MYSQLI_ASSOC);
        $n = count($list);
        $result = [];
        if ($n < $columns) {
            foreach ($list as $l) {
                $result[] = [$l];
            }
        } else {
            for ($i = 0; $i < $columns - 1; ++$i) {
                $column = (int)ceil(count($list) / ($columns - $i));
                $result[] = array_splice($list, 0, $column);
            }
            $result = array_merge($result, [$list]);
        }
        return $result;
    }

    /**
     * Get the names of all genre tags
     *
     * @return array List of names
     */
    public function genreList(): array {
        $list = $this->cache->get_value('genre_tags');
        if (!$list) {
            $this->db->prepared_query("
                SELECT Name
                FROM tags
                WHERE TagType = 'genre'
                ORDER BY Name
            ");
            $list = $this->db->collect('Name');
            $this->cache->cache_value('genre_tags', $list, 3600 * 24);
        }
        return $list;
    }

    /**
     * Rename a tag
     *
     * @param int $tagId
     * @param string $tag new tag name
     * @param int $userId who is doing the rename
     * @return int number of affected rows (should be 0 or 1)
     */
    public function rename(int $tagId, string $name, int $userId) {
        $this->db->prepared_query("
            UPDATE tags SET
                Name = ?,
                UserID = ?
            WHERE ID = ?
            ", $name, $userId, $tagId
        );
        return $this->db->affected_rows();
    }

    /**
     * Merge (or split) a tag
     *
     * @param int $currentId The tag that will be removed in the merge
     * @param array $replacement The replacement tags for $currentId
     * @param int $userID The ID of the moderator performing the merge
     * @return int number of items changed by the merge
     */
    public function merge(int $currentId, array $replacement, int $userId) {
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
            $this->db->prepared_query("
                INSERT INTO torrents_tags (TagID, UserID, GroupID, PositiveVotes, NegativeVotes)
                    SELECT ?, ?, curr.GroupID, curr.PositiveVotes, curr.NegativeVotes
                    FROM torrents_tags curr
                    LEFT JOIN torrents_tags merge ON (merge.GroupID = curr.GroupID AND merge.TagID = ?)
                    WHERE curr.TagID = ? AND merge.TagID IS NULL
                ", $replacementId, $userId, $replacementId, $currentId
            );
            $changed += $this->db->affected_rows();

            // same for artists,
            $this->db->prepared_query('
                INSERT INTO artists_tags (TagID, UserID, ArtistID, PositiveVotes, NegativeVotes)
                    SELECT ?, ?, curr.ArtistID, curr.PositiveVotes, curr.NegativeVotes
                    FROM artists_tags curr
                    LEFT JOIN artists_tags merge ON (merge.ArtistID = curr.ArtistID AND merge.TagID = ?)
                    WHERE curr.TagID = ? AND merge.TagID IS NULL
                ', $replacementId, $userId, $replacementId, $currentId
            );
            $changed += $this->db->affected_rows();

            // and requests.
            $this->db->prepared_query("
                INSERT INTO requests_tags (TagID, RequestID)
                    SELECT ?, curr.RequestID
                    FROM requests_tags curr
                    LEFT JOIN requests_tags merge ON (merge.RequestID = curr.RequestID AND merge.TagID = ?)
                    WHERE curr.TagID = ? AND merge.TagID IS NULL
                ", $replacementId, $replacementId, $currentId
            );
            $changed += $this->db->affected_rows();

            // update usage count for replacement tag
            $this->db->prepared_query("
                UPDATE tags SET
                    Uses = Uses + ?
                WHERE ID = ?
                ", $changed, $replacementId
            );
            $totalChanged += $changed;
        }

        // Kill the old tag everywhere
        $this->db->prepared_query("
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
     *
     * @param string $bad The bad tag name (to be replaced upon usage by)
     * @param string $good The good name.
     * @return int Number of rows added (0 or 1)
     */
    public function createAlias(string $bad, string $good): int {
        $this->db->prepared_query("
            INSERT INTO tag_aliases
                   (BadTag, AliasTag)
            VALUES (?,     ?)
            ", $this->sanitize($bad), $this->sanitize($good)
        );
        return $this->db->affected_rows();
    }

    /**
     * Modify the mapping of a bad tag alias to a acceptable alias
     *
     * @param int $aliasId The id of the alias to change
     * @param string $bad The bad tag name (to be replaced upon usage by)
     * @param string $good The good name.
     * @return int Number of rows changed (0 or 1)
     */
    public function modifyAlias(int $aliasId, string $bad, string $good): int {
        $this->db->prepared_query("
            UPDATE tag_aliases SET
                BadTag = ?,
                AliasTag = ?
            WHERE ID = ?
            ", $this->sanitize($bad), $this->sanitize($good), $aliasId
        );
        return $this->db->affected_rows();
    }

    /**
     * Remove the mapping of a bad tag alias.
     *
     * @param int $aliasId The id of the alias to remove
     * @return int Number of rows deleted (0 or 1)
     */
    public function removeAlias(int $aliasId): int {
        $this->db->prepared_query("
            DELETE FROM tag_aliases WHERE ID = ?
            ", $aliasId
        );
        return $this->db->affected_rows();
    }

    /**
     * Resolve the alias of a tag.
     *
     * @see lookupBad()
     * @param string $tag the name we want to change if has an alias
     * @return string The resolved tag name, its alias or itself
     */
    public function resolve($name): string {
        $QueryID = $this->db->get_query_id();
        $resolved = $this->db->scalar("
            SELECT AliasTag
            FROM tag_aliases
            WHERE BadTag = ?
            ", $name
        );
        $this->db->set_query_id($QueryID);
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
        $this->db->prepared_query("
            SELECT ID AS id, BadTag AS bad, AliasTag AS alias
            FROM tag_aliases
            ORDER BY $column
        ");
        return $this->db->to_array('id', MYSQLI_ASSOC, false);
    }

    /**
     * Get the list of torrents matched by a tag
     *
     * @param int $tagId
     * @return array [artistId, artistName, torrentGroupId, torrentGroupName]
     * (artist elements may be null)
     */
    public function torrentLookup(int $tagId): array {
        $this->db->prepared_query("
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
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Get the list of torrents matched by a tag
     *
     * @param int $tagId
     * @return array [artistId, artistName, requestId, requestName]
     * (artist elements may be null)
     */
    public function requestLookup(int $tagId): array {
        $this->db->prepared_query("
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
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Create a tag on a request
     *
     * @param int $tagId The id of the tag
     * @param int $requestId The id of the request
     * @return int Number of rows affected
     */
    public function createRequestTag(int $tagId, int $requestId): int {
        $this->db->prepared_query("
            INSERT IGNORE INTO requests_tags
                   (TagID, RequestID)
            VALUES (?,     ?)
            ", $tagId, $requestId
        );
        return $this->db->affected_rows();
    }

    /**
     * Create a tag on a torrent group
     *
     * @param int $tagId The id of the tag
     * @param int $groupId The id of the torrent group
     * @param int $userId The id of the user
     * @param int $weight The the weight of this addition
     * @return int Number of rows affected
     */
    public function createTorrentTag(int $tagId, int $groupId, int $userId, int $weight): int {
        $this->db->prepared_query("
            INSERT INTO torrents_tags
                   (TagID, GroupID, UserID, PositiveVotes)
            VALUES (?,     ?,       ?,      ?)
            ON DUPLICATE KEY UPDATE
                PositiveVotes = PositiveVotes + 2
            ", $tagId, $groupId, $userId, $weight
        );
        return $this->db->affected_rows();
    }

    /**
     * Create a tag vote on a torrent group
     *
     * @param int $tagId The id of the tag
     * @param int $groupId The id of the torrent group
     * @param int $userId The id of the user
     * @param string $vote 'up' or 'down'
     *
     * @return int Number of rows affected
     */
    public function createTorrentTagVote(int $tagId, int $groupId, int $userId, string $vote): int {
        $this->db->prepared_query("
            INSERT INTO torrents_tags_votes
                   (TagID, GroupID, UserID, Way)
            VALUES (?,     ?,       ?,      ?)
            ", $tagId, $groupId, $userId, $vote
        );
        return $this->db->affected_rows();
    }

    /**
     * Check if a user has voted on a torrent tag
     *
     * @param int $tagId The id of the tag
     * @param int $groupId The id of the torrent group
     * @param int $userId The id of the user
     * @return bool True if the user as already voted on this tag
     */
    public function torrentTagHasVote(int $tagId, int $groupId, int $userId): bool {
        $this->db->prepared_query("
            SELECT 1
            FROM torrents_tags_votes
            WHERE TagID = ?
                AND GroupID = ?
                AND UserID = ?
            ", $tagId, $groupId, $userId
        );
        return $this->db->has_results();
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

        if (($suggestions = $this->cache->get($key)) == false) {
            $this->db->prepared_query("
                SELECT Name
                FROM tags
                WHERE (Uses > 700 OR TagType = 'genre')
                    AND Name REGEXP concat('^', ?)
                ORDER BY TagType = 'genre' DESC, Uses DESC
                LIMIT ?
                ", $word, 10
            );
            $suggestions = $this->db->to_array(false, MYSQLI_NUM, false);
            $this->cache->cache_value($key, $suggestions, 1800 + 7200 * ($maxKeySize - $keySize)); // Can't cache things for too long in case names are edited
        }
        return array_map(function ($v) { return ['value' => $v[0]]; }, $suggestions);
    }
}
