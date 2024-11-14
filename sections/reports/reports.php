<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

use Gazelle\Enum\SearchReportOrder;

if (!$Viewer->permittedAny('admin_reports', 'site_moderate_forums')) {
    error(403);
}

require_once 'array.php';

$search    = new Gazelle\Search\Report();
$paginator = new Gazelle\Util\Paginator(REPORTS_PER_PAGE, (int)($_REQUEST['page'] ?? 1));
$typeList  = ['collage', 'comment', 'post', 'request', 'thread', 'user'];

if (!$Viewer->permitted('admin_reports')) {
    $search->restrictForumMod();
}

if (isset($_REQUEST['id'])) {
    $search->setId((int)$_GET['id']);
} elseif (!($_REQUEST['view'] ?? '' == 'old') && !isset($_REQUEST['order'])) {
    $search->setStatus(['New', 'InProgress']);
} else {
    $search->setStatus(['Resolved']);

    // see what report types were set on the form
    $formTypeList = array_values(
        array_map(
            fn(string $type): string => explode('-', $type)[1],
            array_filter(
                array_keys($_REQUEST),
                fn(string $checkbox): bool
                    => (bool)preg_match('/^type-(?:collage|comment|post|request|thread|user)+$/', $checkbox)
            )
        )
    );
    // the form has changed from the defaults
    if ($formTypeList) {
        $typeList = $formTypeList;
    }
    foreach ($typeList as $type) {
        $paginator->setParam("type-$type", "on");
    }
    $search->setTypeFilter($typeList);

    if (isset($_REQUEST['order'])) {
        $paginator->setParam('view', 'old');
        $paginator->setParam("order", $_REQUEST['order']);
        $search->setOrder(match ($_REQUEST['order']) {
            'resolved-asc'  => SearchReportOrder::resolvedAsc,
            'resolved-desc' => SearchReportOrder::resolvedDesc,
            'created-asc'   => SearchReportOrder::createdAsc,
            default         => SearchReportOrder::createdDesc,
        });
    }
}

$paginator->setTotal($search->total());

echo $Twig->render('report/index.twig', [
    'list' => (new Gazelle\Manager\Report(new Gazelle\Manager\User()))->decorate(
        $search->page($paginator->limit(), $paginator->offset()),
        new Gazelle\Manager\Collage(),
        new Gazelle\Manager\Comment(),
        new Gazelle\Manager\ForumThread(),
        new Gazelle\Manager\ForumPost(),
        new Gazelle\Manager\Request(),
    ),
    'paginator' => $paginator,
    'type'      => $Types,
    'type_list' => $typeList,
    'view_old'  => ($_REQUEST['view'] ?? '')  === 'old' || isset($_REQUEST['order']),
    'order'     => $search->order(),
    'viewer'    => $Viewer,
]);
