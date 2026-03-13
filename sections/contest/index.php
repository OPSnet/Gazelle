<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$contestMan = new Manager\Contest();

switch ($_GET['action'] ?? '') {
    case 'leaderboard':
        require __DIR__ . '/leaderboard.php';
        break;
    case 'admin':
    case 'create':
        require __DIR__ . '/admin.php';
        break;
    default:
        echo $Twig->render('contest/intro.twig', [
            'contest' => $contestMan->currentContest(),
            'viewer'  => $Viewer,
        ]);
}
