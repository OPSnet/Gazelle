<?php

namespace Gazelle;

use Gazelle\Enum\AvatarDisplay;
use Gazelle\Enum\UserStatus;
use Gazelle\Enum\UserTokenType;
use Gazelle\Util\Irc;
use Gazelle\Util\Mail;
use Gazelle\Util\Time;

class User extends BaseObject {
    final public const tableName             = 'users_main';
    final protected const CACHE_KEY          = 'u2_%d';
    final protected const CACHE_NOTIFY       = 'u_notify_%d';
    final protected const USER_RECENT_UPLOAD = 'u_recent_up_%d';

    protected bool $forceCacheFlush = false;
    protected int $lastReadForum;

    protected array $avatarCache;
    protected array $lastRead;
    protected array $tokenCache;
    protected array $forumWarning = [];
    protected array $staffNote = [];

    protected Stats\User     $stats;
    protected User\Invite    $invite;
    protected User\Privilege $privilege;
    protected User\Snatch    $snatch;

    public function flush(): static {
        self::$cache->delete_multi([
            sprintf(self::CACHE_KEY, $this->id),
            sprintf('user_inv_pending_%d', $this->id),
            sprintf('user_invited_%d', $this->id),
            sprintf('user_last_access_%d', $this->id),
            sprintf('user_siteip_count_%d', $this->id),
            sprintf('user_stat_%d', $this->id),
            sprintf('users_tokens_%d', $this->id),
        ]);
        $this->stats()->flush();
        $this->privilege()->flush();
        unset($this->info);
        unset($this->privilege);
        unset($this->stats);
        unset($this->tokenCache);
        return $this;
    }
    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), html_escape($this->username())); }
    public function location(): string { return 'user.php?id=' . $this->id; }

    /**
     * Delegate snatch status methods to the User\Inbox class.
     * A new object is instantiated each time. This is nearly
     * always what you need, if just creating a new conversation.
     */
    public function inbox(): User\Inbox {
        return new User\Inbox($this);
    }

    /**
     * Delegate privilege methods to the User\Invite class
     * This delegation is stateful.
     */
    public function invite(): User\Invite {
        return $this->invite ??= new User\Invite($this);
    }

    public function privilege(): User\Privilege {
        return $this->privilege ??= new User\Privilege($this);
    }

    public function snatch(): User\Snatch {
        return $this->snatch ??= new User\Snatch($this);
    }

    public function stats(): \Gazelle\Stats\User {
        return $this->stats ??= new Stats\User($this->id);
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
        if (isset($this->info)) {
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
                um.auth_key,
                um.avatar,
                um.can_leech,
                um.collage_total,
                um.created,
                um.inviter_user_id,
                um.IP,
                um.Email,
                um.Enabled,
                um.Invites,
                um.IRCKey,
                um.nav_list,
                um.Paranoia,
                um.PassHash,
                um.PermissionID,
                um.profile_info,
                um.profile_title,
                um.RequiredRatio,
                um.slogan,
                um.Title,
                um.torrent_pass,
                um.Visible,
                um.2FA_Key,
                ui.AdminComment,
                ui.BanDate,
                ui.NavItems,
                ui.RatioWatchEnds,
                ui.RatioWatchDownload,
                ui.SiteOptions,
                uls.Uploaded,
                uls.Downloaded,
                p.Level AS Class,
                p.Name  AS className,
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
        $this->info['nav_list']    = json_decode($this->info['nav_list'] ?? '[]', true);
        $this->info['NavItems']    = empty($this->info['NavItems']) ? [] : explode(',', $this->info['NavItems']);
        $this->info['ParanoiaRaw'] = $this->info['Paranoia'];
        $this->info['Paranoia']    = $this->info['Paranoia'] ? unserialize($this->info['Paranoia']) : [];
        $this->info['SiteOptions'] = $this->info['SiteOptions'] ? unserialize($this->info['SiteOptions']) : [];
        if (!isset($this->info['SiteOptions']['HttpsTracker'])) {
            $this->info['SiteOptions']['HttpsTracker'] = true;
        }
        $this->info['RatioWatchEndsEpoch'] = $this->info['RatioWatchEnds']
            ? strtotime($this->info['RatioWatchEnds']) : 0;

        self::$db->prepared_query("
            SELECT ua.Name, ua.ID
            FROM user_attr ua
            INNER JOIN user_has_attr uha ON (uha.UserAttrID = ua.ID)
            WHERE uha.UserID = ?
            ", $this->id
        );
        $this->info['attr'] = self::$db->to_pair('Name', 'ID', false);

        $this->info['warning_expiry'] = (new User\Warning($this))->warningExpiry();

        self::$cache->cache_value($key, $this->info, 3600);
        self::$db->set_query_id($qid);
        return $this->info;
    }

    /**
     * Get the custom user link navigation configuration.
     */
    public function navigationList(): array {
        return $this->info()['NavItems'];
    }

    public function addCustomPrivilege(string $name): bool {
        $custom = (string)self::$db->scalar("
            SELECT CustomPermissions FROM users_main WHERE ID = ?
            ", $this->id
        );
        $custom = unserialize($custom) ?: [];
        $custom[$name] = 1;
        $this->privilege()->flush();
        return $this->setField('CustomPermissions', serialize($custom))->modify();
    }

    /**
     * Set the custom permissions for this user
     * TODO: this is pretty messed up, make it nice (get rid if "perm_")
     *
     * @param array $current a list of "perm_<permission_name>" custom permissions
     * @return bool was there a change?
     */
    public function modifyPrivilegeList(array $current): bool {
        $permissionList = array_keys(\Gazelle\Manager\Privilege::privilegeList());
        $default = $this->privilege()->defaultPrivilegeList();
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
        $this->privilege()->flush();
        return $affected === 1;
    }

    /**
     * Does the user have a specific privilege?
     */
    public function permitted(string $privilege): bool {
        return $this->privilege()->permitted($privilege);
    }

    /**
     * Does the user have any of the specified privileges?
     */
    public function permittedAny(string ...$privilege): bool {
        foreach ($privilege as $p) {
            if ($this->privilege()->permitted($p)) {
                return true;
            }
        }
        return false;
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
        return $this->info()['auth_key'];
    }

    public function avatar(): ?string {
        return $this->info()['avatar'];
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
                'image' => match ($this->avatarMode()) {
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

    public function banDate(): ?string {
        return $this->info()['BanDate'];
    }

    public function bonusPointsTotal(): int {
        return (int)$this->info()['BonusPoints'];
    }

    /**
     * Is a user allowed to download a torrent file?
     */
    public function canLeech(): bool {
        return $this->info()['can_leech'];
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

    public function downloadSpeed(): float {
        $createdEpoch = strtotime($this->created());
        if ($createdEpoch === false) {
            return 0.0;
        }
        return $this->downloadedSize() / (time() - $createdEpoch);
    }

    public function downloadedOnRatioWatch(): int {
        return $this->info()['RatioWatchDownload'];
    }

    public function email(): string {
        return $this->info()['Email'];
    }

    public function externalProfile(): User\ExternalProfile {
        return new User\ExternalProfile($this);
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

    public function lastAccess(): ?string {
        $lastAccess = $this->getSingleValue('user_last_access', "
            SELECT ula.last_access FROM user_last_access ula WHERE user_id = ?
        ");
        return $lastAccess ?: null;
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

    public function option(string $option): mixed {
        return $this->info()['SiteOptions'][$option] ?? null;
    }

    public function postsPerPage(): int {
        return $this->info()['SiteOptions']['PostsPerPage'] ?? POSTS_PER_PAGE;
    }

    public function profileInfo(): string {
        return $this->info()['profile_info'];
    }

    public function profileTitle(): string {
        return $this->info()['profile_title'] ?: 'Profile';
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

    public function slogan(): ?string {
        return $this->info()['slogan'];
    }

    public function staffNotes(): string {
        return $this->info()['AdminComment'];
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

    public function uploadSpeed(): float {
        $createdEpoch = strtotime($this->created());
        if ($createdEpoch === false) {
            return 0.0;
        }
        return ($this->uploadedSize() - STARTING_UPLOAD) / (time() - $createdEpoch);
    }

    public function userclassName(): string {
        return $this->info()['className'];
    }

    public function username(): string {
        return $this->info()['Username'];
    }

    public function userStatus(): UserStatus {
        return match ($this->info()['Enabled']) {
            '1'     => UserStatus::enabled,
            '2'     => UserStatus::disabled,
            default => UserStatus::unconfirmed,
        };
    }

    /**
     * Create the recovery keys for the user
     */
    public function create2FA(Manager\UserToken $manager, string $key): int {
        $unique = [];
        while (count($unique) < 10) {
            $unique[randomString(20)] = 1;
        }
        $recovery = array_keys($unique);
        self::$db->prepared_query("
            UPDATE users_main SET
                2FA_Key = ?,
                Recovery = ?
            WHERE ID = ?
            ", $key, serialize($recovery), $this->id
        );
        $affected = self::$db->affected_rows();
        foreach ($recovery as $value) {
            $manager->create(UserTokenType::mfa, user: $this, value: $value);
        }
        $this->flush();
        return $affected;
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

    public function remove2FA(): static {
        return $this->setField('2FA_Key', null)
            ->setField('Recovery', null);
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
        return match (true) {
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
        return $this->info()['PermissionID'];
    }

    /**
     * Checks whether user has autocomplete enabled
     *
     * @param string $Type Where is the input requested (search, other)
     */
    public function hasAutocomplete(string $Type): bool {
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
        return $this->privilege()->forbiddenForumIdList();
    }

    /**
     * Return the list for forum IDs to which the user has been granted special access.
     */
    public function permittedForums(): array {
        return $this->privilege()->permittedForumIdList();
    }

    public function forbiddenForumsList(): string {
        return $this->privilege()->forbiddenForums();
    }

    public function permittedForumsList(): string {
        return $this->privilege()->permittedForums();
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
            || $this->forumCatchupEpoch() >= $forum->lastPostEpoch();
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

    public function forumLastReadList(int $perPage, Forum $forum): array {
        self::$db->prepared_query("
            SELECT l.TopicID AS thread_id,
                l.PostID     AS post_id,
                ceil((SELECT count(*) FROM forums_posts AS p WHERE p.TopicID = l.TopicID AND p.ID <= l.PostID) / ?)
                    AS page
            FROM forums_last_read_topics AS l
            INNER JOIN forums_topics ft ON (ft.ID = l.TopicID)
            INNER JOIN forums f ON (f.ID = ft.ForumID)
            WHERE l.UserID = ?
                AND f.ID = ?
            ", $perPage, $this->id, $forum->id()
        );
        $list = [];
        foreach (self::$db->to_array('thread_id', MYSQLI_ASSOC, false) as $row) {
            $row['page'] = (int)$row['page'];
            $list[$row['thread_id']] = $row;
        }
        return $list;
    }

    public function forceCacheFlush($flush = true): bool {
        return $this->forceCacheFlush = $flush;
    }

    public function flushRecentUpload(): bool {
        return self::$cache->delete_value(sprintf(self::USER_RECENT_UPLOAD, $this->id));
    }

    protected function notifyPasswordChange(string $ipaddr, string $userAgent): void {
        Irc::sendMessage($this->username(), "Security alert: Your password was changed via $ipaddr with $userAgent. Not you? Contact staff ASAP.");
        (new Mail())->send($this->email(), 'Password changed information for ' . SITE_NAME,
            self::$twig->render('email/password-change.twig', [
                'ipaddr'     => $ipaddr,
                'now'        => date('Y-m-d H:i:s'),
                'user_agent' => $userAgent,
                'username'   => $this->username(),
            ])
        );
    }

    public function remove(): int {
        $id       = $this->id;
        $username = $this->username();
        // Many, but not all, of the associated user tables will drop their entries via foreign key cascades.
        // But some won't. If this call fails, you will need to decide what to do about the tables in question.
        self::$db->prepared_query("
            DELETE FROM users_main WHERE ID = ?
            ", $id
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        self::$cache->delete_multi([
            sprintf(Manager\User::ID_KEY, $id),
            sprintf(Manager\User::USERNAME_KEY, $username),
        ]);
        return $affected;
    }

    /**
     * Record a forum warning for this user
     */
    public function addForumWarning(string $reason): static {
        $this->forumWarning[] = $reason;
        return $this;
    }

    /**
     * Record a staff not for this user
     */
    public function addStaffNote(string $note): static {
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
        $this->setField('Title', $title);
        return true;
    }

    /**
     * Remove the custom title of a user
     */
    public function removeTitle(): static {
        return $this->setField('Title', null);
    }

    /**
     * Warn a user. Returns expiry date.
     */
    public function warn(int $duration, string $reason, \Gazelle\User $staff, string $userMessage): string {
        $warnTime = Time::offset($duration * 7 * 86_400);
        $warning  = new \Gazelle\User\Warning($this);
        $expiry   = $warning->warningExpiry();
        if ($expiry) {
            $subject = 'You have received a new warning';
            $message = "You have received a new warning by [user]{$staff->username()}[/user]. "
                . "You had an existing warning (set to expire at $expiry).\n\nDue to this prior warning, "
                . "you will remain warned until $warnTime.\nReason: $userMessage";
        } else {
            $subject = 'You have been warned';
            $message = "You have been warned by [user]{$staff->username()}[/user]. "
                . "The warning is set to expire on $warnTime. Remember, repeated warnings may jeopardize "
                . "your account.\nReason: $userMessage";
        }
        $this->inbox()->createSystem($subject, $message);
        return $warning->add($reason, "$duration week" . plural($duration), $staff);
    }

    /**
     * Issue a warning for a comment or forum post
     */
    public function warnPost(
        BaseObject $post,
        int $weekDuration,
        \Gazelle\User $staffer,
        string $staffReason,
        string $userMessage
    ): void {
        if (!$weekDuration) {  // verbal warning
            $warned  = "Verbally warned";
            $this->inbox()->createSystem(
                "You have received a verbal warning",
                "You have received a verbal warning by [user]{$staffer->username()}[/user] for {$post->publicLocation()}.\n\n[quote]{$userMessage}[/quote]"
            );
        } else {
            $message = "for {$post->publicLocation()}.\n\n[quote]{$userMessage}[/quote]";
            $expiry  = $this->warn($weekDuration, "{$post->publicLocation()} - $staffReason", $staffer, $message);
            $warned  = "Warned until $expiry";
        }
        $this->addForumWarning("$warned by {$staffer->username()} for {$post->publicLocation()}\nReason: $staffReason")
            ->modify();
    }

    public function modifyOption(string $name, $value): static {
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
            self::$cache->delete_value('user_forum_warn_' . $this->id);
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

        $userInfo = [];
        if ($this->field('nav_list') !== null) {
            $userInfo['NavItems = ?'] = implode(',', $this->field('nav_list'));
            $this->setField('nav_list', json_encode($this->clearField('nav_list')));
        }
        if ($this->field('option_list') !== null) {
            $userInfo['SiteOptions = ?'] = serialize($this->clearField('option_list')); // remove field
        }

        $now = [];
        foreach (['BanDate', 'RatioWatchEnds'] as $field) {
            if ($this->nowField($field)) {
                // the value is the field, this came from $nowField
                $now[] = "{$this->clearField($field)} = now()";
            }
        }
        foreach (['AdminComment', 'BanDate', 'BanReason', 'PermittedForums', 'RestrictedForums', 'RatioWatchDownload', 'RatioWatchEnds'] as $field) {
            if ($this->field($field) !== null || $this->nullField($field)) {
                $userInfo["$field = ?"] = $this->clearField($field);
            }
        }
        if ($userInfo || $now) {
            $columns = implode(', ', [...array_keys($userInfo), ...$now]);
            self::$db->prepared_query("
                UPDATE users_info SET $columns WHERE UserID = ?
                ", ...[...array_values($userInfo), $this->id]
            );
            $changed = $changed || self::$db->affected_rows() === 1;
        }

        $leech = [];
        if ($this->field('leech_upload') !== null) {
            $leech['Uploaded = ?'] = $this->clearField('leech_upload');
        }
        if ($this->field('leech_download') !== null) {
            $leech['Downloaded = ?'] = $this->clearField('leech_download');
        }
        if ($leech) {
            $columns = implode(', ', array_keys($leech));
            self::$db->prepared_query("
                UPDATE users_leech_stats SET $columns WHERE UserID = ?
                ", ...[...array_values($leech), $this->id]
            );
            $changed = $changed || self::$db->affected_rows() === 1;
        }

        if ($this->field('lock-type') !== null) {
            $lockType = $this->clearField('lock-type');
            if (!$lockType) {
                self::$db->prepared_query("
                    DELETE FROM locked_accounts WHERE UserID = ?
                    ", $this->id
                );
            } else {
                self::$db->prepared_query("
                    INSERT INTO locked_accounts
                           (UserID, Type)
                    VALUES (?,      ?)
                    ON DUPLICATE KEY UPDATE Type = ?
                    ", $this->id, $lockType, $lockType
                );
            }
            $changed = $changed || self::$db->affected_rows() === 1;
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

    public function lockType(): ?int {
        return $this->info()['locked_account'];
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

    /**
     * Set a new user password. Requires calling modify() to persist new password.
     */
    public function updatePassword(#[\SensitiveParameter] string $pw, string $ipaddr, string $userAgent, bool $notify): static {
        $this->setField('PassHash', UserCreator::hashPassword($pw));
        self::$db->prepared_query('
            INSERT INTO users_history_passwords
                   (UserID, ChangerIP, useragent)
            VALUES (?,      ?,         ?)
            ', $this->id, $ipaddr, $userAgent
        );
        self::$cache->delete_value('user_pw_count_' . $this->id);
        if ($notify) {
            $this->notifyPasswordChange($ipaddr, $userAgent);
        }
        return $this;
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

    public function modifyAnnounceKeyHistory(string $oldPasskey, string $newPasskey, string $ipaddr): int {
        self::$db->prepared_query("
            INSERT INTO users_history_passkeys
                   (UserID, OldPassKey, NewPassKey, ChangerIP)
            VALUES (?,      ?,          ?,          ?)
            ", $this->id, $oldPasskey, $newPasskey, $ipaddr
        );
        $affected = self::$db->affected_rows();
        self::$cache->delete_value("user_passkey_count_{$this->id}");
        return $affected;
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

    public function supportCount(int $newClassId, int $levelClassId): int {
        return (int)self::$db->scalar("
            SELECT count(DISTINCT DisplayStaff)
            FROM permissions
            WHERE ID IN (?, ?)
            ", $newClassId, $levelClassId
        );
    }

    public function updateCatchup(): bool {
        return (new WitnessTable\UserReadForum())->witness($this);
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

    public function isUnconfirmed(): bool { return $this->info()['Enabled'] == UserStatus::unconfirmed->value; }
    public function isEnabled(): bool     { return $this->info()['Enabled'] == UserStatus::enabled->value; }
    public function isDisabled(): bool    { return $this->info()['Enabled'] == UserStatus::disabled->value; }
    public function isLocked(): bool      { return !is_null($this->info()['locked_account']); }
    public function isVisible(): bool     { return $this->info()['Visible'] == '1'; }
    public function isWarned(): bool      { return !is_null($this->warningExpiry()); }

    public function isStaff(): bool         { return $this->info()['isStaff']; }
    public function isFLS(): bool           { return $this->privilege()->isFLS(); }
    public function isInterviewer(): bool   { return $this->privilege()->isInterviewer(); }
    public function isRecruiter(): bool     { return $this->privilege()->isRecruiter(); }
    public function isStaffPMReader(): bool { return $this->isFLS() || $this->isStaff(); }

    public function warningExpiry(): ?string {
        return $this->info()['warning_expiry'];
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
        return $this->info()['collage_total'];
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

    public function trackerIPCount(): int {
        return (int)$this->getSingleValue('user_trackip_count', "
            SELECT count(DISTINCT IP) FROM xbt_snatched WHERE uid = ? AND IP != ''
        ");
    }

    public function inviter(): ?User {
        return $this->inviterId() ? new User($this->inviterId()) : null;
    }

    public function inviterId(): int {
        return (int)$this->info()['inviter_user_id'];
    }

    public function unusedInviteTotal(): int {
        return $this->disableInvites() ? 0 : $this->info()['Invites'];
    }

    public function passwordAge(): int {
        return time() - (int)$this->getSingleValue('user_pw_epoch', "
            SELECT unix_timestamp(coalesce(max(uhp.ChangeTime), um.created))
            FROM users_main um
            LEFT JOIN users_history_passwords uhp ON (uhp.UserID = um.ID)
            WHERE um.ID = ?
        ");
    }

    public function forumWarning(): ?string {
        $warning = $this->getSingleValue('user_forum_warn', "
            SELECT Comment FROM users_warnings_forums WHERE UserID = ?
        ");
        return $warning ?: null;
    }

    public function collagesCreated(): int {
        return (int)$this->getSingleValue('user_collage_create', "
            SELECT count(*) FROM collages WHERE Deleted = '0' AND UserID = ?
        ");
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
                ORDER BY t.created DESC
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
            $order[] = ['id' => $key . '_' . (int)(!!$checked), 'checked' => $checked, 'label' => $val];
        }
        return $order;
    }

    public function tokenCount(): int {
        return $this->info()['FLTokens'];
    }

    /**
     * Check if the viewer has an active freeleech token on this torrent
     */
    public function hasToken(TorrentAbstract $torrent): bool {
        if (!isset($this->tokenCache)) {
            $key = "users_tokens_" . $this->id;
            $tokenCache = self::$cache->get_value($key);
            if ($tokenCache === false) {
                $qid = self::$db->get_query_id();
                self::$db->prepared_query("
                    SELECT TorrentID FROM users_freeleeches WHERE Expired = 0 AND UserID = ?
                    ", $this->id
                );
                $tokenCache = array_fill_keys(self::$db->collect(0, false), true);
                self::$db->set_query_id($qid);
                self::$cache->cache_value($key, $tokenCache, 3600);
            }
            $this->tokenCache = $tokenCache;
        }
        return isset($this->tokenCache[$torrent->id()]);
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
        if (!strlen($this->profileInfo())) {
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
        $demotion = array_filter((new Manager\User())->demotionCriteria(), fn($v) => in_array($class, $v['From']));
        $criteria = end($demotion);

        $effectiveUpload = $this->uploadedSize() + $this->stats()->requestBountySize();
        if ($criteria) {
            $ratio = $criteria['Ratio'];
        } else {
            $ratio = $this->requiredRatio();
        }

        return [$ratio, $ratio == 0 ? $effectiveUpload : $effectiveUpload / $ratio - $this->downloadedSize()];
    }

    public function nextClass(Manager\User $manager): ?array {
        $criteria = $manager->promotionCriteria()[$this->primaryClass()] ?? null;
        if (!$criteria) {
            return null;
        }
        $upload   = $this->uploadedSize();
        $download = $this->downloadedSize();
        $bounty   = $this->stats()->requestVoteSize();
        $week     = $criteria['Weeks'];
        $goal = [
            'Upload' => [
                'current' => byte_format($upload + $bounty),
                'target'  => byte_format($criteria['MinUpload']),
                'percent' => ratio_percent(($upload + $bounty) / $criteria['MinUpload']),
            ],
            'Ratio' => [
                'current' => $download == 0 ? '∞' : number_format($upload / $download, 2),
                'target'  => number_format($criteria['MinRatio'], 2),
                'percent' => ratio_percent($download == 0 ? 1 : ($upload / $download) / $criteria['MinRatio']),
            ],
            'Time' => [
                'current' => $this->created(),
                'target'  => "$week week" . plural($week),
                'percent' => ratio_percent((time() - strtotime($this->created())) / ($criteria['Weeks'] * 7 * 86_400)),
            ],
        ];

        if ($criteria['MinUploads']) {
            $uploadTotal = $this->stats()->uploadTotal();
            $goal['Torrents'] = [
                'current' => number_format($uploadTotal),
                'target'  => $criteria['MinUploads'],
                'percent' => ratio_percent($uploadTotal / $criteria['MinUploads']),
            ];
        }
        if (isset($criteria['Extra'])) {
            foreach ($criteria['Extra'] as $req => $info) {
                $query = $info['Query'];
                $query = (str_starts_with($query, 'us.'))
                    ? "SELECT $query FROM user_summary us WHERE user_id = ?"
                    : str_replace('um.ID', '?', $query);
                $current = (int)self::$db->scalar($query, ...array_fill(0, substr_count($query, '?'), $this->id));
                if ($req == SITE_NAME . ' Upload') {
                    $goal[$req] = [
                        'current' => byte_format($current),
                        'target'  => byte_format($info['Count']),
                        'percent' => ratio_percent($current / $info['Count']),
                    ];
                } else {
                    $goal[$req] = [
                        'current' => number_format($current),
                        'target'  => $info['Count'],
                        'percent' => ratio_percent($current / $info['Count']),
                    ];
                }
            }
        }

        return [
            'class' => $manager->userclassName($criteria['To']),
            'goal'  => $goal,
        ];
    }

    /**
     * See whether a user is seeding a torrent. This method has no caching, but is
     * only expected to be called at the moment a user wants to download a torrent.
     */
    public function isSeeding(TorrentAbstract $torrent): bool {
        return (bool)self::$db->scalar("
            SELECT 1
            FROM xbt_files_users
            WHERE uid = ?
                AND fid = ?
            LIMIT 1;
            ", $this->id, $torrent->id()
        );
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

    public function createApiToken(string $name): string {
        while (true) {
            // prevent collisions with an existing token
            $token = base64_encode(random_bytes(87));
            try {
                self::$db->prepared_query("
                    INSERT INTO api_tokens
                           (user_id, name, token)
                    VALUES (?,       ?,    ?)
                    ", $this->id, $name, $token
                );
                return $token;
            } catch (\Gazelle\DB\MysqlDuplicateKeyException) {
            }
        }
    }

    public function apiTokenList(bool $revoked = false): array {
        self::$db->prepared_query("
            SELECT id, name, token, created
            FROM api_tokens
            WHERE user_id = ?
                AND revoked = ?
            ORDER BY created DESC
            ", $this->id, (int)$revoked
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function hasApiTokenByName(string $name): bool {
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
        return (bool)self::$db->scalar("
            SELECT 1
            FROM api_tokens
            WHERE revoked = 0
                AND user_id = ?
                AND token = ?
            ", $this->id, $token
        );
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
        return !$this->disableInvites() && $this->privilege()->effectiveClassLevel() >= MIN_INVITE_CLASS;
    }
}
