<?php

use Gazelle\Util\Textarea;

if (!check_perms('admin_manage_applicants')) {
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
            if ($userId == $LoggedUser['ID']) {
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
            $LoggedUser['ID']
        );
        $saved = 'saved';
    }
}

View::show_header('Applicant administration');
echo $Twig->render('applicant/admin.twig', [
    'auth'     => $LoggedUser['AuthKey'],
    'edit_id'  => $editId,
    'list'     => (new Gazelle\Manager\ApplicantRole)->list(true),
    'role'     => $appRole ?? null,
    'saved'    => $saved,
    'text'     => new Textarea('description', $editId ? $appRole->description() : ''),
    'user_id'  => $LoggedUser['ID'],
]);
echo Textarea::activate();
View::show_footer();
