<?php
/*************************************************************************\
//--------------Take moderation -----------------------------------------//
\*************************************************************************/

// Are they being tricky blighters?
$UserID = (int)$_POST['userid'];
if ($UserID < 1) {
    error(404);
} elseif (!check_perms('users_mod')) {
    error(403);
}
$ownProfile = $UserID == $LoggedUser['ID'];
$user = new Gazelle\User($UserID);

authorize();
// End checking for moronity

// Variables for database input
$Class = (int)$_POST['Class'];
$Username = trim($_POST['Username']);
$Title = $_POST['Title'];
$AdminComment = trim($_POST['AdminComment']);
$SecondaryClasses = isset($_POST['secondary_classes']) ? $_POST['secondary_classes'] : [];
foreach ($SecondaryClasses as $i => $Val) {
    if (!is_number($Val)) {
        unset($SecondaryClasses[$i]);
    }
}
$Visible = isset($_POST['Visible']) ? 1 : 0;
$unlimitedDownload = isset($_POST['unlimitedDownload']) ? 1 : 0;
$Invites = (int)$_POST['Invites'];
$SupportFor = trim($_POST['SupportFor']);
$ChangePassword = $_POST['ChangePassword'];
$Warned = isset($_POST['Warned']) ? 1 : 0;
$Uploaded = $Downloaded = $BonusPoints = null;
if (isset($_POST['Uploaded']) && isset($_POST['Downloaded'])) {
    $Uploaded = ($_POST['Uploaded'] === '' ? 0 : $_POST['Uploaded']);
    if ($Arithmetic = strpbrk($Uploaded, '+-')) {
        $Uploaded += max(-$Uploaded, Format::get_bytes($Arithmetic));
    }
    $Downloaded = ($_POST['Downloaded'] === '' ? 0 : $_POST['Downloaded']);
    if ($Arithmetic = strpbrk($Downloaded, '+-')) {
        $Downloaded += max(-$Downloaded, Format::get_bytes($Arithmetic));
    }
    if (!is_number($Uploaded) || !is_number($Downloaded)) {
        error(0);
    }
}
if (isset($_POST['BonusPoints'])) {
    if (empty($_POST['BonusPoints'])) {
        $BonusPoints = 0;
    }
    elseif ($_POST['BonusPoints'] != strval(floatval($_POST['BonusPoints']))) {
        error(0);
    }
    else {
        $BonusPoints = round(floatval($_POST['BonusPoints']), 5);
    }
}
$FLTokens = isset($_POST['FLTokens']) ? trim($_POST['FLTokens']) : 0;
if (!is_number($FLTokens)) {
    error(0);
}

$WarnLength = (int)$_POST['WarnLength'];
$ExtendWarning = $_POST['ExtendWarning'] ?? '---';
$ReduceWarning = $_POST['ReduceWarning'] ?? '---';
$WarnReason = trim($_POST['WarnReason']);
$UserReason = trim($_POST['UserReason']);
$DisableAvatar = isset($_POST['DisableAvatar']) ? 1 : 0;
$DisableInvites = isset($_POST['DisableInvites']) ? 1 : 0;
$DisablePosting = isset($_POST['DisablePosting']) ? 1 : 0;
$DisablePoints = isset($_POST['DisablePoints']) ? 1 : 0;
$DisableForums = isset($_POST['DisableForums']) ? 1 : 0;
$DisableTagging = isset($_POST['DisableTagging']) ? 1 : 0;
$DisableUpload = isset($_POST['DisableUpload']) ? 1 : 0;
$DisableWiki = isset($_POST['DisableWiki']) ? 1 : 0;
$DisablePM = isset($_POST['DisablePM']) ? 1 : 0;
$DisableIRC = isset($_POST['DisableIRC']) ? 1 : 0;
$DisableRequests = isset($_POST['DisableRequests']) ? 1 : 0;
$DisableLeech = isset($_POST['DisableLeech']) ? 0 : 1;
$LockAccount = isset($_POST['LockAccount']) ? 1 : 0;
$LockType = (int)$_POST['LockType'];

$RestrictedForums = trim($_POST['RestrictedForums']);
$PermittedForums = trim($_POST['PermittedForums']);
$EnableUser = (int)$_POST['UserStatus'];
$ResetRatioWatch = $_POST['ResetRatioWatch'] ?? 0 ? 1 : 0;
$ResetIPHistory = $_POST['ResetIPHistory'] ?? 0;
$ResetPasskey = isset($_POST['ResetPasskey']) ? 1 : 0;
$ResetAuthkey = isset($_POST['ResetAuthkey']) ? 1 : 0;
$LogoutSession = isset($_POST['Logout']) ? 1 : 0;
$SendHackedMail = isset($_POST['SendHackedMail']) ? 1 : 0;
if ($SendHackedMail && !empty(trim($_POST['HackedEmail']))) {
    $HackedEmail = trim($_POST['HackedEmail']);
} else {
    $SendHackedMail = false;
}
$MergeStatsFrom = trim($_POST['MergeStatsFrom']);
$Reason = trim($_POST['Reason']);

// Get the existing user information
if (!$Cur = $user->info()) { // If user doesn't exist
    header("Location: log.php?search=User+$UserID");
}

if ($_POST['comment_hash'] != $Cur['CommentHash']) {
    error("Somebody else has moderated this user since you loaded it. Please go back and refresh the page.");
}

// NOW that we know the class of the current user, we can see if one staff member is trying to hax0r us.
if (!check_perms('users_mod', $Cur['Class'])) {
    error(403);
}

if ($MergeStatsFrom && ($Downloaded != $Cur['Downloaded'] || $Uploaded != $Cur['Uploaded'])) {
    // Too make make-work code to deal with this unlikely eventuality
    error("Do not transfer buffer and edit upload/download in the same operation.");
}

$donorMan = new Gazelle\Manager\Donation;
$donorMan->twig(G::$Twig);
if (!empty($_POST['donor_points_submit']) && !empty($_POST['donation_value']) && is_numeric($_POST['donation_value'])) {
    $donorMan->moderatorDonate($UserID, $_POST['donation_value'], $_POST['donation_currency'], $_POST['donation_reason'], $LoggedUser['ID']);
} elseif (!empty($_POST['donor_values_submit'])) {
    $donorMan->moderatorAdjust($UserID, $_POST['donor_rank_delta'], $_POST['total_donor_rank_delta'], $_POST['reason'], $LoggedUser['ID']);
}

// If we're deleting the user, we can ignore all the other crap

if ($_POST['UserStatus'] === 'delete' && check_perms('users_delete_users')) {
    Misc::write_log("User account $UserID (".$Cur['Username'].") was deleted by ".$LoggedUser['Username']);
    $user->remove();
    Tracker::update_tracker('remove_user', ['passkey' => $Cur['torrent_pass']]);
    header("Location: log.php?search=User+$UserID");
}

// User was not deleted. Perform other stuff.

$EditSummary = [];
$TrackerUserUpdates = ['passkey' => $Cur['torrent_pass']];

if (!$LockType || $LockAccount == 0) {
    if ($Cur['Type']) {
        $user->unlock();
        $Cache->delete_value('user_' . $Cur['torrent_pass']);
        $EditSummary[] = 'account unlocked';
    }
} elseif ($LockType) {
    if ($Cur['Type'] !== $LockType) {
        if ($user->lock($LockType)) {
            $Cache->delete_value('user_' . $Cur['torrent_pass']);
            $EditSummary[] = empty($Cur['Type'])
                ? "Account locked (type $LockType)"
                : "Account lock type changed to $LockType";
        }
    }
}

if ($_POST['ResetRatioWatch'] ?? 0 && check_perms('users_edit_reset_keys')) {
    $user->resetRatioWatch();
    $EditSummary[] = 'RatioWatch history reset';
}

if ($ResetIPHistory && check_perms('users_edit_reset_keys')) {
    $user->resetIpHistory();
    $EditSummary[] = 'IP history cleared';
}

if ($_POST['ResetEmailHistory'] ?? 0 && check_perms('users_edit_reset_keys')) {
    $user->resetEmailHistory($Username . '@' . SITE_URL, $ResetIPHistory ? '127.0.0.1' : $Cur['IP']);
    $EditSummary[] = 'email history cleared';
}

if ($_POST['ResetSnatchList'] ?? 0 && check_perms('users_edit_reset_keys')) {
    $user->resetSnatched();
    $EditSummary[] = 'snatch list cleared';
}

if ($_POST['ResetDownloadList'] ?? 0 && check_perms('users_edit_reset_keys')) {
    $user->resetDownloadList();
    $EditSummary[] = 'download list cleared';
}

if ($LogoutSession && check_perms('users_logout')) {
    $sessions = $user->logout();
    $EditSummary[] = "logged out of all sessions (n=$sessions)";
}

if ($FLTokens != $Cur['FLTokens'] && ($editRatio || check_perms('admin_manage_user_fls'))) {
    $EditSummary[] = "freeleech tokens changed from $Cur[FLTokens] to $FLTokens";
}

$newBonusPoints = false;
if ($BonusPoints != floatval($Cur['BonusPoints']) && $BonusPoints != floatval($_POST['OldBonusPoints'])
    && (check_perms('users_edit_ratio') || (check_perms('users_edit_own_ratio') && $ownProfile))) {
    $newBonusPoints = $BonusPoints;
    $EditSummary[] = "bonus points changed from {$Cur['BonusPoints']} to {$BonusPoints}";
}

$removedClasses = [];
$addedClasses   = [];
if (check_perms('users_promote_below') || check_perms('users_promote_to')) {
    $OldClasses = $Cur['SecondaryClasses'] ? explode(',', $Cur['SecondaryClasses']) : [];
    $removedClasses = array_diff($OldClasses, $SecondaryClasses);
    $addedClasses   = array_diff($SecondaryClasses, $OldClasses);
    if ($removedClasses) {
        $EditSummary[] = 'secondary classes dropped: ' . classNames($removedClasses);
    }
    if ($addedClasses) {
        $EditSummary[] = "secondary classes added: " . classNames($addedClasses);
    }
}

if ($unlimitedDownload != $Cur['unlimitedDownload'] && check_perms('admin_rate_limit_manage')) {
    if ($user->toggleUnlimitedDownload($unlimitedDownload)) {
        $EditSummary[] = "unlimited download " . strtolower(enabledStatus($unlimitedDownload));
    }
}

$leechSet = [];
$leechArgs = [];
$editRatio = check_perms('users_edit_ratio') || (check_perms('users_edit_own_ratio') && $ownProfile);
if ($editRatio) {
    if ($Uploaded != $Cur['Uploaded'] && $Uploaded != $_POST['OldUploaded']) {
        $leechSet[] = 'Uploaded = ?';
        $leechArgs[] = $Uploaded;
        $EditSummary[] = "uploaded changed from " . Format::get_size($Cur['Uploaded'])
            . ' to ' . Format::get_size($Uploaded)
            . " (delta " . Format::get_size($Cur['Uploaded'] - $Uploaded) . ")";
    }
    if ($Downloaded != $Cur['Downloaded'] && $Downloaded != $_POST['OldDownloaded']) {
        $leechSet[] = 'Downloaded = ?';
        $leechArgs[] = $Downloaded;
        $EditSummary[] = "downloaded changed from " . Format::get_size($Cur['Downloaded'])
            . ' to ' . Format::get_size($Downloaded)
            . " (delta " . Format::get_size($Cur['Downloaded'] - $Downloaded) . ")";
    }
}

// Begin building users_main/users_info update
$set = [];
$args = [];

if ($Classes[$Class]['Level'] != $Cur['Class']
    && (
        ($Classes[$Class]['Level'] < $LoggedUser['Class'] && check_perms('users_promote_below', $Cur['Class']))
        || ($Classes[$Class]['Level'] <= $LoggedUser['Class'] && check_perms('users_promote_to', $Cur['Class'] - 1))
        )
    ) {
    $set[] = 'PermissionID = ?';
    $args[] = $Class;
    $EditSummary[] = 'class changed to '.Users::make_class_string($Class);

    if ($user->supportCount($Class, $ClassLevels[$Cur['Class']]['ID']) === 2) {
        if ($Classes[$Class]['Level'] < $Cur['Class']) {
            $SupportFor = '';
        }
        $Cache->delete_value('staff_ids');
    }
    $Cache->delete_value("donor_info_$UserID");

    if ($Username !== $Cur['Username'] && check_perms('users_edit_usernames', $Cur['Class'] - 1)) {
        if (strtolower($Username) !== strtolower($Cur['Username'])) {
            if ($this->idFromUsername($Username)) {
                error("Username already in use by <a href=\"user.php?id=$inUse\">$Username</a>");
                header("Location: user.php?id=$UserID");
            } else {
                $set[] = 'Username = ?';
                $args[] = $Username;
                $EditSummary[] = "username changed from ".$Cur['Username']." to $Username";
            }
        } elseif (in_array($Username, ['0', '1'])) {
            error('You cannot set a username of "0" or "1".');
            header("Location: user.php?id=$UserID");
        } else {
            $set[] = 'Username = ?';
            $args[] = $Username;
            $EditSummary[] = "username changed from ".$Cur['Username']." to $Username";
        }
    }

    if ($Title != $Cur['Title'] && check_perms('users_edit_titles')) {
        // Using the unescaped value for the test to avoid confusion
        if (mb_strlen($_POST['Title']) > 1024) {
            error("Custom titles have a maximum length of 1,024 characters.");
            header("Location: user.php?id=$UserID");
        } else {
            $set[] = 'Title = ?';
            $args[] = $Title;
            $EditSummary[] = "title changed to [code]{$Title}[/code]";
        }
    }
}

if (check_perms('users_warn')) {
    if ($Warned == 0) {
        if (!is_null($Cur['Warned'])) {
            $set[] = "Warned = ?";
            $args[] = null;
            $EditSummary[] = 'warning removed';
        }
    } elseif (
        (is_null($Cur['Warned']) && $WarnLength != '---')
        ||
        ($Cur['Warned'] && ($ExtendWarning != '---' || $ReduceWarning != '---'))
    ) {
        if (is_null($Cur['Warned'])) {
            $weeksChange = $WarnLength;
            $duration = 'week' . ($WarnLength === 1 ? '' : 's');
            $message = [
                'summary' => "warned for $WarnLength $duration",
                'subject' => 'You have received a warning',
                'body'    => "You have been [url=" . site_url()
                    . "wiki.php?action=article&amp;name=warnings]warned[/url] for $WarnLength $duration",
            ];
        } else {
            $weeksChange = ($ExtendWarning != '---') ? $ExtendWarning : -$ReduceWarning;
            $nrWeeks = abs($weeksChange);
            $duration = 'week' . ($nrWeeks === 1 ? '' : 's');
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
            . " The reason given was:\n[quote]{$WarnReason}[/quote]. The warning will expire on $expiry."
            . "\n\nThis is an automated message. You may reply for more information if necessary.";
        Misc::send_pm($UserID, $LoggedUser['ID'], $message['subject'], $message['body']);
        $EditSummary[] = $message['summary'] . ", expiry: $expiry"
            . ($WarnReason ? ", reason: \"$WarnReason\"" : '');
    }
}

if ($RestrictedForums != $Cur['RestrictedForums'] && check_perms('users_mod')) {
    $set[] = "RestrictedForums = ?";
    $args[] = $RestrictedForums;
    $EditSummary[] = "prohibited forum(s): $RestrictedForums";
}

if ($PermittedForums != $Cur['PermittedForums'] && check_perms('users_mod')) {
    $ForumSet = explode(',', $PermittedForums);
    $ForumList = [];
    foreach ($ForumSet as $ForumID) {
        $f = trim($ForumID);
        if ($Forums[$f]['MinClassCreate'] <= $LoggedUser['EffectiveClass']) {
            $ForumList[] = $f;
        }
    }
    $set[] = "PermittedForums = ?";
    $args[] = implode(',', $ForumList);
    $EditSummary[] = "permitted forum(s): $PermittedForums";
}

if ($Visible != $Cur['Visible'] && check_perms('users_make_invisible')) {
    $set[] = 'Visible = ?';
    $args[] = $Visible ? '1' : '0';
    $TrackerUserUpdates['visible'] = $Visible;
    $EditSummary[] = 'visibility ' . ($Visible ? 'on' : 'off');
}

if ($Invites != $Cur['Invites'] && check_perms('users_edit_invites')) {
    $set[] = 'Invites = ?';
    $args[] = $Invites;
    $EditSummary[] = "number of invites changed from $Cur[Invites] to $Invites";
}

if ($SupportFor != $Cur['SupportFor'] && (check_perms('admin_manage_fls') || (check_perms('users_mod') && $ownProfile))) {
    $set[] = "SupportFor = ?";
    $args[] = $SupportFor;
    $EditSummary[] = "First-Line Support status changed to \"$SupportFor\"";
}

$privChange = [];
if ($DisableAvatar != $Cur['DisableAvatar'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your avatar privileges have been ' . ($DisableAvatar ? 'removed' : 'restored');
    $set[] = "DisableAvatar = ?";
    $args[] = $DisableAvatar ? '1' : '0';
    $EditSummary[] = 'avatar privileges ' . disabled($DisableAvatar);
}

if ($DisableLeech != $Cur['can_leech'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your leeching privileges have been ' . ($DisableLeech ? 'removed' : 'restored');
    $set[] = "can_leech = ?";
    $args[] = $DisableLeech ? '1' : '0';
    $TrackerUserUpdates['can_leech'] = $DisableLeech;
    $EditSummary[] = "leeching status changed (".enabledStatus($Cur['can_leech'])." &rarr; ".enabledStatus($DisableLeech).")";
}

if ($DisableInvites != $Cur['DisableInvites'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your invite privileges have been ' . ($DisableInvites ? 'removed' : 'restored');
    $set[] = "DisableInvites = ?";
    $args[] = $DisableInvites ? '1' : '0';
    $EditSummary[] = 'invites privileges ' . disabled($DisableInvites);
}

if ($DisablePosting != $Cur['DisablePosting'] && check_perms('users_disable_posts')) {
    $privChange[] = 'Your forum posting privileges have been ' . ($DisablePosting ? 'removed' : 'restored');
    $set[] = "DisablePosting = ?";
    $args[] = $DisablePosting ? '1' : '0';
    $EditSummary[] = 'posting privileges ' . disabled($DisablePosting);
}

if ($DisablePoints != $Cur['DisablePoints'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your bonus points acquisition has been ' . ($DisablePoints ? 'revoked' : 'restored');
    $set[] = "DisablePoints = ?";
    $args[] = $DisablePoints ? '1' : '0';
    $EditSummary[] = 'points privileges ' . disabled($DisablePoints);
}

if ($DisableForums != $Cur['DisableForums'] && check_perms('users_disable_posts')) {
    $privChange[] = 'Your forum access has been ' . ($DisableForums ? 'revoked' : 'restored');
    $set[] = "DisableForums = ?";
    $args[] = $DisableForums ? '1' : '0';
    $EditSummary[] = 'forums privileges ' . disabled($DisableForums);
}

if ($DisableTagging != $Cur['DisableTagging'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your tagging privileges have been ' . ($DisableTagging ? 'removed' : 'restored');
    $set[] = "DisableTagging = ?";
    $args[] = $DisableTagging ? '1' : '0';
    $EditSummary[] = 'tagging privileges ' . disabled($DisableTagging);
}

if ($DisableUpload != $Cur['DisableUpload'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your upload privileges have been ' . ($DisableUpload ? 'removed' : 'restored');
    $set[] = "DisableUpload = ?";
    $args[] = $DisableUpload ? '1' : '0';
    $EditSummary[] = 'upload privileges ' . disabled($DisableUpload);
}

if ($DisableWiki != $Cur['DisableWiki'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your site editing privileges have been ' . ($DisableWiki ? 'removed' : 'restored');
    $set[] = "DisableWiki = ?";
    $args[] = $DisableWiki ? '1' : '0';
    $EditSummary[] = 'wiki privileges ' . disabled($DisableWiki);
}

if ($DisablePM != $Cur['DisablePM'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your private messate (PM) privileges have been ' . ($DisablePM ? 'removed' : 'restored');
    $set[] = "DisablePM = ?";
    $args[] = $DisablePM ? '1' : '0';
    $EditSummary[] = 'PM privileges ' . disabled($DisablePM);
}

if ($DisableIRC != $Cur['DisableIRC']) {
    $privChange[] = 'Your IRC privileges have been ' . ($DisableIRC ? 'removed' : 'restored');
    $set[] = "DisableIRC = ?";
    $args[] = $DisableIRC ? '1' : '0';
    $EditSummary[] = 'IRC privileges ' . disabled($DisableIRC);
}

if ($DisableRequests != $Cur['DisableRequests'] && check_perms('users_disable_any')) {
    $privChange[] = 'Your request privileges have been ' . ($DisableRequests ? 'removed' : 'restored');
    $set[] = "DisableRequests = ?";
    $args[] = $DisableRequests ? '1' : '0';
    $EditSummary[] = 'request privileges ' . disabled($DisableRequests);
}

if ($privChange && $UserReason) {
    sort($privChange);
    Misc::send_pm(
        $UserID, 0,
        count($privChange) == 1 ? $privChange[0] : 'Multiple privileges have changed on your account',
        G::$Twig->render('user/pm-privilege.twig', [
            'privs'  => $privChange,
            'reason' => $UserReason,
            'chan'   => BOT_DISABLED_CHAN,
            'url'    => site_url() . 'wiki.php?action=article&amp;id=5',
        ])
    );
    $EditSummary[] = 'PM sent';
}

if ($EnableUser != $Cur['Enabled'] && check_perms('users_disable_users')) {
    $EnableStr = 'account ' . translateUserStatus($Cur['Enabled']) . ' &rarr; ' . translateUserStatus($EnableUser);
    if ($EnableUser == '2') {
        Tools::disable_users($UserID, '', 1);
        $TrackerUserUpdates = [];
    } elseif ($EnableUser == '1') {
        $Cache->increment('stats_user_count');
        $VisibleTrIP = $Visible && $Cur['IP'] != '127.0.0.1' ? '1' : '0';
        Tracker::update_tracker('add_user', ['id' => $UserID, 'passkey' => $Cur['torrent_pass'], 'visible' => $VisibleTrIP]);
        if (($Cur['Downloaded'] == 0) || ($Cur['Uploaded'] / $Cur['Downloaded'] >= $Cur['RequiredRatio'])) {
            $CanLeech = 1;
            $set[] = "i.RatioWatchEnds = ?";
            $args[] = null;
            $set[] = "m.can_leech = ?";
            $args[] = '1';
            $set[] = "i.RatioWatchDownload = ?";
            $args[] = '0';
        } else {
            $EnableStr .= ' (Ratio: '.Format::get_ratio_html($Cur['Uploaded'], $Cur['Downloaded'], false).', RR: '.number_format($Cur['RequiredRatio'],2).')';
            if ($Cur['RatioWatchEnds']) {
                $set[] = "i.RatioWatchEnds = now()";
                $set[] = "i.RatioWatchDownload = m.Downloaded";
                $CanLeech = 0;
            }
            $TrackerUserUpdates['can_leech'] = 0;
        }
        $set[] = "i.BanReason = ?";
        $args[] = '0';
        $set[] = "Enabled = ?";
        $args[] = '1';
    }
    $Cache->replace_value("enabled_$UserID", $EnableUser, 0);
    $EditSummary[] = $EnableStr;
}

if ($ResetPasskey == 1 && check_perms('users_edit_reset_keys')) {
    $Passkey = randomString();
    $user->resetPasskeyHistory($Cur['torrent_pass'], $Passkey, '0.0.0.0');
    $Cache->delete_value('user_'.$Cur['torrent_pass']);
    $TrackerUserUpdates['passkey'] = $Passkey; // MUST come after the case for updating can_leech
    Tracker::update_tracker('change_passkey', ['oldpasskey' => $Cur['torrent_pass'], 'newpasskey' => $Passkey]);
    $set[] = "torrent_pass = ?";
    $args[] = $Passkey;
    $EditSummary[] = 'passkey reset';
}

if ($ResetAuthkey == 1 && check_perms('users_edit_reset_keys')) {
    $set[] = "Authkey = ?";
    $args[] = randomString();
    $EditSummary[] = 'authkey reset';
}

if ($SendHackedMail && check_perms('users_disable_any')) {
    Misc::send_email($HackedEmail, 'Your '.SITE_NAME.' account', G::$Twig->render('emails/hacked.twig', [
            'site_name' => SITE_NAME,
            'server'    => BOT_SERVER,
            'port'      => BOT_PORT,
            'port_ssl'  => BOT_PORT_SSL,
            'channel'   => BOT_DISABLED_CHAN,
        ]),
        'noreply'
    );
    Tools::disable_users($UserID, '', 1);
    $EditSummary[] = "hacked account email sent to $HackedEmail";
}

if ($MergeStatsFrom && check_perms('users_edit_ratio')) {
    $stats = $user->mergeLeechStats($MergeStatsFrom, $LoggedUser['Username']);
    if ($stats) {
        $merge = new Gazelle\User($stats['userId']);
        $merge->flushCache();
        $leechSet[] = "Uploaded = Uploaded + ?";
        $leechArgs[] = $stats['up'];
        $leechSet[] = "Downloaded = Downloaded + ?";
        $leechArgs[] = $stats['down'];
        $EditSummary[] = sprintf('leech stats (up: %s, down: %s, ratio: %s) merged from %s (%s) prior(up: %s, down: %s, ratio: %s)',
            Format::get_size($stats['up']), Format::get_size($stats['down']), Format::get_ratio($stats['up'], $stats['down']),
            $merge->url(), $MergeStatsFrom,
            Format::get_size($Cur['Uploaded']), Format::get_size($Cur['Downloaded']),
            Format::get_ratio($Cur['Uploaded'], $Cur['Downloaded'])
        );
    }
}

if ($ChangePassword && check_perms('users_edit_password')) {
    $set[] = "PassHash = ?";
    $args[] = Users::make_password_hash($Pass);
    $user->logout();
    $EditSummary[] = 'password reset';
}

if (!(count($set) || count($leechSet) || count($EditSummary))) {
    if (!$Reason) {
        header("Location: user.php?id=$UserID");
    } else {
        $EditSummary[] = 'notes added';
    }
}

if (count($EditSummary)) {
    $summary = implode(', ', $EditSummary) . ' by ' . $LoggedUser['Username'];
    $set[] = "AdminComment = ?";
    $args[] = sqltime() . ' - ' . ucfirst($summary) . ($Reason ? "\nReason: $Reason" : '') . "\n\n$AdminComment";
} elseif ($AdminComment != $Cur['AdminComment']) {
    $set[] = "AdminComment = ?";
    $args[] = $AdminComment;
}

if ($set) {
    $args[] = $UserID;
    $DB->prepared_query("
        UPDATE users_main AS m
        INNER JOIN users_info AS i ON (m.ID = i.UserID)
        SET " .  implode(', ', $set) . "
        WHERE m.ID = ?
        ", ...$args
    );
}

if ($leechSet) {
    $leechArgs[] = $UserID;
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
    $Bonus = new \Gazelle\Bonus;
    $Bonus->setPoints($UserID, $newBonusPoints);
}

if ($FLTokens != $Cur['FLTokens']) {
    $user->updateTokens($FLTokens);
}

if (count($TrackerUserUpdates) > 1) {
    Tracker::update_tracker('update_user', $TrackerUserUpdates);
}

$user->flushCache();

header("location: user.php?id=$UserID");

function translateUserStatus($Status) {
    switch ($Status) {
        case 0:
            return 'Unconfirmed';
        case 1:
            return 'Enabled';
        case 2:
            return 'Disabled';
        default:
            return $Status;
    }
}

function enabledStatus($Status) {
    switch ($Status) {
        case 0:
            return 'Disabled';
        case 1:
            return 'Enabled';
        default:
            return $Status;
    }
}

function disabled (bool $state) {
    return $state ? 'disabled' : 'enabled';
}

function classNames(array $classes) {
    return G::$DB->scalar("
        SELECT group_concat(Name SEPARATOR ', ')
        FROM permissions
        WHERE ID in (" . placeholders($classes) . ")
        ", ...$classes
    );
}
