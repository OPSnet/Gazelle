<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$alias = $_GET['alias'] ?? '';
$article = new Manager\Wiki()->findByAlias($alias);
if (is_null($article)) {
    Error404::error();
}

if (!$article->editable($Viewer)) {
    Error403::error();
}

$article->removeAlias($alias);
