<?php
/** @phpstan-var \Gazelle\User $Viewer */

if (!$Viewer->permitted('site_vote')) {
    error(403);
}

authorize();

$request = (new Gazelle\Manager\Request())->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    $result = ['status' => 'missing', 'get' => $_GET];
} elseif ($request->isFilled()) {
    $result = ['status' => 'filled'];
} elseif (!$request->vote($Viewer, max((int)($_GET['amount'] ?? 0), REQUEST_MIN * 1024 * 1024))) {
    $result = ['status' => 'bankrupt'];
} else {
    $result = [
        'id'     => $request->id(),
        'status' => 'success',
        'total'  => $request->userVotedTotal(),
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
