<?php

use Gazelle\Manager\Notification;

function display_paranoia($FieldName) {
    global $Paranoia;
    $Level = (in_array($FieldName . '+', $Paranoia)) ? 0 : (in_array($FieldName, $Paranoia) ? 1 : 2);
    return sprintf('<label><input type="checkbox" name="p_c_%s"%s onchange="AlterParanoia()" /> Show count</label>&nbsp;&nbsp;
        <label><input type="checkbox" name="p_l_%s"%s onchange="AlterParanoia()" /> Show list</label>',
        $FieldName, $Level >= 1 ? ' checked="checked"' : '', $FieldName, $Level >= 2 ? ' checked="checked"' : '') . "\n";
}

$UserID = (int)$_REQUEST['userid'];
if (!$UserID) {
    error(404);
}
if ($UserID != $Viewer->id() && !check_perms('users_edit_profiles')) {
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

$options = unserialize($SiteOptions) ?: [];
if (!isset($options['HttpsTracker'])) {
    $options['HttpsTracker'] = true;
}
$Paranoia = unserialize($Paranoia) ?: [];

$NavItems = Users::get_nav_items();
$UserNavItems = array_filter(array_map('trim', explode(',', $UserNavItems)));
if (!count($UserNavItems)) {
    $UserNavItems = array_keys(array_filter($NavItems, function($v) {
        return $v['initial'];
    }));
}

$enabledReward = $User->enabledDonorRewards();
$profileReward = $User->profileDonorRewards();
$profile = [
    0 => [
        'title' => $InfoTitle,
        'ta'    => new Gazelle\Util\Textarea('info', $Info, 42, 8),
    ]
];
foreach (range(1, 4) as $level) {
    if (!$enabledReward["HasProfileInfo$level"]) {
        $profile[$level] = [
            'enabled'  => false,
        ];
    } else {
        $profile[$level] = [
            'enabled' => true,
            'title'   => display_str($profileReward["ProfileInfoTitle$level"]),
            'ta'      => new Gazelle\Util\Textarea("profile_info_$level", $profileReward["ProfileInfo$level"] ?? '', 42, 8),
        ];
    }
}

View::show_header($User->username() . " &rsaquo; Settings",
     ['js' => 'user,jquery-ui,release_sort,password_validate,validate,cssgallery,preview_paranoia,bbcode,user_settings,donor_titles']
 );
$Val = new Gazelle\Util\Validator;
echo $Val->generateJS('userform');
echo $Twig->render('user/setting.twig', [
    'auth'             => $Viewer->auth(),
    'avatar'           => $Avatar,
    'download_text'    => $DownloadAlt,
    'is_mod'           => check_perms('users_mod'),
    'lastfm_username'  => (new Gazelle\Util\LastFM)->username($UserID),
    'logged_user'      => $Viewer->id(),
    'nav_items'        => $NavItems,
    'nav_items_user'   => $UserNavItems,
    'option'           => $options,
    'profile'          => $profile,
    'release_order'    => $User->releaseOrder($options, (new Gazelle\ReleaseType)->extendedList()),
    'style_id'         => $StyleID,
    'style_url'        => $StyleURL,
    'stylesheets'      => (new Gazelle\Stylesheet)->list(),
    'user'             => $User,
    'can' => [
        'advanced_search' => check_perms('site_advanced_search'),
        'request_notify'  => check_perms('site_vote'),
        'torrent_notify'  => check_perms('site_torrents_notify'),
    ],
    'donor' => [
        'enabled' => $enabledReward,
        'reward'  => $User->donorRewards(),
        'title'   => $User->donorTitles(),
    ],
    'notify' => [
        'autosub'    => $options['AutoSubscribe'] ?: false,
        'seeded'     => $NotifyOnDeleteSeeding,
        'snatched'   => $NotifyOnDeleteSnatched,
        'downloaded' => $NotifyOnDeleteDownloaded,
        'unseeded'   => $UnseededAlerts,
        'settings'   => (new Notification($UserID))->settings(),
    ],
    'paranoia' => [
        'donor_visible'    => $User->donorVisible($UserID),
        'artists'          => !in_array('artistsadded', $Paranoia),
        'bonus'            => !in_array('bonuspoints', $Paranoia),
        'hide_heart'       => !in_array('hide_donor_heart', $Paranoia),
        'download'         => !in_array('downloaded', $Paranoia),
        'invited'          => !in_array('invitedcount', $Paranoia),
        'lastseen'         => !in_array('lastseen', $Paranoia),
        'notify'           => !in_array('notifications', $Paranoia),
        'ratio'            => !in_array('ratio', $Paranoia),
        'ratio_req'        => !in_array('requiredratio', $Paranoia),
        'upload'           => !in_array('uploaded', $Paranoia),
        'collages'         => display_paranoia('collages'),
        'collages_contrib' => display_paranoia('collagecontribs'),
        'leeching'         => display_paranoia('leeching'),
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
