<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_view_email')) {
    Error403::error();
}


$emailBlacklist = new Manager\EmailBlacklist();
$email = trim($_POST['email'] ?? '');
if (!empty($email)) {
    $emailBlacklist->setFilterEmail($email);
}
$comment = trim($_POST['comment'] ?? '');
if (!empty($comment)) {
    $emailBlacklist->setFilterComment($comment);
}

$paginator = new Util\Paginator(LOG_ENTRIES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($emailBlacklist->total());

echo $Twig->render('admin/email-blacklist.twig', [
    'comment'   => $comment,
    'email'     => $email,
    'list'      => $emailBlacklist->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
