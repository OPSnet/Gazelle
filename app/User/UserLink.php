<?php

declare(strict_types=1);

namespace Gazelle\User;

use Gazelle\Enum\UserAuditEvent;
use Gazelle\User;

class UserLink extends \Gazelle\BasePgUser {
    final public const tableName = 'user_link';

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
        return is_null($linkInfo) ? [] : [
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
        $sourceGroupId = $linkInfo['id_user_link_group'] ?? null;
        $targetGroupId = $this->groupId($target);

        $this->pg()->begin();
        if ($targetGroupId) {
            if ($targetGroupId === $sourceGroupId) {
                $this->pg()->rollback();
                return false;
            }
            if ($sourceGroupId) {
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
                $this->pg()->query('
                    insert into user_link
                           (id_user, id_user_link_group, id_user_creator)
                    values ($1,      $2,                 $3)
                    ', $this->user->id, $targetGroupId, $admin->id
                );
            }
        } elseif ($sourceGroupId) {
            $this->pg()->query('
                insert into user_link
                       (id_user, id_user_link_group, id_user_creator)
                values ($1,      $2,                 $3)
                ', $target->id, $sourceGroupId, $admin->id
            );
        } else {
            $linkGroupId = $this->pg()->scalarex('
                insert into user_link_group default values
                returning id_user_link_group
            ');
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
        $this->pg()->commit();
        return true;
    }

    public function addGroupComment(string $comments, User $admin, bool $updateNote): bool {
        $this->pg()->begin();
        $groupId = $this->groupId($this->user);
        $oldHash = signature(
            (string)$this->pg()->scalarex("
                select comment from user_link_group where id_user_link_group = $1
                ", $groupId
            ),
            USER_EDIT_SALT
        );
        if ($oldHash === signature($comments, USER_EDIT_SALT)) {
            $this->pg()->rollback();
            return false;
        }
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
        $this->pg()->commit();
        return true;
    }

    public function removeUser(User $target, User $admin): int {
        $targetId = $target->id;
        $this->pg()->begin();
        $target->auditTrail()->addEvent(
            UserAuditEvent::link,
            "[user]{$target->username()}[/user] unlinked",
            $admin,
        );
        $groupId = $this->groupId($target);
        $affected = $this->pg()->query('
            delete from user_link where id_user = $1
            ', $targetId
        );

        // was that the last association in the group?
        $remaining = $this->pg()->scalarex("
            select count(*) from user_link where id_user_link_group = $1
            ", $groupId
        );
        if ($remaining === 1) {
            $affected += $this->pg()->query('
                delete from user_link_group where id_user_link_group = $1
                ', $groupId
            );
        }
        $this->pg()->commit();
        return $affected;
    }
}
