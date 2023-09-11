<?php

namespace Gazelle\Manager;

class UserLink extends \Gazelle\BaseUser {
    final const tableName = 'users_dupes';

    public function flush(): UserLink { $this->user()->flush(); return $this; }
    public function link(): string { return $this->user()->link(); }
    public function location(): string { return $this->user()->location(); }

    public function groupId(\Gazelle\User $user): ?int {
        return self::$db->scalar("
            SELECT GroupID
            FROM users_dupes
            WHERE UserID = ?
            ", $user->id()
        );
    }

    public function dupe(\Gazelle\User $target, string $adminUsername, bool $updateNote): bool {
        $sourceId = $this->user->id();
        [$sourceGroupId, $comments] = self::$db->row("
            SELECT u.GroupID, d.Comments
            FROM users_dupes AS u
            INNER JOIN dupe_groups AS d ON (d.ID = u.GroupID)
            WHERE u.UserID = ?
            ", $sourceId
        );
        $targetGroupId = $this->groupId($target);

        if ($targetGroupId) {
            if ($targetGroupId == $sourceGroupId) {
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
                    . $target->username() . "[/user] linked by {$adminUsername}\n\n",
                $linkGroupId
            );
        }
        return true;
    }

    function addGroupComments(string $comments, string $adminName, bool $updateNote) {
        $groupId = $this->groupId($this->user);
        $oldHash = self::$db->scalar("
            SELECT sha1(Comments) AS CommentHash
            FROM dupe_groups
            WHERE ID = ?
            ", $groupId
        );
        $newHash = sha1($comments);
        if ($oldHash === $newHash) {
            return;
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
                ",  "- Linked accounts updated: Comments updated by {$adminName}\n\n",
                    $this->user->id()
            );
        }
    }

    function info(): array {
        $sourceId = $this->user->id();
        [$linkedGroupId, $comments] = self::$db->row("
            SELECT d.ID, d.Comments
            FROM dupe_groups AS d
            INNER JOIN users_dupes AS u ON (u.GroupID = d.ID)
            WHERE u.UserID = ?
            ", $sourceId
        );
        self::$db->prepared_query("
            SELECT um.ID as user_id,
                um.Username AS username
            FROM users_dupes AS d
            INNER JOIN users_main AS um ON (um.ID = d.UserID)
            WHERE d.GroupID = ?
                AND d.UserID != ?
            ORDER BY um.ID
            ", $linkedGroupId, $sourceId
        );
        return [$linkedGroupId, $comments ?? '', self::$db->to_array(false, MYSQLI_ASSOC, false)];
    }

    function remove(\Gazelle\User $target, string $adminName) {
        $targetId = $target->id();
        self::$db->prepared_query("
            UPDATE users_info AS i
            INNER JOIN users_dupes AS d1 ON (d1.UserID = i.UserID)
            INNER JOIN users_dupes AS d2 ON (d2.GroupID = d1.GroupID) SET
                i.AdminComment = concat(now(), ?, i.AdminComment)
            WHERE d2.UserID = ?
            ", " - Linked accounts updated: [user]" . $target->username() . "[/user] unlinked by $adminName\n\n"
            , $targetId
        );
        self::$db->prepared_query("
            DELETE FROM users_dupes WHERE UserID = ?
            ", $targetId
        );
        self::$db->prepared_query("
            DELETE g.*
            FROM dupe_groups AS g
            LEFT JOIN users_dupes AS u ON (u.GroupID = g.ID)
            WHERE u.GroupID IS NULL
        ");
    }

    function removeGroup(int $linkGroupId) {
        self::$db->prepared_query("
            DELETE FROM dupe_groups WHERE ID = ?
            ", $linkGroupId
        );
    }
}
