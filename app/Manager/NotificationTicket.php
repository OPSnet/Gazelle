<?php

namespace Gazelle\Manager;

use Gazelle\Enum\NotificationTicketState;

class NotificationTicket {
    use \Gazelle\Pg;

    public function create(\Gazelle\Torrent $torrent): \Gazelle\NotificationTicket {
        $this->pg()->prepared_query("
            INSERT INTO notification_ticket (id_torrent) VALUES (?)
            ", $torrent->id()
        );
        return $this->findById($torrent->id());
    }

    public function findById(int $torrentId): ?\Gazelle\NotificationTicket {
        if (
            $this->pg()->scalar("
                select 1 from notification_ticket where id_torrent = ?
                ", $torrentId
            )
        ) {
            return new \Gazelle\NotificationTicket($torrentId);
        }
        return null;
    }

    /**
     * Find a ticket that is in a given state. If you keep calling this and
     * there are no other changes to the table, it will return a ticket that
     * has been seen previously. To work around this, the client code can maintain
     * a list of tickets it has seen, so that a subsequent call won't return a
     * ticket that has already been proposed.
     *
     * Think of it as "give me something, but not one of these"
     *
     * In this way, a client can loop over all the valid tickets exactly once
     * and see if any can be processed. The earliest modified ticket is returned.
     */
    public function findByExclusion(NotificationTicketState $state, array $exclude): ?\Gazelle\NotificationTicket {
        $cond = ["state = ?"];
        $args = [$state->value];
        if ($exclude) {
            $cond[] = "id_torrent not in (" . placeholders($exclude) . ")";
            $args = array_merge($args, $exclude);
        }
        $condition = implode(' and ', $cond);
        return $this->findById(
            (int)$this->pg()->scalar("
                select id_torrent
                from notification_ticket
                where $condition
                order by modified asc
                limit 1
                ", ...$args
            )
        );
    }
}
