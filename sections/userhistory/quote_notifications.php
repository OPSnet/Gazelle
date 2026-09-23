<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if ($Viewer->disableForums()) {
    Error403::error();
}
$userQuote = new User\Quote($Viewer);

if ($_GET['catchup'] ?? 0) {
    $userQuote->clearAll();
    header('Location: userhistory.php?action=quote_notifications');
    return;
}

$userQuote->setShowAll(($_GET['showall'] ?? 0) == 1);

$paginator = new Util\Paginator($Viewer->postsPerPage(), (int)($_GET['page'] ?? 1));
$paginator->setTotal($userQuote->total());

echo $Twig->render('user/quote-notification.twig', [
    'page'      => $userQuote->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'show_all'  => $userQuote->showAll(),
    'user'      => $Viewer,
]);
