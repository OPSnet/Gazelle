<?php

if (!$Viewer->permitted('site_view_flow')) {
    error(403);
}

$userMan = new Gazelle\Manager\User;
$paginator = new Gazelle\Util\Paginator(100, (int)($_GET['page'] ?? 1));
$paginator->setTotal($userMan->userflowTotal());
$showFlow = $paginator->page() === 1;

$userflow = $showFlow ? $userMan->userflow() : [];
$userflowDetails = $userMan->userflowDetails($paginator->limit(), $paginator->offset());

echo $Twig->render('admin/userflow.twig', [
    'category'  => array_map(fn($x) => "'$x'", array_keys($userflow)),
    'disabled'  => array_map(function ($x) use ($userflow) { return  $userflow[$x]['Joined']; }, array_keys($userflow)),
    'enabled'   => array_map(function ($x) use ($userflow) { return -$userflow[$x]['Disabled']; }, array_keys($userflow)),
    'paginator' => $paginator,
    'show_flow' => $showFlow,
    'details'   => $userflowDetails,
]);
