<?php

namespace Gazelle;

use Gazelle\Util\Irc;
use Gazelle\Util\Mail;

class User extends BaseObject {

    const CACHE_KEY         = 'u_%d';
    const CACHE_SNATCH_TIME = 'users_snatched_%d_time';
    const CACHE_NOTIFY      = 'u_notify_%d';
    const SNATCHED_UPDATE_AFTERDL = 300; // How long after a torrent download we want to update a user's snatch lists

    const DISCOGS_API_URL = 'https://api.discogs.com/artists/%d';

    protected bool $forceCacheFlush = false;
    protected int $lastReadForum;

    protected array $lastRead;
    protected array $forumWarning = [];
    protected array $staffNote = [];
    protected array $info = [];

    protected bool $donorVisible;
    protected string $donorHeart;

    protected Stats\User $stats;
    protected Manager\Torrent $torMan;
    protected Manager\TorrentLabel $labelMan;

    public function tableName(): string {
        return 'users_main';
    }

    public function __construct(int $id) {
        parent::__construct($id);
    }

    /**
     * Delegate stats methods to the Stats\User class
     */
    public function stats(): \Gazelle\Stats\User {
        if (!isset($this->stats)) {
            $this->stats = new Stats\User($this->id);
        }
        return $this->stats;
    }

    /**
     * Log out the current session
     */
    function logout($sessionId = false) {
        setcookie('session', '', [
            'expires'  => time() - 60 * 60 * 24 * 90,
            'path'     => '/',
            'secure'   => !DEBUG_MODE,
            'httponly' => DEBUG_MODE,
            'samesite' => 'Lax',
        ]);
        if ($sessionId) {
            (new Session($this->id))->drop($sessionId);
        }
        $this->flush();
    }

    /**
     * Logout all sessions
     */
    function logoutEverywhere() {
        $session = new Session($this->id);
        $session->dropAll();
        $this->logout();
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
        if (!empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = $this->cache->get_value($key);
        if ($info !== false) {
            return $this->info = $info;
        }
        $qid = $this->db->get_query_id();
        $this->db->prepared_query("
            SELECT um.Username,
                um.can_leech,
                um.CustomPermissions,
                um.IP,
                um.Email,
                um.Enabled,
                um.Invites,
                um.IRCKey,
                um.Paranoia,
                um.PassHash,
                um.PermissionID,
                um.RequiredRatio,
                um.Title,
                um.torrent_pass,
                um.Visible,
                um.2FA_Key,
                ui.AdminComment,
                ui.AuthKey,
                ui.Avatar,
                ui.collages,
                ui.DisableAvatar,
                ui.DisableForums,
                ui.DisableIRC,
                ui.DisableInvites,
                ui.DisablePM,
                ui.DisablePoints,
                ui.DisablePosting,
                ui.DisableRequests,
                ui.DisableTagging,
                ui.DisableUpload,
                ui.DisableWiki,
                ui.Info,
                ui.InfoTitle,
                ui.Inviter,
                ui.JoinDate,
                ui.NotifyOnQuote,
                ui.PermittedForums,
                ui.RatioWatchEnds,
                ui.RestrictedForums,
                ui.SiteOptions,
                ui.StyleID,
                ui.StyleURL,
                ui.SupportFor,
                ui.Warned,
                uls.Uploaded,
                uls.Downloaded,
                lower(replace(s.Name, ' ', '_')) AS styleName,
                p.Level AS Class,
                p.Name  AS className,
                p.Values AS primaryPermissions,
                if(p.Level >= (SELECT Level FROM permissions WHERE ID = ?), 1, 0) as isStaff,
                uf.tokens AS FLTokens,
                coalesce(ub.points, 0) AS BonusPoints,
                la.Type as locked_account
            FROM users_main              AS um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            INNER JOIN users_info        AS ui ON (ui.UserID = um.ID)
            INNER JOIN user_flt          AS uf ON (uf.user_id = um.ID)
            INNER JOIN stylesheets       AS s ON (s.ID = ui.StyleID)
            LEFT JOIN permissions        AS p ON (p.ID = um.PermissionID)
            LEFT JOIN user_bonus         AS ub ON (ub.user_id = um.ID)
            LEFT JOIN locked_accounts    AS la ON (la.UserID = um.ID)
            WHERE um.ID = ?
            ", FORUM_MOD, $this->id
        );
        $this->info = $this->db->next_record(MYSQLI_ASSOC, false) ?? [];
        $this->db->set_query_id($qid);
        if (empty($this->info)) {
            return $this->info;
        }
        $this->info['DisableAvatar']   = ($this->info['DisableAvatar'] == '1');
        $this->info['DisableForums']   = ($this->info['DisableForums'] == '1');
        $this->info['DisableInvites']  = ($this->info['DisableInvites'] == '1');
        $this->info['DisableIrc']      = ($this->info['DisableIRC'] == '1');
        $this->info['DisablePM']       = ($this->info['DisablePM'] == '1');
        $this->info['DisablePoints']   = ($this->info['DisablePoints'] == '1');
        $this->info['DisablePosting']  = ($this->info['DisablePosting'] == '1');
        $this->info['DisableRequests'] = ($this->info['DisableRequests'] == '1');
        $this->info['DisableTagging']  = ($this->info['DisableTagging'] == '1');
        $this->info['DisableUpload']   = ($this->info['DisableUpload'] == '1');
        $this->info['DisableWiki']     = ($this->info['DisableWiki'] == '1');
        $this->info['NotifyOnQuote']   = ($this->info['NotifyOnQuote'] == '1');

        $this->info['CommentHash'] = sha1($this->info['AdminComment']);
        $this->info['ParanoiaRaw'] = $this->info['Paranoia'];
        $this->info['Paranoia']    = unserialize($this->info['Paranoia']) ?: [];
        $this->info['SiteOptions'] = unserialize($this->info['SiteOptions']) ?: [];
        if (!isset($this->info['SiteOptions']['HttpsTracker'])) {
            $this->info['SiteOptions']['HttpsTracker'] = true;
        }
        $this->info['RatioWatchEndsEpoch'] = strtotime($this->info['RatioWatchEnds']);

        // load their permissions
        $this->db->prepared_query("
            SELECT p.ID,
                p.Level,
                p.Name,
                p.PermittedForums,
                p.Secondary,
                p.Values,
                if(p.badge = '', NULL, p.badge) as badge
            FROM permissions p
            INNER JOIN users_levels ul ON (ul.PermissionID = p.ID)
            WHERE ul.UserID = ?
            ORDER BY p.Secondary, p.Level DESC
            ", $this->id
        );
        $permissions = $this->db->to_array('ID', MYSQLI_ASSOC, false);
        $this->info['secondary_badge'] = [];
        $this->info['secondary_class'] = [];
        $forumAccess = [];
        $secondaryClassLevel = [];
        $secondaryClassPerms = [];
        foreach ($permissions as $p) {
            if ($p['Secondary']) {
                $this->info['secondary_badge'][$p['badge']] = $p['Name'];
                $this->info['secondary_class'][$p['ID']] = $p['Name'];
                $secondaryClassPerms = array_merge($secondaryClassPerms, unserialize($p['Values']));
                $secondaryClassLevel[$p['ID']] = $p['Level'];
                $allowed = array_map('intval', explode(',', $p['PermittedForums']) ?: []);
                foreach ($allowed as $forumId) {
                    if ($forumId) {
                        $forumAccess[$forumId] = true;
                    }
                }
            }
        }
        $this->info['effective_class'] = count($secondaryClassLevel)
            ? max($this->info['Class'], ...array_values($secondaryClassLevel))
            : $this->info['Class'];

        $this->info['Permission'] = [];
        $primary = unserialize($this->info['primaryPermissions']) ?: [];
        foreach ($primary as $name => $value) {
            $this->info['Permission'][$name] = (bool)$value;
        }
        foreach ($secondaryClassPerms as $name => $value) {
            $this->info['Permission'][$name] = (bool)$value;
        }
        $this->info['defaultPermission'] = $this->info['Permission'];

        // a custom permission may revoke a primary or secondary grant
        $custom = unserialize($this->info['CustomPermissions']) ?: [];
        foreach ($custom as $name => $value) {
            $this->info['Permission'][$name] = (bool)$value;
        }

        $allowed = array_map('intval', explode(',', $this->info['PermittedForums']) ?: []);
        foreach ($allowed as $forumId) {
            if ($forumId) {
                $forumAccess[$forumId] = true;
            }
        }
        $forbidden = array_map('intval', explode(',', $this->info['RestrictedForums'])) ?: [];
        foreach ($forbidden as $forumId) {
            // forbidden may override permitted
            if ($forumId) {
                $forumAccess[$forumId] = false;
            }
        }
        $this->info['forum_access'] = $forumAccess;

        $this->db->prepared_query("
            SELECT ua.Name, ua.ID
            FROM user_attr ua
            INNER JOIN user_has_attr uha ON (uha.UserAttrID = ua.ID)
            WHERE uha.UserID = ?
            ", $this->id
        );
        $this->info['attr'] = $this->db->to_pair('Name', 'ID', false);
        $this->cache->cache_value($key, $this->info, 86400);
        return $this->info;
    }

    /**
     * Get the permissions (granted or revoked) for this user
     *
     * @return array permission list
     */
    public function permissionList(): array {
        return $this->info()['Permission'];
    }

    /**
     * Get the default permissions of this user
     * (before any userlevel grants or revocations are considered).
     *
     * @return array permission list
     */
    public function defaultPermissionList(): array {
        return $this->info()['defaultPermission'] ?? [];
    }

    /**
     * Set the custom permissions for this user
     *
     * @param array a list of "perm_<permission_name>" custom permissions
     * @return bool was there a change?
     */
    public function modifyPermissionList(array $current): bool {
        $permissionList = array_keys(\Permissions::list());
        $default = $this->defaultPermissionList();
        $delta = [];
        foreach ($permissionList as $p) {
            $new = isset($current["perm_$p"]) ? 1 : 0;
            $old = isset($default[$p]) ? 1 : 0;
            if ($new != $old) {
                $delta[$p] = $new;
            }
        }
        $this->db->prepared_query("
            UPDATE users_main SET
                CustomPermissions = ?
            WHERE ID = ?
            ", count($delta) ? serialize($delta) : null, $this->id
        );
        $this->cache->delete_value("u_" . $this->id);
        $this->cache->delete_value("user_info_heavy_" . $this->id);
        $this->info = false;
        return $this->db->affected_rows() === 1;
    }

    /**
     * Does the user have a specific permission?
     *
     * @param string permission name
     * @return bool permission granted
     */
    public function permitted(string $permission): bool {
        return $this->info()['Permission'][$permission] ?? false;
    }

    /**
     * Get the secondary classes of the user (enabled or not)
     *
     * @return array secondary classes list
     */
    public function secondaryClassesList(): array {
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

    public function secondaryClasses(): array {
        return $this->info()['secondary_class'];
    }

    public function secondaryBadges(): array {
        return $this->info()['secondary_badge'];
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
        if ($toggled) {
            $this->flush();
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
        return !is_null($this->hasAttr('unlimited-download'));
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

    public function postsPerPage(): int {
        return $this->info()['SiteOptions']['PostsPerPage'] ?? POSTS_PER_PAGE;
    }

    public function username(): string {
        return $this->info()['Username'];
    }

    public function label(): string {
        return $this->id . " (" . $this->info()['Username'] . ")";
    }

    public function announceKey(): string {
        return $this->info()['torrent_pass'];
    }

    public function announceUrl(): string {
        return ($this->info()['SiteOptions']['HttpsTracker'] ? ANNOUNCE_HTTPS_URL : ANNOUNCE_HTTP_URL)
            . '/' . $this->announceKey() . '/announce';
    }

    public function disableBonusPoints(): bool {
        return $this->info()['DisablePoints'];
    }

    public function disableForums(): bool {
        return $this->info()['DisableForums'];
    }

    public function disableInvites(): bool {
        return $this->info()['DisableInvites'];
    }

    public function disableIrc(): bool {
        return $this->info()['DisableIrc'];
    }

    public function disablePm(): bool {
        return $this->info()['DisablePM'];
    }

    public function disablePosting(): bool {
        return $this->info()['DisablePosting'];
    }

    public function disableRequests(): bool {
        return $this->info()['DisableRequests'];
    }

    public function disableTagging(): bool {
        return $this->info()['DisableTagging'];
    }

    public function disableUpload(): bool {
        return $this->info()['DisableUpload'];
    }

    public function disableWiki(): bool {
        return $this->info()['DisableWiki'];
    }

    public function auth(): string {
        return $this->info()['AuthKey'];
    }

    public function rssAuth(): string {
        return md5($this->id . RSS_HASH . $this->announceKey());
    }

    public function avatar(): ?string {
        return $this->info()['Avatar'];
    }

    public function avatarMode(): int {
        return $this->option('DisableAvatars') ?? 0;
    }

    public function showAvatars(): bool {
        return $this->avatarMode() != 1;
    }

    public function donorAvatar(): array {
        $enabled = $this->enabledDonorRewards();
        $rewards = $this->donorRewards();
        if (!$enabled['HasAvatarMouseOverText']) {
            $mouseOver = null;
        } else {
            $text = $rewards['AvatarMouseOverText'];
            $mouseOver = empty($text) ? null : "title=\"$text\" alt=\"$text\"";
        }
        return [$mouseOver, $enabled['HasSecondAvatar'] ? ($rewards['SecondAvatar'] ?: null) : null];
    }

    public function donorVisible(): bool {
        if (!isset($this->donorVisible)) {
            $this->donorVisible = (bool)$this->db->scalar("
                SELECT 1 FROM users_donor_ranks WHERE Hidden = '0' AND UserID = ?
                ", $this->id
            );
        }
        return $this->donorVisible;
    }

    public function donorHeart(): string {
        if (!isset($this->donorHeart)) {
            if (!$this->isDonor()) {
                $this->donorHeart = '';
            } else {
                $enabled = $this->enabledDonorRewards();
                $reward = $this->donorRewards();
                if ($enabled['HasCustomDonorIcon'] && $reward['CustomIcon']) {
                    $iconImage = \ImageTools::process($reward['CustomIcon'], false, 'donoricon', $this->id);
                } else {
                    $rank = $this->donorRank();
                    if ($rank == 0) {
                        $rank = 1;
                    }
                    if ($this->specialDonorRank() === MAX_SPECIAL_RANK) {
                        $donorHeart = 6;
                    } elseif ($rank === 5) {
                        $donorHeart = 4; // Two points between rank 4 and 5
                    } elseif ($rank >= MAX_RANK) {
                        $donorHeart = 5;
                    } else {
                        $donorHeart = $rank;
                    }
                    $iconImage = STATIC_SERVER . '/common/symbols/'
                        . ($donorHeart === 1 ? 'donor.png' : "donor_{$donorHeart}.png");
                }
                $iconText = $enabled['HasDonorIconMouseOverText'] ? ($reward['IconMouseOverText'] ?? 'Donor') : 'Donor';
                $this->donorHeart = '<a target="_blank" href="'
                    . ($enabled['HasDonorIconLink'] ? ($reward['CustomIconLink'] ?? 'donate.php') : 'donate.php')
                    . '"><img class="donor_icon tooltip" src="' . $iconImage
                    . '" alt="' . $iconText . '" title="' . $iconText
                    . '" /></a>';
            }
        }
        return $this->donorHeart;
    }

    public function email(): string {
        return $this->info()['Email'];
    }

    public function infoProfile() {
        return $this->info()['Info'];
    }

    public function infoTitle(): string {
        return $this->info()['InfoTitle'] ?? 'Profile';
    }

    public function ipaddr(): string {
        return $this->info()['IP'];
    }

    public function IRCKey() {
        return $this->info()['IRCKey'];
    }

    public function TFAKey() {
        return $this->info()['2FA_Key'];
    }

    /**
     * Create the recovery keys for the user
     *
     * @param string 2FA seed (to validate challenges)
     * @return int 1 if update succeeded
     */
    public function create2FA($key): int {
        $recovery = [];
        for ($i = 0; $i < 10; $i++) {
            $recovery[] = randomString(20);
        }
        $this->db->prepared_query("
            UPDATE users_main SET
                2FA_Key = ?,
                Recovery = ?
            WHERE ID = ?
            ", $key, serialize($recovery), $this->id
        );
        $this->flush();
        return $this->db->affected_rows();
    }

    public function list2FA(): array {
        return unserialize($this->db->scalar("
            SELECT Recovery FROM users_main WHERE ID = ?
            ", $this->id
        )) ?: [];
    }

    /**
     * A user is attempting to login with 2FA via a recovery key
     * If we have the key on record, burn it and let them in.
     *
     * @param string Recovery key from user
     * @return bool Valid key, they may log in.
     */
    public function burn2FARecovery(string $key): bool {
        $list = $this->list2FA();
        $index = array_search($key, $list);
        if ($index === false) {
            return false;
        }
        unset($list[$index]);
        $this->db->prepared_query('
            UPDATE users_main SET
                Recovery = ?
            WHERE ID = ?
            ', count($list) === 0 ? null : serialize($list), $this->id
        );
        return $this->db->affected_rows() === 1;
    }

    public function remove2FA() {
        return $this->setUpdate('2FA_Key', null)
            ->setUpdate('Recovery', null);
    }

    public function joinDate() {
        return $this->info()['JoinDate'];
    }

    public function paranoia(): array {
        return $this->info()['Paranoia'];
    }

    public function paranoiaLevel(): int {
        $paranoia = $this->paranoia();
        $level = count($paranoia);
        foreach ($paranoia as $p) {
            if (strpos($p, '+') !== false) {
                $level++;
            }
        }
        return $level;
    }

    public function paranoiaLabel(): string {
        $level = $this->paranoiaLevel();
        if ($level > 20) {
            return 'Very high';
        } elseif ($level > 5) {
            return 'High';
        } elseif ($level > 1) {
            return 'Low';
        } elseif ($level == 1) {
            return 'Very Low';
        }
        return 'Off';
    }

    // The following are used throughout the site:
    // uploaded, ratio, downloaded: stats
    // lastseen: approximate time the user last used the site
    // uploads: the full list of the user's uploads
    // uploads+: just how many torrents the user has uploaded
    // snatched, seeding, leeching: the list of the user's snatched torrents, seeding torrents, and leeching torrents respectively
    // snatched+, seeding+, leeching+: the length of those lists respectively
    // uniquegroups, perfectflacs: the list of the user's uploads satisfying a particular criterion
    // uniquegroups+, perfectflacs+: the length of those lists
    // If "uploads+" is disallowed, so is "uploads". So if "uploads" is in the array, the user is a little paranoid, "uploads+", very paranoid.

    // The following are almost only used in /sections/user/user.php:
    // requiredratio
    // requestsfilled_count: the number of requests the user has filled
    //   requestsfilled_bounty: the bounty thus earned
    //   requestsfilled_list: the actual list of requests the user has filled
    // requestsvoted_...: similar
    // artistsadded: the number of artists the user has added
    // torrentcomments: the list of comments the user has added to torrents
    //   +
    // collages: the list of collages the user has created
    //   +
    // collagecontribs: the list of collages the user has contributed to
    //   +
    // invitedcount: the number of users this user has directly invited

    /**
     * Return whether currently logged in user can see $Property on a user with $Paranoia, $UserClass and (optionally) $UserID
     * If $Property is an array of properties, returns whether currently logged in user can see *all* $Property ...
     *
     * @param $Property The property to check, or an array of properties.
     * @param $Paranoia The paranoia level to check against.
     * @param $UserClass The user class to check against (Staff can see through paranoia of lower classed staff)
     * @param $UserID Optional. The user ID of the person being viewed
     * @return mixed   1 representing the user has normal access
                       2 representing that the paranoia was overridden,
                       false representing access denied.
     */

    /**
     * What right does the viewer have to see a list of properties of this user?
     *
     * @param Gazelle\User viewer Who is looking?
     * @param array Property What properties are they looking for?
     * @return int PARANOIA_HIDE, PARANOIA_OVERRIDDEN, PARANOIA_ALLOWED
     */
    function propertyVisibleMulti(User $viewer, array $property): int {
        $final = false;
        foreach ($property as $p) {
            $result = $this->propertyVisible($viewer, $p);
            if ($result === PARANOIA_HIDE) {
                return PARANOIA_HIDE;
            }
            if ($final === PARANOIA_OVERRIDDEN && $result = PARANOIA_ALLOWED) {
                continue;
            }
            $final = $result;
        }
        return $final;
    }

    /**
     * What right does the viewer have to see a property of this user?
     *
     * @param Gazelle\User viewer Who is looking?
     * @param array Property What property are they looking for?
     * @return int PARANOIA_HIDE, PARANOIA_OVERRIDDEN, PARANOIA_ALLOWED
     */
    function propertyVisible(User $viewer, string $property): int {
        if ($this->id() === $viewer->id()) {
            return PARANOIA_ALLOWED;
        }

        $paranoia = $this->paranoia();
        if (!in_array($property, $paranoia) && !in_array("$property+", $paranoia)) {
            return PARANOIA_ALLOWED;
        }
        if ($viewer->permitted('users_override_paranoia') || $viewer->permitted(PARANOIA_OVERRIDE[$property] ?? '')) {
            return PARANOIA_OVERRIDDEN;
        }
        return PARANOIA_HIDE;
    }

    public function ratioWatchExpiry(): ?string {
        return $this->info()['RatioWatchEnds'];
    }

    public function requiredRatio(): float {
        return $this->info()['RequiredRatio'];
    }

    public function staffNotes() {
        return $this->info()['AdminComment'];
    }

    public function stylesheetId(): int {
        return $this->info()['StyleID'];
    }

    public function stylesheetName(): string {
        return $this->info()['styleName'];
    }

    public function stylesheetUrl(): ?string {
        $styleUrl = $this->info()['StyleURL'];
        if (is_null($styleUrl)) {
            return null;
        }
        $info = parse_url($styleUrl);
        if (substr($info['path'], -4) === '.css'
                && $info['query'] . $info['fragment'] === ''
                && $info['host'] === SITE_HOST
                && file_exists(SERVER_ROOT . $info['path'])) {
            return $styleUrl . '?v=' . filemtime(SERVER_ROOT . "/public/{$info['path']}");
        }
        return $styleUrl;
    }

    public function supportFor() {
        return $this->info()['SupportFor'];
    }

    public function title() {
        return $this->info()['Title'];
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

    public function userclassName(): string {
        return $this->info()['className'];
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

    /**
     * Return the list for forum IDs to which the user has been banned.
     * (Note that banning takes precedence of permitting).
     *
     * @return array of forum ids
     */
    public function forbiddenForums(): array {
        return array_keys(array_filter($this->info()['forum_access'], function ($v) {return $v === false;}));
    }

    /**
     * Return the list for forum IDs to which the user has been granted special access.
     *
     * @return array of forum ids
     */
    public function permittedForums(): array {
        return array_keys(array_filter($this->info()['forum_access'], function ($v) {return $v === true;}));
    }

    public function forbiddenForumsList(): string {
        return $this->info()['RestrictedForums'];
    }

    public function permittedForumsList(): string {
        return $this->info()['PermittedForums'];
    }

    /**
     * Checks whether user has any overrides to a forum
     *
     * @param int forum id
     * @return bool has access
     */
    public function forumAccess(int $forumId, int $forumMinClassLevel): bool {
        return ($this->classLevel() >= $forumMinClassLevel || in_array($forumId, $this->permittedForums()))
            && !in_array($forumId, $this->forbiddenForums());
    }

    /**
     * Checks whether user has the permission to create a forum.
     *
     * @param \Gazelle\Forum the forum
     * @return boolean true if user has permission
     */
    public function createAccess(Forum $forum): bool {
        return $this->forumAccess($forum->id(), $forum->minClassCreate());
    }

    /**
     * Checks whether user has the permission to read a forum.
     *
     * @param \Gazelle\Forum the forum
     * @return boolean true if user has permission
     */
    public function readAccess(Forum $forum): bool {
        return $this->forumAccess($forum->id(), $forum->minClassRead());
    }

    /**
     * Checks whether user has the permission to write to a forum.
     *
     * @param \Gazelle\Forum the forum
     * @return boolean true if user has permission
     */
    public function writeAccess(Forum $forum): bool {
        return $this->forumAccess($forum->id(), $forum->minClassWrite());
    }

    /**
     * Checks whether the user is up to date on the forum
     *
     * @param \Gazelle\Forum
     * @return bool the user is up to date
     */
    public function hasReadLastPost(Forum $forum): bool {
        return $forum->isLocked()
            || $this->lastReadInThread($forum->lastThreadId()) >= $forum->lastPostId()
            || $this->forumCatchupEpoch() >= $forum->lastPostTime();
    }

    /**
     * What is the last post this user has read in a thread?
     *
     * @param int thread id
     * @param int post id (or 0 if they have not read it).
     */
    public function lastReadInThread(int $threadId): int {
        if (!isset($this->lastRead)) {
            $this->db->prepared_query("
                SELECT TopicID, PostID FROM forums_last_read_topics WHERE UserID = ?
                ", $this->id
            );
            $this->lastRead = $this->db->to_pair('TopicID', 'PostID', false);
        }
        return $this->lastRead[$threadId] ?? 0;
    }

    /**
     * When did the user last perform a global catchup on the forums?
     *
     * @return int epoch of catchup
     */
    public function forumCatchupEpoch() {
        if (!isset($this->lastReadForum)) {
            $this->lastReadForum = (int)$this->db->scalar("
                SELECT unix_timestamp(last_read) FROM user_read_forum WHERE user_id = ?
                ", $this->id
            );
        }
        return $this->lastReadForum;
    }

    public function forceCacheFlush($flush = true) {
        return $this->forceCacheFlush = $flush;
    }

    public function flush() {
        $this->info = [];
        $this->cache->deleteMulti([
            "u_" . $this->id,
            "user_info_" . $this->id,
            "user_info_heavy_" . $this->id,
            "user_stats_" . $this->id,
        ]);
    }

    public function recordEmailChange(string $newEmail, string $ipaddr): int {
        $this->db->prepared_query("
            INSERT INTO users_history_emails
                   (UserID, Email, IP, useragent)
            VALUES (?,      ?,     ?,  ?)
            ", $this->id, $newEmail, $ipaddr, $_SERVER['HTTP_USER_AGENT']
        );
        Irc::sendRaw("PRIVMSG " . $this->username()
            . " :Security alert: Your email address was changed via $ipaddr with {$_SERVER['HTTP_USER_AGENT']}. Not you? Contact staff ASAP.");
        (new Mail)->send($this->email(), 'Email address changed information for ' . SITE_NAME,
            $this->twig->render('email/email-address-change.twig', [
                'ipaddr'     => $ipaddr,
                'new_email'  => $newEmail,
                'now'        => Date('Y-m-d H:i:s'),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'username'   => $this->username(),
            ])
        );
        $this->cache->delete_value('user_email_count_' . $this->id);
        return $this->db->affected_rows();
    }

    public function recordPasswordChange(string $ipaddr): int {
        $this->db->prepared_query("
            INSERT INTO users_history_passwords
                   (UserID, ChangerIP, useragent)
            VALUES (?,      ?,         ?)
            ", $this->id, $ipaddr, $_SERVER['HTTP_USER_AGENT']
        );
        Irc::sendRaw("PRIVMSG " . $this->username()
            . " :Security alert: Your password was changed via $ipaddr with {$_SERVER['HTTP_USER_AGENT']}. Not you? Contact staff ASAP.");
        (new Mail)->send($this->email(), 'Password changed information for ' . SITE_NAME,
            $this->twig->render('email/password-change.twig', [
                'ipaddr'     => $ipaddr,
                'now'        => Date('Y-m-d H:i:s'),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'username'   => $this->username(),
            ])
        );
        $this->cache->delete_value('user_pw_count_' . $this->id);
        return $this->db->affected_rows();
    }

    public function remove() {
        $this->db->prepared_query("
            DELETE FROM users_main WHERE ID = ?
            ", $this->id
        );
        $this->flush();
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
        if (parent::modify() || $changed) {
            $this->flush(); // parent::modify() may have done a flush() but it's too much code to optimize this second call away
            return true;
        }
        return false;
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
        $this->flush();
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
        $this->flush();
        return $this->db->affected_rows() === 1;
    }

    public function unlock(): bool {
        $this->db->prepared_query("
            DELETE FROM locked_accounts WHERE UserID = ?
            ", $this->id
        );
        $this->flush();
        return $this->db->affected_rows() === 1;
    }

    public function updateTokens(int $n): bool {
        $this->db->prepared_query('
            UPDATE user_flt SET
                tokens = ?
            WHERE user_id = ?
            ', $n, $this->id
        );
        $this->flush();
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
        $this->flush();
    }

    public function registerDownload(int $torrentId): int {
        $this->db->prepared_query("
            INSERT INTO users_downloads
                   (UserID, TorrentID)
            VALUES (?,      ?)
            ", $this->id, $torrentId
        );
        $affected = $this->db->affected_rows();
        if (!$affected) {
            return 0;
        }
        $this->cache->delete_value('user_rlim_' . $this->id);
        $key = sprintf(self::CACHE_SNATCH_TIME, $this->id);
        $nextUpdate = $this->cache->get_value($key);
        if ($nextUpdate !== false) {
            $soon = time() + self::SNATCHED_UPDATE_AFTERDL;
            if ($soon < $nextUpdate['next']) { // only if the change is closer than the next update
                $this->cache->cache_value($key, ['next' => $soon], 0);
            }
        }
        return $affected;
    }

    /**
     * Validate a user password
     *
     * @param string password
     * @return bool  true on correct password
     */
    public function validatePassword(string $plaintext): bool {
        $hash = $this->info()['PassHash'];
        $success = password_verify(hash('sha256', $plaintext), $hash);
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->db->prepared_query("
                UPDATE users_main SET
                    PassHash = ?
                WHERE ID = ?
                ", UserCreator::hashPassword($plaintext), $this->id
            );
        }
        return $success;
    }

    public function updatePassword(string $pw, string $ipaddr): bool {
        $this->db->begin_transaction();
        $this->db->prepared_query('
            UPDATE users_main SET
                PassHash = ?
            WHERE ID = ?
            ', UserCreator::hashPassword($pw), $this->id
        );
        if ($this->db->affected_rows() !== 1) {
            $this->db->rollback();
            return false;
        }
        $this->db->prepared_query('
            INSERT INTO users_history_passwords
                   (UserID, ChangerIP, useragent)
            VALUES (?,      ?,         ?)
            ', $this->id, $ipaddr, $_SERVER['HTTP_USER_AGENT']
        );
        if ($this->db->affected_rows() !== 1) {
            $this->db->rollback();
            return false;
        }
        $this->db->commit();
        $this->flush();
        return true;
    }

    public function passwordHistory(): array {
        $this->db->prepared_query("
            SELECT ChangeTime AS date,
                ChangerIP     AS ipaddr,
                useragent
            FROM users_history_passwords
            WHERE UserID = ?
            ORDER BY ChangeTime DESC
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
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
        $this->flush();
        return $this->db->affected_rows() === 1;
    }

    public function unreadTorrentNotifications(): int {
        if (($new = $this->cache->get_value('user_notify_upload_' . $this->id)) === false) {
            $new = $this->db->scalar("
                SELECT count(*)
                FROM users_notify_torrents
                WHERE UnRead = 1
                    AND UserID = ?
                ", $this->id
            );
            $this->cache->cache_value('user_notify_upload_' . $this->id, $new, 0);
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
        $this->cache->delete_value('user_notify_upload_' . $this->id);
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
            UPDATE users_history_passwords SET ChangerIP = '', useragent = 'reset-ip-history' WHERE UserID = ?
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
        $this->flush();
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
                   (UserID, Email, IP, useragent)
            VALUES (?,      ?,     ?, 'email-reset')
            ", $this->id, $email, $ipaddr
        );
        $this->db->prepared_query("
            UPDATE users_main
            SET Email = ?
            WHERE ID = ?
            ", $email, $this->id
        );
        $this->flush();
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

    public function modifyAnnounceKeyHistory(string $oldPasskey, string $newPasskey, string $ipaddr): bool {
        $this->db->prepared_query("
            INSERT INTO users_history_passkeys
                   (UserID, OldPassKey, NewPassKey, ChangerIP)
            VALUES (?,      ?,          ?,          ?)
            ", $this->id, $oldPasskey, $newPasskey, $ipaddr
        );
        return $this->db->affected_rows() === 1;
    }

    public function announceKeyHistory(): array {
        $this->db->prepared_query("
            SELECT OldPassKey AS old,
                NewPassKey    AS new,
                ChangeTime    AS date,
                ChangerIP     AS ipaddr
            FROM users_history_passkeys
            WHERE UserID = ?
            ORDER BY ChangeTime DESC
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
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

    public function addClasses(array $classes): int {
        $this->db->prepared_query("
            INSERT IGNORE INTO users_levels (UserID, PermissionID)
            VALUES " . implode(', ', array_fill(0, count($classes), '(' . $this->id . ', ?)')),
            ...$classes
        );
        $this->flush();
        return $this->db->affected_rows();
    }

    public function removeClasses(array $classes): int {
        $this->db->prepared_query("
            DELETE FROM users_levels
            WHERE UserID = ?
                AND PermissionID IN (" . placeholders($classes) . ")",
            $this->id, ...$classes
        );
        $this->flush();
        return $this->db->affected_rows();
    }

    public function notifyFilters(): array {
        $key = sprintf(self::CACHE_NOTIFY, $this->id);
        if ($this->forceCacheFlush || ($filters = $this->cache->get_value($key)) === false) {
            $this->db->prepared_query('
                SELECT ID, Label
                FROM users_notify_filters
                WHERE UserID = ?
                ', $this->id
            );
            $filters = $this->db->to_pair('ID', 'Label', false);
            $this->cache->cache_value($key, $filters, 2592000);
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
            $this->cache->deleteMulti(['u_notify_' . $this->id, 'notify_artists_' . $this->id]);
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
            $this->cache->deleteMulti(['u_notify_' . $this->id, 'notify_artists_' . $this->id]);
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
            $this->cache->deleteMulti(['u_notify_' . $this->id, 'notify_artists_' . $this->id]);
        }
        return $change;
    }

    public function isUnconfirmed(): bool { return $this->info()['Enabled'] == '0'; }
    public function isEnabled(): bool     { return $this->info()['Enabled'] == '1'; }
    public function isDisabled(): bool    { return $this->info()['Enabled'] == '2'; }
    public function isLocked(): bool      { return !is_null($this->info()['locked_account']); }
    public function isVisible(): bool     { return $this->info()['Visible'] == '1'; }
    public function isWarned(): bool      { return !is_null($this->warningExpiry()); }

    public function isStaff(): bool         { return $this->info()['isStaff']; }
    public function isDonor(): bool         { return isset($this->info()['secondary_class'][DONOR]) || $this->isStaff(); }
    public function isFLS(): bool           { return isset($this->info()['secondary_class'][FLS_TEAM]); }
    public function isInterviewer(): bool   { return isset($this->info()['secondary_class'][INTERVIEWER]); }
    public function isRecruiter(): bool     { return isset($this->info()['secondary_class'][RECRUITER]); }
    public function isStaffPMReader(): bool { return $this->isFLS() || $this->isStaff(); }

    public function warningExpiry(): ?string {
        return $this->info()['Warned'];
    }

    public function endWarningDate(int $weeks) {
        return $this->db->scalar("
            SELECT coalesce(Warned, now()) + INTERVAL ? WEEK
            FROM users_info
            WHERE UserID = ?
            ", $weeks, $this->id
        );
    }

    /**
     * How many personal collages is this user allowed to create?
     *
     * @return int number of collages (including collages granted from donations)
     */
    public function allowedPersonalCollages(): int {
        return $this->paidPersonalCollages() + $this->personalDonorCollages();
    }

    /**
     * How many collages has this user bought?
     *
     * @return int number of collages
     */
    public function paidPersonalCollages(): int {
        return $this->info()['collages'];
    }

    /**
     * How many personal collages has this user created?
     *
     * @return int number of active collages
     */
    public function activePersonalCollages(): int {
        return $this->db->scalar("
            SELECT count(*)
            FROM collages
            WHERE CategoryID = 0
                AND Deleted = '0'
                AND UserID = ?
            ", $this->id
        );
    }

    /**
     * Is this user allowed to create a new personal collage?
     *
     * @return bool Yes we can
     */
    public function canCreatePersonalCollage(): bool {
        return $this->allowedPersonalCollages() > $this->activePersonalCollages();
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
     * @return array [email address, ip, date, useragent]
     */
    public function emailHistory(): array {
        $this->db->prepared_query("
            SELECT h.Email,
                h.Time,
                h.IP,
                h.useragent
            FROM users_history_emails AS h
            WHERE h.UserID = ?
            ORDER BY h.Time DESC
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_NUM, false);
    }

    /**
     * Email duplicates
     *
     * @return array of array of [id, email, user_id, created, ipv4, \User user]
     */
    public function emailDuplicateHistory(): array {
        // Get history of matches
        $this->db->prepared_query("
            SELECT users_history_emails_id AS id,
                Email  AS email,
                UserID AS user_id,
                Time   AS created,
                IP     AS ipv4,
                useragent
            FROM users_history_emails AS uhe
            WHERE uhe.UserID != ?
                AND uhe.Email in (SELECT DISTINCT Email FROM users_history_emails WHERE UserID = ?)
            ORDER BY uhe.Email, uhe.Time DESC
            ", $this->id, $this->id
        );
        $dupe = $this->db->to_array('id', MYSQLI_ASSOC, false);
        foreach ($dupe as &$d) {
            $d['user'] = new User($d['user_id']);
        }
        unset($d);
        return $dupe;
    }

    public function clients(): array {
        $this->db->prepared_query('
            SELECT DISTINCT useragent FROM xbt_files_users WHERE uid = ?
            ', $this->id
        );
        return $this->db->collect(0) ?: ['None'];
    }

    protected function getSingleValue($cacheKey, $query) {
        $cacheKey .= '_' . $this->id;
        if ($this->forceCacheFlush || ($value = $this->cache->get_value($cacheKey)) === false) {
            $value = $this->db->scalar($query, $this->id);
            $this->cache->cache_value($cacheKey, $value, 3600);
        }
        return $value;
    }

    public function duplicateIPv4Count(): int {
        $cacheKey = "ipv4_dup_" . str_replace('-', '_', $this->info()['IP']);
        if (($value = $this->cache->get_value($cacheKey)) === false) {
            $value = $this->db->scalar("
                SELECT count(*) FROM users_history_ips WHERE IP = ?
                ", $this->info()['IP']
            );
            $this->cache->cache_value($cacheKey, $value, 3600);
        }
        return max(0, $value - 1);
    }

    public function lastAccess() {
        return $this->getSingleValue('user_last_access', '
            SELECT ula.last_access FROM user_last_access ula WHERE user_id = ?
        ');
    }

    public function uploadCount(): int {
        return $this->getSingleValue('user_upload_count', '
            SELECT count(*) FROM torrents WHERE UserID = ?
        ');
    }

    public function leechingCounts(): int {
        return $this->getSingleValue('user_leeching_count', '
            SELECT count(*)
            FROM xbt_files_users AS x
            INNER JOIN torrents AS t ON (t.ID = x.fid)
            WHERE x.remaining > 0
                AND x.uid = ?
        ');
    }

    public function seedingCounts(): int {
        return $this->getSingleValue('user_seeding_count', '
            SELECT count(*)
            FROM xbt_files_users AS x
            INNER JOIN torrents AS t ON (t.ID = x.fid)
            WHERE x.remaining = 0
                AND x.uid = ?
        ');
    }

    public function artistsAdded(): int {
        return $this->getSingleValue('user_artists_count', '
            SELECT count(*) FROM torrents_artists WHERE UserID = ?
        ');
    }

    public function passwordCount(): int {
        return $this->getSingleValue('user_pw_count', '
            SELECT count(*) FROM users_history_passwords WHERE UserID = ?
        ');
    }

    public function announceKeyCount(): int {
        return $this->getSingleValue('user_passkey_count', '
            SELECT count(*) FROM users_history_passkeys WHERE UserID = ?
        ');
    }

    public function siteIPCount(): int {
        return $this->getSingleValue('user_siteip_count', '
            SELECT count(DISTINCT IP) FROM users_history_ips WHERE UserID = ?
        ');
    }

    public function trackerIPCount(): int {
        return $this->getSingleValue('user_trackip_count', "
            SELECT count(DISTINCT IP) FROM xbt_snatched WHERE uid = ? AND IP != ''
        ");
    }

    public function emailCount(): int {
        return $this->getSingleValue('user_email_count', '
            SELECT count(*) FROM users_history_emails WHERE UserID = ?
        ');
    }

    public function inviter(): ?User {
        return $this->info()['Inviter'] ? new User($this->info()['Inviter']) : null;
    }

    public function inviteCount(): int {
        return $this->info()['DisableInvites'] ? 0 : $this->info()['Invites'];
    }

    public function invitedCount(): int {
        return $this->getSingleValue('user_invites', '
            SELECT count(*)
            FROM users_info
            WHERE Inviter = ?
        ');
    }

    public function pendingInviteCount(): int {
        return $this->getSingleValue('user_inv_pending', '
            SELECT count(*)
            FROM invites
            WHERE InviterID = ?
        ');
    }

    public function pendingInviteList(): array {
        $this->db->prepared_query("
            SELECT InviteKey AS invite_key,
                Email        AS email,
                Expires      AS expires
            FROM invites
            WHERE InviterID = ?
            ORDER BY Expires
            ", $this->id
        );
        return $this->db->to_array('invite_key', MYSQLI_ASSOC, false);
    }

    public function inviteList(string $orderBy, string $direction): array {
        $this->db->prepared_query("
            SELECT
                um.ID          AS user_id,
                um.Email       AS email,
                uls.Uploaded   AS uploaded,
                uls.Downloaded AS downloaded,
                ui.JoinDate    AS join_date,
                ula.last_access
            FROM users_main AS um
            LEFT  JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            INNER JOIN users_info AS ui ON (ui.UserID = um.ID)
            WHERE ui.Inviter = ?
            ORDER BY $orderBy $direction
            ", $this->id
        );
        return $this->db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function passwordAge() {
        $age = time_diff(
            $this->getSingleValue('user_pw_age', '
                SELECT coalesce(max(uhp.ChangeTime), ui.JoinDate)
                FROM users_info ui
                LEFT JOIN users_history_passwords uhp USING (UserID)
                WHERE ui.UserID = ?
            ')
        );
        return substr($age, 0, strpos($age, " ago"));
    }

    public function artistCommentCount(): int {
        return $this->getSingleValue('user_comment_artist', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'artists' AND AuthorID = ?
        ");
    }

    public function collageCommentCount(): int {
        return $this->getSingleValue('user_comment_collage', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'collages' AND AuthorID = ?
        ");
    }

    public function requestCommentCount(): int {
        return $this->getSingleValue('user_comment_request', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'requests' AND AuthorID = ?
        ");
    }

    public function torrentCommentCount(): int {
        return $this->getSingleValue('user_comment_torrent', "
            SELECT count(*)
            FROM comments
            WHERE Page = 'torrents' AND AuthorID = ?
        ");
    }

    public function forumWarning() {
        return $this->getSingleValue('user_forum_warn', '
            SELECT Comment
            FROM users_warnings_forums
            WHERE UserID = ?
        ');
    }

    public function releaseVotes(): int {
        return $this->getSingleValue('user_release_votes', '
            SELECT count(*)
            FROM users_votes
            WHERE UserID = ?
        ');
    }

    public function bonusPointsSpent(): int {
        return (int)$this->getSingleValue('user_bp_spent', '
            SELECT sum(Price)
            FROM bonus_history
            WHERE UserID = ?
        ');
    }

    public function collagesCreated(): int {
        return $this->getSingleValue('user_collage_create', "
            SELECT count(*)
            FROM collages
            WHERE Deleted = '0' AND UserID = ?
        ");
    }

    public function artistCollageContributed(): int {
        return $this->getSingleValue('user_collage_a_contrib', "
            SELECT count(DISTINCT ct.CollageID)
            FROM collages_artists AS ct
            INNER JOIN collages AS c ON (ct.CollageID = c.ID)
            WHERE c.Deleted = '0' AND ct.UserID = ?
        ");
    }

    public function torrentCollageContributed(): int {
        return $this->getSingleValue('user_collage_t_contrib', "
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
        return $this->getSingleValue('user_collage_a_add', "
            SELECT count(*)
            FROM collages_artists AS ct
            INNER JOIN collages AS c ON (ct.CollageID = c.ID)
            WHERE c.Deleted = '0' AND ct.UserID = ?
        ");
    }

    public function torrentCollageAdditions(): int {
        return $this->getSingleValue('user_collage_t_add', "
            SELECT count(*)
            FROM collages_torrents AS ct
            INNER JOIN collages AS c ON (ct.CollageID = c.ID)
            WHERE c.Deleted = '0' AND ct.UserID = ?
        ");
    }

    public function collageAdditions(): int {
        return $this->artistCollageAdditions() + $this->torrentCollageAdditions();
    }

    public function forumPosts(): int {
        return $this->getSingleValue('user_forum_posts', "
            SELECT count(*) FROM forums_posts WHERE AuthorID = ?
        ");
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
            while ([$key, $count] = $this->db->next_record(MYSQLI_ASSOC, false)) {
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
            $defaults = !empty($options['HideTypes']);
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
                $checked = $defaults && isset($options['HideTypes'][$key]);
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

    public function tokenCount(): int {
        return $this->info()['FLTokens'];
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
                $torrent = $this->torMan->findById($t['torrent_id']);
                $artist = $torrent->group()->artistHtml();
                if ($artist) {
                    $name = "$artist - $name";
                }
                $this->labelMan->load($torrent->info());
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
        if (is_null($this->avatar())) {
            $factor *= 0.75;
        }
        if (!strlen($this->info()['Info'])) {
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
            $stats = $this->db->next_record(MYSQLI_ASSOC, false);
            $stats['BytesUploaded'] = (int)$stats['BytesUploaded'];
            $stats['BytesDownloaded'] = (int)$stats['BytesDownloaded'];
            $stats['BonusPoints'] = (float)$stats['BonusPoints'];
            $stats['BonusPointsPerHour'] = (new \Gazelle\Bonus)->userHourlyRate($this->id);
            $stats['RequiredRatio'] = (float)$stats['RequiredRatio'];
            $this->cache->cache_value('user_stats_' . $this->id, $stats, 3600);
        }
        return $stats;
    }

    public function buffer(): array {
        $class = $this->primaryClass();
        $demotion = array_filter((new Manager\User)->demotionCriteria(), function ($v) use ($class) {
            return in_array($class, $v['From']);
        });
        $criteria = end($demotion);

        $stats = $this->activityStats();
        $ustats = new Stats\User($this->id);
        $effectiveUpload = $stats['BytesUploaded'] + $ustats->requestBountyTotal();
        if ($criteria) {
            $ratio = $criteria['Ratio'];
        } else {
            $ratio = $stats['RequiredRatio'];
        }

        return [$ratio, $ratio == 0 ? $effectiveUpload : $effectiveUpload / $ratio - $stats['BytesDownloaded']];
    }

    public function nextClass() {
        $criteria = (new Manager\User)->promotionCriteria()[$this->info()['PermissionID']] ?? null;
        if (!$criteria) {
            return null;
        }

        $stats = $this->activityStats();
        $ustats = new Stats\User($this->id);
        $progress = [
            'Class' => (new Manager\User)->userclassName($criteria['To']),
            'Requirements' => [
                'Upload' => [$stats['BytesUploaded'] + $ustats->requestBountySize(), $criteria['MinUpload'], 'bytes'],
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
        $this->flush();
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
            && ($this->info()['Invites'] > 0 || $this->permitted('site_send_unlimited_invites'));
    }

    /**
     * Checks whether a user is allowed to purchase an invite. Lower classes are capped,
     * users above this class will always return true.
     *
     * @param integer $minClass Minimum class level necessary to purchase invites
     * @return boolean false if insufficient funds, otherwise true
     */
    public function canPurchaseInvite(): bool {
        return !$this->disableInvites() && $this->effectiveClass() >= MIN_INVITE_CLASS;
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
        $this->flush();
        return true;
    }

    /**
     * Initiate a password reset
     *
     * @param int $UserID The user ID
     * @param string $Username The username
     * @param string $Email The email address
     */
    public function resetPassword($twig) {
        $resetKey = randomString();
        $this->db->prepared_query("
            UPDATE users_info SET
                ResetExpires = now() + INTERVAL 1 HOUR,
                ResetKey = ?
            WHERE UserID = ?
            ", $resetKey, $this->id
        );
        $this->flush();
        (new Mail)->send($this->email(), 'Password reset information for ' . SITE_NAME,
            $twig->render('email/password_reset.twig', [
                'username'  => $this->username(),
                'reset_key' => $resetKey,
                'ipaddr'    => $_SERVER['REMOTE_ADDR'],
            ])
        );
    }

    /*
     * Has a password reset expired?
     *
     * @return true if it has expired (or none exists)
     */
    public function resetPasswordExpired(): bool {
        return (bool)$this->db->scalar("
            SELECT 1
            FROM users_info
            WHERE coalesce(ResetExpires, now()) <= now()
                AND UserID = ?
            ", $this->id
        );
    }

    /**
     * Forcibly clear a password reset
     *
     * @return 1 if the user was cleared
     */
    public function clearPasswordReset(): int {
        $this->db->prepared_query("
            UPDATE users_info SET
                ResetKey = '',
                ResetExpires = NULL
            WHERE UserID = ?
            ", $this->id
        );
        return $this->db->affected_rows();
    }

    /**
     * Returns an array with User Bookmark data: group IDs, collage data, torrent data
     * @param string|int $UserID
     * @return array Group IDs, Bookmark Data, Torrent List
     */
    public function bookmarkList(): array {
        $key = "bookmarks_group_ids_" . $this->id;
        if (($info = $this->cache->get_value($key)) !== false) {
            [$groupIds, $bookmarks] = $info;
        } else {
            $qid = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT GroupID,
                    Sort,
                    `Time`
                FROM bookmarks_torrents
                WHERE UserID = ?
                ORDER BY Sort, `Time`
                ", $this->id
            );
            $groupIds = $this->db->collect('GroupID');
            $bookmarks = $this->db->to_array('GroupID', MYSQLI_ASSOC, false);
            $this->db->set_query_id($qid);
            $this->cache->cache_value($key, [$groupIds, $bookmarks], 3600);
        }
        return [$groupIds, $bookmarks, \Torrents::get_groups($groupIds)];
    }

    public function isFriend(int $userId): bool {
        return (bool)$this->db->scalar("
            SELECT 1
            FROM friends
            WHERE UserID = ?
                AND FriendID = ?
            ", $this->id, $userId
        );
    }

    public function addFriend(int $userId): bool {
        if (!$this->db->scalar("SELECT 1 FROM users_main WHERE ID = ?", $userId)) {
            return false;
        }
        $this->db->prepared_query("
            INSERT IGNORE INTO friends
                   (UserID, FriendID)
            VALUES (?,      ?)
            ", $this->id, $userId
        );
        return $this->db->affected_rows() === 1;
    }

    public function addFriendComment(int $userId, string $comment): bool {
        $this->db->prepared_query("
            UPDATE friends SET
                Comment = ?
            WHERE UserID = ?
                AND FriendID = ?
            ", $comment, $this->id, $userId
        );
        return $this->db->affected_rows() === 1;
    }

    public function removeFriend(int $userId): bool {
        $this->db->prepared_query("
            DELETE FROM friends
            WHERE UserID = ?
                AND FriendID = ?
            ", $this->id, $userId
        );
        return $this->db->affected_rows() === 1;
    }

    public function totalFriends(): int {
        return $this->db->scalar("
            SELECT count(*) FROM friends WHERE UserID = ?
            ", $this->id
        );
    }

    public function friendList(int $limit, int $offset): array {
        $this->db->prepared_query("
            SELECT f.FriendID AS id,
                f.Comment as comment
            FROM friends AS f
            INNER JOIN users_main AS um ON (um.ID = f.FriendID)
            WHERE f.UserID = ?
            ORDER BY um.Username
            LIMIT ? OFFSET ?
            ", $this->id, $limit, $offset
        );
        $list = $this->db->to_array('id', MYSQLI_ASSOC, false);
        $userMan = new Manager\User;
        foreach (array_keys($list) as $id) {
            $list[$id]['user'] = new User($id);
            $list[$id]['avatar'] = $userMan->avatarMarkup($this, $list[$id]['user']);
        }
        return $list;
    }

    /**
     * Get the donation history of the user
     *
     * @return array of array of [amount, date, currency, reason, source, addedby, rank, totalrank]
     */
    public function donorHistory(): array {
        $QueryID = $this->db->get_query_id();
        $this->db->prepared_query("
            SELECT Amount, Time, Currency, Reason, Source, AddedBy, Rank, TotalRank
            FROM donations
            WHERE UserID = ?
            ORDER BY Time DESC
            ", $this->id
        );
        $list = $this->db->to_array(false, MYSQLI_ASSOC, false);
        $this->db->set_query_id($QueryID);
        return $list;
    }

    /**
     * Put all the common donor info in the same cache key to save some cache calls
     */
    protected function donorInfo() {
        // Our cache class should prevent identical memcached requests
        $UserID = $this->id;
        $DonorInfo = $this->cache->get_value("donor_info_$UserID");
        if ($DonorInfo === false) {
            $QueryID = $this->db->get_query_id();
            $this->db->prepared_query('
                SELECT Rank,
                    SpecialRank,
                    TotalRank,
                    DonationTime,
                    RankExpirationTime + INTERVAL 766 HOUR
                FROM users_donor_ranks
                WHERE UserID = ?
                ', $UserID
            );
            // 2 hours less than 32 days to account for schedule run times
            if ($this->db->has_results()) {
                [$Rank, $SpecialRank, $TotalRank, $DonationTime, $ExpireTime]
                    = $this->db->next_record(MYSQLI_NUM, false);
                if ($DonationTime === null) {
                    $DonationTime = 0;
                }
                if ($ExpireTime === null) {
                    $ExpireTime = 0;
                }
            } else {
                $Rank = $SpecialRank = $TotalRank = $DonationTime = $ExpireTime = 0;
            }
            if ($this->isStaff()) {
                $Rank = MAX_EXTRA_RANK;
                $SpecialRank = MAX_SPECIAL_RANK;
            }
            $this->db->prepared_query('
                SELECT IconMouseOverText,
                    AvatarMouseOverText,
                    CustomIcon,
                    CustomIconLink,
                    SecondAvatar
                FROM donor_rewards
                WHERE UserID = ?
                ', $UserID
            );
            if ($this->db->has_results()) {
                $Rewards = $this->db->next_record(MYSQLI_ASSOC, false);
            } else {
                $Rewards = [
                    'IconMouseOverText'   => null,
                    'AvatarMouseOverText' => null,
                    'CustomIcon'          => null,
                    'CustomIconLink'      => null,
                    'SecondAvatar'        => null,
                ];
            }
            $this->db->set_query_id($QueryID);

            $DonorInfo = [
                'Rank'       => (int)$Rank,
                'SRank'      => (int)$SpecialRank,
                'TotRank'    => (int)$TotalRank,
                'Time'       => $DonationTime,
                'ExpireTime' => $ExpireTime,
                'Rewards'    => $Rewards
            ];
            $this->cache->cache_value("donor_info_$UserID", $DonorInfo, 86400);
        }
        return $DonorInfo;
    }

    /**
     * Donor honorifics for the donor forum
     *
     * @return array [prefix, suffix, usecomma]
     */
    public function donorTitles() {
        $key = "donor_title_" . $this->id;
        if (($Results = $this->cache->get_value($key)) === false) {
            $Results = $this->db->row("
                SELECT Prefix, Suffix, UseComma
                FROM donor_forum_usernames
                WHERE UserID = ?
                ", $this->id
            );
            $this->cache->cache_value($key, $Results, 0);
        }
        return $Results;
    }

    /**
     * Current Donor rank (points)
     *
     * @return int points
     */
    public function donorRank() {
        return $this->donorInfo()['Rank'];
    }

    /**
     * Special Donor rank of user
     *
     * @return int special rank
     */
    public function specialDonorRank() {
        return $this->donorInfo()['SRank'];
    }

    /**
     * Total Donor points (to help calculate special rank)
     *
     * @return int total points
     */
    public function totalDonorRank() {
        return $this->donorInfo()['TotRank'];
    }

    /**
     * How many collages does the user have thanks to their donations?
     *
     * @return int number of collages
     */
    public function personalDonorCollages() {
        $info = $this->donorInfo();
        if ($info['SRank'] == MAX_SPECIAL_RANK) {
            return 5;
        }
        return min($info['Rank'], 5); // One extra collage per donor rank up to 5
    }

    /**
     * When did the user last donate?
     *
     * @return date of last donation
     */
    public function lastDonation() {
        return $this->donorInfo()['Time'];
    }

    /**
     * Get the donation rewards (profiles, avatars etc)
     *
     * @return array rewards
     */
    public function donorRewards() {
        return $this->donorInfo()['Rewards'];
    }

    /**
     * Get the rewards to which the user is entitled
     *
     * @return array enabled rewards
     */
    public function enabledDonorRewards() {
        $Rewards = [];
        $Rank = $this->donorRank();
        $SpecialRank = $this->specialDonorRank();
        $HasAll = $SpecialRank == 3;

        $Rewards = [
            'HasAvatarMouseOverText' => false,
            'HasCustomDonorIcon' => false,
            'HasDonorForum' => false,
            'HasDonorIconLink' => false,
            'HasDonorIconMouseOverText' => false,
            'HasProfileInfo1' => false,
            'HasProfileInfo2' => false,
            'HasProfileInfo3' => false,
            'HasProfileInfo4' => false,
            'HasSecondAvatar' => false
        ];

        if ($Rank >= 2 || $HasAll) {
            $Rewards["HasDonorIconMouseOverText"] = true;
            $Rewards["HasProfileInfo1"] = true;
        }
        if ($Rank >= 3 || $HasAll) {
            $Rewards["HasAvatarMouseOverText"] = true;
            $Rewards["HasProfileInfo2"] = true;
        }
        if ($Rank >= 4 || $HasAll) {
            $Rewards["HasDonorIconLink"] = true;
            $Rewards["HasProfileInfo3"] = true;
        }
        if ($Rank >= MAX_RANK || $HasAll) {
            $Rewards["HasCustomDonorIcon"] = true;
            $Rewards["HasDonorForum"] = true;
            $Rewards["HasProfileInfo4"] = true;
        }
        if ($SpecialRank >= 2) {
            $Rewards["HasSecondAvatar"] = true;
        }
        return $Rewards;
    }

    /**
     * Get the donation rewards that are used on the user profile page
     *
     * @return array rewards
     */
    public function profileDonorRewards() {
        $key = "donor_profile_rewards_" . $this->id;
        $Results = $this->cache->get_value($key);
        if ($Results === false) {
            $QueryID = $this->db->get_query_id();
            $this->db->prepared_query('
                SELECT ProfileInfo1,
                    ProfileInfo2,
                    ProfileInfo3,
                    ProfileInfo4,
                    ProfileInfoTitle1,
                    ProfileInfoTitle2,
                    ProfileInfoTitle3,
                    ProfileInfoTitle4
                FROM donor_rewards
                WHERE UserID = ?
                ', $this->id
            );
            if ($this->db->has_results()) {
                $Results = $this->db->next_record(MYSQLI_ASSOC, false);
            } else {
                $Results = [
                    'ProfileInfo1' => null,
                    'ProfileInfo2' => null,
                    'ProfileInfo3' => null,
                    'ProfileInfo4' => null,
                    'ProfileInfoTitle1' => null,
                    'ProfileInfoTitle2' => null,
                    'ProfileInfoTitle3' => null,
                    'ProfileInfoTitle4' => null,
                ];
            }
            $this->db->set_query_id($QueryID);
            $this->cache->cache_value($key, $Results, 0);
        }
        return $Results;
    }

    /**
     * Get the donation label
     *
     * @return string donation label
     */
    public function donorRankLabel(bool $showOverflow = false): string {
        if ($this->specialDonorRank() == 3) {
            return '&infin; [Diamond]';
        }
        $rank = $this->donorRank();
        $label = $rank >= MAX_RANK ? MAX_RANK : $rank;
        $overflow = $rank - $label;
        if ($label == 5 || $label == 6) {
            $label--;
        }
        if ($showOverflow && $overflow) {
            $label .= " (+$overflow)";
        }
        if ($rank >= 6) {
            $label .= ' [Gold]';
        } elseif ($rank >= 4) {
            $label .= ' [Silver]';
        } elseif ($rank >= 3) {
            $label .= ' [Bronze]';
        } elseif ($rank >= 2) {
            $label .= ' [Copper]';
        } elseif ($rank >= 1) {
            $label .= ' [Red]';
        }
        return $label;
    }

    /**
     * When does the current donation level expire?
     *
     * @return string expiry label
     */
    public function donorRankExpiry(): string {
        $info = $this->donorInfo();
        if (in_array($info['SRank'], [1, MAX_SPECIAL_RANK])) {
            return 'Never';
        }
        if (!$info['ExpireTime']) {
            return '';
        }
        $expiry = strtotime($info['ExpireTime']);
        return ($expiry - time() < 60) ? 'Soon' : ('in ' . time_diff($expiry));
    }

    /**
     * Update donor rewards
     *
     * @param array rewards
     */
    public function updateReward(array $field) {
        $Rank = $this->donorRank();
        $SpecialRank = $this->specialDonorRank();
        $HasAll = ($SpecialRank === 3);
        $insert = [];
        $args = [];

        $QueryID = $this->db->get_query_id();
        $UserID = $this->id();

        if ($Rank >= 2 || $HasAll) {
            if (isset($field['donor_icon_mouse_over_text'])) {
                $insert[] = "IconMouseOverText";
                $args[] = $field['donor_icon_mouse_over_text'];
            }
        }
        if ($Rank >= 3 || $HasAll) {
            if (isset($field['avatar_mouse_over_text'])) {
                $insert[] = "AvatarMouseOverText";
                $args[] = $field['avatar_mouse_over_text'];
            }
        }
        if ($Rank >= 4 || $HasAll) {
            if (isset($field['donor_icon_link'])) {
                $value = $field['donor_icon_link'];
                if ($value === '' || preg_match(URL_REGEXP, $value)) {
                    $insert[] = "CustomIconLink";
                    $args[] = $value;
                }
            }
        }
        if ($Rank >= MAX_RANK || $HasAll) {
            if (isset($field['donor_icon_custom_url'])) {
                $value = $field['donor_icon_custom_url'];
                if ($value === '' || preg_match(IMAGE_REGEXP, $value)) {
                    $insert[] = "CustomIcon";
                    $args[] = $value;
                }
            }
            $comma = empty($field['donor_title_comma']) ? 0 : 1;
            $this->db->prepared_query('
                INSERT INTO donor_forum_usernames
                       (UserID, Prefix, Suffix, UseComma)
                VALUES (?,      ?,      ?,      ?)
                ON DUPLICATE KEY UPDATE
                    Prefix = ?, Suffix = ?, UseComma = ?
                ', $UserID, $field['donor_title_prefix'], $field['donor_title_suffix'], $comma,
                    $field['donor_title_prefix'], $field['donor_title_suffix'], $comma,
            );
            $this->cache->delete_value("donor_title_$UserID");
        }

        for ($i = 1; $i < min(MAX_RANK, $Rank); $i++) {
            if (isset($field["profile_title_" . $i]) && isset($field["profile_info_" . $i])) {
                $insert[] = "ProfileInfoTitle" . $i;
                $insert[] = "ProfileInfo" . $i;
                $args[] = $field["profile_title_" . $i];
                $args[] = $field["profile_info_" . $i];
            }
        }
        if ($SpecialRank >= 2) {
            if (isset($field['second_avatar'])) {
                $value = $field['second_avatar'];
                if ($value === '' || preg_match(IMAGE_REGEXP, $value)) {
                    $insert[] = "SecondAvatar";
                    $args[] = $value;
                }
            }
        }
        if (count($insert) > 0) {
            $this->db->prepared_query("
                INSERT INTO donor_rewards
                       (UserID, " . implode(', ', $insert) . ")
                VALUES (?, " . placeholders($insert) . ")
                ON DUPLICATE KEY UPDATE
                " . implode(', ', array_map(fn($c) => "$c = ?", $insert)),
                $UserID, ...array_merge($args, $args)
            );
        }
        $this->db->set_query_id($QueryID);
        $this->cache->deleteMulti(["donor_profile_rewards_$UserID", "donor_info_$UserID"]);
    }

    public function modifyCollectorDefaults(array $collector): int {
        if (serialize($this->option('Collector')) != serialize($collector)) {
            $this->info['SiteOptions']['Collector'] = $collector;
            $this->db->prepared_query("
                UPDATE users_info SET
                    SiteOptions = ?
                WHERE UserID = ?
                ", serialize($this->info['SiteOptions']), $this->id
            );
            $success = $this->db->affected_rows() == 1;
            if ($success) {
                $this->flush();
            }
            return $success;
        }
        return false;
    }
}
