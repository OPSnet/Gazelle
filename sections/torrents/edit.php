<?php
//**********************************************************************//
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Edit form ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
// This page relies on the Util\UploadForm class. All it does is call   //
// the necessary functions.                                             //
//----------------------------------------------------------------------//
// At the bottom, there are grouping functions which are off limits to  //
// most members.                                                        //
//**********************************************************************//

$torrent = (new Gazelle\Manager\Torrent)->findById((int)($_GET['id'] ?? 0));
if (is_null($torrent)) {
    error(404);
}

if (($Viewer->id() != $torrent->uploaderId() && !$Viewer->permitted('torrents_edit')) || $Viewer->disableWiki()) {
    error(403);
}

$artist       = $torrent->group()->primaryArtist();
$categoryId   = $torrent->group()->categoryId();
$categoryName = $torrent->group()->categoryName();
$isMusic      = $categoryName === 'Music';
$tgroupId     = $torrent->groupId();
$torrentId    = $torrent->id();
$releaseTypes = (new Gazelle\ReleaseType)->list();

View::show_header('Edit torrent', ['js' => 'upload,torrent']);

if ($Viewer->permitted('torrents_edit') && ($Viewer->permitted('users_mod') || $isMusic)) {
    if ($isMusic) {
?>
<div class="linkbox">
    <a class="brackets" href="#group-change">Change Group</a>
    <a class="brackets" href="#group-split">Split Off into New Group</a>
<?php   if ($Viewer->permitted('users_mod')) { ?>
    <a class="brackets" href="#category-change">Change Category</a>
<?php   } ?>
</div>
<?php
    }
}

if (!($torrent->isRemastered() && !$torrent->remasterYear()) || $Viewer->permitted('edit_unknowns')) {
    $uploadForm = new Gazelle\Util\UploadForm(
        $Viewer,
        [
            'ID'                      => $torrentId,
            'Media'                   => $torrent->media(),
            'Format'                  => $torrent->format(),
            'Bitrate'                 => $torrent->encoding(),
            'RemasterYear'            => $torrent->remasterYear(),
            'Remastered'              => $torrent->isRemastered(),
            'RemasterTitle'           => $torrent->remasterTitle(),
            'RemasterCatalogueNumber' => $torrent->remasterCatalogueNumber(),
            'RemasterRecordLabel'     => $torrent->remasterRecordLabel(),
            'Scene'                   => $torrent->isScene(),
            'FreeTorrent'             => $torrent->isFreeleech(),
            'FreeLeechType'           => $torrent->freeleechType(),
            'TorrentDescription'      => $torrent->description(),
            'CategoryID'              => $categoryId,
            'Title'                   => $torrent->group()->name(),
            'Year'                    => $torrent->group()->year(),
            'VanityHouse'             => $torrent->group()->isShowcase(),
            'GroupID'                 => $tgroupId,
            'UserID'                  => $torrent->uploaderId(),
            'HasLog'                  => $torrent->hasLog(),
            'HasCue'                  => $torrent->hasCue(),
            'LogScore'                => $torrent->logScore(),
            'BadTags'                 => $torrent->hasBadTags(),
            'BadFolders'              => $torrent->hasBadFolders(),
            'BadFiles'                => $torrent->hasBadFiles(),
            'MissingLineage'          => $torrent->hasMissingLineage(),
            'CassetteApproved'        => $torrent->hasCassetteApproved(),
            'LossymasterApproved'     => $torrent->hasLossymasterApproved(),
            'LossywebApproved'        => $torrent->hasLossywebApproved(),
        ],
        $Err ?? false,
        false
    );
    $uploadForm->setCategoryId($categoryId);
    $uploadForm->head();
    switch ($categoryName) {
        case 'Music':
            $uploadForm->music_form('');
            break;

        case 'Audiobooks':
        case 'Comedy':
            $uploadForm->audiobook_form();
            break;

        case 'Applications':
        case 'Comics':
        case 'E-Books':
        case 'E-Learning Videos':
            $uploadForm->simple_form();
            break;

        default:
            $uploadForm->music_form('');
    }
    $uploadForm->foot(false);
}
if ($Viewer->permitted('torrents_edit') && ($Viewer->permitted('users_mod') || $isMusic)) {
?>
<div class="thin">
<?php if ($isMusic) { ?>
    <div class="header">
        <h2><a name="group-change">Change group</a></h2>
    </div>
    <form class="edit_form" name="torrent_group" action="torrents.php" method="post">
        <input type="hidden" name="action" value="editgroupid" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <input type="hidden" name="torrentid" value="<?= $torrentId ?>" />
        <input type="hidden" name="oldgroupid" value="<?= $tgroupId ?>" />
        <table class="layout">
            <tr>
                <td class="label">Group ID</td>
                <td>
                    <input type="text" name="groupid" value="<?= $tgroupId ?>" size="10" />
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
        <input type="hidden" name="torrentid" value="<?= $torrentId ?>" />
        <input type="hidden" name="oldgroupid" value="<?= $tgroupId ?>" />
        <table class="layout">
            <tr>
                <td class="label">Artist</td>
                <td>
                    <input type="text" name="artist" value="<?= $artist?->name() ?>" size="50" />
                </td>
            </tr>
            <tr>
                <td class="label">Title</td>
                <td>
                    <input type="text" name="title" value="<?= $torrent->group()->name() ?>" size="50" />
                </td>
            </tr>
            <tr>
                <td class="label">Year</td>
                <td>
                    <input type="text" name="year" value="<?= $torrent->group()->year() ?>" size="10" />
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Split off into new group" />
                </td>
            </tr>
        </table>
    </form>
    <br />
<?php
    } /* category == 1 */
    if ($Viewer->permitted('users_mod')) { ?>
    <h2><a name="category-change">Change category</a></h2>
    <form action="torrents.php" method="post">
        <input type="hidden" name="action" value="changecategory" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <input type="hidden" name="torrentid" value="<?= $torrentId ?>" />
        <input type="hidden" name="oldgroupid" value="<?= $tgroupId ?>" />
        <input type="hidden" name="oldartistid" value="<?= $artist?->id() ?>" />
        <input type="hidden" name="oldcategoryid" value="<?= $categoryId ?>" />
        <table>
            <tr>
                <td class="label">Change category</td>
                <td>
                    <select id="newcategoryid" name="newcategoryid" onchange="ChangeCategory(this.value);">
<?php   foreach (CATEGORY as $CatID => $CatName) { ?>
                        <option value="<?= $CatID + 1 ?>"<?= $categoryId == $CatID + 1 ? ' selected="selected"' : '' ?>><?= $CatName ?></option>
<?php   } ?>
                    </select>
                </td>
            <tr id="split_releasetype">
                <td class="label">Release type</td>
                <td>
                    <select name="releasetype">
<?php
        foreach ($releaseTypes as $RTID => $ReleaseType) {
?>
                        <option value="<?= $RTID ?>"><?= $ReleaseType ?></option>
<?php   } ?>
                    </select>
                </td>
            </tr>
            <tr id="split_artist">
                <td class="label">Artist</td>
                <td>
                    <input type="text" name="artist" value="<?= $artist?->name() ?>" size="50" />
                </td>
            </tr>
            <tr id="split_title">
                <td class="label">Title</td>
                <td>
                    <input type="text" name="title" value="<?= $torrent->group()->name() ?>" size="50" />
                </td>
            </tr>
            <tr id="split_year">
                <td class="label">Year</td>
                <td>
                    <input type="text" name="year" value="<?= $torrent->group()->year() ?>" size="10" />
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
} // if $Viewer->permitted('torrents_edit')

View::show_footer();
