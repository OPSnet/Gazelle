<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

if (!$Viewer->permitted('site_vote')) {
    Error403::error();
}

authorize();

$request = new Manager\Request()->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    $result = ['status' => 'missing', 'get' => $_GET];
} elseif ($request->isFilled()) {
    $result = ['status' => 'filled'];
} elseif (!$request->vote($Viewer, max((int)($_GET['amount'] ?? 0), REQUEST_MIN * 1024 * 1024))) {
    $result = ['status' => 'bankrupt'];
} else {
    $result = [
        'bounty' => $request->bountyTotal(),
        'id'     => $request->id,
        'status' => 'success',
        'total'  => $request->userVotedTotal(),
    ];
}

header('Content-Type: application/json');
echo json_encode($result);
