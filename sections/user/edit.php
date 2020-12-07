<?php

use Gazelle\Manager\Notification;

function display_paranoia($FieldName) {
    global $Paranoia;
    $Level = (in_array($FieldName . '+', $Paranoia)) ? 0 : (in_array($FieldName, $Paranoia) ? 1 : 2);
    $level1Checked = $Level >= 1 ? ' checked="checked"' : '';
    $level2Checked = $Level >= 2 ? ' checked="checked"' : '';
    return "<label><input type=\"checkbox\" name=\"p_{$FieldName}_c\"level1Checked" . ' onchange="AlterParanoia()" /> Show count</label>'."&nbsp;&nbsp;\n"
        . "<label><input type=\"checkbox\" name=\"p_{$FieldName}_l\"level1Checked" . ' onchange="AlterParanoia()" /> Show list</label>'."\n";
}

$UserID = (int)$_REQUEST['userid'];
if (!$UserID) {
    error(404);
}
if ($UserID != $LoggedUser['ID'] && !check_perms('users_edit_profiles')) {
    error(403);
}
$User = new Gazelle\User($UserID);

[$Paranoia, $Info, $InfoTitle, $Avatar, $StyleID, $StyleURL, $SiteOptions, $DownloadAlt, $UnseededAlerts,
    $NotifyOnDeleteSeeding, $NotifyOnDeleteSnatched, $NotifyOnDeleteDownloaded, $UserNavItems] = $DB->row("
    SELECT
        m.Paranoia,
        i.Info,
        i.InfoTitle,
        i.Avatar,
        i.StyleID,
        i.StyleURL,
        i.SiteOptions,
        i.DownloadAlt,
        i.UnseededAlerts,
        i.NotifyOnDeleteSeeding,
        i.NotifyOnDeleteSnatched,
        i.NotifyOnDeleteDownloaded,
        i.NavItems
    FROM users_main AS m
    INNER JOIN users_info AS i ON (i.UserID = m.ID)
    LEFT JOIN permissions AS p ON (p.ID = m.PermissionID)
    WHERE m.ID = ?
    ", $UserID
);

$options = array_merge(Users::default_site_options(), unserialize($SiteOptions) ?? []);
$Paranoia = unserialize($Paranoia) ?? [];

$NavItems = Users::get_nav_items();
$UserNavItems = array_filter(array_map('trim', explode(',', $UserNavItems)));
if (!count($UserNavItems)) {
    $UserNavItems = array_keys(array_filter($NavItems, function($v) {
        return $v['initial'];
    }));
}

$donorMan = new Gazelle\Manager\Donation;
$enabledReward = $donorMan->enabledRewards($UserID);
$profileReward = $donorMan->profileRewards($UserID);
$profile = [
    0 => [
        'title'    => $InfoTitle,
        'textarea' => new TEXTAREA_PREVIEW('info', 'info', display_str($Info), 40, 8, false, false, true),
    ]
];
foreach (range(1, 4) as $level) {
    if (!$enabledReward["HasProfileInfo$level"]) {
        $profile[$level] = [
            'enabled'  => false,
        ];
    } else {
        $profile[$level] = [
            'enabled'  => true,
            'title'    => display_str($profileReward["ProfileInfoTitle$level"]),
            'textarea' => new TEXTAREA_PREVIEW("profile_info_$level", "profile_info_$level", display_str($profileReward["ProfileInfo$level"]), 40, 8, false, false, true),
        ];
    }
}

View::show_header("$Username &rsaquo; Settings", 'user,jquery-ui,release_sort,password_validate,validate,cssgallery,preview_paranoia,bbcode,user_settings,donor_titles');

$Val = new Validate;
echo $Val->GenerateJS('userform');

echo G::$Twig->render('user/setting.twig', [
    'auth'             => $LoggedUser['AuthKey'],
    'avatar'           => $Avatar,
    'bot_nick'         => BOT_NICK,
    'download_text'    => $DownloadAlt,
    'is_mod'           => check_perms('users_mod'),
    'logged_user'      => $LoggedUser['ID'],
    'nav_items'        => $NavItems,
    'nav_items_user'   => $UserNavItems,
    'option'           => $options,
    'profile'          => $profile,
    'release_order'    => $User->releaseOrder($options, (new Gazelle\ReleaseType)->extendedList()),
    'site_name'        => SITE_NAME,
    'static_host'      => STATIC_SERVER,
    'style_id'         => $StyleID,
    'style_url'        => $StyleURL,
    'stylesheets'      => (new Gazelle\Stylesheet)->list(),
    'user'             => $User,
    'can' => [
        'advanced_search' => check_perms('site_advanced_search'),
        'torrent_notify'  => check_perms('site_torrents_notify'),
    ],
    'donor' => [
        'enabled' => $enabledReward,
        'reward'  => $donorMan->rewards($UserID),
        'title'   => $donorMan->titles($UserID),
        'visible' => $donorMan->isVisible($UserID),
    ],
    'notify' => [
        'seeded'     => $NotifyOnDeleteSeeding,
        'snatched'   => $NotifyOnDeleteSnatched,
        'downloaded' => $NotifyOnDeleteDownloaded,
        'unseeded'   => $UnseededAlerts,
        'settings'   => (new Notification($UserID))->settings(),
    ],
    'paranoia' => [
        'artists'          => !in_array('artistsadded', $Paranoia),
        'bonus'            => !in_array('bonuspoints', $Paranoia),
        'hide_heart'       => !in_array('hide_donor_heart', $Paranoia),
        'download'         => !in_array('downloaded', $Paranoia),
        'invited'          => !in_array('invitedcount', $Paranoia),
        'lastseen'         => !in_array('lastseen', $Paranoia),
        'ratio'            => !in_array('ratio', $Paranoia),
        'ratio_req'        => !in_array('requiredratio', $Paranoia),
        'upload'           => !in_array('uploaded', $Paranoia),
        'collages'         => display_paranoia('collages'),
        'collages_contrib' => display_paranoia('collagecontribs'),
        'leeching'         => display_paranoia('leeching'),
        'notify'           => display_paranoia('notifications'),
        'perfectflacs'     => display_paranoia('perfectflacs'),
        'seeding'          => display_paranoia('seeding'),
        'snatched'         => display_paranoia('snatched'),
        'torrentcomments'  => display_paranoia('torrentcomments'),
        'unique'           => display_paranoia('uniquegroups'),
        'uploads'          => display_paranoia('uploads'),
        'request_fill' => [
            'bounty' => !in_array('requestsfilled_bounty', $Paranoia),
            'count'  => !in_array('requestsfilled_count', $Paranoia),
            'list'   => !in_array('requestsfilled_list', $Paranoia),
        ],
        'request_vote' => [
            'bounty' => !in_array('requestsvoted_bounty', $Paranoia),
            'count'  => !in_array('requestsvoted_count', $Paranoia),
            'list'   => !in_array('requestsvoted_list', $Paranoia),
        ],
    ],
]);
View::show_footer();

