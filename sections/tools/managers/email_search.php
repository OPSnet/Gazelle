<?php

if (!$Viewer->permitted('users_view_email')) {
    error(403);
}

$found     = 0;
$list      = [];
$column    = (int)($_POST['column'] ?? 0);
$direction = (int)($_POST['direction'] ?? 0);
$limit     = 0;
$offset    = 0;
$search    = null;
$paginator = new Gazelle\Util\Paginator(50, (int)($_GET['page'] ?? 1));

$text = match(true) {
    isset($_POST['text'])     => trim($_POST['text']),
    isset($_GET['emaillist']) => implode("\n", array_map(fn ($ip) => long2ip((int)base_convert($ip, 36, 10)), explode(',', $_GET['emaillist']))),
    isset($_GET['ip'])        => $_GET['ip'],
    default                   => '',
};
if ($text) {
    $search = (new Gazelle\Search\Email(new Gazelle\Search\ASN))
        ->create('email_search_' . getmypid())
        ->setColumn($column)
        ->setDirection($direction);

    $list  = $search->extract($text);
    $found = $search->add($list);
    if ($found) {
        $paginator->setParam('emaillist=' . implode(',', $list))
            ->setTotal(max($search->liveTotal(), $search->historyTotal()));
        $limit  = $paginator->limit();
        $offset = $paginator->offset();
    }
}

echo $Twig->render('admin/email-search.twig', [
    'auth'         => $Viewer->auth(),
    'column'       => $column,
    'direction'    => $direction,
    'found'        => $found,
    'email_list'   => $list,
    'live_page'    => $search?->liveList($limit, $offset),
    'history_page' => $search?->historyList($limit, $offset),
    'paginator'    => $paginator,
    'text'         => new Gazelle\Util\Textarea('text', $text, 90, 10),
]);
