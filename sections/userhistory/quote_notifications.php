<?php

if ($Viewer->disableForums()) {
    error(403);
}
$userQuote = new Gazelle\User\Quote($Viewer);

if ($_GET['catchup'] ?? 0) {
    $userQuote->clearAll();
    header('Location: userhistory.php?action=quote_notifications');
    exit;
}

$userQuote->setShowAll(($_GET['showall'] ?? 0) == 1);

$paginator = new Gazelle\Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal($userQuote->total());

echo $Twig->render('user/quote-notification.twig', [
    'page'      => $userQuote->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'show_all'  => $userQuote->showAll(),
    'user'      => $Viewer,
]);
