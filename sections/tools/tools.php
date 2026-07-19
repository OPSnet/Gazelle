<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_mod')) {
    Error403::error();
}

/** @var \Gazelle\User $Viewer phpstan is dense */
echo $Twig->render('admin/toolbox.twig', [
    'applicant_viewer' => (bool)array_filter(
        new Manager\ApplicantRole()->publishedList(),
        fn ($r) => $r->isStaffViewer($Viewer)
    ),
    'viewer' => $Viewer,
]);
