<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

$wikiMan = new Manager\Wiki();

if (empty($_GET['nojump'])) {
    $article = $wikiMan->findByAlias($_GET['search'] ?? '');
    if ($article) {
        header('Location: ' . $article->location());
        exit;
    }
}

$header = new Util\SortableTableHeader('created', [
    'created' => ['dbColumn' => 'ID',    'defaultSort' => 'desc'],
    'title'   => ['dbColumn' => 'Title', 'defaultSort' => 'asc',  'text' => 'Article'],
    'edited'  => ['dbColumn' => 'Date',  'defaultSort' => 'desc', 'text' => 'Last updated'],
]);

$TypeMap = [
    'title' => 'Title',
    'body'  => 'Body',
];
$Type = $TypeMap[$_GET['type'] ?? 'title'];

$search = new Search\Wiki($Viewer, $Type, $_GET['search'] ?? '');
$search->setOrderBy($header->orderBy())->setOrderDir($header->dir());

$paginator = new Util\Paginator(WIKI_ARTICLES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

echo $Twig->render('wiki/search.twig', [
    'header'    => $header,
    'paginator' => $paginator,
    'page'      => $search->page($paginator->limit(), $paginator->offset()),
    'alias'     => Wiki::normalizeAlias($_GET['search'] ?? ''),
    'order'     => $_GET['order'] ?? 'asc',
    'search'    => $_GET['search'],
    'sort'      => $_GET['sort'] ?? 'title',
    'type'      => $Type,
]);
