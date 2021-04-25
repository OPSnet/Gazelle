<?php
$appMan = new Gazelle\Manager\Applicant;
if (isset($_POST['auth'])) {
    authorize();
var_dump($_POST);
    $roleId = (int)($_POST['role'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    if (!$roleId) {
        $error = "You need to choose which role interests you.";
    } elseif (strlen($body) < 80) {
        $error = "You need to explain things a bit more.";
    } else {
        header('Location: /apply.php?action=view&id='
            . $appMan->createApplicant($LoggedUser['ID'], $roleId, $body)->id());
        exit;
    }
}

View::show_header('Apply', 'apply');
echo $Twig->render('applicant/apply.twig', [
    'auth'         => $LoggedUser['AuthKey'],
    'body'         => new Gazelle\Util\Textarea('body', $body ?? ''),
    'error'        => $error ?? null,
    'list'         => (new Gazelle\Manager\ApplicantRole)->list(),
    'role'         => $role ?? null,
    'is_admin'     => check_perms('admin_manage_applicants'),
    'is_applicant' => $appMan->userIsApplicant($LoggedUser['ID']),
]);
View::show_footer();
