<?php
//**********************************************************************//
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~ Upload form ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
// This page relies on the TORRENT_FORM class. All it does is call      //
// the necessary functions.                                             //
//----------------------------------------------------------------------//
// $Properties, $Err and $uploadCategory are set in upload_handle.php, and  //
// are only used when the form doesn't validate and this page must be   //
// called again.                                                        //
//**********************************************************************//

ini_set('max_file_uploads', '100');
View::show_header('Upload', ['js' => 'upload,validate_upload,valid_tags,musicbrainz,bbcode']);

if (empty($Properties) && !empty($_GET['groupid']) && is_number($_GET['groupid'])) {
    $DB->prepared_query("
        SELECT
            tg.ID as GroupID,
            tg.CategoryID,
            tg.Name AS Title,
            tg.Year,
            tg.RecordLabel,
            tg.CatalogueNumber,
            tg.WikiImage AS Image,
            tg.WikiBody AS GroupDescription,
            tg.ReleaseType,
            tg.VanityHouse,
            group_concat(t.Name SEPARATOR ', ') AS TagList
        FROM torrents_group AS tg
        INNER JOIN torrents_tags AS tt ON (tt.GroupID = tg.ID)
        INNER JOIN tags t ON (t.ID = tt.TagID)
        WHERE tg.ID = ?
        GROUP BY tg.ID, tg.CategoryID, tg.Name, tg.Year, tg.RecordLabel, tg.CatalogueNumber,
            tg.WikiImage, tg.WikiBody, tg.ReleaseType, tg.VanityHouse
        ", (int)$_GET['groupid']
    );
    if ($DB->has_results()) {
        $Properties = $DB->next_record();
        $uploadCategory = CATEGORY[$Properties['CategoryID'] - 1];
        $Properties['CategoryName'] = CATEGORY[$Properties['CategoryID'] - 1];
        $Properties['Artists'] = Artists::get_artist($_GET['groupid']);
    } else {
        unset($_GET['groupid']);
    }
    if (!empty($_GET['requestid']) && is_number($_GET['requestid'])) {
        $Properties['RequestID'] = $_GET['requestid'];
    }
} elseif (empty($Properties) && !empty($_GET['requestid']) && is_number($_GET['requestid'])) {
    $DB->prepared_query("
        SELECT
            ID AS RequestID,
            CategoryID,
            Title,
            Year,
            RecordLabel,
            CatalogueNumber,
            ReleaseType,
            Image
        FROM requests
        WHERE ID = ?
        ", (int)$_GET['requestid']
    );
    $Properties = $DB->next_record();
    $uploadCategory = CATEGORY[$Properties['CategoryID'] - 1];
    $Properties['CategoryName'] = CATEGORY[$Properties['CategoryID'] - 1];
    $Properties['Artists'] = Requests::get_artists($_GET['requestid']);
    $Properties['TagList'] = implode(', ', Requests::get_tags($_GET['requestid'])[$_GET['requestid']]);
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
    SELECT
        Name,
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
switch ($uploadCategory) {
    case 'Music':
        $uploadForm->music_form($GenreTags);
        break;

    case 'Audiobooks':
    case 'Comedy':
        $uploadForm->audiobook_form();
        break;

    case 'Applications':
    case 'Comics':
    case 'E-Books':
    case 'E-Learning Videos':
        $uploadForm->simple_form($Properties['CategoryID']);
        break;
    default:
        $uploadForm->music_form($GenreTags);
}
$uploadForm->foot();
