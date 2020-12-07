<?php

use \Gazelle\Manager\Notification;

$UserID = $_REQUEST['userid'];
if (!is_number($UserID)) {
    error(404);
}

$User = new \Gazelle\User($UserID);

$DB->prepared_query('
    SELECT
        m.Username,
        m.Email,
        m.IRCKey,
        m.Paranoia,
        m.2FA_Key,
        i.Info,
        i.Avatar,
        i.StyleID,
        i.StyleURL,
        i.SiteOptions,
        i.UnseededAlerts,
        i.DownloadAlt,
        p.Level AS Class,
        i.InfoTitle,
        i.NotifyOnDeleteSeeding,
        i.NotifyOnDeleteSnatched,
        i.NotifyOnDeleteDownloaded,
        i.NavItems,
        CASE WHEN uhafl.UserID IS NULL THEN 1 ELSE 0 END AS AcceptFL,
        CASE WHEN uhaud.UserID IS NULL THEN 0 ELSE 1 END AS UnlimitedDownload
    FROM users_main AS m
    INNER JOIN users_info AS i ON (i.UserID = m.ID)
    LEFT JOIN permissions AS p ON (p.ID = m.PermissionID)
    LEFT JOIN user_has_attr AS uhafl ON (uhafl.UserID = m.ID)
    LEFT JOIN user_attr as uafl ON (uafl.ID = uhafl.UserAttrID AND uafl.Name = ?)
    LEFT JOIN user_has_attr AS uhaud ON (uhaud.UserID = m.ID)
    LEFT JOIN user_attr as uaud ON (uaud.ID = uhaud.UserAttrID AND uaud.Name = ?)
    WHERE m.ID = ?
    ', 'no-fl-gifts', 'unlimited-download', $UserID
);
[$Username, $Email, $IRCKey, $Paranoia, $TwoFAKey, $Info, $Avatar, $StyleID, $StyleURL, $SiteOptions, $UnseededAlerts, $DownloadAlt,
    $Class, $InfoTitle, $NotifyOnDeleteSeeding, $NotifyOnDeleteSnatched, $NotifyOnDeleteDownloaded, $UserNavItems, $AcceptFL, $UnlimitedDownload] = $DB->next_record(MYSQLI_NUM, [3, 9]);

if ($UserID != $LoggedUser['ID'] && !check_perms('users_edit_profiles', $Class)) {
    error(403);
}

$Paranoia = unserialize($Paranoia);
if (!is_array($Paranoia)) {
    $Paranoia = [];
}

function paranoia_level($Setting) {
    global $Paranoia;
    // 0: very paranoid; 1: stats allowed, list disallowed; 2: not paranoid
    return (in_array($Setting . '+', $Paranoia)) ? 0 : (in_array($Setting, $Paranoia) ? 1 : 2);
}

function display_paranoia($FieldName) {
    $Level = paranoia_level($FieldName);
    $level1Checked = $Level >= 1 ? ' checked="checked"' : '';
    $level2Checked = $Level >= 2 ? ' checked="checked"' : '';
    return "<label><input type=\"checkbox\" name=\"p_{$FieldName}_c\"level1Checked" . ' onchange="AlterParanoia()" /> Show count</label>'."&nbsp;&nbsp;\n"
        . "<label><input type=\"checkbox\" name=\"p_{$FieldName}_l\"level1Checked" . ' onchange="AlterParanoia()" /> Show list</label>'."\n";
}

View::show_header("$Username &rsaquo; Settings", 'user,jquery-ui,release_sort,password_validate,validate,cssgallery,preview_paranoia,bbcode,user_settings,donor_titles');

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

$Val = new Validate;
echo $Val->GenerateJS('userform');

echo G::$Twig->render('user/edit.twig', [
    'accept_fl'        => $AcceptFL,
    'auth'             => $LoggedUser['AuthKey'],
    'avatar'           => $Avatar,
    'bot_nick'         => BOT_NICK,
    'download_text'    => $DownloadAlt,
    'is_mod'           => check_perms('users_mod'),
    'logged_user'      => $LoggedUser['ID'],
    'nav_items'        => $NavItems,
    'nav_items_user'   => $UserNavItems,
    'option'           => array_merge(Users::default_site_options(), unserialize_array($SiteOptions)),
    'profile'          => $profile,
    'release_order'    => Users::release_order($SiteOptions),
    'release_order_js' => Users::release_order_default_js($SiteOptions),
    'site_name'        => SITE_NAME,
    'static_host'      => STATIC_SERVER,
    'style_id'         => $StyleID,
    'style_url'        => $StyleURL,
    'stylesheets'      => (new \Gazelle\Stylesheet)->list(),
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
    'select'           => [
        'comm_stats' => Format::selected('AutoloadCommStats', 1, 'checked', $SiteOptions),
        'tags'       => Format::selected('ShowTags', 1, 'checked', $SiteOptions),
    ],
]);
View::show_footer();

