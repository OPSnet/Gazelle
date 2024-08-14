<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */

if (!$Viewer->permitted('users_auto_reports')) {
    error(403);
}

$userMan = new Gazelle\Manager\User();
$ratMan  = new Gazelle\Manager\ReportAutoType();
$search  = new Gazelle\Search\ReportAuto(new Gazelle\Manager\ReportAuto($ratMan), $ratMan);

$isOld = isset($_GET['view']) && $_GET['view'] === 'old';
if (isset($_GET['id'])) {
    $search->setId((int)$_GET['id']);
} elseif (empty($_GET['view'])) {
    $search->setState(\Gazelle\Enum\ReportAutoState::open);
} elseif ($isOld) {
    $search->setState(\Gazelle\Enum\ReportAutoState::closed);
} else {
    error(404);
}

if (isset($_GET['owner'])) {
    $owner = $userMan->findById((int)$_GET['owner']);
    if (is_null($owner)) {
        error("no such owner");
    }
    $search->setOwner($owner);
}

if (isset($_GET['userid'])) {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        error("no such user");
    }
    $search->setUser($user);
}

$type = null;
if (isset($_GET['type'])) {
    $type = $ratMan->findById((int)$_GET['type']);
    if (is_null($type)) {
        error("no such report type");
    }
    $search->setType($type);
}

$paginator = new Gazelle\Util\Paginator(REPORTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

$baseUri = strpos($_SERVER['REQUEST_URI'], '?') ? $_SERVER['REQUEST_URI'] : $_SERVER['REQUEST_URI'] . '?';
$baseUri = rtrim(preg_replace('/(\?|&)page=[0-9]+(\&|$)/', '$1', $baseUri), '&');

echo $Twig->render('report_auto/index.twig', [
    'auto_reports' => $search->page($paginator->limit(), $paginator->offset()),
    'paginator'    => $paginator,
    'viewer'       => $Viewer,
    'is_old'       => $isOld,
    'type_id'      => $type?->id(),
    'base_uri'     => $baseUri,
    'type_count'   => $search->typeTotalList(),
    'user_count'   => $search->userTotalList($userMan, 20),
]);
