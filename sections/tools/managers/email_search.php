<?php

if (!$Viewer->permitted('users_view_email')) {
    error(403);
}

$text      = '';
$found     = 0;
$list      = [];
$column    = (int)($_POST['column'] ?? 0);
$direction = (int)($_POST['direction'] ?? 0);
$limit     = false;
$offset    = false;
$search    = false;
$paginator = new Gazelle\Util\Paginator(50, (int)($_GET['page'] ?? 1));
$userMan   = new Gazelle\Manager\User;

if (isset($_POST['text']) || isset($_GET['emaillist'])) {
    $search = (new Gazelle\Search\Email)
        ->create('email_search_' . getmypid())
        ->setColumn($column)
        ->setDirection($direction);
    $text  = isset($_POST['text']) ? trim($_POST['text']) : implode("\n", explode(',', $_GET['emaillist']));
    $list  = $search->extract($text);
    $found = $search->add($list);

    $paginator->setTotal(max($search->liveTotal(), $search->historyTotal()));
    if ($found) {
        $paginator->setParam('emaillist=' . implode(',', $list));
    }
    $limit  = $paginator->limit();
    $offset = $paginator->offset();
}

echo $Twig->render('admin/email-search.twig', [
    'auth'         => $Viewer->auth(),
    'column'       => $column,
    'direction'    => $direction,
    'found'        => $found,
    'email_list'   => $list,
    'live_page'    => $search ? $search->livePage($userMan, $limit, $offset) : null,
    'history_page' => $search ? $search->historyPage($userMan, $limit, $offset) : null,
    'paginator'    => $paginator,
    'text'         => new Gazelle\Util\Textarea('text', $text, 90, 10),
]);
