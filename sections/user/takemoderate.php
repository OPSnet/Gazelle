<?php

use Gazelle\Enum\UserStatus;

use Gazelle\Util\Mail;
use Gazelle\Util\Time;

authorize();

function enabledStatus(string $status): string {
    return match ($status) {
        '0' => 'Disabled',
        '1' => 'Enabled',
        default => $status,
    };
}

function revoked(bool $state): string {
    return $state ? 'revoked' : 'restored';
}

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$userMan = new Gazelle\Manager\User;
$user = $userMan->findById((int)$_POST['userid']);
if (is_null($user)) {
    header("Location: log.php?search=User+" . (int)$_POST['userid']);
    exit;
}
$userId = $user->id();
$ownProfile = $userId === $Viewer->id();

// Variables for database input
$class = (int)($_POST['Class'] ?? 0);
$title = trim($_POST['Title']);
$adminComment = trim($_POST['admincomment'] ?? '');
$visible = isset($_POST['Visible']) ? '1' : '0';
$unlimitedDownload = isset($_POST['unlimitedDownload']);
$invites = (int)$_POST['Invites'];
$slogan = trim($_POST['slogan']);
$changePassword = !empty($_POST['ChangePassword']);
$warned = isset($_POST['Warned']) ? 1 : 0;
$uploaded = $downloaded = $bonusPoints = null;
if (isset($_POST['Uploaded']) && isset($_POST['Downloaded'])) {
    $uploaded = ($_POST['Uploaded'] === '' ? 0 : $_POST['Uploaded']);
    if ($arithmetic = strpbrk($uploaded, '+-')) {
        $uploaded += max(-$uploaded, get_bytes($arithmetic));
    }
    $downloaded = ($_POST['Downloaded'] === '' ? 0 : $_POST['Downloaded']);
    if ($arithmetic = strpbrk($downloaded, '+-')) {
        $downloaded += max(-$downloaded, get_bytes($arithmetic));
    }
    if (!is_number($uploaded) || !is_number($downloaded)) {
        error(0);
    }
}
if (isset($_POST['BonusPoints'])) {
    $bonusPoints = (float)$_POST['BonusPoints'];
}
$Collages = (int)($_POST['Collages'] ?? 0);
$flTokens = (int)($_POST['FLTokens'] ?? 0);

$warnWeeks        = (int)($_POST['WarnLength'] ?? 0);
$extendWarning   = $_POST['ExtendWarning'] ?? '---';
$reduceWarning   = $_POST['ReduceWarning'] ?? '---';
$warnReason      = trim($_POST['WarnReason']);
$userReason      = trim($_POST['UserReason']);
$disableAvatar   = isset($_POST['DisableAvatar']);
$disableInvites  = isset($_POST['DisableInvites']);
$disablePosting  = isset($_POST['DisablePosting']);
$disablePoints   = isset($_POST['DisablePoints']);
$disableForums   = isset($_POST['DisableForums']);
$disableTagging  = isset($_POST['DisableTagging']);
$disableUpload   = isset($_POST['DisableUpload']);
$disableWiki     = isset($_POST['DisableWiki']);
$disablePM       = isset($_POST['DisablePM']);
$disableIRC      = isset($_POST['DisableIRC']);
$disableRequests = isset($_POST['DisableRequests']);
$disableLeech    = isset($_POST['DisableLeech']) ? 0 : 1;
$resetRatioWatch = $_POST['ResetRatioWatch'] ?? 0 ? 1 : 0;
$resetIPHistory  = $_POST['ResetIPHistory'] ?? 0;
$resetPasskey    = isset($_POST['ResetPasskey']) ? 1 : 0;
$resetAuthkey    = isset($_POST['ResetAuthkey']) ? 1 : 0;
$logoutSession   = isset($_POST['Logout']) ? 1 : 0;
$sendHackedMail  = isset($_POST['SendHackedMail']) ? 1 : 0;
if ($sendHackedMail && !empty(trim($_POST['HackedEmail']))) {
    $hackedEmail = trim($_POST['HackedEmail']);
} else {
    $sendHackedMail = false;
}
$mergeStatsFrom = trim($_POST['MergeStatsFrom']);
$reason = trim($_POST['Reason']);

$cur = $user->info();
if ($_POST['comment_hash'] != $cur['CommentHash']) {
    error("Somebody else has moderated this user since you loaded it. Please go back and refresh the page.");
}

if ($mergeStatsFrom && ($downloaded != $user->downloadedSize() || $uploaded != $user->uploadedSize())) {
    // Too make make-work code to deal with this unlikely eventuality
    error("Do not transfer buffer and edit upload/download in the same operation.");
}

$tracker = new Gazelle\Tracker;

// If we're deleting the user, we can ignore all the other crap
if ($_POST['UserStatus'] === 'delete' && $Viewer->permitted('users_delete_users')) {
    (new Gazelle\Log)->general("User account {$user->label()} was deleted by ".$Viewer->username());
    $tracker->update_tracker('remove_user', ['passkey' => $user->announceKey()]);
    $user->remove();
    header("Location: log.php?search=User+$userId");
    exit;
}

// User was not deleted. Perform other stuff.

$editSummary = [];
$trackerUserUpdates = ['passkey' => $user->announceKey()];

$lockType = (int)$_POST['LockType'];
if ($user->lockType() != $lockType) {
    // This is a pseudo-field that does not exist in the table,
    // the modify() method knows how to deal with it.
    $user->setUpdate('lock-type', $lockType);
    if (!$lockType) {
        $editSummary[] = "Account unlocked";
    } else {
        $editSummary[] = $user->isLocked()
            ? "Account lock type changed to $lockType"
            : "Account locked (type $lockType)";
    }
}

if (isset($_POST['ResetRatioWatch']) && $Viewer->permitted('users_edit_reset_keys')) {
    (new Gazelle\User\History($user))->resetRatioWatch();
    $editSummary[] = 'RatioWatch history reset';
}

if ($resetIPHistory && $Viewer->permitted('users_edit_reset_keys')) {
    (new Gazelle\User\History($user))->resetIp();
    $editSummary[] = 'IP history cleared';
}

if (isset($_POST['ResetEmailHistory']) && $Viewer->permitted('users_edit_reset_keys')) {
    (new Gazelle\User\History($user))->resetEmail($user->username() . '@' . SITE_HOST, $resetIPHistory ? '127.0.0.1' : $user->ipaddr());
    $editSummary[] = 'email history cleared';
}

if (isset($_POST['ResetSnatchList']) && $Viewer->permitted('users_edit_reset_keys')) {
    (new Gazelle\User\History($user))->resetSnatched();
    $editSummary[] = 'snatch list cleared';
}

if (isset($_POST['ResetDownloadList']) && $Viewer->permitted('users_edit_reset_keys')) {
    (new Gazelle\User\History($user))->resetDownloaded();
    $editSummary[] = 'download list cleared';
}

if ($logoutSession && $Viewer->permitted('users_logout')) {
    $editSummary[] = "logged out of all sessions (n=" . (new Gazelle\User\Session($user))->dropAll() . ")";
}

if ($visible != $user->isVisible() && $Viewer->permitted('users_make_invisible')) {
    $user->setUpdate('Visible', $visible ? '1' : '0');
    $trackerUserUpdates['visible'] = $visible;
    $editSummary[] = 'swarm visibility ' . ($visible ? 'on' : 'off');
}

// For the users_info update
$set = [];
$args = [];

if ($slogan != $user->slogan() && ($Viewer->permitted('admin_manage_fls') || $ownProfile)) {
    $user->setUpdate('slogan', $slogan);
    $set[] = "SupportFor = ?";
    $args[] = (string)$slogan;
    $editSummary[] = "First-Line Support status changed to \"$slogan\"";
}

$editRatio = $Viewer->permitted('users_edit_ratio') || ($Viewer->permitted('users_edit_own_ratio') && $ownProfile);
if ($flTokens != $user->tokenCount() && ($editRatio || $Viewer->permitted('admin_manage_user_fls'))) {
    $editSummary[] = "freeleech tokens changed from {$user->tokenCount()} to $flTokens";
}

$newBonusPoints = false;
if (!in_array($bonusPoints, [$user->bonusPointsTotal(), (float)($_POST['OldBonusPoints'])])
    && ($Viewer->permitted('users_edit_ratio') || ($Viewer->permitted('users_edit_own_ratio') && $ownProfile))) {
    $newBonusPoints = $bonusPoints;
    $editSummary[] = "bonus points changed from {$user->bonusPointsTotal()} to {$bonusPoints}";
}

if ($unlimitedDownload !== $user->hasUnlimitedDownload() && $Viewer->permitted('admin_rate_limit_manage')) {
    if ($user->toggleUnlimitedDownload($unlimitedDownload)) {
        $editSummary[] = "unlimited download " . strtolower(enabledStatus($unlimitedDownload ? '1' : '0'));
    }
}

if ($Collages != $user->paidPersonalCollages() && $Collages != (int)$_POST['OldCollages']
    && ($Viewer->permitted('users_edit_ratio') || ($Viewer->permitted('users_edit_own_ratio') && $ownProfile))
) {
    $user->setUpdate('collage_total', $Collages);
    $set[] = 'collages = ?';
    $args[] = $Collages;
    $EditSummary[] = "personal collages changed from {$user->paidPersonalCollages()} to {$Collages}";
}

if ($invites != $user->unusedInviteTotal() && $Viewer->permitted('users_edit_invites')) {
    $user->setUpdate('Invites', $invites);
    $editSummary[] = "number of invites changed from {$user->unusedInviteTotal()} to $invites";
}

$leechSet = [];
$leechArgs = [];
if ($editRatio) {
    if ($uploaded != $user->uploadedSize() && $uploaded != $_POST['OldUploaded']) {
        $leechSet[] = 'Uploaded = ?';
        $leechArgs[] = $uploaded;
        $editSummary[] = "uploaded changed from " . byte_format($user->uploadedSize())
            . ' to ' . byte_format($uploaded)
            . " (delta " . byte_format($uploaded - $user->uploadedSize()) . ")";
    }
    if ($downloaded != $user->downloadedSize() && $downloaded != $_POST['OldDownloaded']) {
        $leechSet[] = 'Downloaded = ?';
        $leechArgs[] = $downloaded;
        $editSummary[] = "downloaded changed from " . byte_format($user->downloadedSize())
            . ' to ' . byte_format($downloaded)
            . " (delta " . byte_format($downloaded - $user->downloadedSize()) . ")";
    }
}

if ($class) {
    $Classes = $userMan->classList();
    $newClass = $Classes[$class]['Level'];
    if ($newClass != $user->classLevel()
        && (
            ($newClass < $Viewer->classLevel() && $Viewer->permitted('users_promote_below'))
            || ($newClass <= $Viewer->classLevel() && $Viewer->permitted('users_promote_to'))
    )) {
        $user->setUpdate('PermissionID', $class);
        $editSummary[] = 'class changed to ' . $userMan->userclassName($class);

        if ($user->supportCount($class, $user->primaryClass()) === 2) {
            if ($newClass < $user->primaryClass()) {
                $slogan = null;
            }
            $Cache->delete_value('staff_ids');
        }
        $Cache->delete_value("donor_info_$userId");
    }
}

if ($Viewer->permitted('users_edit_usernames')) {
    $username = trim($_POST['Username']);
    if ($username !== $user->username()) {
        if (in_array($username, ['0', '1'])) {
            error('You cannot set a username of "0" or "1".');
        } elseif (strtolower($username) !== strtolower($user->username())) {
            $found = $userMan->findByUsername($username);
            if ($found) {
                error("Username already in use by $username");
            }
        }
        $user->setUpdate('Username', $username);
        $editSummary[] = "username changed from {$user->username()} to $username";
    }
}

if ($title != $user->title() && $Viewer->permitted('users_edit_titles')) {
    // Using the unescaped value for the test to avoid confusion
    if (mb_strlen($_POST['Title']) > 1024) {
        error("Custom titles have a maximum length of 1,024 characters.");
    } else {
        $user->setUpdate('Title', $title);
        $editSummary[] = "title changed to [code]{$title}[/code]";
    }
}

if ($Viewer->permitted('users_warn')) {
    $warning = new Gazelle\User\Warning($user);
    if ($warned == 0) {
        if ($user->isWarned()) {
            $warning->clear();
            $set[] = "Warned = ?";
            $args[] = null;
            $editSummary[] = 'warning removed';
        }
    } elseif (
        ($user->isWarned() && $warnWeeks)
        ||
        (!$user->isWarned() && ($extendWarning != '---' || $reduceWarning != '---'))
    ) {
        if ($reduceWarning) {
            $warning->clear();
        }
        $warning->create(reason: $warnReason, interval: "$warnWeeks week", warner: $Viewer);
        if (!$user->isWarned()) {
            $weeksChange = $warnWeeks;
            $duration = 'week' . plural($warnWeeks);
            $message = [
                'summary' => "warned for $warnWeeks $duration",
                'subject' => 'You have received a warning',
                'body'    => "You have been [url=wiki.php?action=article&amp;name=warnings]warned[/url] for $warnWeeks $duration",
            ];
        } else {
            $weeksChange = ($extendWarning != '---') ? $extendWarning : -$reduceWarning;
            $nrWeeks = (int)abs($weeksChange);
            $duration = 'week' . plural($nrWeeks);
            $action = $weeksChange > 0 ? 'extended' : 'reduced';
            $message = [
                'summary' => "warning $action $nrWeeks $duration",
                'subject' => "Your warning has been $action to $nrWeeks $duration",
                'body'    => "Your warning has been $action to $nrWeeks $duration",
            ];
        }
        $set[] = "Warned = now() + INTERVAL ? WEEK";
        $args[] = $weeksChange;
        $expiry = $user->endWarningDate($weeksChange);
        $message['body'] .= ", by [user]" . $Viewer->username() . "[/user]."
            . " The reason given was:\n[quote]{$warnReason}[/quote]. The warning will expire on $expiry."
            . "\n\nThis is an automated message. You may reply for more information if necessary.";
        $userMan->sendPM($userId, $Viewer->id(), $message['subject'], $message['body']);
        $editSummary[] = $message['summary'] . ", expiry: $expiry"
            . ($warnReason ? ", reason: \"$warnReason\"" : '');
    }
}

$secondaryClasses = array_filter(
    array_map('intval', $_POST['secondary_classes'] ?? [] ),
    fn($id) => $id > 0
);

if ($Viewer->permitted('users_give_donor')) {
    $donor = new Gazelle\User\Donor($user);
    $value = (float)trim($_POST['donation_value']);
    if ($value > 0.0) {
        $donor->donate(
            amount:   $value,
            xbtRate:  (new Gazelle\Manager\XBT)->latestRate('EUR'),
            currency: $_POST['donation_currency'],
            reason:   trim($_POST['donation_reason']),
            source:   'Add Points',
            who:      $Viewer,
        );
        // pretend the secondary_classes field was checked, otherwise the
        // class will be removed below, and we just added it!
        $secondaryClasses[] = DONOR;
    } else {
        // can add a donation or adjust points, not both
        $rankDelta   = (int)$_POST['donor_rank_delta'];
        $totalDelta  = (int)$_POST['total_donor_rank_delta'];
        if ($rankDelta || $totalDelta) {
            $donor->adjust(
                rankDelta:  $rankDelta,
                totalDelta: $totalDelta,
                reason:     trim($_POST['reason']),
                adjuster:   $Viewer,
            );
        }
    }
}

$removedClasses = [];
$addedClasses   = [];
if ($Viewer->permittedAny('users_promote_below', 'users_promote_to')) {
    $currentClasses = array_keys((new Gazelle\User\Privilege($user))->secondaryClassList());
    sort($currentClasses);
    sort($secondaryClasses);
    if ($currentClasses != $secondaryClasses) {
        $removedClasses = array_diff($currentClasses, $secondaryClasses);
        $addedClasses   = array_diff($secondaryClasses, $currentClasses);
        if (!empty($removedClasses)) {
            $names = array_map(fn ($c) => $userMan->userclassName($c), $removedClasses);
            $editSummary[] = 'secondary classes dropped: ' . implode(', ', $names);
        }
        if (!empty($addedClasses)) {
            $names = array_map(fn ($c) => $userMan->userclassName($c), $addedClasses);
            $editSummary[] = "secondary classes added: " . implode(', ', $names);
        }
    }
}

$fMan = new Gazelle\Manager\Forum;
$restricted = array_map('intval', array_unique(explode(',', trim($_POST['RestrictedForums']))));
sort($restricted);
$restrictedIds = [];
$restrictedNames = [];
foreach ($restricted as $forumId) {
    $forum = $fMan->findById($forumId);
    if (!is_null($forum)) {
        $restrictedIds[] = $forumId;
        $restrictedNames[] = $forum->name() . "($forumId)";
    }
}
$restrictedForums = implode(',', $restrictedIds);
if ($restrictedForums != $cur['RestrictedForums']) {
    $set[] = "RestrictedForums = ?";
    $args[] = $restrictedForums;
    $editSummary[] = "prohibited forum(s): " . ($restrictedForums == '' ? 'none' : implode(', ', $restrictedNames));
}

$permitted = array_map('intval', array_unique(explode(',', trim($_POST['PermittedForums']))));
sort($permitted);
$permittedIds = [];
$permittedNames = [];
foreach ($permitted as $forumId) {
    $forum = $fMan->findById($forumId);
    if (!is_null($forum)) {
        $permittedIds[] = $forumId;
        $permittedNames[] = $forum->name() . "($forumId)";
    }
}
$permittedForums = implode(',', $permittedIds);
if ($permittedForums != $cur['PermittedForums']) {
    $set[] = "PermittedForums = ?";
    $args[] = $permittedForums;
    $editSummary[] = "permitted forum(s): " . ($permittedForums == '' ? 'none' : implode(', ', $permittedNames));
}

$privChange = [];

if ($Viewer->permitted('users_disable_any')) {
    if ($disableLeech != $user->canLeech()) {
        $privChange[] = 'Your leeching privileges have been ' . revoked((bool)$disableLeech);
        $user->setUpdate('can_leech', $disableLeech ? 1 : 0);
        $trackerUserUpdates['can_leech'] = $disableLeech;
        $editSummary[] = "leeching status changed ("
            . enabledStatus($user->canLeech() ? '1' : '0')." &rarr; ".enabledStatus($disableLeech ? '1' : '0').")";
    }
    if ($disableInvites !== $user->disableInvites()) {
        $privChange[] = 'Your invite privileges have been ' . revoked($disableInvites);
        $editSummary[] = 'invites privileges ' . revoked($disableInvites);
        $user->toggleAttr('disable-invites', $disableInvites);
    }
    if ($disableAvatar !== $user->disableAvatar()) {
        $privChange[] = 'Your avatar privileges have been ' . revoked($disableAvatar);
        $editSummary[] = 'avatar privileges ' . revoked($disableAvatar);
        $user->toggleAttr('disable-avatar', $disableAvatar);
    }
    if ($disablePoints !== $user->disableBonusPoints()) {
        $privChange[] = 'Your bonus points acquisition has been ' . revoked($disablePoints);
        $editSummary[] = 'points privileges ' . revoked($disablePoints);
        $user->toggleAttr('disable-bonus-points', $disablePoints);
    }
    if ($disableTagging !== $user->disableTagging()) {
        $privChange[] = 'Your tagging privileges have been ' . revoked($disableTagging);
        $editSummary[] = 'tagging privileges ' . revoked($disableTagging);
        $user->toggleAttr('disable-tagging', $disableTagging);
    }
    if ($disableUpload !== $user->disableUpload()) {
        $privChange[] = 'Your upload privileges have been ' . revoked($disableUpload);
        $editSummary[] = 'upload privileges ' . revoked($disableUpload);
        $user->toggleAttr('disable-upload', $disableUpload);
    }
    if ($disableWiki !== $user->disableWiki()) {
        $privChange[] = 'Your site editing privileges have been ' . revoked($disableWiki);
        $editSummary[] = 'wiki privileges ' . revoked($disableWiki);
        $user->toggleAttr('disable-wiki', $disableWiki);
    }
    if ($disablePM !== $user->disablePm()) {
        $privChange[] = 'Your private messate (PM) privileges have been ' . revoked($disablePM);
        $editSummary[] = 'PM privileges ' . revoked($disablePM);
        $user->toggleAttr('disable-pm', $disablePM);
    }
    if ($disableRequests !== $user->disableRequests()) {
        $privChange[] = 'Your request privileges have been ' . revoked($disableRequests);
        $editSummary[] = 'request privileges ' . revoked($disableRequests);
        $user->toggleAttr('disable-requests', $disableRequests);
    }
}

if ($Viewer->permitted('users_disable_posts')) {
    if ($disablePosting !== $user->disablePosting()) {
        $privChange[] = 'Your forum posting privileges have been ' . revoked($disablePosting);
        $editSummary[] = 'posting privileges ' . revoked($disablePosting);
        $user->toggleAttr('disable-posting', $disablePosting);
    }

    if ($disableForums !== $user->disableForums()) {
        $privChange[] = 'Your forum access has been ' . revoked($disableForums);
        $editSummary[] = 'forums privileges ' . revoked($disableForums);
        $user->toggleAttr('disable-forums', $disableForums);
    }
}

if ($disableIRC !== $user->disableIRC()) {
    $privChange[] = 'Your IRC privileges have been ' . revoked($disableIRC);
    $editSummary[] = 'IRC privileges ' . revoked($disableIRC);
    $user->toggleAttr('disable-irc', $disableIRC);
}

if ($privChange && $userReason) {
    sort($privChange);
    $userMan->sendPM(
        $userId, 0,
        count($privChange) == 1 ? $privChange[0] : 'Multiple privileges have changed on your account',
        $Twig->render('user/pm-privilege.twig', [
            'privs'  => $privChange,
            'reason' => $userReason,
            'url'    => 'wiki.php?action=article&amp;id=5',
        ])
    );
    $editSummary[] = 'PM sent';
}

$userStatus = match($_POST['UserStatus']) {
    '1'     =>  UserStatus::enabled,
    '2'     =>  UserStatus::disabled,
    default => UserStatus::unconfirmed,
};
if ($userStatus != $user->userStatus() && $Viewer->permitted('users_disable_users')) {
    $enableStr = "account status {$user->userStatus()->label()} &rarr; {$userStatus->label()}";
    if ($userStatus == UserStatus::disabled) {
        $userMan->disableUserList($tracker, [$userId], "Disabled via moderation", Gazelle\Manager\User::DISABLE_MANUAL);
        $trackerUserUpdates = [];
    } elseif ($userStatus == UserStatus::enabled) {
        $Cache->increment('stats_user_count');
        $visibleTrIP = $visible && $user->ipaddr() != '127.0.0.1' ? '1' : '0';
        $tracker->update_tracker('add_user', ['id' => $userId, 'passkey' => $user->announceKey(), 'visible' => $visibleTrIP]);
        if (($user->downloadedSize() == 0) || ($user->uploadedSize() / $user->downloadedSize() >= $user->requiredRatio())) {
            $user->setUpdate('can_leech', 1);
            $canLeech = 1;
            $set[] = "RatioWatchEnds = ?";
            $args[] = null;
            $set[] = "RatioWatchDownload = ?";
            $args[] = '0';
        } else {
            $enableStr .= ' (Ratio: ' . ratio_html($user->uploadedSize(), $user->downloadedSize(), false) . ', RR: '.number_format($user->requiredRatio(), 2).')';
            if ($cur['RatioWatchEnds']) {
                $set[] = "RatioWatchEnds = now()";
                $set[] = "RatioWatchDownload = ?";
                $args[] = $user->downloadedSize();
                $canLeech = 0;
            }
            $trackerUserUpdates['can_leech'] = 0;
        }
        $set[] = "BanReason = ?";
        $args[] = '0';
    }
    $user->setUpdate('Enabled', $userStatus->value);
    $editSummary[] = $enableStr;
}

if ($Viewer->permitted('users_edit_reset_keys')) {
    if ($resetAuthkey == 1) {
        $authKey = randomString();
        $user->setUpdate('auth_key', $authKey);
        $set[] = "Authkey = ?";
        $args[] = $authKey;
        $editSummary[] = 'authkey reset';
    }
    if ($resetPasskey == 1) {
        $passkey = randomString();
        $user->modifyAnnounceKeyHistory($user->announceKey(), $passkey, '0.0.0.0');
        $user->setUpdate('torrent_pass', $passkey);
        $trackerUserUpdates['passkey'] = $passkey; // MUST come after the case for updating can_leech
        $tracker->update_tracker('change_passkey', ['oldpasskey' => $user->announceKey(), 'newpasskey' => $passkey]);
        $editSummary[] = 'passkey reset';
    }
}

if ($sendHackedMail && $Viewer->permitted('users_disable_any')) {
    (new Mail)->send($hackedEmail, 'Your ' . SITE_NAME . ' account',
        $Twig->render('email/hacked.twig')
    );
    $userMan->disableUserList($tracker, [$userId], "Disabled via hacked email", Gazelle\Manager\User::DISABLE_MANUAL);
    $editSummary[] = "hacked account email sent to $hackedEmail";
}

if ($mergeStatsFrom && $Viewer->permitted('users_edit_ratio')) {
    $stats = $user->mergeLeechStats($mergeStatsFrom, $Viewer->username());
    if ($stats) {
        $merge = new Gazelle\User($stats['userId']);
        $merge->flush();
        $leechSet[] = "Uploaded = Uploaded + ?";
        $leechArgs[] = $stats['up'];
        $leechSet[] = "Downloaded = Downloaded + ?";
        $leechArgs[] = $stats['down'];
        $editSummary[] = sprintf('leech stats (up: %s, down: %s, ratio: %s) merged from %s (%s) prior(up: %s, down: %s, ratio: %s)',
            byte_format($stats['up']), byte_format($stats['down']), ratio($stats['up'], $stats['down']),
            $merge->url(), $mergeStatsFrom,
            byte_format($user->uploadedSize()), byte_format($user->downloadedSize()), ratio($user->uploadedSize(), $user->downloadedSize())
        );
    }
}

if ($changePassword && $Viewer->permitted('users_edit_password')) {
    $editSummary[] = 'password reset';
}

if (!(count($set) || count($leechSet) || count($editSummary)) && $reason) {
    $editSummary[] = 'notes added';
}

// Because of the infinitely fucked up encoding/decoding of Gazelle, $adminComment !== $cur['admincomment']
// almost always evaluates to true, even if the user did not purposely change the field. This then means
// we do have a bug where if a mod changes something about a user AND changes the admin comment, we will lose
// that change, but until we never decode stuff coming out of the DB, not much can be done.

if (count($editSummary)) {
    $summary = implode(', ', $editSummary) . ' by ' . $Viewer->username();
    $set[] = "AdminComment = ?";
    $args[] = Time::sqlTime() . ' - ' . ucfirst($summary) . ($reason ? "\nReason: $reason" : '') . "\n\n$adminComment";
} elseif ($adminComment !== $cur['admincomment']) {
    $set[] = "AdminComment = ?";
    $args[] = $adminComment;
}

if ($set) {
    $args[] = $userId;
    Gazelle\DB::DB()->prepared_query("
        UPDATE users_info
        SET " .  implode(', ', $set) . "
        WHERE UserID = ?
        ", ...$args
    );
}

if ($leechSet) {
    $leechArgs[] = $userId;
    Gazelle\DB::DB()->prepared_query("
        UPDATE users_leech_stats
        SET " . implode(', ', $leechSet) . "
        WHERE UserID = ?
        ", ...$leechArgs
    );
}

if ($removedClasses) {
    $user->removeClasses($removedClasses);
}
if ($addedClasses) {
    $user->addClasses($addedClasses);
}

if ($changePassword && $Viewer->permitted('users_edit_password')) {
    $user->updatePassword($_POST['ChangePassword'], '127.0.0.1');
    (new \Gazelle\User\Session($user))->dropAll();
}

if ($newBonusPoints !== false) {
    (new Gazelle\User\Bonus($user))->setPoints($newBonusPoints);
}

if ($flTokens != $user->tokenCount()) {
    $user->updateTokens($flTokens);
}

if (count($trackerUserUpdates) > 1) {
    $tracker->update_tracker('update_user', $trackerUserUpdates);
}

if (!$user->dirty() && count($set) || count($leechSet)) {
    // users_main was not touched, force an update
    $user->setUpdate('username', $user->username());
}
$user->modify();

if (isset($_POST['invite_source_update'])) {
    $source = array_keys(array_filter($_POST, fn($x) => preg_match('/^source-\d+$/', $x), ARRAY_FILTER_USE_KEY));
    if ($source) {
        $ids = [];
        foreach ($source as $s) {
            $ids[] = ((int)explode('-', $s)[1]);
        }
        (new Gazelle\Manager\InviteSource)->modifyInviterConfiguration($user->id(), $ids);
        header("Location: tools.php?action=invite_source");
        exit;
    }
}

header('Location: ' . $user->location());
