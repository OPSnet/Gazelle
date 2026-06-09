<?php
/** @phpstan-var \Gazelle\User $Viewer */

declare(strict_types=1);

namespace Gazelle;

authorize();

if ($_POST['type'] === 'torrents') {
    $editor = new Editor\UserBookmark($Viewer->id);
    if (isset($_POST['update']) && !empty($_POST['sort'])) {
        $editor->modify($_POST['sort']);
    } elseif (isset($_POST['delete'])) {
        $remove = array_keys($_POST['remove']);
        if (!empty($remove)) {
            $editor->remove($remove);
        }
    }
}

header('Location: bookmarks.php?type=torrents');
