<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_view_ips')) {
    Error403::error();
}

$column    = (int)($_POST['column'] ?? 3);
$direction = (int)($_POST['direction'] ?? 1);
$found     = 0;
$limit     = 0;
$offset    = 0;
$search    = null;
$paginator = new Util\Paginator(10, (int)($_GET['page'] ?? 1));

$text = match (true) {
    isset($_POST['text'])  => trim($_POST['text']),
    isset($_GET['iplist']) => implode("\n", array_map(fn ($ip) => long2ip((int)base_convert($ip, 36, 10)), explode('.', $_GET['iplist']))),
    isset($_GET['ip'])     => $_GET['ip'],
    default                => '',
};
if ($text) {
    $search = new Search\IPv4(new Search\ASN())
        ->create()
        ->setColumn($column)
        ->setDirection($direction);

    $found = $search->add($text);
    if ($found) {
        $paginator->setParam('iplist', $search->ipList())
            ->removeParam('iplist')
            ->setTotal(max($search->siteTotal(), $search->snatchTotal(), $search->trackerTotal()));
        $limit  = $paginator->limit();
        $offset = $paginator->offset();
    }
}

echo $Twig->render('admin/ip-search.twig', [
    'column'    => $column,
    'direction' => $direction,
    'found'     => $found,
    'ip_list'   => $search?->ipList(),
    'site'      => $search?->siteList($limit, $offset),
    'snatch'    => $search?->snatchList($limit, $offset),
    'tracker'   => $search?->trackerList($limit, $offset),
    'paginator' => $paginator,
    'text'      => new Util\Textarea('text', $text, 90, 10)
]);
