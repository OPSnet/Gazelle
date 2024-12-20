<?php

class View {
    /**
     * Display the page header
     * @param array<string> $option
     */
    public static function show_header(string $pageTitle, array $option = []): void {
        echo self::header($pageTitle, $option);
    }

    /**
     * Generate the page header
     * @param array<string> $option
     */
    public static function header(string $pageTitle, array $option = []): string {
        if ($pageTitle != '') {
            $pageTitle .= ' :: ';
        }
        $pageTitle .= SITE_NAME;
        global $Viewer;
        $module = is_null($Viewer)
            ? 'index'
            : $Viewer->requestContext()->module();

        $js = [];

        if (DEBUG_MODE || $Viewer?->permitted('site_debug')) {
            $js[] = 'debug';
        }

        global $Twig;
        if (!isset($Viewer) || $pageTitle == 'Recover Password :: ' . SITE_NAME) {
            $js[] = 'storage.class';
            if (!empty($option['js'])) {
                array_push($js, ...explode(',', $option['js']));
            }
            echo $Twig->render('index/public-header.twig', [
                'page_title' => $pageTitle,
                'script'     => array_map(fn($s) => "$s.js", $js),
            ]);
            return '';
        }

        array_push($js,
            'jquery',
            'script_start',
            'ajax.class',
            'autocomplete',
            'jquery.autocomplete',
            'jquery.countdown.min',
            'global',
            'katex-0.16.10.min'
        );

        if (DEBUG_MODE || $Viewer->permitted('site_debug')) {
            $js[] = 'jquery-migrate';
        }
        if (!empty($option['js'])) {
            array_push($js, ...explode(',', $option['js']));
        }

        $cssList  = ['katex/katex-0.16.10.min.css'];
        $scssList = ['global.css'];

        if (!empty($option['css'])) {
            array_push($scssList, ...array_map(fn($s) => "$s/style.css", explode(',', $option['css'])));
        }
        if ($Viewer->option('Tooltipster') ?? 1) {
            array_push($js, 'tooltipster', 'tooltipster_settings');
            $scssList[] = 'tooltipster/style.css';
        }
        if ($Viewer->option('UseOpenDyslexic')) {
            $scssList[] = 'opendyslexic/style.css';
        }

        $activity = new Gazelle\User\Activity($Viewer);
        $activity->configure()
            ->setStaffPM(new Gazelle\Manager\StaffPM());

        $notifier = new Gazelle\User\Notification($Viewer);
        $alertList = $notifier->setDocument($module, $_REQUEST['action'] ?? '')->alertList();
        foreach ($alertList as $alert) {
            if (in_array($alert->display(), [Gazelle\User\Notification::DISPLAY_TRADITIONAL, Gazelle\User\Notification::DISPLAY_TRADITIONAL_PUSH])) {
                $activity->setAlert(sprintf('<a href="%s">%s</a>', $alert->notificationUrl(), $alert->title()));
            }
        }
        if ($notifier->useNoty()) {
            array_push($js, 'noty/noty', 'noty/layouts/bottomRight', 'noty/themes/default', 'user_notifications');
        }

        $payMan = new Gazelle\Manager\Payment();
        if ($Viewer->permitted('users_mod')) {
            $raTypeMan = new \Gazelle\Manager\ReportAutoType();
            $activity->setStaff(new Gazelle\Staff($Viewer))
                ->setReport(new Gazelle\Stats\Report())
                ->setPayment($payMan)
                ->setApplicant(new Gazelle\Manager\Applicant())
                ->setDb(new Gazelle\DB())
                ->setScheduler(new Gazelle\TaskScheduler())
                ->setSSLHost(new Gazelle\Manager\SSLHost())
                ->setAutoReport(new Gazelle\Search\ReportAuto(new \Gazelle\Manager\ReportAuto($raTypeMan),
                                                              $raTypeMan))
                ;

            if (OPEN_EXTERNAL_REFERRALS) {
                $activity->setReferral(new Gazelle\Manager\Referral());
            }
        }

        $PageID = [$module, $_REQUEST['action'] ?? false, $_REQUEST['type'] ?? false];
        $navLinks = [];
        foreach ((new Gazelle\Manager\UserNavigation())->userControlList($Viewer) as $n) {
            [$ID, $Key, $Title, $Target, $Tests, $TestUser, $Mandatory] = array_values($n);
            if (str_contains($Tests, ':')) {
                $testList = [];
                foreach (array_map('trim', explode(',', $Tests)) as $Part) {
                    $testList[] = array_map(fn ($t) => $t === 'false' ? false : $t, explode(':', $Part));
                }
            } elseif (str_contains($Tests, ',')) {
                $testList = array_map(fn ($t) => $t === 'false' ? false : $t, explode(',', $Tests));
            } else {
                $testList = [$Tests];
            }
            if ($Key === 'notifications' && !$Viewer->permitted('site_torrents_notify')) {
                continue;
            }

            $extraClass = [];
            if ($Key === 'inbox') {
                $Target = 'inbox.php';
            } elseif ($Key === 'subscriptions') {
                if (isset($alertList['Subscription'])) {
                    $extraClass[] = 'new-subscriptions';
                }
                if (self::add_active($PageID, ['userhistory', 'subscriptions'])) {
                    $extraClass[] = 'active';
                }
            } elseif ($Key === 'staffinbox') {
                if ($activity->showStaffInbox()) {
                    $extraClass[] = 'new-subscriptions';
                }
                if (self::add_active($PageID, $testList)) {
                    $extraClass[] = 'active';
                }
            } elseif ($TestUser && $Viewer->id() != ($_REQUEST['userid'] ?? 0) && self::add_active($PageID, $testList)) {
                $extraClass[] = 'active';
            }
            $navLinks[] = "<li id=\"nav_{$Key}\""
                . ($extraClass ? ' class="' . implode(' ', $extraClass) . '"' : '')
                . "><a href=\"{$Target}\">{$Title}</a></li>\n";
        }

        return $Twig->render('index/private-header.twig', [
            'auth_args'    => "&amp;user={$Viewer->id()}&amp;passkey={$Viewer->announceKey()}&amp;authkey={$Viewer->auth()}&amp;auth={$Viewer->rssAuth()}",
            'page_title'   => $pageTitle,
            'script'       => array_map(fn($s) => "$s.js", $js),
            'css_style'    => $cssList,
            'scss_style'   => $scssList,
            'stylesheet'   => new \Gazelle\User\Stylesheet($Viewer),
            'viewer'       => $Viewer,
        ])
        . $Twig->render('index/page-header.twig', [
            'action'      => $_REQUEST['action'] ?? null,
            'action_list' => $activity->actionList(),
            'alert_list'  => $activity->alertList(),
            'bonus'       => new Gazelle\User\Bonus($Viewer),
            'document'    => $module,
            'dono_target' => $payMan->monthlyPercent(new Gazelle\Manager\Donation()),
            'nav_links'   => $navLinks,
            'user'        => $Viewer,
        ]);
    }

    /**
     * Determine if a link should be marked as 'active'
     * @param array<mixed> $Target
     * @param array<mixed> $Tests
     */
    protected static function add_active(array $Target, array $Tests, bool $UserIDKey = false): bool {
        if (!is_array($Tests[0])) {
            // Test all values in vectors
            foreach ($Tests as $Type => $Part) {
                if (!isset($Target[$Type]) || $Target[$Type] !== $Part) {
                    return false;
                }
            }
        } else {
            // Loop to the end of the array or until we find a matching test
            foreach ($Tests as $Test) {
                // If $Pass remains true after this test, it's a match
                foreach ($Test as $Type => $Part) {
                    if (!isset($Target[$Type]) || $Target[$Type] !== $Part) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /**
     * Display the footer of the page
     */
    public static function show_footer(): void {
        echo self::footer();
    }

    public static function footer(bool $showDisclaimer = false): string {
        global $Twig, $Viewer;
        if (!isset($Viewer)) {
            return $Twig->render('index/public-footer.twig');
        }

        $launch = date('Y');
        if ($launch != SITE_LAUNCH_YEAR) {
            $launch = SITE_LAUNCH_YEAR . "-$launch";
        }

        $alertList = (new Gazelle\User\Notification($Viewer))
            ->setDocument(
                $Viewer->requestContext()->module(),
                $_REQUEST['action'] ?? ''
            )
            ->alertList();
        $notification = [];
        foreach ($alertList as $alert) {
            if (in_array($alert->display(), [Gazelle\User\Notification::DISPLAY_POPUP, Gazelle\User\Notification::DISPLAY_POPUP_PUSH])) {
                $notification[] = $alert;
            }
        }

        global $Cache, $Debug, $SessionID;
        return $Twig->render('index/private-footer.twig', [
            'cache'        => $Cache,
            'db_time'      => Gazelle\DB::DB()->Time,
            'debug'        => $Debug,
            'disclaimer'   => $showDisclaimer,
            'last_active'  => (new Gazelle\User\Session($Viewer))->lastActive($SessionID),
            'launch'       => $launch,
            'load'         => sys_getloadavg(),
            'notification' => $notification,
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
    }
}
