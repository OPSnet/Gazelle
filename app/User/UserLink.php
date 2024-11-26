<?php

namespace Gazelle\User;

class UserLink extends \Gazelle\BaseUser {
    final public const tableName = 'users_dupes';

    public function flush(): static {
        $this->user->flush();
        return $this;
    }

    public function link(): string {
        return $this->user->link();
    }

    public function location(): string {
        return $this->user->location();
    }

    public function groupId(\Gazelle\User $user): ?int {
        $id = (int)self::$db->scalar("
            SELECT GroupID FROM users_dupes WHERE UserID = ?
            ", $user->id()
        );
        return $id ? (int)$id : null;
    }

    public function info(): array {
        $sourceId = $this->user->id();
        [$linkedGroupId, $comment] = self::$db->row("
            SELECT dg.ID, dg.Comments
            FROM dupe_groups AS dg
            INNER JOIN users_dupes AS ud ON (ud.GroupID = dg.ID)
            WHERE ud.UserID = ?
            ", $sourceId
        );
        self::$db->prepared_query("
            SELECT um.ID as user_id,
                um.Username AS username
            FROM users_dupes AS ud
            INNER JOIN users_main AS um ON (um.ID = ud.UserID)
            WHERE ud.GroupID = ?
                AND ud.UserID != ?
            ORDER BY um.ID
            ", $linkedGroupId, $sourceId
        );
        return [
            'id'      => $linkedGroupId,
            'comment' => $comment ?? '',
            'list'    => self::$db->to_pair('user_id', 'username', false),
        ];
    }

    public function dupe(\Gazelle\User $target, \Gazelle\User $admin, bool $updateNote): bool {
        $sourceId = $this->user->id();
        self::$db->begin_transaction();
        [$sourceGroupId, $comments] = self::$db->row("
            SELECT ud.GroupID, dg.Comments
            FROM users_dupes AS ud
            INNER JOIN dupe_groups AS dg ON (dg.ID = ud.GroupID)
            WHERE ud.UserID = ?
            ", $sourceId
        );
        $targetGroupId = $this->groupId($target);

        if ($targetGroupId) {
            if ($targetGroupId === $sourceGroupId) {
                self::$db->rollback();
                return false;
            }
            if ($sourceGroupId) {
                self::$db->prepared_query("
                    UPDATE users_dupes SET
                        GroupID = ?
                    WHERE GroupID = ?
                    ", $targetGroupId, $sourceGroupId
                );
                self::$db->prepared_query("
                    UPDATE dupe_groups SET
                        Comments = concat(?, Comments)
                    WHERE ID = ?
                    ", "$comments\n\n", $targetGroupId
                );
                self::$db->prepared_query("
                    DELETE FROM dupe_groups WHERE ID = ?
                    ", $sourceGroupId
                );
                $linkGroupId = $sourceGroupId;
            } else {
                self::$db->prepared_query("
                    INSERT INTO users_dupes
                           (UserID, GroupID)
                    VALUES (?,      ?)
                    ", $sourceId, $targetGroupId
                );
                $linkGroupId = $targetGroupId;
            }
        } elseif ($sourceGroupId) {
            self::$db->prepared_query("
                INSERT INTO users_dupes
                       (UserID, GroupID)
                VALUES (?,      ?)
                ", $target->id(), $sourceGroupId
            );
            $linkGroupId = $sourceGroupId;
        } else {
            self::$db->prepared_query("INSERT INTO dupe_groups () VALUES ()");
            $linkGroupId = self::$db->inserted_id();
            self::$db->prepared_query("
                INSERT INTO users_dupes
                       (UserID, GroupID)
                VALUES (?,      ?),
                       (?,      ?)
                ", $target->id(), $linkGroupId, $sourceId, $linkGroupId
            );
        }

        if ($updateNote) {
            self::$db->prepared_query("
                UPDATE users_info AS i
                INNER JOIN users_dupes AS d USING (UserID) SET
                    i.AdminComment = concat(now(), ?, i.AdminComment)
                WHERE d.GroupID = ?
                ", " - Linked accounts updated: [user]" . $this->user->username() . "[/user] and [user]"
                    . $target->username() . "[/user] linked by {$admin->username()}\n\n",
                $linkGroupId
            );
        }
        self::$db->commit();
        return true;
    }

    public function addGroupComment(string $comments, \Gazelle\User $admin, bool $updateNote): bool {
        self::$db->begin_transaction();
        $groupId = $this->groupId($this->user);
        $oldHash = signature(
            (string)self::$db->scalar("
                SELECT Comments AS CommentHash
                FROM dupe_groups
                WHERE ID = ?
                ", $groupId
            ),
            USER_EDIT_SALT
        );
        if ($oldHash === signature($comments, USER_EDIT_SALT)) {
            return false;
        }
        self::$db->prepared_query("
            UPDATE dupe_groups SET
                Comments = ?
            WHERE ID = ?
            ", $comments, $groupId
        );
        if ($updateNote) {
            self::$db->prepared_query("
                UPDATE users_info AS i SET
                    i.AdminComment = concat(now(), ?, i.AdminComment)
                WHERE i.UserID = ?
                ",  "- Linked accounts updated: Comments updated by {$admin->username()}\n\n",
                    $this->user->id()
            );
        }
        self::$db->commit();
        return true;
    }

    public function removeUser(\Gazelle\User $target, \Gazelle\User $admin): int {
        $targetId = $target->id();
        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE users_info AS i
            INNER JOIN users_dupes AS d1 ON (d1.UserID = i.UserID)
            INNER JOIN users_dupes AS d2 ON (d2.GroupID = d1.GroupID) SET
                i.AdminComment = concat(now(), ?, i.AdminComment)
            WHERE d2.UserID = ?
            ", " - Linked accounts updated: [user]" . $target->username() . "[/user] unlinked by {$admin->username()}\n\n",
            $targetId
        );
        $groupId = $this->groupId($target);
        self::$db->prepared_query("
            DELETE FROM users_dupes WHERE UserID = ?
            ", $targetId
        );
        $affected = self::$db->affected_rows();

        // was that the last association in the group?
        $remaining = (int)self::$db->scalar("
            SELECT count(*) FROM users_dupes WHERE GroupID = ?
            ", $groupId
        );
        if ($remaining === 1) {
            self::$db->prepared_query("
                DELETE dg, ud
                FROM dupe_groups dg
                INNER JOIN users_dupes ud ON (ud.GroupID = dg.ID)
                WHERE dg.ID = ?
                ", $targetId
            );
            $affected += self::$db->affected_rows();
        }
        self::$db->commit();
        return $affected;
    }
}
