<?php

if (!$Viewer->permitted('torrents_edit')) {
    error(403);
}
authorize();

$artist = (new Gazelle\Manager\Artist)->findById((int)$_POST['artistid']);
if (is_null($artist)) {
    error(404);
}
$ArtistID = $artist->id();
$Redirect = (int)$_POST['redirect'];
$aliasName = Gazelle\Artist::sanitize($_POST['name']);
if (is_null($aliasName) || empty($aliasName)) {
    error('You must supply an alias for this artist.');
}

/*
 * In the case of foo, who released an album before changing his name to bar and releasing another
 * the field shared to make them appear on the same artist page is the ArtistID
 * 1. For a normal artist, there'll be one entry, with the ArtistID, the same name as the artist and a 0 redirect
 * 2. For Celine Dion (Céline Dion), there's two, same ArtistID, diff Names, one has a redirect to the alias of the first
 * 3. For foo, there's two, same ArtistID, diff names, no redirect
 */

$CloneAliasID = false;
$DB->prepared_query("
    SELECT AliasID, ArtistID, Name, Redirect
    FROM artists_alias
    WHERE Name = ?
    ", $aliasName
);
if ($DB->has_results()) {
    while ([$CloneAliasID, $CloneArtistID, $CloneAliasName, $CloneRedirect] = $DB->next_record(MYSQLI_NUM, false)) {
        if (!strcasecmp($CloneAliasName, $aliasName)) {
            break;
        }
    }
    if ($CloneAliasID) {
        if (!$CloneRedirect) {
            error('No changes were made as the target alias did not redirect anywhere.');
        }
        if (!($ArtistID == $CloneArtistID && $Redirect == 0)) {
            error('An alias by that name already exists <a href="artist.php?id=' . $CloneArtistID . '">here</a>. You can try renaming that artist to this one.');
        }
        $artist->removeAlias($CloneAliasID);
        (new Gazelle\Log)->general(sprintf("Redirection for the alias %d (%s) for the artist %d was removed by user %d (%s)",
            $CloneAliasID, $aliasName, $ArtistID, $Viewer->id(), $Viewer->username()
        ));
    }
}

if (!$CloneAliasID) {
    if ($Redirect) {
        try {
            $Redirect = $artist->resolveRedirect($Redirect);
        }
        catch (\Exception $e) {
            switch ($e->getMessage()) {
            case 'Artist:not-redirected':
                error('Redirection must target an alias for the current artist.');
            default:
            case 'Artist:not-found':
                error('Cannot redirect to a nonexistent artist alias.');
            }
        }

    }
    $aliasId = $artist->addAlias($Viewer->id(), $aliasName, $Redirect);

    (new Gazelle\Log)->general(sprintf("The alias %d (%s) was added to the artist %d (%s) by user %s",
        $aliasId, $aliasName, $ArtistID, $artist->name(), $Viewer->label()
    ));
}

header("Location:" . redirectUrl("artist.php?action=edit&artistid={$ArtistID}"));
