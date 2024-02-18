<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

/** @var \Gazelle\User $Viewer phpstan is dense */
echo $Twig->render('admin/toolbox.twig', [
    'applicant_viewer' => (bool)array_filter(
        (new Gazelle\Manager\ApplicantRole())->publishedList(),
        fn($r) => $r->isStaffViewer($Viewer)
    ),
    'viewer' => $Viewer,
]);
