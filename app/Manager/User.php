<?php

namespace Gazelle\Manager;

class User extends \Gazelle\Base {

    protected const CACHE_STAFF = 'pm_staff_list';
    protected const ID_KEY = 'zz_u_%d';
    protected const USERNAME_KEY = 'zz_unam_%s';

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
                self::$cache->cache_value($key, $id, 0);
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
                self::$cache->cache_value($key, $id, 0);
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
     *
     * @param string username
     * @return \Gazelle\User object or null if not found
     */
    public function findByEmail(string $email): ?\Gazelle\User {
        return $this->findById((int)self::$db->scalar("
            SELECT ID FROM users_main WHERE Email = ?  ", trim($email)
        ));
    }

    /**
     * Get a User object from their announceKey
     *
     * @param string announceKey
     * @return \Gazelle\User object or null if not found
     */
    public function findByAnnounceKey(string $announceKey): ?\Gazelle\User {
        return $this->findById((int)self::$db->scalar("
            SELECT ID FROM users_main WHERE torrent_pass = ?  ", $announceKey
        ));
    }

    /**
     * Get a User object from their password reset key
     *
     * @param string key
     * @return \Gazelle\User object or null if not found
     */
    public function findByResetKey(string $key): ?\Gazelle\User {
        return $this->findById((int)self::$db->scalar("
            SELECT ui.UserID FROM users_info ui WHERE ui.ResetKey = ?
            ", $key
        ));
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
     * Generate HTML for a user's avatar
     *
     * @param Gazelle\User viewer Who is doing the viewing, to determine how to fallback if no avatar is available
     * @param Gazelle\User viewed Which avatar is being viewed
     */
    public function avatarMarkup(\Gazelle\User $viewer, \Gazelle\User $viewed): string {
        static $cache = [];
        $viewedId = $viewed->id();
        if (!isset($cache[$viewedId])) {
            $imgProxy = (new \Gazelle\Util\ImageProxy)->setViewer($viewer);
            switch ($viewer->avatarMode()) {
                case 1:
                    $avatar = STATIC_SERVER . '/common/avatars/default.png';
                    break;
                case 2:
                    $avatar = $imgProxy->process($viewed->avatar(), 'avatar', $viewedId)
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
                    $avatar = $imgProxy->process($viewed->avatar(), 'avatar', $viewedId)
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
                . ($second ? ("<div><img $attr class=\"avatar_1\" src=\"" . $imgProxy->process($second, 'avatar2', $viewedId) . '" /></div>') : '')
                . "</div>";
        }
        return $cache[$viewedId];
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
            self::$cache->cache_value('user_class', $classList, 0);
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
        if (($staffClassList = self::$cache->get_value('staff_class')) === false) {
            self::$db->prepared_query("
                SELECT ID, Name, Level
                FROM permissions
                WHERE Secondary = 0
                    AND LEVEL >= (SELECT Level FROM permissions WHERE ID = ?)
                ORDER BY Level
                ", FORUM_MOD
            );
            $staffClassList = self::$db->to_array('ID', MYSQLI_ASSOC);
            self::$cache->cache_value('staff_class', $staffClassList, 0);
        }
        return $staffClassList;
    }

    public function staffListGrouped() {
        if (($staff = self::$cache->get_value('idstaff')) === false) {
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
        $userMan = new \Gazelle\Manager\User;
        foreach ($staff as &$group) {
            $group = array_map(function ($userId) use ($userMan) { return $userMan->findById($userId); }, $group);
        }
        return $staff;
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
     * Get the number of enabled users last day/week/month
     *
     * @return array [Day, Week, Month]
     */
    public function globalActivityStats(): array {
        if (($stats = self::$cache->get_value('stats_users')) === false) {
            self::$db->prepared_query("
                SELECT
                    sum(ula.last_access > now() - INTERVAL 1 DAY) AS Day,
                    sum(ula.last_access > now() - INTERVAL 1 WEEK) AS Week,
                    sum(ula.last_access > now() - INTERVAL 1 MONTH) AS Month
                FROM users_main um
                INNER JOIN user_last_access AS ula ON (ula.user_id = um.ID)
                WHERE um.Enabled = '1'
                    AND ula.last_access > now() - INTERVAL 1 MONTH
            ");
            $stats = self::$db->next_record(MYSQLI_ASSOC);
            self::$cache->cache_value('stats_users', $stats, 7200);
        }
        return $stats;
    }

    /**
     * Get the last year of user flow (joins, disables)
     *
     * @return array [week, joined, disabled]
     */
    public function userflow(): array {
        if (($userflow = self::$cache->get_value('userflow')) === false) {
            self::$db->query("
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
            $userflow = self::$db->to_array('Week', MYSQLI_ASSOC, false);
            self::$cache->cache_value('userflow', $userflow, 86400);
        }
        return $userflow;
    }

    /**
     * Get total number of userflow changes (for pagination)
     *
     * @return int number of results
     */
    public function userflowTotal(): int {
        return self::$db->scalar("
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
        self::$db->prepared_query("
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
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Get the count of enabled users.
     *
     * @return integer Number of enabled users (this is cached).
     */
    public function getEnabledUsersCount(): int {
        if (($count = self::$cache->get_value('stats_user_count')) == false) {
            $count = self::$db->scalar("SELECT count(*) FROM users_main WHERE Enabled = '1'");
            self::$cache->cache_value('stats_user_count', $count, 0);
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
        self::$cache->delete_value('stats_user_count');
        return $this;
    }

    /**
     * Disable a user from being able to use invites
     *
     * @param int user id
     * @return bool success (if invite status was changed)
     */
    public function disableInvites(int $userId): bool {
        self::$db->prepared_query("
            UPDATE users_info SET
                DisableInvites = '1'
            WHERE DisableInvites = '0'
                AND UserID = ?
            ", $userId
        );
        return self::$db->affected_rows() === 1;
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
        return self::$db->scalar("SELECT count(*) " . $this->sqlRatioWatchJoins());
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
                ui.JoinDate           AS join_date,
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
        return self::$db->scalar("
            SELECT count(*) FROM users_info WHERE BanDate IS NOT NULL AND BanReason = '2'
        ");
    }

    /**
     * How many unresolved torrent reports are there for this user?
     * @param int User ID
     * @return int number of unresolved reports
     */
    public function unresolvedReportsTotal(int $userId): int {
        return self::$db->scalar("
            SELECT count(*)
            FROM reportsv2 AS r
            INNER JOIN torrents AS t ON (t.ID = r.TorrentID)
            WHERE r.Status != 'Resolved'
                AND t.UserID = ?
            ", $userId
        );
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

        $qid = self::$db->get_query_id();
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO pm_conversations (Subject) VALUES (?)
            ", $subject
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

        $qid = self::$db->get_query_id();
        self::$db->begin_transaction();
        $this->deliverPM($toId, $fromId, $subject, $body, $convId);
        self::$db->commit();
        self::$db->set_query_id($qid);

        return $convId;
    }

    protected function deliverPM(int $toId, int $fromId, string $subject, string $body, int $convId) {
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
        self::$cache->deleteMulti(["pm_{$convId}_{$fromId}", "pm_{$convId}_{$toId}"]);
        $senderName = self::$db->scalar("
            SELECT Username FROM users_main WHERE ID = ?
            ", $fromId
        );
        (new Notification)->push($toId,
            "Message from $senderName, Subject: $subject", $body, SITE_URL . '/inbox.php', Notification::INBOX
        );
    }

    public function sendSnatchPM(\Gazelle\User $viewer, \Gazelle\Torrent  $torrent, string $subject, string $body): int {
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
            . " of torrent " . $torrent->id() . " (" . $torrent->group()->displayNameText() . ")"
        );
        return $total;
    }

    public function sendRemovalPM(int $torrentId, int $uploaderId, string $name, string $log, int $trumpId, bool $pmUploader): int {
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
            FROM xbt_files_users AS xfu
            INNER JOIN users_info AS ui ON (xfu.uid = ui.UserID)
            WHERE ui.NotifyOnDeleteSeeding = '1'
                AND xfu.fid = ?
                AND xfu.uid NOT IN (" . placeholders($seen) . ")
            ", $torrentId, ...$seen
        );
        $ids = self::$db->collect('uid');
        foreach ($ids as $userId) {
            $this->sendPM($userId, 0, $subject, sprintf($message, 'you are seeding'));
        }
        $seen = array_merge($seen, $ids);

        self::$db->prepared_query("
            SELECT DISTINCT xs.uid
            FROM xbt_snatched AS xs
            INNER JOIN users_info AS ui ON (xs.uid = ui.UserID)
            WHERE ui.NotifyOnDeleteSnatched = '1'
                AND xs.fid = ?
                AND xs.uid NOT IN (" . placeholders($seen) . ")
            ", $torrentId, ...$seen
        );
        $ids = self::$db->collect('uid');
        foreach ($ids as $userId) {
            $this->sendPM($userId, 0, $subject, sprintf($message, 'you have snatched'));
        }
        $seen = array_merge($seen, $ids);

        self::$db->prepared_query("
            SELECT DISTINCT ud.UserID
            FROM users_downloads AS ud
            INNER JOIN users_info AS ui ON (ud.UserID = ui.UserID)
            WHERE ui.NotifyOnDeleteDownloaded = '1'
                AND ud.TorrentID = ?
                AND ud.UserID NOT IN (" . placeholders($seen) . ")
            ", $torrentId, ...$seen
        );
        $ids = self::$db->collect('UserID');
        foreach ($ids as $userId) {
            $this->sendPM($userId, 0, $subject, sprintf($message, 'you have downloaded'));
        }

        return count(array_merge($seen, $ids));
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
        $current = self::$db->scalar("
            SELECT Warned FROM users_info WHERE UserID = ?
            ", $userId
        );
        if (is_null($current)) {
            // User was not already warned
            self::$cache->delete_value("u_$userId");
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
        self::$db->prepared_query("
            UPDATE users_info SET
                WarnedTimes = WarnedTimes + 1,
                Warned = ?,
                AdminComment = CONCAT(now(), ' - ', ?, AdminComment)
            WHERE UserID = ?
            ", $warnTime, "$warning by $staffName\nReason: $reason\n\n", $userId
        );
        return self::$db->affected_rows();
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
        self::$db->begin_transaction();
        self::$db->prepared_query("
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
        $n = self::$db->affected_rows();

        self::$db->prepared_query("
            SELECT concat('session_', SessionID) as cacheKey
            FROM users_sessions
            WHERE Active = 1
                AND UserID IN (" . placeholders($userIds) . ")
            ", ...$userIds
        );
        self::$cache->deleteMulti(self::$db->collect('cacheKey'));
        self::$db->prepared_query("
            DELETE FROM users_sessions WHERE UserID IN (" . placeholders($userIds) . ")
            ", ...$userIds
        );
        foreach ($userIds as $userId) {
            self::$cache->deleteMulti([
                "u_$userId", "user_stats_$userId", "users_sessions_$userId"
            ]);

        }
        $this->flushEnabledUsersCount();

        // Remove the users from the tracker.
        self::$db->prepared_query("
            SELECT torrent_pass FROM users_main WHERE ID IN (" . placeholders($userIds) . ")
            ", ...$userIds
        );
        $PassKeys = self::$db->collect('torrent_pass');
        self::$db->commit();
        $Concat = '';
        $tracker = new \Gazelle\Tracker;
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

    /**
     * Manage donor status visibility
     */
    protected function setDonorVisibility(\Gazelle\User $user, bool $visible): int {
        $hidden = $visible ? '0' : '1';
        self::$db->prepared_query("
            INSERT INTO users_donor_ranks
                   (UserID, Hidden)
            VALUES (?,      ?)
            ON DUPLICATE KEY UPDATE
                Hidden = ?
            ", $user->id(), $hidden, $hidden
        );
        return self::$db->affected_rows();
    }

    public function hideDonor(\Gazelle\User $user): int {
        return $this->setDonorVisibility($user, false);
    }

    public function showDonor(\Gazelle\User $user): int {
        return $this->setDonorVisibility($user, true);
    }

    public function donorRewardTotal() {
        return self::$db->scalar("
            SELECT count(*)
            FROM users_main AS um
            INNER JOIN users_eonor_ranks AS d ON (d.UserID = um.ID)
            INNER JOIN donor_rewards AS r ON (r.UserID = um.ID)
        ");
    }

    public function donorRewardPage($search, int $limit, int $offset): array {
        $args = [$limit, $offset];
        if (is_null($search)) {
            $where = '';
        } else {
            $where = "WHERE um.username REGEXP ?";
            array_unshift($args, $search);
        }
        self::$db->prepared_query("
            SELECT um.Username,
                d.UserID AS user_id,
                d.donor_rank,
                if(hidden=0, 'No', 'Yes') AS hidden,
                d.DonationTime AS donation_time,
                r.IconMouseOverText AS icon_mouse,
                r.AvatarMouseOverText AS avatar_mouse,
                r.CustomIcon AS custom_icon,
                r.SecondAvatar AS second_avatar,
                r.CustomIconLink AS custom_link
            FROM users_main AS um
            INNER JOIN users_donor_ranks AS d ON (d.UserID = um.ID)
            INNER JOIN donor_rewards AS r ON (r.UserID = um.ID)
            $where ORDER BY d.donor_rank DESC, d.DonationTime ASC
            LIMIT ? OFFSET ?
            ", ...$args
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
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
        $criteria = [
            USER => [
                'From' => USER,
                'To' => MEMBER,
                'MinUpload' => 10 * 1024 * 1024 * 1024,
                'MinRatio' => 0.7,
                'MinUploads' => 0,
                'Weeks' => 1
            ],
            MEMBER => [
                'From' => MEMBER,
                'To' => POWER,
                'MinUpload' => 25 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'MinUploads' => 5,
                'Weeks' => 2
            ],
            POWER => [
                'From' => POWER,
                'To' => ELITE,
                'MinUpload' => 100 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'MinUploads' => 50,
                'Weeks' => 4
            ],
            ELITE => [
                'From' => ELITE,
                'To' => TORRENT_MASTER,
                'MinUpload' => 500 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'MinUploads' => 500,
                'Weeks' => 8
            ],
            TORRENT_MASTER => [
                'From' => TORRENT_MASTER,
                'To' => POWER_TM,
                'MinUpload' => 500 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'MinUploads' => 500,
                'Weeks' => 8,
                'Extra' => [
                    'Unique groups' => [
                        'Query' => '
                            SELECT count(DISTINCT GroupID) AS val
                            FROM torrents
                            WHERE UserID = users_main.ID',
                        'Count' => 500,
                        'Type' => 'int'
                    ]
                ]
            ],
            POWER_TM => [
                'From' => POWER_TM,
                'To' => ELITE_TM,
                'MinUpload' => 500 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'MinUploads' => 500,
                'Weeks' => 8,
                'Extra' => [
                    '"Perfect" FLACs' => [
                        'Query' => "
                            SELECT count(DISTINCT t.GroupID)
                            FROM torrents t
                            WHERE t.Format = 'FLAC'
                                AND (
                                    (t.Media = 'CD' AND t.LogScore = 100)
                                    OR (t.Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT'))
                                )
                                AND t.UserID = users_main.ID",
                        'Count' => 500,
                        'Type' => 'int'
                    ]
                ]
            ],
            ELITE_TM => [
                'From' => ELITE_TM,
                'To' => ULTIMATE_TM,
                'MinUpload' => 2 * 1024 * 1024 * 1024 * 1024,
                'MinRatio' => 1.05,
                'Weeks' => 12,
                'Extra' => [
                    '"Perfecter" FLACs' => [
                        'Query' => "
                            SELECT count(DISTINCT t.GroupID)
                            FROM torrents t
                            WHERE t.Format = 'FLAC'
                                AND (
                                       (t.Media = 'CD' AND t.LogScore = 100)
                                    OR t.Media IN ('Cassette', 'DAT')
                                    OR (t.Media IN ('Vinyl', 'DVD', 'Soundboard', 'SACD', 'BD') AND t.Encoding = '24bit Lossless')
                                )
                                AND t.UserID = users_main.ID",
                        'Count' => 2000,
                        'Type' => 'int'
                    ]
                ]
            ]
        ];
        if (RECOVERY_DB) {
            $criteria[ELITE_TM]['Extra'][SITE_NAME . ' Upload'] = [
               'Query' => "
                    SELECT uls.Uploaded + coalesce(b.Bounty, 0) - coalesce(rb.final, 0)
                    FROM users_leech_stats uls
                    LEFT JOIN
                    (
                        SELECT UserID, sum(Bounty) AS Bounty
                        FROM requests_votes
                        GROUP BY UserID
                    ) b ON (b.UserID = uls.UserID)
                    LEFT JOIN recovery_buffer rb ON (rb.user_id = uls.UserID)
                    WHERE uls.UserID = users_main.ID",
               'Count' => 2 * 1024 * 1024 * 1024 * 1024,
               'Type' => 'bytes'
            ];
        }
        return $criteria;
    }

    public function addMassTokens(int $amount, bool $allowLeechDisabled): int {
        $where = !$allowLeechDisabled ? "um.Enabled = '1' AND um.can_leech = 1" : "um.Enabled = '1'";

        self::$db->begin_transaction();
        self::$db->prepared_query("
            SELECT ID FROM users_main um WHERE $where
        ");
        $ids = self::$db->collect('ID');
        self::$db->prepared_query("
            UPDATE users_main um
            INNER JOIN user_flt uf ON (uf.user_id = um.ID) SET
                uf.tokens = uf.tokens + ?
            WHERE $where
            ", $amount
        );
        self::$db->commit();

        self::$cache->deleteMulti(array_map(fn($id) => "u_$id", $ids));
        return count($ids);
    }

    public function clearMassTokens(int $amount, bool $allowLeechDisabled, bool $onlyDrop): int {
        $cond = [];
        if (!$onlyDrop) {
            $cond[] = "um.Enabled = '1'";
            if (!$allowLeechDisabled) {
                $cond[] = "um.can_leech = 1";
            }
        }
        if (count($cond) == 2) {
            $cond = ['(' . implode(' AND ', $cond) . ')'];
        }
        array_push($cond, "uf.tokens > ?");
        $where = implode(' OR ', $cond);

        self::$db->begin_transaction();
        self::$db->prepared_query("
            SELECT ID FROM users_main um INNER JOIN user_flt uf ON (uf.user_id = um.ID) WHERE $where
            ", $amount
        );
        $ids = self::$db->collect('ID');
        self::$db->prepared_query("
            UPDATE users_main um
            INNER JOIN user_flt uf ON (uf.user_id = um.ID) SET
                uf.tokens = ?
            WHERE $where
            ", $amount
        );
        self::$db->commit();

        self::$cache->deleteMulti(array_map(fn($id) => "u_$id", $ids));
        return count($ids);
    }

    public  function forumNavItemUserList(\Gazelle\User $user): array {
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
}
