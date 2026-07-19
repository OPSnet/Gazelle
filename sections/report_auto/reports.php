<?php

/**
 * @phpstan-var \Gazelle\User $Viewer
 * @phpstan-var \Twig\Environment $Twig
 */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('users_auto_reports')) {
    Error403::error();
}

$userMan = new Manager\User();
$ratMan  = new Manager\ReportAutoType();
$search  = new Search\ReportAuto(new Manager\ReportAuto($ratMan), $ratMan);

$isOld = isset($_GET['view']) && $_GET['view'] === 'old';
if (isset($_GET['id'])) {
    $search->setId((int)$_GET['id']);
} elseif (empty($_GET['view'])) {
    $search->setState(Enum\ReportAutoState::open);
} elseif ($isOld) {
    $search->setState(Enum\ReportAutoState::closed);
} else {
    Error404::error();
}

if (isset($_GET['owner'])) {
    $owner = $userMan->findById((int)$_GET['owner']);
    if (is_null($owner)) {
        Error404::error("no such owner");
    }
    $search->setOwner($owner);
}

if (isset($_GET['userid'])) {
    $user = $userMan->findById((int)$_GET['userid']);
    if (is_null($user)) {
        Error404::error("no such user");
    }
    $search->setUser($user);
}

$type = null;
if (isset($_GET['type'])) {
    $type = $ratMan->findById((int)$_GET['type']);
    if (is_null($type)) {
        Error404::error("no such report type");
    }
    $search->setType($type);
}

$paginator = new Util\Paginator(REPORTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$paginator->setTotal($search->total());

$requestUri = (string)$_SERVER['REQUEST_URI'];
$baseUri = strpos($requestUri, '?') ? $requestUri : "$requestUri?";
$baseUri = rtrim(preg_replace('/[?&]page=\d+(?:\&|$)/', '$1', $baseUri), '&');

echo $Twig->render('report_auto/index.twig', [
    'auto_reports' => $search->page($paginator->limit(), $paginator->offset()),
    'paginator'    => $paginator,
    'viewer'       => $Viewer,
    'is_old'       => $isOld,
    'type_id'      => $type?->id,
    'base_uri'     => $baseUri,
    'type_count'   => $search->typeTotalList(),
    'user_count'   => $search->userTotalList(20),
]);
