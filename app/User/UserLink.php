<?php

namespace Gazelle\User;

use Gazelle\Enum\UserAuditEvent;
use Gazelle\User;

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

    public function groupId(User $user): ?int {
        $id = self::$db->scalar("
            SELECT GroupID FROM users_dupes WHERE UserID = ?
            ", $user->id
        );
        return is_null($id) ? null : (int)$id;
    }

    public function info(): array {
        $sourceId = $this->user->id;
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
            'list'    => self::$db->to_pair('user_id', 'username'),
        ];
    }

    public function dupe(User $target, User $admin, bool $updateNote): bool {
        $sourceId = $this->user->id;
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
                $this->pg()->executeParams('
                    update user_link set
                        id_user_link_group = $1
                    where id_user_link_group = $2
                    ', $targetGroupId, $sourceGroupId
                );
                $this->pg()->executeParams('
                    update user_link_group set
                        comment = $1 || comment
                    where id_user_link_group = $2
                    ', "$comments\n\n", $targetGroupId
                );
                $this->pg()->executeParams('
                    delete from user_link_group where id_user_link_group = $1
                    ', $sourceGroupId
                );
                $linkGroupId = $sourceGroupId;
            } else {
                self::$db->prepared_query("
                    INSERT INTO users_dupes
                           (UserID, GroupID)
                    VALUES (?,      ?)
                    ", $sourceId, $targetGroupId
                );
                $this->pg()->executeParams('
                    insert into user_link
                           (id_user, id_user_link_group, id_user_creator)
                    values ($1,      $2,                 $3)
                    ', $sourceId, $targetGroupId, $admin->id
                );
                $linkGroupId = $targetGroupId;
            }
        } elseif ($sourceGroupId) {
            self::$db->prepared_query("
                INSERT INTO users_dupes
                       (UserID, GroupID)
                VALUES (?,      ?)
                ", $target->id, $sourceGroupId
            );
            $this->pg()->executeParams('
                insert into user_link
                       (id_user, id_user_link_group, id_user_creator)
                values ($1,      $2,                 $3)
                ', $target->id, $sourceGroupId, $admin->id
            );
            $linkGroupId = $sourceGroupId;
        } else {
            self::$db->prepared_query("
                INSERT INTO dupe_groups () VALUES ()
            ");
            $linkGroupId = self::$db->inserted_id();
            self::$db->prepared_query("
                INSERT INTO users_dupes
                       (UserID, GroupID)
                VALUES (?,      ?),
                       (?,      ?)
                ", $target->id, $linkGroupId, $sourceId, $linkGroupId
            );
            $this->pg()->executeParams('
                insert into user_link_group (id_user_link_group) values ($1)
                ', $linkGroupId
            );
            $this->pg()->executeParams('
                insert into user_link
                       (id_user, id_user_link_group, id_user_creator)
                values ($1,      $2,                 $3),
                       ($4,      $5,                 $6)
                ', $target->id, $linkGroupId, $admin->id,
                $sourceId, $linkGroupId, $admin->id,
            );
        }

        if ($updateNote) {
            $this->user->auditTrail()->addEvent(
                UserAuditEvent::link,
                "[user]{$this->user->username()}[/user] and [user]{$target->username()}[/user] linked",
                $admin,
            );
            $target->auditTrail()->addEvent(
                UserAuditEvent::link,
                "[user]{$this->user->username()}[/user] and [user]{$target->username()}[/user] linked",
                $admin,
            );
        }
        self::$db->commit();
        return true;
    }

    public function addGroupComment(string $comments, User $admin, bool $updateNote): bool {
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
        $this->pg()->executeParams('
            update user_link_group set
                comment = $1
            where id_user_link_group = $2
            ', $comments, $groupId
        );
        if ($updateNote) {
            $this->user->auditTrail()->addEvent(
                UserAuditEvent::link,
                "Comments updated",
                $admin,
            );
        }
        self::$db->commit();
        return true;
    }

    public function removeUser(User $target, User $admin): int {
        $targetId = $target->id;
        $target->auditTrail()->addEvent(
            UserAuditEvent::link,
            "[user]{$target->username()}[/user] unlinked",
            $admin,
        );
        $groupId = $this->groupId($target);
        self::$db->prepared_query("
            DELETE FROM users_dupes WHERE UserID = ?
            ", $targetId
        );
        $affected = self::$db->affected_rows();
        $this->pg()->executeParams('
            delete from user_link where id_user = $1
            ', $targetId
        );

        // was that the last association in the group?
        $remaining = (int)self::$db->scalar("
            SELECT count(*) FROM users_dupes WHERE GroupID = ?
            ", $groupId
        );
        if ($remaining === 1) {
            self::$db->prepared_query("
                DELETE FROM users_dupes WHERE GroupID = ?
                ", $groupId
            );
            $affected += self::$db->affected_rows();
            self::$db->prepared_query("
                DELETE FROM dupe_groups dg WHERE ID = ?
                ", $groupId
            );
            $affected += self::$db->affected_rows();
            $this->pg()->executeParams('
                delete from user_link_group where id_user_link_group = $1
                ', $groupId
            );
        }
        self::$db->commit();
        return $affected;
    }
}
