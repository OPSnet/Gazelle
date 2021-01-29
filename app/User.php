<?php

namespace Gazelle;

use Gazelle\Util\Mail;

class User extends BaseObject {

    /** @var int */
    protected $forceCacheFlush = false;

    /** @var array queue of forum warnings to persist */
    protected $forumWarning = [];

    /** @var array queue of staff notes to persist */
    protected $staffNote = [];

    /** @var array contents of info
     * TODO: kill $heavy and $light
     */
    protected $info;

    /** @var array contents of \Users::user_heavy_info */
    protected $heavy;

    /** @var array contents of \Users::user_info */
    protected $light;

    /** @var \Gazelle\Manager\Torrent to look up torrents associated with a user (snatched, uploaded, ...) */
    protected $torMan;

    /** @var \Gazelle\Manager\TorrentLabel to look up torrents associated with a user (snatched, uploaded, ...) */
    protected $labelMan;

    const DISCOGS_API_URL = 'https://api.discogs.com/artists/%d';

    public function tableName(): string {
        return 'users_main';
    }

    public function __construct(int $id) {
        parent::__construct($id);
    }

    public function setTorrentManager(Manager\Torrent $torMan) {
        $this->torMan = $torMan;
        return $this;
    }

    public function setTorrentLabelManager(Manager\TorrentLabel $labelMan) {
        $this->labelMan = $labelMan;
        return $this;
    }

    public function url(): string {
        return SITE_URL . "/user.php?id=" . $this->id;
    }

    public function info(): ?array {
        if ($this->info) {
            return $this->info;
        }
        $this->db->prepared_query("
            SELECT um.Username,
                um.IP,
                um.Email,
                um.Paranoia,
                um.PermissionID,
                um.Title,
                um.Enabled,
                um.Invites,
                um.can_leech,
                um.Visible,
                um.torrent_pass,
                um.RequiredRatio,
                um.IRCKey,
                um.2FA_Key,
                ui.AdminComment,
                ui.Collages,
                ui.DisableAvatar,
                ui.DisableInvites,
                ui.DisablePoints,
                ui.DisablePosting,
                ui.DisableForums,
                ui.DisableTagging,
                ui.DisableUpload,
                ui.DisableWiki,
                ui.DisablePM,
                ui.DisableIRC,
                ui.DisableRequests,
                ui.JoinDate,
                ui.NotifyOnQuote,
                ui.PermittedForums,
                ui.RatioWatchEnds,
                ui.RestrictedForums,
                ui.SiteOptions,
                ui.SupportFor,
                ui.Warned,
                uls.Uploaded,
                uls.Downloaded,
                p.Level AS Class,
                uf.tokens AS FLTokens,
                coalesce(ub.points, 0) AS BonusPoints,
                la.Type as locked_account
            FROM users_main              AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            INNER JOIN users_info        AS ui ON (ui.UserID = um.ID)
            INNER JOIN user_flt          AS uf ON (uf.user_id = um.ID)
            LEFT JOIN permissions        AS p ON (p.ID = um.PermissionID)
            LEFT JOIN user_bonus         AS ub ON (ub.user_id = um.ID)
            LEFT JOIN locked_accounts    AS la ON (la.UserID = um.ID)
            WHERE um.ID = ?
            ", $this->id
        );
        $this->info = $this->db->next_record(MYSQLI_ASSOC, false) ?? [];
        if (empty($this->info)) {
            return $this->info;
        }
        $this->info['CommentHash'] = sha1($this->info['AdminComment']);
        $this->info['DisableForums'] = (bool)($this->info['DisableForums'] == '1');
        $this->info['DisableInvites'] = (bool)($this->info['DisableInvites'] == '1');
        $this->info['DisableRequests'] = (bool)($this->info['DisableRequests'] == '1');
        $this->info['NotifyOnQuote'] = (bool)($this->info['NotifyOnQuote'] == '1');
        $this->info['Paranoia'] = unserialize($this->info['Paranoia']) ?: [];
        $this->info['SiteOptions'] = unserialize($this->info['SiteOptions']) ?: ['HttpsTracker' => true];
        $this->info['RatioWatchEndsEpoch'] = strtotime($this->info['RatioWatchEnds']);
        $this->db->prepared_query("
            SELECT PermissionID FROM users_levels WHERE UserID = ?
            ", $this->id
        );
        $this->info['secondary_class'] = $this->db->collect(0);
        $this->info['effective_class'] = $this->info['secondary_class']
            ? max($this->info['Class'], ...$this->info['secondary_class'])
            : $this->info['Class'];
        $this->info['is_donor'] = in_array(
            $this->db->scalar("SELECT ID FROM permissions WHERE Name = 'Donor' LIMIT 1"),
            $this->info['secondary_class']
        );

        $this->db->prepared_query("
            SELECT ua.Name, ua.ID
            FROM user_attr ua
            INNER JOIN user_has_attr uha ON (uha.UserAttrID = ua.ID)
            WHERE uha.UserID = ?
            ", $this->id
        );
        $this->info['attr'] = $this->db->to_pair('Name', 'ID');
        return $this->info;
    }

    public function hasAttr(string $name): ?int {
        $attr = $this->info()['attr'];
        return isset($attr[$name]) ? $attr[$name] : null;
    }

    protected function toggleAttr(string $attr, bool $flag): bool {
        $attrId = $this->hasAttr($attr);
        $found = !is_null($attrId);
        $toggled = false;
        if (!$flag && $found) {
            $this->db->prepared_query('
                DELETE FROM user_has_attr WHERE UserID = ? AND UserAttrID = ?
                ', $this->id, $attrId
            );
            $toggled = $this->db->affected_rows() === 1;
        }
        elseif ($flag && !$found) {
            $this->db->prepared_query('
                INSERT INTO user_has_attr (UserID, UserAttrID)
                    SELECT ?, ID FROM user_attr WHERE Name = ?
                ', $this->id, $attr
            );
            $toggled = $this->db->affected_rows() === 1;
        }
        return $toggled;
    }

    /**
     * toggle Unlimited Download setting
     */
    public function toggleUnlimitedDownload(bool $flag): bool {
        return $this->toggleAttr('unlimited-download', $flag);
    }

    public function hasUnlimitedDownload(): bool {
        return $this->hasAttr('unlimited-download');
    }

    /**
     * toggle Accept FL token setting
     * If user accepts FL tokens and the refusal attribute is found, delete it.
     * If user refuses FL tokens and the attribute is not found, insert it.
     */
    public function toggleAcceptFL($flag): bool {
        return $this->toggleAttr('no-fl-gifts', !$flag);
    }

    public function hasAcceptFL(): bool {
        return !$this->hasAttr('no-fl-gifts');
    }

    public function option(string $option) {
        return $this->info()['SiteOptions'][$option] ?? null;
    }

    protected function light() {
        if (!$this->light) {
            $this->light = \Users::user_info($this->id);
        }
        return $this->light;
    }

    protected function heavy() {
        if (!$this->heavy) {
            $this->heavy = \Users::user_heavy_info($this->id);
        }
        return $this->heavy;
    }

    public function username(): string {
        return $this->info()['Username'];
    }

    public function announceKey(): string {
        return $this->info()['torrent_pass'];
    }

    public function announceUrl(): string {
        return ($this->info()['SiteOptions']['HttpsTracker'] ? ANNOUNCE_HTTPS_URL : ANNOUNCE_HTTP_URL)
            . '/' . $this->announceKey() . '/announce';
    }

    public function disableForums(): bool {
        return $this->info()['DisableForums'];
    }

    public function disableRequests(): bool {
        return $this->info()['DisableRequests'];
    }

    public function email(): string {
        return $this->info()['Email'];
    }

    public function IRCKey() {
        return $this->info()['IRCKey'];
    }

    public function TFAKey() {
        return $this->info()['2FA_Key'];
    }

    public function joinDate() {
        return $this->info()['JoinDate'];
    }

    public function supportFor() {
        return $this->info()['SupportFor'];
    }

    public function avatarMode(): string {
        return $this->heavy()['DisableAvatars'] ?? '0';
    }

    public function primaryClass(): int {
        // temp hack to understand why this is sometimes null
        $permId = $this->info()['PermissionID'];
        if (is_null($permId)) {
            (new Manager\User)->sendPM(2, 0, "TypeError caught by user " . $this->id,
                var_export($_SERVER, true) . "\n\n"
                . var_export($_REQUEST, true) . "\n\n"
                . var_export(debug_backtrace(), true)
            );
        }
        return $this->info()['PermissionID'];
    }

    public function classLevel(): int {
        return $this->info()['Class'];
    }

    public function effectiveClass(): int {
        return $this->info()['effective_class'];
    }

    /**
     * Checks whether user has autocomplete enabled
     *
     * @param string Where the is the input requested (search, other)
     * @return boolean
     */
    public function hasAutocomplete($Type): bool {
        $autoComplete = $this->option('AutoComplete');
        if (is_null($autoComplete)) {
            // not set, default to enabled
            return true;
        } elseif ($autoComplete == 1) {
            // disabled
            return false;
        } elseif ($Type === 'search' && $autoComplete != 1) {
            return true;
        } elseif ($Type === 'other' && $autoComplete != 2) {
            return true;
        }
        return false;
    }

    public function forbiddenForums(): array {
        $heavy = $this->heavy();
        return isset($heavy['CustomForums']) ? array_keys($heavy['CustomForums'], 0) : [];
    }

    public function permittedForums(): array {
        $heavy = $this->heavy();
        $permitted = isset($heavy['CustomForums']) ? array_keys($heavy['CustomForums'], 1) : [];
        // TODO: This logic needs to be moved from the Donations manager to the User
        $donorMan = new Manager\Donation;
        if ($donorMan->hasForumAccess($this->id) && !in_array(DONOR_FORUM, $permitted)) {
            $permitted[] = DONOR_FORUM;
        }
        return $permitted;
    }

    /**
     * Checks whether user has the permission to read a forum.
     *
     * @param \Gazelle\Forum the forum
     * @return boolean true if user has permission
     */
    public function readAccess(Forum $forum): bool {
        if (in_array($forum->id(), $this->permittedForums())) {
            return true;
        }
        if ($this->classLevel() < $forum->minClassRead() || in_array($forum->id(), $this->forbiddenForums())) {
            return false;
        }
        return true;
    }

    /**
     * When did the user last perform a global catchup on the forums?
     *
     * @return int epoch of catchup
     */
    public function forumCatchupEpoch() {
        $lastRead = $this->db->scalar("
            SELECT last_read FROM user_read_forum WHERE user_id = ?
            ", $this->id
        );
        return is_null($lastRead) ? 0 : strtotime($lastRead);
    }

    public function forceCacheFlush($flush = true) {
        return $this->forceCacheFlush = $flush;
    }

    public function flush() {
        $this->info = null;
        $this->heavy = null;
        $this->light = null;
        $this->cache->deleteMulti([
            "enabled_" . $this->id,
            "user_info_" . $this->id,
            "user_info_heavy_" . $this->id,
            "user_stats_" . $this->id,
        ]);
    }

    public function remove() {
        $this->db->prepared_query("
            DELETE FROM users_main WHERE ID = ?
            ", $this->id
        );
        $this->flush();
    }

    public function remove2FA() {
        return $this->setUpdate('2FA_Key', '')
            ->setUpdate('Recovery', '');
    }

    /**
     * Record a forum warning for this user
     *
     * @param string reason for the warning.
     */
    public function addForumWarning(string $reason) {
        $this->forumWarning[] = $reason;
        return $this;
    }

    /**
     * Record a staff not for this user
     *
     * @param string staff note
     */
    public function addStaffNote(string $note) {
        $this->staffNote[] = $note;
        return $this;
    }

    /**
     * Set the user custom title
     *
     * @param string The text of the title (may contain BBcode)
     */
    public function setTitle(string $title) {
        $title = trim($title);
        $length = mb_strlen($title);
        if ($length > USER_TITLE_LENGTH) {
            throw new Exception\UserException("title-too-long:" . USER_TITLE_LENGTH . ":$length");
        }
        return $this->setUpdate('Title', $title);
    }

    /**
     * Remove the custom title of a user
     */
    public function removeTitle() {
        return $this->setUpdate('Title', null);
    }

    public function modify(): bool {
        $changed = false;
        if (!empty($this->forumWarning)) {
            $warning = implode(', ', $this->forumWarning);
            $this->db->prepared_query("
                INSERT INTO users_warnings_forums
                       (UserID, Comment)
                VALUES (?,      concat(now(), ' - ', ?))
                ON DUPLICATE KEY UPDATE
                    Comment = concat(Comment, '\n', now(), ' - ', ?)
                ", $this->id, $warning, $warning
            );
            $changed = $changed || $this->db->affected_rows() > 0; // 1 or 2 depending on whether the update is triggered
            $this->forumWarning = [];
        }
        if (!empty($this->staffNote)) {
            $this->db->prepared_query("
                UPDATE users_info SET
                AdminComment = CONCAT(now(), ' - ', ?, AdminComment)
                WHERE UserID = ?
                ", implode(', ', $this->staffNote) . "\n\n", $this->id
            );
            $changed = $changed || $this->db->affected_rows() === 1;
            $this->staffNote = [];
        }
        return parent::modify() || $changed;
    }

    public function mergeLeechStats(string $username, string $staffname) {
        [$mergeId, $up, $down] = $this->db->row("
            SELECT um.ID, uls.Uploaded, uls.Downloaded
            FROM users_main um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            WHERE um.Username = ?
            ", $username
        );
        if (!$mergeId) {
            return null;
        }
        $this->db->prepared_query("
            UPDATE users_leech_stats uls
            INNER JOIN users_info ui USING (UserID)
            SET
                uls.Uploaded = 0,
                uls.Downloaded = 0,
                ui.AdminComment = concat(now(), ' - ', ?, ui.AdminComment)
            WHERE uls.UserID = ?
            ", sprintf("leech stats (up: %s, down: %s, ratio: %s) transferred to %s (%s) by %s\n\n",
                    \Format::get_size($up), \Format::get_size($down), \Format::get_ratio($up, $down),
                    $this->url(), $this->username(), $staffname
            ),
            $mergeId
        );
        return ['up' => $up, 'down' => $down, 'userId' => $mergeId];
    }

    public function lock(int $lockType): bool {
        $this->db->prepared_query("
            INSERT INTO locked_accounts
                   (UserID, Type)
            VALUES (?,      ?)
            ON DUPLICATE KEY UPDATE Type = ?
            ", $this->id, $lockType, $lockType
        );
        return $this->db->affected_rows() === 1;
    }

    public function unlock(): bool {
        $this->db->prepared_query("
            DELETE FROM locked_accounts WHERE UserID = ?
            ", $this->id
        );
        return $this->db->affected_rows() === 1;
    }

    public function updateTokens(int $n): bool {
        $this->db->prepared_query('
            UPDATE user_flt SET
                tokens = ?
            WHERE user_id = ?
            ', $n, $this->id
        );
        return $this->db->affected_rows() === 1;
    }

    public function updateIP($oldIP, $newIP) {
        $this->db->prepared_query('
            UPDATE users_history_ips SET
                EndTime = now()
            WHERE EndTime IS NULL
                AND UserID = ?  AND IP = ?
                ', $this->id, $oldIP
        );
        $this->db->prepared_query('
            INSERT IGNORE INTO users_history_ips
                   (UserID, IP)
            VALUES (?,      ?)
            ', $this->id, $newIP
        );
        $this->db->prepared_query('
            UPDATE users_main SET
                IP = ?, ipcc = ?
            WHERE ID = ?
            ', $newIP, \Tools::geoip($newIP), $this->id
        );
        $this->heavy = null;
        $this->cache->delete_value('user_info_heavy_' . $this->id);
    }

    public function updatePassword(string $pw, string $ipaddr): bool {
        $this->db->prepared_query('
            UPDATE users_main SET
                PassHash = ?
            WHERE ID = ?
            ', UserCreator::hashPassword($pw), $this->id
        );
        if ($this->db->affected_rows() == 1) {
            $this->db->prepared_query('
                INSERT INTO users_history_passwords
                       (UserID, ChangerIP, ChangeTime)
                VALUES (?,      ?,         now())
                ', $this->id, $ipaddr
            );
        }
        return $this->db->affected_rows() === 1;
    }

    public function onRatioWatch(): bool {
        $stats = $this->activityStats();
        return $this->info()['RatioWatchEndsEpoch'] !== false
            && time() > $this->info()['RatioWatchEndsEpoch']
            && $stats['BytesUploaded'] <= $stats['BytesDownloaded'] * $stats['RequiredRatio'];
    }

    public function resetRatioWatch(): bool {
        $this->db->prepared_query("
            UPDATE users_info SET
                RatioWatchEnds = NULL,
                RatioWatchDownload = 0,
                RatioWatchTimes = 0
            WHERE UserID = ?
            ", $this->id
        );
        return $this->db->affected_rows() === 1;
    }

    public function unreadTorrentNotifications(): int {
        if (($new = $this->cache->get_value('notifications_new_' . $this->id)) === false) {
            $new = $this->db->scalar("
                SELECT count(*)
                FROM users_notify_torrents
                WHERE UnRead = '1'
                    AND UserID = ?
                ", $this->id
            );
            $this->cache->cache_value('notifications_new_' . $this->id, $new, 0);
        }
        return $new;
    }

    public function clearTorrentNotifications(): bool {
        $this->db->prepared_query("
            UPDATE users_notify_torrents
            SET Unread = '0'
            WHERE UnRead = '1'
                AND UserID = ?
            ", $this->id
        );
        $this->cache->delete_value('notifications_new_' . $this->id);
        return $this->db->affected_rows() === 1;
    }

    public function siteIPv4Summary(): array {
        $this->db->prepared_query("
            SELECT IP,
                min(StartTime) AS min_start,
                max(coalesce(EndTime, now())) AS max_end
            FROM users_history_ips
            WHERE UserID = ?
            GROUP BY IP
            ORDER BY inet_aton(IP), StartTime DESC, EndTime DESC
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_NUM, false);
    }

    public function trackerIPv4Summary(): array {
        $this->db->prepared_query("
            SELECT IP,
                from_unixtime(min(tstamp)) as first_seen,
                from_unixtime(max(tstamp)) as last_seen
            FROM xbt_snatched
            WHERE uid = ?
            GROUP BY inet_aton(IP)
            ORDER BY tstamp DESC
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_NUM, false);
    }

    public function resetIpHistory(): int {
        $n = 0;
        $this->db->prepared_query("
            DELETE FROM users_history_ips WHERE UserID = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        $this->db->prepared_query("
            UPDATE users_main SET IP = '127.0.0.1' WHERE ID = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        $this->db->prepared_query("
            UPDATE xbt_snatched SET IP = '' WHERE uid = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        $this->db->prepared_query("
            UPDATE users_history_passwords SET ChangerIP = '' WHERE UserID = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        $this->db->prepared_query("
            UPDATE users_history_passkeys SET ChangerIP = '' WHERE UserID = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        $this->db->prepared_query("
            UPDATE users_sessions SET IP = '127.0.0.1' WHERE UserID = ?
            ", $this->id
        );
        $n += $this->db->affected_rows();
        return $n;
    }

    public function resetEmailHistory(string $email, string $ipaddr): bool {
        $this->db->prepared_query("
            DELETE FROM users_history_emails
            WHERE UserID = ?
            ", $this->id
        );
        $this->db->prepared_query("
            INSERT INTO users_history_emails
                   (UserID, Email, IP)
            VALUES (?,      ?,     ?)
            ", $this->id, $email, $ipaddr
        );
        $this->db->prepared_query("
            UPDATE users_main
            SET Email = ?
            WHERE ID = ?
            ", $email, $this->id
        );
        return $this->db->affected_rows() === 1;
    }

    public function resetSnatched(): int {
        $this->db->prepared_query("
            DELETE FROM xbt_snatched
            WHERE uid = ?
            ", $this->id
        );
        $this->cache->delete_value("user_recent_snatch_" . $this->id);
        return $this->db->affected_rows();
    }

    public function resetDownloadList(): int {
        $this->db->prepared_query('
            DELETE FROM users_downloads
            WHERE UserID = ?
            ', $this->id
        );
        return $this->db->affected_rows();
    }

    public function resetPasskeyHistory(string $oldPasskey, string $newPasskey, string $ipaddr): bool {
        $this->db->prepared_query("
            INSERT INTO users_history_passkeys
                   (UserID, OldPassKey, NewPassKey, ChangerIP)
            VALUES (?,      ?,          ?,          ?)
            ", $this->id, $oldPasskey, $newPasskey, $ipaddr
        );
        return $this->db->affected_rows() === 1;
    }

    public function inboxUnreadCount(): int {
        if (($unread = $this->cache->get_value('inbox_new_' . $this->id)) === false) {
            $unread = $this->db->scalar("
                SELECT count(*)
                FROM pm_conversations_users
                WHERE UnRead    = '1'
                    AND InInbox = '1'
                    AND UserID  = ?
                ", $this->id
            );
            $this->cache->cache_value('inbox_new_' . $this->id, $unread, 0);
        }
        return $unread;
    }

    public function markAllReadInbox(): int {
        $this->db->prepared_query("
            UPDATE pm_conversations_users SET
                Unread = '0'
            WHERE Unread = '1'
                AND UserID = ?
            ", $this->id
        );
        $this->cache->delete_value('inbox_new_' . $this->id);
        return $this->db->affected_rows();
    }

    public function markAllReadStaffPM(): int {
        $this->db->prepared_query("
            UPDATE staff_pm_conversations SET
                Unread = false
            WHERE Unread = true
                AND UserID = ?
            ", $this->id
        );
        $this->cache->delete_value('staff_pm_new_' . $this->id);
        return $this->db->affected_rows();
    }

    public function supportCount(int $newClassId, int $levelClassId): int {
        return $this->db->scalar("
            SELECT count(DISTINCT DisplayStaff)
            FROM permissions
            WHERE ID IN (?, ?)
            ", $newClassId, $levelClassId
        );
    }

    public function updateCatchup(): bool {
        return (new WitnessTable\UserReadForum)->witness($this->id);
    }

    public function permissionList(): array {
        $this->db->prepared_query('
            SELECT
                p.ID                   AS permId,
                p.Name                 AS permName,
                (l.UserID IS NOT NULL) AS isSet
            FROM permissions AS p
            LEFT JOIN users_levels AS l ON (l.PermissionID = p.ID AND l.UserID = ?)
            WHERE p.Secondary = 1
            ORDER BY p.Name
            ', $this->id
        );
        return $this->db->to_array('permName', MYSQLI_ASSOC, false);
    }

    public function addClasses(array $classes): int {
        $this->db->prepared_query("
            INSERT IGNORE INTO users_levels (UserID, PermissionID)
            VALUES " . implode(', ', array_fill(0, count($classes), '(' . $this->id . ', ?)')),
            ...$classes
        );
        return $this->db->affected_rows();
    }

    public function removeClasses(array $classes): int {
        $this->db->prepared_query("
            DELETE FROM users_levels
            WHERE UserID = ?
                AND PermissionID IN (" . placeholders($classes) . ")",
            $this->id, ...$classes
        );
        return $this->db->affected_rows();
    }

    public function notifyFilters(): array {
        if ($this->forceCacheFlush || ($filters = $this->cache->get_value('notify_filters_' . $this->id)) === false) {
            $this->db->prepared_query('
                SELECT ID, Label
                FROM users_notify_filters
                WHERE UserID = ?
                ', $this->id
            );
            $filters = $this->db->to_array('ID');
            $this->cache->cache_value('notify_filters_' . $this->id, $filters, 2592000);
        }
        return $filters;
    }

    public function removeNotificationFilter(int $notifId): int {
        $this->db->prepared_query('
            DELETE FROM users_notify_filters
            WHERE UserID = ? AND ID = ?
            ', $this->id, $notifId
        );
        $removed = $this->db->affected_rows();
        if ($removed) {
            $this->cache->deleteMulti(['notify_filters_' . $this->id, 'notify_artists_' . $this->id]);
        }
        return $removed;
    }

    public function loadArtistNotifications(): array {
        $info = $this->cache->get_value('notify_artists_' . $this->id);
        if (empty($info)) {
            $this->db->prepared_query("
                SELECT ID, Artists
                FROM users_notify_filters
                WHERE Label = ?
                    AND UserID = ?
                ORDER BY ID
                LIMIT 1
                ", 'Artist notifications', $this->id
            );
            $info = $this->db->next_record(MYSQLI_ASSOC, false);
            if (!$info) {
                $info = ['ID' => 0, 'Artists' => ''];
            }
            $this->cache->cache_value('notify_artists_' . $this->id, $info, 0);
        }
        return $info;
    }

    public function hasArtistNotification(string $name): bool {
        $info = $this->loadArtistNotifications();
        return stripos($info['Artists'], "|$name|") !== false;
    }

    public function addArtistNotification(\Gazelle\Artist $artist): int {
        $info = $this->loadArtistNotifications();
        $alias = implode('|', $artist->aliasList());
        if (!$alias) {
            return 0;
        }

        $change = 0;
        if (!$info['ID']) {
            $this->db->prepared_query("
                INSERT INTO users_notify_filters
                       (UserID, Label, Artists)
                VALUES (?,      ?,     ?)
                ", $this->id, 'Artist notifications', "|$alias|"
            );
            $change = $this->db->affected_rows();
        } elseif (stripos($info['Artists'], "|$alias|") === false) {
            $this->db->prepared_query("
                UPDATE users_notify_filters SET
                    Artists = ?
                WHERE ID = ? AND Artists NOT LIKE concat('%', ?, '%')
                ", $info['Artists'] . "$alias|", $info['ID'], "|$alias|"
            );
            $change = $this->db->affected_rows();
        }
        if ($change) {
            $this->cache->deleteMulti(['notify_filters_' . $this->id, 'notify_artists_' . $this->id]);
        }
        return $change;
    }

    public function removeArtistNotification(\Gazelle\Artist $artist): int {
        $info = $this->loadArtistNotifications();
        $aliasList = $artist->aliasList();
        foreach ($aliasList as $alias) {
            while (stripos($info['Artists'], "|$alias|") !== false) {
                $info['Artists'] = str_ireplace("|$alias|", '|', $info['Artists']);
            }
        }
        $change = 0;
        if ($info['Artists'] === '||') {
            $this->db->prepared_query("
                DELETE FROM users_notify_filters
                WHERE ID = ?
                ", $info['ID']
            );
            $change = $this->db->affected_rows();
        } else {
            $this->db->prepared_query("
                UPDATE users_notify_filters SET
                    Artists = ?
                WHERE ID = ?
                ", $info['Artists'], $info['ID']
            );
            $change = $this->db->affected_rows();
        }
        if ($change) {
            $this->cache->deleteMulti(['notify_filters_' . $this->id, 'notify_artists_' . $this->id]);
        }
        return $change;
    }

    protected function enabledState(): int {
        if ($this->forceCacheFlush || ($enabled = $this->cache->get_value('enabled_' . $this->id)) === false) {
            $this->db->prepared_query("
                SELECT Enabled FROM users_main WHERE ID = ?
                ", $this->id()
            );
            [$enabled] = $this->db->next_record(MYSQLI_NUM);
            $this->cache->cache_value('enabled_' . $this->id, (int)$enabled, 86400 * 3);
        }
        return $enabled;
    }

    public function isUnconfirmed() { return $this->enabledState() == 0; }
    public function isEnabled()     { return $this->enabledState() == 1; }
    public function isDisabled()    { return $this->enabledState() == 2; }

    public function endWarningDate(int $weeks) {
        return $this->db->scalar("
            SELECT coalesce(Warned, now()) + INTERVAL ? WEEK
            FROM users_info
            WHERE UserID = ?
            ", $weeks, $this->id
        );
    }

    public function LastFMUsername(): string {
        return $this->db->scalar('
            SELECT username
            FROM lastfm_users
            WHERE ID = ?
            ', $this->id
        ) ?? '';
    }

    public function personalCollages(): array {
        $this->db->prepared_query("
            SELECT ID, Name
            FROM collages
            WHERE UserID = ?
                AND CategoryID = 0
                AND Deleted = '0'
            ORDER BY Featured DESC, Name ASC
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_NUM, false);
    }

    public function canCreatePersonalCollage(): bool {
        [$allowed, $created] = $this->db->row("
            SELECT i.collages, coalesce(c.num, 0)
            FROM users_info i
            LEFT JOIN
            (
                SELECT UserID, count(*) AS num
                FROM collages
                WHERE CategoryID = 0
                  AND Deleted = '0'
            ) c USING (UserID)
            WHERE i.UserID = ?
            ", $this->id
        );
        $donorMan = new Manager\Donation();
        $allowed += $donorMan->personalCollages($this->id);

        return $allowed > $created;
    }

    public function collageUnreadCount(): int {
        if (($new = $this->cache->get_value(sprintf(Collage::SUBS_NEW_KEY, $this->id))) === false) {
            $new = $this->db->scalar("
                 SELECT count(*) FROM (
                    SELECT s.LastVisit
                    FROM users_collage_subs s
                    INNER JOIN collages c ON (c.ID = s.CollageID)
                    LEFT JOIN collages_torrents ct ON (ct.CollageID = s.CollageID)
                    LEFT JOIN collages_artists ca ON (ca.CollageID = s.CollageID)
                    WHERE c.Deleted = '0'
                        AND s.UserID = ?
                    GROUP BY s.CollageID
                    HAVING max(coalesce(ct.AddedOn, ca.AddedOn)) > s.LastVisit
                ) unread
                ", $this->id
            );
            $this->cache->cache_value(sprintf(Collage::SUBS_NEW_KEY, $this->id), $new, 0);
        }
        return $new;
    }

    /**
     * Email history
     *
     * @return array [email address, ip, date]
     */
    public function emailHistory(): array {
        $this->db->prepared_query("
            SELECT
                h.Email,
                h.Time,
                h.IP
            FROM users_history_emails AS h
            WHERE h.UserID = ?
            ORDER BY h.Time DESC
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_NUM, false);
    }

    public function isFriend($id) {
        $this->db->prepared_query("
            SELECT 1
            FROM friends
            WHERE UserID = ?
                AND FriendID = ?
            ", $id, $this->id
        );
        return $this->db->has_results();
    }

    public function requestsBounty() {
        $this->db->prepared_query('
            SELECT COUNT(DISTINCT r.ID), SUM(rv.Bounty)
            FROM requests AS r
            LEFT JOIN requests_votes AS rv ON (r.ID = rv.RequestID)
            WHERE r.FillerID = ?
            ', $this->id
        );
        if ($this->db->has_results()) {
            [$filled, $bounty] = $this->db->next_record(MYSQLI_NUM);
        } else {
            $filled = $bounty = 0;
        }
        return [$filled, $bounty];
    }

    public function requestsVotes() {
        $this->db->prepared_query('
            SELECT count(*), coalesce(sum(Bounty), 0)
            FROM requests_votes
            WHERE UserID = ?
            ', $this->id
        );
        if ($this->db->has_results()) {
            [$voted, $bounty] = $this->db->next_record(MYSQLI_NUM);
        } else {
            $voted = $bounty = 0;
        }
        return [$voted, $bounty];
    }

    public function requestsCreated() {
        $this->db->prepared_query('
            SELECT count(*), coalesce(sum(rv.Bounty), 0)
            FROM requests AS r
            LEFT JOIN requests_votes AS rv ON (rv.RequestID = r.ID AND rv.UserID = r.UserID)
            WHERE r.UserID = ?
            ', $this->id
        );
        if ($this->db->has_results()) {
            [$created, $bounty] = $this->db->next_record(MYSQLI_NUM);
        } else {
            $created = $bounty = 0;
        }
        return [$created, $bounty];
    }

    public function clients(): array {
        $this->db->prepared_query('
            SELECT DISTINCT useragent
            FROM xbt_files_users
            WHERE uid = ?
            ', $this->id
        );
        return $this->db->collect(0) ?: ['None'];
    }

    protected function getSingleValue($cacheKey, $query) {
        $cacheKey .= '_' . $this->id;
        if ($this->forceCacheFlush || ($value = $this->cache->get_value($cacheKey)) === false) {
            $this->db->prepared_query($query, $this->id);
            [$value] = $this->db->next_record(MYSQLI_NUM);
            $this->cache->cache_value($cacheKey, $value, 3600);
        }
        return $value;
    }

    public function lastAccess() {
        return $this->getSingleValue('user-last-access', '
            SELECT ula.last_access
            FROM user_last_access ula
            WHERE user_id = ?
        ');
    }

    public function uploadCount(): int {
        return $this->getSingleValue('user-upload-count', '
            SELECT count(*)
            FROM torrents
            WHERE UserID = ?
        ');
    }

    public function leechingCounts(): int {
        return $this->getSingleValue('user-leeching-count', '
            SELECT count(*)
            FROM xbt_files_users AS x
            INNER JOIN torrents AS t ON (t.ID = x.fid)
            WHERE x.remaining > 0
                AND x.uid = ?
        ');
    }

    public function seedingCounts(): int {
        return $this->getSingleValue('user-seeding-count', '
            SELECT count(*)
            FROM xbt_files_users AS x
            INNER JOIN torrents AS t ON (t.ID = x.fid)
            WHERE x.remaining = 0
                AND x.uid = ?
        ');
    }

    public function artistsAdded(): int {
        return $this->getSingleValue('user-artists-count', '
            SELECT count(*)
            FROM torrents_artists
            WHERE UserID = ?
        ');
    }

    public function passwordCount(): int {
        return $this->getSingleValue('user-pw-count', '
            SELECT count(*)
            FROM users_history_passwords
            WHERE UserID = ?
        ');
    }

    public function passkeyCount(): int {
        return $this->getSingleValue('user-passkey-count', '
            SELECT count(*)
            FROM users_history_passkeys
            WHERE UserID = ?
        ');
    }

    public function siteIPCount(): int {
        return $this->getSingleValue('user-siteip-count', '
            SELECT count(DISTINCT IP)
            FROM users_history_ips
            WHERE UserID = ?
        ');
    }

    public function trackerIPCount(): int {
        return $this->getSingleValue('user-trackip-count', "
            SELECT count(DISTINCT IP)
            FROM xbt_snatched
            WHERE uid = ? AND IP != ''
        ");
    }

    public function emailCount(): int {
        return $this->getSingleValue('user-email-count', '
            SELECT count(*)
            FROM users_history_emails
            WHERE UserID = ?
        ');
    }

    public function invitedCount(): int {
        return $this->getSingleValue('user-invites', '
            SELECT count(*)
            FROM users_info
            WHERE Inviter = ?
        ');
    }

    public function pendingInviteCount(): int {
        return $this->getSingleValue('user-inv-pending', '
            SELECT count(*)
            FROM invites
            WHERE InviterID = ?
        ');
    }

    public function passwordAge() {
        $age = time_diff(
            $this->getSingleValue('user-pw-age', '
                SELECT coalesce(max(uhp.ChangeTime), ui.JoinDate)
                FROM users_info ui
                LEFT JOIN users_history_passwords uhp USING (UserID)
                WHERE ui.UserID = ?
            ')
        );
        return substr($age, 0, strpos($age, " ago"));
    }

    public function artistCommentCount(): int {
        return $this->getSingleValue('user-comment-artist', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'artists' AND AuthorID = ?
        ");
    }

    public function collageCommentCount(): int {
        return $this->getSingleValue('user-comment-collage', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'collages' AND AuthorID = ?
        ");
    }

    public function requestCommentCount(): int {
        return $this->getSingleValue('user-comment-request', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'requests' AND AuthorID = ?
        ");
    }

    public function torrentCommentCount(): int {
        return $this->getSingleValue('user-comment-torrent', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'torrents' AND AuthorID = ?
        ");
    }

    public function forumWarning() {
        return $this->getSingleValue('user-forumwarn', '
            SELECT Comment
            FROM users_warnings_forums
            WHERE UserID = ?
        ');
    }

    public function releaseVotes(): int {
        return $this->getSingleValue('user-release-votes', '
            SELECT count(*)
            FROM users_votes
            WHERE UserID = ?
        ');
    }

    public function bonusPointsSpent(): int {
        return (int)$this->getSingleValue('user-bp-spent', '
            SELECT sum(Price)
            FROM bonus_history
            WHERE UserID = ?
        ');
    }

    public function collagesCreated(): int {
        return $this->getSingleValue('user-collage-create', "
            SELECT count(*)
            FROM collages
            WHERE Deleted = '0' AND UserID = ?
        ");
    }

    public function artistCollageContributed(): int {
        return $this->getSingleValue('user-collage-a-contrib', "
            SELECT count(DISTINCT ct.CollageID)
            FROM collages_artists AS ct
            INNER JOIN collages AS c ON (ct.CollageID = c.ID)
            WHERE c.Deleted = '0' AND ct.UserID = ?
        ");
    }

    public function torrentCollageContributed(): int {
        return $this->getSingleValue('user-collage-t-contrib', "
            SELECT count(DISTINCT ct.CollageID)
            FROM collages_torrents AS ct
            INNER JOIN collages AS c ON (ct.CollageID = c.ID)
            WHERE c.Deleted = '0' AND ct.UserID = ?
        ");
    }

    public function collagesContributed(): int {
        return $this->artistCollageContributed() + $this->torrentCollageContributed();
    }

    public function artistCollageAdditions(): int {
        return $this->getSingleValue('user-collage-a-add', "
            SELECT count(*)
            FROM collages_artists AS ct
            INNER JOIN collages AS c ON (ct.CollageID = c.ID)
            WHERE c.Deleted = '0' AND ct.UserID = ?
        ");
    }

    public function torrentCollageAdditions(): int {
        return $this->getSingleValue('user-collage-t-add', "
            SELECT count(*)
            FROM collages_torrents AS ct
            INNER JOIN collages AS c ON (ct.CollageID = c.ID)
            WHERE c.Deleted = '0' AND ct.UserID = ?
        ");
    }

    public function collageAdditions(): int {
        return $this->artistCollageAdditions() + $this->torrentCollageAdditions();
    }

    public function peerCounts(): array {
        $this->db->prepared_query("
            SELECT IF(remaining = 0, 'seed', 'leech') AS Type, count(*)
            FROM xbt_files_users AS x
            INNER JOIN torrents AS t ON (t.ID = x.fid)
            WHERE x.active = 1
                AND x.uid = ?
            GROUP BY Type
            ", $this->id
        );
        $result = $this->db->to_array(0, MYSQLI_NUM, false);
        return [
            'seeding' => (isset($result['seed']) ? (int)$result['seed'][1] : 0),
            'leeching' => (isset($result['leech']) ? (int)$result['leech'][1] : 0)
        ];
    }

    public function snatchCounts(): array {
        $this->db->prepared_query('
            SELECT count(*), count(DISTINCT x.fid)
            FROM xbt_snatched AS x
            INNER JOIN torrents AS t ON (t.ID = x.fid)
            WHERE x.uid = ?
            ', $this->id
        );
        [$total, $unique] = $this->db->next_record(MYSQLI_NUM, false);
        return [(int)$total, (int)$unique];
    }

    public function recentSnatches(int $limit = 5) {
        if (($recent = $this->cache->get_value('user_recent_snatch_' . $this->id)) === false) {
            $this->db->prepared_query("
                SELECT
                    g.ID,
                    g.Name,
                    g.WikiImage
                FROM xbt_snatched AS s
                INNER JOIN torrents AS t ON (t.ID = s.fid)
                INNER JOIN torrents_group AS g ON (t.GroupID = g.ID)
                WHERE g.CategoryID = '1'
                    AND g.WikiImage != ''
                    AND t.UserID != s.uid
                    AND s.uid = ?
                GROUP BY g.ID
                ORDER BY s.tstamp DESC
                LIMIT ?
                ", $this->id, $limit
            );
            $recent = $this->db->to_array() ?? [];
            $artists = \Artists::get_artists($this->db->collect('ID'));
            foreach ($recent as $id => $info) {
                $recent[$id]['Name'] = \Artists::display_artists($artists[$info['ID']], false, true)
                    . $recent[$id]['Name'];
            }
            $this->cache->cache_value('user_recent_snatch_' . $this->id, $recent, 86400 * 3);
        }
        return $recent;
    }

    public function tagSnatchCounts(int $limit = 8) {
        if (($list = $this->cache->get_value('user_tag_snatch_' . $this->id)) === false) {
            $this->db->prepared_query("
                SELECT tg.Name AS name,
                    tg.ID AS id,
                    count(*) AS n
                FROM torrents_tags tt
                INNER JOIN tags tg ON (tg.ID = tt.TagID)
                INNER JOIN (
                    SELECT DISTINCT t.GroupID
                    FROM xbt_snatched xs
                    INNER JOIN torrents t ON (t.id = xs.fid)
                    WHERE tstamp > unix_timestamp(now() - INTERVAL 6 MONTH)
                        AND xs.uid = ?
                ) SN USING (GroupID)
                GROUP BY tg.ID
                ORDER BY 3 DESC, 1
                LIMIT ?
                ", $this->id, $limit
            );
            $list = $this->db->to_array(false, MYSQLI_ASSOC, false);
            $this->cache->cache_value('user_tag_snatch_' . $this->id, $list, 86400 * 90);
        }
        return $list;
    }

    public function recentUploads(int $limit = 5) {
        if (($recent = $this->cache->get_value('user_recent_up_' . $this->id)) === false) {
            $this->db->prepared_query("
                SELECT
                    g.ID,
                    g.Name,
                    g.WikiImage
                FROM torrents_group AS g
                INNER JOIN torrents AS t ON (t.GroupID = g.ID)
                WHERE g.WikiImage != ''
                    AND g.CategoryID = '1'
                    AND t.UserID = ?
                GROUP BY g.ID
                ORDER BY t.Time DESC
                LIMIT ?
                ", $this->id, $limit
            );
            $recent = $this->db->to_array() ?? [];
            $artists = \Artists::get_artists($this->db->collect('ID'));
            foreach ($recent as $id => $info) {
                $recent[$id]['Name'] = \Artists::display_artists($artists[$info['ID']], false, true)
                    . $recent[$id]['Name'];
            }
            $this->cache->cache_value('user_recent_up_' . $this->id, $recent, 86400 * 3);
        }
        return $recent;
    }

    public function downloadCounts() {
        $this->db->prepared_query('
            SELECT count(*), count(DISTINCT ud.TorrentID)
            FROM users_downloads AS ud
            INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
            WHERE ud.UserID = ?
            ', $this->id
        );
        [$total, $unique] = $this->db->next_record(MYSQLI_NUM, false);
        return [(int)$total, (int)$unique];
    }

    public function torrentDownloadCount($torrentId) {
        return (int)$this->db->scalar('
            SELECT count(*)
            FROM users_downloads ud
            INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
            WHERE ud.UserID = ?
                AND ud.TorrentID = ?
            ', $this->id, $torrentId
        );
    }

    public function torrentRecentDownloadCount() {
        return (int)$this->db->scalar('
            SELECT count(*)
            FROM users_downloads ud
            INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
            WHERE ud.Time > now() - INTERVAL 1 DAY
                AND ud.UserID = ?
            ', $this->id
        );
    }

    public function torrentRecentRemoveCount(int $hours): int {
        return (int)$this->db->scalar('
            SELECT count(*)
            FROM user_torrent_remove utr
            WHERE utr.user_id = ?
                AND utr.removed >= now() - INTERVAL ? HOUR
            ', $this->id, $hours
        );
    }

    public function downloadSnatchFactor() {
        if ($this->hasAttr('unlimited-download')) {
            // they are whitelisted, let them through
            return 0.0;
        }
        $stats = $this->cache->get_value('user_rlim_' . $this->id);
        if ($stats === false) {
            $this->db->prepared_query("
                SELECT 'download', count(DISTINCT ud.TorrentID) as nr
                FROM users_downloads ud
                INNER JOIN torrents t ON (t.ID = ud.TorrentID)
                WHERE ud.UserID = ? AND t.UserID != ?
                UNION ALL
                SELECT 'snatch', count(DISTINCT x.fid)
                FROM xbt_snatched AS x
                INNER JOIN torrents AS t ON (t.ID = x.fid)
                WHERE x.uid = ?
                ", $this->id, $this->id, $this->id
            );
            $stats = ['download' => 0, 'snatch' => 0];
            while ([$key, $count] = $this->db->next_record(MYSQLI_ASSOC)) {
                $stats[$key] = $count;
            }
            $stats = $this->cache->cache_value('user_rlim_' . $this->id, $stats, 3600);
        }
        return (1 + $stats['download']) / (1 + $stats['snatch']);
    }

    /**
     * Generates a check list of release types, ordered by the user or default
     * @param array $option
     * @param array $releaseType
     */
    public function releaseOrder(array $options, array $releaseType) {
        if (empty($options['SortHide'])) {
            $sort = $releaseType;
            $defaults = !empty($option['HideTypes']);
        } else {
            $sort = $options['SortHide'];
            $missingTypes = array_diff_key($releaseType, $sort);
            foreach (array_keys($missingTypes) as $missing) {
                $sort[$missing] = 0;
            }
        }

        $x = [];
        foreach ($sort as $key => $val) {
            if (isset($defaults)) {
                $checked = $defaults && isset($option['HideTypes'][$key]);
            } else {
                if (!isset($releaseType[$key])) {
                    continue;
                }
                $checked = $val;
                $val = $releaseType[$key];
            }
            $x[] = ['id' => $key. '_' . (int)(!!$checked), 'checked' => $checked, 'label' => $val];
        }
        return $x;
    }

    /**
     * Get a page of FL token uses by user
     *
     * @param int How many? (To fill a page)
     * @param int From where (which page)
     * @return array [torrent_id, group_id, created, expired, downloaded, uses, group_name, format, encoding, size]
     */
    public function tokenPage(int $limit, int $offset): array {
        $this->db->prepared_query("
            SELECT t.GroupID AS group_id,
                g.Name       AS group_name,
                t.ID         AS torrent_id,
                t.Size       AS size,
                f.Time       AS created,
                f.Expired    AS expired,
                f.Downloaded AS downloaded,
                f.Uses       AS uses
            FROM users_freeleeches AS f
            LEFT JOIN torrents AS t ON (t.ID = f.TorrentID)
            LEFT JOIN torrents_group AS g ON (g.ID = t.GroupID)
            WHERE f.UserID = ?
            ORDER BY f.Time DESC
            LIMIT ? OFFSET ?
            ", $this->id, $limit, $offset
        );
        $list = [];
        $torrents = $this->db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($torrents as $t) {
            if (!$t['group_id']) {
                $name = "(<i>Deleted torrent <a href=\"log.php?search=Torrent+{$t['torrent_id']}\">{$t['torrent_id']}</a></i>)";
            } else {
                $name = "<a href=\"torrents.php?id={$t['group_id']}&amp;torrentid={$t['torrent_id']}\">{$t['group_name']}</a>";
                $artist = $this->torMan->setGroupId($t['group_id'])->setTorrentId($t['torrent_id'])->artistHtml();
                if ($artist) {
                    $name = "$artist - $name";
                }
                $this->labelMan->load($this->torMan->torrentInfo()[1]);
                $name .= ' [' . $this->labelMan->label() . ']';
            }
            $t['expired'] = ($t['expired'] === 1);
            $t['name'] = $name;
            $list[] = $t;
        }
        return $list;
    }

    /**
     * Scale down user rank if certain steps have not been taken
     * @return float Scaling factor between 0.0 and 1.0
     */
    public function rankFactor(): float {
        $factor = 1.0;
        if (!strlen($this->light()['Avatar'])) {
            $factor *= 0.75;
        }
        if (!strlen($this->heavy()['Info'])) {
            $factor *= 0.75;
        }
        return $factor;
    }

    public function activityStats(): array {
        if (($stats = $this->cache->get_value('user_stats_' . $this->id)) === false) {
            $this->db->prepared_query("
                SELECT
                    uls.Uploaded AS BytesUploaded,
                    uls.Downloaded AS BytesDownloaded,
                    coalesce(ub.points, 0) as BonusPoints,
                    um.RequiredRatio
                FROM users_main um
                INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
                LEFT JOIN user_bonus AS ub ON (ub.user_id = um.ID)
                WHERE um.ID = ?
                ", $this->id
            );
            $stats = $this->db->next_record(MYSQLI_ASSOC);
            $stats['BytesUploaded'] = (int)$stats['BytesUploaded'];
            $stats['BytesDownloaded'] = (int)$stats['BytesDownloaded'];
            $stats['BonusPoints'] = (float)$stats['BonusPoints'];
            $stats['BonusPointsPerHour'] = (new \Gazelle\Bonus)->userHourlyRate($this->id);
            $stats['RequiredRatio'] = (float)$stats['RequiredRatio'];
            $this->cache->cache_value('user_stats_' . $this->id, $stats, 3600);
        }
        return $stats;
    }

    public function buffer() {
        $class = $this->primaryClass();
        $demotion = array_filter(self::demotionCriteria(), function ($v) use ($class) {
            return in_array($class, $v['From']);
        });
        $criteria = end($demotion);

        $stats = $this->activityStats();
        [$votes, $bounty] = $this->requestsVotes();
        $effectiveUpload = $stats['BytesUploaded'] + $bounty;
        if ($criteria) {
            $ratio = $criteria['Ratio'];
        } else {
            $ratio = $stats['RequiredRatio'];
        }

        return [$ratio, $ratio == 0 ? $effectiveUpload : $effectiveUpload / $ratio - $stats['BytesDownloaded']];
    }

    public function nextClass() {
        $criteria = self::promotionCriteria()[$this->info()['PermissionID']] ?? null;
        if (!$criteria) {
            return null;
        }

        $stats = $this->activityStats();
        [$votes, $bounty] = $this->requestsVotes();
        $progress = [
            'Class' => \Users::make_class_string($criteria['To']),
            'Requirements' => [
                'Upload' => [$stats['BytesUploaded'] + $bounty, $criteria['MinUpload'], 'bytes'],
                'Ratio' => [$stats['BytesDownloaded'] == 0 ? '∞'
                    : $stats['BytesUploaded'] / $stats['BytesDownloaded'], $criteria['MinRatio'], 'float'],
                'Time' => [
                    $this->joinDate(),
                    $criteria['Weeks'] * 7 * 24 * 60 * 60,
                    'time'
                ],
                'Torrents' => [$this->uploadCount(), $criteria['MinUploads'], 'int'],
            ]
        ];

        if (isset($criteria['Extra'])) {
            foreach ($criteria['Extra'] as $req => $info) {
                $query = str_replace('users_main.ID', '?', $info['Query']);
                $params = array_fill(0, substr_count($query, '?'), $this->id);
                $count = $this->db->scalar($query, ...$params);

                $progress['Requirements'][$req] = [$count, $info['Count'], $info['Type']];
            }
        }
        return $progress;
    }

    public function seedingSize(): int {
        return $this->getSingleValue('seeding_size', '
            SELECT coalesce(sum(t.Size), 0)
            FROM
            (
                SELECT DISTINCT fid
                FROM xbt_files_users
                WHERE active = 1
                  AND remaining = 0
                  AND mtime > unix_timestamp(now() - INTERVAL 1 HOUR)
                  AND uid = ?
            ) AS xfu
            INNER JOIN torrents AS t ON (t.ID = xfu.fid)
        ');
    }

    public static function demotionCriteria() {
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

    public static function promotionCriteria() {
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
                            SELECT count(ID)
                            FROM torrents
                            WHERE Format = 'FLAC'
                                AND (
                                    (Media = 'CD' AND LogScore = 100)
                                    OR Media IN ('Vinyl', 'WEB', 'DVD', 'Soundboard', 'Cassette', 'SACD', 'Blu-ray', 'DAT')
                                )
                                AND UserID = users_main.ID",
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
                'MinUploads' => 2000,
                'Weeks' => 12,
                'Extra' => [
                    '"Perfecter" FLACs' => [
                        'Query' => "
                            SELECT count(DISTINCT t.GroupID, t.RemasterYear, t.RemasterCatalogueNumber, t.RemasterRecordLabel, t.RemasterTitle, t.Media)
                            FROM torrents t
                            WHERE t.Format = 'FLAC'
                                AND (
                                    (t.LogScore = 100 AND t.Media = 'CD')
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

        if (defined('RECOVERY_DB') && !empty(RECOVERY_DB)) {
            $criteria[ELITE_TM]['Extra'][SITE_NAME . ' Upload'] = [
               'Query' => sprintf("
                            SELECT uls.Uploaded + coalesce(b.Bounty, 0) - coalesce(ubl.final, 0)
                            FROM users_leech_stats uls
                            LEFT JOIN
                            (
                                SELECT UserID, sum(Bounty) AS Bounty
                                FROM requests_votes
                                GROUP BY UserID
                            ) b ON (b.UserID = uls.UserID)
                            LEFT JOIN %s.users_buffer_log ubl ON (ubl.opsid = uls.UserID)
                            WHERE uls.UserID = users_main.ID", RECOVERY_DB),
               'Count' => 2 * 1024 * 1024 * 1024 * 1024,
               'Type' => 'bytes'
            ];
        }
        return $criteria;
    }

    public function createApiToken(string $name, string $key): string {
        $suffix = sprintf('%014d', $this->id);

        while (true) {
            // prevent collisions with an existing token name
            $token = Util\Text::base64UrlEncode(Util\Crypto::encrypt(random_bytes(32) . $suffix, $key));
            if (!$this->hasApiToken($token)) {
                break;
            }
        }

        $this->db->prepared_query("
            INSERT INTO api_tokens
                   (user_id, name, token)
            VALUES (?,       ?,    ?)
            ", $this->id, $name, $token
        );
        return $token;
    }

    public function apiTokenList(): array {
        $this->db->prepared_query("
            SELECT id, name, token, created
            FROM api_tokens
            WHERE user_id = ?
                AND revoked = 0
            ORDER BY created DESC
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function hasTokenByName(string $name) {
        return $this->db->scalar("
            SELECT 1
            FROM api_tokens
            WHERE revoked = 0
                AND user_id = ?
                AND name = ?
            ", $this->id, $name
        ) === 1;
    }

    public function hasApiToken(string $token): bool {
        return $this->db->scalar("
            SELECT 1
            FROM api_tokens
            WHERE revoked = 0
                AND user_id = ?
                AND token = ?
            ", $this->id, $token
        ) === 1;
    }

    public function revokeApiTokenById(int $tokenId): int {
        $this->db->prepared_query("
            UPDATE api_tokens SET
                revoked = 1
            WHERE user_id = ? AND id = ?
            ", $this->id, $tokenId
        );
        return $this->db->affected_rows();
    }

    public function revokeUpload(): int {
        $this->db->prepared_query("
            UPDATE users_info SET
                DisableUpload = '1'
            WHERE UserID = ?
            ", $this->id
        );
        $this->cache->delete_value("user_info_heavy_" . $this->id);
        return $this->db->affected_rows();
    }

    /**
     * Is a user allowed to download a torrent file?
     *
     * @return bool Yes they can
     */
    public function canLeech(): bool {
        return $this->info()['can_leech'];
    }

    /**
     * Checks whether a user is allowed to issue an invite.
     *  - invites not disabled
     *  - not on ratio watch
     *  - leeching privs not suspended
     *  - has at least one invite to spend
     *
     * @return boolean false if they have been naughty, otherwise true
     */
    public function canInvite(): bool {
        return !$this->info()['DisableInvites']
            && !$this->onRatioWatch()
            && $this->canLeech()
            && $this->info()['Invites'] > 0;
    }

    /**
     * Checks whether a user is allowed to purchase an invite. User classes up to Elite are capped,
     * users above this class will always return true.
     *
     * @param integer $minClass Minimum class level necessary to purchase invites
     * @return boolean false if insufficient funds, otherwise true
     */
    public function canPurchaseInvite(): bool {
        if ($this->info()['DisableInvites']) {
            return false;
        }
        return $this->info()['effective_class'] >= MIN_INVITE_CLASS;
    }

    /**
     * Remove an active invitation
     *
     * @param string invite key
     * @return bool success
     */
    public function removeInvite(string $key) {
        $this->db->begin_transaction();
        $this->db->prepared_query("
            DELETE FROM invites WHERE InviteKey = ?
            ", $key
        );
        if ($this->db->affected_rows() == 0) {
            $this->db->rollback();
            return false;
        }
        if (check_perms('site_send_unlimited_invites')) {
            $this->db->commit();
            return true;
        }

        $this->db->prepared_query("
            UPDATE users_main SET
                Invites = Invites + 1
            WHERE ID = ?
            ", $this->id
        );
        $this->db->commit();
        $this->cache->begin_transaction("user_info_heavy_{$this->id()}");
        $this->cache->update_row(false, ['Invites' => '+1']);
        $this->cache->commit_transaction(0);
        return true;
    }

    /**
     * Initiate a password reset
     *
     * @param int $UserID The user ID
     * @param string $Username The username
     * @param string $Email The email address
     */
    public function resetPassword() {
        $resetKey = randomString();
        $this->db->prepared_query("
            UPDATE users_info SET
                ResetExpires = now() + INTERVAL 1 HOUR,
                ResetKey = ?
            WHERE UserID = ?
            ", $resetKey, $this->id
        );
        (new Mail)->send($this->email(), 'Password reset information for ' . SITE_NAME,
            \G::$Twig->render('email/password_reset.twig', [
                'username'  => $this->username(),
                'reset_key' => $resetKey,
                'ipaddr'    => $_SERVER['REMOTE_ADDR'],
            ])
        );
    }
}
