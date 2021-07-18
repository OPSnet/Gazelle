<?php
if (!check_perms('torrents_edit')) {
    error(403);
}

$oldGroupId = (int)$_POST['groupid'];
$newGroupId = (int)$_POST['targetgroupid'];
if (!$oldGroupId || !$newGroupId) {
    error(404);
}
if ($newGroupId == $oldGroupId) {
    error('Old group ID is the same as new group ID!');
}
[$CategoryID, $newName] = $DB->row("
    SELECT CategoryID, Name
    FROM torrents_group
    WHERE ID = ?
    ", $newGroupId
);
if (!$CategoryID) {
    error('Target group does not exist.');
}
if (CATEGORY[$CategoryID - 1] != 'Music') {
    error('Only music groups can be merged.');
}

$oldName = $DB->scalar("
    SELECT Name
    FROM torrents_group
    WHERE ID = ?
    ", $oldGroupId
);

// Everything is legit, let's just confim they're not retarded
if (empty($_POST['confirm'])) {
    $Artists = Artists::get_artists([$oldGroupId, $newGroupId]);

    View::show_header('Merge ' . display_str($oldName));
?>
    <div class="center thin">
    <div class="header">
        <h2>Merge Confirm!</h2>
    </div>
    <div class="box pad">
        <form class="confirm_form" name="torrent_group" action="torrents.php" method="post">
            <input type="hidden" name="action" value="merge" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="confirm" value="true" />
            <input type="hidden" name="groupid" value="<?=$oldGroupId?>" />
            <input type="hidden" name="targetgroupid" value="<?=$newGroupId?>" />
            <h3>You are attempting to merge the group:</h3>
            <ul>
                <li><?= Artists::display_artists($Artists[$oldGroupId], true, false)?> - <a href="torrents.php?id=<?=$oldGroupId?>"><?=$oldName?></a></li>
            </ul>
            <h3>Into the group:</h3>
            <ul>
                <li><?= Artists::display_artists($Artists[$newGroupId], true, false)?> - <a href="torrents.php?id=<?=$newGroupId?>"><?=$newName?></a></li>
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
        SELECT concat('voted_albums_', UserID) as cachekey
        FROM users_votes
        WHERE GroupID = ?
        ", $oldGroupId
    );
    $Cache->deleteMulti($DB->collect('cacheKey'));

    // 2. Update the existing votes where possible, clear out the duplicates left by key
    // conflicts, and update the torrents_votes table
    $DB->prepared_query("
        UPDATE IGNORE users_votes SET
            GroupID = ?
        WHERE GroupID = ?
        ", $newGroupId, $oldGroupId
    );
    $DB->prepared_query("
        DELETE FROM users_votes
        WHERE GroupID = ?
        ", $oldGroupId
    );
    $DB->prepared_query("
        INSERT INTO torrents_votes (GroupId, Ups, Total, Score)
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
        ", $newGroupId, $oldGroupId
    );
    if ($DB->affected_rows()) {
        // recompute score
        $DB->prepared_query("
            UPDATE torrents_votes SET
                Score = IFNULL(binomial_ci(Ups, Total), 0)
            WHERE GroupID = ?
            ", $newGroupId
        );
    }

    // 3. Clear the votes_pairs keys!
    $DB->prepared_query("
        SELECT concat('vote_pairs_', v2.GroupId) as cachekey
        FROM users_votes AS v1
        INNER JOIN users_votes AS v2 USING (UserID)
        WHERE (v1.Type = 'Up' OR v2.Type = 'Up')
            AND (v1.GroupId     IN (?, ?))
            AND (v2.GroupId NOT IN (?, ?))
        ", $oldGroupId, $newGroupId, $oldGroupId, $newGroupId
    );
    $Cache->deleteMulti($DB->collect('cacheKey'));

    // GroupIDs
    $DB->prepared_query("SELECT ID FROM torrents WHERE GroupID = ?", $oldGroupId);
    $cacheKeys = [];
    while ([$TorrentID] = $DB->next_row()) {
        $cacheKeys[] = 'torrent_download_' . $TorrentID;
        $cacheKeys[] = 'tid_to_group_' . $TorrentID;
    }
    $Cache->deleteMulti($cacheKeys);
    unset($cacheKeys);

    $DB->prepared_query("
        UPDATE torrents SET
            GroupID = ?
        WHERE GroupID = ?
        ", $newGroupId, $oldGroupId
    );
    $DB->prepared_query("
        UPDATE wiki_torrents SET
            PageID = ?
        WHERE PageID = ?
        ", $newGroupId, $oldGroupId
    );

    // Comments
    (new \Gazelle\Manager\Comment)->merge('torrents', $oldGroupId, $newGroupId);

    // Collages
    $DB->prepared_query("
        SELECT CollageID
        FROM collages_torrents
        WHERE GroupID = ?
        ", $oldGroupId
    );
    while ([$collageId] = $DB->next_record()) {
        $DB->prepared_query("
            UPDATE IGNORE collages_torrents SET
                GroupID = ?
            WHERE GroupID = ?
                AND CollageID = ?
            ", $newGroupId, $oldGroupId, $collageId
        );
        $DB->prepared_query("
            DELETE FROM collages_torrents
            WHERE GroupID = ?
                AND CollageID = ?
                ", $oldGroupId, $collageId
        );
        $Cache->delete_value(sprintf(\Gazelle\Collage::CACHE_KEY, $collageId));
    }

    // Requests
    $DB->prepared_query("
        SELECT concat('request_', ID) as cachekey
        FROM requests
        WHERE GroupID = ?
        ", $oldGroupId
    );
    $Cache->deleteMulti($DB->collect('cacheKey'));
    $DB->prepared_query("
        UPDATE requests SET
            GroupID = ?
        WHERE GroupID = ?
        ", $newGroupId, $oldGroupId
    );

    Torrents::delete_group($oldGroupId);
    (new \Gazelle\Manager\TGroup)->refresh($newGroupId);

    $Cache->deleteMulti([
        "requests_group_$newGroupId",
        "torrent_collages_$newGroupId",
        "torrent_collages_personal_$newGroupId",
        "torrents_details_$oldGroupId",
        "votes_$newGroupId"
    ]);

    $DB->prepared_query("
        UPDATE group_log SET
            GroupID = ?
        WHERE GroupID = ?
        ", $newGroupId, $oldGroupId
    );
    (new Gazelle\Log)->group($newGroupId, $Viewer->id(), "Merged Group $oldGroupId ($oldName) to $newGroupId ($newName)");
    header("Location: torrents.php?id=" . $newGroupId);
}
