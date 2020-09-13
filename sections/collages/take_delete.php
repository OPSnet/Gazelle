<?php
authorize();

$CollageID = (int)$_POST['collageid'];
if ($CollageID < 1) {
    error(404);
}

[$Name, $CategoryID, $UserID] = $DB->row("
    SELECT Name, CategoryID, UserID
    FROM collages
    WHERE ID = ?
    ", $CollageID
);

if (!check_perms('site_collages_delete') && $UserID !== $LoggedUser['ID']) {
    error(403);
}

$Reason = trim($_POST['reason']);
if (!$Reason) {
    error('You must enter a reason!');
}

$DB->prepared_query("
    SELECT GroupID
    FROM collages_torrents
    WHERE CollageID = ?
    ", $CollageID
);
while ([$GroupID] = $DB->next_record()) {
    $Cache->deleteMulti(["torrents_details_$GroupID", "torrent_collages_$GroupID", "torrent_collages_personal_$GroupID"]);
}

//Personal collages have CategoryID 0
if ($CategoryID == 0) {
    $DB->prepared_query("
        DELETE FROM collages
        WHERE ID = ?
        ", $CollageID
    );
    $DB->prepared_query("
        DELETE FROM collages_torrents
        WHERE CollageID = ?
        ", $CollageID
    );
    Comments::delete_page('collages', $CollageID);
} else {
    $DB->prepared_query("
        UPDATE collages SET
            Deleted = '1'
        WHERE ID = ?
        ", $CollageID
    );

    $subscription = new \Gazelle\Manager\Subscription;
    $subscription->flush('collages', $CollageID);
    $subscription->flushQuotes('collages', $CollageID);
}

(new Gazelle\Log)->general("Collage $CollageID ($Name) was deleted by ".$LoggedUser['Username'].": $Reason");

$Cache->delete_value("collage_$CollageID");
header('Location: collages.php');
