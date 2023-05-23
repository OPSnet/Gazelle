<?php

authorize();

$userMan = new Gazelle\Manager\User;
if (!isset($_REQUEST['id'])) {
    $ownProfile = true;
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)$_REQUEST['id']);
    if (is_null($user)) {
        error(404);
    }
    $ownProfile = ($user->id() == $Viewer->id());
    if (!$ownProfile && !$Viewer->permitted('users_edit_profiles')) {
        Gazelle\Util\Irc::sendMessage(ADMIN_CHAN, 'User ' . $Viewer->label()
            . ' tried to edit ' . $user->publicLocation()
        );
        error(403);
    }
}
$db     = Gazelle\DB::DB();
$userId = $user->id();

$validator = new Gazelle\Util\Validator;
$validator->setFields([
    ['stylesheet', true, "number", "You forgot to select a stylesheet."],
    ['styleurl', false, "regex", "You did not enter a valid stylesheet URL.", ['regex' => CSS_REGEXP]],
    ['postsperpage', true, "number", "You forgot to select your posts per page option.", ['inarray' => [25, 50, 100]]],
    ['collagecovers', true, "number", "You forgot to select your collage option."],
    ['avatar', false, "regex", "You did not enter a valid avatar URL.", ['regex' => IMAGE_REGEXP]],
    ['email', true, "email", "You did not enter a valid email address."],
    ['irckey', false, "string", "You did not enter a valid IRC key. An IRC key must be between 6 and 32 characters long.", ['range' => [6, 32]]],
    ['new_pass_1', false, "regex",
        "You did not enter a valid password. A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol.",
        ['regex' => '/(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$|.{20,}/']
    ],
    ['new_pass_2', true, "compare", "Your passwords do not match.", ['comparefield' => 'new_pass_1']],
]);
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

$user->setUpdate('IRCKey', $_POST['irckey']);
$user->setUpdate('Paranoia', serialize($Paranoia));
$user->setUpdate('profile_info', substr($_POST['info'], 0, 20480));
$user->setUpdate('profile_title', trim($_POST['profile_title']));

$NewEmail = false;
if ($user->email() != trim($_POST['email'])) {
    if (!$Viewer->permitted('users_edit_profiles') && !$user->validatePassword($_POST['password'])) {
        error('You must enter your current password when changing your email address.');
    }
    $NewEmail = trim($_POST['email']);
    $user->setUpdate('Email', $NewEmail);
}

$ResetPassword = false;
if (!empty($_POST['password']) && !empty($_POST['new_pass_1']) && !empty($_POST['new_pass_2'])) {
    if (!$user->validatePassword($_POST['password'])) {
        error('You did not enter the correct password.');
    } else {
        if ($_POST['password'] == $_POST['new_pass_1']) {
            error('Your new password cannot be the same as your old password.');
        } else if ($_POST['new_pass_1'] !== $_POST['new_pass_2']) {
            error('You did not enter the same password twice.');
        }
        $user->setUpdate('PassHash', Gazelle\UserCreator::hashPassword($_POST['new_pass_1']));
        $ResetPassword = true;
    }
}

$avatar = trim($_POST['avatar']);
if ($avatar != $user->avatar()) {
    if ($Viewer->disableAvatar()) {
        error('Your avatar privileges have been revoked.');
    }
    $len = strlen($avatar);
    if ($len > 255) {
        error('Your avatar link is too long ($len characters, maximum allowed is 255).');
    }
    $user->setUpdate('Avatar', $avatar);
}

$Options['DisableGrouping2']    = (!empty($_POST['disablegrouping']) ? 0 : 1);
$Options['TorrentGrouping']     = (!empty($_POST['torrentgrouping']) ? 1 : 0);
$Options['PostsPerPage']        = (int)$_POST['postsperpage'];
$Options['CollageCovers']       = (int)$_POST['collagecovers'];
$Options['ShowTorFilter']       = (empty($_POST['showtfilter']) ? 0 : 1);
$Options['AutoSubscribe']       = (!empty($_POST['autosubscribe']) ? 1 : 0);
$Options['DisableSmileys']      = (int)isset($_POST['disablesmileys']);
$Options['EnableMatureContent'] = (!empty($_POST['enablematurecontent']) ? 1 : 0);
$Options['UseOpenDyslexic']     = (!empty($_POST['useopendyslexic']) ? 1 : 0);
$Options['Tooltipster']         = (!empty($_POST['usetooltipster']) ? 1 : 0);
$Options['DisableAvatars']      = (int)($_POST['disableavatars'] ?? 0);
$Options['Identicons']          = (int)($_POST['identicons'] ?? 0);
$Options['DisablePMAvatars']    = (!empty($_POST['disablepmavatars']) ? 1 : 0);
$Options['ListUnreadPMsFirst']  = (!empty($_POST['list_unread_pms_first']) ? 1 : 0);
$Options['ShowSnatched']        = (!empty($_POST['showsnatched']) ? 1 : 0);
$Options['DisableAutoSave']     = (!empty($_POST['disableautosave']) ? 1 : 0);
$Options['NoVoteLinks']         = (!empty($_POST['novotelinks']) ? 1 : 0);
$Options['CoverArt']            = (int)!empty($_POST['coverart']);
$Options['ShowExtraCovers']     = (int)!empty($_POST['show_extra_covers']);
$Options['AutoComplete']        = $_POST['autocomplete'];
$Options['HttpsTracker']        = (!empty($_POST['httpstracker']) ? 1 : 0);

foreach (['DefaultSearch', 'DisableFreeTorrentTop10'] as $opt) {
    if ($Viewer->option($opt)) {
        $Options[$opt] = $Viewer->option($opt);
    }
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

if ($Viewer->permitted('site_advanced_search')) {
    $Options['SearchType'] = (int)!empty($_POST['search_type_advanced']);
} else {
    unset($Options['SearchType']);
}

$NavItems = $userMan->forumNavItemList();
$UserNavItems = [];
foreach ($NavItems as $n) {
    if ($n['mandatory'] || (!empty($_POST["n_{$n['id']}"]) && $_POST["n_{$n['id']}"] == 'on')) {
        $UserNavItems[] = $n['id'];
    }
}

$LastFMUsername = trim($_POST['lastfm_username'] ?? '');
$OldFMUsername = (new Gazelle\Util\LastFM)->username($userId);
if (is_null($OldFMUsername) && $LastFMUsername !== '') {
    $db->prepared_query('
        INSERT INTO lastfm_users (ID, Username)
        VALUES (?, ?)
        ', $userId, $LastFMUsername
    );
    $Cache->delete_value("lastfm_username_$userId");
} elseif (!is_null($OldFMUsername) && $LastFMUsername !== '') {
    $db->prepared_query('
        UPDATE lastfm_users SET
            Username = ?
        WHERE ID = ?
        ', $LastFMUsername, $userId
    );
    $Cache->delete_value("lastfm_username_$userId");
} elseif (!is_null($OldFMUsername) && $LastFMUsername === '') {
    $db->prepared_query('
        DELETE FROM lastfm_users WHERE ID = ?
        ', $userId
    );
    $Cache->delete_value("lastfm_username_$userId");
}

/* transform
 *   'notifications_News_popup'
 *   'notifications_Blog_popup'
 *   'notifications_Inbox_traditional'
 * into
 *   [
 *     'News'  => 'popup',
 *     'Blog'  => 'popup',
 *     'Inbox' => 'traditional',
 *   ];
 */
$notification = array_values(
    array_map(
        fn($s) => explode('_', $s),
        preg_grep('/^notifications_[^_]+_/', array_keys($_POST))
    )
);
$settings = [];
foreach ($notification as $n) {
    $settings[$n[1]] = $n[2];
}
(new Gazelle\User\Notification($user))->save($settings, ["PushKey" => $_POST['pushkey']], $_POST['pushservice'], $_POST['pushdevice']);

foreach ([
    'admin-error-reporting' => isset($_POST['error_reporting']),
    'download-as-text'      => isset($_POST['downloadtext']),
    'hide-tags'             => isset($_POST['hidetags']),
    'hide-vote-history'     => !isset($_POST['pattr_hide_vote_history']),
    'hide-vote-recent'      => !isset($_POST['pattr_hide_vote_recent']),
    'no-fl-gifts'           => !isset($_POST['acceptfltoken']),
    'no-pm-delete-download' => !isset($_POST['notifyondeletedownloaded']),
    'no-pm-delete-seed'     => !isset($_POST['notifyondeleteseeding']),
    'no-pm-delete-snatch'   => !isset($_POST['notifyondeletesnatched']),
    'no-pm-unseeded-snatch' => !isset($_POST['notifyonunseededsnatch']),
    'no-pm-unseeded-upload' => !isset($_POST['notifyonunseededupload']),
] as $attr => $state) {
    $user->toggleAttr($attr, $state);
}

if ($ResetPassword) {
    $user->recordPasswordChange($Viewer->ipaddr());
}

if ($NewEmail) {
    $user->recordEmailChange($NewEmail, $Viewer->ipaddr());
}

if (isset($_POST['resetpasskey'])) {
    $OldPassKey = $user->announceKey();
    $NewPassKey = randomString();
    $ChangerIP = $Viewer->ipaddr();
    $user->setUpdate('torrent_pass', $NewPassKey);
    $db->prepared_query('
        INSERT INTO users_history_passkeys
               (UserID, OldPassKey, NewPassKey, ChangerIP)
        VALUES (?,      ?,          ?,          ?)
        ', $userId, $OldPassKey, $NewPassKey, $ChangerIP
    );

    (new Gazelle\Tracker)->update_tracker('change_passkey', ['oldpasskey' => $OldPassKey, 'newpasskey' => $NewPassKey]);
}

$db->prepared_query("
    UPDATE users_info SET
        NavItems = ?,
        SiteOptions = ?
    WHERE UserID = ?
    ", implode(',', $UserNavItems), serialize($Options), $userId
);

$user->modify();

$donor = new Gazelle\User\Donor($user);
if ($donor->isDonor()) {
    $donor->setVisible(isset($_POST['p_donor_stats']));
    $donor->setForumPrefix((string)$_POST['donor_title_prefix']);
    $donor->setForumSuffix((string)$_POST['donor_title_suffix']);
    $donor->setForumUseComma(isset($_POST['donor_title_comma']));
    $donor->updateAvatarHover((string)$_POST['second_avatar'])
        ->updateAvatarHoverText((string)$_POST['avatar_mouse_over_text'])
        ->updateIcon((string)$_POST['donor_icon_custom_url'])
        ->updateIconHoverText((string)$_POST['donor_icon_mouse_over_text'])
        ->updateIconLink((string)$_POST['donor_icon_link'])
        ->updateProfileInfo(1, (string)$_POST['profile_info_1'])
        ->updateProfileInfo(2, (string)$_POST['profile_info_2'])
        ->updateProfileInfo(3, (string)$_POST['profile_info_3'])
        ->updateProfileInfo(4, (string)$_POST['profile_info_4'])
        ->updateProfileTitle(1, (string)$_POST['profile_title_1'])
        ->updateProfileTitle(2, (string)$_POST['profile_title_2'])
        ->updateProfileTitle(3, (string)$_POST['profile_title_3'])
        ->updateProfileTitle(4, (string)$_POST['profile_title_4'])
        ->modify();
}

$user->flush();

(new Gazelle\User\Stylesheet($user))->modifyInfo((int)$_POST['stylesheet'], $_POST['styleurl']);

if ($ResetPassword) {
    $user->logoutEverywhere();
}

header('Location: ' . $user->location() . '&action=edit');
