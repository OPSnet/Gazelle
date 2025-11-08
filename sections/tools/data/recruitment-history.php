<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permittedAny('users_view_ips', 'users_view_email')) {
    Error403::error();
}

$manager = new Stats\Users();
echo $Twig->render('admin/recruitment-history.twig', [
    'list'  => $manager->recruitmentHistory(),
    'range' => $manager->recruitmentHistoryRange(),
]);
