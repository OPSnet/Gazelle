<?php

if (!$Viewer->permitted('admin_manage_applicants')) {
    error(403);
}

$appRoleMan = new Gazelle\Manager\ApplicantRole;
$editId     = 0;
$saved      = '';

if (!isset($_POST['auth'])) {
    $appRole = null;
} else {
    authorize();

    $edit  = array_filter($_POST, fn ($x) => preg_match('/^edit-\d+$/', $x), ARRAY_FILTER_USE_KEY);

    if (count($edit) == 1) {
        $editId = (int)trim(array_keys($edit)[0], 'edit-');
        $appRole = $appRoleMan->findById($editId);
        if (is_null($appRole)) {
            error(0);
        }
    } elseif (isset($_POST['edit'])) {
        $editId = (int)$_POST['edit'];
        $appRole = $appRoleMan->findById($editId);
        if (is_null($appRole)) {
            error(0);
        }
        if (isset($_POST['user_id'])) {
            $userId = (int)$_POST['user_id'];
            if ($userId == $Viewer->id()) {
                $appRole->modify(
                    $_POST['title'],
                    $_POST['description'],
                    (isset($_POST['status']) && is_numeric($_POST['status']) && $_POST['status'] == 1)
                );
            }
            $editId = 0; /* return to list */
            $saved = 'updated';
        }
    } else {
        $appRole = $appRoleMan->create(
            $_POST['title'],
            $_POST['description'],
            (isset($_POST['status']) && is_numeric($_POST['status']) && $_POST['status'] == 1),
            $Viewer->id()
        );
        $saved = 'saved';
    }
}

echo $Twig->render('applicant/admin.twig', [
    'auth'     => $Viewer->auth(),
    'edit_id'  => $editId,
    'list'     => $appRoleMan->list(true),
    'role'     => $appRole,
    'saved'    => $saved,
    'text'     => new Gazelle\Util\Textarea('description', $appRole?->description() ?? ''),
    'user_id'  => $Viewer->id(),
]);
