<?php

// This page is ajax!

if (!$Viewer->permitted('site_vote')) {
    error(403);
}

authorize();

$request = (new Gazelle\Manager\Request())->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    echo "missing";
} elseif ($request->isFilled()) {
    echo "filled";
} elseif (!$request->vote($Viewer, max((int)($_GET['amount'] ?? 0), REQUEST_MIN * 1024 * 1024))) {
    echo "bankrupt";
} else {
    echo "success";
}
