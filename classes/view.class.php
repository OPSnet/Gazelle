<?php
class View {

    protected static $footerSeen = false;

    /**
     * @var string Path relative to where (P)HTML templates reside
     */
    const IncludePath = __DIR__.'/../design/views/';

    /**
     * This function is to include the header file on a page.
     *
     * @param $PageTitle the title of the page
     * @param $JSIncludes is a comma-separated list of JS files to be included on
     *                    the page. ONLY PUT THE RELATIVE LOCATION WITHOUT '.js'
     *                    example: 'somefile,somedir/somefile'
     */
    public static function show_header($PageTitle = '', $JSIncludes = '', $CSSIncludes = '') {
        global $Document;

        if ($PageTitle != '') {
            $PageTitle .= ' :: ';
        }
        $PageTitle .= SITE_NAME;
        $PageID = [
            $Document, // Document
            empty($_REQUEST['action']) ? false : $_REQUEST['action'], // Action
            empty($_REQUEST['type']) ? false : $_REQUEST['type'] // Type
        ];

        global $LoggedUser;
        if (!isset($LoggedUser['ID']) || $PageTitle == 'Recover Password :: ' . SITE_NAME) {
            require_once('../design/publicheader.php');
        } else {
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
        global $Debug, $LoggedUser, $SessionID, $Time, $UserSessions;
        if (!isset($LoggedUser['ID']) || (isset($Options['recover']) && $Options['recover'] === true)) {
            require_once('../design/publicfooter.php');
        } else {
            require_once('../design/privatefooter.php');
        }
    }
}
