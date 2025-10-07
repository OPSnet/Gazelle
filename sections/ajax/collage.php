<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

$collage = new Manager\Collage()->findById((int)($_GET['id'] ?? 0));
if (is_null($collage)) {
    json_die('bad parameters');
}

echo new Json\Collage(
    $collage,
    (int)($_GET['page'] ?? 1),
    $Viewer,
)
    ->response();
