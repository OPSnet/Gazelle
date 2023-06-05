<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}
authorize();

$redirectId = (int)$_POST['redirect'];
$aliasName = Gazelle\Artist::sanitize($_POST['name']);
if (is_null($aliasName) || empty($aliasName)) {
    error('You must supply an alias for this artist.');
}

$artMan = new Gazelle\Manager\Artist;
$artist = $artMan->findById((int)$_POST['artistid']);
if (is_null($artist)) {
    error(404);
}
$artistId = $artist->id();

/*
 * In the case of foo, who released an album before changing his name to bar and releasing another
 * the field shared to make them appear on the same artist page is the ArtistID
 * 1. For a normal artist, there'll be one entry, with the ArtistID, the same name as the artist and a 0 redirect
 * 2. For Celine Dion (Céline Dion), there's two, same ArtistID, diff Names, one has a redirect to the alias of the first
 * 3. For foo, there's two, same ArtistID, diff names, no redirect
 */

$db = Gazelle\DB::DB();
$CloneAliasID = false;
$db->prepared_query("
    SELECT AliasID, ArtistID, Name, Redirect
    FROM artists_alias
    WHERE Name = ?
    ", $aliasName
);
if ($db->has_results()) {
    while ([$CloneAliasID, $CloneArtistID, $CloneAliasName, $CloneRedirect] = $db->next_record(MYSQLI_NUM, false)) {
        if (strcasecmp($CloneAliasName, $aliasName) == 0) {
            $CloneAliasID = (int)$CloneAliasID;
            $CloneArtistID = (int)$CloneArtistID;
            break;
        }
    }
    if ($CloneAliasID) {
        if (!$CloneRedirect) {
            error('No changes were made as the target alias did not redirect anywhere.');
        }
        if ($artistId != $CloneArtistID || $redirectId) {
            echo $Twig->render('artist/error-alias.twig', [
                'alias'  => $aliasName,
                'artist' => $artMan->findById($CloneArtistID),
            ]);
            exit;
        }
        $artist->clearAliasFromArtist($CloneAliasID, $Viewer, new Gazelle\Log);
    }
}

if (!$CloneAliasID) {
    if ($redirectId) {
        $redirectArtist = $artMan->findByRedirectId($redirectId);
        if (is_null($redirectArtist)) {
            error('Cannot redirect to a nonexistent artist alias.');
        } elseif ($artist->id() != $redirectArtist->id()) {
            error('Redirection must target an alias for the current artist.');
        }
    }
    $artist->addAlias($aliasName, $redirectId, $Viewer, new Gazelle\Log);
}

header("Location:" . redirectUrl("artist.php?action=edit&artistid={$artistId}"));
