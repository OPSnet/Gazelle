<?php
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

use Gazelle\Util\Irc;

function notify($Viewer, $Channel, $Message) {
    $ipaddr = $Viewer->requestContext()->remoteAddr();
    Irc::sendMessage($Channel,
        $Message . " error by "
        . ($Viewer
            ? $Viewer->publicLocation() . " (" . $Viewer->username() . ")"
            : $ipaddr
        )
        . " (" . (new \Gazelle\Util\GeoIP(new \Gazelle\Util\Curl()))->countryISO($ipaddr) . ")"
        . " accessing " . SITE_URL . $_SERVER['REQUEST_URI'] . ' (' . $_SERVER['REQUEST_METHOD'] . ')'
        . (!empty($_SERVER['HTTP_REFERER']) ? " from " . $_SERVER['HTTP_REFERER'] : '')
    );
}

switch ($Error) {
    case '403':
        $Title = "Error 403";
        $Description = "You tried to go to a page that you don't have enough permission to view.";
        notify($Viewer, IRC_CHAN_STATUS, 403);
        break;
    case '404':
        $Title = "Error 404";
        $Description = "You tried to go to a page that doesn't exist.";
        break;
    case '429':
        $Title = "Error 429";
        $Description = "You tried to do something too frequently.";
        break;
    default:
        if (empty($Error)) {
            $Title = "Unexpected Error";
            $Description = "You have encountered an unexpected error.";
        } else {
            $Title = 'Error';
            $Description = $Error;
        }
}

if (isset($Log) && $Log) {
    $Description .= ' <a href="log.php?search=' . $Log . '">Search Log</a>';
}

if (empty($NoHTML) && isset($Error) && $Error != -1) {
    echo $Twig->render('error.twig', [
        'title'       => $Title,
        'description' => $Description,
    ]);
} else {
    echo $Description;
}
