<?php

global $LoggedUser, $Viewer;

if (empty($LoggedUser['StyleURL'])) {
?>
    <link rel="stylesheet" type="text/css" title="<?= $Viewer->stylesheetName() ?>" media="screen" href="<?=
        STATIC_SERVER?>/styles/<?= $Viewer->stylesheetName() ?>/style.css?v=<?=filemtime(SERVER_ROOT.'/sass/'. $Viewer->stylesheetName() .'/style.scss')?>" />
<?php
} else {
        $StyleURLInfo = parse_url($LoggedUser['StyleURL']);
        if (substr($LoggedUser['StyleURL'], -4) == '.css'
                && $StyleURLInfo['query'].$StyleURLInfo['fragment'] == ''
                && $StyleURLInfo['host'] === SITE_HOST
                && file_exists(SERVER_ROOT.$StyleURLInfo['path'])) {
            $StyleURL = $LoggedUser['StyleURL'].'?v='.filemtime(SERVER_ROOT.'/public/'.$StyleURLInfo['path']);
        } else {
            $StyleURL = $LoggedUser['StyleURL'];
        }
?>
    <link rel="stylesheet" type="text/css" media="screen" href="<?=$StyleURL?>" title="External CSS" />
<?php
}

$activity = new Gazelle\Activity;
if ($LoggedUser['RatioWatch']) {
    $activity->setAlert('<a class="nobr" href="rules.php?p=ratio">Ratio Watch</a>: You have '
        . time_diff($LoggedUser['RatioWatchEnds'], 3)
        . ' to get your ratio over your required ratio or your leeching abilities will be disabled.'
    );
} elseif ($LoggedUser['CanLeech'] != 1) {
    $activity->setAlert('<a class="nobr" href="rules.php?p=ratio">Ratio Watch</a>: Your downloading privileges are disabled until you meet your required ratio.');
}

$needStaffInbox = false;
if (check_perms('users_mod') || $LoggedUser['PermissionID'] === FORUM_MOD) {
    if (check_perms('users_mod')) {
        $activity->setAction('<a class="nobr" href="tools.php">Toolbox</a>');
    }

    $staff = new Gazelle\Staff($Viewer);
    $count = $staff->pmCount();
    if ($count > 0) {
        $needStaffInbox = true;
        $activity->setAction('<a class="nobr" href="staffpm.php">' . $count . ' Staff PMs</a>');
    }

    if ($staff->blogAlert()) {
        $activity->setAlert('<a class="nobr" href="staffblog.php">New staff blog post!</a>');
    }

    if (FEATURE_EMAIL_REENABLE) {
        global $Cache, $DB;
        $NumEnableRequests = $Cache->get_value(AutoEnable::CACHE_KEY_NAME);
        if ($NumEnableRequests === false) {
            $NumEnableRequests = $DB->scalar("SELECT count(*) FROM users_enable_requests WHERE Outcome IS NULL");
            $Cache->cache_value(AutoEnable::CACHE_KEY_NAME, $NumEnableRequests);
        }
        if ($NumEnableRequests > 0) {
            $activity->setAction('<a class="nobr" href="tools.php?action=enable_requests">' . $NumEnableRequests . " Enable requests</a>");
        }
    }
}

$notifMan = new Gazelle\Manager\Notification($Viewer->id());
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

if (!$Viewer->permitted('site_torrents_notify')) {
    $hasNewSubscriptions = false;
    $NewSubscriptions = [];
} else {
    $notifMan = new Gazelle\Manager\Notification($Viewer->id());
    $Notifications = $notifMan->notifications();
    $hasNewSubscriptions = isset($Notifications[Gazelle\Manager\Notification::SUBSCRIPTIONS]);
    if ($notifMan->isSkipped(Gazelle\Manager\Notification::SUBSCRIPTIONS)) {
        $NewSubscriptions = (new Gazelle\Manager\Subscription($Viewer->id()))->unread();
    }
}

$parseNavItem = function($val) {
    $val = trim($val);
    return $val === 'false' ? false : $val;
};

$navItems = Users::get_user_nav_items($LoggedUser['ID']);

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
        $Target = Gazelle\Inbox::getLinkQuick(null, $LoggedUser['ListUnreadPMsFirst'] ?? false);
    } elseif ($Key === 'subscriptions') {
        if ($hasNewSubscriptions) {
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
echo $Twig->render('index/page-header.twig', [
    'action'            => $_REQUEST['action'] ?? null,
    'action_list'       => $activity->actionList(),
    'alert_list'        => $activity->alertList(),
    'auth'              => $LoggedUser['AuthKey'],
    'advanced_search'   => isset($LoggedUser['SearchType']) && $LoggedUser['SearchType'],
    'document'          => $Document,
    'dono_target'       => $payMan->monthlyPercent(new Gazelle\Manager\Donation),
    'nav_links'         => $navLinks,
    'required_ratio'    => $LoggedUser['RequiredRatio'],
    'subscriptions'     => $NewSubscriptions,
    'user'              => $Viewer,
    'user_class'        => (new Gazelle\Manager\User)->userclassName($Viewer->primaryClass()),
]);
