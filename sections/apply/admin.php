<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$appRoleMan = new Manager\ApplicantRole();
if ($Viewer->permitted('admin_manage_applicants')) {
    $list = $appRoleMan->list(); // everything, including archived roles
} else {
    /** @var \Gazelle\User $Viewer phpstan is dense */
    if (!array_filter($appRoleMan->publishedList(), fn($r) => $r->isStaffViewer($Viewer))) {
        // a user is being naughty
        Error403::error();
    }
    // Staff who can see specific roles cannot see the admin page
    header('Location: apply.php?action=view');
    return;
}

$error = null;
if (isset($_POST['auth'])) {
    authorize();

    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    if (empty($title) || empty($description)) {
        $error = 'Please fill out the title and description';
    } else {
        $appRoleMan->create($title, $description, (bool)$_POST['status'], $Viewer);
        $error = 'saved';
    }
}

echo $Twig->render('applicant/admin.twig', [
    'error'  => $error,
    'list'   => $list,
    'text'   => new Util\Textarea('description', ''),
    'viewer' => $Viewer,
]);
