<?php

if (!$Viewer->permitted('site_view_flow')) {
    error(403);
}

$userMan = new Gazelle\Manager\User();
$paginator = new Gazelle\Util\Paginator(100, (int)($_GET['page'] ?? 1));
$paginator->setTotal($userMan->userflowTotal());
$showFlow = $paginator->page() === 1;

$userflow = $showFlow ? $userMan->userflow() : [];
$userflowDetails = $userMan->userflowDetails($paginator->limit(), $paginator->offset());

echo $Twig->render('admin/userflow.twig', [
    'category'  => array_map(fn($x) => "'$x'", array_keys($userflow)),
    'enabled'   => array_map(fn($x) => $userflow[$x]['created'], array_keys($userflow)),
    'disabled'  => array_map(fn($x) => -$userflow[$x]['disabled'], array_keys($userflow)),
    'net'       => array_map(fn($x) => $userflow[$x]['created'] - $userflow[$x]['disabled'], array_keys($userflow)),
    'paginator' => $paginator,
    'show_flow' => $showFlow,
    'details'   => $userflowDetails,
]);
