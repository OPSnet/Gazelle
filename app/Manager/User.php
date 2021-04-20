<?php

namespace Gazelle\Manager;

class User extends \Gazelle\Base {

    public const DISABLE_MANUAL     = 1;
    public const DISABLE_INACTIVITY = 3;
    public const DISABLE_TREEBAN    = 4;

    /**
     * Get a User object based on a magic field (id or @name)
     *
     * @param mixed name (numeric ID or @username)
     * @return \Gazelle\User object or null if not found
     */
    public function find($name) {
        if (substr($name, 0, 1) === '@') {
            return $this->findByUsername(substr($name, 1));
        } elseif ((int)$name > 0) {
            return $this->findById((int)$name);
        }
        return null;
    }

    /**
     * Get a User object based on their ID
     *
     * @param int userId
     * @return \Gazelle\User object or null if not found
     */
    public function findById(int $userId): ?\Gazelle\User {
        static $idCache;
        if (!isset($idCache[$userId])) {
            $idCache[$userId] = (int)$this->db->scalar("
                SELECT ID FROM users_main WHERE ID = ?
                ", $userId
            );
        }
        return $idCache[$userId] ? new \Gazelle\User($idCache[$userId]) : null;
    }

    /**
     * Get a User object based on their username
     *
     * @param string username
     * @return \Gazelle\User object or null if not found
     */
    public function findByUsername(string $username): ?\Gazelle\User {
        $userId = (int)$this->db->scalar("
            SELECT ID FROM users_main WHERE Username = ?
            ", trim($username)
        );
        return $userId ? new \Gazelle\User($userId) : null;
    }

    /**
     * Get a User object from their email address
     * (used for password reset)
     *
     * @param string username
     * @return \Gazelle\User object or null if not found
     */
    public function findByEmail(string $email): ?\Gazelle\User {
        $userId = (int)$this->db->scalar("
            SELECT ID FROM users_main WHERE Email = ?  ", $email
        );
        return $userId ? new \Gazelle\User($userId) : null;
    }

    /**
     * Get a User object from their password reset key
     *
     * @param string key
     * @return \Gazelle\User object or null if not found
     */
    public function findByResetKey(string $key): ?\Gazelle\User {
        $userId = (int)$this->db->scalar("
            SELECT ui.UserID
            FROM users_info ui
            WHERE ui.ResetKey = ?
            ", $key
        );
        return $userId ? new \Gazelle\User($userId) : null;
    }

    /**
     * Generate HTML for a user's avatar
     *
     * @param Gazelle\User viewer Who is doing the viewing, to determine how to fallback if no avatar is available
     * @param Gazelle\User viewed Which avatar is being viewed
     * @return HTML markup of the viewed avatar
     */
    public function avatarMarkup(\Gazelle\User $viewer, \Gazelle\User $viewed) {
        static $cache = [];
        $viewedId = $viewed->id();
        if (!isset($cache[$viewedId])) {
            switch ($viewer->avatarMode()) {
                case 1:
                    $avatar = STATIC_SERVER . '/common/avatars/default.png';
                    break;
                case 2:
                    $avatar = \ImageTools::process($viewed->avatar(), false, 'avatar', $viewedId)
                        ?: (new \Gazelle\Util\Avatar((int)$viewer->option('Identicons')))
                            ->setSize(AVATAR_WIDTH)
                            ->avatar($viewed->username());
                    break;
                case 3:
                    $avatar = (new \Gazelle\Util\Avatar((int)$viewer->option('Identicons')))
                        ->setSize(AVATAR_WIDTH)
                        ->avatar($viewed->username());
                    break;
                default:
                    $avatar = \ImageTools::process($viewed->avatar(), false, 'avatar', $viewedId)
                        ?: STATIC_SERVER . '/common/avatars/default.png';
                    break;
            }
            $attrs = ['width="' . AVATAR_WIDTH . '"'];
            [$mouseover, $second] = $viewed->donorAvatar();
            if (!is_null($mouseover)) {
                $attrs[] = $mouseover;
            }
            $attr = implode(' ', $attrs);
            $cache[$viewedId] = "<div class=\"avatar_container\"><div><img $attr class=\"avatar_0\" src=\"$avatar\" /></div>"
                . ($second ? ("<div><img $attr class=\"avatar_1\" src=\"" . \ImageTools::process($second, false, 'avatar2', $viewedId) . '" /></div>') : '')
                . "</div>";
        }
        return $cache[$viewedId];
    }

    /**
     * Get list of user classes by ID
     * @return array $classes
     */
    public function classList(): array {
        if (($classList = $this->cache->get_value('user_class')) === false) {
            $qid = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT ID, Name, Level, Secondary, badge
                FROM permissions
                ORDER BY Level
            ");
            $classList = $this->db->to_array('ID');
            $this->db->set_query_id($qid);
            $this->cache->cache_value('user_class', $classList, 0);
        }
        return $classList;
    }

    /**
     * Textual name of a userclass (a.k.a users_main.PermissionID)
     *
     * @param int permission id
     * @return string class name
     */
    public function userclassName(int $id): ?string {
        return $this->classlist()[$id]['Name'];
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
     * Get list of staff classes by ID
     * @return array $classes
     */
    public function staffLevelList(): array {
        if (($staffLevelList = $this->cache->get_value('staff_class')) === false) {
            $this->db->prepared_query("
                SELECT ID, Name, Level 
                FROM permissions
                WHERE Secondary = 0
                    AND LEVEL >= (SELECT Level FROM permissions WHERE ID = ?)
                ORDER BY Level
                ", FORUM_MOD
            );
            $staffLevelList = $this->db->to_array('ID');
            $this->cache->cache_value('staff_class', $staffLevelList, 0);
        }
        return $staffLevelList;
    }

    /**
     * Get list of staff names
     * @return array id => username
     */
    public function staffList(): array {
        $this->db->prepared_query("
            SELECT um.ID    AS id,
                um.Username AS username
            FROM users_main AS um 
            INNER JOIN permissions AS p ON (p.ID = um.PermissionID)
            WHERE p.Level >= (SELECT Level FROM permissions WHERE ID = ?)
            ORDER BY p.Level DESC, um.Username ASC
            ", FORUM_MOD
        );
        return $this->db->to_pair('id', 'username');
    }

    /**
     * Get list of FLS names
     * @return array id => username
     */
    public function FLSList(): array {
        $this->db->prepared_query("
            SELECT um.ID    AS id,
                um.Username AS username
            FROM users_main AS um 
            INNER JOIN users_levels ul ON (ul.UserID = um.ID)
            WHERE ul.PermissionID = ?
            ORDER BY um.Username ASC
            ", FLS_TEAM
        );
        return $this->db->to_pair('id', 'username');
    }

    public function findAllByCustomPermission(): array {
        $this->db->prepared_query("
            SELECT ID, CustomPermissions
            FROM users_main
            WHERE CustomPermissions NOT IN ('', 'a:0:{}')
        ");
        return array_map(function ($perm) {return unserialize($perm);},
            $this->db->to_pair('ID', 'CustomPermissions', false)
        );
    }

    /**
     * Get the number of enabled users last day/week/month
     *
     * @return array [Day, Week, Month]
     */
    public function globalActivityStats(): array {
        if (($stats = $this->cache->get_value('stats_users')) === false) {
            $this->db->prepared_query("
                SELECT
                    sum(ula.last_access > now() - INTERVAL 1 DAY) AS Day,
                    sum(ula.last_access > now() - INTERVAL 1 WEEK) AS Week,
                    sum(ula.last_access > now() - INTERVAL 1 MONTH) AS Month
                FROM users_main um
                INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
                WHERE um.Enabled = '1'
                    AND ula.last_access > now() - INTERVAL 1 MONTH
            ");
            $stats = $this->db->next_record(MYSQLI_ASSOC);
            $this->cache->cache_value('stats_users', $stats, 7200);
        }
        return $stats;
    }

    /**
     * Get the last year of user flow (joins, disables)
     *
     * @return array [week, joined, disabled]
     */
    public function userflow(): array {
        if (($userflow = $this->cache->get_value('userflow')) === false) {
            $this->db->query("
                SELECT J.Week, J.n as Joined, coalesce(D.n, 0) as Disabled
                FROM (
                    SELECT DATE_FORMAT(JoinDate, '%X-%V') AS Week, count(*) AS n
                    FROM users_info
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
            $userflow = $this->db->to_array('Week', MYSQLI_ASSOC, false);
            $this->cache->cache_value('userflow', $userflow, 86400);
        }
        return $userflow;
    }

    /**
     * Get total number of userflow changes (for pagination)
     *
     * @return int number of results
     */
    public function userflowTotal(): int {
        return $this->db->scalar("
            SELECT count(*) FROM (
                SELECT 1
                FROM users_info
                GROUP BY DATE_FORMAT(coalesce(BanDate, JoinDate), '%Y-%m-%d')
            ) D
        ") ?? 0;
    }

    /**
     * Get a page of userflow details
     *
     * @param int limit of resultset
     * @param int offset of resultset
     * @return array of array [day, month, joined, manual, ratio, inactivity]
     */
    public function userflowDetails(int $limit, int $offset): array {
        $this->db->prepared_query("
            SELECT j.Date                    AS date,
                DATE_FORMAT(j.Date, '%Y-%m') AS month,
                coalesce(j.Flow, 0)          AS joined,
                coalesce(m.Flow, 0)          AS manual,
                coalesce(r.Flow, 0)          AS ratio,
                coalesce(i.Flow, 0)          AS inactivity
            FROM (
                    SELECT
                        DATE_FORMAT(JoinDate, '%Y-%m-%d') AS Date,
                        count(*) AS Flow
                    FROM users_info
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
                ) AS m ON j.Date = m.Date
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
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Get the count of enabled users.
     *
     * @return integer Number of enabled users (this is cached).
     */
    public function getEnabledUsersCount(): int {
        if (($count = $this->cache->get_value('stats_user_count')) == false) {
            $count = $this->db->scalar("SELECT count(*) FROM users_main WHERE Enabled = '1'");
            $this->cache->cache_value('stats_user_count', $count, 0);
        }
        return $count;
    }

    /**
     * Can new members be invited at this time?
     * @return bool Yes we can
     */
    public function newUsersAllowed(): bool {
        return USER_LIMIT === 0 || $this->getEnabledUsersCount() < USER_LIMIT;
    }

    /**
     * Flush the cached count of enabled users.
     */
    public function flushEnabledUsersCount() {
        $this->cache->delete_value('stats_user_count');
        return $this;
    }

    /**
     * Disable a user from being able to use invites
     *
     * @param int user id
     * @return bool success (if invite status was changed)
     */
    public function disableInvites(int $userId): bool {
        $this->db->prepared_query("
            UPDATE users_info SET
                DisableInvites = '1'
            WHERE DisableInvites = '0'
                AND UserID = ?
            ", $userId
        );
        return $this->db->affected_rows() === 1;
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
        return $this->db->scalar("SELECT count(*) " . $this->sqlRatioWatchJoins());
    }

    /**
     * Get details of people on ratio watch
     *
     * @return array user details
     */
    public function ratioWatchUsers(int $limit, int $offset): array {
        $this->db->prepared_query("
            SELECT um.ID              AS user_id,
                uls.Uploaded          AS uploaded,
                uls.Downloaded        AS downloaded,
                ui.JoinDate           AS join_date,
                ui.RatioWatchEnds     AS ratio_watch_ends,
                ui.RatioWatchDownload AS ratio_watch_downloaded,
                um.RequiredRatio      AS required_ratio
            " . $this->sqlRatioWatchJoins() . "
            ORDER BY ui.RatioWatchEnds ASC
            LIMIT ? OFFSET ?
            ", $limit, $offset
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * How many users are banned for inadequate ratio?
     *
     * @return int number of users
     */
    public function totalBannedForRatio(): int {
        return $this->db->scalar("
            SELECT count(*) FROM users_info WHERE BanDate IS NOT NULL AND BanReason = '2'
        ");
    }

    /**
     * Sends a PM from $FromId to $ToId.
     *
     * @param string $toId ID of user to send PM to. If $toId is an array and $convId is empty, a message will be sent to multiple users.
     * @param string $fromId ID of user to send PM from, 0 to send from system
     * @param string $subject
     * @param string $body
     * @param int $convId The conversation the message goes in. Leave blank to start a new conversation.
     * @return int conversation Id
     */
    public function sendPM(int $toId, int $fromId, string $subject, string $body): int {
        if ($toId === 0 || $toId === $fromId) {
            // Don't allow users to send messages to the system or themselves
            return 0;
        }

        $qid = $this->db->get_query_id();
        $this->db->begin_transaction();

        $this->db->prepared_query("
            INSERT INTO pm_conversations (Subject) VALUES (?)
            ", $subject
        );
        $convId = $this->db->inserted_id();

        $placeholders = ["(?, ?, '1', '0', '1')"];
        $args = [$toId, $convId];
        if ($fromId !== 0) {
            $placeholders[] = "(?, ?, '0', '1', '0')";
            $args = array_merge($args, [$fromId, $convId]);
        }

        $this->db->prepared_query("
            INSERT INTO pm_conversations_users
                   (UserID, ConvID, InInbox, InSentbox, UnRead)
            VALUES
            " . implode(', ', $placeholders), ...$args
        );
        $this->deliverPM($toId, $fromId, $subject, $body, $convId);
        $this->db->commit();
        $this->db->set_query_id($qid);

        return $convId;
    }

    /**
     * Send a reply from $FromId to $ToId.
     *
     * @param string $toId ID of user to send PM to. If $toId is an array and $convId is empty, a message will be sent to multiple users.
     * @param string $fromId ID of user to send PM from, 0 to send from system
     * @param string $subject
     * @param string $body
     * @param int $convId The conversation the message goes in. Leave blank to start a new conversation.
     * @return int conversation Id
     */
    public function replyPM(int $toId, int $fromId, string $subject, string $body, int $convId): int {
        if ($toId === 0 || $toId === $fromId) {
            // Don't allow users to reply to the system or themselves
            return 0;
        }

        $qid = $this->db->get_query_id();
        $this->db->begin_transaction();
        $this->deliverPM($toId, $fromId, $subject, $body, $convId);
        $this->db->commit();
        $this->db->set_query_id($qid);

        return $convId;
    }

    protected function deliverPM(int $toId, int $fromId, string $subject, string $body, int $convId) {
        $this->db->prepared_query("
            UPDATE pm_conversations_users SET
                InInbox = '1',
                UnRead = '1',
                ReceivedDate = now()
            WHERE UserID = ?
                AND ConvID = ?
            ", $toId, $convId
        );
        $this->db->prepared_query("
            UPDATE pm_conversations_users SET
                InSentbox = '1',
                SentDate = now()
            WHERE UserID = ?
                AND ConvID = ?
            ", $fromId, $convId
        );

        $this->db->prepared_query("
            INSERT INTO pm_messages
                   (SenderID, ConvID, Body)
            VALUES (?,        ?,      ?)
            ", $fromId, $convId, $body
        );

        // Update the cached new message count.
        $this->cache->cache_value("inbox_new_$toId",
            $this->db->scalar("
                SELECT count(*) FROM pm_conversations_users WHERE UnRead = '1' AND InInbox = '1' AND UserID = ?
                ", $toId
            )
        );

        $senderName = $this->db->scalar("
            SELECT Username FROM users_main WHERE ID = ?
            ", $fromId
        );
        (new Notification)->push($toId,
            "Message from $senderName, Subject: $subject", $body, SITE_URL . '/inbox.php', Notification::INBOX
        );
    }

    /**
     * Warn a user.
     *
     * @param int $userId
     * @param int $duration length of warning in seconds
     * @param string $reason
     * @return int 1 if user was warned
     */
    public function warn(int $userId, int $duration, string $reason, string $staffName): int {
        $current = $this->db->scalar("
            SELECT Warned FROM users_info WHERE UserID = ?
            ", $userId
        );
        if (is_null($current)) {
            // User was not already warned
            $this->cache->deleteMulti(["u_$userId", "user_info_$userId"]);
            $warnTime = time_plus($duration);
            $warning = "Warned until $warnTime";
        } else {
            // User was already warned, appending new warning to old.
            $warnTime = date('Y-m-d H:i:s', strtotime($current) + $duration);
            $warning = "Warning extended until $warnTime";
            $this->sendPM($userId, 0,
                'You have received multiple warnings.',
                "When you received your latest warning (set to expire on "
                    . date('Y-m-d', (time() + $duration))
                    . "), you already had a different warning (set to expire at $current).\n\n"
                    . "Due to this collision, your warning status will now expire at $warnTime."
            );
        }
        $this->db->prepared_query("
            UPDATE users_info SET
                WarnedTimes = WarnedTimes + 1,
                Warned = ?,
                AdminComment = CONCAT(now(), ' - ', ?, AdminComment)
            WHERE UserID = ?
            ", $warnTime, "$warning by $staffName\nReason: $reason\n\n", $userId
        );
        return $this->db->affected_rows();
    }

    /**
     * Disable a list of users.
     *
     * @param array userIds (use [$userId] to disable a single user).
     * @param string staff note
     * @param int reason for disabling
     * @return int number of users disabled
     */
    public function disableUserList(array $userIds, string $comment, int $reason): int {
        $this->db->begin_transaction();
        $this->db->prepared_query("
            UPDATE users_info AS ui
            INNER JOIN users_main AS um ON (um.ID = ui.UserID) SET
                um.Enabled = '2',
                um.can_leech = '0',
                ui.BanDate = now(),
                ui.AdminComment = CONCAT(now(), ' - ', ?, ui.AdminComment),
                ui.BanReason = ?
            WHERE um.ID IN (" . placeholders($userIds) . ")
            ", "$comment\n\n", $reason, ...$userIds
        );
        $n = $this->db->affected_rows();

        $this->db->prepared_query("
            SELECT concat('session_', SessionID) as cacheKey
            FROM users_sessions
            WHERE Active = 1
                AND UserID IN (" . placeholders($userIds) . ")
            ", ...$userIds
        );
        $this->cache->deleteMulti($this->db->collect('cacheKey'));
        $this->db->prepared_query("
            DELETE FROM users_sessions WHERE UserID IN (" . placeholders($userIds) . ")
            ", ...$userIds
        );
        foreach ($userIds as $userId) {
            $this->cache->deleteMulti([
                "u_$userId", "user_info_$userId", "user_info_heavy_$userId", "user_stats_$userId", "users_sessions_$userId"
            ]);

        }
        $this->flushEnabledUsersCount();

        // Remove the users from the tracker.
        $this->db->prepared_query("
            SELECT torrent_pass FROM users_main WHERE ID IN (" . placeholders($userIds) . ")
            ", ...$userIds
        );
        $PassKeys = $this->db->collect('torrent_pass');
        $this->db->commit();
        $Concat = '';
        foreach ($PassKeys as $PassKey) {
            if (strlen($Concat) > 3950) { // Ocelot's read buffer is 4 KiB and anything exceeding it is truncated
                \Tracker::update_tracker('remove_users', ['passkeys' => $Concat]);
                $Concat = $PassKey;
            } else {
                $Concat .= $PassKey;
            }
        }
        \Tracker::update_tracker('remove_users', ['passkeys' => $Concat]);
        return $n;
    }

    /**
     * Manage donor status visibility
     */
    protected function setDonorVisibility(\Gazelle\User $user, bool $visible): int {
        $hidden = $visible ? '0' : '1';
        $this->db->prepared_query("
            INSERT INTO users_donor_ranks
                   (UserID, Hidden)
            VALUES (?,      ?)
            ON DUPLICATE KEY UPDATE
                Hidden = ?
            ", $user->id(), $hidden, $hidden
        );
        return $this->db->affected_rows();
    }

    public function hideDonor(\Gazelle\User $user): int {
        return $this->setDonorVisibility($user, false);
    }

    public function showDonor(\Gazelle\User $user): int {
        return $this->setDonorVisibility($user, true);
    }
}
