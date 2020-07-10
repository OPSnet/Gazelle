<?php

use \Gazelle\Manager\Notification;

authorize();

$UserID = empty($_REQUEST['userid']) ? $LoggedUser['ID'] : (int)$_REQUEST['userid'];
if ($UserID < 1) {
    error(404);
}
$donorMan = new Gazelle\Manager\Donation;

//For this entire page, we should generally be using $UserID not $LoggedUser['ID'] and $U[] not $LoggedUser[]
$U = Users::user_info($UserID);
if (!$U) {
    error(404);
}
$UH = Users::user_heavy_info($UserID);

$Permissions = Permissions::get_permissions($U['PermissionID']);
if ($UserID != $LoggedUser['ID'] && !check_perms('users_edit_profiles', $Permissions['Class'])) {
    send_irc('PRIVMSG '.ADMIN_CHAN.' :User '.$LoggedUser['Username'].' ('.site_url().'user.php?id='.$LoggedUser['ID'].') just tried to edit the profile of '.site_url().'user.php?id='.$_REQUEST['userid']);
    error(403);
}

$Val->SetFields('stylesheet', 1, "number", "You forgot to select a stylesheet.");
$Val->SetFields('styleurl', 0, "regex", "You did not enter a valid stylesheet URL.", ['regex' => '/^'.CSS_REGEX.'$/i']);
$Val->SetFields('postsperpage', 1, "number", "You forgot to select your posts per page option.", ['inarray' => [25, 50, 100]]);
$Val->SetFields('collagecovers', 1, "number", "You forgot to select your collage option.");
$Val->SetFields('avatar', 0, "regex", "You did not enter a valid avatar URL.", ['regex' => "/^".IMAGE_REGEX."$/i"]);
$Val->SetFields('email', 1, "email", "You did not enter a valid email address.");
$Val->SetFields('irckey', 0, "string", "You did not enter a valid IRC key. An IRC key must be between 6 and 32 characters long.", ['minlength' => 6, 'maxlength' => 32]);
$Val->SetFields('new_pass_1', 0, "regex", "You did not enter a valid password. A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol.", ['regex' => '/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/']);
$Val->SetFields('new_pass_2', 1, "compare", "Your passwords do not match.", ['comparefield' => 'new_pass_1']);
if (check_perms('site_advanced_search')) {
    $Val->SetFields('searchtype', 1, "number", "You forgot to select your default search preference.", ['minlength' => 0, 'maxlength' => 1]);
}

$Err = $Val->ValidateForm($_POST);
if ($Err) {
    error($Err);
    header("Location: user.php?action=edit&userid=$UserID");
    die();
}

// Begin building $Paranoia
// Reduce the user's input paranoia until it becomes consistent
if (isset($_POST['p_uniquegroups_l'])) {
    $_POST['p_uploads_l'] = 'on';
    $_POST['p_uploads_c'] = 'on';
}

if (isset($_POST['p_uploads_l'])) {
    $_POST['p_uniquegroups_l'] = 'on';
    $_POST['p_uniquegroups_c'] = 'on';
    $_POST['p_perfectflacs_l'] = 'on';
    $_POST['p_perfectflacs_c'] = 'on';
    $_POST['p_artistsadded'] = 'on';
}

if (isset($_POST['p_collagecontribs_l'])) {
    $_POST['p_collages_l'] = 'on';
    $_POST['p_collages_c'] = 'on';
}

if (isset($_POST['p_snatched_c']) && isset($_POST['p_seeding_c']) && isset($_POST['p_downloaded'])) {
    $_POST['p_requiredratio'] = 'on';
}

// if showing exactly 2 of stats, show all 3 of stats
$StatsShown = 0;
$Stats = ['downloaded', 'uploaded', 'ratio'];
foreach ($Stats as $S) {
    if (isset($_POST["p_$S"])) {
        $StatsShown++;
    }
}

if ($StatsShown == 2) {
    foreach ($Stats as $S) {
        $_POST["p_$S"] = 'on';
    }
}

$Paranoia = [];
$Checkboxes = ['downloaded', 'uploaded', 'ratio', 'bonuspoints', 'lastseen', 'requiredratio', 'invitedcount', 'artistsadded', 'notifications'];
foreach ($Checkboxes as $C) {
    if (!isset($_POST["p_$C"])) {
        $Paranoia[] = $C;
    }
}

$SimpleSelects = ['torrentcomments', 'collages', 'collagecontribs', 'uploads', 'uniquegroups', 'perfectflacs', 'seeding', 'leeching', 'snatched'];
foreach ($SimpleSelects as $S) {
    if (!isset($_POST["p_$S".'_c']) && !isset($_POST["p_$S".'_l'])) {
        // Very paranoid - don't show count or list
        $Paranoia[] = "$S+";
    } elseif (!isset($_POST["p_$S".'_l'])) {
        // A little paranoid - show count, don't show list
        $Paranoia[] = $S;
    }
}

$Bounties = ['requestsfilled', 'requestsvoted'];
foreach ($Bounties as $B) {
    if (isset($_POST["p_$B".'_list'])) {
        $_POST["p_$B".'_count'] = 'on';
        $_POST["p_$B".'_bounty'] = 'on';
    }
    if (!isset($_POST["p_$B".'_list'])) {
        $Paranoia[] = $B.'_list';
    }
    if (!isset($_POST["p_$B".'_count'])) {
        $Paranoia[] = $B.'_count';
    }
    if (!isset($_POST["p_$B".'_bounty'])) {
        $Paranoia[] = $B.'_bounty';
    }
}

if (!isset($_POST['p_donor_heart'])) {
    $Paranoia[] = 'hide_donor_heart';
}

if (isset($_POST['p_donor_stats'])) {
    $donorMan->show($UserID);
} else {
    $donorMan->hide($UserID);
}

// End building $Paranoia

// Email change
$DB->prepared_query('
    SELECT Email
    FROM users_main
    WHERE ID = ?
    ', $UserID
);

list($CurEmail) = $DB->next_record();
if ($CurEmail != $_POST['email']) {
    if (!check_perms('users_edit_profiles')) { // Non-admins have to authenticate to change email
        $DB->prepared_query('
            SELECT PassHash
            FROM users_main
            WHERE ID = ?
            ', $UserID
        );

        list($PassHash) = $DB->next_record();
        if (!Users::check_password($_POST['cur_pass'], $PassHash)) {
            $Err = 'You did not enter the correct password.';
        }
    }
    if (!$Err) {
        $NewEmail = $_POST['email'];

        //This piece of code will update the time of their last email change to the current time *not* the current change.
        $ChangerIP = $LoggedUser['IP'];
        $DB->prepared_query("
            UPDATE users_history_emails
            SET Time = now()
            WHERE UserID = ? 
                AND Time = '0000-00-00 00:00:00'
            ", $UserID
        );

        $DB->prepared_query("
            INSERT INTO users_history_emails
                (UserID, Email, IP, Time)
            VALUES
                (?,      ?,     ?, '0000-00-00 00:00:00')
            ", $UserID, $NewEmail, $_SERVER['REMOTE_ADDR']
        );
    } else {
        error($Err);
        header("Location: user.php?action=edit&userid=$UserID");
        die();
    }
}
//End email change

if (!$Err && !empty($_POST['cur_pass']) && !empty($_POST['new_pass_1']) && !empty($_POST['new_pass_2'])) {
    $DB->prepared_query('
        SELECT PassHash
        FROM users_main
        WHERE ID = ? 
        ', $UserID
    );

    list($PassHash) = $DB->next_record();

    if (Users::check_password($_POST['cur_pass'], $PassHash)) {
        if ($_POST['cur_pass'] == $_POST['new_pass_1']) {
            $Err = 'Your new password cannot be the same as your old password.';
        } else if ($_POST['new_pass_1'] !== $_POST['new_pass_2']) {
            $Err = 'You did not enter the same password twice.';
        }
        else {
            $ResetPassword = true;
        }
    } else {
        $Err = 'You did not enter the correct password.';
    }
}

if ($LoggedUser['DisableAvatar'] && $_POST['avatar'] != $U['Avatar']) {
    $Err = 'Your avatar privileges have been revoked.';
}

if ($Err) {
    error($Err);
    header("Location: user.php?action=edit&userid=$UserID");
    die();
}

if (!empty($LoggedUser['DefaultSearch'])) {
    $Options['DefaultSearch'] = $LoggedUser['DefaultSearch'];
}
$Options['DisableGrouping2']    = (!empty($_POST['disablegrouping']) ? 0 : 1);
$Options['TorrentGrouping']     = (!empty($_POST['torrentgrouping']) ? 1 : 0);
$Options['PostsPerPage']        = (int)$_POST['postsperpage'];
$Options['CollageCovers']       = (empty($_POST['collagecovers']) ? 0 : $_POST['collagecovers']);
$Options['ShowTorFilter']       = (empty($_POST['showtfilter']) ? 0 : 1);
$Options['ShowTags']            = (!empty($_POST['showtags']) ? 1 : 0);
$Options['AutoSubscribe']       = (!empty($_POST['autosubscribe']) ? 1 : 0);
$Options['DisableSmileys']      = (!empty($_POST['disablesmileys']) ? 1 : 0);
$Options['EnableMatureContent'] = (!empty($_POST['enablematurecontent']) ? 1 : 0);
$Options['UseOpenDyslexic']     = (!empty($_POST['useopendyslexic']) ? 1 : 0);
$Options['Tooltipster']         = (!empty($_POST['usetooltipster']) ? 1 : 0);
$Options['AutoloadCommStats']   = (check_perms('users_mod') && !empty($_POST['autoload_comm_stats']) ? 1 : 0);
$Options['DisableAvatars']      = (!empty($_POST['disableavatars']) ? (int)$_POST['disableavatars'] : 0);
$Options['Identicons']          = (!empty($_POST['identicons']) ? (int)$_POST['identicons'] : 0);
$Options['DisablePMAvatars']    = (!empty($_POST['disablepmavatars']) ? 1 : 0);
$Options['NotifyOnQuote']       = (!empty($_POST['notifications_Quotes_popup']) ? 1 : 0);
$Options['ListUnreadPMsFirst']  = (!empty($_POST['list_unread_pms_first']) ? 1 : 0);
$Options['ShowSnatched']        = (!empty($_POST['showsnatched']) ? 1 : 0);
$Options['DisableAutoSave']     = (!empty($_POST['disableautosave']) ? 1 : 0);
$Options['AcceptFL']            = (!empty($_POST['acceptfltoken']) ? 1 : 0);
$Options['NoVoteLinks']         = (!empty($_POST['novotelinks']) ? 1 : 0);
$Options['CoverArt']            = (int)!empty($_POST['coverart']);
$Options['ShowExtraCovers']     = (int)!empty($_POST['show_extra_covers']);
$Options['AutoComplete']        = (int)$_POST['autocomplete'];
$Options['HttpsTracker']        = (!empty($_POST['httpstracker']) ? 1 : 0);

if (isset($LoggedUser['DisableFreeTorrentTop10'])) {
    $Options['DisableFreeTorrentTop10'] = $LoggedUser['DisableFreeTorrentTop10'];
}

if (!empty($_POST['sorthide'])) {
    $JSON = json_decode($_POST['sorthide']);
    foreach ($JSON as $J) {
        $E = explode('_', $J);
        $Options['SortHide'][$E[0]] = $E[1];
    }
} else {
    $Options['SortHide'] = [];
}

if (check_perms('site_advanced_search')) {
    $Options['SearchType'] = $_POST['searchtype'];
} else {
    unset($Options['SearchType']);
}

// These are all enums of '0' or '1'
$DownloadAlt = isset($_POST['downloadalt']) ? '1' : '0';
$UnseededAlerts = isset($_POST['unseededalerts']) ? '1' : '0';
$NotifyOnDeleteSeeding = (!empty($_POST['notifyondeleteseeding']) ? '1' : '0');
$NotifyOnDeleteSnatched = (!empty($_POST['notifyondeletesnatched']) ? '1' : '0');
$NotifyOnDeleteDownloaded = (!empty($_POST['notifyondeletedownloaded']) ? '1' : '0');

$NavItems = Users::get_nav_items();
$UserNavItems = [];
foreach ($NavItems as $n) {
    list($ID, $Key, $Title, $Target, $Tests, $TestUser, $Mandatory) = array_values($n);
    if ($Mandatory || (!empty($_POST["n_$Key"]) && $_POST["n_$Key"] == 'on')) {
        $UserNavItems[] = $ID;
    }
}
$UserNavItems = implode(',', $UserNavItems);

$LastFMUsername = $_POST['lastfm_username'];
$OldLastFMUsername = '';
$DB->prepared_query('
    SELECT username
    FROM lastfm_users
    WHERE ID = ?
    ', $UserID
);

if ($DB->has_results()) {
    list($OldLastFMUsername) = $DB->next_record();
    if ($OldLastFMUsername != $LastFMUsername) {
        if (empty($LastFMUsername)) {
            $DB->prepared_query('
                DELETE FROM lastfm_users
                WHERE ID = ?
                ', $UserID
            );
        } else {
            $DB->prepared_query('
                UPDATE lastfm_users
                SET Username = ?
                WHERE ID = ?
                ', $LastFMUsername, $UserID
            );
        }
    }
} elseif (!empty($LastFMUsername)) {
    $DB->prepared_query('
        INSERT INTO lastfm_users (ID, Username)
        VALUES (?, ?)
        ', $UserID, $LastFMUsername
    );
}
G::$Cache->delete_value("lastfm_username_$UserID");

Users::toggleAcceptFL($UserID, $Options['AcceptFL']);
$donorMan->updateReward($UserID);
$notification = new Notification($UserID);
// A little cheat technique, gets all keys in the $_POST array starting with 'notifications_'
$settings = array_intersect_key($_POST, array_flip(preg_grep('/^notifications_/', array_keys($_POST))));
$notification->save($settings, ["PushKey" => $_POST['pushkey']], (int)$_POST['pushservice'], $_POST['pushdevice']);

// Information on how the user likes to download torrents is stored in cache
if ($DownloadAlt != $UH['DownloadAlt'] || $Options['HttpsTracker'] != $UH['HttpsTracker']) {
    $Cache->delete_value('user_'.$UH['torrent_pass']);
}

$Cache->begin_transaction("user_info_$UserID");
$Cache->update_row(false, [
        'Avatar' => display_str($_POST['avatar']),
        'Paranoia' => $Paranoia
]);
$Cache->commit_transaction(0);

$Cache->begin_transaction("user_info_heavy_$UserID");
$Cache->update_row(false, [
        'StyleID' => $_POST['stylesheet'],
        'StyleURL' => display_str($_POST['styleurl']),
        'DownloadAlt' => $DownloadAlt,
        'NavItems' => explode(',', $UserNavItems)
        ]);
$Cache->update_row(false, $Options);
$Cache->commit_transaction(0);

$SQL = '
    UPDATE users_main AS m
        JOIN users_info AS i ON m.ID = i.UserID
    SET
        i.StyleID = ?,
        i.StyleURL = ?,
        i.Avatar = ?,
        i.SiteOptions = ?,
        i.NotifyOnQuote = ?,
        i.Info = ?,
        i.InfoTitle = ?,
        i.DownloadAlt = ?,
        i.UnseededAlerts = ?,
        i.NotifyOnDeleteSeeding = ?,
        i.NotifyOnDeleteSnatched = ?,
        i.NotifyOnDeleteDownloaded = ?,
        m.Email = ?,
        m.IRCKey = ?,
        m.Paranoia = ?,
        i.NavItems = ?
';

$Params = [
    $_POST['stylesheet'],
    $_POST['styleurl'],
    $_POST['avatar'],
    serialize($Options),
    strval($Options['NotifyOnQuote']),
    $_POST['info'],
    $_POST['profile_title'],
    $DownloadAlt,
    $UnseededAlerts,
    $NotifyOnDeleteSeeding,
    $NotifyOnDeleteSnatched,
    $NotifyOnDeleteDownloaded,
    $_POST['email'],
    $_POST['irckey'],
    serialize($Paranoia),
    $UserNavItems
];

if ($ResetPassword) {
    $SQL .= ',m.PassHash = ?';
    $Params[] = Users::make_password_hash($_POST['new_pass_1']);
    $DB->prepared_query('
        INSERT INTO users_history_passwords
            (UserID, ChangerIP, ChangeTime)
        VALUES
            (?, ?, now())
        ', $UserID, $LoggedUser['IP']
    );
}

if (isset($_POST['resetpasskey'])) {
    $UserInfo = Users::user_heavy_info($UserID);
    $OldPassKey = $UserInfo['torrent_pass'];
    $NewPassKey = randomString();
    $ChangerIP = $LoggedUser['IP'];
    $SQL .= ',m.torrent_pass = ?';
    $Params[] = $NewPassKey;
    $DB->prepared_query('
        INSERT INTO users_history_passkeys
            (UserID, OldPassKey, NewPassKey, ChangerIP, ChangeTime)
        VALUES
            (?, ?, ?, ?, now())
        ', $UserID, $OldPassKey, $NewPassKey, $ChangerIP
    );
    $Cache->begin_transaction("user_info_heavy_$UserID");
    $Cache->update_row(false, ['torrent_pass' => $NewPassKey]);
    $Cache->commit_transaction(0);
    $Cache->delete_value("user_$OldPassKey");

    Tracker::update_tracker('change_passkey', ['oldpasskey' => $OldPassKey, 'newpasskey' => $NewPassKey]);
}

$SQL .= ' WHERE m.ID = ?';
$Params[] = $UserID;

$DB->prepared_query($SQL, ...$Params);

if ($ResetPassword) {
    logout_all_sessions($UserID);
}

header("Location: user.php?action=edit&userid=$UserID");
