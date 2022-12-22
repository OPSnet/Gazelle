<?php
/***************************************************************
* Temp handler for changing the category for a single torrent.
****************************************************************/

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

authorize();

$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_POST['torrentid'] ?? 0));
if (is_null($torrent)) {
    error('Torrent does not exist!');
}

$tgMan = new Gazelle\Manager\TGroup;
$old = $tgMan->findById((int)($_POST['oldgroupid'] ?? 0));
if (is_null($old)) {
    error('The source torrent group does not exist!');
}

$Title = trim($_POST['title'] ?? '');
if ($Title === '') {
    error('Title cannot be blank');
}

$NewCategoryID = (int)($_POST['newcategoryid'] ?? 0);
if (!$NewCategoryID) {
    error('Bad category');
} elseif ($NewCategoryID === $old->categoryId()) {
    error("Cannot change category to same category ({$old->categoryName()})");
}

switch (CATEGORY[$NewCategoryID - 1]) {
    case 'Music':
        $ArtistName = trim($_POST['artist']);
        $Year = (int)$_POST['year'];
        $ReleaseType = (int)$_POST['releasetype'];
        if (empty($ArtistName) || !$Year || !$ReleaseType) {
            error(0);
        }

        $DB->prepared_query("
            INSERT INTO torrents_group
                   (Name, Year, ReleaseType, CategoryID, WikiBody, WikiImage)
            VALUES (?,    ?,    ?,           1,          '',       '')
            ", $Title, $Year, $ReleaseType
        );
        $new = $tgMan->findById($DB->inserted_id());
        $new->addArtists($Viewer, [ARTIST_MAIN], [$ArtistName]);
        break;

    case 'Audiobooks':
    case 'Comedy':
        $Year = (int)$_POST['year'];
        if (!$Year) {
            error(0);
        }
        $DB->prepared_query("
            INSERT INTO torrents_group
                   (Name, Year, CategoryID, WikiBody, WikiImage)
            VALUES (?,    ?,    ?,          '',       '')
            ", $Title, $Year, $NewCategoryID
        );
        $new = $tgMan->findById($DB->inserted_id());
        break;

    case 'Applications':
    case 'Comics':
    case 'E-Books':
    case 'E-Learning Videos':
        $DB->prepared_query("
            INSERT INTO torrents_group
                   (Name, CategoryID, WikiBody, WikiImage)
            VALUES (?,    ?,          '',       '')
            ", $Title, $NewCategoryID
        );
        $new = $tgMan->findById($DB->inserted_id());
        break;
}

$DB->prepared_query('
    UPDATE torrents SET
        GroupID = ?
    WHERE ID = ?
    ', $new->id(), $torrent->id()
);

$log = new Gazelle\Log;
$oldId = $old->id();
$oldCategoryId = $old->categoryId();

// Delete old group if needed
if ($DB->scalar('SELECT ID FROM torrents WHERE GroupID = ?', $oldId)) {
    $old->flush();
    $old->refresh();
} else {
    // TODO: votes etc.

    (new Gazelle\Manager\Bookmark)->merge($oldId, $new->id());
    (new \Gazelle\Manager\Comment)->merge('torrents', $oldId, $new->id());
    $log->merge($oldId, $new->id());

    $old->remove($Viewer, $log);
}

$new->flush();
$new->refresh();
$Cache->deleteMulti([
    "torrents_details_" . $oldId,
    "torrent_download_" . $torrent->id(),
]);

$log->group($new->id(), $Viewer->id(), "category changed from $oldCategoryId to " . $new->categoryId() . ", merged from group $oldId")
    ->general("Torrent " . $torrent->id() . " was changed to category " . $new->categoryId() . " by " . $Viewer->label());

header('Location: ' . $new->location());
