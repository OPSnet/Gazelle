<?php

authorize();

(new Gazelle\User\Bookmark($Viewer))->removeSnatched();

header('Location: bookmarks.php');
