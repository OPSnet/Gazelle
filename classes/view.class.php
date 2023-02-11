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

        $js = [
            'jquery',
            'script_start',
            'ajax.class',
            'global',
        ];
        if (DEBUG_MODE || $Viewer?->permitted('site_debug')) {
            array_push($js, 'jquery-migrate', 'debug');
        }
        if (!empty($option['js'])) {
            array_push($js, ...explode(',', $option['js']));
        }

        if (!isset($Viewer) || $pageTitle == 'Recover Password :: ' . SITE_NAME) {
            $js[] = 'storage.class';
            echo $Twig->render('index/public-header.twig', [
                'page_title' => html_entity_decode($pageTitle),
                'script'     => array_map(fn($s) => "$s.js", $js),
            ]);
            return;
        }
        array_push($js, 'autocomplete', 'jquery.autocomplete', 'jquery.countdown.min');
        $Style = [];
        $ScssStyle = [
            'global.css',
        ];
        if (!empty($option['css'])) {
            array_push($ScssStyle, ...array_map(fn($s) => "$s/style.css", explode(',', $option['css'])));
        }
        if ($Viewer->option('Tooltipster') ?? 1) {
            array_push($js, 'tooltipster', 'tooltipster_settings');
            $ScssStyle[] = 'tooltipster/style.css';
        }
        if ($Viewer->option('UseOpenDyslexic')) {
            $ScssStyle[] = 'opendyslexic/style.css';
        }

        // add KaTeX renderer for bbcode [tex] elements
        $Style[] = 'katex/katex-0.16.4.min.css';
        $js[] = 'katex-0.16.4.min';

        $activity = new Gazelle\User\Activity($Viewer);
        $activity->configure()
            ->setStaffPM(new Gazelle\Manager\StaffPM);

        $notifier = new Gazelle\User\Notification($Viewer);
        $alertList = $notifier->setDocument($Document, $_REQUEST['action'] ?? '')->alertList();
        foreach($alertList as $alert) {
            if (in_array($alert->display(), [Gazelle\User\Notification::DISPLAY_TRADITIONAL, Gazelle\User\Notification::DISPLAY_TRADITIONAL_PUSH])) {
                $activity->setAlert(sprintf('<a href="%s">%s</a>', $alert->url(), $alert->title()));
            }
        }
        if ($notifier->useNoty()) {
            array_push($js, 'noty/noty', 'noty/layouts/bottomRight', 'noty/themes/default', 'user_notifications');
        }

        $payMan = new Gazelle\Manager\Payment;
        if ($Viewer->permitted('users_mod')) {
            $activity->setStaff(new Gazelle\Staff($Viewer))
                ->setReport(new Gazelle\Stats\Report)
                ->setPayment($payMan)
                ->setApplicant(new Gazelle\Manager\Applicant)
                ->setDb(new Gazelle\DB)
                ->setScheduler(new Gazelle\Schedule\Scheduler)
                ->setSSLHost(new Gazelle\Manager\SSLHost)
                ;

            if (OPEN_EXTERNAL_REFERRALS) {
                $activity->setReferral(new Gazelle\Manager\Referral);
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
            if (str_contains($Tests, ':')) {
                $Parts = array_map('trim', explode(',', $Tests));
                $Tests = [];

                foreach ($Parts as $Part) {
                    $Tests[] = array_map($parseNavItem, explode(':', $Part));
                }
            } else if (str_contains($Tests, ',')) {
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
                if (self::add_active($PageID, $Tests)) {
                    $extraClass[] = 'active';
                }
            } elseif ($TestUser && $Viewer->id() != ($_REQUEST['userid'] ?? 0) && self::add_active($PageID, $Tests)) {
                $extraClass[] = 'active';
            }
            $navLinks[] = "<li id=\"nav_{$Key}\""
                . ($extraClass ? ' class="' . implode(' ', $extraClass) . '"' : '')
                . "><a href=\"{$Target}\">{$Title}</a></li>\n";
        }

        echo $Twig->render('index/private-header.twig', [
            'auth_args'   => '&amp;user=' . $Viewer->id() . '&amp;passkey=' . $Viewer->announceKey() . '&amp;authkey=' . $Viewer->auth() . '&amp;auth=' . $Viewer->rssAuth(),
            'page_title'  => html_entity_decode($pageTitle),
            'script'      => array_map(fn($s) => "$s.js", $js),
            'style'       => new Gazelle\User\Stylesheet($Viewer),
            'scss_style'  => $ScssStyle,
            'css_style'   => $Style,
            'viewer'      => $Viewer,
        ]);
        echo $Twig->render('index/page-header.twig', [
            'action'      => $_REQUEST['action'] ?? null,
            'action_list' => $activity->actionList(),
            'alert_list'  => $activity->alertList(),
            'bonus'       => new Gazelle\User\Bonus($Viewer),
            'document'    => $Document,
            'dono_target' => $payMan->monthlyPercent(new Gazelle\Manager\Donation),
            'nav_links'   => $navLinks,
            'user'        => $Viewer,
        ]);
    }

    /**
     * Determine if a link should be marked as 'active'
     *
     * @param mixed $Target The variable to compare all values against
     * @param mixed $Tests The condition values. Type and dimension determines test type
     *     Scalar: $Tests must be equal to $Target for a match
     *     Array: All elements in $Tests must correspond to equal values in $Target 2-dimensional array
     *            At least one array must be identical to $Target
     */
    protected static function add_active($Target, $Tests, $UserIDKey = false): bool {
        if (!is_array($Tests)) {
            // Scalars are nice and easy
            return $Tests === $Target;
        } elseif (!is_array($Tests[0])) {
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

        $launch = date('Y');
        if ($launch != SITE_LAUNCH_YEAR) {
            $launch = SITE_LAUNCH_YEAR . "-$launch";
        }

        global $Document;
        $alertList = (new Gazelle\User\Notification($Viewer))->setDocument($Document, $_REQUEST['action'] ?? '')->alertList();
        $notification = [];
        foreach($alertList as $alert) {
            if (in_array($alert->display(), [Gazelle\User\Notification::DISPLAY_POPUP, Gazelle\User\Notification::DISPLAY_POPUP_PUSH])) {
                $notification[] = $alert;
            }
        }

        global $Cache, $DB, $Debug, $SessionID;
        return $Twig->render('index/private-footer.twig', [
            'cache'        => $Cache,
            'db_time'      => $DB->Time,
            'debug'        => $Debug,
            'disclaimer'   => isset($Options['disclaimer']),
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
