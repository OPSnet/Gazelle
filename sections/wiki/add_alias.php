<?php

/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

$article = new Manager\Wiki()->findById((int)$_POST['article']);
if (is_null($article)) {
    Error404::error();
}
if (!$article->editable($Viewer)) {
    Error403::error();
}

try {
    $article->addAlias(trim($_POST['alias']), $Viewer);
} catch (DB\MysqlDuplicateKeyException) {
    Error400::error('The alias you attempted to add is already assigned to an article.');
}

header('Location: ' . $article->location());
