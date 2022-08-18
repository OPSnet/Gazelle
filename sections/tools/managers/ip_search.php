<?php

if (!$Viewer->permitted('users_view_ips')) {
    error(403);
}

$text      = '';
$found     = 0;
$column    = (int)($_POST['column'] ?? 0);
$direction = (int)($_POST['direction'] ?? 0);
$limit     = false;
$offset    = false;
$search    = false;
$paginator = new Gazelle\Util\Paginator(50, (int)($_GET['page'] ?? 1));
$userMan   = new Gazelle\Manager\User;

if (isset($_POST['text']) || isset($_GET['iplist'])) {
    $search = (new Gazelle\Search\IPv4)
        ->create('search_' . getmypid())
        ->setColumn($column)
        ->setDirection($direction);
    $text = isset($_POST['text'])
        ? trim($_POST['text'] ?? '')
        : implode("\n", array_map(fn ($ip) => long2ip((int)base_convert($ip, 36, 10)), explode(',', $_GET['iplist'])));
    $found = $search->add($text);

    if ($found) {
        $paginator->setTotal(max($search->siteTotal(), $search->snatchTotal(), $search->trackerTotal()))
            ->setParam('iplist=' . $search->ipList());
        $limit  = $paginator->limit();
        $offset = $paginator->offset();
    }
}

echo $Twig->render('admin/ip-search.twig', [
    'auth'         => $Viewer->auth(),
    'column'       => $column,
    'direction'    => $direction,
    'found'        => $found,
    'ip_list'      => $search ? $search->ipList() : '',
    'page_site'    => $search ? $search->sitePage($userMan, $limit, $offset) : null,
    'page_snatch'  => $search ? $search->snatchList($userMan, $limit, $offset) : null,
    'page_tracker' => $search ? $search->trackerPage($userMan, $limit, $offset) : null,
    'paginator'    => $paginator,
    'text'         => new Gazelle\Util\Textarea('text', $text, 90, 10)
]);
