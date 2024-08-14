<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('users_view_ips')) {
    error(403);
}

$column    = (int)($_POST['column'] ?? 0);
$direction = (int)($_POST['direction'] ?? 0);
$found     = 0;
$limit     = 0;
$offset    = 0;
$search    = null;
$paginator = new Gazelle\Util\Paginator(50, (int)($_GET['page'] ?? 1));

$text = match (true) {
    isset($_POST['text'])  => trim($_POST['text']),
    isset($_GET['iplist']) => implode("\n", array_map(fn ($ip) => long2ip((int)base_convert($ip, 36, 10)), explode(',', $_GET['iplist']))),
    isset($_GET['ip'])     => $_GET['ip'],
    default                => '',
};
if ($text) {
    $search = (new Gazelle\Search\IPv4(new Gazelle\Search\ASN()))
        ->create('search_' . getmypid())
        ->setColumn($column)
        ->setDirection($direction);

    $found = $search->add($text);
    if ($found) {
        $paginator->setParam('iplist', $search->ipList())
            ->setTotal(max($search->siteTotal(), $search->snatchTotal(), $search->trackerTotal()));
        $limit  = $paginator->limit();
        $offset = $paginator->offset();
    }
}

echo $Twig->render('admin/ip-search.twig', [
    'auth'      => $Viewer->auth(),
    'column'    => $column,
    'direction' => $direction,
    'found'     => $found,
    'ip_list'   => $search?->ipList(),
    'site'      => $search?->siteList($limit, $offset),
    'snatch'    => $search?->snatchList($limit, $offset),
    'tracker'   => $search?->trackerList($limit, $offset),
    'paginator' => $paginator,
    'text'      => new Gazelle\Util\Textarea('text', $text, 90, 10)
]);
