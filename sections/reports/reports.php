<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permittedAny('admin_reports', 'site_moderate_forums')) {
    Error403::error();
}

require __DIR__ . '/array.php';

$search    = new Search\Report();
$paginator = new Util\Paginator(REPORTS_PER_PAGE, (int)($_REQUEST['page'] ?? 1));
$typeList  = ['collage', 'comment', 'post', 'request', 'thread', 'user'];

if (!$Viewer->permitted('admin_reports')) {
    $search->restrictForumMod();
}

if (isset($_REQUEST['id'])) {
    $search->setId((int)$_GET['id']);
} elseif (!($_REQUEST['view'] ?? '') == 'old' && !isset($_REQUEST['order'])) {
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
            'resolved-asc'  => Enum\SearchReportOrder::resolvedAsc,
            'resolved-desc' => Enum\SearchReportOrder::resolvedDesc,
            'created-asc'   => Enum\SearchReportOrder::createdAsc,
            default         => Enum\SearchReportOrder::createdDesc,
        });
    }
}

$paginator->setTotal($search->total());

echo $Twig->render('report/index.twig', [
    'list' => new Manager\Report()->decorate(
        $search->page($paginator->limit(), $paginator->offset()),
    ),
    'paginator' => $paginator,
    'type'      => $Types,
    'type_list' => $typeList,
    'view_old'  => ($_REQUEST['view'] ?? '')  === 'old' || isset($_REQUEST['order']),
    'order'     => $search->order(),
    'viewer'    => $Viewer,
]);
