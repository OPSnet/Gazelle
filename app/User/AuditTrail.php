<?php

namespace Gazelle\User;

use Gazelle\Enum\UserAuditEvent;
use Gazelle\Enum\UserAuditOrder;

class AuditTrail extends \Gazelle\BaseUser {
    public function flush(): static {
        return $this;
    }

    public function addEvent(UserAuditEvent $event, string $note, \Gazelle\User|null $creator = null): int {
        return $this->pg()->scalarex("
            insert into audit_trail_user
                   (id_user, event, note, id_user_creator)
            values ($1,      $2,    $3,   $4)
            returning id_audit_trail_user
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
        return $this->pg()->scalarex("
            insert into audit_trail_user
                   (id_user, event, note, id_user_creator, created)
            values ($1,      $2,    $3,   $4,              $5)
            returning id_audit_trail_user
            ", $this->user->id, $event->value, $note, $creator?->id, $date
        );
    }

    public function hasEvent(UserAuditEvent $event): bool {
        return (bool)$this->pg()->scalarex("
            select 1
            from audit_trail_user
            where id_user = $1
                and event = $2
            ", $this->user->id, $event->value
        );
    }

    public function eventList(array $idList, UserAuditOrder $order = UserAuditOrder::created): array {
        return $this->pg()->allex("
            select id_audit_trail_user,
                id_user_creator,
                event,
                note,
                created
            from audit_trail_user
            where id_user = $1
                and id_audit_trail_user = any($2)
            order by {$order->value}
            ", $this->user->id, $this->pg()->integerConverter()->output($idList)
        );
    }

    public function fullEventList(UserAuditOrder $order = UserAuditOrder::created): array {
        return $this->pg()->allex("
            select id_audit_trail_user,
                id_user_creator,
                event,
                note,
                created
            from audit_trail_user
            where id_user = $1
            order by {$order->value}
            ", $this->user->id
        );
    }

    /**
     * Return the details of the most recent audit event.
     */
    public function lastEvent(): array {
        return $this->pg()->rowAssocex("
            select id_audit_trail_user as id,
                id_user_creator,
                created,
                event,
                note
            from audit_trail_user
            where id_user = $1
            order by id_audit_trail_user desc
            limit 1
            ", $this->user->id
        );
    }

    public function removeEvent(int $eventId): int {
        return $this->pg()->query("
            delete from audit_trail_user
            where id_audit_trail_user = $1
                and id_user = $2
            ", $eventId, $this->user->id
        );
    }

    public function resetAuditTrail(): int {
        return $this->pg()->query("
            delete from audit_trail_user where id_user = $1
            ", $this->user->id
        );
    }

    /**
     * When updating a list of events, copy the list over to the revision table.
     * Then remove all the events in the audit table except for the oldest one,
     * and update its note with the new contents.
     */
    public function modifyEventList(array $idList, string $note, \Gazelle\User $user): int {
        $affected = $this->pg()->query("
            insert into audit_trail_user_revision
            (id_audit_trail_user, id_user, id_user_creator, created, event, note, revision)
            select atu.id_audit_trail_user, 
                atu.id_user,
                atu.id_user_creator,
                atu.created,
                atu.event,
                atu.note,
                1 + count(atur.id_audit_trail_user) as revision
            from audit_trail_user atu
            left join audit_trail_user_revision atur using (id_audit_trail_user)
            where id_audit_trail_user = any($1)
            group by atu.id_audit_trail_user,
                atu.id_user,
                atu.id_user_creator,
                atu.created,
                atu.event,
                atu.note
            ", $this->pg()->integerConverter()->output($idList)
        );
        if (!$note) {
            // delete
            $this->pg()->query("
                delete from audit_trail_user
                where id_audit_trail_user = any($1)
                ", $this->pg()->integerConverter()->output($idList)
            );
        } elseif (count($idList)) {
            // edit
            $eventId = min($idList);
            $this->pg()->query("
                delete from audit_trail_user
                where id_audit_trail_user != $1
                    and id_audit_trail_user = any($2)
                ", $eventId, $this->pg()->integerConverter()->output($idList)
            );
            $this->pg()->query("
                update audit_trail_user set
                    note = $1,
                    id_user_creator = $2
                where id_audit_trail_user = $3
                ", $note, $user->id, $eventId
            );
        }
        return $affected;
    }
}
