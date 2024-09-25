<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Gazelle\Cache $Cache */
/** @phpstan-var \Twig\Environment $Twig */

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

$userMan = new Gazelle\Manager\User();
$user = $userMan->findById((int)$_POST['userid']);
if (is_null($user)) {
    header("Location: log.php?search=User+" . (int)$_POST['userid']);
    exit;
}
$userId = $user->id();
$ownProfile = $userId === $Viewer->id();

// Variables for database input
$class             = (int)($_POST['Class'] ?? 0);
$title             = trim($_POST['Title']);
$adminComment      = trim($_POST['admincomment'] ?? '');
$visible           = isset($_POST['Visible']) ? '1' : '0';
$unlimitedDownload = isset($_POST['unlimitedDownload']);
$invites           = (int)$_POST['Invites'];
$slogan            = trim($_POST['slogan']);
$changePassword    = !empty($_POST['ChangePassword']);
$uploaded          = $downloaded = $bonusPoints = null;

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
        error('Invalid upload/download amounts');
    }
}
if (isset($_POST['BonusPoints'])) {
    $bonusPoints = (float)$_POST['BonusPoints'];
}
$Collages = (int)($_POST['Collages'] ?? 0);
$flTokens = (int)($_POST['FLTokens'] ?? 0);

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
$reason         = trim($_POST['Reason']);

$cur = $user->info();
if ($_POST['comment_hash'] != $cur['CommentHash']) {
    error("Somebody else has moderated this user since you loaded it. Please go back and refresh the page.");
}
$cur['PermittedForums']  = $user->privilege()->permittedUserForums();
$cur['RestrictedForums'] = $user->privilege()->forbiddenUserForums();

if ($mergeStatsFrom && ($downloaded != $user->downloadedSize() || $uploaded != $user->uploadedSize())) {
    // Too make make-work code to deal with this unlikely eventuality
    error("Do not transfer buffer and edit upload/download in the same operation.");
}

$tracker = new Gazelle\Tracker();
$needTrackerAdd     = false;
$needTrackerRefresh = false;

// If we're deleting the user, we can ignore all the other crap
if ($_POST['UserStatus'] === 'delete' && $Viewer->permitted('users_delete_users')) {
    (new Gazelle\Log())->general("User account {$user->label()} was deleted by " . $Viewer->username());
    $tracker->removeUser($user);
    $user->remove();
    header("Location: log.php?search=User+$userId");
    exit;
}

// User was not deleted. Perform other stuff.

$editSummary = [];

$lockType = (int)$_POST['LockType'];
if ($user->lockType() != $lockType) {
    // This is a pseudo-field that does not exist in the table,
    // the modify() method knows how to deal with it.
    $user->setField('lock-type', $lockType);
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
    $user->setField('Visible', $visible ? '1' : '0');
    $needTrackerRefresh = true;
    $editSummary[] = 'swarm visibility ' . ($visible ? 'on' : 'off');
}

if ($slogan != $user->slogan() && ($Viewer->permitted('admin_manage_fls') || $ownProfile)) {
    $user->setField('slogan', $slogan);
    $editSummary[] = "First-Line Support status changed to \"$slogan\"";
}

$editRatio = $Viewer->permitted('users_edit_ratio') || ($Viewer->permitted('users_edit_own_ratio') && $ownProfile);
if ($flTokens != $user->tokenCount() && ($editRatio || $Viewer->permitted('admin_manage_user_fls'))) {
    $editSummary[] = "freeleech tokens changed from {$user->tokenCount()} to $flTokens";
}

$newBonusPoints = false;
if (
    !in_array($bonusPoints, [$user->bonusPointsTotal(), (float)($_POST['OldBonusPoints'])])
    && ($Viewer->permitted('users_edit_ratio') || ($Viewer->permitted('users_edit_own_ratio') && $ownProfile))
) {
    $newBonusPoints = $bonusPoints;
    $editSummary[] = "bonus points changed from {$user->bonusPointsTotal()} to {$bonusPoints}";
}

if ($unlimitedDownload !== $user->hasUnlimitedDownload() && $Viewer->permitted('admin_rate_limit_manage')) {
    if ($user->toggleUnlimitedDownload($unlimitedDownload)) {
        $editSummary[] = "unlimited download " . strtolower(enabledStatus($unlimitedDownload ? '1' : '0'));
    }
}

if (
    $Collages != $user->paidPersonalCollages() && $Collages != (int)$_POST['OldCollages']
    && ($Viewer->permitted('users_edit_ratio') || ($Viewer->permitted('users_edit_own_ratio') && $ownProfile))
) {
    $user->setField('collage_total', $Collages);
    $user->ordinal()->set('personal-collage', $Collages);
    $EditSummary[] = "personal collages changed from {$user->paidPersonalCollages()} to {$Collages}";
}

if ($invites != $user->unusedInviteTotal() && $Viewer->permitted('users_edit_invites')) {
    $user->setField('Invites', $invites);
    $editSummary[] = "number of invites changed from {$user->unusedInviteTotal()} to $invites";
}

if ($editRatio) {
    if ($uploaded != $user->uploadedSize() && $uploaded != $_POST['OldUploaded']) {
        $user->setField('leech_upload', $uploaded);
        $editSummary[] = "uploaded changed from " . byte_format($user->uploadedSize())
            . ' to ' . byte_format($uploaded)
            . " (delta " . byte_format($uploaded - $user->uploadedSize()) . ")";
    }
    if ($downloaded != $user->downloadedSize() && $downloaded != $_POST['OldDownloaded']) {
        $user->setField('leech_download', $downloaded);
        $editSummary[] = "downloaded changed from " . byte_format($user->downloadedSize())
            . ' to ' . byte_format($downloaded)
            . " (delta " . byte_format($downloaded - $user->downloadedSize()) . ")";
    }
}

if ($class) {
    $Classes = $userMan->classList();
    $newClass = $Classes[$class]['Level'];
    if (
        $newClass != $user->classLevel()
        && (
            ($newClass < $Viewer->classLevel() && $Viewer->permitted('users_promote_below'))
            || ($newClass <= $Viewer->classLevel() && $Viewer->permitted('users_promote_to'))
        )
    ) {
        $user->setField('PermissionID', $class);
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
        $user->setField('Username', $username);
        $editSummary[] = "username changed from {$user->username()} to $username";
    }
}

if ($title != $user->title() && $Viewer->permitted('users_edit_titles')) {
    // Using the unescaped value for the test to avoid confusion
    if (mb_strlen($_POST['Title']) > 1024) {
        error("Custom titles have a maximum length of 1,024 characters.");
    } else {
        $user->setField('Title', $title);
        $editSummary[] = "title changed to [code]{$title}[/code]";
    }
}

if ($Viewer->permitted('users_warn')) {
    $weeks   = (int)($_POST['WarnLength'] ?? 0);
    $extend  = (int)($_POST['ExtendWarning'] ?? 0);
    $reduce  = (int)($_POST['ReduceWarning'] ?? 0);
    $warning = new Gazelle\User\Warning($user);
    if (!isset($_POST['Warned'])) {
        if ($user->isWarned()) {
            $warning->clear();
            $editSummary[] = 'warning removed';
        }
    } elseif ($reduce) {
        $warning->clear(); // replace the current warning with the new duration
        $duration = 'week' . plural($reduce);
        $expiry = $warning->add($reason, "$reduce $duration", $Viewer);
        $userMessage = trim($_POST['WarnReason'] ?? '');
        $body = "Your warning has been reduced to $reduce $duration, set to expire at $expiry, by [user]{$Viewer->username()}[/user].";
        if ($userMessage) {
            $body .= " Reason:\n[quote]{$userMessage}[/quote].";
        }
        $user->inbox()->createSystem(
            "Your warning has been reduced to $reduce $duration",
            $body,
        );
    } elseif ($weeks || $extend) {
        $staffReason = $reason ?: ($extend ? 'warning extension' : 'no reason');
        $user->warn($extend ?: $weeks, $staffReason, $Viewer, $_POST['WarnReason'] ?? 'none given');
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
            xbtRate:  (new Gazelle\Manager\XBT())->latestRate('EUR'),
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
    $currentClasses = array_keys($user->privilege()->secondaryClassList());
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

$forumMan = new Gazelle\Manager\Forum();
$restricted = array_map('intval', array_unique(explode(',', trim($_POST['RestrictedForums']))));
sort($restricted);
$restrictedIds = [];
$restrictedNames = [];
foreach ($restricted as $forumId) {
    $forum = $forumMan->findById($forumId);
    if (!is_null($forum) && !isset($restrictedIds[$forumId])) {
        $restrictedIds[$forumId] = true;
        $restrictedNames[] = "{$forum->name()} ($forumId)";
    }
}

$permitted = array_map('intval', array_unique(explode(',', trim($_POST['PermittedForums']))));
sort($permitted);
$permittedIds = [];
$permittedNames = [];
foreach ($permitted as $forumId) {
    $forum = $forumMan->findById($forumId);
    if (!is_null($forum) && !isset($permittedIds[$forumId])) {
        $permittedIds[$forumId] = true;
        $permittedNames[] = "{$forum->name()} ($forumId)";
    }
}

$privChange = [];
if ($Viewer->permitted('users_disable_any')) {
    if ($disableLeech != $user->canLeech()) {
        $privChange[] = 'Your leeching privileges have been ' . revoked((bool)$disableLeech);
        $user->setField('can_leech', $disableLeech ? 1 : 0);
        $needTrackerRefresh = true;
        $editSummary[] = "leeching status changed ("
            . enabledStatus($user->canLeech() ? '1' : '0') . " → " . enabledStatus($disableLeech ? '1' : '0') . ")";
    }
    if ($disableInvites !== $user->disableInvites()) {
        $privChange[] = 'Your invite privileges have been ' . revoked($disableInvites);
        $editSummary[] = 'invites privileges ' . revoked($disableInvites);
        $user->toggleAttr('disable-invites', $disableInvites);
        if ($disableInvites) {
            unset($permittedIds[INVITATION_FORUM_ID]);
            $restrictedIds[INVITATION_FORUM_ID] = true;
            $restrictedNames[] = $forumMan->findById(INVITATION_FORUM_ID)->name() . " (" . INVITATION_FORUM_ID . ")";
        }
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

$permittedForums = implode(',', array_keys($permittedIds));
if ($permittedForums != $cur['PermittedForums']) {
    $user->setField('PermittedForums', $permittedForums);
    $editSummary[] = "permitted forum(s): " . ($permittedForums == '' ? 'none' : implode(', ', $permittedNames));
}
$restrictedForums = implode(',', array_keys($restrictedIds));
if ($restrictedForums != $cur['RestrictedForums']) {
    $user->setField('RestrictedForums', $restrictedForums);
    $editSummary[] = "prohibited forum(s): " . ($restrictedForums == '' ? 'none' : implode(', ', $restrictedNames));
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
    $user->inbox()->createSystem(
        count($privChange) == 1 ? $privChange[0] : 'Multiple privileges have changed on your account',
        $Twig->render('user/pm-privilege.twig', [
            'privs'  => $privChange,
            'reason' => $userReason,
            'url'    => 'wiki.php?action=article&amp;id=5',
        ])
    );
    $editSummary[] = 'PM sent';
}

$userStatus = match ($_POST['UserStatus']) {
    '1'     => UserStatus::enabled,
    '2'     => UserStatus::disabled,
    default => UserStatus::unconfirmed,
};
if ($userStatus != $user->userStatus() && $Viewer->permitted('users_disable_users')) {
    $enableStr = "account status {$user->userStatus()->label()} → {$userStatus->label()}";
    if ($userStatus == UserStatus::disabled) {
        $userMan->disableUserList($tracker, [$userId], "Disabled via moderation", Gazelle\Manager\User::DISABLE_MANUAL);
        $needTrackerRefresh = false;
    } elseif ($userStatus == UserStatus::enabled) {
        $needTrackerAdd = true;
        if (($user->downloadedSize() == 0) || ($user->uploadedSize() / $user->downloadedSize() >= $user->requiredRatio())) {
            $user->setField('can_leech', 1)
                ->setField('RatioWatchEnds', null)
                ->setField('RatioWatchDownload', 0);
        } else {
            $enableStr .= ' (Ratio: ' . ratio_html($user->uploadedSize(), $user->downloadedSize(), false) . ', RR: ' . number_format($user->requiredRatio(), 2) . ')';
            if ($cur['RatioWatchEnds']) {
                $user->setField('can_leech', 1)
                    ->setField('RatioWatchDownload', $user->downloadedSize())
                    ->setFieldNow('RatioWatchEnds');
            }
        }
        $user->setField('BanReason', '0');
    }
    $user->setField('Enabled', $userStatus->value);
    $editSummary[] = $enableStr;
}

if ($Viewer->permitted('users_edit_reset_keys')) {
    if ($resetAuthkey == 1) {
        $user->setField('auth_key', authKey());
        $editSummary[] = 'authkey reset';
    }
    if ($resetPasskey == 1) {
        $passkey = randomString();
        $user->modifyAnnounceKeyHistory($user->announceKey(), $passkey);
        $user->setField('torrent_pass', $passkey);
        $tracker->modifyPasskey(old: $user->announceKey(), new: $passkey);
        $editSummary[] = 'passkey reset';
    }
}

if ($sendHackedMail && $Viewer->permitted('users_disable_any')) {
    (new Mail())->send($hackedEmail, SITE_NAME . ' account - suspicious activity',
        $Twig->render('email/hacked.twig', [
            'user' => $user
        ])
    );
    $userMan->disableUserList($tracker, [$userId], "Disabled via hacked email", Gazelle\Manager\User::DISABLE_MANUAL);
    $editSummary[] = "hacked account email sent to $hackedEmail";
}

if ($mergeStatsFrom && $Viewer->permitted('users_edit_ratio')) {
    $stats = $user->mergeLeechStats($mergeStatsFrom, $Viewer->username());
    if ($stats) {
        $merge = new Gazelle\User($stats['userId']);
        $merge->flush();
        $user->setField('leech_uploaded', $user->uploadedSize() + $stats['up'])
            ->setField('leech_downloaded', $user->downloadedSize() + $stats['down']);
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

if (!(count($editSummary)) && $reason) {
    $editSummary[] = 'notes added';
}

// Because of the infinitely fucked up encoding/decoding of Gazelle, $adminComment !== $cur['admincomment']
// almost always evaluates to true, even if the user did not purposely change the field. This then means
// we do have a bug where if a mod changes something about a user AND changes the admin comment, we will lose
// that change, but until we never decode stuff coming out of the DB, not much can be done.

if ($editSummary) {
    $summary = implode(', ', $editSummary) . ' by ' . $Viewer->username();
    $user->setField(
        'AdminComment',
        Time::sqlTime() . ' - ' . ucfirst($summary) . ($reason ? "\nReason: $reason" : '') . "\n\n$adminComment"
    );
} elseif ($adminComment !== $cur['admincomment']) {
    $user->setField('AdminComment', $adminComment);
}

if ($removedClasses) {
    $user->removeClasses($removedClasses);
}
if ($addedClasses) {
    $user->addClasses($addedClasses);
}

if ($changePassword && $Viewer->permitted('users_edit_password')) {
    $user->updatePassword($_POST['ChangePassword'], false);
    (new \Gazelle\User\Session($user))->dropAll();
}

if ($newBonusPoints !== false) {
    (new Gazelle\User\Bonus($user))->setPoints($newBonusPoints);
}

if ($flTokens != $user->tokenCount()) {
    $user->updateTokens($flTokens);
}

$user->modify();
$user->flush();
if ($needTrackerAdd) {
    $tracker->addUser($user);
} elseif ($needTrackerRefresh) {
    $tracker->refreshUser($user);
}
if ($Viewer->permitted('admin_tracker')) {
    $tracker->traceUser($user, isset($_POST['tracker-trace']));
}

if (isset($_POST['invite_source_update'])) {
    $idList = array_key_extract_suffix('source-', $_POST);
    if ($idList) {
        (new Gazelle\Manager\InviteSource())->modifyInviterConfiguration($user, $idList);
        header("Location: tools.php?action=invite_source");
        exit;
    }
}

header('Location: ' . $user->location());
