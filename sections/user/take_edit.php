<?php

use Gazelle\Util\Irc;

authorize();

$userMan = new Gazelle\Manager\User;
$user = $userMan->findById((int)($_REQUEST['userid'] ?? $LoggedUser['ID']));
if (is_null($user)) {
    error(404);
}
$userId = $user->id();
if ($userId == $LoggedUser['ID']) {
    $ownProfile = true;
} else {
    if (!check_perms('admin_bp_history')) {
        error(403);
    }
    $ownProfile = false;
}

if (!$ownProfile && !check_perms('users_edit_profiles')) {
    Irc::sendRaw('PRIVMSG ' . ADMIN_CHAN . ' :User ' . $LoggedUser['Username']
        . ' (' . SITE_URL . '/user.php?id=' . $LoggedUser['ID']
        . ') just tried to edit the profile of ' . SITE_URL . '/user . php?id=' . $_REQUEST['userid']);
    error(403);
}

$validator = new Gazelle\Util\Validator;
$validator->setFields([
    ['stylesheet', 1, "number", "You forgot to select a stylesheet."],
    ['styleurl', 0, "regex", "You did not enter a valid stylesheet URL.", ['regex' => '/^'.CSS_REGEX.'$/i']],
    ['postsperpage', 1, "number", "You forgot to select your posts per page option.", ['inarray' => [25, 50, 100]]],
    ['collagecovers', 1, "number", "You forgot to select your collage option."],
    ['avatar', 0, "regex", "You did not enter a valid avatar URL.", ['regex' => "/^".IMAGE_REGEX."$/i"]],
    ['email', 1, "email", "You did not enter a valid email address."],
    ['irckey', 0, "string", "You did not enter a valid IRC key. An IRC key must be between 6 and 32 characters long.", ['range' => [6, 32]]],
    ['new_pass_1', 0, "regex",
        "You did not enter a valid password. A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol.",
        ['regex' => '/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/']
    ],
    ['new_pass_2', 1, "compare", "Your passwords do not match.", ['comparefield' => 'new_pass_1']],
]);
if (check_perms('site_advanced_search')) {
    $validator->setField('searchtype', 1, "number", "You forgot to select your default search preference.", ['range' => [0, 1]]);
}
if (!$validator->validate($_POST)) {
    error($validator->errorMessage());
}

// Begin building $Paranoia
// Reduce the user's input paranoia until it becomes consistent
if (isset($_POST['p_l_uniquegroups'])) {
    $_POST['p_l_uploads'] = 'on';
    $_POST['p_c_uploads'] = 'on';
}

if (isset($_POST['p_l_uploads'])) {
    $_POST['p_l_uniquegroups'] = 'on';
    $_POST['p_l_perfectflacs'] = 'on';
    $_POST['p_c_uniquegroups'] = 'on';
    $_POST['p_c_perfectflacs'] = 'on';
    $_POST['p_artistsadded'] = 'on';
}

if (isset($_POST['p_collagecontribs'])) {
    $_POST['p_l_collages'] = 'on';
    $_POST['p_c_collages'] = 'on';
}

if (isset($_POST['p_c_snatched']) && isset($_POST['p_c_seeding']) && isset($_POST['p_downloaded'])) {
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

foreach (['torrentcomments', 'collages', 'collagecontribs', 'uploads', 'uniquegroups', 'perfectflacs', 'seeding', 'leeching', 'snatched'] as $S) {
    if (!isset($_POST["p_l_$S"])) {
        $Paranoia[] = isset($_POST["p_c_$S"]) ? $S : "$S+";
    }
}

foreach (['requestsfilled', 'requestsvoted'] as $bounty) {
    if (isset($_POST["p_list_$bounty"])) {
        $_POST["p_count_$bounty"] = 'on';
        $_POST["p_bounty_$bounty"] = 'on';
    }
    foreach (['list', 'count', 'bounty'] as $item) {
        if (!isset($_POST["p_{$item}_{$bounty}"])) {
            $Paranoia[] = "{$bounty}_{$item}";
        }
    }
}

if (!isset($_POST['p_donor_heart'])) {
    $Paranoia[] = 'hide_donor_heart';
}
// End building $Paranoia

$user->updateReward(
    array_map('trim',
        array_filter($_POST,
        function ($key) {
            return in_array($key, [
                'second_avatar', 'avatar_mouse_over_text',
                'donor_icon_mouse_over_text', 'donor_icon_link', 'donor_icon_custom_url',
                'donor_title_prefix', 'donor_title_suffix', 'donor_title_comma',
                'profile_title_1', 'profile_info_1',
                'profile_title_2', 'profile_info_2',
                'profile_title_3', 'profile_info_3',
                'profile_title_4', 'profile_info_4',
            ]);
        }, ARRAY_FILTER_USE_KEY)
    )
);

if (isset($_POST['p_donor_stats'])) {
    $userMan->showDonor($user);
} else {
    $userMan->hideDonor($user);
}

$CurEmail = $user->email();
if ($CurEmail != $_POST['email']) {
    // Non-admins have to authenticate to change email
    if (!check_perms('users_edit_profiles') && !$user->validatePassword($_POST['cur_pass'])) {
        error('You did not enter the correct password.');
    }
    $NewEmail = $_POST['email'];
    $DB->prepared_query("
        INSERT INTO users_history_emails
               (UserID, Email, IP)
        VALUES (?,      ?,     ?)
        ", $userId, $NewEmail, $_SERVER['REMOTE_ADDR']
    );
}

$ResetPassword = false;
if (!empty($_POST['cur_pass']) && !empty($_POST['new_pass_1']) && !empty($_POST['new_pass_2'])) {
    if (!$user->validatePassword($_POST['cur_pass'])) {
        error('You did not enter the correct password.');
    } else {
        if ($_POST['cur_pass'] == $_POST['new_pass_1']) {
            error('Your new password cannot be the same as your old password.');
        } else if ($_POST['new_pass_1'] !== $_POST['new_pass_2']) {
            error('You did not enter the same password twice.');
        }
        $ResetPassword = true;
    }
}

if ($LoggedUser['DisableAvatar'] && $_POST['avatar'] != $user->avatar()) {
    error('Your avatar privileges have been revoked.');
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
$Options['AutoComplete']        = $_POST['autocomplete'];
$Options['HttpsTracker']        = (!empty($_POST['httpstracker']) ? 1 : 0);

if (isset($LoggedUser['DisableFreeTorrentTop10'])) {
    $Options['DisableFreeTorrentTop10'] = $LoggedUser['DisableFreeTorrentTop10'];
}

if (empty($_POST['sorthide'])) {
    $Options['SortHide'] = [];
} else {
    $JSON = json_decode($_POST['sorthide']);
    foreach ($JSON as $J) {
        $E = explode('_', $J);
        $Options['SortHide'][$E[0]] = $E[1];
    }
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
    if ($n['mandatory'] || (!empty($_POST["n_{$n['id']}"]) && $_POST["n_{$n['id']}"] == 'on')) {
        $UserNavItems[] = $n['id'];
    }
}
$UserNavItems = implode(',', $UserNavItems);

$LastFMUsername = $_POST['lastfm_username'];
$OldLastFMUsername = '';
$DB->prepared_query('
    SELECT username FROM lastfm_users WHERE ID = ?
    ', $userId
);

if ($DB->has_results()) {
    [$OldLastFMUsername] = $DB->next_record();
    if ($OldLastFMUsername != $LastFMUsername) {
        if (empty($LastFMUsername)) {
            $DB->prepared_query('
                DELETE FROM lastfm_users WHERE ID = ?
                ', $userId
            );
        } else {
            $DB->prepared_query('
                UPDATE lastfm_users SET
                    Username = ?
                WHERE ID = ?
                ', $LastFMUsername, $userId
            );
        }
        $Cache->delete_value("lastfm_username_$userId");
    }
} elseif (!empty($LastFMUsername)) {
    $DB->prepared_query('
        INSERT INTO lastfm_users (ID, Username)
        VALUES (?, ?)
        ', $userId, $LastFMUsername
    );
    $Cache->delete_value("lastfm_username_$userId");
}

$user->toggleAcceptFL($Options['AcceptFL']);
(new Gazelle\Manager\Notification($userId))
    ->save(
        array_intersect_key($_POST, array_flip(preg_grep('/^notifications_/', array_keys($_POST)))),
        ["PushKey" => $_POST['pushkey']],
        $_POST['pushservice'],
        $_POST['pushdevice']
    );

// Information on how the user likes to download torrents is stored in cache
if ($DownloadAlt != $user->option('DownloadAlt') || $Options['HttpsTracker'] != $user->option('HttpsTracker')) {
    $Cache->delete_value('user_' . $user->announceKey());
}

$SQL = "
UPDATE users_main AS m
INNER JOIN users_info AS i ON (m.ID = i.UserID) SET
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
";

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
    $Params[] = Gazelle\UserCreator::hashPassword($_POST['new_pass_1']);
    $DB->prepared_query('
        INSERT INTO users_history_passwords
               (UserID, ChangerIP, ChangeTime)
        VALUES (?,      ?,         now())
        ', $userId, $LoggedUser['IP']
    );
}

if (isset($_POST['resetpasskey'])) {
    $OldPassKey = $user->announceKey();
    $NewPassKey = randomString();
    $ChangerIP = $LoggedUser['IP'];
    $SQL .= ',m.torrent_pass = ?';
    $Params[] = $NewPassKey;
    $DB->prepared_query('
        INSERT INTO users_history_passkeys
               (UserID, OldPassKey, NewPassKey, ChangerIP)
        VALUES (?,      ?,          ?,          ?)
        ', $userId, $OldPassKey, $NewPassKey, $ChangerIP
    );
    $Cache->delete_value("user_$OldPassKey");

    (new Gazelle\Tracker)->update_tracker('change_passkey', ['oldpasskey' => $OldPassKey, 'newpasskey' => $NewPassKey]);
}

$SQL .= ' WHERE m.ID = ?';
$Params[] = $userId;

$DB->prepared_query($SQL, ...$Params);

$user->flush();

if ($ResetPassword) {
    $user->logoutEverywhere();
}

header("Location: user.php?action=edit&userid=$userId");
