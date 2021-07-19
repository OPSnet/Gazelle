<?php
class View {

    protected static $footerSeen = false;

    /**
     * This function is to include the header file on a page.
     *
     * @param $PageTitle the title of the page
     * @param $JSIncludes is a comma-separated list of JS files to be included on
     *                    the page. ONLY PUT THE RELATIVE LOCATION WITHOUT '.js'
     *                    example: 'somefile,somedir/somefile'
     */
    public static function show_header(string $pageTitle, $option = []) {
        global $Document, $Twig, $Viewer;
        if ($pageTitle != '') {
            $pageTitle .= ' :: ';
        }
        $pageTitle .= SITE_NAME;
        $PageID = [$Document, $_REQUEST['action'] ?? false, $_REQUEST['type'] ?? false];

        if (!isset($Viewer) || $pageTitle == 'Recover Password :: ' . SITE_NAME) {
            echo $Twig->render('index/public-header.twig', [
                'page_title' => $pageTitle,
            ]);
        } else {
            $Style = [
                'global.css',
            ];
            if (!empty($option['css'])) {
                $Style = array_merge($Style, explode(',', $option['css']));
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
            if (!isset($LoggedUser['Tooltipster']) || $LoggedUser['Tooltipster']) {
                array_push($Scripts, 'tooltipster', 'tooltipster_settings');
                array_push($Style, 'tooltipster/style.css');
            }
            if ($Viewer->option('UseOpenDyslexic')) {
                array_push($Style, 'opendyslexic/style.css');
            }

            if ($Viewer->permitted('site_torrents_notify')) {
                $notifMan = new Gazelle\Manager\Notification($Viewer->id());
                $Notifications = $notifMan->notifications();
                $NewSubscriptions = isset($Notifications[Gazelle\Manager\Notification::SUBSCRIPTIONS]);
                if ($notifMan->isSkipped(Gazelle\Manager\Notification::SUBSCRIPTIONS)) {
                    $NewSubscriptions = (new Gazelle\Manager\Subscription($LoggedUser['ID']))->unread();
                }
                if ($notifMan->useNoty()) {
                    array_push($Scripts, 'noty/noty', 'noty/layouts/bottomRight', 'noty/themes/default', 'user_notifications');
                }
            }

            echo $Twig->render('index/private-header.twig', [
                'auth_args'    => '&amp;user=' . $Viewer->id() . '&amp;passkey=' . $Viewer->announceKey() . '&amp;authkey=' . $Viewer->auth() . '&amp;auth=' . $Viewer->rssAuth(),
                'page_title'   => $pageTitle,
                'script'       => array_map(function ($s) { return "$s.js"; }, $Scripts),
                'style'        => $Style,
                'viewer'       => $Viewer,
            ]);
            require_once('../design/privateheader.php');
        }
    }

    /**
     * This function is to include the footer file on a page.
     *
     * @param $Options an optional array that you can pass information to the
     *                 header through as well as setup certain limitations
     *                   Here is a list of parameters that work in the $Options array:
     *                 ['disclaimer'] = [boolean] (False) Displays the disclaimer in the footer
     */
    public static function show_footer($Options = []) {
        if (self::$footerSeen) {
            return;
        }
        self::$footerSeen = true;
        global $Viewer;
        if (!isset($Viewer) || ($Options['recover'] ?? false) === true) {
            global $Twig;
            echo $Twig->render('index/public-footer.twig');
        } else {
            require_once('../design/privatefooter.php');
        }
    }
}
