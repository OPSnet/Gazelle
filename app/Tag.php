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

    public function link(): string {
        return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->name()));
    }

    public function location(): string {
        return 'torrents.php?taglist=' . $this->name();
    }

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
        $affected = $this->pg()->query("
            update request set
                tag = array_append(tag, $2)
            where id_request = $1
                and not tag @> array[$2::int]
            ", $request->id, $this->id
        );
        self::$db->prepared_query("
            UPDATE tags SET
                Uses = Uses + ?
            WHERE ID = ?
            ", $affected, $this->id
        );
        $request->flush();
        return $affected;
    }

    /**
     * Get the list of requests matched by a tag
     *
     * @return array [artistId, artistName, requestId, requestName]
     * (artist elements may be null)
     */
    public function requestList(): array {
        return $this->pg()->allex(<<<END_SQL
            select
                aa."ArtistID" as artistId,
                aa."Name"     as artistName,
                r.id_request  as requestId,
                r.title       as requestName
            from request                  r
            left join request_artist      ra using (id_request)
            left join relay.artists_group ag on (ag."PrimaryAlias" = ra.id_alias)
            left join relay.artists_alias aa on (aa."AliasID" = ag."PrimaryAlias")
            where r.tag @> array[$1::int]
            END_SQL
            , $this->id
        );
    }

    public function addTGroup(TGroup $tgroup, User $user, int $weight): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO torrents_tags
                   (TagID, GroupID, UserID, PositiveVotes)
            VALUES (?,     ?,       ?,      ?)
            ON DUPLICATE KEY UPDATE
                PositiveVotes = PositiveVotes + 2
            ", $this->id, $tgroup->id(), $user->id, $weight
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
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function voteTGroup(TGroup $tgroup, User $user, string $way): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            SELECT TagID
            FROM torrents_tags_votes
            WHERE TagID = ?
                AND GroupID = ?
                AND UserID = ?
                AND Way = ?
            ", $this->id, $tgroup->id(), $user->id, $way
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
            ", $this->id, $tgroup->id(), $user->id, $way
        );
        $affected = self::$db->affected_rows();
        self::$db->commit();
        $tgroup->flush();
        return $affected;
    }

    public function hasVoteTGroup(TGroup $tgroup, User $user): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM torrents_tags_votes
            WHERE TagID = ?
                AND GroupID = ?
                AND UserID = ?
            ", $this->id, $tgroup->id(), $user->id
        );
    }

    public function removeTGroup(TGroup $tgroup): bool {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM torrents_tags_votes WHERE GroupID = ? AND TagID = ?
            ", $tgroup->id, $this->id
        );
        self::$db->prepared_query("
            DELETE FROM torrents_tags WHERE GroupID = ? AND TagID = ?
            ", $tgroup->id, $this->id
        );
        $affected = self::$db->affected_rows();
        if (!$affected) {
            self::$db->rollback();
            return false;
        }

        // Was this the last occurrence?
        $inUse = self::$db->scalar("
            SELECT 1 FROM torrents_tags WHERE TagID = ? LIMIT 1
            ", $this->id
        )
        || $this->pg()->scalarex("
            select 1 from request where tag @> array[$1::int] limit 1
            ", $this->id
        );
        $affected += $inUse ? 0 : $this->remove();

        self::$db->commit();
        $tgroup->refresh();
        return $affected != 0;
    }
}
