<?php
/***************************************************************
* This page handles the backend of the "new group" function
* which splits a torrent off into a new group.
****************************************************************/

authorize();

if (!check_perms('torrents_edit')) {
    error(403);
}

$OldGroupID = (int)$_POST['oldgroupid'];
$TorrentID = (int)$_POST['torrentid'];
$ArtistName = trim($_POST['artist']);
$Title = trim($_POST['title']);
$Year = (int)$_POST['year'];

if (!$OldGroupID || !$TorrentID || !$Year || empty($Title) || empty($ArtistName)) {
    error(0);
}

//Everything is legit, let's just confim they're not retarded
if (empty($_POST['confirm'])) {
    View::show_header();
?>
    <div class="center thin">
    <div class="header">
        <h2>Split Confirm!</h2>
    </div>
    <div class="box pad">
        <form class="confirm_form" name="torrent_group" action="torrents.php" method="post">
            <input type="hidden" name="action" value="newgroup" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="confirm" value="true" />
            <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
            <input type="hidden" name="oldgroupid" value="<?=$OldGroupID?>" />
            <input type="hidden" name="artist" value="<?=display_str($_POST['artist'])?>" />
            <input type="hidden" name="title" value="<?=display_str($_POST['title'])?>" />
            <input type="hidden" name="year" value="<?=$Year?>" />
            <h3>You are attempting to split the torrent <a href="torrents.php?torrentid=<?=$TorrentID?>"><?=$TorrentID?></a> off into a new group:</h3>
            <ul><li><?=display_str($_POST['artist'])?> - <?=display_str($_POST['title'])?> [<?=$Year?>]</li></ul>
            <input type="submit" value="Confirm" />
        </form>
    </div>
    </div>
<?php
    View::show_footer();
} else {
    $DB->prepared_query('
        SELECT ArtistID, AliasID, Redirect, Name
        FROM artists_alias
        WHERE Name = ?
        ', $ArtistName
    );
    $artistMan = new \Gazelle\Manager\Artist;
    if (!$DB->has_results()) {
        [$ArtistID, $AliasID] = $artistMan->create($ArtistName);
        $Redirect = 0;
    } else {
        [$ArtistID, $AliasID, $Redirect, $ArtistName] = $DB->next_record();
        if ($Redirect) {
            $AliasID = $Redirect;
        }
    }

    $DB->prepared_query("
        INSERT INTO torrents_group /* [$Title] [$Year] */
               (Name, Year, CategoryID, WikiBody, WikiImage)
        VALUES (?,    ?,    1,         '',       '')
        ", $Title, $Year
    );
    $GroupID = $DB->inserted_id();

    $artistMan->setGroupId($GroupID)
        ->setUserId($Viewer->id())
        ->addToGroup($ArtistID, $AliasID, 1);

    $DB->prepared_query('
        UPDATE torrents SET
            GroupID = ?
        WHERE ID = ?
        ', $GroupID, $TorrentID
    );

    // Update or remove previous group, depending on whether there is anything left
    $tgroupMan = new \Gazelle\Manager\TGroup;
    $tgroupMan->refresh($GroupID);
    if ($DB->scalar('SELECT 1 FROM torrents WHERE GroupID = ?', $OldGroupID)) {
        $tgroupMan->refresh($OldGroupID);
    } else {
        Torrents::delete_group($OldGroupID);
    }

    $Cache->delete_value("torrent_download_$TorrentID");

    (new Gazelle\Log)->general("Torrent $TorrentID was split out from group $OldGroupID to $GroupId by " . $Viewer->username());

    header("Location: torrents.php?id=$GroupID");
}
