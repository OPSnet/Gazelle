<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

authorize();

$irc     = new Gazelle\Util\Irc();
$userMan = new Gazelle\Manager\User();
if (!isset($_REQUEST['id'])) {
    $ownProfile = true;
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)$_REQUEST['id']);
    if (is_null($user)) {
        error(404);
    }
    $ownProfile = ($user->id() === $Viewer->id());
    if (!$ownProfile && !$Viewer->permitted('users_edit_profiles')) {
        $irc::sendMessage(IRC_CHAN_MOD, "User {$Viewer->label()} tried to edit {$user->publicLocation()}");
        error(403);
    }
}

$validator = new Gazelle\Util\Validator();
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
        ['regex' => \Gazelle\Util\PasswordCheck::REGEXP]
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

$user->setField('IRCKey', $_POST['irckey']);
$user->setField('Paranoia', serialize($Paranoia));
$user->setField('profile_info', substr($_POST['info'], 0, 20480));
$user->setField('profile_title', trim($_POST['profile_title']));

$NewEmail = false;
if ($user->email() != trim($_POST['email'])) {
    if (!$Viewer->permitted('users_edit_profiles') && !$user->validatePassword($_POST['password'])) {
        error('You must enter your current password when changing your email address.');
    }
    if ($ownProfile && !\Gazelle\Util\PasswordCheck::checkPasswordStrength($_POST['password'], $user)) {
        // same corner case as with changing passwords, see comment there
        $user->addStaffNote("forced logout because of weak/compromised password")->modify();
        $user->logoutEverywhere();
        echo $Twig->render('login/weak-password.twig');
        exit;
    }
    $NewEmail = trim($_POST['email']);
    $user->setField('Email', $NewEmail);
}

$avatar = trim($_POST['avatar']);
if ($avatar != $user->avatar()) {
    if ($ownProfile && $user->disableAvatar()) {
        error('Your avatar privileges have been revoked.');
    }
    $len = strlen($avatar);
    if ($len > 255) {
        error('Your avatar link is too long ($len characters, maximum allowed is 255).');
    }
    $user->setField('Avatar', $avatar);
}

$ResetPassword = false;
if (!empty($_POST['password']) && !empty($_POST['new_pass_1']) && !empty($_POST['new_pass_2'])) {
    if (!$user->validatePassword($_POST['password'])) {
        error('You did not enter the correct password.');
    } elseif (!\Gazelle\Util\PasswordCheck::checkPasswordStrength($_POST['password'], $user)) {
        // This is a corner case: the user already has an active session and is trying to change their password.
        // They would not have been able to log in with this password and since it is weak it might as well be
        // an attacker that happens to have the compromised password, and an old login session, but no access to
        // the email account. Force password reset by email.
        $user->addStaffNote("forced logout because of weak/compromised password")->modify();
        $user->logoutEverywhere();
        echo $Twig->render('login/weak-password.twig');
        exit;
    } else {
        if (!\Gazelle\Util\PasswordCheck::checkPasswordStrength($_POST['new_pass_1'], $user)) {
            error(\Gazelle\Util\PasswordCheck::ERROR_MSG);
        }
        if ($_POST['password'] == $_POST['new_pass_1']) {
            error('Your new password cannot be the same as your old password.');
        } elseif ($_POST['new_pass_1'] !== $_POST['new_pass_2']) {
            error('You did not enter the same password twice.');
        }
        $user->updatePassword($_POST['new_pass_1'], true);
        $ResetPassword = true;
    }
}

$option['DisableGrouping2']    = (!empty($_POST['disablegrouping']) ? 0 : 1);
$option['TorrentGrouping']     = (!empty($_POST['torrentgrouping']) ? 1 : 0);
$option['PostsPerPage']        = (int)$_POST['postsperpage'];
$option['CollageCovers']       = (int)$_POST['collagecovers'];
$option['ShowTorFilter']       = (empty($_POST['showtfilter']) ? 0 : 1);
$option['AutoSubscribe']       = (!empty($_POST['autosubscribe']) ? 1 : 0);
$option['DisableSmileys']      = (int)isset($_POST['disablesmileys']);
$option['EnableMatureContent'] = (!empty($_POST['enablematurecontent']) ? 1 : 0);
$option['UseOpenDyslexic']     = (!empty($_POST['useopendyslexic']) ? 1 : 0);
$option['DisableAvatars']      = (int)($_POST['disableavatars'] ?? 0);
$option['Identicons']          = (int)($_POST['identicons'] ?? 0);
$option['DisablePMAvatars']    = (!empty($_POST['disablepmavatars']) ? 1 : 0);
$option['ListUnreadPMsFirst']  = (!empty($_POST['list_unread_pms_first']) ? 1 : 0);
$option['ShowSnatched']        = (!empty($_POST['showsnatched']) ? 1 : 0);
$option['DisableAutoSave']     = (!empty($_POST['disableautosave']) ? 1 : 0);
$option['NoVoteLinks']         = (!empty($_POST['novotelinks']) ? 1 : 0);
$option['CoverArt']            = (int)!empty($_POST['coverart']);
$option['ShowExtraCovers']     = (int)!empty($_POST['show_extra_covers']);
$option['AutoComplete']        = $_POST['autocomplete'];
$option['HttpsTracker']        = (!empty($_POST['httpstracker']) ? 1 : 0);

foreach (['DefaultSearch', 'DisableFreeTorrentTop10'] as $opt) {
    if ($user->option($opt)) {
        $option[$opt] = $user->option($opt);
    }
}

if (empty($_POST['sorthide'])) {
    $option['SortHide'] = [];
} else {
    $JSON = json_decode($_POST['sorthide']);
    foreach ($JSON as $J) {
        $E = explode('_', $J);
        $option['SortHide'][$E[0]] = $E[1];
    }
}

if ($Viewer->permitted('site_advanced_search')) {
    $option['SearchType'] = (int)!empty($_POST['search_type_advanced']);
} else {
    unset($option['SearchType']);
}
$user->setField('option_list', $option);

$navList = [];
foreach ((new Gazelle\Manager\UserNavigation())->fullList() as $n) {
    if ($n['mandatory'] || isset($_POST["n_{$n['id']}"])) {
        $navList[] = (int)$n['id'];
    }
}
$user->setField('nav_list', $navList);

(new Gazelle\Util\LastFM())->modifyUsername($user, trim($_POST['lastfm_username'] ?? ''));

$notification = preg_grep('/^notifications_[^_]+_/', array_keys($_POST));
if ($notification) {
    (new Gazelle\User\Notification($user))->save($notification);
}

foreach (
    [
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
    ] as $attr => $state
) {
    $user->toggleAttr($attr, $state);
}

$history = new \Gazelle\User\History($user);
if ($NewEmail) {
    $history->registerNewEmail($NewEmail, $ownProfile, new \Gazelle\Manager\IPv4(), $irc, new \Gazelle\Util\Mail());
}

if (isset($_POST['resetpasskey'])) {
    $oldPasskey = $user->announceKey();
    $newPasskey = randomString();
    $user->setField('torrent_pass', $newPasskey);
    $user->modifyAnnounceKeyHistory($oldPasskey, $newPasskey);
    (new Gazelle\Tracker())->modifyPasskey(old: $oldPasskey, new: $newPasskey);
}

$user->modify();

$ordinal = $user->ordinal();
if ($user->hasAttr('feature-file-count') || $Viewer->isStaff()) {
    $ordinal->set('file-count-display', (int)$_POST['file-count-display']);
    $ordinal->set('non-primary-threshold', (int)$_POST['non-primary-threshold']);
}

$requestBountyCreate = max(
    REQUEST_MIN * 1024 * 1024, // never go below request minimum
    min(
        2 * 1024 ** 4, // do not exceed 2 TiB
        byte_unformat($_POST['req-create'], $_POST['req-c-unit'])
    )
);
if ($requestBountyCreate != $ordinal->value('request-bounty-create')) {
    $ordinal->set('request-bounty-create', $requestBountyCreate);
}
$requestBountyVote = max(
    REQUEST_MIN * 1024 * 1024, // never go below request minimum
    min(
        1024 ** 4, // do not exceed 1 TiB
        byte_unformat($_POST['req-vote'], $_POST['req-v-unit'])
    )
);
if ($requestBountyVote != $ordinal->value('request-bounty-vote')) {
    $ordinal->set('request-bounty-vote', $requestBountyVote);
}

$donor = new Gazelle\User\Donor($user);
if ($donor->isDonor()) {
    $donor->setVisible(isset($_POST['p_donor_stats']));
    $donor->setForumDecoration(
        $_POST['donor_title_prefix'] ?? '',
        $_POST['donor_title_suffix'] ?? '',
        isset($_POST['donor_title_comma']),
    );
    $donor->updateAvatarHover($_POST['second_avatar'] ?? '')
        ->updateAvatarHoverText($_POST['avatar_mouse_over_text'] ?? '')
        ->updateIcon($_POST['donor_icon_custom_url'] ?? '')
        ->updateIconHoverText($_POST['donor_icon_mouse_over_text'] ?? '')
        ->updateIconLink($_POST['donor_icon_link'] ?? '')
        ->updateProfileInfo(1, $_POST['profile_info_1'] ?? '')
        ->updateProfileInfo(2, $_POST['profile_info_2'] ?? '')
        ->updateProfileInfo(3, $_POST['profile_info_3'] ?? '')
        ->updateProfileInfo(4, $_POST['profile_info_4'] ?? '')
        ->updateProfileTitle(1, $_POST['profile_title_1'] ?? '')
        ->updateProfileTitle(2, $_POST['profile_title_2'] ?? '')
        ->updateProfileTitle(3, $_POST['profile_title_3'] ?? '')
        ->updateProfileTitle(4, $_POST['profile_title_4'] ?? '')
        ->modify();
}

$user->flush();

(new Gazelle\User\Stylesheet($user))->modifyInfo((int)$_POST['stylesheet'], $_POST['styleurl']);

if ($ResetPassword) {
    $user->logoutEverywhere();
}

header('Location: ' . $user->location() . '&action=edit');
