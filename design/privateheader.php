<?php

use Gazelle\Manager\Notification;

$user = new Gazelle\User(G::$LoggedUser['ID']);
$authArgs = '&amp;user=' . G::$LoggedUser['ID']
    . '&amp;auth=' . G::$LoggedUser['RSS_Auth']
    . '&amp;passkey=' . G::$LoggedUser['torrent_pass']
    . '&amp;authkey=' . G::$LoggedUser['AuthKey'];
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
        [$FilterID, $FilterName] = $Filter;
?>
    <link rel="alternate" type="application/rss+xml" href="feeds.php?feed=torrents_notify_<?=
        $FilterID?>_<?=G::$LoggedUser['torrent_pass']?><?= $authArgs ?>&amp;name=<?=
        urlencode($FilterName)?>" title="<?=SITE_NAME?> - <?=display_str($FilterName)?>" />
<?php
    }
}
?>
    <link rel="stylesheet" type="text/css" href="<?=
        STATIC_SERVER?>/styles/global.css?v=<?=filemtime(SERVER_ROOT.'/sass/global.scss')?>" />
<?php

$Scripts = [
    'jquery',
    'script_start',
    'ajax.class',
    'global',
    'jquery.autocomplete',
    'autocomplete',
    'jquery.countdown.min'
];
if (!empty($JSIncludes)) {
    $Scripts = array_merge($Scripts, explode(',', $JSIncludes));
}

if (DEBUG_MODE || check_perms('site_debug')) {
    $Scripts[] = 'jquery-migrate';
    $Scripts[] = 'debug';
}
if (!isset(G::$LoggedUser['Tooltipster']) || G::$LoggedUser['Tooltipster']) {
    $Scripts[] = 'tooltipster';
    $Scripts[] = 'tooltipster_settings';
?>
    <link rel="stylesheet" href="<?=STATIC_SERVER?>/styles/tooltipster/style.css?v=<?=filemtime(SERVER_ROOT.'/sass/tooltipster/style.scss')?>" type="text/css" media="screen" />
<?php
}

if (empty(G::$LoggedUser['StyleURL'])) {
?>
    <link rel="stylesheet" type="text/css" title="<?=G::$LoggedUser['StyleName']?>" media="screen" href="<?=
        STATIC_SERVER?>/styles/<?=G::$LoggedUser['StyleName']?>/style.css?v=<?=filemtime(SERVER_ROOT.'/sass/'.G::$LoggedUser['StyleName'].'/style.scss')?>" />
<?php
} else {
        $StyleURLInfo = parse_url(G::$LoggedUser['StyleURL']);
        if (substr(G::$LoggedUser['StyleURL'], -4) == '.css'
                && $StyleURLInfo['query'].$StyleURLInfo['fragment'] == ''
                && $StyleURLInfo['host'] === SITE_HOST
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
?>
    <link rel="stylesheet" type="text/css" charset="utf-8" href="<?=
        STATIC_SERVER?>/styles/opendyslexic/style.css?v=<?=filemtime(SERVER_ROOT.'/public/static/styles/opendyslexic/style.css')?>" />
<?php
}
$ExtraCSS = explode(',', $CSSIncludes);
foreach ($ExtraCSS as $CSS) {
    if (trim($CSS) == '') {
        continue;
    }
?>
    <link rel="stylesheet" type="text/css" media="screen" href="<?=STATIC_SERVER."/styles/$CSS/style.css?v="
        . filemtime(SERVER_ROOT."/public/static/styles/$CSS/style.css")?>" />
<?php
}
foreach ($Scripts as $Script) {
?>
    <script src="<?= STATIC_SERVER ?>/functions/<?=$Script?>.js?v=<?=filemtime(SERVER_ROOT
        . '/public/static/functions/'.$Script.'.js')?>" type="text/javascript"></script>
<?php } ?>
    <script type="text/javascript">
        //<![CDATA[
        var authkey = "<?=G::$LoggedUser['AuthKey']?>";
        var userid = <?=G::$LoggedUser['ID']?>;
        //]]>
    </script>

<?php

// Get notifications early to change menu items if needed
$notifMan = new Notification(G::$LoggedUser['ID']);

$Notifications = $notifMan->notifications();
$NewSubscriptions = isset($Notifications[Notification::SUBSCRIPTIONS]);
if ($notifMan->isSkipped(Notification::SUBSCRIPTIONS)) {
    $NewSubscriptions = (new Gazelle\Manager\Subscription(G::$LoggedUser['ID']))->unread();
}

if ($notifMan->useNoty()) {
    foreach (['noty/noty.js', 'noty/layouts/bottomRight.js', 'noty/themes/default.js', 'user_notifications.js'] as $inc) {
?>
<script src="<?= STATIC_SERVER . "/functions/$inc" ?>?v=<?= filemtime(SERVER_ROOT . "/public/static/functions/$inc")?>" type="text/javascript"></script>
<?php
    }
}

$activity = new Gazelle\Activity;
if (G::$LoggedUser['RatioWatch']) {
    $activity->setAlert('<a class="nobr" href="rules.php?p=ratio">Ratio Watch</a>: You have '
        . time_diff(G::$LoggedUser['RatioWatchEnds'], 3)
        . ' to get your ratio over your required ratio or your leeching abilities will be disabled.'
    );
} elseif (G::$LoggedUser['CanLeech'] != 1) {
    $activity->setAlert('<a class="nobr" href="rules.php?p=ratio">Ratio Watch</a>: Your downloading privileges are disabled until you meet your required ratio.');
}

$needStaffInbox = false;
if (check_perms('users_mod') || G::$LoggedUser['PermissionID'] === FORUM_MOD) {
    if (check_perms('users_mod')) {
        $activity->setAction('<a class="nobr" href="tools.php">Toolbox</a>');
    }

    $staff = new Gazelle\Staff($user);
    $count = $staff->pmCount();
    if ($count > 0) {
        $needStaffInbox = true;
        $activity->setAction('<a class="nobr" href="staffpm.php">' . $count . ' Staff PMs</a>');
    }

    if ($staff->blogAlert()) {
        $activity->setAlert('<a class="nobr" href="staffblog.php">New staff blog post!</a>');
    }

    if (FEATURE_EMAIL_REENABLE) {
        $NumEnableRequests = G::$Cache->get_value(AutoEnable::CACHE_KEY_NAME);
        if ($NumEnableRequests === false) {
            $NumEnableRequests = G::$DB->scalar("SELECT count(*) FROM users_enable_requests WHERE Outcome IS NULL");
            G::$Cache->cache_value(AutoEnable::CACHE_KEY_NAME, $NumEnableRequests);
        }
        if ($NumEnableRequests > 0) {
            $activity->setAction('<a class="nobr" href="tools.php?action=enable_requests">' . $NumEnableRequests . " Enable requests</a>");
        }
    }
}

$activity->setNotification($notifMan);
$payMan = new Gazelle\Manager\Payment;

if (check_perms('admin_manage_payments')) {
    $due = $payMan->due();
    if ($due) {
        $AlertText = '<a class="nobr" href="tools.php?action=payment_list">Payments due</a>';
        foreach ($due as $p) {
            [$Text, $Expiry] = array_values($p);
            $Color = strtotime($Expiry) < (strtotime('+3 days')) ? 'red' : 'orange';
            $AlertText .= sprintf(' | <span style="color: %s">%s: %s</span>', $Color, $Text, date('Y-m-d', strtotime($Expiry)));
        }
        $activity->setAlert($AlertText);
    }
}

if (check_perms('admin_reports')) {
    $repoMan = new Gazelle\Report;
    $open = $repoMan->openCount();
    $activity->setAction("<a class=\"nobr\" href=\"reportsv2.php\">$open Report" . plural($open) . '</a>');
    $other = $repoMan->otherCount();
    if ($other > 0) {
        $activity->setAction("<a class=\"nobr\" href=\"reports.php\">$other Other report" . plural($other) . '</a>');
    }
} elseif (check_perms('site_moderate_forums')) {
    $open = (new Gazelle\Report)->forumCount();
    if ($open > 0) {
        $activity->setAction("<a href=\"reports.php\">$open Forum report" . plural($open) . '</a>');
    }
}

if (check_perms('admin_manage_applicants')) {
    $appMan = new Gazelle\Manager\Applicant;
    $NumNewApplicants = $appMan->newApplicantCount();
    if ($NumNewApplicants > 0) {
        $activity->setAction(sprintf(
            '<a href="apply.php?action=view">%d new Applicant%s</a>',
                $NumNewApplicants,
                plural($NumNewApplicants)
        ));
    }
    $NumNewReplies = $appMan->newReplyCount();
    if ($NumNewReplies > 0) {
        $activity->setAction(sprintf(
            '<a href="apply.php?action=view">%d new Applicant %s</a>',
                $NumNewReplies,
                ($NumNewReplies == 1 ? 'Reply' : 'Replies')
        ));
    }
}

if (check_perms('admin_site_debug')) {
    if (!apcu_exists('DB_KEY') || !apcu_fetch('DB_KEY')) {
        $activity->setAlert('<a href="tools.php?action=dbkey"><span style="color: red">DB key not loaded</span></a>');
    }
}

if (check_perms('admin_manage_referrals')) {
    if (!(new Gazelle\Manager\Referral)->checkBouncer()) {
        $activity->setAlert('<a href="tools.php?action=referral_sandbox"><span class="nobr" style="color: red">Referral bouncer not responding</span></a>');
    }
}

if (check_perms('admin_periodic_task_view')) {
    if ($insane = (new Gazelle\Schedule\Scheduler)->getInsaneTasks()) {
        $activity->setAlert(sprintf('<a href="tools.php?action=periodic&amp;mode=view">There are %d insane tasks</a>', $insane));
    }
}

$parseNavItem = function($val) {
    $val = trim($val);
    return $val === 'false' ? false : $val;
};

$navItems = Users::get_user_nav_items(G::$LoggedUser['ID']);

$navLinks = [];
foreach ($navItems as $n) {
    [$ID, $Key, $Title, $Target, $Tests, $TestUser, $Mandatory] = array_values($n);
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
    if ($Key === 'notifications' && !check_perms('site_torrents_notify')) {
        continue;
    }

    $extraClass = [];
    if ($Key === 'inbox') {
        $Target = Gazelle\Inbox::getLinkQuick(null, G::$LoggedUser['ListUnreadPMsFirst'] ?? false);
    } elseif ($Key === 'subscriptions') {
        if ($NewSubscriptions) {
            $extraClass[] = 'new-subscriptions';
        }
        $extraClass[] = Format::add_class($PageID, ['userhistory', 'subscriptions'], 'active', false);
    } elseif ($Key === 'staffinbox') {
        if ($needStaffInbox) {
            $extraClass[] = 'new-subscriptions';
        }
        $extraClass[] = Format::add_class($PageID, $Tests, 'active', false);
    }

    $li = "<li id=\"nav_{$Key}\"" . (
        $extraClass
            ? (' class="' . implode(' ', $extraClass) . '"')
            : Format::add_class($PageID, $Tests, 'active', true, $TestUser ? 'userid' : false)
        ) . '>';
    $navLinks[] = $li . '<a href="' . $Target . '">' . $Title . "</a></li>\n";
}

global $Twig;
echo $Twig->render('index/private-header.twig', [
    'action'            => $_REQUEST['action'] ?? null,
    'action_list'       => $activity->actionList(),
    'alert_list'        => $activity->alertList(),
    'auth'              => G::$LoggedUser['AuthKey'],
    'advanced_search'   => isset(G::$LoggedUser['SearchType']) && G::$LoggedUser['SearchType'],
    'document'          => $Document,
    'dono_target'       => $payMan->monthlyPercent(new Gazelle\Manager\Donation),
    'nav_links'         => $navLinks,
    'required_ratio'    => G::$LoggedUser['RequiredRatio'],
    'subscriptions'     => $NewSubscriptions,
    'user'              => $user,
    'user_class'        => (new Gazelle\Manager\User)->userclassName($user->primaryClass()),
]);
