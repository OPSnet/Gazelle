<?php

namespace Gazelle;

use \Gazelle\Enum\AvatarDisplay;
use \Gazelle\Enum\AvatarSynthetic;
use \Gazelle\Util\Irc;
use \Gazelle\Util\Mail;

class User extends BaseObject {
    final const CACHE_KEY          = 'u_%d';
    final const CACHE_SNATCH_TIME  = 'users_snatched_%d_time';
    final const CACHE_NOTIFY       = 'u_notify_%d';
    final const USER_RECENT_SNATCH = 'u_recent_snatch_%d';
    final const USER_RECENT_UPLOAD = 'u_recent_up_%d';

    final const SNATCHED_UPDATE_AFTERDL = 300; // How long after a torrent download we want to update a user's snatch lists

    final const DISCOGS_API_URL = 'https://api.discogs.com/artists/%d';

    protected bool $forceCacheFlush = false;
    protected int $lastReadForum;
    protected array $voteSummary;

    protected array $avatarCache;
    protected array $lastRead;
    protected array $forumWarning = [];
    protected array $staffNote = [];

    protected Stats\User|null $stats;

    public function flush(): User {
        self::$cache->delete_multi([
            sprintf(self::CACHE_KEY, $this->id),
            sprintf(User\Privilege::CACHE_KEY, $this->id),
            sprintf('user_inv_pending_%d', $this->id),
            sprintf('user_invited_%d', $this->id),
            sprintf('user_stat_%d', $this->id),
        ]);
        $this->stats()->flush();
        $this->stats = null;
        $this->info = [];
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), $this->username()); }
    public function location(): string { return 'user.php?id=' . $this->id; }
    public function tableName(): string { return 'users_main'; }

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
    public function logout($sessionId = false): void {
        setcookie('session', '', [
            'expires'  => time() - 60 * 60 * 24 * 90,
            'path'     => '/',
            'secure'   => !DEBUG_MODE,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if ($sessionId) {
            (new User\Session($this))->drop($sessionId);
        }
        $this->flush();
    }

    /**
     * Logout all sessions
     */
    public function logoutEverywhere(): void {
        $session = new User\Session($this);
        $session->dropAll();
        $this->logout();
    }

    public function info(): array {
        if (isset($this->info) && !empty($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info !== false) {
            return $this->info = $info;
        }
        $qid = self::$db->get_query_id();
        self::$db->prepared_query("
            SELECT um.Username,
                um.can_leech,
                um.created,
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
                ui.Info,
                ui.InfoTitle,
                ui.Inviter,
                ui.NavItems,
                ui.PermittedForums,
                ui.RatioWatchEnds,
                ui.RatioWatchDownload,
                ui.RestrictedForums,
                ui.SiteOptions,
                ui.SupportFor,
                ui.Warned,
                uls.Uploaded,
                uls.Downloaded,
                p.Level AS Class,
                p.Name  AS className,
                p.Values AS primaryPermissions,
                p.PermittedForums AS primaryForum,
                if(p.Level >= (SELECT Level FROM permissions WHERE ID = ?), 1, 0) as isStaff,
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
            ", FORUM_MOD, $this->id
        );
        $this->info = self::$db->next_record(MYSQLI_ASSOC, false) ?? [];
        self::$db->set_query_id($qid);
        if (empty($this->info)) {
            return $this->info;
        }

        $this->info['CommentHash'] = sha1($this->info['AdminComment']);
        $this->info['NavItems']    = array_map('trim', explode(',', $this->info['NavItems'] ?? ''));
        $this->info['ParanoiaRaw'] = $this->info['Paranoia'];
        $this->info['Paranoia']    = $this->info['Paranoia'] ? unserialize($this->info['Paranoia']) : [];
        $this->info['SiteOptions'] = $this->info['SiteOptions'] ? unserialize($this->info['SiteOptions']) : [];
        if (!isset($this->info['SiteOptions']['HttpsTracker'])) {
            $this->info['SiteOptions']['HttpsTracker'] = true;
        }
        $this->info['RatioWatchEndsEpoch'] = $this->info['RatioWatchEnds']
            ? strtotime($this->info['RatioWatchEnds']) : 0;

        $privilege = new User\Privilege($this);
        $this->info['effective_class'] = max($this->info['Class'], $privilege->maxSecondaryLevel());

        $this->info['Permission'] = [];
        $primary = unserialize($this->info['primaryPermissions']) ?: [];
        foreach ($primary as $name => $value) {
            $this->info['Permission'][$name] = (bool)$value;
        }
        foreach ($privilege->secondaryPrivilegeList() as $name => $value) {
            $this->info['Permission'][$name] = (bool)$value;
        }
        $this->info['defaultPermission'] = $this->info['Permission'];

        // a custom permission may revoke a primary or secondary grant
        $custom = $this->info['CustomPermissions'] ? unserialize($this->info['CustomPermissions']) : [];
        foreach ($custom as $name => $value) {
            $this->info['Permission'][$name] = (bool)$value;
        }

        $forumAccess = $privilege->allowedForumList(); // grants from secondary classes
        $allowed = array_map('intval', explode(',', $this->info['PermittedForums']));
        foreach ($allowed as $forumId) {
            if ($forumId) {
                $forumAccess[$forumId] = true;
            }
        }
        $allowed = array_map('intval', explode(',', $this->info['primaryForum']));
        foreach ($allowed as $forumId) {
            if ($forumId) {
                $forumAccess[$forumId] = true;
            }
        }
        $forbidden = array_map('intval', explode(',', $this->info['RestrictedForums']));
        foreach ($forbidden as $forumId) {
            // forbidden may override permitted
            if ($forumId) {
                $forumAccess[$forumId] = false;
            }
        }
        $this->info['forum_access'] = $forumAccess;

        self::$db->prepared_query("
            SELECT ua.Name, ua.ID
            FROM user_attr ua
            INNER JOIN user_has_attr uha ON (uha.UserAttrID = ua.ID)
            WHERE uha.UserID = ?
            ", $this->id
        );
        $this->info['attr'] = self::$db->to_pair('Name', 'ID', false);
        self::$cache->cache_value($key, $this->info, 3600);
        self::$db->set_query_id($qid);
        return $this->info;
    }

    /**
     * Get the custom forum navigation configuration.
     */
    public function forumNavList(): array {
        return $this->info()['NavItems'];
    }

    /**
     * Get the permissions (granted or revoked) for this user
     */
    public function permissionList(): array {
        return $this->info()['Permission'];
    }

    /**
     * Get the default permissions of this user
     * (before any userlevel grants or revocations are considered).
     */
    public function defaultPermissionList(): array {
        return $this->info()['defaultPermission'] ?? [];
    }

    public function addCustomPrivilege(string $name): bool {
        $custom = (string)self::$db->scalar("
            SELECT CustomPermissions FROM users_main WHERE ID = ?
            ", $this->id
        );
        $custom = empty($custom) ? [] : unserialize($custom);
        $custom[$name] = 1;
        return $this->setUpdate('CustomPermissions', serialize($custom))->modify();
    }

    /**
     * Set the custom permissions for this user
     * TODO: this is pretty messed up, make it nice (get rid if "perm_")
     *
     * @param array $current a list of "perm_<permission_name>" custom permissions
     * @return bool was there a change?
     */
    public function modifyPermissionList(array $current): bool {
        $permissionList = array_keys(\Gazelle\Manager\Privilege::privilegeList());
        $default = $this->defaultPermissionList();
        $delta = [];
        foreach ($permissionList as $p) {
            $new = isset($current["perm_$p"]) ? 1 : 0;
            $old = isset($default[$p]) ? 1 : 0;
            if ($new != $old) {
                $delta[$p] = $new;
            }
        }
        self::$db->prepared_query("
            UPDATE users_main SET
                CustomPermissions = ?
            WHERE ID = ?
            ", count($delta) ? serialize($delta) : null, $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected === 1;
    }

    /**
     * Does the user have a specific permission?
     */
    public function permitted(string $permission): bool {
        return $this->info()['Permission'][$permission] ?? false;
    }

    /**
     * Does the user have any of the specified permissions?
     *
     * @param string[] $permission names
     */
    public function permittedAny(...$permission): bool {
        foreach ($permission as $p) {
            if ($this->info()['Permission'][$p] ?? false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the secondary classes of the user (enabled or not)
     */
    public function secondaryClassesList(): array {
        self::$db->prepared_query('
            SELECT p.ID                AS permId,
                p.Name                 AS permName,
                (l.UserID IS NOT NULL) AS isSet
            FROM permissions AS p
            LEFT JOIN users_levels AS l ON (l.PermissionID = p.ID AND l.UserID = ?)
            WHERE p.Secondary = 1
            ORDER BY p.Name
            ', $this->id
        );
        return self::$db->to_array('permName', MYSQLI_ASSOC, false);
    }

    public function hasAttr(string $name): bool {
        return isset($this->info()['attr'][$name]);
    }

    public function toggleAttr(string $attr, bool $flag): bool {
        $hasAttr = $this->hasAttr($attr);
        $toggled = false;
        if (!$flag && $hasAttr) {
            self::$db->prepared_query("
                DELETE FROM user_has_attr
                WHERE UserID = ?
                    AND UserAttrID = (SELECT ID FROM user_attr WHERE Name = ?)
                ", $this->id, $attr
            );
            $toggled = self::$db->affected_rows() === 1;
        } elseif ($flag && !$hasAttr) {
            self::$db->prepared_query("
                INSERT INTO user_has_attr (UserID, UserAttrID)
                    SELECT ?, ID FROM user_attr WHERE Name = ?
                ", $this->id, $attr
            );
            $toggled = self::$db->affected_rows() === 1;
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

    public function announceKey(): string {
        return $this->info()['torrent_pass'];
    }

    public function announceUrl(): string {
        return ($this->info()['SiteOptions']['HttpsTracker'] ? ANNOUNCE_HTTPS_URL : ANNOUNCE_HTTP_URL)
            . '/' . $this->announceKey() . '/announce';
    }

    public function auth(): string {
        return $this->info()['AuthKey'];
    }

    public function avatar(): ?string {
        return $this->info()['Avatar'];
    }

    public function avatarMode(): AvatarDisplay {
        return match ((int)$this->option('DisableAvatars')) {
            AvatarDisplay::none->value              => AvatarDisplay::none,
            AvatarDisplay::fallbackSynthetic->value => AvatarDisplay::fallbackSynthetic,
            AvatarDisplay::forceSynthetic->value    => AvatarDisplay::forceSynthetic,
            default                                 => AvatarDisplay::show,
        };
    }

    /**
     * Assemble the pieces needed to display a user avatar
     *  - an avatar (or the site default, or an sythetic image based on the username)
     *  - optional hover text for donors
     *  - optional rollover avatar for donors
     * Twig will use these pieces to construct the markup for their avatar.
     */
    public function avatarComponentList(User $viewed): array {
        $viewedId = $viewed->id();
        if (!isset($this->avatarCache[$viewedId])) {
            $donor = new User\Donor($viewed);
            $this->avatarCache[$viewedId] = [
                'image' => match($this->avatarMode()) {
                    AvatarDisplay::show              => $viewed->avatar() ?: USER_DEFAULT_AVATAR,
                    AvatarDisplay::fallbackSynthetic => $viewed->avatar() ?: (new User\SyntheticAvatar($this))->avatar($viewed->username()),
                    AvatarDisplay::forceSynthetic    => (new User\SyntheticAvatar($this))->avatar($viewed->username()),
                    AvatarDisplay::none              => USER_DEFAULT_AVATAR, /** @phpstan-ignore-line */
                },
                'hover' => $donor->avatarHover(),
                'text'  => $donor->avatarHoverText(),
            ];
        }
        return $this->avatarCache[$viewedId];
    }

    public function bonusPointsTotal(): int {
        return (int)$this->info()['BonusPoints'];
    }

    public function classLevel(): int {
        return $this->info()['Class'];
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function disableAvatar(): bool {
        return $this->hasAttr('disable-avatar');
    }

    public function disableBonusPoints(): bool {
        return $this->hasAttr('disable-bonus-points');
    }

    public function disableForums(): bool {
        return $this->hasAttr('disable-forums');
    }

    public function disableInvites(): bool {
        return $this->hasAttr('disable-invites');
    }

    public function disableIRC(): bool {
        return $this->hasAttr('disable-irc');
    }

    public function disablePm(): bool {
        return $this->hasAttr('disable-pm');
    }

    public function disablePosting(): bool {
        return $this->hasAttr('disable-posting');
    }

    public function disableRequests(): bool {
        return $this->hasAttr('disable-requests');
    }

    public function disableTagging(): bool {
        return $this->hasAttr('disable-tagging');
    }

    public function disableUpload(): bool {
        return $this->hasAttr('disable-upload');
    }

    public function disableWiki(): bool {
        return $this->hasAttr('disable-wiki');
    }

    public function downloadAsText(): bool {
        return $this->hasAttr('download-as-text');
    }

    public function downloadedSize(): int {
        return $this->info()['Downloaded'];
    }

    public function downloadedOnRatioWatch(): int {
        return $this->info()['RatioWatchDownload'];
    }

    public function effectiveClass(): int {
        return $this->info()['effective_class'];
    }

    public function email(): string {
        return $this->info()['Email'];
    }

    public function ipaddr(): string {
        return $this->info()['IP'];
    }

    public function IRCKey(): ?string {
        return $this->info()['IRCKey'];
    }

    public function label(): string {
        return $this->id . " (" . $this->info()['Username'] . ")";
    }

    public function option(string $option): mixed {
        return $this->info()['SiteOptions'][$option] ?? null;
    }

    public function postsPerPage(): int {
        return $this->info()['SiteOptions']['PostsPerPage'] ?? POSTS_PER_PAGE;
    }

    public function profileInfo(): string {
        return $this->info()['Info'] ?? '';
    }

    public function profileTitle(): string {
        return $this->info()['InfoTitle'] ?? 'Profile';
    }

    public function requiredRatio(): float {
        return $this->info()['RequiredRatio'];
    }

    public function rssAuth(): string {
        return md5($this->id . RSS_HASH . $this->announceKey());
    }

    public function showAvatars(): bool {
        return $this->avatarMode() != AvatarDisplay::none;
    }

    public function staffNotes(): string {
        return $this->info()['AdminComment'];
    }

    public function supportFor(): string {
        return $this->info()['SupportFor'];
    }

    public function TFAKey(): ?string {
        return $this->info()['2FA_Key'];
    }

    public function title(): ?string {
        return $this->info()['Title'];
    }

    public function uploadedSize(): int {
        return $this->info()['Uploaded'];
    }

    public function userclassName(): string {
        return $this->info()['className'];
    }

    public function username(): string {
        return $this->info()['Username'];
    }

    /**
     * Create the recovery keys for the user
     *
     * @param string $key 2FA seed (to validate challenges)
     * @return int 1 if update succeeded
     */
    public function create2FA($key): int {
        $recovery = [];
        for ($i = 0; $i < 10; $i++) {
            $recovery[] = randomString(20);
        }
        self::$db->prepared_query("
            UPDATE users_main SET
                2FA_Key = ?,
                Recovery = ?
            WHERE ID = ?
            ", $key, serialize($recovery), $this->id
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function list2FA(): array {
        return unserialize((string)self::$db->scalar("
            SELECT Recovery FROM users_main WHERE ID = ?
            ", $this->id
        )) ?: [];
    }

    /**
     * A user is attempting to login with 2FA via a recovery key
     * If we have the key on record, burn it and let them in.
     *
     * @param string $key Recovery key from user
     * @return bool Valid key, they may log in.
     */
    public function burn2FARecovery(string $key): bool {
        $list = $this->list2FA();
        $index = array_search($key, $list);
        if ($index === false) {
            return false;
        }
        unset($list[$index]);
        self::$db->prepared_query('
            UPDATE users_main SET
                Recovery = ?
            WHERE ID = ?
            ', count($list) === 0 ? null : serialize($list), $this->id
        );
        return self::$db->affected_rows() === 1;
    }

    public function remove2FA(): User {
        return $this->setUpdate('2FA_Key', null)
            ->setUpdate('Recovery', null);
    }

    public function paranoia(): array {
        return $this->info()['Paranoia'];
    }

    public function isParanoid(string $for): bool {
        return in_array($for, $this->info()['Paranoia']);
    }

    public function paranoiaLevel(): int {
        $paranoia = $this->paranoia();
        $level = count($paranoia);
        foreach ($paranoia as $p) {
            if (str_ends_with($p, '+')) {
                $level++;
            }
        }
        return $level;
    }

    public function paranoiaLabel(): string {
        $level = $this->paranoiaLevel();
        return match(true) {
            ($level > 20) => 'Very high',
            ($level >  5) => 'High',
            ($level >  1) => 'Low',
            ($level == 1) => 'Very Low',
            default       => 'Off',
        };
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
     * returns PARANOIA_HIDE, PARANOIA_OVERRIDDEN, PARANOIA_ALLOWED
     */
    public function propertyVisibleMulti(User $viewer, array $propertyList): int {
        $paranoia = array_map(fn($p) => $this->propertyVisible($viewer, $p), $propertyList);
        if (in_array(PARANOIA_HIDE, $paranoia)) {
            return PARANOIA_HIDE;
        }
        return in_array(PARANOIA_OVERRIDDEN, $paranoia) ? PARANOIA_OVERRIDDEN : PARANOIA_ALLOWED;
    }

    /**
     * What right does the viewer have to see a property of this user?
     *
     * returns PARANOIA_HIDE, PARANOIA_OVERRIDDEN, PARANOIA_ALLOWED
     */
    public function propertyVisible(User $viewer, string $property): int {
        if ($this->id === $viewer->id()) {
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

    public function recoveryFinalSize(): ?float {
        if (RECOVERY_DB) {
            return (float)self::$db->scalar("
                SELECT final FROM recovery_buffer WHERE user_id = ?
                ", $this->id
            );
        }
        return null;
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

    /**
     * Checks whether user has autocomplete enabled
     *
     * @param string $Type Where the is the input requested (search, other)
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
     */
    public function forbiddenForums(): array {
        return array_keys(array_filter($this->info()['forum_access'], fn ($v) => $v === false));
    }

    /**
     * Return the list for forum IDs to which the user has been granted special access.
     */
    public function permittedForums(): array {
        return array_keys(array_filter($this->info()['forum_access'], fn ($v) => $v === true));
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
     * @return bool has access
     */
    public function forumAccess(int $forumId, int $forumMinClassLevel): bool {
        return ($this->classLevel() >= $forumMinClassLevel || in_array($forumId, $this->permittedForums()))
            && !in_array($forumId, $this->forbiddenForums());
    }

    /**
     * Checks whether user has the permission to create a forum.
     *
     * @return boolean true if user has permission
     */
    public function createAccess(Forum $forum): bool {
        return $this->forumAccess($forum->id(), $forum->minClassCreate());
    }

    /**
     * Checks whether user has the permission to read a forum.
     *
     * @return boolean true if user has permission
     */
    public function readAccess(Forum $forum): bool {
        return $this->forumAccess($forum->id(), $forum->minClassRead());
    }

    /**
     * Checks whether user has the permission to write to a forum.
     *
     * @return boolean true if user has permission
     */
    public function writeAccess(Forum $forum): bool {
        return $this->forumAccess($forum->id(), $forum->minClassWrite());
    }

    /**
     * Checks whether the user is up to date on the forum
     *
     * @return bool the user is up to date
     */
    public function hasReadLastPost(Forum $forum): bool {
        return $forum->isLocked()
            || $this->lastReadInThread($forum->lastThreadId()) >= $forum->lastPostId()
            || $this->forumCatchupEpoch() >= $forum->lastPostTime();
    }

    /**
     * What is the last post this user has read in a thread?
     */
    public function lastReadInThread(int $threadId): int {
        if (!isset($this->lastRead)) {
            self::$db->prepared_query("
                SELECT TopicID, PostID FROM forums_last_read_topics WHERE UserID = ?
                ", $this->id
            );
            $this->lastRead = self::$db->to_pair('TopicID', 'PostID', false);
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
            $this->lastReadForum = (int)self::$db->scalar("
                SELECT unix_timestamp(last_read) FROM user_read_forum WHERE user_id = ?
                ", $this->id
            );
        }
        return $this->lastReadForum;
    }

    public function forceCacheFlush($flush = true): bool {
        return $this->forceCacheFlush = $flush;
    }

    public function flushRecentSnatch(): bool {
        return self::$cache->delete_value(sprintf(self::USER_RECENT_SNATCH, $this->id));
    }

    public function flushRecentUpload(): bool {
        return self::$cache->delete_value(sprintf(self::USER_RECENT_UPLOAD, $this->id));
    }

    public function recordEmailChange(string $newEmail, string $ipaddr): int {
        self::$db->prepared_query("
            INSERT INTO users_history_emails
                   (UserID, Email, IP, useragent)
            VALUES (?,      ?,     ?,  ?)
            ", $this->id, $newEmail, $ipaddr, $_SERVER['HTTP_USER_AGENT']
        );
        Irc::sendMessage($this->username(), "Security alert: Your email address was changed via $ipaddr with {$_SERVER['HTTP_USER_AGENT']}. Not you? Contact staff ASAP.");
        (new Mail)->send($this->email(), 'Email address changed information for ' . SITE_NAME,
            self::$twig->render('email/email-address-change.twig', [
                'ipaddr'     => $ipaddr,
                'new_email'  => $newEmail,
                'now'        => date('Y-m-d H:i:s'),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'username'   => $this->username(),
            ])
        );
        self::$cache->delete_value('user_email_count_' . $this->id);
        return self::$db->affected_rows();
    }

    public function recordPasswordChange(string $ipaddr): int {
        self::$db->prepared_query("
            INSERT INTO users_history_passwords
                   (UserID, ChangerIP, useragent)
            VALUES (?,      ?,         ?)
            ", $this->id, $ipaddr, $_SERVER['HTTP_USER_AGENT']
        );
        Irc::sendMessage($this->username(), "Security alert: Your password was changed via $ipaddr with {$_SERVER['HTTP_USER_AGENT']}. Not you? Contact staff ASAP.");
        (new Mail)->send($this->email(), 'Password changed information for ' . SITE_NAME,
            self::$twig->render('email/password-change.twig', [
                'ipaddr'     => $ipaddr,
                'now'        => date('Y-m-d H:i:s'),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'username'   => $this->username(),
            ])
        );
        self::$cache->delete_value('user_pw_count_' . $this->id);
        return self::$db->affected_rows();
    }

    public function remove(): int {
        // Many, but not all, of the associated user tables will drop their entries via foreign key cascades.
        // But some won't. If this call fails, you will need to decide what to do about the tables in question.
        self::$db->prepared_query("
            DELETE FROM users_main WHERE ID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    /**
     * Record a forum warning for this user
     */
    public function addForumWarning(string $reason): User {
        $this->forumWarning[] = $reason;
        return $this;
    }

    /**
     * Record a staff not for this user
     */
    public function addStaffNote(string $note): User {
        $this->staffNote[] = $note;
        return $this;
    }

    /**
     * Set the user custom title (may contain BBcode)
     */
    public function setTitle(string $title): bool {
        $title = trim($title);
        if (mb_strlen($title) > USER_TITLE_LENGTH) {
            return false;
        }
        $this->setUpdate('Title', $title);
        return true;
    }

    /**
     * Remove the custom title of a user
     */
    public function removeTitle(): User {
        return $this->setUpdate('Title', null);
    }

    public function modifyOption(string $name, $value): User {
        $options = $this->info()['SiteOptions'];
        if (is_null($value)) {
            unset($options[$name]);
        } else {
            $options[$name] = $value;
        }
        self::$db->prepared_query("
            UPDATE users_info SET
                SiteOptions = ?
            WHERE UserID = ?
            ", serialize($options), $this->id
        );
        $this->flush();
        return $this;
    }

    public function modify(): bool {
        $changed = false;
        if (!empty($this->forumWarning)) {
            $warning = implode(', ', $this->forumWarning);
            self::$db->prepared_query("
                INSERT INTO users_warnings_forums
                       (UserID, Comment)
                VALUES (?,      concat(now(), ' - ', ?))
                ON DUPLICATE KEY UPDATE
                    Comment = concat(Comment, '\n', now(), ' - ', ?)
                ", $this->id, $warning, $warning
            );
            $changed = self::$db->affected_rows() > 0; // 1 or 2 depending on whether the update is triggered
            $this->forumWarning = [];
        }
        if (!empty($this->staffNote)) {
            self::$db->prepared_query("
                UPDATE users_info SET
                AdminComment = CONCAT(now(), ' - ', ?, AdminComment)
                WHERE UserID = ?
                ", implode(', ', $this->staffNote) . "\n\n", $this->id
            );
            $changed = $changed || self::$db->affected_rows() === 1;
            $this->staffNote = [];
        }
        if (parent::modify() || $changed) {
            $this->flush(); // parent::modify() may have done a flush() but it's too much code to optimize this second call away
            return true;
        }
        return false;
    }

    public function mergeLeechStats(string $username, string $staffname): ?array {
        [$mergeId, $up, $down] = self::$db->row("
            SELECT um.ID, uls.Uploaded, uls.Downloaded
            FROM users_main um
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            WHERE um.Username = ?
            ", $username
        );
        if (!$mergeId) {
            return null;
        }
        self::$db->prepared_query("
            UPDATE users_leech_stats uls
            INNER JOIN users_info ui USING (UserID)
            SET
                uls.Uploaded = 0,
                uls.Downloaded = 0,
                ui.AdminComment = concat(now(), ' - ', ?, ui.AdminComment)
            WHERE uls.UserID = ?
            ", sprintf("leech stats (up: %s, down: %s, ratio: %s) transferred to %s (%s) by %s\n\n",
                    byte_format($up), byte_format($down), ratio($up, $down),
                    $this->url(), $this->username(), $staffname
            ),
            $mergeId
        );
        $this->flush();
        return ['up' => $up, 'down' => $down, 'userId' => $mergeId];
    }

    public function lock(int $lockType): bool {
        self::$db->prepared_query("
            INSERT INTO locked_accounts
                   (UserID, Type)
            VALUES (?,      ?)
            ON DUPLICATE KEY UPDATE Type = ?
            ", $this->id, $lockType, $lockType
        );
        $this->flush();
        return self::$db->affected_rows() === 1;
    }

    public function unlock(): bool {
        self::$db->prepared_query("
            DELETE FROM locked_accounts WHERE UserID = ?
            ", $this->id
        );
        $this->flush();
        return self::$db->affected_rows() === 1;
    }

    public function updateTokens(int $n): bool {
        self::$db->prepared_query('
            UPDATE user_flt SET
                tokens = ?
            WHERE user_id = ?
            ', $n, $this->id
        );
        $this->flush();
        return self::$db->affected_rows() === 1;
    }

    public function updateIP($oldIP, $newIP): int {
        self::$db->prepared_query('
            UPDATE users_history_ips SET
                EndTime = now()
            WHERE EndTime IS NULL
                AND UserID = ?  AND IP = ?
                ', $this->id, $oldIP
        );
        self::$db->prepared_query('
            INSERT IGNORE INTO users_history_ips
                   (UserID, IP)
            VALUES (?,      ?)
            ', $this->id, $newIP
        );
        self::$db->prepared_query('
            UPDATE users_main SET
                IP = ?, ipcc = ?
            WHERE ID = ?
            ', $newIP, geoip($newIP), $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function registerDownload(int $torrentId): int {
        self::$db->prepared_query("
            INSERT INTO users_downloads
                   (UserID, TorrentID)
            VALUES (?,      ?)
            ", $this->id, $torrentId
        );
        $affected = self::$db->affected_rows();
        if (!$affected) {
            return 0;
        }
        $this->stats()->increment('download_total');
        self::$cache->delete_value('user_rlim_' . $this->id);
        $key = sprintf(self::CACHE_SNATCH_TIME, $this->id);
        $nextUpdate = self::$cache->get_value($key);
        if ($nextUpdate !== false) {
            $soon = time() + self::SNATCHED_UPDATE_AFTERDL;
            if ($soon < $nextUpdate['next']) { // only if the change is closer than the next update
                self::$cache->cache_value($key, ['next' => $soon], 0);
            }
        }
        return $affected;
    }

    /**
     * Validate a user password
     *
     * @param string $plaintext password
     * @return bool  true on correct password
     */
    public function validatePassword(#[\SensitiveParameter] string $plaintext): bool {
        $hash = $this->info()['PassHash'];
        $success = password_verify(hash('sha256', $plaintext), $hash);
        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            self::$db->prepared_query("
                UPDATE users_main SET
                    PassHash = ?
                WHERE ID = ?
                ", UserCreator::hashPassword($plaintext), $this->id
            );
        }
        return $success;
    }

    public function updatePassword(#[\SensitiveParameter] string $pw, string $ipaddr): bool {
        self::$db->begin_transaction();
        self::$db->prepared_query('
            UPDATE users_main SET
                PassHash = ?
            WHERE ID = ?
            ', UserCreator::hashPassword($pw), $this->id
        );
        if (self::$db->affected_rows() !== 1) {
            self::$db->rollback();
            return false;
        }
        self::$db->prepared_query('
            INSERT INTO users_history_passwords
                   (UserID, ChangerIP, useragent)
            VALUES (?,      ?,         ?)
            ', $this->id, $ipaddr, $_SERVER['HTTP_USER_AGENT']
        );
        self::$db->commit();
        $this->flush();
        return true;
    }

    public function passwordHistory(): array {
        self::$db->prepared_query("
            SELECT ChangeTime AS date,
                ChangerIP     AS ipaddr,
                useragent
            FROM users_history_passwords
            WHERE UserID = ?
            ORDER BY ChangeTime DESC
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function onRatioWatch(): bool {
        return $this->info()['RatioWatchEndsEpoch'] !== false
            && time() > $this->info()['RatioWatchEndsEpoch']
            && $this->uploadedSize() <= $this->downloadedSize() * $this->requiredRatio();
    }

    public function modifyAnnounceKeyHistory(string $oldPasskey, string $newPasskey, string $ipaddr): bool {
        self::$db->prepared_query("
            INSERT INTO users_history_passkeys
                   (UserID, OldPassKey, NewPassKey, ChangerIP)
            VALUES (?,      ?,          ?,          ?)
            ", $this->id, $oldPasskey, $newPasskey, $ipaddr
        );
        return self::$db->affected_rows() === 1;
    }

    public function announceKeyHistory(): array {
        self::$db->prepared_query("
            SELECT OldPassKey AS old,
                NewPassKey    AS new,
                ChangeTime    AS date,
                ChangerIP     AS ipaddr
            FROM users_history_passkeys
            WHERE UserID = ?
            ORDER BY ChangeTime DESC
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function inboxUnreadCount(): int {
        $unread = self::$cache->get_value('inbox_new_' . $this->id);
        if ($unread === false) {
            $unread = (int)self::$db->scalar("
                SELECT count(*)
                FROM pm_conversations_users
                WHERE UnRead    = '1'
                    AND InInbox = '1'
                    AND UserID  = ?
                ", $this->id
            );
            self::$cache->cache_value('inbox_new_' . $this->id, $unread, 0);
        }
        return $unread;
    }

    public function supportCount(int $newClassId, int $levelClassId): int {
        return (int)self::$db->scalar("
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
        self::$db->prepared_query("
            INSERT IGNORE INTO users_levels (UserID, PermissionID)
            VALUES " . implode(', ', array_fill(0, count($classes), "({$this->id}, ?)")),
            ...$classes
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function removeClasses(array $classes): int {
        self::$db->prepared_query("
            DELETE FROM users_levels
            WHERE UserID = ?
                AND PermissionID IN (" . placeholders($classes) . ")",
            $this->id, ...$classes
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function notifyFilters(): array {
        $key = sprintf(self::CACHE_NOTIFY, $this->id);
        if ($this->forceCacheFlush || ($filters = self::$cache->get_value($key)) === false) {
            self::$db->prepared_query('
                SELECT ID, Label
                FROM users_notify_filters
                WHERE UserID = ?
                ', $this->id
            );
            $filters = self::$db->to_pair('ID', 'Label', false);
            self::$cache->cache_value($key, $filters, 2_592_000);
        }
        return $filters;
    }

    public function removeNotificationFilter(int $notifId): int {
        self::$db->prepared_query('
            DELETE FROM users_notify_filters
            WHERE UserID = ? AND ID = ?
            ', $this->id, $notifId
        );
        $removed = self::$db->affected_rows();
        if ($removed) {
            self::$cache->delete_multi(['u_notify_' . $this->id, 'notify_artists_' . $this->id]);
        }
        return $removed;
    }

    public function loadArtistNotifications(): array {
        $info = self::$cache->get_value('notify_artists_' . $this->id);
        if (empty($info)) {
            self::$db->prepared_query("
                SELECT ID, Artists
                FROM users_notify_filters
                WHERE Label = ?
                    AND UserID = ?
                ORDER BY ID
                LIMIT 1
                ", 'Artist notifications', $this->id
            );
            $info = self::$db->next_record(MYSQLI_ASSOC, false);
            if (!$info) {
                $info = ['ID' => 0, 'Artists' => ''];
            }
            self::$cache->cache_value('notify_artists_' . $this->id, $info, 0);
        }
        return $info;
    }

    public function hasArtistNotification(string $name): bool {
        $info = $this->loadArtistNotifications();
        return stripos($info['Artists'], "|$name|") !== false;
    }

    public function addArtistNotification(\Gazelle\Artist $artist): int {
        $info = $this->loadArtistNotifications();
        $alias = implode('|', $artist->aliasNameList());
        if (!$alias) {
            return 0;
        }

        $change = 0;
        if (!$info['ID']) {
            self::$db->prepared_query("
                INSERT INTO users_notify_filters
                       (UserID, Label, Artists)
                VALUES (?,      ?,     ?)
                ", $this->id, 'Artist notifications', "|$alias|"
            );
            $change = self::$db->affected_rows();
        } elseif (stripos($info['Artists'], "|$alias|") === false) {
            self::$db->prepared_query("
                UPDATE users_notify_filters SET
                    Artists = ?
                WHERE ID = ? AND Artists NOT LIKE concat('%', ?, '%')
                ", $info['Artists'] . "$alias|", $info['ID'], "|$alias|"
            );
            $change = self::$db->affected_rows();
        }
        if ($change) {
            self::$cache->delete_multi(['u_notify_' . $this->id, 'notify_artists_' . $this->id]);
        }
        return $change;
    }

    public function notifyDeleteSeeding(): bool {
        return !$this->hasAttr('no-pm-delete-seed');
    }

    public function notifyDeleteSnatch(): bool {
        return !$this->hasAttr('no-pm-delete-snatch');
    }

    public function notifyDeleteDownload(): bool {
        return !$this->hasAttr('no-pm-delete-download');
    }

    public function removeArtistNotification(\Gazelle\Artist $artist): int {
        $info = $this->loadArtistNotifications();
        $aliasList = $artist->aliasNameList();
        foreach ($aliasList as $alias) {
            while (stripos($info['Artists'], "|$alias|") !== false) {
                $info['Artists'] = str_ireplace("|$alias|", '|', $info['Artists']);
            }
        }
        if ($info['Artists'] === '||') {
            self::$db->prepared_query("
                DELETE FROM users_notify_filters
                WHERE ID = ?
                ", $info['ID']
            );
            $change = self::$db->affected_rows();
        } else {
            self::$db->prepared_query("
                UPDATE users_notify_filters SET
                    Artists = ?
                WHERE ID = ?
                ", $info['Artists'], $info['ID']
            );
            $change = self::$db->affected_rows();
        }
        if ($change) {
            self::$cache->delete_multi(['u_notify_' . $this->id, 'notify_artists_' . $this->id]);
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
    public function isFLS(): bool           { return (new User\Privilege($this))->isFLS(); }
    public function isInterviewer(): bool   { return (new User\Privilege($this))->isInterviewer(); }
    public function isRecruiter(): bool     { return (new User\Privilege($this))->isRecruiter(); }
    public function isStaffPMReader(): bool { return $this->isFLS() || $this->isStaff(); }

    public function warningExpiry(): ?string {
        return $this->info()['Warned'];
    }

    public function endWarningDate(int $weeks): string {
        return (string)self::$db->scalar("
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
        return $this->paidPersonalCollages() + (new User\Donor($this))->collageTotal();
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
        return (int)self::$db->scalar("
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

    public function collageUnreadCount(): int {
        if (($new = self::$cache->get_value(sprintf(Collage::SUBS_NEW_KEY, $this->id))) === false) {
            $new = self::$db->scalar("
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
            self::$cache->cache_value(sprintf(Collage::SUBS_NEW_KEY, $this->id), $new, 0);
        }
        return $new;
    }

    public function clients(): array {
        self::$db->prepared_query('
            SELECT DISTINCT useragent FROM xbt_files_users WHERE uid = ?
            ', $this->id
        );
        return self::$db->collect(0) ?: ['None'];
    }

    protected function getSingleValue($cacheKey, $query): string {
        $cacheKey .= '_' . $this->id;
        if ($this->forceCacheFlush || ($value = self::$cache->get_value($cacheKey)) === false) {
            $value = (string)self::$db->scalar($query, $this->id);
            self::$cache->cache_value($cacheKey, $value, 3600);
        }
        return $value;
    }

    public function duplicateIPv4Count(): int {
        $cacheKey = "ipv4_dup_" . str_replace('-', '_', $this->info()['IP']);
        $value = self::$cache->get_value($cacheKey);
        if ($value === false) {
            $value = self::$db->scalar("
                SELECT count(*) FROM users_history_ips WHERE IP = ?
                ", $this->info()['IP']
            );
            self::$cache->cache_value($cacheKey, $value, 3600);
        }
        return max(0, (int)$value - 1);
    }

    public function lastAccess(): ?string {
        $lastAccess = $this->getSingleValue('user_last_access', "
            SELECT ula.last_access FROM user_last_access ula WHERE user_id = ?
        ");
        return $lastAccess ? (string)$lastAccess : null;
    }

    public function lastAccessRealtime(): ?string {
        $lastAccess = self::$db->scalar("
            SELECT coalesce(max(ulad.last_access), ula.last_access)
            FROM user_last_access ula
            LEFT JOIN user_last_access_delta ulad USING (user_id)
            WHERE ula.user_id = ?
            GROUP BY ula.user_id
            ", $this->id
        );
        return $lastAccess ? (string)$lastAccess : null;
    }

    public function passwordCount(): int {
        return (int)$this->getSingleValue('user_pw_count', '
            SELECT count(*) FROM users_history_passwords WHERE UserID = ?
        ');
    }

    public function announceKeyCount(): int {
        return (int)$this->getSingleValue('user_passkey_count', '
            SELECT count(*) FROM users_history_passkeys WHERE UserID = ?
        ');
    }

    public function siteIPCount(): int {
        return (int)$this->getSingleValue('user_siteip_count', '
            SELECT count(DISTINCT IP) FROM users_history_ips WHERE UserID = ?
        ');
    }

    public function trackerIPCount(): int {
        return (int)$this->getSingleValue('user_trackip_count', "
            SELECT count(DISTINCT IP) FROM xbt_snatched WHERE uid = ? AND IP != ''
        ");
    }

    public function emailCount(): int {
        return (int)$this->getSingleValue('user_email_count', '
            SELECT count(*) FROM users_history_emails WHERE UserID = ?
        ');
    }

    public function inviter(): ?User {
        return new User($this->inviterId());
    }

    public function inviterId(): int {
        return (int)$this->info()['Inviter'];
    }

    public function unusedInviteTotal(): int {
        return $this->disableInvites() ? 0 : $this->info()['Invites'];
    }

    public function decrementInviteCount(): bool {
        if ($this->permitted('site_send_unlimited_invites')) {
            return true;
        }
        self::$db->prepared_query("
            UPDATE users_main SET
                Invites = GREATEST(Invites, 1) - 1
            WHERE ID = ?
            ", $this->id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected > 0;
    }

    public function pendingInviteCount(): int {
        return (int)$this->getSingleValue('user_inv_pending', '
            SELECT count(*)
            FROM invites
            WHERE InviterID = ?
        ');
    }

    public function pendingInviteList(): array {
        self::$db->prepared_query("
            SELECT InviteKey AS invite_key,
                Email        AS email,
                Expires      AS expires
            FROM invites
            WHERE InviterID = ?
            ORDER BY Expires
            ", $this->id
        );
        return self::$db->to_array('invite_key', MYSQLI_ASSOC, false);
    }

    public function inviteList(string $orderBy, string $direction): array {
        self::$db->prepared_query("
            SELECT
                um.ID          AS user_id,
                um.Email       AS email,
                uls.Uploaded   AS uploaded,
                uls.Downloaded AS downloaded,
                um.created     AS created,
                ula.last_access
            FROM users_main AS um
            LEFT  JOIN user_last_access AS ula ON (ula.user_id = um.ID)
            INNER JOIN users_leech_stats AS uls ON (uls.UserID = um.ID)
            INNER JOIN users_info AS ui ON (ui.UserID = um.ID)
            WHERE ui.Inviter = ?
            ORDER BY $orderBy $direction
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function invitedTotal(): int {
        return (int)$this->getSingleValue('user_invited', '
            SELECT count(*) FROM users_info WHERE Inviter = ?
        ');
    }

    public function passwordAge(): string {
        $age = time_diff(
            $this->getSingleValue('user_pw_age', '
                SELECT coalesce(max(uhp.ChangeTime), um.created)
                FROM users_main um
                LEFT JOIN users_history_passwords uhp ON (uhp.UserID = um.ID)
                WHERE um.ID = ?
            ')
        );
        return substr($age, 0, (int)strpos($age, " ago"));
    }

    public function forumWarning(): ?string {
        $warning = $this->getSingleValue('user_forum_warn', "
            SELECT Comment FROM users_warnings_forums WHERE UserID = ?
        ");
        return $warning ? (string)$warning : null;
    }

    public function collagesCreated(): int {
        return (int)$this->getSingleValue('user_collage_create', "
            SELECT count(*) FROM collages WHERE Deleted = '0' AND UserID = ?
        ");
    }

    /**
     * Default list 5 will be cached. When fetching a different amount,
     * set $forceNoCache to true to avoid caching a list with an unexpected length.
     * This technique should be revisited, possibly by adding the limit to the key.
     */
    public function recentSnatchList(int $limit = 5, bool $forceNoCache = false): array {
        $key = sprintf(self::USER_RECENT_SNATCH, $this->id);
        $recent = self::$cache->get_value($key);
        if ($forceNoCache) {
            $recent = false;
        }
        if ($recent === false) {
            self::$db->prepared_query("
                SELECT g.ID
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
            $recent = self::$db->collect(0, false);
            if (!$forceNoCache) {
                self::$cache->cache_value($key, $recent, 86400 * 3);
            }
        }
        return $recent;
    }

    public function tagSnatchCounts(int $limit = 8): array {
        $list = self::$cache->get_value('user_tag_snatch_' . $this->id);
        if ($list === false) {
            self::$db->prepared_query("
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
            $list = self::$db->to_array(false, MYSQLI_ASSOC, false);
            self::$cache->cache_value('user_tag_snatch_' . $this->id, $list, 86400 * 90);
        }
        return $list;
    }

    /**
     * Default list 5 will be cached. When fetching a different amount,
     * set $forceNoCache to true to avoid caching a list with an unexpected length
     */
    public function recentUploadList(int $limit = 5, bool $forceNoCache = false): array {
        $key = sprintf(self::USER_RECENT_UPLOAD, $this->id);
        $recent = self::$cache->get_value($key);
        if ($forceNoCache) {
            $recent = false;
        }
        if ($recent === false) {
            self::$db->prepared_query("
                SELECT g.ID
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
            $recent = self::$db->collect(0, false);
            if (!$forceNoCache) {
                self::$cache->cache_value($key, $recent, 86400 * 3);
            }
        }
        return $recent;
    }

    public function torrentDownloadCount(int $torrentId): int {
        return (int)self::$db->scalar('
            SELECT count(*)
            FROM users_downloads ud
            INNER JOIN torrents AS t ON (t.ID = ud.TorrentID)
            WHERE ud.UserID = ?
                AND ud.TorrentID = ?
            ', $this->id, $torrentId
        );
    }

    public function torrentRecentRemoveCount(int $hours): int {
        return (int)self::$db->scalar('
            SELECT count(*)
            FROM user_torrent_remove utr
            WHERE utr.user_id = ?
                AND utr.removed >= now() - INTERVAL ? HOUR
            ', $this->id, $hours
        );
    }

    /**
     * Generates a check list of release types, ordered by the user or default
     */
    public function releaseOrder(array $releaseType): array {
        if (empty($this->option('SortHide'))) {
            $sort = $releaseType;
            $defaults = !empty($this->option('HideTypes'));
        } else {
            $sort = (array)$this->option('SortHide');
            $missingTypes = array_diff_key($releaseType, $sort);
            foreach (array_keys($missingTypes) as $missing) {
                $sort[$missing] = 0;
            }
        }

        $order = [];
        foreach ($sort as $key => $val) {
            if (isset($defaults)) {
                $checked = $defaults && isset($this->option('HideTypes')[$key]);
            } elseif (isset($releaseType[$key])) {
                $checked = $val;
                $val = $releaseType[$key];
            } else {
                $checked = true;
            }
            $order[] = ['id' => $key. '_' . (int)(!!$checked), 'checked' => $checked, 'label' => $val];
        }
        return $order;
    }

    public function tokenCount(): int {
        return $this->info()['FLTokens'];
    }

    /**
     * Can the user spend a token (or more) to set this torrent Freeleech?
     * Note: The torrent object MUST be instantiated with setViewer() set
     * to the user.
     */
    public function canSpendFLToken(Torrent $torrent): bool {
        return $this->canLeech()
            && !$torrent->isFreeleech()
            && !$torrent->isFreeleechPersonal()
            && (STACKABLE_FREELEECH_TOKENS || $torrent->tokenCount() == 1)
            && $this->tokenCount() >= $torrent->tokenCount()
            ;
    }

    /**
     * Get a page of FL token uses by user
     *
     * @param int $limit How many? (To fill a page)
     * @param int $offset From where (which page)
     * @return array [torrent_id, group_id, created, expired, downloaded, uses, group_name, format, encoding, size]
     */
    public function tokenList(Manager\Torrent $torMan, int $limit, int $offset): array {
        self::$db->prepared_query("
            SELECT t.GroupID AS group_id,
                g.Name       AS group_name,
                f.TorrentId  AS torrent_id,
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
        $torrents = self::$db->to_array(false, MYSQLI_ASSOC, false);
        foreach ($torrents as $t) {
            $torrent = $torMan->findById($t['torrent_id']);
            $t['name'] = $torrent
                ? $torrent->fullLink()
                : "(<i>Deleted torrent <a href=\"log.php?search=Torrent+{$t['torrent_id']}\">{$t['torrent_id']}</a></i>)";
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

    /**
     * Add request bounty and update stats immediately.
     * Negative bounty can be added (!) in the case of a request unfill.
     */
    public function addBounty(int $bounty): int {
        if ($bounty > 0) {
            // adding
            self::$db->prepared_query("
                UPDATE users_leech_stats SET
                    Uploaded = Uploaded + ?
                WHERE UserID = ?
                ", $bounty, $this->id
            );
        } else {
            // removing, $bounty is negative
            $this->flush();
            $uploaded = $this->uploadedSize();
            if ($uploaded + $bounty < 0) {
                // If we can't take it all out of upload, zero that out and add whatever is left as download.
                self::$db->prepared_query("
                    UPDATE users_leech_stats SET
                        Uploaded = 0,
                        Downloaded = Downloaded + ?
                    WHERE UserID = ?
                    ", $uploaded + $bounty, $this->id
                );
            } else {
                self::$db->prepared_query("
                    UPDATE users_leech_stats SET
                        Uploaded = Uploaded + ?
                    WHERE UserID = ?
                    ", $bounty, $this->id
                );
            }
        }
        $nr = self::$db->affected_rows();
        $this->flush();
        $this->stats()->increment('request_bounty_total', $bounty > 0 ? 1 : -1);
        $this->stats()->increment('request_bounty_size', $bounty);
        return $nr;
    }

    public function buffer(): array {
        $class = $this->primaryClass();
        $demotion = array_filter((new Manager\User)->demotionCriteria(), fn($v) => in_array($class, $v['From']));
        $criteria = end($demotion);

        $effectiveUpload = $this->uploadedSize() + $this->stats()->requestBountySize();
        if ($criteria) {
            $ratio = $criteria['Ratio'];
        } else {
            $ratio = $this->requiredRatio();
        }

        return [$ratio, $ratio == 0 ? $effectiveUpload : $effectiveUpload / $ratio - $this->downloadedSize()];
    }

    public function nextClass(): ?array {
        $criteria = (new Manager\User)->promotionCriteria()[$this->info()['PermissionID']] ?? null;
        if (!$criteria) {
            return null;
        }

        $progress = [
            'Class' => (new Manager\User)->userclassName($criteria['To']),
            'Requirements' => [
                'Upload' => [$this->uploadedSize() + $this->stats()->requestVoteSize(), $criteria['MinUpload'], 'bytes'],
                'Ratio' => [$this->downloadedSize() == 0 ? '∞'
                    : $this->uploadedSize() / $this->downloadedSize(), $criteria['MinRatio'], 'float'],
                'Time' => [
                    $this->created(),
                    $criteria['Weeks'] * 7 * 24 * 60 * 60,
                    'time'
                ],
            ]
        ];

        if (isset($criteria['MinUploads'])) {
            $progress['Requirements']['Torrents'] = [$this->stats()->uploadTotal(), $criteria['MinUploads'], 'int'];
        }
        if (isset($criteria['Extra'])) {
            foreach ($criteria['Extra'] as $req => $info) {
                $query = $info['Query'];
                if (str_starts_with($query, 'us.')) {
                    $query = "SELECT $query FROM user_summary us WHERE user_id = ?";
                } else {
                    $query = str_replace('um.ID', '?', $query);
                }
                $progress['Requirements'][$req] = [
                    self::$db->scalar($query, ...array_fill(0, substr_count($query, '?'), $this->id)),
                    $info['Count'],
                    $info['Type']
                ];
            }
        }
        return $progress;
    }

    public function seedingSize(): int {
        return (int)$this->getSingleValue('seeding_size', '
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

        self::$db->prepared_query("
            INSERT INTO api_tokens
                   (user_id, name, token)
            VALUES (?,       ?,    ?)
            ", $this->id, $name, $token
        );
        return $token;
    }

    public function apiTokenList(): array {
        self::$db->prepared_query("
            SELECT id, name, token, created
            FROM api_tokens
            WHERE user_id = ?
                AND revoked = 0
            ORDER BY created DESC
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function hasTokenByName(string $name): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM api_tokens
            WHERE revoked = 0
                AND user_id = ?
                AND name = ?
            ", $this->id, $name
        );
    }

    public function hasApiToken(string $token): bool {
        return self::$db->scalar("
            SELECT 1
            FROM api_tokens
            WHERE revoked = 0
                AND user_id = ?
                AND token = ?
            ", $this->id, $token
        ) === 1;
    }

    public function revokeApiTokenById(int $tokenId): int {
        self::$db->prepared_query("
            UPDATE api_tokens SET
                revoked = 1
            WHERE user_id = ? AND id = ?
            ", $this->id, $tokenId
        );
        return self::$db->affected_rows();
    }

    public function revokeUpload(): int {
        self::$db->prepared_query("
            UPDATE users_info SET
                DisableUpload = '1'
            WHERE UserID = ?
            ", $this->id
        );
        $this->flush();
        return self::$db->affected_rows();
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
     *
     * @return boolean false if they have been naughty, otherwise true
     */
    public function canInvite(): bool {
        return $this->permitted('site_send_unlimited_invites')
            || (
                  !$this->onRatioWatch()
                && $this->canLeech()
                && $this->canPurchaseInvite()
            );
    }

    /**
     * Checks whether a user is allowed to purchase an invite. Lower classes are capped,
     * users above this class will always return true.
     *
     * @return boolean false if insufficient funds, otherwise true
     */
    public function canPurchaseInvite(): bool {
        return !$this->disableInvites() && $this->effectiveClass() >= MIN_INVITE_CLASS;
    }

    /**
     * Remove an active invitation
     */
    public function removeInvite(string $key): bool {
        self::$db->begin_transaction();
        self::$db->prepared_query("
            DELETE FROM invites WHERE InviteKey = ?
            ", $key
        );
        if (self::$db->affected_rows() == 0) {
            self::$db->rollback();
            return false;
        }
        if ($this->permitted('site_send_unlimited_invites')) {
            self::$db->commit();
            return true;
        }

        self::$db->prepared_query("
            UPDATE users_main SET
                Invites = Invites + 1
            WHERE ID = ?
            ", $this->id
        );
        self::$db->commit();
        $this->flush();
        return true;
    }

    /**
     * Initiate a password reset
     */
    public function resetPassword(): void {
        $resetKey = randomString();
        self::$db->prepared_query("
            UPDATE users_info SET
                ResetExpires = now() + INTERVAL 1 HOUR,
                ResetKey = ?
            WHERE UserID = ?
            ", $resetKey, $this->id
        );
        $this->flush();
        (new Mail)->send($this->email(), 'Password reset information for ' . SITE_NAME,
            self::$twig->render('email/password_reset.twig', [
                'username'  => $this->username(),
                'reset_key' => $resetKey,
                'ipaddr'    => $_SERVER['REMOTE_ADDR'],
            ])
        );
    }

    /*
     * Has a password reset expired?
     */
    public function resetPasswordExpired(): bool {
        return (bool)self::$db->scalar("
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
     * @return int 1 if the user was cleared
     */
    public function clearPasswordReset(): int {
        self::$db->prepared_query("
            UPDATE users_info SET
                ResetKey = '',
                ResetExpires = NULL
            WHERE UserID = ?
            ", $this->id
        );
        return self::$db->affected_rows();
    }
}
