<?php

$GroupID = (int)$_GET['groupid'];
if (!$GroupID) {
    error(404);
}

$Name = $DB->scalar("
    SELECT Name
    FROM torrents_group
    WHERE ID = ?
    ", $GroupID
);
if (!$Name) {
    error(404);
}

View::show_header("Revision history for $Name");
?>
<div class="thin">
    <div class="header">
        <h2>Revision history for <a href="torrents.php?id=<?=$GroupID?>"><?=$Name?></a></h2>
    </div>
<?php
RevisionHistoryView::render_revision_history(RevisionHistory::get_revision_history('torrents', $GroupID), "torrents.php?id=$GroupID");
?>
</div>
<?php
View::show_footer();
