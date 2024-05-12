<?php

//**********************************************************************//
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Upload form ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
// $Properties, $Err and $categoryId are set in upload_handle.php,      //
// and are only used when the form doesn't validate and this page must  //
// be called again.                                                     //
//**********************************************************************//

ini_set('max_file_uploads', '100');

$tgMan = new Gazelle\Manager\TGroup();
if (!isset($Properties)) {
    $requestId = (int)($_GET['requestid'] ?? 0);
    if ((int)($_GET['groupid'] ?? 0)) {
        $tgroup = $tgMan->findById((int)$_GET['groupid']);
        if (is_null($tgroup)) {
            unset($_GET['groupid']);
        } else {
            $categoryId = $tgroup->categoryId();
            $Properties = [
                'add-format'       => true,
                'GroupID'          => $tgroup->id(),
                'ReleaseType'      => $tgroup->releaseType(),
                'Title'            => $tgroup->name(),
                'Year'             => $tgroup->year(),
                'Image'            => $tgroup->image(),
                'GroupDescription' => $tgroup->description(),
                'RecordLabel'      => $tgroup->recordLabel(),
                'CatalogueNumber'  => $tgroup->catalogueNumber(),
                'VanityHouse'      => $tgroup->isShowcase(),
                'Artists'          => $tgroup->artistRole()?->idList() ?? [],
                'TagList'          => implode(', ', $tgroup->tagNameList()),
                'UserID'           => $Viewer->id(),
            ];
            if ($requestId) {
                $Properties['RequestID'] = $requestId;
            }
        }
    } elseif ($requestId) {
        $request = (new Gazelle\Manager\Request())->findById($requestId);
        if ($request) {
            $categoryId = $request->categoryId();
            $Properties = [
                'add-format'       => true,
                'RequestID'        => $requestId,
                'ReleaseType'      => $request->releaseType(),
                'Title'            => $request->title(),
                'Year'             => $request->year(),
                'Image'            => $request->image(),
                'GroupDescription' => $request->description(),
                'RecordLabel'      => $request->recordLabel(),
                'CatalogueNumber'  => $request->catalogueNumber(),
                'Artists'          => $request->artistRole()?->idList() ?? [],
                'TagList'          => implode(', ', $request->tagNameList()),
                'UserID'           => $Viewer->id(),
            ];
        }
    } elseif (isset($_GET['artistid'])) {
        $artist = (new Gazelle\Manager\Artist())->findById((int)$_GET['artistid']);
        if ($artist) {
            $Properties = [
                'add-format' => true,
                'Artists'    => [
                    ARTIST_MAIN => [[
                        'id'   => $artist->id(),
                        'name' => $artist->name(),
                    ]],
                ],
                'UserID' => $Viewer->id(),
            ];
        }
    }
}

if (!isset($Properties)) {
    $Properties = false;
} elseif (isset($ArtistForm)) {
    $Properties['Artists'] = $ArtistForm;
}

if (!isset($Err)) {
    $Err = false;
}

$dnu     = new Gazelle\Manager\DNU();
$dnuNew  = $dnu->hasNewForUser($Viewer);
$dnuHide = !$dnuNew && $Viewer->permitted('torrents_hide_dnu');

View::show_header('Upload', ['js' => 'upload,validate_upload,valid_tags,musicbrainz,bbcode']);
?>
<div class="<?= $Viewer->permitted('torrents_hide_dnu') ? 'box pad' : '' ?>" style="margin: 0px auto; width: 700px;">
    <h3 id="dnu_header">Do Not Upload List</h3>
    <p><?= $dnuNew ? '<strong class="important_text">' : '' ?>Last updated: <?= time_diff($dnu->latest()) ?><?= $dnuNew ? '</strong>' : '' ?></p>
    <p>The following releases are currently forbidden from being uploaded to the site. Do not upload them unless your torrent meets a condition specified in the comment.
<?php if ($dnuHide) { ?>
    <span id="showdnu"><a href="#" onclick="$('#dnulist').gtoggle(); this.innerHTML = (this.innerHTML == 'Hide' ? 'Show' : 'Hide'); return false;" class="brackets">Show</a></span>
<?php } ?>
    </p>
    <table id="dnulist" class="<?= $dnuHide ? 'hidden' : '' ?>">
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
</div>
<br />
<?php
if (!isset($categoryId)) {
    $categoryId = CATEGORY_MUSIC;
}
$uploadForm = new Gazelle\Upload($Viewer, $Properties, $Err);
echo $uploadForm->head($categoryId);
echo match (CATEGORY[$categoryId - 1]) {
    'Audiobooks',       => $uploadForm->audiobook(),
    'Applications'      => $uploadForm->application(),
    'Comics'            => $uploadForm->comic(),
    'Comedy'            => $uploadForm->comedy(),
    'E-Books'           => $uploadForm->ebook(),
    'E-Learning Videos' => $uploadForm->elearning(),
    default             => $uploadForm->music((new Gazelle\Manager\Tag())->genreList(), $tgMan),
};
echo $uploadForm->foot(true);
