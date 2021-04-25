<?php

$user = new Gazelle\User($LoggedUser['ID']);
if ($user->disableForums()) {
    error(403);
}
$userQuote = new Gazelle\User\Quote($user);

if ($_GET['catchup'] ?? 0) {
    $userQuote->clear();
    header('Location: userhistory.php?action=quote_notifications');
    die();
}

$userQuote->setShowAll(($_GET['showall'] ?? 0) == 1);
$total = $userQuote->total();

$paginator = new Gazelle\Util\Paginator($user->option('PostsPerPage') ?: USERS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($total);

View::show_header('Quote Notifications');
echo $Twig->render('user/quote-notification.twig', [
    'page'      => $userQuote->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'show_all'  => $userQuote->showAll(),
    'total'     => $total,
    'user'      => $user,
]);
View::show_footer();
