<?php

namespace Gazelle\Manager;

use Gazelle\Enum\UserStatus;

use Gazelle\Util\Time;

class User extends \Gazelle\BaseManager {
    protected const CACHE_STAFF = 'pm_staff_list';
    protected const ID_KEY = 'zz_u_%d';
    protected const USERNAME_KEY = 'zz_unam_%s';
    protected const USERFLOW_KEY = 'uflow';

    final public const DISABLE_MANUAL     = 1;
    final public const DISABLE_TOR        = 2;
    final public const DISABLE_INACTIVITY = 3;
    final public const DISABLE_TREEBAN    = 4;

    /**
     * Get a User object based on a magic field (id or @name)
     */
    public function find($name): ?\Gazelle\User {
        if (str_starts_with($name, '@')) {
            return $this->findByUsername(substr($name, 1));
        } elseif ((int)$name > 0) {
            return $this->findById((int)$name);
        }
        return null;
    }

    /**
     * Get a User object based on their ID
     */
    public function findById(int $userId): ?\Gazelle\User {
        $key = sprintf(self::ID_KEY, $userId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM users_main WHERE ID = ?
                ", $userId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\User($id) : null;
    }

    /**
     * Get a User object based on their username
     * This happens often enough for it to be worth caching the username-id mapping.
     */
    public function findByUsername(string $username): ?\Gazelle\User {
        $username = trim($username);
        $key = sprintf(self::USERNAME_KEY, $username);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM users_main WHERE Username = ?
                ", $username
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\User($id) : null;
    }

    /**
     * Find a user based on an Authorization header.
     *
     * @return array [success, result]
     * If success is false, the result is the error message to be returned in the response
     * Otherwise the result is a Gazelle\User object.
     */
    public function findByAuthorization(IPv4 $ipv4Man, string $authorization, string $ipaddr): array {
        $info = explode(" ", $authorization);
        // this first case is for compatibility with RED
        if (count($info) === 1) {
            $token = $info[0];
        } elseif (count($info) === 2) {
            if ($info[0] !== 'token') {
                return [false, 'invalid authorization type, must be "token"'];
            }
            $token = $info[1];
        } else {
            return [false, 'invalid authorization type, must be "token"'];
        }
        $userId = (int)substr(
            \Gazelle\Util\Crypto::decrypt(
                base64_decode(str_pad(strtr($token, '-_', '+/'), strlen($token) % 4, '=', STR_PAD_RIGHT)),
                ENCKEY
            ),
            32
        );
        $user = $this->findById($userId);
        if (is_null($user) || !$user->hasApiToken($token) || $user->isDisabled() || $user->isLocked()) {
            $watch = new \Gazelle\LoginWatch($ipaddr);
            $watch->increment($userId, "[usertoken:$userId]");
            if ($watch->nrAttempts() >= 5) {
                $watch->ban("[id:$userId]");
                if ($watch->nrBans() >= 10) {
                    $ipv4Man->createBan(0, $ipaddr, $ipaddr, 'Automated ban per failed token usage');
                }
            }
            return [false, 'invalid token'];
        }
        return [true, $user];
    }

    /**
     * Get a User object from their email address
     * (used for password reset)
     */
    public function findByEmail(string $email): ?\Gazelle\User {
        return $this->findById((int)self::$db->scalar("
            SELECT ID FROM users_main WHERE Email = ?  ", trim($email)
        ));
    }

    /**
     * Get a User object from their announceKey
     */
    public function findByAnnounceKey(string $announceKey): ?\Gazelle\User {
        return $this->findById((int)self::$db->scalar("
            SELECT ID FROM users_main WHERE torrent_pass = ?  ", $announceKey
        ));
    }

    public function findAllByCustomPermission(): array {
        self::$db->prepared_query("
            SELECT ID, CustomPermissions
            FROM users_main
            WHERE CustomPermissions NOT IN ('', 'a:0:{}')
        ");
        return array_map(fn($perm) => unserialize($perm),
            self::$db->to_pair('ID', 'CustomPermissions', false)
        );
    }

    /**
     * Bulk update a user attribute for a list of user ids.
     * Note: the user cache is not updated, the calling code is
     * responsible for flushing each object afterwards.
     */
    public function modifyAttr(array $idList, string $attr, bool $active): int {
        if (!$idList) {
            return 0;
        }
        $attrId = (int)self::$db->scalar("
            SELECT ID FROM user_attr WHERE Name = ?
            ", $attr
        );
        if ($active) {
            self::$db->prepared_query("
                INSERT IGNORE INTO user_has_attr (UserID, UserAttrID)
                VALUES " . placeholders($idList, "(?, $attrId)")
                , ...$idList
            );
        } else {
            self::$db->prepared_query("
                DELETE FROM user_has_attr
                WHERE UserAttrID = ?
                    AND UserID IN (" . placeholders($idList) . ")
                ", $attrId, ...$idList
            );
        }
        return self::$db->affected_rows();
    }

    public function staffPMList(): array {
        $list = self::$cache->get_value(self::CACHE_STAFF);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT um.ID, um.Username
                FROM users_main AS um
                INNER JOIN permissions AS p ON (p.ID = um.PermissionID)
                WHERE p.DisplayStaff = '1'
                ORDER BY um.Username
            ");
            $list = self::$db->to_pair('ID', 'Username', false);
            self::$cache->cache_value(self::CACHE_STAFF, $list, 86400);
        }
        return $list;
    }

    /**
     * Get list of user classes by ID
     * @return array $classes
     */
    public function classList(): array {
        if (($classList = self::$cache->get_value('user_class')) === false) {
            $qid = self::$db->get_query_id();
            self::$db->prepared_query("
                SELECT ID, Name, Level, Secondary, badge
                FROM permissions
                ORDER BY Level
            ");
            $classList = self::$db->to_array('ID');
            self::$db->set_query_id($qid);
            self::$cache->cache_value('user_class', $classList, 7200);
        }
        return $classList;
    }

    /**
     * Textual name of a userclass (a.k.a users_main.PermissionID)
     *
     * @return string class name
     */
    public function userclassName(int $id): ?string {
        return $this->classList()[$id]['Name'];
    }

    /**
     * Get list of user classes by level
     * @return array $classes
     */
    public function classLevelList(): array {
        $classList = $this->classList();
        $classLevelList = [];
        foreach ($classList as $c) {
            $classLevelList[$c['Level']] = $c;
        }
        return $classLevelList;
    }

    /**
     * Get list of FLS names
     * @return array id => \Gazelle\User
     */
    public function flsList() {
        if (($list = self::$cache->get_value('idfls')) === false) {
            self::$db->prepared_query("
                SELECT um.ID
                FROM users_main AS um
                INNER JOIN users_levels AS ul ON (ul.UserID = um.ID)
                WHERE ul.PermissionID = ?
                ORDER BY um.Username
                ", FLS_TEAM
            );
            $list = self::$db->collect(0);
            self::$cache->cache_value('idfls', $list, 3600);
        }
        $fls = [];
        foreach ($list as $id) {
            $fls[$id] = $this->findById($id);
        }
        return $fls;
    }

    /**
     * Get list of staff names
     * @return array id => username
     */
    public function staffList(): array {
        self::$db->prepared_query("
            SELECT um.ID    AS id
            FROM users_main AS um
            INNER JOIN permissions AS p ON (p.ID = um.PermissionID)
            WHERE p.Level >= (SELECT Level FROM permissions WHERE ID = ?)
            ORDER BY p.Level DESC, um.Username ASC
            ", FORUM_MOD
        );
        $list = self::$db->collect(0);
        $staff = [];
        foreach ($list as $id) {
            $staff[$id] = $this->findById($id);
        }
        return $staff;
    }

    /**
     * Get the names of the staff classes sorted by rank
     * @return array $classes
     */
    public function staffClassList(): array {
        $staffClassList = self::$cache->get_value('staff_class');
        if ($staffClassList === false) {
            self::$db->prepared_query("
                SELECT ID, Name, Level
                FROM permissions
                WHERE Secondary = 0
                    AND LEVEL >= (SELECT Level FROM permissions WHERE ID = ?)
                ORDER BY Level
                ", FORUM_MOD
            );
            $staffClassList = self::$db->to_array('ID', MYSQLI_ASSOC);
            self::$cache->cache_value('staff_class', $staffClassList, 7200);
        }
        return $staffClassList;
    }

    public function staffListGrouped(): array {
        $staff = self::$cache->get_value('idstaff');
        if ($staff === false) {
            self::$db->prepared_query("
                SELECT sg.Name as staffGroup,
                    um.ID
                FROM users_main AS um
                INNER JOIN permissions AS p ON (p.ID = um.PermissionID)
                INNER JOIN staff_groups AS sg ON (sg.ID = p.StaffGroup)
                WHERE p.DisplayStaff = '1'
                    AND p.Secondary = 0
                ORDER BY sg.Sort, p.Level, um.Username
            ");
            $list = self::$db->to_array(false, MYSQLI_ASSOC);
            $staff = [];
            foreach ($list as $user) {
                if (!isset($staff[$user['staffGroup']])) {
                    $staff[$user['staffGroup']] = [];
                }
                $staff[$user['staffGroup']][] = $user['ID'];
            }
            self::$cache->cache_value('idstaff', $staff, 3600);
        }
        foreach ($staff as &$group) {
            $group = array_map(fn($userId) => $this->findById($userId), $group);
        }
        return $staff;
    }

    /**
     * Get the last year of user flow (joins, disables)
     *
     * @return array [week, joined, disabled]
     */
    public function userflow(): array {
        $userflow = self::$cache->get_value(self::USERFLOW_KEY);
        if ($userflow === false) {
            self::$db->prepared_query("
                SELECT J.Week,
                    J.n              AS created,
                    coalesce(D.n, 0) AS disabled
                FROM (
                    SELECT DATE_FORMAT(created, '%X-%V') AS Week, count(*) AS n
                    FROM users_main
                    GROUP BY Week
                    ORDER BY 1 DESC
                    LIMIT 52) J
                LEFT JOIN (
                    SELECT DATE_FORMAT(BanDate, '%X-%V') AS Week, count(*) AS n
                    FROM users_info
                    GROUP By Week
                    ORDER BY 1 DESC
                    LIMIT 52) D USING (Week)
                ORDER BY 1
            ");
            $userflow = self::$db->to_array('Week', MYSQLI_ASSOC, false);
            self::$cache->cache_value(self::USERFLOW_KEY, $userflow, 86400);
        }
        return $userflow;
    }

    /**
     * Get total number of userflow changes (for pagination)
     */
    public function userflowTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM (
                SELECT 1
                FROM users_main um
                INNER JOIN users_info ui ON (ui.UserID = um.ID)
                GROUP BY DATE_FORMAT(coalesce(ui.BanDate, um.created), '%Y-%m-%d')
            ) D
        ");
    }

    /**
     * Get a page of userflow details
     *
     * @return array of array [day, month, joined, manual, ratio, inactivity]
     */
    public function userflowDetails(int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT j.Date                    AS date,
                DATE_FORMAT(j.Date, '%Y-%m') AS month,
                coalesce(j.Flow, 0)          AS created,
                coalesce(m.Flow, 0)          AS manual,
                coalesce(r.Flow, 0)          AS ratio,
                coalesce(i.Flow, 0)          AS inactivity
            FROM (
                    SELECT
                        DATE_FORMAT(created, '%Y-%m-%d') AS Date,
                        count(*) AS Flow
                    FROM users_main
                    GROUP BY Date
                ) AS j
                LEFT JOIN (
                    SELECT
                        DATE_FORMAT(BanDate, '%Y-%m-%d') AS Date,
                        count(*) AS Flow
                    FROM users_info
                    WHERE BanDate IS NOT NULL
                        AND BanReason = '1'
                    GROUP BY Date
                ) AS m ON (j.Date = m.Date)
                LEFT JOIN (
                    SELECT
                        DATE_FORMAT(BanDate, '%Y-%m-%d') AS Date,
                        count(*) AS Flow
                    FROM users_info
                    WHERE BanDate IS NOT NULL
                        AND BanReason = '2'
                    GROUP BY Date
                ) AS r ON j.Date = r.Date
                LEFT JOIN (
                    SELECT
                        DATE_FORMAT(BanDate, '%Y-%m-%d') AS Date,
                        count(*) AS Flow
                    FROM users_info
                    WHERE BanDate IS NOT NULL
                        AND BanReason = '3'
                    GROUP BY Date
                ) AS i ON j.Date = i.Date
            ORDER BY j.Date DESC
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function flushUserclass(int $level): int {
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main um
            INNER JOIN permissions p ON (p.ID = um.PermissionID)
            WHERE um.Enabled = ?
                AND p.Level = ?
            ", UserStatus::enabled->value, $level
        );
        $affected = 0;
        foreach (self::$db->collect(0, false) as $id) {
            $user = $this->findById($id);
            if ($user) {
                $user->flush();
                ++$affected;
            }
        }
        return $affected;
    }

    /**
     * Flush the cached count of enabled users.
     */
    public function flushEnabledUsersCount(): User {
        self::$cache->delete_value('stats_user_count');
        return $this;
    }

    /**
     * Sends a PM from $FromId to $ToId.
     *
     * @return int conversation Id
     */
    public function sendPM(int $toId, int $fromId, string $subject, string $body): int {
        if ($toId === 0 || $toId === $fromId) {
            // Don't allow users to send messages to the system or themselves
            return 0;
        }

        $qid = self::$db->get_query_id();
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO pm_conversations (Subject) VALUES (?)
            ", mb_substr($subject, 0, 255)
        );
        $convId = self::$db->inserted_id();

        $placeholders = ["(?, ?, '1', '0', '1')"];
        $args = [$toId, $convId];
        if ($fromId !== 0) {
            $placeholders[] = "(?, ?, '0', '1', '0')";
            $args = array_merge($args, [$fromId, $convId]);
        }

        self::$db->prepared_query("
            INSERT INTO pm_conversations_users
                   (UserID, ConvID, InInbox, InSentbox, UnRead)
            VALUES
            " . implode(', ', $placeholders), ...$args
        );
        $this->deliverPM($toId, $fromId, $subject, $body, $convId);
        self::$db->commit();
        self::$db->set_query_id($qid);

        return $convId;
    }

    /**
     * Send a reply from $FromId to $ToId.
     *
     * @return int conversation Id
     */
    public function replyPM(int $toId, int $fromId, string $subject, string $body, int $convId): int {
        if ($toId === 0 || $toId === $fromId) {
            // Don't allow users to reply to the system or themselves
            return 0;
        }

        $qid = self::$db->get_query_id();
        self::$db->begin_transaction();
        $this->deliverPM($toId, $fromId, $subject, $body, $convId);
        self::$db->commit();
        self::$db->set_query_id($qid);

        return $convId;
    }

    protected function deliverPM(int $toId, int $fromId, string $subject, string $body, int $convId): void {
        self::$db->prepared_query("
            UPDATE pm_conversations_users SET
                InInbox = '1',
                UnRead = '1',
                ReceivedDate = now()
            WHERE UserID = ?
                AND ConvID = ?
            ", $toId, $convId
        );
        self::$db->prepared_query("
            UPDATE pm_conversations_users SET
                InSentbox = '1',
                SentDate = now()
            WHERE UserID = ?
                AND ConvID = ?
            ", $fromId, $convId
        );

        self::$db->prepared_query("
            INSERT INTO pm_messages
                   (SenderID, ConvID, Body)
            VALUES (?,        ?,      ?)
            ", $fromId, $convId, $body
        );

        // Update the cached new message count.
        self::$cache->cache_value("inbox_new_$toId",
            self::$db->scalar("
                SELECT count(*) FROM pm_conversations_users WHERE UnRead = '1' AND InInbox = '1' AND UserID = ?
                ", $toId
            )
        );
        self::$cache->delete_multi(["pm_{$convId}_{$fromId}", "pm_{$convId}_{$toId}"]);
        $senderName = self::$db->scalar("
            SELECT Username FROM users_main WHERE ID = ?
            ", $fromId
        );
        (new Notification)->push([$toId],
            "Message from $senderName, Subject: $subject", $body, SITE_URL . '/inbox.php', Notification::INBOX
        );
    }

    public function sendCustomPM(\Gazelle\User $sender, string $subject, string $template, array $idList): int {
        $total = 0;
        foreach ($idList as $userId) {
            $user = $this->findById($userId);
            if (is_null($user)) {
                continue;
            }
            $message = preg_replace('/%USERNAME%/', $user->username(), $template);
            $this->sendPM($userId, $sender->id(), $subject, $message);
            $total++;
        }
        return $total;
    }

    public function sendSnatchPm(\Gazelle\User $viewer, \Gazelle\Torrent  $torrent, string $subject, string $body): int {
        self::$db->prepared_query('
            SELECT uid FROM xbt_snatched WHERE fid = ?
            ', $torrent->id()
        );

        $snatchers = self::$db->collect(0, false);
        foreach ($snatchers as $userId) {
            $this->sendPM($userId, 0, $subject, $body);
        }
        $total = count($snatchers);
        (new \Gazelle\Log)->general($viewer->username()." sent a mass PM to $total snatcher" . plural($total)
            . " of torrent " . $torrent->id() . " (" . $torrent->group()->text() . ")"
        );
        return $total;
    }

    public function sendRemovalPm(int $torrentId, int $uploaderId, string $name, string $log, int $trumpId, bool $pmUploader): int {
        $subject = 'Torrent deleted: ' . $name;
        $message = 'A torrent %s '
            . (!$trumpId
                 ? ' has been deleted.'
                 : " has been trumped. You can find the new torrent [url=torrents.php?torrentid={$trumpId}]here[/url]."
            )
            . "\n\n[url=log.php?search=Torrent+{$torrentId}]Log message[/url]: "
            . str_replace('%', '%%', $log) // to prevent sprintf() interpolation
            . ".";

        if ($pmUploader) {
            $this->sendPM($uploaderId, 0, $subject, sprintf($message, 'you uploaded'));
        }
        $seen = [$uploaderId];

        self::$db->prepared_query("
            SELECT DISTINCT xfu.uid
            FROM xbt_files_users    xfu
            LEFT JOIN user_has_attr uha ON (uha.UserID = xfu.uid AND uha.UserAttrID = (SELECT ID FROM user_attr WHERE Name = ?))
            WHERE uha.UserID IS NULL
                AND xfu.fid = ?
                AND xfu.uid NOT IN (" . placeholders($seen) . ")
            ", 'no-pm-delete-seed', $torrentId, ...$seen
        );
        $ids = self::$db->collect('uid');
        foreach ($ids as $userId) {
            $this->sendPM($userId, 0, $subject, sprintf($message, 'you are seeding'));
        }
        $seen = array_merge($seen, $ids);

        self::$db->prepared_query("
            SELECT DISTINCT xs.uid
            FROM xbt_snatched AS xs
            LEFT JOIN user_has_attr uha ON (uha.UserID = xs.uid AND uha.UserAttrID = (SELECT ID FROM user_attr WHERE Name = ?))
            WHERE uha.UserID IS NULL
                AND xs.fid = ?
                AND xs.uid NOT IN (" . placeholders($seen) . ")
            ", 'no-pm-delete-snatch', $torrentId, ...$seen
        );
        $ids = self::$db->collect('uid');
        foreach ($ids as $userId) {
            $this->sendPM($userId, 0, $subject, sprintf($message, 'you have snatched'));
        }
        $seen = array_merge($seen, $ids);

        self::$db->prepared_query("
            SELECT DISTINCT ud.UserID
            FROM users_downloads AS ud
            LEFT JOIN user_has_attr uha ON (uha.UserID = ud.UserID AND uha.UserAttrID = (SELECT ID FROM user_attr WHERE Name = ?))
            WHERE uha.UserID IS NULL
                AND ud.TorrentID = ?
                AND ud.UserID NOT IN (" . placeholders($seen) . ")
            ", 'no-pm-delete-download', $torrentId, ...$seen
        );
        $ids = self::$db->collect('UserID');
        foreach ($ids as $userId) {
            $this->sendPM($userId, 0, $subject, sprintf($message, 'you have downloaded'));
        }

        return count(array_merge($seen, $ids));
    }

    public function disableUnconfirmedUsers(\Gazelle\Task $task = null): int {
        // get a list of user IDs for clearing cache keys
        self::$db->prepared_query("
            SELECT ID
            FROM users_main um
            LEFT JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            WHERE ula.user_id IS NULL
                AND um.created < now() - INTERVAL 7 DAY
                AND um.Enabled != '2'
            "
        );
        $idList = self::$db->collect(0, false);

        // disable the users
        self::$db->prepared_query("
            UPDATE users_main          um
            INNER JOIN users_info      ui  ON (ui.UserID = um.ID)
            LEFT JOIN user_last_access ula ON (ula.user_id = um.ID)
            SET um.Enabled = '2',
                ui.BanDate = now(),
                ui.BanReason = '3',
                ui.AdminComment = CONCAT(now(), ' - Disabled for inactivity (never logged in)\n\n', ui.AdminComment)
            WHERE ula.user_id IS NULL
                AND um.created < now() - INTERVAL 7 DAY
                AND um.Enabled != '2'
            "
        );
        if (self::$db->has_results()) {
            $this->flushEnabledUsersCount();
        }

        // clear the appropriate cache keys
        foreach ($idList as $userId) {
            $user = $this->findById($userId);
            if (is_null($user)) {
                continue;
            }
            $user->flush();
            $task?->debug("Disabled {$user->label()}", $userId);
        }
        return count($idList);
    }

    public function inactiveUserWarn(\Gazelle\Util\Mail $mailer): int {
        self::$db->prepared_query("
            SELECT DISTINCT um.ID
            FROM users_main             um
            INNER JOIN user_last_access ula ON (ula.user_id = um.ID)
            INNER JOIN permissions      p   ON (p.ID = um.PermissionID)
            WHERE um.Enabled = ?
                AND ula.last_access < now() - INTERVAL ? DAY
                AND NOT EXISTS (
                    SELECT 1
                    FROM user_has_attr uha
                    INNER JOIN user_attr ua ON (ua.ID = uha.UserAttrId AND ua.Name = ?)
                    WHERE uha.UserID = um.ID
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM users_levels ul
                    INNER JOIN permissions ulp ON (ulp.ID = ul.PermissionID)
                    WHERE ul.UserID = um.ID
                        AND ulp.Name in (?, ?)
                )
                AND p.Name IN (?, ?)
            ", UserStatus::enabled->value,
                INACTIVE_USER_WARN_DAYS,
                'inactive-warning-sent',
                'Donor', 'Torrent Celebrity',
                'User', 'Member'
        );

        $processed = 0;
        foreach (self::$db->collect(0, false) as $userId) {
            $user = $this->findById($userId);
            if ($user) {
                $mailer->send($user->email(), 'Your ' . SITE_NAME . ' account is about to be deactivated',
                    self::$twig->render('email/disable-warning.twig', [
                        'username' => $user->username(),
                    ])
                );
                $processed++;
                $user->toggleAttr('inactive-warning-sent', true);
            }
        }
        return $processed;
    }

    public function inactiveUserDeactivate(\Gazelle\Tracker $tracker): int {
        self::$db->prepared_query("
            SELECT DISTiNCT um.ID
            FROM users_main AS um
            INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            INNER JOIN permissions p ON (p.ID = um.PermissionID)
            WHERE um.Enabled = ?
                AND ula.last_access < now() - INTERVAL ? DAY
                AND NOT EXISTS (
                    SELECT 1
                    FROM users_levels ul
                    INNER JOIN permissions ulp ON (ulp.ID = ul.PermissionID)
                    WHERE ul.UserID = um.ID
                        AND ulp.Name in (?, ?)
                )
                AND p.Name IN (?, ?)
            ", UserStatus::enabled->value,
                INACTIVE_USER_DEACTIVATE_DAYS,
                'Donor', 'Torrent Celebrity',
                'User', 'Member'
        );

        $processed = 0;
        foreach (self::$db->collect(0, false) as $userId) {
            $user = $this->findById($userId);
            if ($user) {
                $this->disableUserList($tracker, [$userId], 'Disabled for inactivity.', self::DISABLE_INACTIVITY);
                $processed++;
            }
            $this->flushEnabledUsersCount();
        }
        return $processed;
    }

    /**
     * Warn a user. Returns expiry date.
     */
    public function warn(\Gazelle\User $user, int $duration, string $reason, \Gazelle\User $staff): string {
        $warnTime = Time::offset($duration * 7 * 86_400);
        $warning  = new \Gazelle\User\Warning($user);
        $expiry   = $warning->warningExpiry();
        $user->addStaffNote("Warned until $warnTime by {$staff->username()}")->modify();
        if ($expiry) {
            $this->sendPM($user->id(), 0,
                'You have received a new warning',
                "You had existing warning (set to expire at $expiry).\n\nDue to this prior warning, you will remain warned until $warnTime.\nReason: $reason"
            );
        } else {
            $this->sendPM($user->id(), 0,
                'You have been warned',
                "You have been warned, the warning is set to expire on $warnTime. Remember, repeated warnings may jeopardize your account.\nReason: $reason"
            );
        }
        return $warning->create($reason, "$duration week", $staff);
    }

    /**
     * Disable a list of users.
     *
     * @return int number of users disabled
     */
    public function disableUserList(\Gazelle\Tracker $tracker, array $idList, string $comment, int $reason): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE users_main um
            INNER JOIN users_info ui ON (um.ID = ui.UserID) SET
                um.Enabled = '2',
                um.can_leech = 0,
                ui.BanDate = now(),
                ui.AdminComment = concat(now(), ' - ', ?, ui.AdminComment),
                ui.BanReason = ?
            WHERE um.ID IN (" . placeholders($idList) . ")
            ", "$comment\n\n", $reason, ...$idList
        );
        $n = self::$db->affected_rows() / 2; // there are two rows, in users_main and users_info

        self::$db->prepared_query("
            SELECT concat('session_', SessionID) as cacheKey
            FROM users_sessions
            WHERE Active = 1
                AND UserID IN (" . placeholders($idList) . ")
            ", ...$idList
        );
        self::$cache->delete_multi(self::$db->collect('cacheKey'));
        self::$db->prepared_query("
            DELETE FROM users_sessions WHERE UserID IN (" . placeholders($idList) . ")
            ", ...$idList
        );
        foreach ($idList as $userId) {
            $user = $this->findById($userId);
            if ($user) {
                $user->flush();
            }
        }
        $this->flushEnabledUsersCount();

        // Remove the users from the tracker.
        self::$db->prepared_query("
            SELECT torrent_pass FROM users_main WHERE ID IN (" . placeholders($idList) . ")
            ", ...$idList
        );
        $PassKeys = self::$db->collect('torrent_pass', false);
        self::$db->commit();
        $Concat = '';
        foreach ($PassKeys as $PassKey) {
            if (strlen($Concat) > 3950) { // Ocelot's read buffer is 4 KiB and anything exceeding it is truncated
                $tracker->update_tracker('remove_users', ['passkeys' => $Concat]);
                $Concat = $PassKey;
            } else {
                $Concat .= $PassKey;
            }
        }
        $tracker->update_tracker('remove_users', ['passkeys' => $Concat]);
        return $n;
    }

    public function demotionCriteria(): array {
        return [
            USER => [
                'From' => [MEMBER, POWER, ELITE, TORRENT_MASTER, POWER_TM, ELITE_TM, ULTIMATE_TM],
                'To' => USER,
                'Ratio' => 0.65,
                'Upload' => 0
            ],
            MEMBER => [
                'From' => [POWER, ELITE, TORRENT_MASTER, POWER_TM, ELITE_TM, ULTIMATE_TM],
                'To' => MEMBER,
                'Ratio' => 0.95,
                'Upload' => 25 * 1024 * 1024 * 1024
            ],
        ];
    }

    public function promotionCriteria(): array {
        $GiB = 1024 * 1024 * 1024;
        $criteria = [
            USER => [
                'From' => USER,
                'To' => MEMBER,
                'MinUpload' => 10 * $GiB,
                'MinRatio' => 0.7,
                'MinUploads' => 0,
                'Weeks' => 1
            ],
            MEMBER => [
                'From' => MEMBER,
                'To' => POWER,
                'MinUpload' => 25 * $GiB,
                'MinRatio' => 1.05,
                'MinUploads' => 5,
                'Weeks' => 2
            ],
            POWER => [
                'From' => POWER,
                'To' => ELITE,
                'MinUpload' => 100 * $GiB,
                'MinRatio' => 1.05,
                'MinUploads' => 50,
                'Weeks' => 4
            ],
            ELITE => [
                'From' => ELITE,
                'To' => TORRENT_MASTER,
                'MinUpload' => 500 * $GiB,
                'MinRatio' => 1.05,
                'MinUploads' => 500,
                'Weeks' => 8
            ],
            TORRENT_MASTER => [
                'From' => TORRENT_MASTER,
                'To' => POWER_TM,
                'MinUpload' => 500 * $GiB,
                'MinRatio' => 1.05,
                'MinUploads' => 500,
                'Weeks' => 8,
                'Extra' => [
                    'Unique groups' => [
                        'Query' => 'us.unique_group_total',
                        'Count' => 500,
                        'Type' => 'int'
                    ]
                ]
            ],
            POWER_TM => [
                'From' => POWER_TM,
                'To' => ELITE_TM,
                'MinUpload' => 500 * $GiB,
                'MinRatio' => 1.05,
                'MinUploads' => 500,
                'Weeks' => 8,
                'Extra' => [
                    '"Perfect" FLACs' => [
                        'Query' => 'us.perfect_flac_total',
                        'Count' => 500,
                        'Type' => 'int'
                    ]
                ]
            ],
            ELITE_TM => [
                'From' => ELITE_TM,
                'To' => ULTIMATE_TM,
                'MinUpload' => 2048 * $GiB,
                'MinRatio' => 1.05,
                'MinUploads' => 2000,
                'Weeks' => 12,
                'Extra' => [
                    '"Perfecter" FLACs' => [
                        'Query' => 'us.perfecter_flac_total',
                        'Count' => 2000,
                        'Type' => 'int'
                    ]
                ]
            ]
        ];
        if (RECOVERY_DB) {
            $criteria[ELITE_TM]['Extra'][SITE_NAME . ' Upload'] = [
               'Query' => "
                    (SELECT uls.Uploaded + us.request_bounty_size - coalesce(rb.final, 0)
                    FROM users_leech_stats uls
                    INNER JOIN user_summary us ON (us.user_id = uls.UserID)
                    LEFT JOIN recovery_buffer rb ON (rb.user_id = uls.UserID)
                    WHERE uls.UserID = um.ID)",
               'Count' => 2048 * $GiB,
               'Type' => 'bytes'
            ];
        }
        return $criteria;
    }

    public function promote(\Gazelle\Task $task = null, bool $commit = true): int {
        $processed = 0;
        foreach ($this->promotionCriteria() as $level) {
            $fromClass = $this->userclassName($level['From']);
            $toClass   = $this->userclassName($level['To']);
            $query = "
                SELECT um.ID
                FROM users_main um
                INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
                LEFT JOIN user_summary       us  ON (us.user_id = um.ID)
                WHERE um.Enabled = ?
                    AND um.PermissionID = ?
                    AND uls.Uploaded >= ?
                    AND (uls.Downloaded = 0 OR uls.Uploaded / uls.Downloaded >= ?)
                    AND um.created <= now() - INTERVAL ? WEEK
                    AND coalesce(us.upload_total, 0) >= ?
            ";
            $args = [UserStatus::enabled->value, $level['From'], $level['MinUpload'], $level['MinRatio'], $level['Weeks'], $level['MinUploads']];

            if (!empty($level['Extra'])) {
                $query .= ' AND ' . implode(' AND ',
                    array_map(function ($v) use (&$args) {
                        $args[] = $v['Count'];
                        return "{$v['Query']} >= ?";
                    }, $level['Extra'])
                );
            }

            self::$db->prepared_query($query, ...$args);
            foreach (self::$db->collect(0, false) as $userId) {
                $user = $this->findById($userId);
                if (is_null($user) || (new \Gazelle\User\Warning($user))->isWarned()) {
                    continue;
                }
                ++$processed;
                $task?->debug("Promoting {$user->label()} from $fromClass to $toClass", $userId);
                if (!$commit) {
                    continue;
                }

                $user->setField('PermissionID', $level['To'])
                    ->addStaffNote("Class changed to $toClass by System")
                    ->modify();
                $this->sendPM($userId, 0,
                    "You have been promoted to $toClass",
                    "Congratulations on your promotion to $toClass!\n\nTo read more about "
                        . SITE_NAME
                        . "'s user classes, read [url=wiki.php?action=article&amp;name=userclasses]this wiki article[/url]."
                );
            }
        }
        return $processed;
    }

    public function demote(\Gazelle\Task $task = null, bool $commit = true): int {
        $processed = 0;
        foreach (array_reverse($this->promotionCriteria()) as $level) {
            $fromClass = $this->userclassName($level['To']);  // note: To/From are reversed
            $toClass   = $this->userclassName($level['From']);
            $task?->debug("Begin demoting users from $fromClass to $toClass");

            $query = "
                SELECT ID
                FROM users_main um
                INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
                INNER JOIN users_info         ui ON (ui.UserID = um.ID)
                INNER JOIN user_summary       us ON (us.user_id = um.ID)
                WHERE um.Enabled = ?
                    AND um.PermissionID = ?
                    AND (
                        uls.Uploaded + us.request_bounty_size < ?
                        OR us.upload_total < ?
            ";
            $args = [UserStatus::enabled->value, $level['To'], $level['MinUpload'], $level['MinUploads']];

            if (!empty($level['Extra'])) {
                $query .= ' OR NOT ' . implode(' AND ',
                    array_map(function ($v) use (&$args) {
                        $args[] = $v['Count'];
                        return "{$v['Query']} >= ?";
                    }, $level['Extra'])
                );
            }
            $query .= ')';

            self::$db->prepared_query($query, ...$args);
            foreach (self::$db->collect('ID', false) as $userId) {
                $user = $this->findById($userId);
                if (is_null($user)) {
                    continue;
                }
                ++$processed;
                $task?->debug("Demoting {$user->label()} from $fromClass to $toClass", $userId);
                if (!$commit) {
                    continue;
                }

                $user->setField('PermissionID', $level['From'])
                    ->addStaffNote("Class changed to $toClass by System")
                    ->modify();
                $this->sendPM($userId, 0,
                    "You have been demoted to $toClass",
                    "You now only qualify for the \"$toClass\" user class.\n\nTo read more about "
                        . SITE_NAME
                        . "'s user classes, read [url=wiki.php?action=article&amp;name=userclasses]this wiki article[/url]."
                );
            }
        }
        return $processed;
    }

    public function updateRatioRequirements(): int {
        // Clear old seed time history
        self::$db->prepared_query("
            DELETE FROM users_torrent_history
            WHERE Date < DATE(now() - INTERVAL 7 DAY)
        ");

        // Store total seeded time for each user in a temp table
        self::$db->dropTemporaryTable("tmp_history_time");
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE tmp_history_time (
                UserID int NOT NULL PRIMARY KEY,
                SumTime bigint NOT NULL DEFAULT 0
            ) ENGINE=InnoDB
        ");
        self::$db->prepared_query("
            INSERT INTO tmp_history_time (UserID, SumTime)
            SELECT UserID, SUM(Time) as SumTime
            FROM users_torrent_history
            GROUP BY UserID
        ");

        // Insert new row with <NumTorrents> = 0 with <Time> being number of seconds short of 72 hours.
        // This is where we penalize torrents seeded for less than 72 hours
        self::$db->prepared_query("
            INSERT INTO users_torrent_history
                   (UserID, NumTorrents, Date,           Time)
            SELECT  UserID, 0,           UTC_DATE() + 0, 259200 - SumTime
            FROM tmp_history_time
            WHERE SumTime < 259200
        ");
        self::$db->dropTemporaryTable("tmp_history_time");

        // Set <Weight> to the time seeding <NumTorrents> torrents
        self::$db->prepared_query("
            UPDATE users_torrent_history SET
                Weight = NumTorrents * Time
            WHERE Weight != NumTorrents * Time
        ");

        // Calculate average time spent seeding each of the currently active torrents.
        // This rounds the results to the nearest integer because SeedingAvg is an int column.
        self::$db->dropTemporaryTable("tmp_history_weight_time");
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE tmp_history_weight_time (
                UserID int NOT NULL PRIMARY KEY,
                SeedingAvg int NOT NULL DEFAULT 0
            ) ENGINE=InnoDB
        ");
        self::$db->prepared_query("
            INSERT INTO tmp_history_weight_time (UserID, SeedingAvg)
            SELECT UserID, SUM(Weight) / SUM(Time)
            FROM users_torrent_history
            GROUP BY UserID
        ");

        // Remove dummy entry for torrents seeded less than 72 hours
        self::$db->prepared_query("
            DELETE FROM users_torrent_history
            WHERE NumTorrents = 0
        ");

        // Get each user's amount of snatches of existing torrents
        self::$db->dropTemporaryTable("tmp_snatch");
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE tmp_snatch (
                UserID int PRIMARY KEY,
                NumSnatches int NOT NULL DEFAULT 0
            ) ENGINE=InnoDB
        ");
        self::$db->prepared_query("
            INSERT INTO tmp_snatch (UserID, NumSnatches)
            SELECT xs.uid as UserID, COUNT(DISTINCT xs.fid)
            FROM xbt_snatched AS xs
            INNER JOIN torrents AS t ON (t.ID = xs.fid)
            GROUP BY xs.uid
        ");

        // Get the fraction of snatched torrents seeded for at least 72 hours this week
        // Essentially take the total number of hours seeded this week and divide that by 72 hours * <NumSnatches>
        self::$db->dropTemporaryTable("tmp_snatch_weight");
        self::$db->prepared_query("
            CREATE TEMPORARY TABLE tmp_snatch_weight (
                UserID int PRIMARY KEY,
                fraction float(10) NOT NULL
            ) ENGINE=InnoDB
        ");
        self::$db->prepared_query("
            INSERT INTO tmp_snatch_weight (UserID, fraction)
            SELECT t.UserID, 1 - (t.SeedingAvg / s.NumSnatches)
            FROM tmp_history_weight_time AS t
            INNER JOIN tmp_snatch AS s USING (UserID)
        ");
        self::$db->dropTemporaryTable("tmp_history_weight_time");
        self::$db->dropTemporaryTable("tmp_snatch");

        $ratioRequirements = [
            [80 * 1024 * 1024 * 1024, 0.60, 0.50],
            [60 * 1024 * 1024 * 1024, 0.60, 0.40],
            [50 * 1024 * 1024 * 1024, 0.60, 0.30],
            [40 * 1024 * 1024 * 1024, 0.50, 0.20],
            [30 * 1024 * 1024 * 1024, 0.40, 0.10],
            [20 * 1024 * 1024 * 1024, 0.30, 0.05],
            [10 * 1024 * 1024 * 1024, 0.20, 0.0],
            [ 5 * 1024 * 1024 * 1024, 0.15, 0.0]
        ];

        $affected = 0;
        $downloadBarrier = 100 * 1024 * 1024 * 1024;
        self::$db->prepared_query("
            UPDATE users_main AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            SET um.RequiredRatio = 0.60
            WHERE uls.Downloaded > ?
                AND um.RequiredRatio != 0.60
            ", $downloadBarrier
        );
        $affected += self::$db->affected_rows();

        foreach ($ratioRequirements as $requirement) {
            [$download, $ratio, $minRatio] = $requirement;

            self::$db->prepared_query("
                UPDATE users_main AS um
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
                INNER JOIN tmp_snatch_weight AS tsw ON (uls.UserID = um.ID)
                SET um.RequiredRatio = tsw.fraction * ?
                WHERE um.RequiredRatio != tsw.fraction * ?
                    AND uls.Downloaded >= ?
                    AND uls.Downloaded < ?
                ", $ratio, $ratio, $download, $downloadBarrier
            );
            $affected += self::$db->affected_rows();

            self::$db->prepared_query("
                UPDATE users_main AS um
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
                SET um.RequiredRatio = ?
                WHERE um.RequiredRatio != ?
                    AND uls.Downloaded >= ?
                    AND uls.Downloaded < ?
                ", $minRatio, $minRatio, $download, $downloadBarrier
            );
            $affected += self::$db->affected_rows();

            $downloadBarrier = $download;
        }
        self::$db->dropTemporaryTable("tmp_snatch_weight");

        self::$db->prepared_query("
            UPDATE users_main AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            SET um.RequiredRatio = 0.00
            WHERE um.RequiredRatio != 0.00
                AND uls.Downloaded < 5 * 1024 * 1024 * 1024
        ");
        return $affected + self::$db->affected_rows();
    }

    /**
     * Get the table joins for looking at users on ratio watch
     *
     * @return string SQL table joins
     */
    protected function sqlRatioWatchJoins(): string {
        return "FROM users_main AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            INNER JOIN users_info AS ui ON (ui.UserID = um.ID)
            WHERE ui.RatioWatchEnds > now()
                AND um.Enabled = '1'";
    }

    /**
     * How many people are on ratio watch?
     *
     * return int number of users
     */
    public function totalRatioWatchUsers(): int {
        return (int)self::$db->scalar("SELECT count(*) " . $this->sqlRatioWatchJoins());
    }

    /**
     * Get details of people on ratio watch
     *
     * @return array user details
     */
    public function ratioWatchUsers(int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT um.ID              AS user_id,
                uls.Uploaded          AS uploaded,
                uls.Downloaded        AS downloaded,
                um.created            AS created,
                ui.RatioWatchEnds     AS ratio_watch_ends,
                ui.RatioWatchDownload AS ratio_watch_downloaded,
                um.RequiredRatio      AS required_ratio
            " . $this->sqlRatioWatchJoins() . "
            ORDER BY ui.RatioWatchEnds ASC
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * How many users are banned for inadequate ratio?
     *
     * @return int number of users
     */
    public function totalBannedForRatio(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM users_info WHERE BanDate IS NOT NULL AND BanReason = '2'
        ");
    }

    public function ratioWatchAudit(\Gazelle\Tracker $tracker, ?\Gazelle\Task $task = null): int {
        // Take users off ratio watch and enable leeching
        return $this->ratioWatchClear($tracker, $task)
            + $this->ratioWatchSet($task);
    }

    /**
     * The list of ids of user whose leeching privileges need to be taken away
     * (Gambled more than 10GiB after being put on ratio watch)
     */
    public function ratioWatchBlockList(): array {
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main              um
            INNER JOIN users_info        ui  ON (ui.UserID = um.ID)
            INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
            WHERE um.can_leech = 1
                AND ui.RatioWatchEnds IS NOT NULL
                AND um.Enabled = ?
                AND uls.Downloaded - ui.RatioWatchDownload > ?
            ", UserStatus::enabled->value, 10 * 1_105_507_304
        );
        return self::$db->collect(0, false);
    }

    /**
     * The list of ids of users who have made good since being put on ratio watch
     * (Meet or exceed the required ratio for the amount downloaded)
     */
    public function ratioWatchClearList(): array {
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main              um
            INNER JOIN users_info        ui  ON (ui.UserID = um.ID)
            INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
            WHERE ui.RatioWatchEnds IS NOT NULL
                AND uls.Downloaded > 0
                AND uls.Uploaded / uls.Downloaded >= um.RequiredRatio
                AND um.Enabled = ?
            ", UserStatus::enabled->value
        );
        return self::$db->collect(0, false);
    }

    /**
     * The list of ids of users who have been on ratio watch for long enough to
     * improve their situation but failed to do so.
     */
    public function ratioWatchEngageList(): array {
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main       um
            INNER JOIN users_info ui ON (ui.UserID = um.ID)
            WHERE um.can_leech = 1
                AND ui.RatioWatchEnds <= now()
                AND um.Enabled = ?
            ", UserStatus::enabled->value
        );
        return self::$db->collect(0, false);
    }

    /**
     * The list of ids of users who are below their required ratio and need to be put on ratio watch
     */
    public function ratioWatchSetList(): array {
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main              um
            INNER JOIN users_info        ui  ON (ui.UserID  = um.ID)
            INNER JOIN users_leech_stats uls ON (uls.UserID = um.ID)
            WHERE um.can_leech = 1
                AND ui.RatioWatchEnds IS NULL
                AND uls.Downloaded > 0
                AND uls.Uploaded / uls.Downloaded < um.RequiredRatio
                AND um.Enabled = ?
            ", UserStatus::enabled->value
        );
        return self::$db->collect(0, false);
    }

    /**
     * Remove leeching privileges from users who were put on ratio watch and did not improve their situation in time
     */
    public function ratioWatchBlock(\Gazelle\Tracker $tracker, ?\Gazelle\Task $task = null): int {
        $idList = $this->ratioWatchBlockList();
        if (!$idList) {
            return 0;
        }

        self::$db->begin_transaction();
        $processed = 0;
        foreach ($idList as $userId) {
            $user = $this->findById($userId);
            if (is_null($user)) {
                continue;
            }
            $ratio = number_format($user->requiredRatio(), 2);
            $user->setField('can_leech', 0)
                ->addStaffNote("Leeching privileges suspended by ratio watch system (required ratio: $ratio) for downloading more than 10 GBs on ratio watch.")
                ->modify();
            $tracker->update_tracker('update_user', ['passkey' => $user->announceKey(), 'can_leech' => '0']);
            $this->sendPM( $userId, 0,
                'Your download privileges have been removed',
                'You have downloaded more than 10 GB while on Ratio Watch. Your leeching privileges have been suspended. Please reread the rules and refer to this guide on [url=wiki.php?action=article&amp;name=ratiotips]how to improve your ratio[/url]',
            );
            $processed++;
            $task?->debug("Disabling leech for {$user->label()}", $userId);
        }
        self::$db->commit();
        return $processed;
    }

    /**
     * Clear users who were on ratio watch and have since improved their situtation
     */
    public function ratioWatchClear(\Gazelle\Tracker $tracker, ?\Gazelle\Task $task = null): int {
        $idList = $this->ratioWatchClearList();
        if (!$idList) {
            return 0;
        }

        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE users_main um
            INNER JOIN users_info ui ON (ui.UserID = um.ID) SET
                um.can_leech          = 1,
                ui.RatioWatchEnds     = NULL,
                ui.RatioWatchDownload = '0',
                ui.AdminComment       = concat(now(), ' - Taken off ratio watch by adequate ratio.\n\n', ui.AdminComment)
            WHERE um.ID IN (" . placeholders($idList) . ")
        ", ...$idList);

        $processed = 0;
        foreach ($idList as $userId) {
            $user = $this->findById($userId);
            if (is_null($user)) {
                continue;
            }
            $user->flush();
            $tracker->update_tracker('update_user', ['passkey' => $user->announceKey(), 'can_leech' => '1']);
            $task?->debug("Taking {$user->label()} off ratio watch", $userId);
            $this->sendPM($userId, 0,
                'You have been taken off Ratio Watch',
                "Congratulations! Feel free to begin downloading again.\n To ensure that you do not get put on ratio watch again, please read the rules located [url=rules.php?p=ratio]here[/url].\n"
            );
            $processed++;
        }
        self::$db->commit();
        return $processed;
    }

    public function ratioWatchEngage(\Gazelle\Tracker $tracker, \Gazelle\Task $task = null): int {
        $idList = $this->ratioWatchEngageList();
        if (!$idList) {
            return 0;
        }

        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM users_torrent_history
            WHERE UserID IN (" . placeholders($idList) . ")
            ", ...$idList
        );
        $processed = 0;
        foreach ($idList as $userId) {
            $user = $this->findById($userId);
            if (is_null($user)) {
                continue;
            }
            $ratio = number_format($user->requiredRatio(), 2);
            $user->setField('can_leech', 0)
                ->addStaffNote("Leeching ability suspended by ratio watch system (required ratio: $ratio)")
                ->modify();
            $tracker->update_tracker('update_user', ['passkey' => $user->announceKey(), 'can_leech' => '0']);
            $this->sendPM($userId, 0,
                'Your downloading privileges have been suspended',
                "As you did not raise your ratio in time, your downloading privileges have been revoked. You will not be able to download any torrents until your ratio is above your new required ratio."
            );
            ++$processed;
            $task?->debug("Disabled leech for {$user->label()}", $userId);
        }
        self::$db->commit();
        return $processed;
    }

    /**
     * Mark all users on ratio watch who have downloaded beyond what their required ratio allows.
     */
    public function ratioWatchSet(?\Gazelle\Task $task = null): int {
        $idList = $this->ratioWatchSetList();
        if (!$idList) {
            return 0;
        }

        self::$db->begin_transaction();
        self::$db->prepared_query("
            UPDATE users_info AS ui
            INNER JOIN users_leech_stats AS uls USING (UserID) SET
                ui.RatioWatchEnds     = now() + INTERVAL 2 WEEK,
                ui.RatioWatchTimes    = ui.RatioWatchTimes + 1,
                ui.RatioWatchDownload = uls.Downloaded
            WHERE ui.UserID IN (" . placeholders($idList) . ")
        ", ...$idList);

        $processed = 0;
        foreach ($idList as $userId) {
            $user = $this->findById($userId);
            if (is_null($user)) {
                continue;
            }
            $user->flush();
            $this->sendPM($userId, 0,
                'You have been put on Ratio Watch',
                "This happens when your ratio falls below the requirements outlined in the rules located [url=rules.php?p=ratio]here[/url].\n For information about ratio watch, click the link above."
            );
            $processed++;
            $task?->debug("Putting $userId on ratio watch", $userId);
        }
        self::$db->commit();
        return $processed;
    }

    public function addMassTokens(int $amount, bool $allowLeechDisabled): int {
        $leech = $allowLeechDisabled ? '' : " AND um.can_leech = 1";
        self::$db->begin_transaction();
        self::$db->prepared_query("
            SELECT um.ID
            FROM users_main um
            LEFT JOIN user_has_attr fl ON (fl.UserID = um.ID AND fl.UserAttrID = ?)
            WHERE fl.UserID IS NULL
                AND um.Enabled = ?
                $leech
            ", (int)self::$db->scalar(" SELECT ID FROM user_attr WHERE Name = ?  ", 'no-fl-gifts'),
                UserStatus::enabled->value
        );
        $idList = array_map('intval', self::$db->collect(0, false));
        if ($idList) {
            self::$db->prepared_query("
                UPDATE user_flt SET
                    tokens = tokens + ?
                WHERE user_id IN (" . placeholders($idList) . ")
                ", $amount, ...$idList
            );
            foreach ($idList as $userId) {
                $this->findById($userId)?->flush();
            }
        }
        self::$db->commit();

        return count($idList);
    }

    public function clearMassTokens(int $amount, bool $allowLeechDisabled, bool $excludeDisabled): int {
        $cond = [];
        $args = [];
        if (!$excludeDisabled) {
            $cond[] = "um.Enabled = ?";
            $args[] = UserStatus::enabled->value;
        }
        if (!$allowLeechDisabled) {
            $cond[] = "um.can_leech = 1";
        }
        $cond[] = "uf.tokens > ?";
        $args[] = $amount;
        $where = implode(' AND ', $cond);

        self::$db->begin_transaction();
        self::$db->prepared_query("
            SELECT ID
            FROM users_main um
            INNER JOIN user_flt uf ON (uf.user_id = um.ID)
            WHERE $where
            ", ...$args
        );
        $idList = self::$db->collect('ID');
        if ($idList) {
            self::$db->prepared_query("
                UPDATE user_flt SET
                    tokens = ?
                WHERE user_id in (" . placeholders($idList) . ")
                ", $amount, ...$idList
            );
        }
        self::$db->commit();

        self::$cache->delete_multi(array_map(fn($id) => "u_$id", $idList));
        return count($idList);
    }

    public function expireFreeleechTokens(\Gazelle\Tracker $tracker): int {
        $slop   = 1.04; // 4% overshoot on download before forced expiry

        self::$db->prepared_query("
            SELECT uf.UserID,
                uf.TorrentID,
                t.info_hash
            FROM users_freeleeches AS uf
            INNER JOIN torrents AS t ON (t.ID = uf.TorrentID)
            WHERE uf.Expired = FALSE
                AND (uf.Downloaded > t.Size * ? OR uf.Time < now() - INTERVAL ? DAY);
            ", $slop, FREELEECH_TOKEN_EXPIRY_DAYS
        );
        $expire = self::$db->to_array(false, MYSQLI_ASSOC, false);

        $clear = [];
        $processed = 0;
        foreach ($expire as $token) {
            $clear["users_tokens_{$token['UserID']}"] = true;
            $tracker->update_tracker('remove_token', ['info_hash' => rawurlencode($token['info_hash']), 'userid' => $token['UserID']]);
            $processed++;
            self::$db->prepared_query("
                UPDATE users_freeleeches SET
                    Expired = TRUE
                WHERE TorrentID = ?
                    AND UserID = ?
                ", $token['TorrentID'], $token['UserID']
            );
        }
        self::$cache->delete_multi(array_keys($clear));
        return $processed;
    }

    public function cycleAuthKeys(): int {
        self::$db->prepared_query("
            UPDATE users_main SET
                auth_key = left(
                    replace(
                        replace(
                            to_base64(unhex(sha2(unhex(sha2(concat(rand(), rand(), rand(), rand()), 256)), 256))),
                            '+', '-'
                        ),
                        '/', '_'
                    ),
                    32
                )
        ");
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            SELECT ID FROM users_main
        ");
        foreach (self::$db->collect(0, false) as $userId) {
            $this->findById($userId)->flush();
        }
        return $affected;
    }

    public function updateLastAccess(): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO user_last_access (user_id, last_access)
            SELECT ulad.user_id,
                max(ulad.last_access)
            FROM user_last_access_delta ulad
            GROUP BY ulad.user_id
            ON DUPLICATE KEY UPDATE last_access = VALUES(last_access)
        ");
        $affected = self::$db->affected_rows();
        self::$db->prepared_query("
            DELETE FROM user_last_access_delta
        ");
        self::$db->commit();
        return $affected;
    }

    public function forumNavItemUserList(\Gazelle\User $user): array {
        $UserIds = $user->forumNavList();
        $NavItems = $this->forumNavItemList();
        $list = [];
        foreach ($NavItems as $n) {
            if (($n['mandatory'] || in_array($n['id'], $UserIds)) || (!count($UserIds) && $n['initial'])) {
                $list[] = $n;
            }
        }
        return $list;
    }

    public function forumNavItemList(): array {
        $list = self::$cache->get_value("nav_items");
        if (!$list) {
            $QueryID = self::$db->get_query_id();
            self::$db->prepared_query("
                SELECT id, tag, title, target, tests, test_user, mandatory, initial
                FROM nav_items");
            $list = self::$db->to_array("id", MYSQLI_ASSOC, false);
            self::$cache->cache_value("nav_items", $list, 0);
            self::$db->set_query_id($QueryID);
        }
        return $list;
    }

    public function checkPassword(string $password): string {
        return $password === ''
            || (bool)self::$db->scalar("
                SELECT 1 FROM bad_passwords WHERE Password = ?
                ", $password
            ) ? 'false' : 'true';
    }
}
