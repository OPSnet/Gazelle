<?php

use Gazelle\Util\Textarea;

if (!$Viewer->permitted('admin_manage_applicants')) {
    error(403);
}

$editId = 0;
$saved   = '';
if (isset($_POST['auth'])) {
    authorize();
    $edit = array_filter($_POST, function ($x) { return preg_match('/^edit-\d+$/', $x);}, ARRAY_FILTER_USE_KEY);
    if (is_array($edit) && count($edit) == 1) {
        $editId = trim(array_keys($edit)[0], 'edit-');
        $appRole = new Gazelle\ApplicantRole($editId);
    }
    elseif (isset($_POST['edit']) && is_numeric($_POST['edit'])) {
        $editId = intval($_POST['edit']);
        $appRole = new Gazelle\ApplicantRole($editId);
        if (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
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
    }
    else {
        $appRoleMan = new Gazelle\Manager\ApplicantRole;
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
    'list'     => (new Gazelle\Manager\ApplicantRole)->list(true),
    'role'     => $appRole ?? null,
    'saved'    => $saved,
    'text'     => new Textarea('description', $editId ? $appRole->description() : ''),
    'user_id'  => $Viewer->id(),
]);
