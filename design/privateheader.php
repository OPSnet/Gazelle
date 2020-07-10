<?php

use \Gazelle\Manager\Notification;

define('FOOTER_FILE', __DIR__ . '/privatefooter.php');

$UseTooltipster = !isset(G::$LoggedUser['Tooltipster']) || G::$LoggedUser['Tooltipster'];

$authArgs = '&amp;user=' . G::$LoggedUser['ID']
    . '&amp;auth=' . G::$LoggedUser['RSS_Auth']
    . '&amp;passkey=' . G::$LoggedUser['torrent_pass']
    . '&amp;authkey=' . G::$LoggedUser['AuthKey'];

$Staff = check_perms('users_mod')
    ? new \Gazelle\Staff(G::$LoggedUser['ID'])
    : null;
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title><?=display_str($PageTitle)?></title>
    <meta http-equiv="X-UA-Compatible" content="chrome=1;IE=edge" />
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="msapplication-config" content="none" />
    <meta name="referrer" content="none, no-referrer, same-origin" />
    <link rel="shortcut icon" href="favicon.ico" />
    <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
    <link rel="search" type="application/opensearchdescription+xml" title="<?=SITE_NAME?> Torrents" href="opensearch.php?type=torrents" />
    <link rel="search" type="application/opensearchdescription+xml" title="<?=SITE_NAME?> Artists" href="opensearch.php?type=artists" />
    <link rel="search" type="application/opensearchdescription+xml" title="<?=SITE_NAME?> Requests" href="opensearch.php?type=requests" />
    <link rel="search" type="application/opensearchdescription+xml" title="<?=SITE_NAME?> Forums" href="opensearch.php?type=forums" />
    <link rel="search" type="application/opensearchdescription+xml" title="<?=SITE_NAME?> Log" href="opensearch.php?type=log" />
    <link rel="search" type="application/opensearchdescription+xml" title="<?=SITE_NAME?> Users" href="opensearch.php?type=users" />
    <link rel="search" type="application/opensearchdescription+xml" title="<?=SITE_NAME?> Wiki" href="opensearch.php?type=wiki" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=feed_news<?= $authArgs ?>" title="<?=SITE_NAME?> - News" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=feed_blog<?= $authArgs ?>" title="<?=SITE_NAME?> - Blog" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=feed_changelog<?= $authArgs ?>" title="<?=SITE_NAME?> - Change Log" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_notify_<?=G::$LoggedUser['torrent_pass']?><?= $authArgs ?>" title="<?=SITE_NAME?> - P.T.N." />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_all<?= $authArgs ?>" title="<?=SITE_NAME?> - All Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_music<?= $authArgs ?>" title="<?=SITE_NAME?> - Music Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_apps<?= $authArgs ?>" title="<?=SITE_NAME?> - Application Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_ebooks<?= $authArgs ?>" title="<?=SITE_NAME?> - E-Book Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_abooks<?= $authArgs ?>" title="<?=SITE_NAME?> - Audiobooks Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_evids<?= $authArgs ?>" title="<?=SITE_NAME?> - E-Learning Video Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_comedy<?= $authArgs ?>" title="<?=SITE_NAME?> - Comedy Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_comics<?= $authArgs ?>" title="<?=SITE_NAME?> - Comic Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_mp3<?= $authArgs ?>" title="<?=SITE_NAME?> - MP3 Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_flac<?= $authArgs ?>" title="<?=SITE_NAME?> - FLAC Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_vinyl<?= $authArgs ?>" title="<?=SITE_NAME?> - Vinyl Sourced Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_lossless<?= $authArgs ?>" title="<?=SITE_NAME?> - Lossless Torrents" />
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_lossless24<?= $authArgs ?>" title="<?=SITE_NAME?> - 24bit Lossless Torrents" />
<?php
if (isset(G::$LoggedUser['Notify'])) {
    foreach (G::$LoggedUser['Notify'] as $Filter) {
        list($FilterID, $FilterName) = $Filter;
?>
    <link rel="alternate" type="application/rss+xml"
            href="feeds.php?feed=torrents_notify_<?=$FilterID?>_<?=G::$LoggedUser['torrent_pass']?><?= $authArgs ?>&amp;name=<?=urlencode($FilterName)?>"
            title="<?=SITE_NAME?> - <?=display_str($FilterName)?>" />
<?php
    }
}
?>
    <link rel="stylesheet" type="text/css"
            href="<?=STATIC_SERVER?>styles/global.css?v=<?=filemtime(SERVER_ROOT.'/public/static/styles/global.css')?>" />
<?php
if ($UseTooltipster) { ?>
    <link rel="stylesheet" href="<?=STATIC_SERVER?>styles/tooltipster/style.css?v=<?=filemtime(SERVER_ROOT.'/public/static/styles/tooltipster/style.css')?>" type="text/css" media="screen" />
<?php
} ?>
    <meta name="viewport" content="width=device-width" />
<?php
if (empty(G::$LoggedUser['StyleURL'])) {
?>
    <link rel="stylesheet" type="text/css" title="<?=G::$LoggedUser['StyleName']?>" media="screen"
            href="<?=STATIC_SERVER?>styles/<?=G::$LoggedUser['StyleName']?>/style.css?v=<?=filemtime(SERVER_ROOT.'/public/static/styles/'.G::$LoggedUser['StyleName'].'/style.css')?>" />
<?php
} else {
        $StyleURLInfo = parse_url(G::$LoggedUser['StyleURL']);
        if (substr(G::$LoggedUser['StyleURL'], -4) == '.css'
                && $StyleURLInfo['query'].$StyleURLInfo['fragment'] == ''
                && in_array($StyleURLInfo['host'], [NONSSL_SITE_URL, SSL_SITE_URL])
                && file_exists(SERVER_ROOT.$StyleURLInfo['path'])) {
            $StyleURL = G::$LoggedUser['StyleURL'].'?v='.filemtime(SERVER_ROOT.'/public/'.$StyleURLInfo['path']);
        } else {
            $StyleURL = G::$LoggedUser['StyleURL'];
        }
?>
    <link rel="stylesheet" type="text/css" media="screen" href="<?=$StyleURL?>" title="External CSS" />
<?php
}
if (!empty(G::$LoggedUser['UseOpenDyslexic'])) {
        // load the OpenDyslexic font
?>
    <link rel="stylesheet" type="text/css" charset="utf-8"
            href="<?=STATIC_SERVER?>styles/opendyslexic/style.css?v=<?=filemtime(SERVER_ROOT.'/public/static/styles/opendyslexic/style.css')?>" />
<?php
}
$ExtraCSS = explode(',', $CSSIncludes);
foreach ($ExtraCSS as $CSS) {
    if (trim($CSS) == '') {
        continue;
    }
?>
    <link rel="stylesheet" type="text/css" media="screen" href="<?=STATIC_SERVER."styles/$CSS/style.css?v=".filemtime(SERVER_ROOT."/public/static/styles/$CSS/style.css")?>" />
<?php
}
?>
    <script type="text/javascript">
        //<![CDATA[
        var authkey = "<?=G::$LoggedUser['AuthKey']?>";
        var userid = <?=G::$LoggedUser['ID']?>;
        //]]>
    </script>
<?php

$Scripts = array_merge(['jquery', 'script_start', 'ajax.class', 'global', 'jquery.autocomplete', 'autocomplete', 'jquery.countdown.min'], explode(',', $JSIncludes));
if ($UseTooltipster) {
    $Scripts[] = 'tooltipster';
}
$Scripts[] = 'tooltipster_settings';
foreach ($Scripts as $Script) {
    if (trim($Script) == '') {
        continue;
    }
?>
    <script
            src="<?=STATIC_SERVER?>functions/<?=$Script?>.js?v=<?=filemtime(SERVER_ROOT.'/public/static/functions/'.$Script.'.js')?>"
            type="text/javascript"></script>
<?php
}

global $ClassLevels;
// Get notifications early to change menu items if needed
global $NotificationSpans;
$notification = new Notification(G::$LoggedUser['ID']);
$Notifications = $notification->notifications();
$UseNoty = $notification->useNoty();
$NewSubscriptions = false;
$NotificationSpans = [];
foreach ($Notifications as $Type => $Notification) {
    if ($Type === Notification::SUBSCRIPTIONS) {
        $NewSubscriptions = true;
    }
    if ($UseNoty) {
        $NotificationSpans[] = "<span class=\"noty-notification\" style=\"display: none;\" data-noty-type=\"$Type\" data-noty-id=\"$Notification[id]\" data-noty-importance=\"$Notification[importance]\" data-noty-url=\"$Notification[url]\">$Notification[message]</span>";
    }
}
if ($UseNoty && !empty($NotificationSpans)) {
    NotificationsManagerView::load_js();
}
if ($notification->isSkipped(Notification::SUBSCRIPTIONS)) {
    $subscription = new \Gazelle\Manager\Subscription(G::$LoggedUser['ID']);
    $NewSubscriptions = $subscription->unread();
}

$NavItems = Users::get_user_nav_items(G::$LoggedUser['ID']);
?>
</head>
<body id="<?=$Document == 'collages' ? 'collage' : $Document?>">
    <input id="extracb1" class="hidden" type="checkbox">
    <input id="extracb2" class="hidden" type="checkbox">
    <input id="extracb3" class="hidden" type="checkbox">
    <input id="extracb4" class="hidden" type="checkbox">
    <input id="extracb5" class="hidden" type="checkbox">
    <div id="wrapper">
        <h1 class="hidden"><?=SITE_NAME?></h1>
        <div id="header">
            <div id="logo">
                <a href="index.php"></a>
            </div>
            <div id="userinfo">
                <ul id="userinfo_username">
                    <li id="nav_userinfo" <?=Format::add_class($PageID, ['user', false, false], 'active', true, 'id')?>>
                        <a href="user.php?id=<?=G::$LoggedUser['ID']?>" class="username"><?=G::$LoggedUser['Username']?></a>
                    </li>
                    <li id="nav_userclass">
                        <span class="hidden userclass"><?=$ClassLevels[G::$LoggedUser['Class']]['Name']?></span>
                    </li>
                    <li id="nav_useredit" class="brackets<?=Format::add_class($PageID, ['user','edit'], 'active', false)?>">
                        <a href="user.php?action=edit&amp;userid=<?=G::$LoggedUser['ID']?>">Edit</a>
                    </li>
                    <li id="nav_logout" class="brackets">
                        <a href="logout.php?auth=<?=G::$LoggedUser['AuthKey']?>">Logout</a>
                    </li>
                </ul>
                <ul id="userinfo_major">
                    <li id="nav_upload" class="brackets<?=Format::add_class($PageID, ['upload'], 'active', false)?>">
                        <a href="upload.php" title="Upload">Upload</a>
                    </li>
                    <li id="nav_bonus" class="brackets<?=Format::add_class($PageID, ['user', 'bonus'], 'active', false)?>">
                        <a href="bonus.php" class='tooltip' title="Bonus (<?=number_format((int) G::$LoggedUser['BonusPoints'])?>)">Bonus (<?=number_format((int) G::$LoggedUser['BonusPoints'])?>)</a>
                    </li>
<?php
if (check_perms('site_send_unlimited_invites')) {
    $Invites = ' (∞)';
} elseif (G::$LoggedUser['Invites'] > 0) {
    $Invites = ' ('.G::$LoggedUser['Invites'].')';
} else {
    $Invites = '';
}
?>
                    <li id="nav_invite" class="brackets<?=Format::add_class($PageID, ['user','invite'], 'active', false)?>">
                        <a href="user.php?action=invite" class='tooltip' title="Invite<?=$Invites?>">Invite<?=$Invites?></a>
                    </li>
                    <li id="nav_donate" class="brackets<?=Format::add_class($PageID, ['donate'], 'active', false)?>">
<?php
$Payment = new Gazelle\Manager\Payment;
$donorMan = new Gazelle\Manager\Donation;
$monthlyRental = $Payment->monthlyRental();
$percent = $monthlyRental == 0.0
    ? 100
    : min(100, (int)(($donorMan->totalMonth(1) / $monthlyRental) * 100));
?>
                        <a href="donate.php" class='tooltip' title="Donate">Donate (<?= $percent ?>%)</a>
                    </li>

                </ul>
                <ul id="userinfo_stats">
                    <li id="stats_seeding">
                        <a href="torrents.php?type=seeding&amp;userid=<?=G::$LoggedUser['ID']?>">Up</a>:
                        <span class="stat tooltip" title="<?=Format::get_size(G::$LoggedUser['BytesUploaded'], 5)?>"><?=Format::get_size(G::$LoggedUser['BytesUploaded'])?></span>
                    </li>
                    <li id="stats_leeching">
                        <a href="torrents.php?type=leeching&amp;userid=<?=G::$LoggedUser['ID']?>">Down</a>:
                        <span class="stat tooltip" title="<?=Format::get_size(G::$LoggedUser['BytesDownloaded'], 5)?>"><?=Format::get_size(G::$LoggedUser['BytesDownloaded'])?></span>
                    </li>
                    <li id="stats_ratio">
                        Ratio: <span class="stat"><?=Format::get_ratio_html(G::$LoggedUser['BytesUploaded'], G::$LoggedUser['BytesDownloaded'])?></span>
                    </li>
<?php
    if (!empty(G::$LoggedUser['RequiredRatio'])) { ?>
                    <li id="stats_required">
                        <a href="rules.php?p=ratio">Required</a>:
                        <span class="stat tooltip" title="<?=number_format(G::$LoggedUser['RequiredRatio'], 5)?>"><?=number_format(G::$LoggedUser['RequiredRatio'], 2)?></span>
                    </li>
<?php
    }
    if (G::$LoggedUser['FLTokens'] > 0) { ?>
                    <li id="fl_tokens">
                        <a href="wiki.php?action=article&amp;id=57">Tokens</a>:
                        <span class="stat">
                            <a href="userhistory.php?action=token_history&amp;userid=<?=G::$LoggedUser['ID']?>"><?=G::$LoggedUser['FLTokens']?></a>
                        </span>
                    </li>
<?php
    } ?>
                </ul>
                <ul id="userinfo_minor"<?=$NewSubscriptions ? ' class="highlite"' : ''?>>
<?php
        $parseNavItem = function($val) {
            $val = trim($val);
            return $val == 'false' ? false : $val;
        };

        foreach ($NavItems as $n) {
            list($ID, $Key, $Title, $Target, $Tests, $TestUser, $Mandatory) = array_values($n);
            if (strpos($Tests, ':')) {
                $Parts = array_map('trim', explode(',', $Tests));
                $Tests = [];

                foreach ($Parts as $Part) {
                    $Tests[] = array_map($parseNavItem, explode(':', $Part));
                }
            } else if (strpos($Tests, ',')) {
                $Tests = array_map($parseNavItem, explode(',', $Tests));
            } else {
                $Tests = [$Tests];
            }

            if ($Key == 'inbox') {
                $Target = Gazelle\Inbox::getLinkQuick(null,
                    isset(G::$LoggedUser['ListUnreadPMsFirst']) && G::$LoggedUser['ListUnreadPMsFirst']
                );
            }

            $ClassNames = NULL;
            if ($Key == 'notifications' && !check_perms('site_torrents_notify')) {
                continue;
            } else if ($Key == 'subscriptions') {
                $ClassNames = $NewSubscriptions ? 'new-subscriptions' : '';
                $ClassNames = trim($ClassNames.Format::add_class($PageID, ['userhistory', 'subscriptions'], 'active', false));
            }

            if ($ClassNames == NULL) {
?>
                    <li id="nav_<?=$Key?>"<?=Format::add_class($PageID, $Tests, 'active', true, $TestUser ? 'userid' : false)?>>
<?php       } else { ?>
                    <li id="nav_<?=$Key?>"<?=$ClassNames ? " class=\"$ClassNames\"" : ''?>>
<?php       } ?>
                        <a href="<?=$Target?>"><?=$Title?></a>
                    </li>
<?php   } ?>
                </ul>
            </div>
            <div id="menu">
                <h4 class="hidden">Site Menu</h4>
                <ul>
                    <li id="nav_index"<?=
                        Format::add_class($PageID, ['index'], 'active', true)?>>
                        <a href="index.php">Home</a>
                    </li>
                    <li id="nav_torrents"<?=
                        Format::add_class($PageID, ['torrents', false, false], 'active', true)?>>
                        <a href="torrents.php">Torrents</a>
                    </li>
                    <li id="nav_collages"<?=
                        Format::add_class($PageID, ['collages'], 'active', true)?>>
                        <a href="collages.php">Collages</a>
                    </li>
                    <li id="nav_requests"<?=
                        Format::add_class($PageID, ['requests'], 'active', true)?>>
                        <a href="requests.php">Requests</a>
                    </li>
                    <li id="nav_forums"<?=
                        Format::add_class($PageID, ['forums'], 'active', true)?>>
                        <a href="forums.php">Forums</a>
                    </li>
                    <li id="nav_irc"<?=
                        Format::add_class($PageID, ['chat'], 'active', true)?>>
                        <a href="wiki.php?action=article&name=irc">IRC</a>
                    </li>
                    <li id="nav_top10"<?=
                        Format::add_class($PageID, ['top10'], 'active', true)?>>
                        <a href="top10.php">Top 10</a>
                    </li>
                    <li id="nav_rules"<?=
                        Format::add_class($PageID, ['rules'], 'active', true)?>>
                        <a href="rules.php">Rules</a>
                    </li>
                    <li id="nav_wiki"<?=
                        Format::add_class($PageID, ['wiki'], 'active', true)?>>
                        <a href="wiki.php">Wiki</a>
                    </li>
                    <li id="nav_staff"<?=
                        Format::add_class($PageID, ['staff'], 'active', true)?>>
                        <a href="staff.php" title="Staff">Staff</a>
                    </li>
                </ul>
            </div>
<?php
//Start handling alert bars
$Alerts = [];
$ModBar = [];

if ($Staff && $Staff->blogAlert()) {
    $Alerts[] = '<a href="staffblog.php">New staff blog post!</a>';
}

// Inbox
if ($notification->isTraditional(Notification::INBOX)) {
    $notification->loadInbox();
    $NewMessages = $notification->notifications();
    if (isset($NewMessages[Notification::INBOX])) {
        $Alerts[] = NotificationsManagerView::format_traditional($NewMessages[Notification::INBOX]);
    }
    $notification->clear();
}

if (G::$LoggedUser['RatioWatch']) {
    $Alerts[] = '<a href="rules.php?p=ratio">Ratio Watch</a>: You have '.time_diff(G::$LoggedUser['RatioWatchEnds'], 3).' to get your ratio over your required ratio or your leeching abilities will be disabled.';
} elseif (G::$LoggedUser['CanLeech'] != 1) {
    $Alerts[] = '<a href="rules.php?p=ratio">Ratio Watch</a>: Your downloading privileges are disabled until you meet your required ratio.';
}

// Torrents
if ($notification->isTraditional(Notification::TORRENTS)) {
    $notification->loadTorrents();
    $NewTorrents = $notification->notifications();
    if (isset($NewTorrents[Notification::TORRENTS])) {
        $Alerts[] = NotificationsManagerView::format_traditional($NewTorrents[Notification::TORRENTS]);
    }
    $notification->clear();
}
if (check_perms('users_mod')) {
    $ModBar[] = '<a href="tools.php">Toolbox</a>';
}
if (check_perms('users_mod') || G::$LoggedUser['PermissionID'] == FORUM_MOD) {
    $NumStaffPMs = G::$Cache->get_value('num_staff_pms_'.G::$LoggedUser['ID']);
    if ($NumStaffPMs === false) {
        if (check_perms('users_mod')) {

            $LevelCap = 1000;

            G::$DB->query("
                SELECT COUNT(ID)
                FROM staff_pm_conversations
                WHERE Status = 'Unanswered'
                    AND (AssignedToUser = ".G::$LoggedUser['ID']."
                        OR (LEAST('$LevelCap', Level) <= '".G::$LoggedUser['EffectiveClass']."'
                            AND Level <= ".G::$LoggedUser['Class']."))");
        }
        if (G::$LoggedUser['PermissionID'] == FORUM_MOD) {
            G::$DB->query("
                SELECT COUNT(ID)
                FROM staff_pm_conversations
                WHERE Status='Unanswered'
                    AND (AssignedToUser = ".G::$LoggedUser['ID']."
                        OR Level = '". $Classes[FORUM_MOD]['Level'] . "')");
        }
        list($NumStaffPMs) = G::$DB->next_record();
        G::$Cache->cache_value('num_staff_pms_'.G::$LoggedUser['ID'], $NumStaffPMs , 1000);
    }

    if ($NumStaffPMs > 0) {
        $ModBar[] = '<a href="staffpm.php">'.$NumStaffPMs.' Staff PMs</a>';
    }
}
if (check_perms('admin_reports')) {
    $open = \Gazelle\Report::openCount(G::$DB, G::$Cache);
    $ModBar[] = '<a href="reportsv2.php">' . $open . (($open == 1) ? ' Report' : ' Reports') . '</a>';

    $other = \Gazelle\Report::otherCount(G::$DB, G::$Cache);
    if ($other > 0) {
        $ModBar[] = '<a href="reports.php">' . $other . (($other == 1) ? ' Other report' : ' Other reports') . '</a>';
    }
} elseif (check_perms('site_moderate_forums')) {
    $open = \Gazelle\Report::forumCount(G::$DB, G::$Cache);
    if ($open > 0) {
        $ModBar[] = '<a href="reports.php">' . $open . (($open == 1) ? ' Forum report' : ' Forum reports') . '</a>';
    }
}

if (check_perms('admin_manage_applicants')) {
    $NumNewApplicants = Applicant::new_applicant_count();
    if ($NumNewApplicants > 0) {
        $ModBar[] = sprintf(
            '<a href="apply.php?action=view">%d new Applicant%s</a>',
                $NumNewApplicants,
                ($NumNewApplicants == 1 ? '' : 's')
        );
    }

    $NumNewReplies = Applicant::new_reply_count();
    if ($NumNewReplies > 0) {
        $ModBar[] = sprintf(
            '<a href="apply.php?action=view">%d new Applicant %s</a>',
                $NumNewReplies,
                ($NumNewReplies == 1 ? 'Reply' : 'Replies')
        );
    }
}

if (check_perms('users_mod') && FEATURE_EMAIL_REENABLE) {
    $NumEnableRequests = G::$Cache->get_value(AutoEnable::CACHE_KEY_NAME);
    if ($NumEnableRequests === false) {
        G::$DB->query("SELECT COUNT(1) FROM users_enable_requests WHERE Outcome IS NULL");
        list($NumEnableRequests) = G::$DB->next_record();
        G::$Cache->cache_value(AutoEnable::CACHE_KEY_NAME, $NumEnableRequests);
    }

    if ($NumEnableRequests > 0) {
        $ModBar[] = '<a href="tools.php?action=enable_requests">' . $NumEnableRequests . " Enable requests</a>";
    }
}

if (check_perms('admin_manage_payments')) {
    $Payment = new \Gazelle\Manager\Payment;
    $DuePayments = $Payment->due();
    if (count($DuePayments) > 0) {
        $AlertText = '<a href="tools.php?action=payment_list">Payments due</a>';
        foreach ($DuePayments as $p) {
            list($Text, $Expiry) = array_values($p);
            $Color = strtotime($Expiry) < (strtotime('+3 days')) ? 'red' : 'orange';
            $AlertText .= sprintf(' | <span style="color: %s">%s: %s</span>', $Color, $Text, date('Y-m-d', strtotime($Expiry)));
        }
        $Alerts[] = $AlertText;
    }

}

if (check_perms('admin_site_debug')) {
    if (!apcu_exists('DB_KEY') || !apcu_fetch('DB_KEY')) {
        $Alerts[] = '<a href="tools.php?action=dbkey"><span style="color: red">DB key not loaded</span></a>';
    }
}

if (check_perms('admin_manage_referrals')) {
    $Referrals = new \Gazelle\Manager\Referral;
    if (!$Referrals->checkBouncer()) {
        $Alerts[] = '<a href="tools.php?action=referral_sandbox"><span style="color: red">Referral bouncer not responding</span></a>';
    }
}

if (check_perms('admin_periodic_task_view')) {
    $scheduler = new \Gazelle\Schedule\Scheduler;
    if ($insane = $scheduler->getInsaneTasks()) {
        $Alerts[] = sprintf('<a href="tools.php?action=periodic&amp;mode=view">There are %d insane tasks</a>', $insane);
    }
}

if (!empty($Alerts) || !empty($ModBar)) { ?>
            <div id="alerts">
<?php
    foreach ($Alerts as $Alert) { ?>
                <div class="alertbar"><?=$Alert?></div>
<?php
    }
    if (!empty($ModBar)) { ?>
                <div class="alertbar blend">
                    <?=implode(' | ', $ModBar); echo "\n"?>
                </div>
<?php
    } ?>
            </div>
<?php
}
//Done handling alertbars

if (isset(G::$LoggedUser['SearchType']) && G::$LoggedUser['SearchType']) { // Advanced search
    $UseAdvancedSearch = true;
} else {
    $UseAdvancedSearch = false;
}
?>
            <div id="searchbars">
                <ul>
                    <li id="searchbar_torrents">
                        <span class="hidden">Torrents: </span>
                        <form class="search_form" name="torrents" action="torrents.php" method="get">
<?php
    if ($UseAdvancedSearch) { ?>
                            <input type="hidden" name="action" value="advanced" />
<?php
    } ?>
                            <input id="torrentssearch" accesskey="t" spellcheck="false"
                                    onfocus="if (this.value == 'Torrents') { this.value = ''; }"
                                    onblur="if (this.value == '') { this.value = 'Torrents'; }"
                                    value="Torrents" placeholder="Torrents" type="text" name="<?=$UseAdvancedSearch ? 'groupname' : 'searchstr' ?>" size="17" />
                        </form>
                    </li>
                    <li id="searchbar_artists">
                        <span class="hidden">Artist: </span>
                        <form class="search_form" name="artists" action="artist.php" method="get">
                            <input id="artistsearch"<?=Users::has_autocomplete_enabled('search');
                                    ?> accesskey="a"
                                    spellcheck="false" autocomplete="off"
                                    onfocus="if (this.value == 'Artists') { this.value = ''; }"
                                    onblur="if (this.value == '') { this.value = 'Artists'; }"
                                    value="Artists" placeholder="Artists" type="text" name="artistname" size="17" />
                        </form>
                    </li>
                    <li id="searchbar_requests">
                        <span class="hidden">Requests: </span>
                        <form class="search_form" name="requests" action="requests.php" method="get">
                            <input id="requestssearch" spellcheck="false"
                                    accesskey="r"
                                    onfocus="if (this.value == 'Requests') { this.value = ''; }"
                                    onblur="if (this.value == '') { this.value = 'Requests'; }"
                                    value="Requests" placeholder="Requests" type="text" name="search" size="17" />
                        </form>
                    </li>
                    <li id="searchbar_forums">
                        <span class="hidden">Forums: </span>
                        <form class="search_form" name="forums" action="forums.php" method="get">
                            <input value="search" type="hidden" name="action" />
                            <input id="forumssearch"
                                    accesskey="f"
                                    onfocus="if (this.value == 'Forums') { this.value = ''; }"
                                    onblur="if (this.value == '') { this.value = 'Forums'; }"
                                    value="Forums" placeholder="Forums" type="text" name="search" size="17" />
                        </form>
                    </li>
<!--
                    <li id="searchbar_wiki">
                        <span class="hidden">Wiki: </span>
                        <form class="search_form" name="wiki" action="wiki.php" method="get">
                            <input type="hidden" name="action" value="search" />
                            <input
                                    onfocus="if (this.value == 'Wiki') { this.value = ''; }"
                                    onblur="if (this.value == '') { this.value = 'Wiki'; }"
                                    value="Wiki" placeholder="Wiki" type="text" name="search" size="17" />
                        </form>
                    </li>
-->
                    <li id="searchbar_log">
                        <span class="hidden">Log: </span>
                        <form class="search_form" name="log" action="log.php" method="get">
                            <input id="logsearch"
                                    accesskey="l"
                                    onfocus="if (this.value == 'Log') { this.value = ''; }"
                                    onblur="if (this.value == '') { this.value = 'Log'; }"
                                    value="Log" placeholder="Log" type="text" name="search" size="17" />
                        </form>
                    </li>
                    <li id="searchbar_users">
                        <span class="hidden">Users: </span>
                        <form class="search_form" name="users" action="user.php" method="get">
                            <input type="hidden" name="action" value="search" />
                            <input
                                    id="userssearch" accesskey="u"
                                    onfocus="if (this.value == 'Users') { this.value = ''; }"
                                    onblur="if (this.value == '') { this.value = 'Users'; }"
                                    value="Users" placeholder="Users" type="text" name="search" size="20" />
                        </form>
                    </li>
                </ul>
            </div>
        </div>
        <div id="content">
