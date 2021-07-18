<?php
authorize();

if (!$_REQUEST['groupid'] || !is_number($_REQUEST['groupid'])) {
    error(404);
}
if (!check_perms('site_edit_wiki')) {
    error(403);
}
if (!check_perms('torrents_edit_vanityhouse') && isset($_POST['vanity_house'])) {
    error(403);
}

// Variables for database input
$UserID = $Viewer->id();
$GroupID = (int)$_REQUEST['groupid'];

// Get information for the group log
list ($OldVH, $oldNoCoverArt) = $DB->row("
    SELECT
        tg.VanityHouse,
        CASE WHEN tgha.TorrentGroupID IS NULL THEN 0 ELSE 1 END as noCoverArt
    FROM torrents_group tg
    LEFT JOIN torrent_group_has_attr AS tgha ON (tgha.TorrentGroupID = tg.ID
        AND tgha.TorrentGroupAttrID = (SELECT tga.ID FROM torrent_group_attr tga WHERE tga.Name = 'no-cover-art')
    )
    WHERE tg.ID = ?
    ", $GroupID
);

if ($OldVH === null) {
    error(404);
}

$VanityHouse = $OldVH;

if (!empty($_GET['action']) && $_GET['action'] == 'revert') { // if we're reverting to a previous revision
    $RevisionID = $_GET['revisionid'];
    if (!is_number($RevisionID)) {
        error(0);
    }

    // to cite from merge: "Everything is legit, let's just confim they're not retarded"
    if (empty($_GET['confirm'])) {
        View::show_header('Group Edit');
?>
    <div class="center thin">
    <div class="header">
        <h2>Revert Confirm!</h2>
    </div>
    <div class="box pad">
        <form class="confirm_form" name="torrent_group" action="torrents.php" method="get">
            <input type="hidden" name="action" value="revert" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="confirm" value="true" />
            <input type="hidden" name="groupid" value="<?=$GroupID?>" />
            <input type="hidden" name="revisionid" value="<?=$RevisionID?>" />
            <h3>You are attempting to revert to the revision <a href="torrents.php?id=<?=$GroupID?>&amp;revisionid=<?=$RevisionID?>"><?=$RevisionID?></a>.</h3>
            <input type="submit" value="Confirm" />
        </form>
    </div>
    </div>
<?php
        View::show_footer();
        die();
    }
} else { // with edit, the variables are passed with POST
    $Body = $_POST['body'];
    $Image = $_POST['image'];
    $ReleaseType = (int)$_POST['releasetype'];
    if (check_perms('torrents_edit_vanityhouse')) {
        $VanityHouse = (isset($_POST['vanity_house']) ? 1 : 0);
    }

    if (($GroupInfo = $Cache->get_value('torrents_details_'.$GroupID)) && !isset($GroupInfo[0][0])) {
        $GroupCategoryID = $GroupInfo[0]['CategoryID'];
    } else {
        $GroupCategoryID = $DB->scalar("
            SELECT CategoryID
            FROM torrents_group
            WHERE ID = ?
            ", $GroupID
        );
    }
    if ($GroupCategoryID == 1 && !(new Gazelle\ReleaseType)->findNameById($ReleaseType) || $GroupCategoryID != 1 && $ReleaseType) {
        error(403);
    }

    // Trickery
    if (!preg_match(IMAGE_REGEXP, $Image)) {
        $Image = '';
    }
    ImageTools::blacklisted($Image);
    foreach (IMAGE_HOST_BANNED as $banned) {
        if (stripos($banned, $Image) !== false) {
            error("Please rehost images from $banned elsewhere.");
        }
    }
}

// Insert revision
if (empty($RevisionID)) { // edit
    $DB->prepared_query("
        UPDATE torrents_group SET
            ReleaseType = ?
        WHERE ID = ?
        ", $ReleaseType, $GroupID
    );
    $DB->prepared_query("
        INSERT INTO wiki_torrents
               (PageID, Body, Image, UserID, Summary)
        VALUES (?,      ?,    ?,     ?,      ?)
        ", $GroupID, $Body, $Image, $UserID, trim($_POST['summary'])
    );
    (new \Gazelle\Manager\TGroup)->refresh($GroupID);
}
else { // revert
    list($PossibleGroupID, $Body, $Image) = $DB->row("
        SELECT PageID, Body, Image
        FROM wiki_torrents
        WHERE RevisionID = ?
        ", $RevisionID
    );
    if ($PossibleGroupID != $GroupID) {
        error(404);
    }
    $DB->prepared_query("
        INSERT INTO wiki_torrents
               (PageID, Body, Image, UserID, Summary)
        SELECT  ?,      Body, Image, ?,      ?
        FROM wiki_torrents
        WHERE RevisionID = ?
        ", $GroupID, $UserID, "Reverted to revision $RevisionID",
            $RevisionID
    );
}
$RevisionID = $DB->inserted_id();

// Update torrents table (technically, we don't need the RevisionID column, but we can use it for a join which is nice and fast)
$DB->prepared_query("
    UPDATE torrents_group SET
        RevisionID  = ?,
        VanityHouse = ?,
        WikiBody    = ?,
        WikiImage   = ?
    WHERE ID = ?
    ", $RevisionID, $VanityHouse, $Body, $Image, $GroupID
);

$noCoverArt = (isset($_POST['no_cover_art']) ? 1 : 0);

$logInfo = [];
if ($_POST['summary']) {
    $logInfo[] = "summary: " . trim($_POST['summary']);
}
if ($noCoverArt != $oldNoCoverArt) {
    if ($noCoverArt) {
        $DB->prepared_query("
            INSERT INTO torrent_group_has_attr
               (TorrentGroupID, TorrentGroupAttrID)
            VALUES (?, (SELECT ID FROM torrent_group_attr WHERE Name = 'no-cover-art'))
            ", $GroupID
        );
        $logInfo[] = 'No cover art exception added';
    } else {
        $DB->prepared_query("
            DELETE FROM torrent_group_has_attr
            WHERE TorrentGroupAttrID = (SELECT ID FROM torrent_group_attr WHERE Name = 'no-cover-art')
                AND TorrentGroupID = ?
            ", $GroupID
        );
        $logInfo[] = 'No cover art exception removed';
    }
}
if ($OldVH != $VanityHouse) {
    $logInfo[] = 'Vanity House status changed to '. ($VanityHouse ? 'true' : 'false');
}
if ($logInfo) {
    (new Gazelle\Log)->group($GroupID, $Viewer->id(), implode(', ', $logInfo));
}

// There we go, all done!

$Cache->delete_value('torrents_details_'.$GroupID);
$DB->prepared_query("
    SELECT concat('collagev2_', CollageID) as ck
    FROM collages_torrents
    WHERE GroupID = ?
    ", $GroupID
);
$Cache->deleteMulti($DB->collect('ck', false));

//Fix Recent Uploads/Downloads for image change
$DB->prepared_query("
    SELECT DISTINCT concat('user_recent_up_' , UserID) as ck
    FROM torrents AS t
    LEFT JOIN torrents_group AS tg ON (t.GroupID = tg.ID)
    WHERE tg.ID = ?
    ", $GroupID
);
$Cache->deleteMulti($DB->collect('ck', false));

$DB->prepared_query('
    SELECT ID FROM torrents WHERE GroupID = ?
    ', $GroupID
);
if ($DB->has_results()) {
    $IDs = $DB->collect('ID');
    $DB->prepared_query(
        sprintf("
            SELECT DISTINCT concat('user_recent_snatch_', uid) as ck
            FROM xbt_snatched
            WHERE fid IN (%s)
            ", placeholders($IDs)
        ), ...$IDs
    );
    $Cache->deleteMulti($DB->collect('ck', false));
}

header("Location: torrents.php?id=$GroupID");
