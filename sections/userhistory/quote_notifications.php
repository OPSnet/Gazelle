<?php

if ($Viewer->disableForums()) {
    error(403);
}
$userQuote = new Gazelle\User\Quote($Viewer);

if ($_GET['catchup'] ?? 0) {
    $userQuote->clear();
    header('Location: userhistory.php?action=quote_notifications');
    die();
}

$userQuote->setShowAll(($_GET['showall'] ?? 0) == 1);
$total = $userQuote->total();

$paginator = new Gazelle\Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal($total);

View::show_header('Quote Notifications');
echo $Twig->render('user/quote-notification.twig', [
    'page'      => $userQuote->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'show_all'  => $userQuote->showAll(),
    'total'     => $total,
    'user'      => $Viewer,
]);
View::show_footer();
