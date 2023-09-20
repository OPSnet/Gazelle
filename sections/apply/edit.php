<?php

if (!$Viewer->permitted('admin_manage_applicants')) {
    error(403);
}

$role = (new Gazelle\Manager\ApplicantRole)->findById((int)($_GET['id'] ?? 0));
if (is_null($role)) {
    error(404);
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

$userMan = new Gazelle\Manager\User;

echo $Twig->render('applicant/role.twig', [
    'text'        => new Gazelle\Util\Textarea('description', $role->description()),
    'role'        => $role,
    'error'       => $error ?? null,
    'viewer'      => $Viewer,
    'viewer_list' => array_map(fn($id) => $userMan->findById($id), $role->viewerList()),
]);
