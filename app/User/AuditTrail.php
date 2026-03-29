<?php

namespace Gazelle\User;

use Gazelle\Enum\UserAuditEvent;
use Gazelle\Enum\UserAuditOrder;

class AuditTrail extends \Gazelle\BaseUser {
    public function flush(): static {
        return $this;
    }

    public function addEvent(UserAuditEvent $event, string $note, \Gazelle\User|null $creator = null): int {
        return $this->pg()->insert("
            insert into user_audit_trail
                   (id_user, event, note, id_user_creator)
            values (?,       ?,     ?,    ?)
            returning id_user_audit_trail
            ", $this->user->id, $event->value, $note, $creator?->id
        );
    }

    /**
     * Used to migrate the old staff notes into the audit trail
     */
    public function addHistoricalEvent(string $date, string $note, \Gazelle\Manager\User $manager): int {
        $creator = null;
        if (str_starts_with($note, 'Disabled for inactivity')) {
            $event = UserAuditEvent::activity;
        } elseif (str_starts_with($note, 'Class changed to ')) {
            $event = UserAuditEvent::userclass;
        } elseif (preg_match('/^Leeching (?:ability|privileges) suspended |Taken off ratio watch /', $note)) {
            $note = str_replace('Leeching ability', 'Leeching privileges', $note); // consistency
            $event = UserAuditEvent::ratio;
        } else {
            if (preg_match('/( by ([\w.-]+))/', $note, $match)) {
                $creator = $manager->find("@{$match[2]}");
                $note    = str_replace($match[1], '.', $note);
            }
            $event = UserAuditEvent::historical;
        }
        return $this->pg()->insert("
            insert into user_audit_trail
                   (id_user, event, note, created, id_user_creator)
            values (?,       ?,     ?,    ?,       ?)
            returning id_user_audit_trail
            ", $this->user->id, $event->value, $note, $date, (int)$creator?->id
        );
    }

    public function hasEvent(UserAuditEvent $event): bool {
        return (bool)$this->pg()->scalarex("
            select 1
            from user_audit_trail
            where id_user = $1
                and event = $2
            ", $this->user->id, $event->value
        );
    }

    public function eventList(array $idList, UserAuditOrder $order = UserAuditOrder::created): array {
        return $this->pg()->all("
            select id_user_audit_trail,
                id_user_creator,
                event,
                note,
                created
            from user_audit_trail
            where id_user = ?
                and id_user_audit_trail in (" . placeholders($idList) . ")
            order by {$order->value}
            ", $this->user->id, ...$idList
        );
    }

    public function fullEventList(UserAuditOrder $order = UserAuditOrder::created): array {
        return $this->pg()->all("
            select id_user_audit_trail,
                id_user_creator,
                event,
                note,
                created
            from user_audit_trail
            where id_user = ?
            order by {$order->value}
            ", $this->user->id
        );
    }

    /**
     * Return the id of the most recent audit event. (0 if no events)
     */
    public function lastEventId(): int {
        return (int)$this->pg()->scalarex("
            select id_user_audit_trail
            from user_audit_trail
            where id_user = $1
            order by id_user_audit_trail desc
            limit 1
            ", $this->user->id
        );
    }

    public function removeEvent(int $eventId): int {
        return $this->pg()->query("
            delete from user_audit_trail
            where id_user_audit_trail = $1
                and id_user = $2
            ", $eventId, $this->user->id
        );
    }

    public function resetAuditTrail(): int {
        return $this->pg()->prepared_query("
            delete from user_audit_trail where id_user = ?
            ", $this->user->id
        );
    }

    /**
     * When updating a list of events, copy the list over to the revision table.
     * Then remove all the events in the audit table except for the oldest one,
     * and update its note with the new contents.
     */
    public function modifyEventList(array $idList, string $note, \Gazelle\User $user): int {
        $affected = $this->pg()->prepared_query("
            insert into user_audit_trail_revision
            (id_user_audit_trail, id_user, id_user_creator, created, event, note, revision)
            select uat.id_user_audit_trail, 
                uat.id_user,
                uat.id_user_creator,
                uat.created,
                uat.event,
                uat.note,
                1 + count(uatr.id_user_audit_trail) as revision
            from user_audit_trail uat
            left join user_audit_trail_revision uatr using (id_user_audit_trail)
            where id_user_audit_trail in (" . placeholders($idList) . ")
            group by uat.id_user_audit_trail
            ", ...$idList
        );
        if (!$note) {
            // delete
            $this->pg()->prepared_query("
                delete from user_audit_trail
                where id_user_audit_trail in (" . placeholders($idList) . ")
                ", ...$idList
            );
        } elseif (count($idList)) {
            // edit
            $eventId = min($idList);
            $this->pg()->prepared_query("
                delete from user_audit_trail
                where id_user_audit_trail != ?
                    and id_user_audit_trail in (" . placeholders($idList) . ")
                ", $eventId, ...$idList
            );
            $this->pg()->prepared_query("
                update user_audit_trail set
                    note = ?,
                    id_user_creator = ?
                where id_user_audit_trail = ?
                ", $note, $user->id, $eventId
            );
        }
        return $affected;
    }
}
