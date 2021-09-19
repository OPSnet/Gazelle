<?php

$wikiMan = new Gazelle\Manager\Wiki;

if (empty($_GET['nojump'])) {
    $article = $wikiMan->findByAlias($_GET['search'] ?? '');
    if ($article) {
        header("Location: wiki.php?action=article&id=" . $article->id());
        exit;
    }
}

$header = new \Gazelle\Util\SortableTableHeader('created', [
    'created' => ['dbColumn' => 'ID',    'defaultSort' => 'desc'],
    'title'   => ['dbColumn' => 'Title', 'defaultSort' => 'asc',  'text' => 'Article'],
    'edited'  => ['dbColumn' => 'Date',  'defaultSort' => 'desc', 'text' => 'Last updated'],
]);

$TypeMap = [
    'title' => 'Title',
    'body'  => 'Body',
];
$Type = $TypeMap[$_GET['type'] ?? 'title'];

$search = new Gazelle\Search\Wiki($Viewer, $Type, $_GET['search'] ?? '');
$search->setOrderBy($header->getOrderBy())->setOrderDir($header->getOrderDir());

$paginator = new Gazelle\Util\Paginator(WIKI_ARTICLES_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

echo $Twig->render('wiki/search.twig', [
    'header'    => $header,
    'paginator' => $paginator,
    'page'      => $search->page($paginator->limit(), $paginator->offset()),
    'alias'     => \Gazelle\Wiki::normalizeAlias($_GET['search'] ?? ''),
    'order'     => $_GET['order'] ?? 'asc',
    'search'    => $_GET['search'],
    'sort'      => $_GET['sort'] ?? 'title',
    'type'      => $Type,
]);
