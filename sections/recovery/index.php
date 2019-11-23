<?php

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'save':
            if (defined('RECOVERY') && RECOVERY) {
                require_once(SERVER_ROOT . '/sections/recovery/save.php');
            }
            else {
                require_once(SERVER_ROOT . '/sections/recovery/recover.php');
            }
            break;
        case 'admin':
            require_once(SERVER_ROOT . '/sections/recovery/admin.php');
            break;
        case 'browse':
            require_once(SERVER_ROOT . '/sections/recovery/browse.php');
            break;
        case 'pair':
            require_once(SERVER_ROOT . '/sections/recovery/pair.php');
            break;
        case 'search':
        case 'view':
            require_once(SERVER_ROOT . '/sections/recovery/view.php');
            break;
        default:
            require_once(SERVER_ROOT . '/sections/recovery/recover.php');
            break;
    }
}
else {
    require_once(SERVER_ROOT . '/sections/recovery/recover.php');
}
