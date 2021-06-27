<?php

use Gazelle\Util\Irc;

function notify ($Channel, $Message) {
    global $Viewer;
    Irc::sendRaw("PRIVMSG "
        . $Channel . " :" . $Message . " error by "
        . ($Viewer
                ? SITE_URL . "/user.php?id=" . $Viewer->id() . " (" . $Viewer->username() . ")"
                : $_SERVER['REMOTE_ADDR']
          )
        . " (" . Tools::geoip($_SERVER['REMOTE_ADDR']) . ")"
        . " accessing " . SITE_URL . $_SERVER['REQUEST_URI'] . ' (' . $_SERVER['REQUEST_METHOD'] . ')'
        . (!empty($_SERVER['HTTP_REFERER']) ? " from " . $_SERVER['HTTP_REFERER'] : '')
    );
}

$Errors = ['403','404','413','504'];

if (!empty($_GET['e']) && in_array($_GET['e'],$Errors)) {
    // Web server error i.e. http://sitename/madeupdocument.php
    include($_GET['e'].'.php');
} else {
    // Gazelle error (Come from the error() function)
    switch ($Error) {

        case '403':
            $Title = "Error 403";
            $Description = "You tried to go to a page that you don't have enough permission to view.";
            notify(STATUS_CHAN, 403);
            break;
        case '404':
            $Title = "Error 404";
            $Description = "You tried to go to a page that doesn't exist.";
            break;
        case '429':
            $Title = "Error 429";
            $Description = "You tried to do something too frequently.";
            notify(STATUS_CHAN, 429);
            break;
        case '0':
            $Title = "Invalid Input";
            $Description = "Something was wrong with the input provided with your request, and the server is refusing to fulfill it.";
            notify(STATUS_CHAN, 'PHP-0');
            break;
        case '-1':
            $Title = "Invalid request";
            $Description = "Something was wrong with your request, and the server is refusing to fulfill it.";
            break;
        default:
            if (!empty($Error)) {
                $Title = 'Error';
                $Description = $Error;
            } else {
                $Title = "Unexpected Error";
                $Description = "You have encountered an unexpected error.";
            }
    }

    if (isset($Log) && $Log) {
        $Description .= ' <a href="log.php?search='.$Log.'">Search Log</a>';
    }

    if (empty($NoHTML) && $Error != -1) {
        View::show_header($Title);
?>
    <div class="thin">
        <div class="header">
            <h2><?=$Title?></h2>
        </div>
        <div class="box pad">
            <p><?=$Description?></p>
        </div>
    </div>
<?php
        View::show_footer();
    } else {
        echo $Description;
    }
}
