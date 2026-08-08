<?php

/**
 * phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Gazelle\Cache $Cache
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

use Gazelle\Enum\UserAuditEvent;
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
    Error403::error();
}

$userMan = new Manager\User();
$user = $userMan->findById((int)$_POST['userid']);
if (is_null($user)) {
    header("Location: log.php?search=User+" . (int)$_POST['userid']);
    exit;
}
if ($_POST['checkpoint'] != $user->checkpoint()) {
    Error400::error(
        "Somebody else has moderated this user since you loaded it. Please go back and refresh the page."
    );
}

$ownProfile = $user->id === $Viewer->id;

// Variables for database input
$title          = trim($_POST['Title']);
$adminComment   = trim($_POST['admincomment'] ?? '');
$visible        = isset($_POST['Visible']) ? '1' : '0';
$invites        = (int)$_POST['Invites'];
$changePassword = $_POST['ChangePassword'] !== '';
$uploaded       = 0;
$downloaded     = 0;
$bonusPoints    = null;

if (isset($_POST['Uploaded']) && isset($_POST['Downloaded'])) {
    $uploaded   = max(0, byte_arithmetic($_POST['Uploaded']));
    $downloaded = max(0, byte_arithmetic($_POST['Downloaded']));
}
if (isset($_POST['BonusPoints'])) {
    $bonusPoints = (float)$_POST['BonusPoints'];
}
$collageTotal = (int)($_POST['Collages'] ?? 0);
$flTokens     = (int)($_POST['FLTokens'] ?? 0);

$userReason      = trim($_POST['UserReason']);
$disableAvatar   = isset($_POST['DisableAvatar']);
$disableForums   = isset($_POST['DisableForums']);
$disableInvites  = isset($_POST['DisableInvites']);
$disableIRC      = isset($_POST['DisableIRC']);
$disableLeech    = !isset($_POST['DisableLeech']);
$disablePM       = isset($_POST['DisablePM']);
$disablePoints   = isset($_POST['DisablePoints']);
$disablePosting  = isset($_POST['DisablePosting']);
$disableRequests = isset($_POST['DisableRequests']);
$disableTagging  = isset($_POST['DisableTagging']);
$disableUpload   = isset($_POST['DisableUpload']);
$disableWiki     = isset($_POST['DisableWiki']);
$logoutSession   = isset($_POST['Logout']);
$resetRatioWatch = isset($_POST['ResetRatioWatch']);
$resetIPHistory  = isset($_POST['ResetIPHistory']);
$resetPasskey    = isset($_POST['ResetPasskey']);
$resetAuthkey    = isset($_POST['ResetAuthkey']);
if (isset($_POST['SendHackedMail'])) {
    $hackedEmail = trim($_POST['HackedEmail']);
    if ($hackedEmail === '') {
        unset($hackedEmail);
    }
}
$mergeStatsFrom = trim($_POST['MergeStatsFrom']);
$reason         = trim($_POST['Reason']);

$cur = $user->info();

if ($mergeStatsFrom && $Viewer->permitted('users_edit_ratio')) {
    if ($downloaded != $user->downloadedSize() || $uploaded != $user->uploadedSize()) {
        // Too make make-work code to deal with this unlikely eventuality
        Error400::error("Do not transfer buffer and edit upload/download in the same operation.");
    }
    $mergeFrom = $userMan->find($mergeStatsFrom);
    if (is_null($mergeFrom)) {
        Error403::error("Cannot merge from $mergeStatsFrom, user not found");
    }
}

$tracker            = new Tracker();
$needTrackerAdd     = false;
$needTrackerRefresh = false;

// If we're deleting the user, we can ignore all the other crap
if ($_POST['UserStatus'] === 'delete' && $Viewer->permitted('users_delete_users')) {
    $tracker->logger()->general(
        "User account {$user->label()} was deleted by {$Viewer->username()}"
    );
    $tracker->removeUser($user);
    $user->remove();
    header("Location: log.php?search=User+{$user->id}");
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
    $user->history()->resetRatioWatch();
    $editSummary[] = 'RatioWatch history reset';
}

if ($resetIPHistory && $Viewer->permitted('users_edit_reset_keys')) {
    $user->history()->resetIp();
    $editSummary[] = 'IP history cleared';
}

if (isset($_POST['ResetEmailHistory']) && $Viewer->permitted('users_edit_reset_keys')) {
    $user->history()->resetEmail($user->username() . '@' . SITE_HOST, $resetIPHistory ? '127.0.0.1' : $user->ipaddr());
    $editSummary[] = 'email history cleared';
}

if (isset($_POST['ResetSnatchList']) && $Viewer->permitted('users_edit_reset_keys')) {
    $user->history()->resetSnatched();
    $editSummary[] = 'snatch list cleared';
}

if (isset($_POST['ResetDownloadList']) && $Viewer->permitted('users_edit_reset_keys')) {
    $user->history()->resetDownloaded();
    $editSummary[] = 'download list cleared';
}

if ($logoutSession && $Viewer->permitted('users_logout')) {
    $editSummary[] = "logged out of all sessions (n=" . new User\Session($user)->dropAll() . ")";
}

if ($visible != $user->isVisible() && $Viewer->permitted('users_make_invisible')) {
    $user->setField('Visible', $visible ? '1' : '0');
    $needTrackerRefresh = true;
    $editSummary[] = 'swarm visibility ' . ($visible ? 'on' : 'off');
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

foreach (
    [
        'admin-error-reporting' => isset($_POST['attr-admin-error-reporting']),
        'audit-trail-hidden'    => isset($_POST['attr-audit-trail-hidden']),
        'bypass-bonus-scale'    => isset($_POST['attr-bypass-bonus-scale']),
        'download-as-text'      => isset($_POST['attr-download-as-text']),
        'feature-file-count'    => isset($_POST['attr-feature-file-count']),
        'feature-seedbox'       => isset($_POST['attr-feature-seedbox']),
        'inactive-warning-sent' => isset($_POST['attr-inactive-warning-sent']),
        'no-fl-gifts'           => isset($_POST['attr-no-fl-gifts']),
        'unlimited-download'    => isset($_POST['attr-unlimited-download']),
        'wiki-edit-readable'    => isset($_POST['attr-wiki-edit-readable']),
        'enforce-mfa'           => isset($_POST['attr-enforce-mfa']),
    ] as $attr => $state
) {
    if ($state !== $user->hasAttr($attr) && $user->toggleAttr($attr, $state)) {
        $editSummary[] = str_replace('-', ' ', $attr) . " "
            . ($state ? 'set' : 'reset');
    }
}

if (
    $collageTotal != $user->paidPersonalCollages() && $collageTotal != (int)$_POST['OldCollages']
    && ($Viewer->permitted('users_edit_ratio') || ($Viewer->permitted('users_edit_own_ratio') && $ownProfile))
) {
    $user->ordinal()->set('personal-collage', $collageTotal);
    $editSummary[] = "personal collages changed from {$user->paidPersonalCollages()} to {$collageTotal}";
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

$userclass = (int)($_POST['Class'] ?? 0);
if ($userclass) {
    $userclassLevel = $userMan->classList()[$userclass]['Level'];
    if ($userclassLevel !== $user->userclassLevel() && $userclassLevel <= $Viewer->userclassLevel()) {
        $user->setField('PermissionID', $userclass);
        $editSummary[] = "class changed to {$userMan->userclassName($userclass)}";
        $Cache->delete_value("donor_info_{$user->id}");
    }
} elseif (isset($_POST['slogan'])) {
    $slogan = trim($_POST['slogan']);
    if ($slogan !== $user->slogan() && ($Viewer->permitted('admin_manage_fls') || $ownProfile)) {
        $editSummary[] = "First-Line Support status changed to “{$slogan}”";
        $user->setField('slogan', $slogan === '' ? null : $slogan);
    }
}

if ($Viewer->permitted('users_edit_usernames')) {
    $username = trim($_POST['Username']);
    if ($username !== $user->username()) {
        if (in_array($username, ['0', '1'])) {
            Error400::error('You cannot set a username of "0" or "1".');
        } elseif (strtolower($username) !== strtolower($user->username())) {
            if ($userMan->findByUsername($username)) {
                Error400::error("Username already in use by $username");
            }
        }
        $user->setField('Username', $username);
        $editSummary[] = "username changed from {$user->username()} to $username";
    }
}

if ($title != $user->title() && $Viewer->permitted('users_edit_titles')) {
    // Using the unescaped value for the test to avoid confusion
    if (mb_strlen($_POST['Title']) > 1024) {
        Error400::error("Custom titles have a maximum length of 1,024 characters.");
    } else {
        $user->setField('Title', $title);
        $editSummary[] = "title changed to [code]{$title}[/code]";
    }
}

if ($Viewer->permitted('users_warn')) {
    $weeks  = (int)($_POST['WarnLength'] ?? 0);
    $extend = (int)($_POST['ExtendWarning'] ?? 0);
    $reduce = (int)($_POST['ReduceWarning'] ?? 0);
    if (!isset($_POST['Warned'])) {
        if ($user->warning()->isWarned()) {
            $user->warning()->clear();
            $editSummary[] = 'warning removed';
        }
    } elseif ($reduce) {
        $user->warning()->clear(); // replace the current warning with the new duration
        $duration = 'week' . plural($reduce);
        $expiry = $user->warning()->add($reason, "$reduce $duration", $Viewer);
        $userMessage = trim($_POST['WarnReason'] ?? '');
        $body = "Your warning has been reduced to $reduce $duration, set to expire at {$expiry->upper->format('Y-m-d H:i')}, by [user]{$Viewer->username()}[/user].";
        if ($userMessage) {
            $body .= " Reason:\n[quote]{$userMessage}[/quote].";
        }
        $user->inbox()->createSystem(
            "Your warning has been reduced to $reduce $duration",
            $body,
        );
    } elseif ($weeks || $extend) {
        $staffReason = $reason ?: ($extend ? 'warning extension' : 'no reason');
        $weeks = $extend ?: $weeks;
        $user->warning()->warn($weeks, $staffReason, $Viewer, $_POST['WarnReason'] ?? 'none given');
        $duration = 'week' . plural($weeks);
    }
}

$secondaryClasses = array_filter(
    array_map(intval(...), $_POST['secondary_classes'] ?? [] ),
    fn($id) => $id > 0
);

if ($Viewer->permitted('users_give_donor')) {
    $donor = new User\Donor($user);
    $value = (float)trim($_POST['donation_value']);
    if ($value > 0.0) {
        $donor->donate(
            amount:   $value,
            xbtRate:  new Manager\XBT()->latestRate('EUR'),
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

$forumMan = new Manager\Forum();
$restricted = $_POST['RestrictedForums']
    |> trim(...)
    |> (fn ($id) => explode(',', $id))
    |> array_unique(...)
    |> (fn ($id) => array_map(intval(...), $id));
sort($restricted);
$restrictedIds = [];
$restrictedNames = [];
foreach ($restricted as $forumId) {
    $forum = $forumMan->findById($forumId);
    if ($forum) {
        $restrictedIds[$forumId] = true;
        $restrictedNames[] = "{$forum->name()} ($forumId)";
    }
}

$permitted = $_POST['PermittedForums']
    |> trim(...)
    |> (fn ($id) => explode(',', $id))
    |> array_unique(...)
    |> (fn ($id) => array_map(intval(...), $id));
sort($permitted);
$permittedIds = [];
$permittedNames = [];
foreach ($permitted as $forumId) {
    $forum = $forumMan->findById($forumId);
    if ($forum) {
        $permittedIds[$forumId]   = true;
        $permittedNames[$forumId] = "{$forum->name()} ($forumId)";
    }
}

$removedClasses = [];
$addedClasses   = [];
$currentClasses = array_keys($user->privilege()->secondaryClassList());
sort($currentClasses);
sort($secondaryClasses);
if ($currentClasses !== $secondaryClasses) {
    $removedClasses = array_diff($currentClasses, $secondaryClasses);
    $addedClasses   = array_diff($secondaryClasses, $currentClasses);
    if ($removedClasses !== []) {
        $names = array_map($userMan->userclassName(...), $removedClasses);
        $editSummary[] = 'secondary classes dropped: ' . implode(', ', $names);
        // revoke access to any associated forums
        $removedForumIdList = new Manager\Privilege()->secondaryForumIdList($removedClasses);
        $permittedIds = array_filter(
            $permittedIds,
            fn ($id) => !in_array($id, $removedForumIdList),
            ARRAY_FILTER_USE_KEY
        );
        $permittedNames = array_filter(
            $permittedNames,
            fn ($id) => !in_array($id, $removedForumIdList),
            ARRAY_FILTER_USE_KEY
        );
    }
    if ($addedClasses !== []) {
        $names = array_map($userMan->userclassName(...), $addedClasses);
        $editSummary[] = "secondary classes added: " . implode(', ', $names);
    }
}

if (isset($removedClasses[FLS_TEAM])) {
    $user->setField('slogan', null);
    $Cache->delete_value('staff_ids');
} else {
    $slogan = isset($_POST['slogan']) ? trim($_POST['slogan']) : null;
    if ($slogan !== $user->slogan() && ($Viewer->permitted('admin_manage_fls') || $ownProfile)) {
        $user->setField('slogan', $slogan);
        if ($user->isFLS()) {
            $editSummary[] = "First-Line Support status changed to “{$slogan}”";
        }
    }
}

$privChange = [];
if ($Viewer->permitted('users_disable_any')) {
    if ($disableLeech != $user->canLeech()) {
        $privChange[] = 'Your leeching privileges have been ' . revoked($disableLeech);
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
if ($permittedForums != $user->privilege()->permittedUserForums()) {
    $user->setField('PermittedForums', $permittedForums);
    $editSummary[] = "permitted forum(s): " . ($permittedForums == '' ? 'none' : implode(', ', $permittedNames));
}
$restrictedForums = implode(',', array_keys($restrictedIds));
if ($restrictedForums != $user->privilege()->forbiddenUserForums()) {
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
        count($privChange) === 1 ? $privChange[0] : 'Multiple privileges have changed on your account',
        $Twig->render('user/pm-privilege.twig', [
            'privs'  => $privChange,
            'reason' => $userReason,
            'url'    => 'wiki.php?action=article&amp;id=5',
        ])
    );
    $editSummary[] = 'PM sent';
}

$userStatus = UserStatus::tryFrom($_POST['UserStatus']) ?? UserStatus::disabled;
if ($userStatus !== $user->userStatus() && $Viewer->permitted('users_disable_users')) {
    $enableStr = "account status {$user->userStatus()->label()} → {$userStatus->label()}";
    if ($userStatus == UserStatus::disabled) {
        $userMan->disableUserList(
            [$user->id],
            UserAuditEvent::activity,
            "Disabled via moderation",
            Manager\User::DISABLE_MANUAL,
        );
        Util\DisabledUserHistory::add($user, $reason);
        $needTrackerRefresh = false;
    } elseif ($userStatus == UserStatus::enabled) {
        $needTrackerAdd = true;
        if (($user->downloadedSize() == 0) || ($user->uploadedSize() / $user->downloadedSize() >= $user->requiredRatio())) {
            $user->setField('can_leech', 1)
                ->setField('RatioWatchEnds', null)
                ->setField('RatioWatchDownload', 0);
        } else {
            $enableStr .= ' (Ratio: ' . ratio_html($user->uploadedSize(), $user->downloadedSize(), false)
                . ', RR: ' . number_format($user->requiredRatio(), 2) . ')';
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
        $oldPasskey = $user->announceKey();
        $passkey = randomString();
        $user->history()->modifyAnnounceKey(old: $oldPasskey, new: $passkey);
        $tracker->modifyPasskey(old: $oldPasskey, new: $passkey);
        $editSummary[] = 'passkey reset';
    }
}

if (isset($hackedEmail) && $Viewer->permitted('users_disable_any')) {
    new Mail()->send($hackedEmail, SITE_NAME . ' account - suspicious activity',
        $Twig->render('email/hacked.text.twig', [
            'user' => $user
        ])
    );
    $userMan->disableUserList(
        [$user->id],
        UserAuditEvent::activity,
        "Disabled via hacked email",
        Manager\User::DISABLE_MANUAL,
    );
    $editSummary[] = "hacked account email sent to $hackedEmail";
    Util\DisabledUserHistory::add($user, "Disabled via hacked email");
}

if (isset($mergeFrom)) {
    $merge = $user->mergeLeechStats($mergeFrom, $Viewer);
    $user->setField('leech_upload', $user->uploadedSize() + $merge['up'])
        ->setField('leech_download', $user->downloadedSize() + $merge['down']);
}

if ($changePassword && $Viewer->permitted('users_edit_password')) {
    $editSummary[] = 'password reset';
}

if ($removedClasses) {
    $user->removeClasses($removedClasses);
}
if ($addedClasses) {
    $user->addClasses($addedClasses);
}

if ($editSummary === [] && $reason) {
    $editSummary[] = 'notes added';
}

if ($editSummary) {
    $summary = implode(', ', $editSummary);
    if ($reason) {
        $summary .= "\nReason: $reason";
    }
    $user->auditTrail()->addEvent(UserAuditEvent::staffNote, ucfirst($summary), $Viewer);
} elseif ($adminComment !== $cur['admincomment']) {
    $user->auditTrail()->addEvent(UserAuditEvent::staffNote, $adminComment, $Viewer);
}

if ($changePassword && $Viewer->permitted('users_edit_password')) {
    $user->history()->modifyPassword($_POST['ChangePassword'], false);
    new User\Session($user)->dropAll();
}

if ($newBonusPoints !== false) {
    new User\Bonus($user)->setPoints($newBonusPoints);
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
        new Manager\InviteSource()->modifyInviterConfiguration($user, $idList);
        header("Location: tools.php?action=invite_source");
        exit;
    }
}

header('Location: ' . $user->location());
