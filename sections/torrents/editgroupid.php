<?php
/***************************************************************
* This page handles the backend of the "edit group ID" function
* (found on edit.php). It simply changes the group ID of a
* torrent.
****************************************************************/

if (!check_perms('torrents_edit')) {
    error(403);
}

$OldGroupID = (int)$_POST['oldgroupid'];
$GroupID = (int)$_POST['groupid'];
$TorrentID = (int)$_POST['torrentid'];

if (!$OldGroupID || !$GroupID || !$TorrentID) {
    error(404);
}

if ($OldGroupID == $GroupID) {
    header("Location: " . $_SERVER['HTTP_REFERER'] ?? "torrents.php?action=edit&id={$OldGroupID}");
    exit;
}

//Everything is legit, let's just confim they're not retarded
if (empty($_POST['confirm'])) {
    $Name = $DB->scalar("
        SELECT Name
        FROM torrents_group
        WHERE ID = ?
        ", $GroupID
    );
    if (is_null($Name)) {
        //Trying to move to an empty group? I think not!
        error('The destination torrent group does not exist!');
    }
    [$CategoryID, $NewName] = $DB->row("
        SELECT CategoryID, Name
        FROM torrents_group
        WHERE ID = ?
        ", $GroupID
    );
    if ($Categories[$CategoryID - 1] != 'Music') {
        error('Destination torrent group must be in the "Music" category.');
    }

    $Artists = Artists::get_artists([$OldGroupID, $GroupID]);

    View::show_header();
?>
    <div class="thin">
        <div class="header">
            <h2>Torrent Group ID Change Confirmation</h2>
        </div>
        <div class="box pad">
            <form class="confirm_form" name="torrent_group" action="torrents.php" method="post">
                <input type="hidden" name="action" value="editgroupid" />
                <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                <input type="hidden" name="confirm" value="true" />
                <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
                <input type="hidden" name="oldgroupid" value="<?=$OldGroupID?>" />
                <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                <h3>You are attempting to move the torrent with ID <?=$TorrentID?> from the group:</h3>
                <ul>
                    <li><?= Artists::display_artists($Artists[$OldGroupID], true, false)?> - <a href="torrents.php?id=<?=$OldGroupID?>"><?=$Name?></a></li>
                </ul>
                <h3>Into the group:</h3>
                <ul>
                    <li><?= Artists::display_artists($Artists[$GroupID], true, false)?> - <a href="torrents.php?id=<?=$GroupID?>"><?=$NewName?></a></li>
                </ul>
                <input type="submit" value="Confirm" />
            </form>
        </div>
    </div>
<?php
    View::show_footer();
} else {
    authorize();

    $DB->prepared_query("
        UPDATE torrents SET
            GroupID = ?
        WHERE ID = ?
        ", $GroupID, $TorrentID
    );

    // Delete old torrent group if it's empty now
    $Count = $DB->scalar("
        SELECT count(*)
        FROM torrents
        WHERE GroupID = ?
        ", $OldGroupID
    );
    if (!$Count) {
        // TODO: votes etc!
        $DB->prepared_query("
            UPDATE comments SET
                PageID = ?
            WHERE Page = 'torrents'
                AND PageID = ?
            ", $GroupID, $OldGroupID
        );
        $Cache->delete_value("torrent_comments_{$GroupID}_catalogue_0");
        $Cache->delete_value("torrent_comments_$GroupID");
        Torrents::delete_group($OldGroupID);
    } else {
        Torrents::update_hash($OldGroupID);
    }
    Torrents::update_hash($GroupID);

    (new Gazelle\Log)->group($GroupID, $Viewer->id(), "merged group $OldGroupID")
        ->general("Torrent $TorrentID was edited by " . $Viewer->username());
    $DB->prepared_query("
        UPDATE group_log
        SET GroupID = ?
        WHERE GroupID = ?
        ", $GroupID, $OldGroupID
    );
    $Cache->delete_value("torrents_details_$GroupID");
    $Cache->delete_value("torrent_download_$TorrentID");

    header("Location: torrents.php?id=$GroupID");
}
