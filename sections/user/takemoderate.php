<?php

use Gazelle\Util\Mail;

authorize();

function translateUserStatus($status) {
    switch ($status) {
        case 0:
            return 'Unconfirmed';
        case 1:
            return 'Enabled';
        case 2:
            return 'Disabled';
        default:
            return $status;
    }
}

function enabledStatus($status) {
    switch ($status) {
        case 0:
            return 'Disabled';
        case 1:
            return 'Enabled';
        default:
            return $status;
    }
}

function disabled (bool $state) {
    return $state ? 'disabled' : 'enabled';
}

function classNames(array $classes) {
    global $DB;
    return $DB->scalar("
        SELECT group_concat(Name SEPARATOR ', ')
        FROM permissions
        WHERE ID in (" . placeholders($classes) . ")
        ", ...$classes
    );
}

if (!check_perms('users_mod')) {
    error(403);
}

$userMan = new Gazelle\Manager\User;
$user = $userMan->findById((int)$_POST['userid']);
if (is_null($user)) {
    header("Location: log.php?search=User+$userID");
    exit;
}
$userID = $user->id();
$ownProfile = $userID === $LoggedUser['ID'];

// Variables for database input
$class = (int)$_POST['Class'];
$username = trim($_POST['Username']);
$title = $_POST['Title'];
$adminComment = trim($_POST['AdminComment']);
$secondaryClasses = isset($_POST['secondary_classes']) ? $_POST['secondary_classes'] : [];
foreach ($secondaryClasses as $i => $Val) {
    if (!is_number($Val)) {
        unset($secondaryClasses[$i]);
    }
}
$visible = isset($_POST['Visible']) ? '1' : '0';
$unlimitedDownload = isset($_POST['unlimitedDownload']) ? 1 : 0;
$invites = (int)$_POST['Invites'];
$supportFor = trim($_POST['SupportFor']);
$changePassword = !empty($_POST['ChangePassword']);
$warned = isset($_POST['Warned']) ? 1 : 0;
$uploaded = $downloaded = $bonusPoints = null;
if (isset($_POST['Uploaded']) && isset($_POST['Downloaded'])) {
    $uploaded = ($_POST['Uploaded'] === '' ? 0 : $_POST['Uploaded']);
    if ($arithmetic = strpbrk($uploaded, '+-')) {
        $uploaded += max(-$uploaded, Format::get_bytes($arithmetic));
    }
    $downloaded = ($_POST['Downloaded'] === '' ? 0 : $_POST['Downloaded']);
    if ($arithmetic = strpbrk($downloaded, '+-')) {
        $downloaded += max(-$downloaded, Format::get_bytes($arithmetic));
    }
    if (!is_number($uploaded) || !is_number($downloaded)) {
        error(0);
    }
}
if (isset($_POST['BonusPoints'])) {
    if (empty($_POST['BonusPoints'])) {
        $bonusPoints = 0;
    }
    elseif ($_POST['BonusPoints'] != strval(floatval($_POST['BonusPoints']))) {
        error(0);
    }
    else {
        $bonusPoints = round(floatval($_POST['BonusPoints']), 5);
    }
}
$Collages = (int)$_POST['Collages'] ?? 0;
$flTokens = isset($_POST['FLTokens']) ? trim($_POST['FLTokens']) : 0;
if (!is_number($flTokens)) {
    error(0);
}

$warnLength = (int)$_POST['WarnLength'];
$extendWarning = $_POST['ExtendWarning'] ?? '---';
$reduceWarning = $_POST['ReduceWarning'] ?? '---';
$warnReason = trim($_POST['WarnReason']);
$userReason = trim($_POST['UserReason']);
$disableAvatar = isset($_POST['DisableAvatar']) ? 1 : 0;
$disableInvites = isset($_POST['DisableInvites']) ? 1 : 0;
$disablePosting = isset($_POST['DisablePosting']) ? 1 : 0;
$disablePoints = isset($_POST['DisablePoints']) ? 1 : 0;
$disableForums = isset($_POST['DisableForums']) ? 1 : 0;
$disableTagging = isset($_POST['DisableTagging']) ? 1 : 0;
$disableUpload = isset($_POST['DisableUpload']) ? 1 : 0;
$disableWiki = isset($_POST['DisableWiki']) ? 1 : 0;
$disablePM = isset($_POST['DisablePM']) ? 1 : 0;
$disableIRC = isset($_POST['DisableIRC']) ? 1 : 0;
$disableRequests = isset($_POST['DisableRequests']) ? 1 : 0;
$disableLeech = isset($_POST['DisableLeech']) ? 0 : 1;
$lockAccount = isset($_POST['LockAccount']) ? 1 : 0;
$lockType = (int)$_POST['LockType'];

$restrictedForums = trim($_POST['RestrictedForums']);
$permittedForums = trim($_POST['PermittedForums']);
$enableUser = (int)$_POST['UserStatus'];
$resetRatioWatch = $_POST['ResetRatioWatch'] ?? 0 ? 1 : 0;
$resetIPHistory = $_POST['ResetIPHistory'] ?? 0;
$resetPasskey = isset($_POST['ResetPasskey']) ? 1 : 0;
$resetAuthkey = isset($_POST['ResetAuthkey']) ? 1 : 0;
$logoutSession = isset($_POST['Logout']) ? 1 : 0;
$sendHackedMail = isset($_POST['SendHackedMail']) ? 1 : 0;
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

// NOW that we know the class of the current user, we can see if one staff member is trying to hax0r us.
if (!check_perms('users_mod')) {
    error(403);
}

if ($mergeStatsFrom && ($downloaded != $cur['Downloaded'] || $uploaded != $cur['Uploaded'])) {
    // Too make make-work code to deal with this unlikely eventuality
    error("Do not transfer buffer and edit upload/download in the same operation.");
}

$donorMan = new Gazelle\Manager\Donation;
$donorMan->twig($Twig);
if (!empty($_POST['donor_points_submit']) && !empty($_POST['donation_value']) && is_numeric($_POST['donation_value'])) {
    $donorMan->moderatorDonate($user, $_POST['donation_value'], $_POST['donation_currency'], $_POST['donation_reason'], $LoggedUser['ID']);
} elseif (!empty($_POST['donor_values_submit'])) {
    $donorMan->moderatorAdjust($user, $_POST['donor_rank_delta'], $_POST['total_donor_rank_delta'], $_POST['reason'], $LoggedUser['ID']);
}

$tracker = new Gazelle\Tracker;

// If we're deleting the user, we can ignore all the other crap
if ($_POST['UserStatus'] === 'delete' && check_perms('users_delete_users')) {
    (new Gazelle\Log)->general("User account $userID (".$cur['Username'].") was deleted by ".$LoggedUser['Username']);
    $user->remove();
    $tracker->update_tracker('remove_user', ['passkey' => $cur['torrent_pass']]);
    header("Location: log.php?search=User+$userID");
    exit;
}

// User was not deleted. Perform other stuff.

$editSummary = [];
$trackerUserUpdates = ['passkey' => $cur['torrent_pass']];

if (!$lockType || $lockAccount == 0) {
    if ($cur['Type']) {
        $user->unlock();
        $Cache->delete_value('user_' . $cur['torrent_pass']);
        $editSummary[] = 'account unlocked';
    }
} elseif ($lockType) {
    if ($cur['Type'] !== $lockType) {
        if ($user->lock($lockType)) {
            $Cache->delete_value('user_' . $cur['torrent_pass']);
            $editSummary[] = empty($cur['Type'])
                ? "Account locked (type $lockType)"
                : "Account lock type changed to $lockType";
        }
    }
}

if ($_POST['ResetRatioWatch'] ?? 0 && check_perms('users_edit_reset_keys')) {
    $user->resetRatioWatch();
    $editSummary[] = 'RatioWatch history reset';
}

if ($resetIPHistory && check_perms('users_edit_reset_keys')) {
    $user->resetIpHistory();
    $editSummary[] = 'IP history cleared';
}

if ($_POST['ResetEmailHistory'] ?? 0 && check_perms('users_edit_reset_keys')) {
    $user->resetEmailHistory($username . '@' . SITE_HOST, $resetIPHistory ? '127.0.0.1' : $cur['IP']);
    $editSummary[] = 'email history cleared';
}

if ($_POST['ResetSnatchList'] ?? 0 && check_perms('users_edit_reset_keys')) {
    $user->resetSnatched();
    $editSummary[] = 'snatch list cleared';
}

if ($_POST['ResetDownloadList'] ?? 0 && check_perms('users_edit_reset_keys')) {
    $user->resetDownloadList();
    $editSummary[] = 'download list cleared';
}

if ($logoutSession && check_perms('users_logout')) {
    $editSummary[] = "logged out of all sessions (n=" . (new Gazelle\Session($UserID))->dropAll() . ")";
}

if ($flTokens != $cur['FLTokens'] && ($editRatio || check_perms('admin_manage_user_fls'))) {
    $editSummary[] = "freeleech tokens changed from {$cur['FLTokens']} to $flTokens";
}

$newBonusPoints = false;
if ($bonusPoints != floatval($cur['BonusPoints']) && $bonusPoints != floatval($_POST['OldBonusPoints'])
    && (check_perms('users_edit_ratio') || (check_perms('users_edit_own_ratio') && $ownProfile))) {
    $newBonusPoints = $bonusPoints;
    $editSummary[] = "bonus points changed from {$cur['BonusPoints']} to {$bonusPoints}";
}

if ($Collages != $Cur['Collages'] && $Collages != (int)$_POST['OldCollages']
    && (check_perms('users_edit_ratio') || (check_perms('users_edit_own_ratio') && $ownProfile))) {
    $set[] = 'collages = ?';
    $args[] = $Collages;
    $EditSummary[] = "personal collages changed from {$Cur['Collages']} to {$Collages}";
}

$removedClasses = [];
$addedClasses   = [];
if (check_perms('users_promote_below') || check_perms('users_promote_to')) {
    $removedClasses = array_diff($cur['secondary_class'], $secondaryClasses);
    $addedClasses   = array_diff($secondaryClasses, $cur['secondary_class']);
    if ($removedClasses) {
        $editSummary[] = 'secondary classes dropped: ' . classNames($removedClasses);
    }
    if ($addedClasses) {
        $editSummary[] = "secondary classes added: " . classNames($addedClasses);
    }
}

if ($unlimitedDownload != $cur['unlimitedDownload'] && check_perms('admin_rate_limit_manage')) {
    if ($user->toggleUnlimitedDownload($unlimitedDownload)) {
        $editSummary[] = "unlimited download " . strtolower(enabledStatus($unlimitedDownload));
    }
}

$leechSet = [];
$leechArgs = [];
$editRatio = check_perms('users_edit_ratio') || (check_perms('users_edit_own_ratio') && $ownProfile);
if ($editRatio) {
    if ($uploaded != $cur['Uploaded'] && $uploaded != $_POST['OldUploaded']) {
        $leechSet[] = 'Uploaded = ?';
        $leechArgs[] = $uploaded;
        $editSummary[] = "uploaded changed from " . Format::get_size($cur['Uploaded'])
            . ' to ' . Format::get_size($uploaded)
            . " (delta " . Format::get_size($uploaded - $cur['Uploaded']) . ")";
    }
    if ($downloaded != $cur['Downloaded'] && $downloaded != $_POST['OldDownloaded']) {
        $leechSet[] = 'Downloaded = ?';
        $leechArgs[] = $downloaded;
        $editSummary[] = "downloaded changed from " . Format::get_size($cur['Downloaded'])
            . ' to ' . Format::get_size($downloaded)
            . " (delta " . Format::get_size($downloaded - $cur['Downloaded']) . ")";
    }
}

// Begin building users_main/users_info update
$set = [];
$args = [];

$Classes = $userMan->classList();
if ($Classes[$class]['Level'] != $cur['Class']
    && (
        ($Classes[$class]['Level'] < $LoggedUser['Class'] && check_perms('users_promote_below'))
        || ($Classes[$class]['Level'] <= $LoggedUser['Class'] && check_perms('users_promote_to'))
)) {
    $set[] = 'PermissionID = ?';
    $args[] = $class;
    $editSummary[] = 'class changed to ' . $userMan->userclassName($class);

    if ($user->supportCount($class, $cur['PermissionID']) === 2) {
        if ($Classes[$class]['Level'] < $cur['Class']) {
            $supportFor = '';
        }
        $Cache->delete_value('staff_ids');
    }
    $Cache->delete_value("donor_info_$userID");
}

if ($username !== $cur['Username'] && check_perms('users_edit_usernames')) {
    if (in_array($username, ['0', '1'])) {
        error('You cannot set a username of "0" or "1".');
        header("Location: user.php?id=$userID");
        exit;
    } elseif (strtolower($username) !== strtolower($cur['Username'])) {
        $found = $userMan->findByUsername($username);
        if ($found) {
            $id = $found->id();
            error("Username already in use by <a href=\"user.php?id=$id\">$username</a>");
            header("Location: user.php?id=$id");
            exit;
        }
        $set[] = 'Username = ?';
        $args[] = $username;
        $editSummary[] = "username changed from ".$cur['Username']." to $username";
    }
}

if ($title != $cur['Title'] && check_perms('users_edit_titles')) {
    // Using the unescaped value for the test to avoid confusion
    if (mb_strlen($_POST['Title']) > 1024) {
        error("Custom titles have a maximum length of 1,024 characters.");
        header("Location: user.php?id=$userID");
        exit;
    } else {
        $set[] = 'Title = ?';
        $args[] = $title;
        $editSummary[] = "title changed to [code]{$title}[/code]";
    }
}

if (check_perms('users_warn')) {
    if ($warned == 0) {
        if (!is_null($cur['Warned'])) {
            $set[] = "Warned = ?";
            $args[] = null;
            $editSummary[] = 'warning removed';
        }
    } elseif (
        (is_null($cur['Warned']) && $warnLength != '---')
        ||
        ($cur['Warned'] && ($extendWarning != '---' || $reduceWarning != '---'))
    ) {
        if (is_null($cur['Warned'])) {
            $weeksChange = $warnLength;
            $duration = 'week' . plural($warnLength);
            $message = [
                'summary' => "warned for $warnLength $duration",
                'subject' => 'You have received a warning',
                'body'    => "You have been [url=" . SITE_URL
                    . "/wiki.php?action=article&amp;name=warnings]warned[/url] for $warnLength $duration",
            ];
        } else {
            $weeksChange = ($extendWarning != '---') ? $extendWarning : -$reduceWarning;
            $nrWeeks = abs($weeksChange);
            $duration = 'week' . plural($nrWeeks);
            $action = $weeksChange > 0 ? 'extended' : 'reduced';
            $message = [
                'summary' => "warning $action $nrWeeks $duration",
                'subject' => "Your warning has been $action by $nrWeeks $duration",
                'body'    => "Your warning has been $action by $nrWeeks $duration",
            ];
        }
        $set[] = "Warned = now() + INTERVAL ? WEEK";
        $args[] = $weeksChange;
        $expiry = $user->endWarningDate($weeksChange);
        $message['body'] .= ", by [user]" . $LoggedUser['Username'] . "[/user]."
            . " The reason given was:\n[quote]{$warnReason}[/quote]. The warning will expire on $expiry."
            . "\n\nThis is an automated message. You may reply for more information if necessary.";
        $userMan->sendPM($userID, $LoggedUser['ID'], $message['subject'], $message['body']);
        $editSummary[] = $message['summary'] . ", expiry: $expiry"
            . ($warnReason ? ", reason: \"$warnReason\"" : '');
    }
}

if ($restrictedForums != $cur['RestrictedForums'] && check_perms('users_mod')) {
    $set[] = "RestrictedForums = ?";
    $args[] = $restrictedForums;
    $editSummary[] = "prohibited forum(s): $restrictedForums";
}

if ($permittedForums != $cur['PermittedForums'] && check_perms('users_mod')) {
    $forumSet = explode(',', $permittedForums);
    $forumList = [];
    foreach ($forumSet as $forumID) {
        $f = trim($forumID);
        if ($forums[$f]['MinClassCreate'] <= $LoggedUser['EffectiveClass']) {
            $forumList[] = $f;
        }
    }
    $set[] = "PermittedForums = ?";
    $args[] = implode(',', $forumList);
    $editSummary[] = "permitted forum(s): $permittedForums";
}

if ($visible != $cur['Visible'] && check_perms('users_make_invisible')) {
    $set[] = 'Visible = ?';
    $args[] = $visible ? '1' : '0';
    $trackerUserUpdates['visible'] = $visible;
    $editSummary[] = 'visibility ' . ($visible ? 'on' : 'off');
}

if ($invites != $cur['Invites'] && check_perms('users_edit_invites')) {
    $set[] = 'Invites = ?';
    $args[] = $invites;
    $editSummary[] = "number of invites changed from {$cur['Invites']} to $invites";
}

if ($supportFor != $cur['SupportFor'] && (check_perms('admin_manage_fls') || (check_perms('users_mod') && $ownProfile))) {
    $set[] = "SupportFor = ?";
    $args[] = $supportFor;
    $editSummary[] = "First-Line Support status changed to \"$supportFor\"";
}

$privChange = [];
if ($disableAvatar != $cur['DisableAvatar'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your avatar privileges have been ' . ($disableAvatar ? 'removed' : 'restored');
    $set[] = "DisableAvatar = ?";
    $args[] = $disableAvatar ? '1' : '0';
    $editSummary[] = 'avatar privileges ' . disabled($disableAvatar);
}

if ($disableLeech != $cur['can_leech'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your leeching privileges have been ' . ($disableLeech ? 'removed' : 'restored');
    $set[] = "can_leech = ?";
    $args[] = $disableLeech ? '1' : '0';
    $trackerUserUpdates['can_leech'] = $disableLeech;
    $editSummary[] = "leeching status changed (".enabledStatus($cur['can_leech'])." &rarr; ".enabledStatus($disableLeech).")";
}

if ($disableInvites != $cur['DisableInvites'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your invite privileges have been ' . ($disableInvites ? 'removed' : 'restored');
    $set[] = "DisableInvites = ?";
    $args[] = $disableInvites ? '1' : '0';
    $editSummary[] = 'invites privileges ' . disabled($disableInvites);
}

if ($disablePosting != $cur['DisablePosting'] && check_perms('users_disable_posts')) {
    $privChange[] = 'Your forum posting privileges have been ' . ($disablePosting ? 'removed' : 'restored');
    $set[] = "DisablePosting = ?";
    $args[] = $disablePosting ? '1' : '0';
    $editSummary[] = 'posting privileges ' . disabled($disablePosting);
}

if ($disablePoints != $cur['DisablePoints'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your bonus points acquisition has been ' . ($disablePoints ? 'revoked' : 'restored');
    $set[] = "DisablePoints = ?";
    $args[] = $disablePoints ? '1' : '0';
    $editSummary[] = 'points privileges ' . disabled($disablePoints);
}

if ($disableForums != $cur['DisableForums'] && check_perms('users_disable_posts')) {
    $privChange[] = 'Your forum access has been ' . ($disableForums ? 'revoked' : 'restored');
    $set[] = "DisableForums = ?";
    $args[] = $disableForums ? '1' : '0';
    $editSummary[] = 'forums privileges ' . disabled($disableForums);
}

if ($disableTagging != $cur['DisableTagging'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your tagging privileges have been ' . ($disableTagging ? 'removed' : 'restored');
    $set[] = "DisableTagging = ?";
    $args[] = $disableTagging ? '1' : '0';
    $editSummary[] = 'tagging privileges ' . disabled($disableTagging);
}

if ($disableUpload != $cur['DisableUpload'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your upload privileges have been ' . ($disableUpload ? 'removed' : 'restored');
    $set[] = "DisableUpload = ?";
    $args[] = $disableUpload ? '1' : '0';
    $editSummary[] = 'upload privileges ' . disabled($disableUpload);
}

if ($disableWiki != $cur['DisableWiki'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your site editing privileges have been ' . ($disableWiki ? 'removed' : 'restored');
    $set[] = "DisableWiki = ?";
    $args[] = $disableWiki ? '1' : '0';
    $editSummary[] = 'wiki privileges ' . disabled($disableWiki);
}

if ($disablePM != $cur['DisablePM'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your private messate (PM) privileges have been ' . ($disablePM ? 'removed' : 'restored');
    $set[] = "DisablePM = ?";
    $args[] = $disablePM ? '1' : '0';
    $editSummary[] = 'PM privileges ' . disabled($disablePM);
}

if ($disableIRC != $cur['DisableIRC']) {
    $privChange[] = 'Your IRC privileges have been ' . ($disableIRC ? 'removed' : 'restored');
    $set[] = "DisableIRC = ?";
    $args[] = $disableIRC ? '1' : '0';
    $editSummary[] = 'IRC privileges ' . disabled($disableIRC);
}

if ($disableRequests != $cur['DisableRequests'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your request privileges have been ' . ($disableRequests ? 'removed' : 'restored');
    $set[] = "DisableRequests = ?";
    $args[] = $disableRequests ? '1' : '0';
    $editSummary[] = 'request privileges ' . disabled($disableRequests);
}

if ($privChange && $userReason) {
    sort($privChange);
    $userMan->sendPM(
        $userID, 0,
        count($privChange) == 1 ? $privChange[0] : 'Multiple privileges have changed on your account',
        $Twig->render('user/pm-privilege.twig', [
            'privs'  => $privChange,
            'reason' => $userReason,
            'chan'   => BOT_DISABLED_CHAN,
            'url'    => SITE_URL . '/wiki.php?action=article&amp;id=5',
        ])
    );
    $editSummary[] = 'PM sent';
}

if ($enableUser != $cur['Enabled'] && check_perms('users_disable_users')) {
    $enableStr = 'account ' . translateUserStatus($cur['Enabled']) . ' &rarr; ' . translateUserStatus($enableUser);
    if ($enableUser == '2') {
        $userMan->disableUserList([$userID], "Disabled via moderation", Gazelle\Manager\User::DISABLE_MANUAL);
        $trackerUserUpdates = [];
    } elseif ($enableUser == '1') {
        $Cache->increment('stats_user_count');
        $visibleTrIP = $visible && $cur['IP'] != '127.0.0.1' ? '1' : '0';
        $tracker->update_tracker('add_user', ['id' => $userID, 'passkey' => $cur['torrent_pass'], 'visible' => $visibleTrIP]);
        if (($cur['Downloaded'] == 0) || ($cur['Uploaded'] / $cur['Downloaded'] >= $cur['RequiredRatio'])) {
            $canLeech = 1;
            $set[] = "i.RatioWatchEnds = ?";
            $args[] = null;
            $set[] = "m.can_leech = ?";
            $args[] = '1';
            $set[] = "i.RatioWatchDownload = ?";
            $args[] = '0';
        } else {
            $enableStr .= ' (Ratio: '.Format::get_ratio_html($cur['Uploaded'], $cur['Downloaded'], false).', RR: '.number_format($cur['RequiredRatio'],2).')';
            if ($cur['RatioWatchEnds']) {
                $set[] = "i.RatioWatchEnds = now()";
                $set[] = "i.RatioWatchDownload = ?";
                $args[] = $cur['Downloaded'];
                $canLeech = 0;
            }
            $trackerUserUpdates['can_leech'] = 0;
        }
        $set[] = "i.BanReason = ?";
        $args[] = '0';
        $set[] = "Enabled = ?";
        $args[] = '1';
    }
    $editSummary[] = $enableStr;
}

if ($resetPasskey == 1 && check_perms('users_edit_reset_keys')) {
    $passkey = randomString();
    $user->modifyAnnounceKeyHistory($cur['torrent_pass'], $passkey, '0.0.0.0');
    $Cache->delete_value('user_'.$cur['torrent_pass']);
    $trackerUserUpdates['passkey'] = $passkey; // MUST come after the case for updating can_leech
    $tracker->update_tracker('change_passkey', ['oldpasskey' => $cur['torrent_pass'], 'newpasskey' => $passkey]);
    $set[] = "torrent_pass = ?";
    $args[] = $passkey;
    $editSummary[] = 'passkey reset';
}

if ($resetAuthkey == 1 && check_perms('users_edit_reset_keys')) {
    $set[] = "Authkey = ?";
    $args[] = randomString();
    $editSummary[] = 'authkey reset';
}

if ($sendHackedMail && check_perms('users_disable_any')) {
    (new Mail)->send($hackedEmail, 'Your ' . SITE_NAME . ' account',
        $Twig->render('email/hacked.twig')
    );
    Tools::disable_users($userID, '', 1);
    $editSummary[] = "hacked account email sent to $hackedEmail";
}

if ($mergeStatsFrom && check_perms('users_edit_ratio')) {
    $stats = $user->mergeLeechStats($mergeStatsFrom, $LoggedUser['Username']);
    if ($stats) {
        $merge = new Gazelle\User($stats['userId']);
        $merge->flush();
        $leechSet[] = "Uploaded = Uploaded + ?";
        $leechArgs[] = $stats['up'];
        $leechSet[] = "Downloaded = Downloaded + ?";
        $leechArgs[] = $stats['down'];
        $editSummary[] = sprintf('leech stats (up: %s, down: %s, ratio: %s) merged from %s (%s) prior(up: %s, down: %s, ratio: %s)',
            Format::get_size($stats['up']), Format::get_size($stats['down']), Format::get_ratio($stats['up'], $stats['down']),
            $merge->url(), $mergeStatsFrom,
            Format::get_size($cur['Uploaded']), Format::get_size($cur['Downloaded']),
            Format::get_ratio($cur['Uploaded'], $cur['Downloaded'])
        );
    }
}

if ($changePassword && check_perms('users_edit_password')) {
    $set[] = "PassHash = ?";
    $args[] = Gazelle\UserCreator::hashPassword($changePassword);
    (new \Gazelle\Session($userID))->dropAll();
    $editSummary[] = 'password reset';
}

if (!(count($set) || count($leechSet) || count($editSummary)) && $reason) {
    $editSummary[] = 'notes added';
}

// Because of the infinitely fucked up encoding/decoding of Gazelle, $adminComment !== $cur['AdminComment']
// almost always evaluates to true, even if the user did not purposely change the field. This then means
// we do have a bug where if a mod changes something about a user AND changes the admin comment, we will lose
// that change, but until we never decode stuff coming out of the DB, not much can be done.

if (count($editSummary)) {
    $summary = implode(', ', $editSummary) . ' by ' . $LoggedUser['Username'];
    $set[] = "AdminComment = ?";
    $args[] = sqltime() . ' - ' . ucfirst($summary) . ($reason ? "\nReason: $reason" : '') . "\n\n$adminComment";
} elseif ($adminComment !== $cur['AdminComment']) {
    $set[] = "AdminComment = ?";
    $args[] = $adminComment;
}

if (!empty($set)) {
    $args[] = $userID;
    $DB->prepared_query("
        UPDATE users_main AS m
        INNER JOIN users_info AS i ON (m.ID = i.UserID)
        SET " .  implode(', ', $set) . "
        WHERE m.ID = ?
        ", ...$args
    );
}

if ($leechSet) {
    $leechArgs[] = $userID;
    $DB->prepared_query("
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

if ($newBonusPoints !== false) {
    $bonus = new \Gazelle\Bonus;
    $bonus->setPoints($userID, $newBonusPoints);
}

if ($flTokens != $cur['FLTokens']) {
    $user->updateTokens($flTokens);
}

if (count($trackerUserUpdates) > 1) {
    $tracker->update_tracker('update_user', $trackerUserUpdates);
}

if (count($set) || count($leechSet)) {
    $user->flush();
}

header("location: user.php?id=$userID");
