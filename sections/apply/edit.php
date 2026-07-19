<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('admin_manage_applicants')) {
    Error403::error();
}

$role = new Manager\ApplicantRole()->findById((int)($_GET['id'] ?? 0));
if (is_null($role)) {
    Error404::error();
}

if (isset($_POST['auth'])) {
    authorize();
    $title       = trim($_POST['title']);
    $description = trim($_POST['description']);
    if (empty($title) || empty($description)) {
        $error = 'Please fill out the title and description';
    } else {
        $role->setField('Title', $title)
            ->setField('Description', $description)
            ->setField('Published', (int)($_POST['status']))
            ->setField('viewer_list', trim($_POST['viewer_list']))
            ->modify();

        header('Location: apply.php?action=admin');
        exit;
    }
}

$userMan = new Manager\User();

echo $Twig->render('applicant/role.twig', [
    'text'        => new Util\Textarea('description', $role->description()),
    'role'        => $role,
    'error'       => $error ?? null,
    'viewer'      => $Viewer,
    'viewer_list' => array_map(fn($id) => $userMan->findById($id), $role->viewerList()),
]);
