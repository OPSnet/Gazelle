<?php
if (!check_perms('torrents_edit')) {
    error(403);
}

$OldGroupID = (int)$_POST['groupid'];
$NewGroupID = (int)$_POST['targetgroupid'];
if ($OldGroupID < 1 || $NewGroupID < 1) {
    error(404);
}
if ($NewGroupID == $OldGroupID) {
    error('Old group ID is the same as new group ID!');
}
list($CategoryID, $NewName) = $DB->row("
    SELECT CategoryID, Name
    FROM torrents_group
    WHERE ID = ?
    ", $NewGroupID
);
if (!$CategoryID) {
    error('Target group does not exist.');
}
if ($Categories[$CategoryID - 1] != 'Music') {
    error('Only music groups can be merged.');
}

$Name = $DB->scalar("
    SELECT Name
    FROM torrents_group
    WHERE ID = ?
    ", $OldGroupID
);

// Everything is legit, let's just confim they're not retarded
if (empty($_POST['confirm'])) {
    $Artists = Artists::get_artists([$OldGroupID, $NewGroupID]);

    View::show_header();
?>
    <div class="center thin">
    <div class="header">
        <h2>Merge Confirm!</h2>
    </div>
    <div class="box pad">
        <form class="confirm_form" name="torrent_group" action="torrents.php" method="post">
            <input type="hidden" name="action" value="merge" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <input type="hidden" name="confirm" value="true" />
            <input type="hidden" name="groupid" value="<?=$OldGroupID?>" />
            <input type="hidden" name="targetgroupid" value="<?=$NewGroupID?>" />
            <h3>You are attempting to merge the group:</h3>
            <ul>
                <li><?= Artists::display_artists($Artists[$OldGroupID], true, false)?> - <a href="torrents.php?id=<?=$OldGroupID?>"><?=$Name?></a></li>
            </ul>
            <h3>Into the group:</h3>
            <ul>
                <li><?= Artists::display_artists($Artists[$NewGroupID], true, false)?> - <a href="torrents.php?id=<?=$NewGroupID?>"><?=$NewName?></a></li>
            </ul>
            <input type="submit" value="Confirm" />
        </form>
    </div>
    </div>
<?php
    View::show_footer();
} else {
    authorize();

    // Votes ninjutsu. This is so annoyingly complicated.
    // 1. Get a list of everybody who voted on the old group and clear their cache keys
    $DB->prepared_query("
        SELECT UserID
        FROM users_votes
        WHERE GroupID = ?
        ", $OldGroupID
    );
    while (list($UserID) = $DB->next_record()) {
        $Cache->delete_value("voted_albums_$UserID");
    }
    // 2. Update the existing votes where possible, clear out the duplicates left by key
    // conflicts, and update the torrents_votes table
    $DB->prepared_query("
        UPDATE IGNORE users_votes SET
            GroupID = ?
        WHERE GroupID = ?
        ", $NewGroupID, $OldGroupID
    );
    $DB->prepared_query("
        DELETE FROM users_votes
        WHERE GroupID = ?
        ", $OldGroupID
    );
    $DB->prepared_query("
        INSERT INTO torrents_votes (GroupID, Ups, Total, Score)
            SELECT ?, UpVotes, TotalVotes, 0
            FROM (
                SELECT
                    ifnull(sum(if(Type = 'Up', 1, 0)), 0) As UpVotes,
                    count(*) AS TotalVotes
                FROM users_votes
                WHERE GroupID = ?
                GROUP BY GroupID
            ) AS a
        ON DUPLICATE KEY UPDATE
            Ups = a.UpVotes,
            Total = a.TotalVotes
        ", $NewGroupID, $OldGroupID
    );
    if ($DB->affected_rows()) {
        // recompute score
        $DB->prepared_query("
            UPDATE torrents_votes SET
                Score = IFNULL(binomial_ci(Ups, Total), 0)
            WHERE GroupID = ?
            ", $NewGroupID
        );
    }
    // 3. Clear the votes_pairs keys!
    $DB->prepared_query("
        SELECT v2.GroupID
        FROM users_votes AS v1
        INNER JOIN users_votes AS v2 USING (UserID)
        WHERE (v1.Type = 'Up' OR v2.Type = 'Up')
            AND (v1.GroupID     IN (?, ?))
            AND (v2.GroupID NOT IN (?, ?))
        ", $OldGroupID, $NewGroupID, $OldGroupID, $NewGroupID
    );
    while (list($CacheGroupID) = $DB->next_record()) {
        $Cache->delete_value("vote_pairs_$CacheGroupID");
    }
    // 4. Clear the new groups vote keys
    $Cache->delete_value("votes_$NewGroupID");

    $DB->prepared_query("
        UPDATE torrents SET
            GroupID = ?
        WHERE GroupID = ?
        ", $NewGroupID, $OldGroupID
    );
    $DB->prepared_query("
        UPDATE wiki_torrents SET
            PageID = ?
        WHERE PageID = ?
        ", $NewGroupID, $OldGroupID
    );

    // Comments
    Comments::merge('torrents', $OldGroupID, $NewGroupID);

    // Collages
    $DB->prepared_query("
        SELECT CollageID
        FROM collages_torrents
        WHERE GroupID = ?
        ", $OldGroupID
    );
    while (list($CollageID) = $DB->next_record()) {
        $DB->prepared_query("
            UPDATE IGNORE collages_torrents SET
                GroupID = ?
            WHERE GroupID = ?
                AND CollageID = ?
            ", $NewGroupID, $OldGroupID, $CollageID
        );
        $DB->prepared_query("
            DELETE FROM collages_torrents
            WHERE GroupID = ?
                AND CollageID = ?
                ", $OldGroupID, $CollageID
        );
        $Cache->delete_value("collage_$CollageID");
    }
    $Cache->delete_value("torrent_collages_$NewGroupID");
    $Cache->delete_value("torrent_collages_personal_$NewGroupID");

    // Requests
    $DB->prepared_query("
        SELECT ID
        FROM requests
        WHERE GroupID = ?
        ", $OldGroupID
    );
    $Requests = $DB->collect('ID');
    foreach ($Requests as $RequestID) {
        $Cache->delete_value("request_$RequestID");
    }
    $DB->prepared_query("
        UPDATE requests SET
            GroupID = ?
        WHERE GroupID = ?
        ", $NewGroupID, $OldGroupID
    );
    $Cache->delete_value('requests_group_'.$NewGroupID);

    Torrents::delete_group($OldGroupID);

    Torrents::write_group_log($NewGroupID, 0, $LoggedUser['ID'], "Merged Group $OldGroupID ($Name) to $NewGroupID ($NewName)", 0);
    $DB->prepared_query("
        UPDATE group_log SET
            GroupID = ?
        WHERE GroupID = ?
        ", $NewGroupID, $OldGroupID
    );
    $DB->prepared_query("
        SELECT ID
        FROM torrents
        WHERE GroupID = ?
        ", $OldGroupID
    );
    while (list($TorrentID) = $DB->next_record()) {
        $Cache->delete_value("torrent_download_$TorrentID");
    }
    $Cache->delete_value("torrents_details_$NewGroupID");
    $Cache->delete_value("groups_artists_$NewGroupID");
    Torrents::update_hash($NewGroupID);

    header("Location: torrents.php?id=" . $NewGroupID);
}
