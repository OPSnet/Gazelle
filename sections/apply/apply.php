<?php

$appMan  = new Gazelle\Manager\Applicant;
$roleMan = new Gazelle\Manager\ApplicantRole;

if (isset($_POST['auth'])) {
    authorize();
    $roleId = (int)($_POST['role'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    if (!$roleId) {
        $error = "You need to choose which role interests you.";
    } elseif (strlen($body) < 80) {
        $error = "You need to explain things a bit more.";
    } else {
        $role = $roleMan->findById($roleId);
        if (is_null($role)) {
            $error = "No such role.";
        } elseif (!$role->isPublished()) {
            $error = "That role is no longer open.";
        } else {
            header("Location: {$role->apply($Viewer, $body)->location()}");
            exit;
        }
    }
}

echo $Twig->render('applicant/apply.twig', [
    'body'         => new Gazelle\Util\Textarea('body', $body ?? ''),
    'error'        => $error ?? null,
    'list'         => $roleMan->publishedList(),
    'role'         => $role ?? null,
    'is_applicant' => $appMan->userIsApplicant($Viewer),
    'viewer'       => $Viewer,
]);
