<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if (!$Viewer->permitted('admin_manage_wiki')) {
    Error403::error();
}

$article = new Manager\Wiki()->findById((int)$_GET['id']);
if (is_null($article)) {
    Error404::error();
}
if (!$article->editable($Viewer)) {
    Error403::error();
}
if ($article->id === INDEX_WIKI_PAGE_ID) {
    Error403::error('You cannot delete the main wiki article.');
}

$article->logger()->general(
    "Wiki article {$article->id} \"{$article->title()}\" was deleted by {$Viewer->username()}"
);
$article->remove();

header("location: wiki.php");
