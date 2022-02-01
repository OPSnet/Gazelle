<?php

function display_paranoia($FieldName) {
    global $Paranoia;
    $Level = (in_array($FieldName . '+', $Paranoia)) ? 0 : (in_array($FieldName, $Paranoia) ? 1 : 2);
    return sprintf('<label><input type="checkbox" name="p_c_%s"%s onchange="AlterParanoia()" /> Show count</label>&nbsp;&nbsp;
        <label><input type="checkbox" name="p_l_%s"%s onchange="AlterParanoia()" /> Show list</label>',
        $FieldName, $Level >= 1 ? ' checked="checked"' : '', $FieldName, $Level >= 2 ? ' checked="checked"' : '') . "\n";
}
$userMan = new Gazelle\Manager\User;

$User = $userMan->findById((int)($_REQUEST['id'] ?? 0));
if (is_null($User)) {
    error(404);
}
$UserID = $User->id();
if ($UserID != $Viewer->id() && !$Viewer->permitted('users_edit_profiles')) {
    error(403);
}

[$Paranoia, $Info, $InfoTitle, $Avatar, $SiteOptions, $DownloadAlt, $UnseededAlerts,
    $NotifyOnDeleteSeeding, $NotifyOnDeleteSnatched, $NotifyOnDeleteDownloaded, $UserNavItems] = $DB->row("
    SELECT
        m.Paranoia,
        i.Info,
        i.InfoTitle,
        i.Avatar,
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

$stylesheet = new Gazelle\User\Stylesheet($User);

$options = unserialize($SiteOptions) ?: [];
if (!isset($options['HttpsTracker'])) {
    $options['HttpsTracker'] = true;
}
$Paranoia = unserialize($Paranoia) ?: [];

$NavItems = (new Gazelle\Manager\User)->forumNavItemList();
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

echo $Twig->render('user/setting.twig', [
    'avatar'           => $Avatar,
    'download_text'    => $DownloadAlt,
    'is_mod'           => $Viewer->permitted('users_mod'),
    'js'               => (new Gazelle\Util\Validator)->generateJS('userform'),
    'lastfm_username'  => (new Gazelle\Util\LastFM)->username($UserID),
    'nav_items'        => $NavItems,
    'nav_items_user'   => $UserNavItems,
    'option'           => $options,
    'profile'          => $profile,
    'release_order'    => $User->releaseOrder($options, (new Gazelle\ReleaseType)->extendedList()),
    'style_id'         => $stylesheet->styleId(),
    'style_url'        => $stylesheet->styleUrl(),
    'stylesheets'      => (new Gazelle\Manager\Stylesheet)->list(),
    'user'             => $User,
    'viewer'           => $Viewer,
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
        'settings'   => (new Gazelle\User\Notification($User))->config(),
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
        'hide_vote_recent'  => $User->hasAttr('hide-vote-recent'),
        'hide_vote_history' => $User->hasAttr('hide-vote-history'),
    ],
]);
