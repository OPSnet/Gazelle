<?php

declare(strict_types=1);

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

    public function groupId(User $user): int|null {
        return $this->pg()->scalarex('
            select id_user_link_group from user_link where id_user = $1
            ', $user->id
        );
    }

    public function linkInfo(): array|null {
        return $this->pg()->rowAssocex("
            select ulg.id_user_link_group,
                ulg.comment
            from user_link_group ulg
            inner join user_link ul using (id_user_link_group)
            where ul.id_user = $1
            ", $this->user->id
        );
    }

    public function info(): array {
        $linkInfo = $this->linkInfo();
        return [
            'id'      => $linkInfo['id_user_link_group'],
            'comment' => $linkInfo['comment'] ?? '',
            'list'    => $this->pg()->pair(
                'user_id',
                'username',
                <<<END_SQL
                    select ul.id_user as user_id,
                        um."Username" as username
                    from user_link ul
                    inner join relay.users_main um on (um."ID" = ul.id_user)
                    where ul.id_user_link_group = $1
                        and ul.id_user != $2
                    order by ul.id_user
                END_SQL,
                    $linkInfo['id_user_link_group'], $this->user->id
            ),
        ];
    }

    public function dupe(User $target, User $admin, bool $updateNote): bool {
        $linkInfo      = $this->linkInfo();
        $sourceGroupId = $linkInfo['id_user_link_group'];
        $targetGroupId = $this->groupId($target);

        self::$db->begin_transaction();
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
                    ", "{$linkInfo['comment']}\n\n", $targetGroupId
                );
                self::$db->prepared_query("
                    DELETE FROM dupe_groups WHERE ID = ?
                    ", $sourceGroupId
                );
                $this->pg()->query('
                    update user_link set
                        id_user_link_group = $1
                    where id_user_link_group = $2
                    ', $targetGroupId, $sourceGroupId
                );
                $this->pg()->query('
                    update user_link_group set
                        comment = $1 || comment
                    where id_user_link_group = $2
                    ', "{$linkInfo['comment']}\n\n", $targetGroupId
                );
                $this->pg()->query('
                    delete from user_link_group where id_user_link_group = $1
                    ', $sourceGroupId
                );
            } else {
                self::$db->prepared_query("
                    INSERT INTO users_dupes
                           (UserID, GroupID)
                    VALUES (?,      ?)
                    ", $this->user->id, $targetGroupId
                );
                $this->pg()->query('
                    insert into user_link
                           (id_user, id_user_link_group, id_user_creator)
                    values ($1,      $2,                 $3)
                    ', $this->user->id, $targetGroupId, $admin->id
                );
            }
        } elseif ($sourceGroupId) {
            self::$db->prepared_query("
                INSERT INTO users_dupes
                       (UserID, GroupID)
                VALUES (?,      ?)
                ", $target->id, $sourceGroupId
            );
            $this->pg()->query('
                insert into user_link
                       (id_user, id_user_link_group, id_user_creator)
                values ($1,      $2,                 $3)
                ', $target->id, $sourceGroupId, $admin->id
            );
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
                ", $target->id, $linkGroupId, $this->user->id, $linkGroupId
            );
            $this->pg()->query('
                insert into user_link_group (id_user_link_group) values ($1)
                ', $linkGroupId
            );
            $this->pg()->query('
                insert into user_link
                       (id_user, id_user_link_group, id_user_creator)
                values ($1,      $2,                 $3),
                       ($4,      $2,                 $3)
                ', $target->id, $linkGroupId, $admin->id,
                $this->user->id,
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
            (string)$this->pg()->scalarex("
                select comment from user_link_group where id_user_link_group = $1
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
        $this->pg()->query('
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
        $this->pg()->query('
            delete from user_link where id_user = $1
            ', $targetId
        );

        // was that the last association in the group?
        $remaining = $this->pg()->scalarex("
            select count(*) from user_link where id_user_link_group = $1
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
            $this->pg()->query('
                delete from user_link_group where id_user_link_group = $1
                ', $groupId
            );
        }
        self::$db->commit();
        return $affected;
    }
}
