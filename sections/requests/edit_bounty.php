<?php

if (!check_perms('site_admin_requests')) {
    error(403);
}

$requestId = (int)$_GET['id'];
if ($requestId < 1) {
    error(404);
}
$request = new \Gazelle\Request($requestId);
$bounty  = $request->bounty();
if (!$bounty) {
    error(404);
}

foreach ($bounty as &$b) {
    $b['bounty_size'] = Format::get_size($b['Bounty']);
    unset($b); // because looping by reference
}

View::show_header('Edit request bounty');
echo $Twig->render('request/edit-bounty.twig', [
    'auth'   => $Viewer->auth(),
    'bounty' => $bounty,
    'id'     => $requestId,
    'title'  => $request->title(),
]);
View::show_footer();
