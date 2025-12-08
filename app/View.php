<?php
// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace Gazelle;

class View extends Base {
    use Pg;

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
        $js = isset($option['js']) ? array_map(fn($s) => "$s.js", explode(',', $option['js'])) : [];
        if (!Base::staticRequestContext()->hasViewer()) {
            return self::$twig->render('index/public-header.twig', [
                'page_title' => $pageTitle,
                'script'     => $js,
            ]);
        }
        $user = Base::staticRequestContext()->viewer();

        $staffPmManager = new Manager\StaffPM();
        $activity = new User\Activity($user);
        $activity->configure()->setStaffPM($staffPmManager);

        $notifier  = new User\Notification($user);
        $module    = $user->requestContext()->module();
        $alertList = $notifier->setDocument($module, $_REQUEST['action'] ?? '')->alertList();
        foreach ($alertList as $alert) {
            if (in_array($alert->display(), [User\Notification::DISPLAY_TRADITIONAL, User\Notification::DISPLAY_TRADITIONAL_PUSH])) {
                $activity->setAlert(sprintf('<a href="%s">%s</a>', $alert->notificationUrl(), $alert->title()));
            }
        }

        $payMan = new Manager\Payment();
        if ($user->permitted('users_mod')) {
            $raTypeMan = new Manager\ReportAutoType();
            $activity->setStaff(new Staff($user))
                ->setReport(new Stats\Report())
                ->setPayment($payMan)
                ->setApplicant(new Manager\Applicant())
                ->setScheduler(new TaskScheduler())
                ->setSSLHost(new Manager\SSLHost())
                ->setAutoReport(
                    new Search\ReportAuto(
                        new Manager\ReportAuto($raTypeMan),
                        $raTypeMan
                    )
                );

            if ($user->permitted('admin_site_debug')) {
                $longRunning = new DB()->longRunning();
                if ($longRunning > 0) {
                    $message = "$longRunning long-running DB operation" . plural($longRunning);
                    $activity->setAlert("<span title=\"$message\" class=\"sys-error\">DB</span>");
                }
                // Check that Ocelot is still writing to xbt_files_users.
                // If not, look for database locks and check the Ocelot log

                if (!new Tracker()->recentUpdate(TRACKER_REFRESH_TIMEOUT)) {
                    $activity->setAlert('<span title="No update received from tracker within '
                        . Util\Time::convertSeconds(TRACKER_REFRESH_TIMEOUT)
                        . '" class="sys-error">TRACKER</span>'
                    );
                }
            }

            $threshold = new Manager\SiteOption()
                ->findValueByName('download-warning-threshold');
            if ($threshold) {
                $activity->setStats((int)$threshold, new Stats\Torrent());
            }

            if (OPEN_EXTERNAL_REFERRALS) {
                $activity->setReferral(new Manager\Referral());
            }
        }

        $PageID = [$module, $_REQUEST['action'] ?? false, $_REQUEST['type'] ?? false];
        $navLinks = [];
        foreach (new Manager\UserNavigation()->userControlList($user) as $n) {
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
            if ($Key === 'notifications' && !$user->permitted('site_torrents_notify')) {
                continue;
            }

            $extraClass = [];
            if ($Key === 'inbox') {
                $Target = 'inbox.php';
                if ($user->inbox()->unreadTotal()) {
                    $extraClass[] = 'new-subscriptions';
                }
            } elseif ($Key === 'subscriptions') {
                if (
                    isset($alertList['Subscription'])
                    && new User\Subscription($user)->unread()
                ) {
                    $extraClass[] = 'new-subscriptions';
                }
                if (self::add_active($PageID, ['userhistory', 'subscriptions'])) {
                    $extraClass[] = 'active';
                }
            } elseif ($Key === 'staffinbox') {
                if (
                    $activity->showStaffInbox()
                    && $staffPmManager->countByStatus($user, ['Unanswered'])
                ) {
                    $extraClass[] = 'new-subscriptions';
                }
                if (self::add_active($PageID, $testList)) {
                    $extraClass[] = 'active';
                }
            } elseif (
                $TestUser
                && $user->id != ($_REQUEST['userid'] ?? 0)
                && self::add_active($PageID, $testList)
            ) {
                $extraClass[] = 'active';
            }
            $navLinks[] = "<li id=\"nav_{$Key}\""
                . ($extraClass ? ' class="' . implode(' ', $extraClass) . '"' : '')
                . "><a href=\"{$Target}\">{$Title}</a></li>\n";
        }

        return self::$twig->render('index/private-header.twig', [
            'page_title' => $pageTitle,
            'script'     => $js,
            'scss_style' => isset($option['css'])
                ? array_map(fn($s) => "$s/style.css", explode(',', $option['css'])) : [],
            'use_noty'   => $notifier->useNoty(),
            'viewer'     => $user,
        ])
        . self::$twig->render('index/page-header.twig', [
            'action'      => $_REQUEST['action'] ?? null,
            'action_list' => $activity->actionList(),
            'alert_list'  => $activity->alertList(),
            'bonus'       => new User\Bonus($user),
            'document'    => $module,
            'dono_target' => $payMan->monthlyPercent(new Manager\Donation()),
            'gift_event'  => new Manager\GiftEvent()->findOpenEvent(),
            'nav_links'   => $navLinks,
            'viewer'      => $user,
        ]);
    }

    /**
     * Determine if a link should be marked as 'active'
     * @param array<mixed> $Target
     * @param array<mixed> $Tests
     */
    protected static function add_active(array $Target, array $Tests): bool {
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
        if (!Base::staticRequestContext()->hasViewer()) {
            return self::$twig->render('index/public-footer.twig');
        }
        $user = Base::staticRequestContext()->viewer();

        $launch = date('Y');
        if ($launch != SITE_LAUNCH_YEAR) {
            $launch = SITE_LAUNCH_YEAR . "-$launch";
        }

        $alertList = new User\Notification($user)
            ->setDocument(
                $user->requestContext()->module(),
                $_REQUEST['action'] ?? ''
            )
            ->alertList();
        $notification = [];
        foreach ($alertList as $alert) {
            if (in_array($alert->display(), [User\Notification::DISPLAY_POPUP, User\Notification::DISPLAY_POPUP_PUSH])) {
                $notification[] = $alert;
            }
        }

        global $Debug, $SessionID;
        $Debug->mark('render footer');
        return self::$twig->render('index/private-footer.twig', [
            'cache'        => self::$cache,
            'db'           => self::$db,
            'debug'        => $Debug,
            'disclaimer'   => $showDisclaimer,
            'last_active'  => new User\Session($user)->lastActive($SessionID),
            'launch'       => $launch,
            'load'         => sys_getloadavg(),
            'notification' => $notification,
            'memory'       => memory_get_usage(true),
            'pg'           => self::pgStatic()->stats(),
            'textarea_js'  => Util\Textarea::activate(),
            'time_ms'      => $Debug->duration() * 1000,
            'tracker'      => new Tracker(),
            'viewer'       => $user,
            'sphinxql'     => class_exists('Sphinxql') && !empty(\Sphinxql::$Queries)
                ? ['list'  => \Sphinxql::$Queries, 'time' => \Sphinxql::$Time]
                : [],
        ]);
    }
}
