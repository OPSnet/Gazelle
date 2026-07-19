<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_view_payments')) {
    Error403::error();
}

echo $Twig->render('admin/payment.twig', [
    'donorMan' => new Manager\Donation(),
    'list'     => new Manager\Payment()->list(),
    'viewer'   => $Viewer,
]);
