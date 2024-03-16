<?php

namespace Gazelle\Torrent;

use Gazelle\Enum\ReaperNotify;
use Gazelle\Enum\ReaperState;

class Reaper extends \Gazelle\Base {
    public function __construct(
        protected \Gazelle\Manager\Torrent $torMan,
        protected \Gazelle\Manager\User    $userMan,
    ) {}

    /**
     * Send notifications of all the possible upload states. Processing the
     * initial states of unseeded and never seeded uploads is what puts
     * torrents onto the conveyor belt towards revival... or the reaper.
     *
     * @return int total number of uploads processed
     */
    public function notify(): int {
        // move uploads along the conveyor belt
        return $this->process($this->initialUnseededList(),    ReaperState::UNSEEDED, ReaperNotify::INITIAL)
             + $this->process($this->initialNeverSeededList(), ReaperState::NEVER,    ReaperNotify::INITIAL)
             + $this->process($this->finalUnseededList(),      ReaperState::UNSEEDED, ReaperNotify::FINAL)
             + $this->process($this->finalNeverSeededList(),   ReaperState::NEVER,    ReaperNotify::FINAL);
    }

    /**
     * Send notifications (to the users who wish to receive them) regarding
     * uploads that are in various states of unseededness. Initial notifications
     * kick off the timer that eventually lead to a torrent being reaped.
     */
    public function process(array $userList, ReaperState $state, ReaperNotify $notify): int {
        $processed = 0;
        foreach ($userList as $userId => $ids) {
            $user = $this->userMan->findById($userId);
            if ($user?->isEnabled()) {
                $this->notifySeeder($user, $ids, $state, $notify);
            }
            $processed += count($ids);
        }
        return $processed;
    }

    /**
     * Send a PM to a seeder listing their unseeded items
     */
    public function notifySeeder(\Gazelle\User $user, array $ids, ReaperState $state, ReaperNotify $notify): ?\Gazelle\PM {
        if ($user->hasAttr($state->notifyAttr())) {
            // No conversation will be created as they didn't ask for one
            return null;
        }
        $never   = $state === ReaperState::NEVER;
        $final   = $notify === ReaperNotify::FINAL;
        $total   = count($ids);
        return $user->inbox()->createSystem(
            ($never ? "You have " : "There " . ($total > 1 ? 'are' : 'is') . " ")
                . article($total, $never ? 'a' : 'an') // "a non-seeded" versus "an unseeded"
                . ($never ? " non-seeded new upload" : " unseeded upload")
                . plural($total)
            . ($final ? " scheduled for deletion very soon" : " to rescue"),
            self::$twig->render('notification/unseeded.bbcode.twig', [
                'final' => $final,
                'never' => $never,
                'list'  => $ids,
                'user'  => $user,
            ])
        );
    }

    public function initialNeverSeededList(): array {
        return $this->initialList(
            cond: [
                'tls.last_action IS NULL',
                't.created < now() - INTERVAL ? HOUR', // interval
            ],
            interval: NOTIFY_NEVER_SEEDED_INITIAL_HOUR,
            state:    ReaperState::NEVER,
        );
        // Unlike unseeded uploads, there's nobody else to contact to see if
        // they can help revive them, so the game stops here.
    }

    public function initialUnseededList(): array {
        $list = $this->initialList(
            cond: [
                'tls.last_action < now() - INTERVAL ? HOUR', // interval
                // TODO: We do not want to spam people who have voluntarily unseeded their redundant V2 uploads.
                // We need to nuke these V2 torrents and afterwards the following condition may be removed.
                "NOT (t.Format = 'MP3' AND t.Encoding = 'V2')",
            ],
            interval: NOTIFY_UNSEEDED_INITIAL_HOUR,
            state:    ReaperState::UNSEEDED,
        );
        if (!$list) {
            self::$db->commit();
            return [];
        }

        // The community can do something about these, find out who snatched them.
        $args = [];
        foreach ($list as $torrentIds) {
            array_push($args, ...$torrentIds);
        }
        self::$db->prepared_query("
            SELECT xs.uid AS user_id,
                group_concat(DISTINCT xs.fid ORDER BY xs.fid) AS ids
            FROM xbt_snatched xs
            INNER JOIN torrents t ON (t.ID = xs.fid AND t.UserID != xs.uid)
            INNER JOIN users_main um ON (um.ID = xs.uid)
            LEFT JOIN torrent_unseeded_claim tuc ON (tuc.user_id = xs.uid AND tuc.torrent_id = xs.fid) 
            WHERE tuc.user_id IS NULL
                AND um.Enabled  = '1'
                AND t.ID IN (" . placeholders($args) . ")
            GROUP BY xs.uid
            ORDER BY xs.uid
            ", ...$args
        );

        // Send an alert to each snatcher listing all the uploads that they could reseed
        $snatchList = $this->expand(NOTIFY_REAPER_MAX_PER_USER, self::$db->to_array(false, MYSQLI_NUM, false));
        foreach ($snatchList as $userId => $torrentIds) {
            $user = $this->userMan->findById($userId);
            // cannot say !$user?->hasAttr() because !null is true
            if ($user && !$user->hasAttr(ReaperState::UNSEEDED->notifyAttr())) {
                $this->notifySnatcher($user, $torrentIds);
            }
        }

        // and open reseed claims for them
        $args = [];
        foreach ($snatchList as $userId => $torrentIds) {
            foreach ($torrentIds as $torrentId) {
                array_push($args, $userId, $torrentId);
            }
        }
        if ($args) {
            self::$db->prepared_query("
                INSERT INTO torrent_unseeded_claim (user_id, torrent_id) VALUES "
                . placeholders(array_fill(0, (int)(count($args) / 2), true), "(?,?)"),
                ...$args
            );
        }
        self::$db->commit();
        return $list;
    }

    /**
     * Send a PM to a snatcher with their unseeded uploads.
     * NB: A message is sent only on the initial phase. On the final round,
     * messages are sent only to the seeders.
     */
    public function notifySnatcher(\Gazelle\User $user, array $ids): \Gazelle\PM {
        $total = count($ids);
        return $user->inbox()->createSystem(
            "You have " . article($total, 'an') . " unseeded snatch" . plural($total, 'es') . ' to save',
            self::$twig->render('notification/unseeded-snatch.bbcode.twig', [
                'list' => $ids,
                'user' => $user,
            ])
        );
    }

    /**
     * Return a hash of user id/torrent ids that have never been/are no longer announced.
     * The query self-limits the number of ids returned: there may be more, but they
     * will be handled in a subsequent run. This is to help prevent users being swamped
     * with hundreds of notifications after the reaper task is deactivated for an extended
     * period of time.
     *
     * Returns list of [user id:torrent ids] pairs to process
     */
    public function initialList(array $cond, int $interval, ReaperState $state): array {
        $condition = implode(' AND ', $cond);
        self::$db->prepared_query("
            SELECT t.UserID AS user_id,
                group_concat(t.ID ORDER BY t.ID) AS ids
            FROM torrents                   t
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = t.ID)
            LEFT JOIN torrent_unseeded      tu  ON (tu.torrent_id = t.ID)
            WHERE $condition
                AND tu.torrent_id IS NULL
            GROUP BY t.UserID
            LIMIT ?
            ", $interval, NOTIFY_REAPER_MAX_NOTIFICATION
        );
        $initial = $this->expand(NOTIFY_REAPER_MAX_PER_USER, self::$db->to_array(false, MYSQLI_NUM, false));

        // We have already limited the number of users visited. We don't, however, know
        // if we have received more uploads than we care to handle. We go through what
        // the net caught and if there is more than the maximum then the rest are
        // discarded. We may not even notify as many as NOTIFY_REAPER_MAX_NOTIFICATION!
        // But there will always be a next time.
        $torrentIds = [];
        $result     = [];
        $limit      = $state === ReaperState::NEVER ? MAX_NEVER_SEEDED_PER_RUN : MAX_UNSEEDED_PER_RUN;
        foreach ($initial as $userId => $ids) {
            $result[$userId] = $ids;
            array_push($torrentIds, ...$ids);
            if (count($torrentIds) > $limit) {
                break;
            }
        }

        if ($torrentIds) {
            // Register the uploads that need to be reseeded, so that another notification
            // can be sent later and then eventually reap what's left afterwards.
            self::$db->prepared_query("
                INSERT INTO torrent_unseeded (torrent_id, state) VALUES "
                . placeholders($torrentIds, "(?,'" . $state->value . "')"),
                ...$torrentIds
            );
        }
        return $result;
    }

    public function finalNeverSeededList(): array {
        return $this->finalList(
            state:    ReaperState::NEVER,
            interval: NOTIFY_NEVER_SEEDED_FINAL_HOUR,
        );
    }

    public function finalUnseededList(): array {
        return $this->finalList(
            state:    ReaperState::UNSEEDED,
            interval: NOTIFY_UNSEEDED_FINAL_HOUR,
        );
    }

    /**
     * Return a hash of user/torrent ids that were not/no longer announced
     * and an unseeded notification has already been issued.
     *
     * Returns a list of [user id:torrent ids] pairs to process
     */
    public function finalList(ReaperState $state, int $interval): array {
        // get the notifications to perform
        self::$db->begin_transaction();
        self::$db->prepared_query("
            SELECT t.UserID AS user_id,
                group_concat(t.ID ORDER BY t.ID) AS ids
            FROM torrents t
            INNER JOIN torrent_unseeded     tu  ON (tu.torrent_id = t.ID)
            WHERE tu.unseeded_date < now() - INTERVAL ? HOUR
                AND tu.notify      = ?
                AND tu.state       = ?
            GROUP BY t.UserID
            ", $interval, ReaperNotify::INITIAL->value, $state->value
        );
        $final      = $this->expand(NOTIFY_REAPER_MAX_PER_USER, self::$db->to_array(false, MYSQLI_NUM, false));
        $limit      = $state === ReaperState::NEVER ? MAX_NEVER_SEEDED_PER_RUN : MAX_UNSEEDED_PER_RUN;
        $torrentIds = [];
        $result     = [];
        foreach ($final as $userId => $ids) {
            $result[$userId] = $ids;
            array_push($torrentIds, ...$ids);
            if (count($torrentIds) > $limit) {
                break;
            }
        }
        if (!$torrentIds) {
            self::$db->commit();
            return [];
        }

        // mark the entries as having generated the second warning
        self::$db->prepared_query("
            UPDATE torrent_unseeded SET
                notify = ?
            WHERE torrent_id IN (" . placeholders($torrentIds) . ")
            ", ReaperNotify::FINAL->value, ...$torrentIds
        );
        self::$db->commit();
        return $result;
    }

    public function removeNeverSeeded(): int {
        return $this->remove(
            reaperList: $this->reaperList(ReaperState::NEVER, REMOVE_NEVER_SEEDED_HOUR),
            reason:     'inactivity (never seeded)',
            template:   'notification/removed-never-seeded.bbcode.twig',
        );
    }

    public function removeUnseeded(): int {
        return $this->remove(
            reaperList: $this->reaperList(ReaperState::UNSEEDED, REMOVE_UNSEEDED_HOUR),
            reason:     'inactivity (unseeded)',
            template:   'notification/removed-unseeded.bbcode.twig',
        );
    }

    /**
     * Once we have listed some torrents in the torrent_unseeded table, we
     * no longer need to refer to the original torrent upload date or last
     * action date. That they have been here for enough time is sufficient
     * grounds to reap them.
     *
     * Return a hash of user id/torrent ids that are no longer/have never
     * been announced and must be removed.
     */
    public function reaperList(ReaperState $state, int $interval): array {
        self::$db->prepared_query("
            SELECT t.UserID AS user_id,
                group_concat(t.ID ORDER BY t.ID) AS ids
            FROM torrents t
            INNER JOIN torrent_unseeded tu ON (tu.torrent_id = t.ID)
            WHERE tu.unseeded_date < now() - INTERVAL ? HOUR
                AND tu.notify      = ?
                AND tu.state       = ?
            GROUP BY t.UserID
            ", $interval, ReaperNotify::FINAL->value, $state->value
        );
        return $this->expand(NOTIFY_REAPER_MAX_PER_USER, self::$db->to_array(false, MYSQLI_NUM, false));
    }

    /**
     * we have a list of users with lists of torrents to remove. Let's do this!
     */
    protected function remove(array $reaperList, string $reason, string $template): int {
        $removed = 0;
        $userList = [];
        foreach ($reaperList as $userId => $torrentIds) {
            $notes = [];
            foreach ($torrentIds as $torrentId) {
                $torrent = $this->torMan->findById($torrentId);
                if (is_null($torrent)) {
                    continue;
                }

                // get the snatchers of this torrent
                self::$db->prepared_query("
                    SELECT DISTINCT xs.uid
                    FROM xbt_snatched xs
                    WHERE xs.fid = ?
                        AND xs.uid != ?
                    ", $torrentId, $torrent->uploaderId()
                );
                foreach (self::$db->collect(0, false) as $snatcherId) {
                    $snatcherId = (int)$snatcherId;
                    if (!isset($userList[$snatcherId])) {
                        $userList[$snatcherId] = [];
                    }
                    $userList[$snatcherId][] = $torrent->group();
                }

                // grab the fields we want to use in the report before we blow the torrent away
                $note = [
                    'id'       => $torrent->id(),
                    'infohash' => $torrent->infohash(),
                    'name'     => $torrent->name(),
                    'tgroup'   => $torrent->group(),
                ];
                [$success, /* $message */] = $torrent->remove(null, $reason, -1);
                if ($success) {
                    $removed++;
                    $notes[]   = $note;
                }
            }

            if ($notes) {
                $total = count($notes);
                $user = $this->userMan->findById($userId);
                if (is_null($user)) {
                    continue;
                }
                if ($user->isEnabled()) {
                    $user->inbox()->createSystem(
                        "$total of your uploads " . ($total == 1 ? 'has' : 'have') . " been deleted for $reason",
                        self::$twig->render($template, [
                            'notes' => $notes,
                            'user'  => $user,
                        ])
                    );
                }
            }
        }

        // now inform the snatchers of all the torrents that were reaped during this run
        foreach ($userList as $userId => $torrentList) {
            $user = $this->userMan->findById($userId);
            if (is_null($user)) {
                continue;
            }
            if ($user->isEnabled()) {
                $total = count($torrentList);
                $user->inbox()->createSystem(
                    "$total of your snatches " . ($total == 1 ? 'was' : 'were') . " deleted for inactivity",
                    self::$twig->render('notification/removed-unseeded-snatch.bbcode.twig', [
                        'list' => $torrentList,
                        'user' => $user,
                    ])
                );
            }
        }
        return $removed;
    }

    public function expand(int $limit, array $list): array {
        $result = [];
        foreach ($list as [$userId, $ids]) {
            $result[$userId] = array_map('intval', array_slice(explode(',', $ids), 0, $limit));
        }
        return $result;
    }

    /**
     *
     * @return array of [torrent_id, user_id, points]
     */
    public function claim(): array {
        self::$db->begin_transaction();

        // Remove claims from uploads that the owner has begun to reseed
        self::$db->prepared_query("
            DELETE tu, tuc
            FROM torrent_unseeded tu
            iNNER JOIN xbt_files_users xfu ON (xfu.fid = tu.torrent_id)
            INNER JOIN torrents t ON (t.ID = xfu.fid AND t.UserID = xfu.uid)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = xfu.fid)
            LEFT JOIN torrent_unseeded_claim tuc USING (torrent_id)
            WHERE tu.unseeded_date < tls.last_action
                AND tuc.claim_date IS NULL
                AND xfu.remaining  = 0
                AND xfu.timespent  > 0
        ");

        // Get the snatchers who are seeding uploads for which a claim is open
        self::$db->prepared_query("
            SELECT xfu.fid as torrent_id,
                xfu.timespent,
                xfu.uid AS user_id
            FROM xbt_files_users xfu
            INNER JOIN torrents t ON (t.ID = xfu.fid)
            INNER JOIN torrents_leech_stats tls ON (tls.TorrentID = xfu.fid)
            INNER JOIN torrent_unseeded tu ON (tu.torrent_id = xfu.fid)
            LEFT JOIN torrent_unseeded_claim tuc ON (tuc.torrent_id = xfu.fid AND tuc.user_id = xfu.uid)
            WHERE tuc.claim_date IS NULL
                AND tu.unseeded_date < tls.last_action
                AND NOT EXISTS (
                    SELECT 1
                    FROM torrent_unseeded_claim tuc_prev
                    WHERE tuc_prev.claim_date   IS NOT NULL
                        AND tuc_prev.torrent_id = tuc.torrent_id
                        AND tuc_prev.user_id    = tuc.user_id
                )
                AND xfu.active    = 1
                AND xfu.remaining = 0
            ORDER BY torrent_id, xfu.timespent DESC
        ");
        $seederList = self::$db->to_array(false, MYSQLI_NUM, false);
        if (empty($seederList)) {
            self::$db->commit();
            return [];
        }

        // The first user (who has been seeding the longest) is rewarded, as
        // well as any other user who reseeds within 15 minutes afterwards
        $win              = [];
        $saved            = [];
        $prevTorrentId    = false;
        $longestTimeSpent = false;
        foreach ($seederList as [$torrentId, $timeSpent, $userId]) {
            $torrent = $this->torMan->findById($torrentId);
            $user    = $this->userMan->findById($userId);
            if (is_null($torrent) || is_null($user) || $torrent->uploaderId() == $userId) {
                // you cannot earn points from your own upload
                continue;
            }
            if ($longestTimeSpent === false) {
                $longestTimeSpent = $timeSpent;
            }
            if ($torrentId !== $prevTorrentId) {
                $saved[] = $torrentId;
            }
            if ($timeSpent + 900 >= $longestTimeSpent) {
                $win[] = [
                    $torrentId,
                    $userId,
                    $this->notifyWinner($torrent, new \Gazelle\User\Bonus($user)),
                ];
            }
            $prevTorrentId = $torrentId;
        }

        if ($saved) {
            self::$db->prepared_query("
                DELETE FROM torrent_unseeded
                WHERE torrent_id IN (" . placeholders($saved) . ")
                ", ...$saved
            );
            // revoke all the remaining claims other seeders had on these uploads
            // NB: It might be worth keeping these longer for subsequent
            // arbitration, as it represents a snapshot that cannot be recreated
            // once the swarm population changes.
            self::$db->prepared_query("
                DELETE FROM torrent_unseeded_claim
                WHERE claim_date IS NULL
                    AND torrent_id IN (" . placeholders($saved) . ")
                ", ...$saved
            );
        }

        self::$db->commit();
        return $win;
    }

    /**
     * Send a PM to the snatchers to thank them for reseeding an upload.
     * Clear out all the other claims.
     */
    public function notifyWinner(\Gazelle\Torrent $torrent, \Gazelle\User\Bonus  $bonus): float {
        self::$db->prepared_query("
            UPDATE torrent_unseeded_claim SET
                claim_date = now()
            WHERE claim_date   IS NULL
                AND torrent_id = ?
                AND user_id    = ?
            ", $torrent->id(), $bonus->user()->id()
        );

        $points = REAPER_RESEED_REWARD_FACTOR * $bonus->torrentValue($torrent);
        $bonus->addPoints($points);
        $bonus->user()->addStaffNote("Awarded {$points} BP for reseeding [pl]{$torrent->id()}[/pl]")->modify();
        $bonus->user()->inbox()->createSystem(
            "Thank you for reseeding {$torrent->group()->name()}!",
            self::$twig->render('notification/reseed.bbcode.twig', [
                'points'  => $points,
                'torrent' => $torrent,
                'user'    => $bonus->user(),
            ])
        );
        return $points;
    }

    public function claimStats(): array {
        return array_map('intval', self::$db->rowAssoc("
            SELECT coalesce(sum(claim_date IS NULL), 0)  AS open,
                coalesce(sum(claim_date IS NOT NULL), 0) AS claimed
            FROM torrent_unseeded_claim
        "));
    }

    /**
     * Some statistics regarding the state of unseeded/never seeded
     * uploads.
     */
    public function stats(): array {
        $stats = [
            'never_seeded_initial' => 0,
            'unseeded_initial'     => 0,
            'never_seeded_final'   => 0,
            'unseeded_final'       => 0,
        ];
        self::$db->prepared_query("
            SELECT notify,
                state,
                count(*) as total
            FROM torrent_unseeded
            GROUP BY notify, state
        ");
        $results = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($results as $r) {
            if ($r['state'] == ReaperState::UNSEEDED->value && $r['notify'] == ReaperNotify::INITIAL->value) {
                $stats['unseeded_initial'] = $r['total'];
            } elseif ($r['state'] == ReaperState::UNSEEDED->value && $r['notify'] == ReaperNotify::FINAL->value) {
                $stats['unseeded_final'] = $r['total'];
            } elseif ($r['state'] == ReaperState::NEVER->value && $r['notify'] == ReaperNotify::INITIAL->value) {
                $stats['never_seeded_initial'] = $r['total'];
            } else {
                $stats['never_seeded_final'] = $r['total'];
            }
        }
        return $stats;
    }

    public function timeline(): array {
        self::$db->prepared_query("
            SELECT date(unseeded_date) as `day`,
                count(*) as total
            FROM torrent_unseeded
            GROUP BY `day`
            ORDER BY `day` DESC
        ");
        return self::$db->to_pair('day', 'total', false);
    }

    public function extendGracePeriod(array $userIdList, int $days): int {
        if (!$userIdList) {
            return 0;
        }
        self::$db->prepared_query("
            UPDATE torrent_unseeded tu
            INNER JOIN torrents t ON (t.ID = tu.torrent_id)
            SET
                tu.unseeded_date = tu.unseeded_date + INTERVAL ? DAY
            WHERE t.UserID IN (" . placeholders($userIdList) . ")",
            $days, ...$userIdList
        );
        return self::$db->affected_rows();
    }

    public function unseederList(bool $showNever = false, bool $showUnseeded = true): array {
        $state = [];
        if ($showNever) {
            $state[] = ReaperState::NEVER->value;
        }
        if ($showUnseeded) {
            $state[] = ReaperState::UNSEEDED->value;
        }
        if (!$state) {
            return [];
        }

        self::$db->prepared_query("
            SELECT t.UserID                   AS user_id,
                count(*)                      AS total,
                min(tu.unseeded_date)         AS min_date,
                max(tu.unseeded_date)         AS max_date,
                min(tu.unseeded_date) > now() AS in_future
            FROM torrents t
            INNER JOIN torrent_unseeded tu ON (tu.torrent_id = t.ID)
            INNER JOIN users_main       um ON (um.ID = t.UserID)
            WHERE tu.state in (" . placeholders($state) . ")
            GROUP BY t.UserID
            ORDER BY um.Username;
            ", ...$state
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
