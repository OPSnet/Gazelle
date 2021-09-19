<?php

if (!$Viewer->permitted('admin_view_payments')) {
    error(403);
}

echo $Twig->render('admin/payment.twig', [
    'donorMan' => new Gazelle\Manager\Donation,
    'list'     => (new Gazelle\Manager\Payment)->list(),
    'viewer'   => $Viewer,
]);
