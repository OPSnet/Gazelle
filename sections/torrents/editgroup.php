<?php
/************************************************************************
||------------|| Edit torrent group wiki page ||-----------------------||

This page is the page that is displayed when someone feels like editing
a torrent group's wiki page.

It is called when $_GET['action'] == 'edit'. $_GET['groupid'] is the
ID of the torrent group and must be set.

The page inserts a new revision into the wiki_torrents table, and clears
the cache for the torrent group page.

************************************************************************/

$GroupID = (int)$_GET['groupid'];
if ($GroupID < 1) {
    error(0);
}

// Get the torrent group name and the body of the last revision
list($Name, $Image, $Body, $WikiImage, $WikiBody, $Year,
    $RecordLabel, $CatalogueNumber, $ReleaseType, $CategoryID, $VanityHouse, $noCoverArt
) = $DB->row("
    SELECT
        tg.Name,
        wt.Image,
        wt.Body,
        tg.WikiImage,
        tg.WikiBody,
        tg.Year,
        tg.RecordLabel,
        tg.CatalogueNumber,
        tg.ReleaseType,
        tg.CategoryID,
        tg.VanityHouse,
        (tgha.TorrentGroupID IS NOT NULL) AS noCoverArt
    FROM torrents_group AS tg
    LEFT JOIN wiki_torrents AS wt USING (RevisionID)
    LEFT JOIN torrent_group_has_attr AS tgha ON (tgha.TorrentGroupID = tg.ID
        AND tgha.TorrentGroupAttrID = (SELECT tga.ID FROM torrent_group_attr tga WHERE tga.Name = 'no-cover-art')
    )
    WHERE tg.ID = ?
    ", $GroupID
);
if (!$Name) {
    error(404);
}

if (!$Body) {
    // TODO: use coalesce(tg.WikiBody, wt.Body)
    $Body = $WikiBody;
    $Image = $WikiImage;
}

View::show_header('Edit torrent group');

// Start printing form
?>
<div class="thin">
    <div class="header">
        <h2>Edit <a href="torrents.php?id=<?=$GroupID?>"><?=$Name?></a></h2>
    </div>
    <div class="box pad">
        <form class="edit_form" name="torrent_group" action="torrents.php" method="post">
            <div>
                <input type="hidden" name="action" value="takegroupedit" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                <h3>Image:</h3>
                <input type="text" name="image" size="92" value="<?=$Image?>" /><br />
<?php if (IMAGE_HOST_BANNED) { ?>
                <br /><b>Images hosted on <strong class="important_text"><?= implode(', ', IMAGE_HOST_BANNED)
                    ?> are not allowed</strong>, please rehost first on one of <?= implode(', ', IMAGE_HOST_RECOMMENDED) ?>.</b>
<?php } ?>
                <br />Or if the release has no known official artwork (e.g. jam band live recording), check the following:<br />
                <label><input type="checkbox" name="no_cover_art" value="1" <?=($noCoverArt ? 'checked="checked" ' : '')?>/> No release cover art</label><br /><br />

                <h3>Torrent group description:</h3>
                <?= (new Gazelle\Util\Textarea('body', display_str($Body), 80, 20))->emit() ?>
<?php if ($CategoryID == 1) { ?>
                <h3>Release type:
                    <select id="releasetype" name="releasetype">
<?php
    $releaseTypes = (new Gazelle\ReleaseType)->list();
    foreach ($releaseTypes as $Key => $Val) {
?>
                        <option value="<?=$Key?>"<?=($Key == $ReleaseType ? ' selected="selected"' : '')?>><?=$Val?></option>
<?php } ?>
                    </select>
                </h3>
<?php if (check_perms('torrents_edit_vanityhouse')) { ?>
                <h3>
                    <label><input type="checkbox" name="vanity_house" value="1" <?=($VanityHouse ? 'checked="checked" ' : '')?>/> Vanity House</label>
                </h3>
<?php
    }
}
?>
                <h3>Edit summary:</h3>
                <input type="text" name="summary" size="92" /><br />
                <div style="text-align: center;">
                    <input type="submit" value="Submit" />
                </div>
            </div>
        </form>
    </div>
<?php
    $DB->prepared_query("
        SELECT UserID
        FROM torrents
        WHERE GroupID = ?
        ", $GroupID
    );
    //Users can edit the group info if they've uploaded a torrent to the group or have torrents_edit
    if (in_array($LoggedUser['ID'], $DB->collect('UserID')) || check_perms('torrents_edit')) { ?>
    <h3>Non-wiki torrent group editing</h3>
    <div class="box pad">
        <form class="edit_form" name="torrent_group" action="torrents.php" method="post">
            <input type="hidden" name="action" value="nonwikiedit" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <input type="hidden" name="groupid" value="<?=$GroupID?>" />
            <table cellpadding="3" cellspacing="1" border="0" class="layout border" width="100%">
                <tr>
                    <td colspan="2" class="center">This is for editing the information related to the <strong>Original Release</strong> only.</td>
                </tr>
                <tr>
                    <td class="label">Year</td>
                    <td>
                        <input type="text" name="year" size="10" value="<?=$Year?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label">Record label</td>
                    <td>
                        <input type="text" name="record_label" size="40" value="<?=$RecordLabel?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label">Catalogue number</td>
                    <td>
                        <input type="text" name="catalogue_number" size="40" value="<?=$CatalogueNumber?>" />
                    </td>
                </tr>
<?php if (check_perms('torrents_freeleech')) { ?>
                <tr>
                    <td class="label">Torrent <strong>group</strong> leech status</td>
                    <td>
<?php
        $Leech = ['Normal', 'Freeleech', 'Neutral Leech'];
        foreach ($Leech as $Key => $Type) {
?>
                        <label><input type="radio" name="freeleechtype" value="<?=$Key?>"<?=($Key == $Torrent['FreeTorrent'] ? ' checked="checked"' : '')?> /> <?=$Type?></label>
<?php   } ?>
                         because
                        <select name="freeleechreason">
<?php
        $FL = ['N/A', 'Staff Pick', 'Perma-FL', 'Vanity House'];
        foreach ($FL as $Key => $FLType) {
?>
                            <option value="<?=$Key?>"<?=($Key == $Torrent['FreeLeechType'] ? ' selected="selected"' : '')?>><?=$FLType?></option>
<?php   } ?>
                        </select>
                    </td>
                </tr>
<?php } ?>
            </table>
            <input type="submit" value="Edit" />
        </form>
    </div>
<?php
}
if (check_perms('torrents_edit')) {
?>
    <h3>Rename (will not merge)</h3>
    <div class="box pad">
        <form class="rename_form" name="torrent_group" action="torrents.php" method="post">
            <div>
                <input type="hidden" name="action" value="rename" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                <input type="text" name="name" size="92" value="<?= display_str($Name) ?>" />
                <div style="text-align: center;">
                    <input type="submit" value="Rename" />
                </div>
            </div>
        </form>
    </div>
    <h3>Merge with another group</h3>
    <div class="box pad">
        <form class="merge_form" name="torrent_group" action="torrents.php" method="post">
            <div>
                <input type="hidden" name="action" value="merge" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                <h3>Target torrent group ID:
                    <input type="text" name="targetgroupid" size="10" />
                </h3>
                <div style="text-align: center;">
                    <input type="submit" value="Merge" />
                </div>
            </div>
        </form>
    </div>
<?php
} ?>
</div>
<?php
View::show_footer();
