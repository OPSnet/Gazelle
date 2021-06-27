<?php

if (!$Viewer->permitted('admin_manage_invite_source')) {
    error(403);
}
$user = (new Gazelle\Manager\User)->find(trim($_POST['user'] ?? ''));
if ($user) {
    header("Location: user.php?id=" . $user->id() . "#invite_source");
    exit;
}

View::show_header('Invite Sources Summary');
echo $Twig->render('admin/invite-source.twig', [
    'auth' => $Viewer->auth(),
    'list' => (new Gazelle\Manager\InviteSource)->summaryByInviter(),
]);
View::show_footer();
