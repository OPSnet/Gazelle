<?php

namespace Gazelle\User;

use Gazelle\Enum\UserAuditEvent;
use Gazelle\Enum\UserAuditOrder;

class AuditTrail extends \Gazelle\BaseUser {
    use \Gazelle\Pg;

    public function flush(): static {
        return $this;
    }

    public function addEvent(UserAuditEvent $event, string $note, ?\Gazelle\User $creator = null): int {
        return $this->pg()->insert("
            insert into user_audit_trail
                   (id_user, event, note, id_user_creator)
            values (?,       ?,     ?,    ?)
            returning id_user_audit_trail
            ", $this->id(), $event->value, $note, (int)$creator?->id()
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
            ", $this->id(), $event->value, $note, $date, (int)$creator?->id()
        );
    }

    public function hasEvent(UserAuditEvent $event): bool {
        return (bool)$this->pg()->scalar("
            select 1
            from user_audit_trail
            where id_user = ?
                and event = ?
            ", $this->id(), $event->value
        );
    }

    public function eventList(UserAuditOrder $order = UserAuditOrder::created): array {
        return $this->pg()->all("
            select id_user_audit_trail,
                event,
                note,
                created
            from user_audit_trail
            where id_user = ?
            order by {$order->value}
            ", $this->id()
        );
    }

    /**
     * Migrate the old users_info.AdminComments to the new audit trail.
     * Returns 0 if the user has already been migrated, otherwise
     * returns the id_user_audit_trail value
     */
    public function migrate(\Gazelle\Manager\User $manager): int {
        if ($this->hasEvent(UserAuditEvent::historical)) {
            return 0;
        }
        $prevDate   = false;
        $lastEvent  = 0;
        // split the legacy staff notes into separate entries
        $historical = preg_split("/\r?\n\r?\n/", $this->user->staffNotes());
        if ($historical) {
            foreach (array_reverse($historical) as $entry) {
                if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) - (.*)/s', $entry, $match)) {
                    $date = $match[1];
                    $note = $match[2];
                } else {
                    // this note is not prefixed with a timestamp. If this is the
                    // first note, use the date the user was created, otherwise
                    // use the timestamp of the previous event.
                    $date = $prevDate === false ? $this->user->created() : $prevDate;
                    $note = $entry;
                }
                $lastEvent = $this->addHistoricalEvent($date, $note, $manager);
                $prevDate  = $date;
            }
        }
        if ($lastEvent === 0) {
            $lastEvent = $this->addEvent(UserAuditEvent::historical, "no prior staff notes");
        }
        return $lastEvent;
    }

    public function removeEvent(int $eventId): int {
        return $this->pg()->prepared_query("
            delete from user_audit_trail
            where id_user_audit_trail = ?
                and id_user = ?
            ", $eventId, $this->id()
        );
    }

    public function resetAuditTrail(): int {
        return $this->pg()->prepared_query("
            delete from user_audit_trail where id_user = ?
            ", $this->id()
        );
    }
}
