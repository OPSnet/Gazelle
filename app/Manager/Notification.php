<?php

namespace Gazelle\Manager;

use Gazelle\Enum\NotificationTicketState;

class Notification extends \Gazelle\Base {
    use \Gazelle\Pg;

    // Option types
    final public const OPT_PUSH             = 3;
    final public const OPT_POPUP_PUSH       = 4;
    final public const OPT_TRADITIONAL_PUSH = 5;

    // Types. These names must correspond to column names in users_notifications_settings
    final public const NEWS         = 'News';
    final public const BLOG         = 'Blog';
    final public const INBOX        = 'Inbox';
    final public const QUOTES       = 'Quotes';
    final public const GLOBALNOTICE = 'Global';

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
    public function push(array $UserIDs, string $Title, string $Body, string $URL = '', string $Type = self::GLOBALNOTICE): void {
        if (!PUSH_SOCKET_LISTEN_ADDRESS) {
            return;
        }
        foreach ($UserIDs as $UserID) {
            $UserID = (int)$UserID;
            $QueryID = self::$db->get_query_id();
            $SQL = "
                SELECT
                    p.PushService, p.PushOptions
                FROM users_notifications_settings AS n
                    JOIN users_push_notifications AS p ON n.UserID = p.UserID
                WHERE n.UserID = '$UserID'
                AND p.PushService != 0";
            if ($Type != self::GLOBALNOTICE) {
                $SQL .= " AND n.$Type IN (" . self::OPT_PUSH . "," . self::OPT_POPUP_PUSH . "," . self::OPT_TRADITIONAL_PUSH . ")";
            }
            self::$db->prepared_query($SQL);

            if (self::$db->has_results()) {
                [$PushService, $PushOptions] = self::$db->next_record(MYSQLI_NUM, false);
                $PushOptions = unserialize($PushOptions);
                if (empty($PushOptions['PushKey'])) {
                    continue;
                }
                switch ($PushService) {
                    // Case 1 is missing because NMA is dead.
                    case '2':
                        $Service = "Prowl";
                        break;
                    // Case 3 is missing because notifo is dead.
                    case '4':
                        $Service = "Toasty";
                        break;
                    case '5':
                        $Service = "Pushover";
                        break;
                    case '6':
                        $Service = "PushBullet";
                        break;
                    default:
                        continue 2;
                }
                $Options = [
                    "service" => strtolower($Service),
                    "user"    => ["key" => $PushOptions['PushKey']],
                    "message" => ["title" => $Title, "body" => $Body, "url" => $URL]
                ];

                if ($Service === 'PushBullet') {
                    $Options["user"]["device"] = $PushOptions['PushDevice'];
                }

                self::$db->prepared_query("
                    INSERT INTO push_notifications_usage
                           (PushService, TimesUsed)
                    VALUES (?,           1)
                    ON DUPLICATE KEY UPDATE
                        TimesUsed = TimesUsed + 1
                    ", $Service
                );
                $sock = fsockopen(PUSH_SOCKET_LISTEN_ADDRESS, PUSH_SOCKET_LISTEN_PORT); /** @phpstan-ignore-line */
                if ($sock !== false) {
                    fwrite($sock, (string)json_encode($Options, JSON_INVALID_UTF8_SUBSTITUTE));
                    fclose($sock);
                }
            }
            self::$db->set_query_id($QueryID);
        }
    }

    /**
     * Gets users who have push notifications enabled
     */
    public function pushableUsers(int $userId): array {
        self::$db->prepared_query("
            SELECT UserID
            FROM users_push_notifications
            WHERE PushService != 0
                AND UserID != ?
            ", $userId
        );
        return self::$db->collect("UserID", false);
    }
}
