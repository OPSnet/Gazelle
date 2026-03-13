<?php
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

switch ($_REQUEST['action'] ?? null) {
    case 'users':
        require __DIR__ . '/users.php';
        break;
    case 'torrents':
        require __DIR__ . '/torrents.php';
        break;
    default:
        echo $Twig->render('stats.twig');
        break;
}
