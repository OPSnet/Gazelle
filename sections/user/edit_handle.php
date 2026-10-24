<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

authorize();

$irc     = new Util\Irc();
$userMan = new Manager\User();
if (!isset($_REQUEST['id'])) {
    $ownProfile = true;
    $user = $Viewer;
} elseif ($_REQUEST['id'] === 'me') {
    $ownProfile = true;
    $user = $Viewer;
} else {
    $user = $userMan->findById((int)$_REQUEST['id']);
    if (is_null($user)) {
        Error404::error();
    }
    $ownProfile = ($user->id === $Viewer->id);
    if (!$ownProfile && !$Viewer->permitted('users_edit_profiles')) {
        $irc::sendMessage(IRC_CHAN_MOD, "User {$Viewer->label()} tried to edit {$user->publicLocation()}");
        Error403::error();
    }
}

$validator = new Util\Validator();
$validator->setFields([
    ['stylesheet', true, "number", "You forgot to select a stylesheet."],
    ['styleurl', false, "regex", "You did not enter a valid stylesheet URL.", ['regex' => CSS_REGEXP]],
    ['postsperpage', true, "number", "You forgot to select your posts per page option.", ['inarray' => [25, 50, 100]]],
    ['collageentries', true, "number", "You forgot to select your collage option.", ['inarray' => [10, 25, 50, 100]]],
    ['avatar', false, "image", "You did not enter a valid avatar URL."],
    ['email', true, "email", "You did not enter a valid email address."],
    ['irckey', false, "string", "You did not enter a valid IRC key. An IRC key must be between 6 and 32 characters long.", ['range' => [6, 32]]],
    ['new_pass_1', false, "regex",
        "You did not enter a valid password. A strong password is 8 characters or longer, contains at least 1 lowercase and uppercase letter, and contains at least a number or symbol.",
        ['regex' => Util\PasswordCheck::REGEXP]
    ],
    ['new_pass_2', true, "compare", "Your passwords do not match.", ['comparefield' => 'new_pass_1']],
]);
if (!$validator->validate($_POST)) {
    Error400::error($validator->errorMessage());
}

$paranoia = Util\Paranoia::calculate($_POST);
$user->setField('Paranoia', serialize($paranoia))
    ->setField('paranoia_v2', json_encode($paranoia))
    ->setField('IRCKey', $_POST['irckey'])
    ->setField('profile_info', substr($_POST['info'], 0, 20480))
    ->setField('profile_title', trim($_POST['profile_title']));

$newEmail = false;
$emailClean = trim($_POST['email']);
if ($user->email() != $emailClean) {
    if (new Manager\EmailBlacklist()->exists($emailClean)) {
        Error400::error('The email address or domain is not allowed.');
    }
    // Everyone must supply their own password to change their email address
    // Staff do not need to supply a password if they are changing someone's email address
    if ($ownProfile) {
        if (!$user->validatePassword($_POST['password'])) {
            Error400::error('You must enter your current password when changing your email address.');
        }
        if (!Util\PasswordCheck::checkPasswordStrength($_POST['password'], $user)) {
            // same corner case as with changing passwords, see comment there
            $user->addStaffNote("forced logout because of weak/compromised password")->modify();
            $user->session()->logoutEverywhere();
            echo $Twig->render('login/weak-password.twig');
            return;
        }
    }
    $newEmail = $emailClean;
    $user->setField('Email', $emailClean);
}

$avatar = trim($_POST['avatar']);
if ($avatar != $user->avatar()) {
    if ($ownProfile && $user->disableAvatar()) {
        Error403::error('Your avatar privileges have been revoked.');
    }
    $len = strlen($avatar);
    if ($len > 255) {
        Error400::error('Your avatar link is too long ($len characters, maximum allowed is 255).');
    }
    $user->setField('Avatar', $avatar);
}

$ResetPassword = false;
if (!empty($_POST['password']) && !empty($_POST['new_pass_1']) && !empty($_POST['new_pass_2'])) {
    if (!$user->validatePassword($_POST['password'])) {
        Error400::error('You did not enter the correct password.');
    } elseif (!Util\PasswordCheck::checkPasswordStrength($_POST['password'], $user)) {
        // This is a corner case: the user already has an active session and is trying to change their password.
        // They would not have been able to log in with this password and since it is weak it might as well be
        // an attacker that happens to have the compromised password, and an old login session, but no access to
        // the email account. Force password reset by email.
        $user->addStaffNote("forced logout because of weak/compromised password")->modify();
        $user->session()->logoutEverywhere();
        echo $Twig->render('login/weak-password.twig');
        return;
    } else {
        if (!Util\PasswordCheck::checkPasswordStrength($_POST['new_pass_1'], $user)) {
            Error400::error(Util\PasswordCheck::ERROR_MSG);
        }
        if ($_POST['password'] == $_POST['new_pass_1']) {
            Error400::error('Your new password cannot be the same as your old password.');
        } elseif ($_POST['new_pass_1'] !== $_POST['new_pass_2']) {
            Error400::error('You did not enter the same password twice.');
        }
        $user->history()->modifyPassword($_POST['new_pass_1'], true);
        $ResetPassword = true;
    }
}

$option['AutoSubscribe']       = isset($_POST['autosubscribe']) ? 1 : 0;
$option['CoverArt']            = isset($_POST['coverart']) ? 1 : 0;
$option['DisableAutoSave']     = isset($_POST['disableautosave']) ? 1 : 0;
$option['DisableGrouping2']    = isset($_POST['disablegrouping']) ? 0 : 1;
$option['DisableSmileys']      = isset($_POST['disablesmileys']) ? 1 : 0;
$option['EnableMatureContent'] = isset($_POST['enablematurecontent']) ? 1 : 0;
$option['ListUnreadPMsFirst']  = isset($_POST['list_unread_pms_first']) ? 1 : 0;
$option['NoVoteLinks']         = isset($_POST['novotelinks']) ? 1 : 0;
$option['ShowExtraCovers']     = isset($_POST['show_extra_covers']) ? 1 : 0;
$option['ShowSnatched']        = isset($_POST['showsnatched']) ? 1 : 0;
$option['ShowTorFilter']       = isset($_POST['showtfilter']) ? 1 : 0;
$option['UseOpenDyslexic']     = isset($_POST['useopendyslexic']) ? 1 : 0;
$option['CollageEntries']      = (int)$_POST['collageentries'];
$option['DisableAvatars']      = (int)($_POST['disableavatars'] ?? 0);
$option['Identicons']          = (int)($_POST['identicons'] ?? 0);
$option['PostsPerPage']        = (int)$_POST['postsperpage'];
$option['TorrentGrouping']     = (int)$_POST['torrentgrouping'];

// user options
$autocomplete = Enum\Autocomplete::tryFrom((int)$_POST['autocomplete'])
    ?? Enum\Autocomplete::everywhere;
$option['AutoComplete'] = $autocomplete->value;
foreach (['DisableFreeTorrentTop10'] as $opt) {
    if ($user->option($opt)) {
        $option[$opt] = $user->option($opt);
    }
}
if (!isset($_POST['sorthide'])) {
    $option['SortHide'] = [];
} else {
    $json = json_decode($_POST['sorthide']) ?? [];
    foreach ($json as $j) {
        [$release, $hidden] = explode('_', $j);
        $option['SortHide'][$release] = (int)$hidden;
    }
}
if ($user->permitted('site_advanced_search')) {
    $option['SearchType'] = (int)isset($_POST['search_type_advanced']);
} else {
    unset($option['SearchType']);
}
$user->setField('option_list', $option);

$navList = [];
foreach (new Manager\UserNavigation()->fullList() as $n) {
    if ($n['mandatory'] || isset($_POST["n_{$n['id']}"])) {
        $navList[] = (int)$n['id'];
    }
}
$user->setField('nav_list', $navList);

new Util\LastFM()->modifyUsername($user, trim($_POST['lastfm_username'] ?? ''));

$notification = preg_grep('/^notifications_[^_]+_/', array_keys($_POST));
if ($notification) {
    new User\Notification($user)->save($notification);
}

foreach (
    [
        'admin-error-reporting' => isset($_POST['error_reporting']),
        'audit-trail-hidden'    => isset($_POST['audit-trail-hidden']),
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
        'show-all-tags'         => !isset($_POST['show-all-tags']),
    ] as $attr => $state
) {
    $user->toggleAttr($attr, $state);
}

if ($newEmail) {
    $user->history()->registerNewEmail($newEmail, $ownProfile);
}

if (isset($_POST['resetpasskey'])) {
    $oldPasskey = $user->announceKey();
    $newPasskey = randomString();
    $user->history()->modifyAnnounceKey(old: $oldPasskey, new: $newPasskey);
    new Tracker()->modifyPasskey(old: $oldPasskey, new: $newPasskey);
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
        byte_unformat((float)$_POST['req-create'], $_POST['req-c-unit'])
    )
);
if ($requestBountyCreate != $ordinal->value('request-bounty-create')) {
    $ordinal->set('request-bounty-create', $requestBountyCreate);
}
$requestBountyVote = max(
    REQUEST_MIN * 1024 * 1024, // never go below request minimum
    min(
        1024 ** 4, // do not exceed 1 TiB
        byte_unformat((float)$_POST['req-vote'], $_POST['req-v-unit'])
    )
);
if ($requestBountyVote != $ordinal->value('request-bounty-vote')) {
    $ordinal->set('request-bounty-vote', $requestBountyVote);
}

$donor = new User\Donor($user);
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

new User\Stylesheet($user)->modifyInfo((int)$_POST['stylesheet'], $_POST['styleurl']);

if ($ResetPassword) {
    $user->session()->logoutEverywhere();
}

header('Location: ' . $user->location() . '&action=edit');
