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
        return sprintf('<a href="%s">%s</a>', $this->url(), html_escape($this->name()));
    }

    public function location(): string {
        return "torrents.php?taglist={$this->name()}";
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
        $this->pg()->query("
            update tag set
                usage = usage + $1
            where id_tag = $2
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
                PositiveVotes = PositiveVotes + ?
            ", $this->id, $tgroup->id, $user->id, $weight, TGROUP_TAG_UPVOTE
        );
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            UPDATE tags SET
                Uses = Uses + 1
            WHERE ID = ?
            ", $this->id

        );
        self::$db->commit();
        $this->pg()->scalarex("
            insert into tgroup_tag
                   (id_tgroup, id_tag, id_user)
            values ($1,        $2,     $3)
            on conflict (id_tgroup, id_tag)
                do nothing
            ", $tgroup->id, $this->id, $user->id
        );
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
            $neg = TGROUP_TAG_DOWNVOTE;
            $pos = 0;
        } else {
            $neg = 0;
            $pos = TGROUP_TAG_UPVOTE;
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
            ", $this->id, $tgroup->id, $user->id, $way
        );
        $affected = self::$db->affected_rows();
        self::$db->commit();
        $prior = $this->pg()->scalarex('
            select ttv.upvote
            from tgroup_tag_vote ttv
            inner join tgroup_tag tt using (id_tgroup_tag)
            where tt.id_tgroup = $1
                and tt.id_tag  = $2
                and tt.id_user = $3
            ', $tgroup->id, $this->id, $user->id
        );
        $vote = $way === "up";
        if (is_null($prior)) {
            // they have not yet voted either way
            $this->pg()->query(<<<END_SQL
                insert into tgroup_tag_vote
                    (id_user, upvote, id_tgroup_tag)
                select $3, $4, id_tgroup_tag
                from tgroup_tag
                    where id_tgroup = $1 and id_tag = $2
                END_SQL,
                    $tgroup->id, $this->id, $user->id, $vote
            );
        } else {
            // they have recorded an vote already and this is in the
            // opposite direction, then they are withdrawing their vote.
            // otherwide do nothing
            if ($prior !== $vote) {
                $this->pg()->query('
                    delete from tgroup_tag_vote
                    where id_tgroup = $1
                        and it_tag  = $2
                        and id_user = $3
                    ', $tgroup->id, $this->id, $user->id
                );
            }
        }
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
        $this->pg()->query("
            delete from tgroup_tag
            where id_tgroup = $1
                and id_tag  = $2
            ", $tgroup->id, $this->id
        );

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

    public function remove(): int {
        $this->pg()->query("
            delete from tag where id_tag = $1
            ", $this->id
        );
        return parent::remove();
    }
}
