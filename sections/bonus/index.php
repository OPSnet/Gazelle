<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if ($Viewer->disableBonusPoints()) {
    Error403::error('Your bonus points have been deactivated.');
}

switch ($_REQUEST['action'] ?? '') {
    case 'bprates':
        require __DIR__ . '/bprates.php';
        break;
    case 'cacheflush':
        new Manager\Bonus()->flush();
        header("Location: bonus.php");
        exit;
    case 'history':
        require __DIR__ . '/history.php';
        break;
    case 'prepare':
        require __DIR__ . '/prepare.php';
        break;
    case 'purchase':
        require __DIR__ . '/purchase.php';
        break;
    case 'donate':
    default:
        require __DIR__ . '/shop.php';
        break;
}
