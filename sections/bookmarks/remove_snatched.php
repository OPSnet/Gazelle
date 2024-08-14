<?php
/** @phpstan-var \Gazelle\User $Viewer */

authorize();

(new Gazelle\User\Bookmark($Viewer))->removeSnatched();

header('Location: bookmarks.php');
