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
    $categoryId = false;
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
    if ($categoryId) {
        $Properties['CategoryName'] = CATEGORY[$categoryId - 1];
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

$DB->prepared_query("
    SELECT Name,
        Comment,
        Time
    FROM do_not_upload
    ORDER BY Sequence"
);
$DNU = $DB->to_array();
$Updated = $DB->scalar('SELECT MAX(Time) FROM do_not_upload');
$NewDNU = $DB->scalar("
    SELECT IF(MAX(Time) IS NULL OR MAX(Time) < ?, 1, 0)
    FROM torrents
    WHERE UserID = ?
    ", $Updated, $Viewer->id()
);
$HideDNU = $Viewer->permitted('torrents_hide_dnu') && !$NewDNU;
View::show_header('Upload', ['js' => 'upload,validate_upload,valid_tags,musicbrainz,bbcode']);
?>
<div class="<?= $Viewer->permitted('torrents_hide_dnu') ? 'box pad' : '' ?>" style="margin: 0px auto; width: 700px;">
    <h3 id="dnu_header">Do Not Upload List</h3>
    <p><?=$NewDNU ? '<strong class="important_text">' : '' ?>Last updated: <?=time_diff($Updated)?><?=$NewDNU ? '</strong>' : '' ?></p>
    <p>The following releases are currently forbidden from being uploaded to the site. Do not upload them unless your torrent meets a condition specified in the comment.
<?php    if ($HideDNU) { ?>
    <span id="showdnu"><a href="#" onclick="$('#dnulist').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Show</a></span>
<?php    } ?>
    </p>
    <table id="dnulist" class="<?=($HideDNU ? 'hidden' : '')?>">
        <tr class="colhead">
            <td width="50%"><strong>Name</strong></td>
            <td><strong>Comment</strong></td>
        </tr>
<?php     $TimeDiff = strtotime('-1 month', strtotime('now'));
    foreach ($DNU as $BadUpload) {
        [$Name, $Comment, $Updated] = $BadUpload;
?>
        <tr>
            <td>
                <?=Text::full_format($Name) . "\n" ?>
<?php   if ($TimeDiff < strtotime($Updated)) { ?>
                <strong class="important_text">(New!)</strong>
<?php   } ?>
            </td>
            <td><?=Text::full_format($Comment)?></td>
        </tr>
<?php
    } ?>
    </table>
</div><?=($HideDNU ? '<br />' : '')?>
<?php
$GenreTags = (new Gazelle\Manager\Tag)->genreList();
$uploadForm = new Gazelle\Util\UploadForm($Viewer, $Properties, $Err);
if (isset($categoryId)) {
    // we have been require'd from upload_handle
    $uploadForm->setCategoryId($categoryId);
}
$uploadForm->head();
switch (CATEGORY[($categoryId ?? 1) - 1]) {
    case 'Audiobooks':
    case 'Comedy':
        $uploadForm->audiobook_form();
        break;

    case 'Applications':
    case 'Comics':
    case 'E-Books':
    case 'E-Learning Videos':
        $uploadForm->simple_form($categoryId);
        break;

    case 'Music':
    default:
        $uploadForm->music_form($GenreTags);
        break;
}
$uploadForm->foot(true);
