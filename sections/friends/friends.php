<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

$friend    = new User\Friend($Viewer);
$paginator = new Util\Paginator(FRIENDS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($friend->total());

echo $Twig->render('user/friend.twig', [
    'list'      => $friend->page($paginator->limit(), $paginator->offset()),
    'paginator' => $paginator,
    'viewer'    => $Viewer,
]);
