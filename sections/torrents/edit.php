<?php
//**********************************************************************//
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Edit form ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
// This page relies on the TORRENT_FORM class. All it does is call        //
// the necessary functions.                                                //
//----------------------------------------------------------------------//
// At the bottom, there are grouping functions which are off limits to    //
// most members.                                                        //
//**********************************************************************//

require(__DIR__ . '/../../classes/torrent_form.class.php');

$TorrentID = (int)$_GET['id'];
if ($TorrentID < 1) {
    error(0);
}

$DB->prepared_query('
    SELECT
        t.Media,
        t.Format,
        t.Encoding AS Bitrate,
        t.RemasterYear,
        t.Remastered,
        t.RemasterTitle,
        t.RemasterCatalogueNumber,
        t.RemasterRecordLabel,
        t.Scene,
        t.FreeTorrent,
        t.FreeLeechType,
        t.Description AS TorrentDescription,
        tg.CategoryID,
        tg.Name AS Title,
        tg.Year,
        ag.ArtistID,
        tg.VanityHouse,
        ag.Name AS ArtistName,
        t.GroupID,
        t.UserID,
        t.HasLog,
        t.HasCue,
        t.LogScore,
        bt.TorrentID AS BadTags,
        bf.TorrentID AS BadFolders,
        bfi.TorrentID AS BadFiles,
        ml.TorrentID AS MissingLineage,
        ca.TorrentID AS CassetteApproved,
        lma.TorrentID AS LossymasterApproved,
        lwa.TorrentID AS LossywebApproved
    FROM torrents AS t
    LEFT JOIN torrents_group AS tg ON (tg.ID = t.GroupID)
    LEFT JOIN torrents_artists AS ta ON (ta.GroupID = tg.ID)
    LEFT JOIN artists_group AS ag ON (ag.ArtistID = ta.ArtistID)
    LEFT JOIN torrents_bad_tags AS bt ON (bt.TorrentID = t.ID)
    LEFT JOIN torrents_bad_folders AS bf ON (bf.TorrentID = t.ID)
    LEFT JOIN torrents_bad_files AS bfi ON (bfi.TorrentID = t.ID)
    LEFT JOIN torrents_missing_lineage AS ml ON (ml.TorrentID = t.ID)
    LEFT JOIN torrents_cassette_approved AS ca ON (ca.TorrentID = t.ID)
    LEFT JOIN torrents_lossymaster_approved AS lma ON (lma.TorrentID = t.ID)
    LEFT JOIN torrents_lossyweb_approved AS lwa ON (lwa.TorrentID = t.id)
    WHERE t.ID = ?
    ', $TorrentID
);

[$Properties] = $DB->to_array(false, MYSQLI_BOTH);
if (!$Properties) {
    error(404);
}

$UploadForm = CATEGORY[$Properties['CategoryID'] - 1];

if (($Viewer->id() != $Properties['UserID'] && !$Viewer->permitted('torrents_edit')) || $Viewer->disableWiki()) {
    error(403);
}

View::show_header('Edit torrent', 'upload,torrent');

if (check_perms('torrents_edit') && (check_perms('users_mod') || $Properties['CategoryID'] == 1)) {
    if ($Properties['CategoryID'] == 1) {
?>
<div class="linkbox">
    <a class="brackets" href="#group-change">Change Group</a>
    <a class="brackets" href="#group-split">Split Off into New Group</a>
<?php   if (check_perms('users_mod')) { ?>
    <a class="brackets" href="#category-change">Change Category</a>
<?php   } ?>
</div>
<?php
    }
}

if (!($Properties['Remastered'] && !$Properties['RemasterYear']) || check_perms('edit_unknowns')) {
    if (!isset($Err)) {
        $Err = false;
    }
    $TorrentForm = new TORRENT_FORM($Properties, $Err, false);

    $TorrentForm->head();
    switch ($UploadForm) {
        case 'Music':
            $TorrentForm->music_form('');
            break;

        case 'Audiobooks':
        case 'Comedy':
            $TorrentForm->audiobook_form();
            break;

        case 'Applications':
        case 'Comics':
        case 'E-Books':
        case 'E-Learning Videos':
            $TorrentForm->simple_form($Properties['CategoryID']);
            break;

        default:
            $TorrentForm->music_form('');
    }
    $TorrentForm->foot();
}
if (check_perms('torrents_edit') && (check_perms('users_mod') || $Properties['CategoryID'] == 1)) {
?>
<div class="thin">
<?php if ($Properties['CategoryID'] == 1) { ?>
    <div class="header">
        <h2><a name="group-change">Change group</a></h2>
    </div>
    <form class="edit_form" name="torrent_group" action="torrents.php" method="post">
        <input type="hidden" name="action" value="editgroupid" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
        <input type="hidden" name="oldgroupid" value="<?=$Properties['GroupID']?>" />
        <table class="layout">
            <tr>
                <td class="label">Group ID</td>
                <td>
                    <input type="text" name="groupid" value="<?=$Properties['GroupID']?>" size="10" />
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Change group ID" />
                </td>
            </tr>
        </table>
    </form>
    <h2><a name="group-split">Split off into new group</a></h2>
    <form class="split_form" name="torrent_group" action="torrents.php" method="post">
        <input type="hidden" name="action" value="newgroup" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
        <input type="hidden" name="oldgroupid" value="<?=$Properties['GroupID']?>" />
        <table class="layout">
            <tr>
                <td class="label">Artist</td>
                <td>
                    <input type="text" name="artist" value="<?=$Properties['ArtistName']?>" size="50" />
                </td>
            </tr>
            <tr>
                <td class="label">Title</td>
                <td>
                    <input type="text" name="title" value="<?=$Properties['Title']?>" size="50" />
                </td>
            </tr>
            <tr>
                <td class="label">Year</td>
                <td>
                    <input type="text" name="year" value="<?=$Properties['Year']?>" size="10" />
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Split into new group" />
                </td>
            </tr>
        </table>
    </form>
    <br />
<?php
    } /* category == 1 */
    if (check_perms('users_mod')) { ?>
    <h2><a name="category-change">Change category</a></h2>
    <form action="torrents.php" method="post">
        <input type="hidden" name="action" value="changecategory" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <input type="hidden" name="torrentid" value="<?=$TorrentID?>" />
        <input type="hidden" name="oldgroupid" value="<?=$Properties['GroupID']?>" />
        <input type="hidden" name="oldartistid" value="<?=$Properties['ArtistID']?>" />
        <input type="hidden" name="oldcategoryid" value="<?=$Properties['CategoryID']?>" />
        <table>
            <tr>
                <td class="label">Change category</td>
                <td>
                    <select id="newcategoryid" name="newcategoryid" onchange="ChangeCategory(this.value);">
<?php   foreach (CATEGORY as $CatID => $CatName) { ?>
                        <option value="<?=($CatID + 1)?>"<?php Format::selected('CategoryID', $CatID + 1, 'selected', $Properties); ?>><?=($CatName)?></option>
<?php   } ?>
                    </select>
                </td>
            <tr id="split_releasetype">
                <td class="label">Release type</td>
                <td>
                    <select name="releasetype">
<?php
        $releaseTypes = (new Gazelle\ReleaseType)->list();
        foreach ($releaseTypes as $RTID => $ReleaseType) {
?>
                        <option value="<?=($RTID)?>"><?=($ReleaseType)?></option>
<?php   } ?>
                    </select>
                </td>
            </tr>
            <tr id="split_artist">
                <td class="label">Artist</td>
                <td>
                    <input type="text" name="artist" value="<?=$Properties['ArtistName']?>" size="50" />
                </td>
            </tr>
            <tr id="split_title">
                <td class="label">Title</td>
                <td>
                    <input type="text" name="title" value="<?=$Properties['Title']?>" size="50" />
                </td>
            </tr>
            <tr id="split_year">
                <td class="label">Year</td>
                <td>
                    <input type="text" name="year" value="<?=$Properties['Year']?>" size="10" />
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Change category" />
                </td>
            </tr>
        </table>
        <script type="text/javascript">ChangeCategory($('#newcategoryid').raw().value);</script>
    </form>
<?php } ?>
</div>
<?php
} // if check_perms('torrents_edit')

View::show_footer();
