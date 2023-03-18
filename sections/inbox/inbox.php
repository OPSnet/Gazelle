<?php

$inbox = new Gazelle\User\Inbox($Viewer);
$inbox->setFolder($_GET['section'] ?? $_GET['action'] ?? 'inbox');
if (isset($_GET['searchtype'])) {
    $inbox->setSearchField($_GET['searchtype']);
}
if (isset($_GET['search'])) {
    $inbox->setSearchTerm($_GET['search']);
}
if (isset($_GET['sort'])) {
    $inbox->setUnreadFirst($_GET['sort'] == 'unread');
}
$filter = $_GET['filter'] ?? false;
if ($filter) {
    $inbox->setFilter($filter);
}

$paginator = new Gazelle\Util\Paginator(MESSAGES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($inbox->messageTotal());

echo $Twig->render('inbox/inbox.twig', [
    'filter'      => $filter,
    'inbox'       => $inbox,
    'messageList' => $inbox->messageList(new \Gazelle\Manager\PM($Viewer), $paginator->limit(), $paginator->offset()),
    'paginator'   => $paginator,
    'viewer'      => $Viewer,
]);
