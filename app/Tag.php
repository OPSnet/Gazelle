<?php

namespace Gazelle;

/**
 * The Tag class knows about Request and TGroup, and as much as possible,
 * the latter have no knowledge of tags (they know how to display tags,
 * but do not perform any management). This is why we attach a Request
 * or a TGroup to a tag and not the converse. This helps place more code
 * here, and less in the Request and TGroup classes, which are already
 * large enough.
 */

class Tag extends BaseObject {
    final public const tableName = 'tags';

    public function flush(): static {
        unset($this->info);
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->name())); }
    public function location(): string { return 'torrents.php?taglist=' . $this->name(); }

    public function info(): array {
        return $this->info ??= self::$db->rowAssoc("
            SELECT t.Name AS name,
                t.TagType AS type,
                t.Uses    AS uses,
                t.UserID  AS user_id
            FROM tags t
            WHERE t.ID = ?
            ", $this->id
        );
    }

    public function name(): string {
        return $this->info()['name'];
    }

    /**
     * Tag type
     * @return string one of 'genre' or 'other' ('genre' designates an official tag).
     */
    public function type(): string {
        return $this->info()['type'];
    }

    /**
     * Number of uses of the tag.
     */
    public function uses(): int {
        return $this->info()['uses'];
    }

    /**
     * Who created the tag.
     */
    public function userId(): int {
        return $this->info()['user_id'];
    }

    public function addRequest(Request $request): int {
        self::$db->prepared_query("
            INSERT IGNORE INTO requests_tags
                   (TagID, RequestID)
            VALUES (?,     ?)
            ", $this->id, $request->id()
        );
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE tags SET
                Uses = Uses + 1
            WHERE ID = ?
            ", $this->id
        );
        $request->updateSphinx();
        return $affected;
    }

    /**
     * Get the list of requests matched by a tag
     *
     * @return array [artistId, artistName, requestId, requestName]
     * (artist elements may be null)
     */
    public function requestList(): array {
        self::$db->prepared_query("
            SELECT
                aa.ArtistID  AS artistId,
                aa.Name      AS artistName,
                ra.RequestID AS requestId,
                r.Title      AS requestName
            FROM requests              r
            INNER JOIN requests_tags   t  ON (t.RequestID = r.ID)
            LEFT JOIN requests_artists ra ON (r.ID = ra.RequestID)
            LEFT JOIN artists_group    ag ON (ag.PrimaryAlias = ra.AliasID)
            LEFT JOIN artists_alias    aa ON (ag.PrimaryAlias = aa.AliasID)
            WHERE t.TagID = ?
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function addTGroup(\Gazelle\TGroup $tgroup, \Gazelle\User $user, int $weight): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO torrents_tags
                   (TagID, GroupID, UserID, PositiveVotes)
            VALUES (?,     ?,       ?,      ?)
            ON DUPLICATE KEY UPDATE
                PositiveVotes = PositiveVotes + 2
            ", $this->id, $tgroup->id(), $user->id(), $weight
        );
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE tags SET
                Uses = Uses + 1
            WHERE ID = ?
            ", $this->id

        );
        self::$db->commit();
        $tgroup->refresh();
        return $affected;
    }

    /**
     * Get the list of torrents matched by a tag
     *
     * @return array [artistId, artistName, torrentGroupId, torrentGroupName]
     * (artist elements may be null)
     */
    public function tgroupList(): array {
        self::$db->prepared_query("
            SELECT
                aa.ArtistID AS artistId,
                aa.Name     AS artistName,
                tg.ID       AS torrentGroupId,
                tg.Name     AS torrentGroupName
            FROM torrents_group        tg
            INNER JOIN torrents_tags   t  ON (t.GroupID = tg.ID)
            LEFT JOIN torrents_artists ta ON (ta.GroupID = tg.ID)
            LEFT JOIN artists_group    ag ON (ag.PrimaryAlias = ta.AliasID)
            LEFT JOIN artists_alias    aa ON (ag.PrimaryAlias = aa.AliasID)
            WHERE t.TagID = ?
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function voteTGroup(\Gazelle\TGroup $tgroup, \Gazelle\User $user, string $way): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            SELECT TagID
            FROM torrents_tags_votes
            WHERE TagID = ?
                AND GroupID = ?
                AND UserID = ?
                AND Way = ?
            ", $this->id, $tgroup->id(), $user->id(), $way
        );
        if (self::$db->has_results()) {
            self::$db->rollback();
            return 0;
        }
        if ($way == 'down') {
            $neg = 1;
            $pos = 0;
        } else {
            $neg = 0;
            $pos = 2;
        }
        self::$db->prepared_query("
            UPDATE torrents_tags SET
                NegativeVotes = NegativeVotes + ?,
                PositiveVotes = PositiveVotes + ?
            WHERE TagID = ?
                AND GroupID = ?
            ", $neg, $pos, $this->id, $tgroup->id()
        );
        self::$db->prepared_query("
            INSERT INTO torrents_tags_votes
                   (TagID, GroupID, UserID, Way)
            VALUES (?,     ?,       ?,      ?)
            ", $this->id, $tgroup->id(), $user->id(), $way
        );
        $affected = self::$db->affected_rows();
        self::$db->commit();
        $tgroup->flush();
        return $affected;
    }

    public function hasVoteTGroup(\Gazelle\TGroup $tgroup, \Gazelle\User $user): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM torrents_tags_votes
            WHERE TagID = ?
                AND GroupID = ?
                AND UserID = ?
            ", $this->id, $tgroup->id(), $user->id()
        );
    }

    public function removeTGroup(\Gazelle\TGroup $tgroup): bool {
        $tgroupId = $tgroup->id();
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM torrents_tags_votes WHERE GroupID = ? AND TagID = ?
            ", $tgroupId, $this->id
        );
        self::$db->prepared_query("
            DELETE FROM torrents_tags WHERE GroupID = ? AND TagID = ?
            ", $tgroupId, $this->id
        );
        if (!self::$db->affected_rows()) {
            return false;
        }

        // Was this the last occurrence?
        $inUse = (int)self::$db->scalar("
            SELECT count(*) FROM torrents_tags WHERE TagID = ?
            ", $this->id
        ) + (int)self::$db->scalar("
            SELECT count(*)
            FROM requests_tags rt
            INNER JOIN requests r ON (r.ID = rt.RequestID)
            WHERE r.FillerID = 0 /* TODO: change to DEFAULT NULL */
                AND rt.TagID = ?
            ", $this->id
        );
        if (!$inUse) {
            self::$db->prepared_query("
                DELETE FROM tags WHERE ID = ?
                ", $this->id
            );
        }

        self::$db->commit();
        $tgroup->refresh();
        return true;
    }
}
