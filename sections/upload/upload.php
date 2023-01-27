<?php
//**********************************************************************//
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Upload form ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
// This page relies on the TORRENT_FORM class. All it does is call      //
// the necessary functions.                                             //
//----------------------------------------------------------------------//
// $Properties, $Err and $categoryId are set in upload_handle.php,      //
// and are only used when the form doesn't validate and this page must  //
// be called again.                                                     //
//**********************************************************************//

ini_set('max_file_uploads', '100');

if (!isset($Properties)) {
    $requestId = (int)($_GET['requestid'] ?? 0);
    if ((int)($_GET['groupid'] ?? 0)) {
        $addTgroup = (new Gazelle\Manager\TGroup)->findById((int)$_GET['groupid']);
        if (is_null($addTgroup)) {
            unset($_GET['groupid']);
        } else {
            $categoryId = $addTgroup->categoryId();
            $Properties = [
                'GroupID'          => $addTgroup->id(),
                'ReleaseType'      => $addTgroup->releaseType(),
                'Title'            => $addTgroup->name(),
                'Year'             => $addTgroup->year(),
                'Image'            => $addTgroup->image(),
                'GroupDescription' => $addTgroup->description(),
                'RecordLabel'      => $addTgroup->recordLabel(),
                'CatalogueNumber'  => $addTgroup->catalogueNumber(),
                'VanityHouse'      => $addTgroup->isShowcase(),
                'Artists'          => Artists::get_artist($addTgroup->id()),
                'TagList'          => implode(', ', $addTgroup->tagNameList()),
            ];
            if ($requestId) {
                $Properties['RequestID'] = $requestId;
            }
        }
    } elseif ($requestId) {
        $addRequest = (new Gazelle\Manager\Request)->findById($requestId);
        if ($addRequest) {
            $categoryId = $addRequest->categoryId();
            $Properties = [
                'RequestID'        => $requestId,
                'ReleaseType'      => $addRequest->releaseType(),
                'Title'            => $addRequest->title(),
                'Year'             => $addRequest->year(),
                'Image'            => $addRequest->image(),
                'GroupDescription' => $addRequest->description(),
                'RecordLabel'      => $addRequest->recordLabel(),
                'CatalogueNumber'  => $addRequest->catalogueNumber(),
                'Artists'          => Artists::get_artist($addRequest->id()),
                'TagList'          => implode(', ', $addRequest->tagNameList()),
            ];
        }
    }
}

if (!empty($ArtistForm)) {
    $Properties['Artists'] = $ArtistForm;
}

if (empty($Properties)) {
    $Properties = null;
}
if (empty($Err)) {
    $Err = null;
}

$dnu     = new Gazelle\Manager\DNU;
$dnuNew  = $dnu->hasNewForUser($Viewer);
$hideDnu = !$dnuNew && $Viewer->permitted('torrents_hide_dnu');

View::show_header('Upload', ['js' => 'upload,validate_upload,valid_tags,musicbrainz,bbcode']);
?>
<div class="<?= $Viewer->permitted('torrents_hide_dnu') ? 'box pad' : '' ?>" style="margin: 0px auto; width: 700px;">
    <h3 id="dnu_header">Do Not Upload List</h3>
    <p><?= $dnuNew ? '<strong class="important_text">' : '' ?>Last updated: <?= time_diff($dnu->latest()) ?><?= $dnuNew ? '</strong>' : '' ?></p>
    <p>The following releases are currently forbidden from being uploaded to the site. Do not upload them unless your torrent meets a condition specified in the comment.
<?php if ($hideDnu) { ?>
    <span id="showdnu"><a href="#" onclick="$('#dnulist').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Show</a></span>
<?php } ?>
    </p>
    <table id="dnulist" class="<?= $hideDnu ? 'hidden' : '' ?>">
        <tr class="colhead">
            <td width="30%"><strong>Name</strong></td>
            <td><strong>Reason</strong></td>
        </tr>
<?php foreach ($dnu->dnuList() as $bad) { ?>
        <tr>
            <td>
                <?= Text::full_format($bad['name']) ?>
<?php   if ($bad['is_new']) { ?>
                <strong class="important_text">(New!)</strong>
<?php   } ?>
            </td>
            <td><?= Text::full_format($bad['comment']) ?></td>
        </tr>
<?php } ?>
    </table>
</div><?= $dnuHide ? '<br />' : '' ?>
<?php
$uploadForm = new Gazelle\Util\UploadForm($Viewer, $Properties, $Err);
if (isset($categoryId)) {
    // we have been require'd from upload_handle
    $uploadForm->setCategoryId($categoryId);
}
echo $uploadForm->head();
echo match (CATEGORY[($categoryId ?? 1) - 1]) {
    'Audiobooks', 'Comedy'                                   => $uploadForm->audiobook_form(),
    'Applications', 'Comics', 'E-Books', 'E-Learning Videos' => $uploadForm->simple_form(),
    default                                                  => $uploadForm->music_form((new Gazelle\Manager\Tag)->genreList()),
};
echo $uploadForm->foot(true);
