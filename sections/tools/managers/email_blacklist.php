<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('users_view_email')) {
    error(403);
}

$emailBlacklist = new Gazelle\Manager\EmailBlacklist();
if (!empty($_POST['email'])) {
    $emailBlacklist->setFilterEmail(trim($_POST['email']));
}
if (!empty($_POST['comment'])) {
    $emailBlacklist->setFilterComment(trim($_POST['comment']));
}

$paginator = new Gazelle\Util\Paginator(LOG_ENTRIES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($emailBlacklist->total());

echo $Twig->render('admin/email-blacklist.twig', [
    'comment'   => $_POST['comment'] ?? '',
    'email'     => $_POST['email'] ?? '',
    'list'      => $emailBlacklist->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
