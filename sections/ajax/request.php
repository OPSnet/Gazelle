<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$request = new Manager\Request()->findById((int)($_GET['id'] ?? 0));
if (is_null($request)) {
    json_die("failure");
}

echo new Json\Request(
    $request,
    $Viewer,
    new User\Bookmark($Viewer),
    new Comment\Request($request->id(), (int)($_GET['page'] ?? 1), (int)($_GET['post'] ?? 0)),
)
    ->setVersion(2)
    ->response();
