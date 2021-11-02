<?php
class View {

    protected static $footerSeen = false;

    /**
     * This function is to include the header file on a page.
     *
     * @param string $pageTitle the title of the page
     * @param array $option associative array which has two keys 'css' and 'js'
     *                      where each value is a comma separated list of files to include
     */
    public static function show_header(string $pageTitle, $option = []) {
        global $Document, $Twig, $Viewer;
        if ($pageTitle != '') {
            $pageTitle .= ' :: ';
        }
        $pageTitle .= SITE_NAME;

        if (!isset($Viewer) || $pageTitle == 'Recover Password :: ' . SITE_NAME) {
            echo $Twig->render('index/public-header.twig', [
                'page_title' => html_entity_decode($pageTitle),
            ]);
            return;
        }
        $Style = [
            'global.css',
        ];
        if (!empty($option['css'])) {
            $Style = array_merge(
                $Style,
                array_map(
                    function ($style) {
                        return $style . "/style.css";
                    },
                    explode(',', $option['css'])
                )
            );
        }

        $Scripts = [
            'jquery',
            'jquery.autocomplete',
            'jquery.countdown.min',
            'script_start',
            'ajax.class',
            'global',
            'autocomplete',
        ];
        if (!empty($option['js'])) {
            $Scripts = array_merge($Scripts, explode(',', $option['js']));
        }
        if (DEBUG_MODE || $Viewer->permitted('site_debug')) {
            array_push($Scripts, 'jquery-migrate', 'debug');
        }
        if ($Viewer->option('Tooltipster') ?? 1) {
            array_push($Scripts, 'tooltipster', 'tooltipster_settings');
            array_push($Style, 'tooltipster/style.css');
        }
        if ($Viewer->option('UseOpenDyslexic')) {
            array_push($Style, 'opendyslexic/style.css');
        }

        $notifMan = new Gazelle\Manager\Notification($Viewer->id());
        if ($notifMan->useNoty()) {
            array_push($Scripts, 'noty/noty', 'noty/layouts/bottomRight', 'noty/themes/default', 'user_notifications');
        }
        $NewSubscriptions = [];
        $hasNewSubscriptions = false;
        if ($Viewer->permitted('site_torrents_notify')) {
            $Notifications = $notifMan->notifications();
            $hasNewSubscriptions = isset($Notifications[Gazelle\Manager\Notification::SUBSCRIPTIONS]);
            if ($notifMan->isSkipped(Gazelle\Manager\Notification::SUBSCRIPTIONS)) {
                $NewSubscriptions = (new Gazelle\Subscription($Viewer))->unread();
            }
        }

        $activity = new Gazelle\Activity;
        if ($Viewer->onRatioWatch()) {
            $activity->setAlert('<a class="nobr" href="rules.php?p=ratio">Ratio Watch</a>: You have '
                . time_diff($Viewer->ratioWatchExpiry(), 3)
                . ' to get your ratio over your required ratio or your leeching abilities will be disabled.'
            );
        } elseif (!$Viewer->canLeech()) {
            $activity->setAlert('<a class="nobr" href="rules.php?p=ratio">Ratio Watch</a>: Your downloading privileges are disabled until you meet your required ratio.');
        }

        $needStaffInbox = false;
        if ($Viewer->permitted('users_mod')) {
            $activity->setAction('<a class="nobr" href="tools.php">Toolbox</a>');
        }
        if ($Viewer->isStaff()) {
            $activity->setNotification($notifMan);
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

        $payMan = new Gazelle\Manager\Payment;
        if ($Viewer->permitted('admin_manage_payments')) {
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

        if ($Viewer->permitted('admin_reports')) {
            $repoMan = new Gazelle\Report;
            $open = $repoMan->openCount();
            $activity->setAction("<a class=\"nobr\" href=\"reportsv2.php\">$open Report" . plural($open) . '</a>');
            $other = $repoMan->otherCount();
            if ($other > 0) {
                $activity->setAction("<a class=\"nobr\" href=\"reports.php\">$other Other report" . plural($other) . '</a>');
            }
        } elseif ($Viewer->permitted('site_moderate_forums')) {
            $open = (new Gazelle\Report)->forumCount();
            if ($open > 0) {
                $activity->setAction("<a href=\"reports.php\">$open Forum report" . plural($open) . '</a>');
            }
        }

        if ($Viewer->permitted('admin_manage_applicants')) {
            $appMan = new Gazelle\Manager\Applicant;
            $count = $appMan->newApplicantCount();
            if ($count > 0) {
                $activity->setAction(sprintf(
                    '<a href="apply.php?action=view">%d new Applicant%s</a>', $count, plural($count)
                ));
            }
            $count = $appMan->newReplyCount();
            if ($count > 0) {
                $activity->setAction(sprintf(
                    '<a href="apply.php?action=view">%d new Applicant %s</a>', $count, ($count == 1 ? 'Reply' : 'Replies')
                ));
            }
        }

        if ($Viewer->permitted('admin_site_debug')) {
            if (!apcu_exists('DB_KEY') || !apcu_fetch('DB_KEY')) {
                $activity->setAlert('<a href="tools.php?action=dbkey"><span style="color: red">DB key not loaded</span></a>');
            }
        }

        if ($Viewer->permitted('admin_manage_referrals')) {
            if (!(new Gazelle\Manager\Referral)->checkBouncer()) {
                $activity->setAlert('<a href="tools.php?action=referral_sandbox"><span class="nobr" style="color: red">Referral bouncer not responding</span></a>');
            }
        }

        if ($Viewer->permitted('admin_periodic_task_view')) {
            if ($insane = (new Gazelle\Schedule\Scheduler)->getInsaneTasks()) {
                $activity->setAlert(sprintf('<a href="tools.php?action=periodic&amp;mode=view">There are %d insane tasks</a>', $insane));
            }
        }

        $parseNavItem = function($val) {
            $val = trim($val);
            return $val === 'false' ? false : $val;
        };

        $PageID = [$Document, $_REQUEST['action'] ?? false, $_REQUEST['type'] ?? false];
        $navLinks = [];
        $navItems = (new Gazelle\Manager\User)->forumNavItemUserList($Viewer);
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
            if ($Key === 'notifications' && !$Viewer->permitted('site_torrents_notify')) {
                continue;
            }

            $extraClass = [];
            if ($Key === 'inbox') {
                $Target = 'inbox.php';
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

        echo $Twig->render('index/private-header.twig', [
            'auth_args'    => '&amp;user=' . $Viewer->id() . '&amp;passkey=' . $Viewer->announceKey() . '&amp;authkey=' . $Viewer->auth() . '&amp;auth=' . $Viewer->rssAuth(),
            'page_title'   => html_entity_decode($pageTitle),
            'script'       => array_map(fn($s) => "$s.js", $Scripts),
            'style'        => $Style,
            'viewer'       => $Viewer,
        ]);
        echo $Twig->render('index/page-header.twig', [
            'action'            => $_REQUEST['action'] ?? null,
            'action_list'       => $activity->actionList(),
            'alert_list'        => $activity->alertList(),
            'document'          => $Document,
            'dono_target'       => $payMan->monthlyPercent(new Gazelle\Manager\Donation),
            'nav_links'         => $navLinks,
            'subscriptions'     => $NewSubscriptions,
            'user'              => $Viewer,
            'user_class'        => (new Gazelle\Manager\User)->userclassName($Viewer->primaryClass()),
        ]);
    }

    /**
     * This function is to include the footer file on a page.
     *
     * @param array $Options an optional array that you can pass information to the
     *                       header through as well as setup certain limitations
     *                         Here is a list of parameters that work in the $Options array:
     *                       ['disclaimer'] = [boolean] (False) Displays the disclaimer in the footer
     */
    public static function show_footer($Options = []) {
        if (self::$footerSeen) {
            return;
        }
        self::$footerSeen = true;
        echo self::footer($Options);
    }

    public static function footer($Options = []) {
        global $Twig, $Viewer;
        if (!isset($Viewer) || ($Options['recover'] ?? false) === true) {
            return $Twig->render('index/public-footer.twig');
        }

        ob_start();

        $launch = date('Y');
        if ($launch != SITE_LAUNCH_YEAR) {
            $launch = SITE_LAUNCH_YEAR . "-$launch";
        }

        global $Cache, $DB, $Debug, $SessionID;
        echo $Twig->render('index/private-footer.twig', [
            'cache_time'   => $Cache->Time,
            'db_time'      => $DB->Time,
            'debug'        => $Debug,
            'disclaimer'   => isset($Options['disclaimer']),
            'last_active'  => (new Gazelle\Session($Viewer->id()))->lastActive($SessionID),
            'launch'       => $launch,
            'load'         => sys_getloadavg(),
            'notification' => (new Gazelle\Manager\Notification())->registeredNotifications($Viewer->id()),
            'memory'       => memory_get_usage(true),
            'date'         => date('Y-m-d'),
            'textarea_js'  => Gazelle\Util\Textarea::activate(),
            'time'         => date('H:i'),
            'time_ms'      => (microtime(true) - $Debug->startTime()) * 1000,
            'viewer'       => $Viewer,
            'sphinxql'     => class_exists('Sphinxql') && !empty(\Sphinxql::$Queries)
                ? ['list'  => \Sphinxql::$Queries, 'time' => \Sphinxql::$Time]
                : [],
        ]);
        return ob_get_clean();
    }
}
