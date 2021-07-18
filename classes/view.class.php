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
    public static function show_header(string $PageTitle, $option = []) {
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

        global $Viewer;
        if (!isset($Viewer) || $PageTitle == 'Recover Password :: ' . SITE_NAME) {
            global $PageTitle, $Twig;
            echo $Twig->render('index/public-header.twig', [
                'page_title' => $PageTitle,
            ]);
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
        global $Viewer;
        if (!isset($Viewer) || ($Options['recover'] ?? false) === true) {
            global $Twig;
            echo $Twig->render('index/public-footer.twig');
        } else {
            require_once('../design/privatefooter.php');
        }
    }
}
