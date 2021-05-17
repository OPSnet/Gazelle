<?php

if (!check_perms('users_view_email')) {
    error(403);
}

$emailBlacklist = new Gazelle\Manager\EmailBlacklist;
if (!empty($_POST['email'])) {
    $emailBlacklist->filterEmail($_POST['email']);
}
if (!empty($_POST['comment'])) {
    $emailBlacklist->filterComment($_POST['comment']);
}

$paginator = new Gazelle\Util\Paginator(LOG_ENTRIES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($emailBlacklist->total());

View::show_header('Manage email blacklist');
echo $Twig->render('admin/email-blacklist.twig', [
    'comment'   => $_POST['comment'] ?? '',
    'email'     => $_POST['email'] ?? '',
    'list'      => $emailBlacklist->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'viewer'    => new Gazelle\User($LoggedUser['ID']),
]);
View::show_footer();
