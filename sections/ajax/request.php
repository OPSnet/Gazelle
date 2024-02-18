<?php

$request = (new Gazelle\Manager\Request())->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    json_die("failure");
}

echo (new Gazelle\Json\Request(
    $request,
    $Viewer,
    new Gazelle\User\Bookmark($Viewer),
    new Gazelle\Comment\Request($request->id(), (int)($_GET['page'] ?? 1), (int)($_GET['post'] ?? 0)),
    new Gazelle\Manager\User(),
))
    ->setVersion(2)
    ->response();
