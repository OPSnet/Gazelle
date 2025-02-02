<?php

namespace Gazelle\Manager;

use Gazelle\Enum\NotificationTicketState;
use Gazelle\Enum\NotificationType;

class Notification extends \Gazelle\Base {
    use \Gazelle\Pg;

    // Option types
    final public const OPT_PUSH             = 3;
    final public const OPT_POPUP_PUSH       = 4;
    final public const OPT_TRADITIONAL_PUSH = 5;

    /**
     * This method is called from a scheduled task. Its job is to look
     * for new uploads that have begun to seed, in which case a notification
     * trigger can be sent. The code assumes it is run once a minute.
     * In the first hour of an upload's life, the check is performed once
     * a minute.
     * From the second hour onwards, the check is performed once an hour.
     * The timing is not exact, it may be 61 or 62 minutes between checks
     * but that is sufficient for our purposes.
     */
    public function processBacklog(
        \Gazelle\Manager\NotificationTicket $ticketMan,
        \Gazelle\Manager\Torrent            $torMan,
    ): int {
        $processed = 0;
        $begin     = microtime(true);
        $seen      = [];

        // How many new uploads can we process in almost a minute
        while (microtime(true) < $begin + 50) {
            $ticket = $ticketMan->findByExclusion(NotificationTicketState::Pending, $seen);
            if (is_null($ticket)) {
                $ticket = $ticketMan->findByExclusion(NotificationTicketState::Stale, $seen);
                if (is_null($ticket)) {
                    break;
                }
            }

            // Handle a notification ticket. New uploads will be examined every minute.
            // After one hour, an upload is considered "stale" and is subsequently
            // examined once an hour. The only particular trick to be aware of is that
            // stale tickets still need to be bumped every minute, so that they can be
            // processed once every 60 retries. The act of incrementing can cause a
            // ticket to move into the Stale state.
            $ticket->incrementRetry();

            if ($ticket->isPending() || ($ticket->isStale() && $ticket->retry() % 60 === 0)) {
                if ($this->handleTicket($ticket, $torMan)) {
                    ++$processed;
                }
            }
            $seen[] = $ticket->torrentId();
        }
        return $processed;
    }

    public function handleTicket(\Gazelle\NotificationTicket $ticket, \Gazelle\Manager\Torrent $torMan): bool {
        $torrent = $torMan->findById($ticket->torrentId());
        if (is_null($torrent)) {
            // something happened and the ticket is out of date
            $ticket->setRemoved();
            return false;
        }
        if (!$torrent->isSeedingRealtime()) {
            return false;
        }
        // We are seeding! generate a notification
        if (!in_array('notifications', $torrent->uploader()->paranoia())) {
            $ticket->setActive();
            $notification = new \Gazelle\Notification\Upload($torrent);
            $ticket->setReach($notification->trigger());
        }
        $ticket->setDone();
        return true;
    }

    public function ticketStats(): array {
        return $this->pg()->allByKey("state", "
            with s as (
                select e.enumlabel as state
                from pg_enum e
                inner join pg_type t on (t.oid = e.enumtypid)
                where t.typname = 'nt_state'
            )
            select s.state,
                count(nt.*) as total
            from s
            left join notification_ticket nt on (nt.state::name = s.state)
            group by s.state
        ");
    }

    public function ticketPendingStats(): array {
        return $this->pg()->all("
            with d as (
                select date_trunc('hour', x::timestamptz(0)) as h
                from generate_series(now() - '71 hour'::interval, now(), interval '1 hour') t(x)
            )
            select d.h as hour,
                count(nt.id_torrent) as total
            from d
            left join notification_ticket nt on (nt.created between d.h and d.h + '1 hour'::interval)
            group by d.h
            order by d.h
        ");
    }

    /**
     * Send a push notification to a user
     */
    public function push(array $pushTokens, string $title, string $body, string $url = ''): bool {
        if (!PUSH_SERVER_HOST) {
            return false;
        }

        foreach ($pushTokens as $pushToken) {
            $curl = new \Gazelle\Util\Curl();
            $curl->setUseProxy(false);
            $curl->setPostData($body . "\n" . $url);
            $curl->setOption(CURLOPT_HTTPHEADER, [
                'Title: ' . str_replace(["\r\n", "\n", "\r"], "", $title),
                'Tags: musical_note',
                'Authorization: Bearer ' . PUSH_SERVER_SECRET
            ]
            );
            $curl->fetch(PUSH_SERVER_HOST . $pushToken);
        }
        return true;
    }

    public function allTokens(): array {
        return $this->pg()->column("
            select push_token from user_push_options
        ");
    }

    public function pushableTokens(NotificationType $type): array {
        return $this->pg()->column("
           select po.push_token
           from user_push_options po
           inner join user_has_attr ha using (id_user)
           inner join user_attr ua using (id_user_attr)
           where ua.name = ?
        ", strtolower($type->toString()) . '_push');
    }

    public function pushableTokensById(array $userIds, NotificationType $type): array {
        $token = $this->pg()->scalar("
        select po.push_token
        from user_push_options po
        inner join user_has_attr ha using (id_user)
        inner join user_attr ua using (id_user_attr)
        where ua.name = ?
        and ha.id_user = ANY(?::INT[])
   ", strtolower($type->toString()) . '_push', "{" . implode(',', $userIds) . "}");
        return $token ? [$token] : [];
    }
}
