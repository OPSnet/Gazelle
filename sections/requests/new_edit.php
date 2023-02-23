<?php

/*
 * Yeah, that's right, edit and new are the same place.
 * It makes the page uglier to read but ultimately better as the alternative
 * means maintaining 2 copies of almost identical files.
 *
 * If a variable appears to have been initialized by magic, remember
 * that this file could have been require()'ed from take_new_edit.php
 * which has already initialized things from the submitted form.
 */

$newRequest = $_GET['action'] === 'new';
if ($newRequest) {
    if ($Viewer->uploadedSize() < 250 * 1024 * 1024 || !$Viewer->permitted('site_submit_requests')) {
        error('You have not enough upload to make a request.');
    }
    $request         = null;
    $categoryName    = '';
    $image           = '';
    $title           = '';
    $description     = '';
    $year            = '';
    $recordLabel     = '';
    $catalogueNumber = '';
    $oclc            = '';

    // We may be able to prepare some things based on whence we came
    if (isset($_GET['artistid'])) {
        $ArtistName = Gazelle\DB::DB()->scalar("
            SELECT Name FROM artists_group WHERE artistid = ?
            ", (int)$_GET['artistid']
        );
        if (!is_null($ArtistName)) {
            $ArtistForm = [
                1 => [['name' => trim($ArtistName)]],
                2 => [],
                3 => []
            ];
        }
    } elseif (isset($_GET['groupid'])) {
        $tgroup = (new Gazelle\Manager\TGroup)->findById((int)$_GET['groupid']);
        if ($tgroup) {
            $GroupID     = $tgroup->id();
            $categoryId  = $tgroup->categoryId();
            $title       = $tgroup->name();
            $year        = $tgroup->year();
            $releaseType = $tgroup->releaseType();
            $image       = $tgroup->image();
            $tags        = implode(', ', $tgroup->tagNameList());
            $ArtistForm  = $tgroup->artistRole()?->idList() ?? [];
        }
    }
} else {
    $request = (new Gazelle\Manager\Request)->findById((int)($_GET['id'] ?? 0));
    if (is_null($request)) {
        error(404);
    }
    $CanEdit = $request->canEdit($Viewer);
    if (!$CanEdit) {
        error(403);
    }
    $requestId = $request->id();

    if (!isset($returnEdit)) {
        // if we are coming back from an edit, these were already initialized in take_new_edit
        $categoryId  = $request->categoryId();
        $title       = $request->title();
        $description = $request->description();
        $year        = $request->year();
        $image       = $request->image();
        $tags        = implode(', ', $request->tagNameList());
        $releaseType = $request->releaseType();
        $GroupID     = $request->tgroupId();
        $VoteCount   = $request->userVotedTotal();
        $IsFilled    = $request->isFilled();
        $ownRequest  = $request->userId() == $Viewer->id();
        $Checksum    = $request->needLogChecksum();
        $LogCue      = $request->descriptionLogCue();
        $NeedCue     = $request->needCue();
        $NeedLog     = $request->needLog();
        if ($NeedLog) {
            $MinLogScore = $request->needLogScore();
        }

        $categoryName = $request->categoryName();
        if ($categoryName === 'Music') {
            $ArtistForm    = $request->artistRole()->idList();
            $EncodingArray = $request->currentEncoding();
            $FormatArray   = $request->currentFormat();
            $MediaArray    = $request->currentMedia();
            $recordLabel   = $request->recordLabel();
        }
    }
}

$releaseTypes = (new Gazelle\ReleaseType)->list();
$GenreTags    = (new Gazelle\Manager\Tag)->genreList();
$pageTitle    = $newRequest ? 'Create a request' : 'Edit request &rsaquo; ' . $request->selfLink();
View::show_header($pageTitle, ['js' => 'requests,form_validate']);
?>
<div class="thin">
    <div class="header">
        <h2><?= $pageTitle ?></h2>
    </div>
<?php
if (!$newRequest && $CanEdit && !$ownRequest && $Viewer->permitted('site_edit_requests')) {
    $requester = new Gazelle\User($request->userId());
?>
    <div class="box pad">
        <strong class="important_text">Warning! You are editing <?= $requester->link() ?>'s request.
        Be careful when making changes!</strong>
    </div>
<?php } ?>

    <div class="box pad">
        <form action="" method="post" id="request_form" onsubmit="Calculate();">
            <div>
<?php if (!$newRequest) { ?>
                <input type="hidden" name="requestid" value="<?=$requestId?>" />
<?php } ?>
                <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                <input type="hidden" name="action" value="<?=($newRequest ? 'takenew' : 'takeedit')?>" />
            </div>

            <table class="layout">
                <tr>
                    <td colspan="2" class="center">Please make sure your request follows <a href="rules.php?p=requests">the request rules</a>!
<?php if (isset($Err)) { ?>
            <div class="save_message error"><?= $Err ?></div>
<?php } ?>
                    </td>
                </tr>
<?php if ($newRequest || $CanEdit) { ?>
                <tr>
                    <td class="label">
                        Type
                    </td>
                    <td>
                        <select id="categories" name="type" onchange="Categories();">
<?php    foreach (CATEGORY as $Cat) { ?>
                            <option value="<?=$Cat?>"<?= $categoryName === $Cat ? ' selected="selected"' : '' ?>><?=$Cat?></option>
<?php    } ?>
                        </select>
                    </td>
                </tr>
                <tr id="releasetypes_tr">
                    <td class="label">Release type</td>
                    <td>
                        <select id="releasetype" name="releasetype">
                            <option value="0">---</option>
<?php       foreach ($releaseTypes as $Key => $Val) { ?>
                            <option value="<?=$Key?>"<?=!empty($releaseType) ? ($Key == $releaseType ? ' selected="selected"' : '') : '' ?>><?=$Val?></option>
<?php       } ?>
                        </select>
                    </td>
                </tr>
                <tr id="artist_tr">
                    <td class="label">Artist(s)</td>
                    <td id="artistfields">
                        <p id="vawarning" class="hidden">Please use the multiple artists feature rather than adding "Various Artists" as an artist; read <a href="wiki.php?action=article&amp;id=64" target="_blank">this</a> for more information.</p>
<?php
        if (!empty($ArtistForm)) {
            $First = true;
            $cnt = 0;
            foreach ($ArtistForm as $Importance => $ArtistNames) {
                foreach ($ArtistNames as $Artist) {
?>
                        <input type="text" id="artist_<?=$cnt ?>" name="artists[]"<?=
                            $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> size="45" value="<?=display_str($Artist['name']) ?>" />
                        <select id="importance" name="importance[]">
                            <option value="<?= ARTIST_MAIN ?>"<?=($Importance == '<?= ARTIST_MAIN ?>' ? ' selected="selected"' : '')?>>Main</option>
                            <option value="<?= ARTIST_GUEST ?>"<?=($Importance == '<?= ARTIST_GUEST ?>' ? ' selected="selected"' : '')?>>Guest</option>
                            <option value="<?= ARTIST_COMPOSER ?>"<?=($Importance == '<?= ARTIST_COMPOSER ?>' ? ' selected="selected"' : '')?>>Composer</option>
                            <option value="<?= ARTIST_CONDUCTOR ?>"<?=($Importance == '<?= ARTIST_CONDUCTOR ?>' ? ' selected="selected"' : '')?>>Conductor</option>
                            <option value="<?= ARTIST_DJ ?>"<?=($Importance == '<?= ARTIST_DJ ?>' ? ' selected="selected"' : '')?>>DJ / Compiler</option>
                            <option value="<?= ARTIST_REMIXER ?>"<?=($Importance == '<?= ARTIST_REMIXER ?>' ? ' selected="selected"' : '')?>>Remixer</option>
                            <option value="<?= ARTIST_PRODUCER ?>"<?=($Importance == '<?= ARTIST_PRODUCER ?>' ? ' selected="selected"' : '')?>>Producer</option>
                            <option value="<?= ARTIST_ARRANGER ?>"<?=($Importance == '<?= ARTIST_ARRANGER ?>' ? ' selected="selected"' : '')?>>Arranger</option>
                        </select>
                        <?php if ($First) { ?><a href="#" onclick="AddArtistField(); return false;" class="brackets">+</a> <a href="#" onclick="RemoveArtistField(); return false;" class="brackets">&minus;</a><?php } $First = false; ?>
                        <br />
<?php
                    $cnt++;
                }
            }
        } else {
?>
                        <input type="text" id="artist_0" name="artists[]"<?=
                            $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> size="45" onblur="CheckVA();" />
                        <select id="importance" name="importance[]">
                            <option value="<?= ARTIST_MAIN ?>">Main</option>
                            <option value="<?= ARTIST_GUEST ?>">Guest</option>
                            <option value="<?= ARTIST_COMPOSER ?>">Composer</option>
                            <option value="<?= ARTIST_CONDUCTOR ?>">Conductor</option>
                            <option value="<?= ARTIST_DJ ?>">DJ / Compiler</option>
                            <option value="<?= ARTIST_REMIXER ?>">Remixer</option>
                            <option value="<?= ARTIST_PRODUCER ?>">Producer</option>
                            <option value="<?= ARTIST_ARRANGER ?>">Arranger</option>
                        </select>
                        <a href="#" onclick="AddArtistField(); return false;" class="brackets">+</a> <a href="#" onclick="RemoveArtistField(); return false;" class="brackets">&minus;</a>
<?php   } ?>
                    </td>
                </tr>
                <tr>
                    <td class="label">Title</td>
                    <td>
                        <input type="text" name="title" size="45" value="<?= $title ?>" />
                    </td>
                </tr>
                <tr id="recordlabel_tr">
                    <td class="label">Record label</td>
                    <td>
                        <input type="text" name="recordlabel" size="45" value="<?= $recordLabel ?>" />
                    </td>
                </tr>
                <tr id="cataloguenumber_tr">
                    <td class="label">Catalogue number</td>
                    <td>
                        <input type="text" name="cataloguenumber" size="15" value="<?= $catalogueNumber ?>" />
                    </td>
                </tr>
                <tr id="oclc_tr">
                    <td class="label">WorldCat (OCLC) ID</td>
                    <td>
                        <input type="text" name="oclc" size="15" value="<?= $oclc ?>" />
                    </td>
                </tr>
<?php } ?>
                <tr id="year_tr">
                    <td class="label">Year</td>
                    <td>
                        <input type="text" name="year" size="5" value="<?= $year ?>" />
                    </td>
                </tr>
<?php if ($newRequest || $CanEdit) { ?>
                <tr id="image_tr">
                    <td class="label">Image</td>
                    <td>
                        <input type="text" name="image" size="45" value="<?= $image ?>" />
<?php       if (IMAGE_HOST_BANNED) { ?>
                        <br /><b>Images hosted on <strong class="important_text"><?= implode(', ', IMAGE_HOST_BANNED)
                            ?> are not allowed</strong>, please rehost first on one of <?= implode(', ', IMAGE_HOST_RECOMMENDED) ?>.</b>
<?php       } ?>
                    </td>
                </tr>
<?php   } ?>
                <tr>
                    <td class="label">Tags</td>
                    <td>
                        <select id="genre_tags" name="genre_tags" onchange="add_tag(); return false;">
                            <option>---</option>
<?php   foreach ($GenreTags as $Genre) { ?>
                            <option value="<?= display_str($Genre) ?>"><?= display_str($Genre) ?></option>
<?php   } ?>
                        </select>
                        <input type="text" id="tags" name="tags" size="45" value="<?= display_str($tags) ?>"<?=
                            $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />
                        <br />
                        Tags should be comma-separated, and you should use a period (".") to separate words inside a tag&#8202;&mdash;&#8202;e.g. "<strong class="important_text_alt">hip.hop</strong>".
                        <br /><br />
                        There is a list of official tags to the left of the text box. Please use these tags instead of "unofficial" tags (e.g. use the official "<strong class="important_text_alt">drum.and.bass</strong>" tag, instead of an unofficial "<strong class="important_text">dnb</strong>" tag.).
                    </td>
                </tr>
<?php   if ($newRequest || $CanEdit || $ownRequest) { ?>
                <tr id="formats_tr">
                    <td class="label">Allowed formats</td>
                    <td>
                        <input type="checkbox" name="all_formats" id="toggle_formats" onchange="Toggle('formats', <?=($newRequest ? 1 : 0)?>);"<?=!empty($FormatArray) && (count($FormatArray) === count(FORMAT)) ? ' checked="checked"' : ''; ?> /><label for="toggle_formats"> All</label>
                        <span style="float: right;"><strong>NB: You cannot require a log or cue unless FLAC is an allowed format</strong></span>
<?php
            foreach (FORMAT as $Key => $Val) {
                if ($Key % 8 === 0) {
                    echo '<br />';
                }
?>
                        <input type="checkbox" name="formats[]" value="<?=$Key?>" onchange="ToggleLogCue(); if (!this.checked) { $('#toggle_formats').raw().checked = false; }" id="format_<?=$Key?>"
                            <?=(!empty($FormatArray) && in_array($Key, $FormatArray) ? ' checked="checked"' : '')?> /><label for="format_<?=$Key?>"> <?=$Val?></label>
<?php       } ?>
                    </td>
                </tr>
                <tr id="bitrates_tr">
                    <td class="label">Allowed bitrates</td>
                    <td>
                        <input type="checkbox" name="all_bitrates" id="toggle_bitrates" onchange="Toggle('bitrates', <?=($newRequest ? 1 : 0)?>);"<?=(!empty($EncodingArray) && (count($EncodingArray) === count(ENCODING)) ? ' checked="checked"' : '')?> /><label for="toggle_bitrates"> All</label>
<?php
            foreach (ENCODING as $Key => $Val) {
                if ($Key % 8 === 0) {
                    echo '<br />';
                }
?>
                        <input type="checkbox" name="bitrates[]" value="<?=$Key?>" id="bitrate_<?=$Key?>"
                            <?=(!empty($EncodingArray) && in_array($Key, $EncodingArray) ? ' checked="checked" ' : '')?>
                        onchange="if (!this.checked) { $('#toggle_bitrates').raw().checked = false; }" /><label for="bitrate_<?=$Key?>"> <?=$Val?></label>
<?php       } ?>
                    </td>
                </tr>
                <tr id="media_tr">
                    <td class="label">Allowed media</td>
                    <td>
                        <input type="checkbox" name="all_media" id="toggle_media" onchange="Toggle('media', <?=($newRequest ? 1 : 0)?>);"<?=(!empty($MediaArray) && (count($MediaArray) === count(MEDIA)) ? ' checked="checked"' : '')?> /><label for="toggle_media"> All</label>
<?php
            foreach (MEDIA as $Key => $Val) {
                if ($Key % 8 === 0) {
                    echo '<br />';
                }
?>
                        <input type="checkbox" name="media[]" value="<?=$Key?>" id="media_<?=$Key?>"
                            <?=(!empty($MediaArray) && in_array($Key, $MediaArray) ? ' checked="checked" ' : '')?>
                        onchange="ToggleLogCue(); if (!this.checked) { $('#toggle_media').raw().checked = false; }" /><label for="media_<?=$Key?>"> <?=$Val?></label>
<?php   } ?>
                    </td>
                </tr>
                <tr id="logcue_tr" class="hidden">
                    <td class="label">Log / Checksum / Cue<br />(CD FLAC only)</td>
                    <td>
                        <input type="checkbox" id="needlog" name="needlog" onchange="ToggleLogScore()" <?=(!empty($NeedLog) ? 'checked="checked" ' : '')?>/><label for="needlog"> Require log</label>
                        <span id="minlogscore_span" class="hidden">&nbsp;<input type="text" name="minlogscore" id="minlogscore" size="4" value="<?=(!empty($MinLogScore) ? $MinLogScore : '')?>" /> Minimum log score</span>
                        <br />
                        <input type="checkbox" id="needcksum" name="needcksum"<?= ($Checksum ?? false) ? ' checked="checked" ' : ''?>/><label for="needcksum"> Require checksum</label>
                        <br />
                        <input type="checkbox" id="needcue" name="needcue" <?=(!empty($NeedCue) ? 'checked="checked" ' : '')?>/><label for="needcue"> Require cue file</label>
                    </td>
                </tr>
<?php  } ?>
                <tr>
                    <td class="label">Description</td>
                    <td>
                        <textarea name="description" cols="70" rows="7"><?= $description ?></textarea> <br />
                    </td>
                </tr>
<?php    if ($Viewer->permitted('site_moderate_requests')) { ?>
                <tr>
                    <td class="label">Torrent group</td>
                    <td>
                        <?=SITE_URL?>/torrents.php?id=<input type="text" name="groupid" value="<?=$GroupID?>" size="15" /><br />
                        If this request matches a torrent group <span style="font-weight: bold;">already existing</span> on the site, please indicate that here.
                    </td>
                </tr>
<?php    } elseif (isset($GroupID) && ($categoryId == CATEGORY_MUSIC)) { ?>
                <tr>
                    <td class="label">Torrent group</td>
                    <td>
                        <a href="torrents.php?id=<?=$GroupID?>"><?=SITE_URL?>/torrents.php?id=<?=$GroupID?></a><br />
                        This request <?=($newRequest ? 'will be' : 'is')?> associated with the above torrent group.
<?php        if (!$newRequest) {    ?>
                        If this is incorrect, please <a href="reports.php?action=report&amp;type=request&amp;id=<?=$requestId?>">report this request</a> so that staff can fix it.
<?php         }    ?>
                        <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                    </td>
                </tr>
<?php    }
    if ($newRequest) { ?>
                <tr id="voting">
                    <td class="label">Bounty (MiB)</td>
                    <td>
                        <input type="text" id="amount_box" size="8" value="<?= !empty($Bounty) ? $Bounty : REQUEST_MIN ?>" />
                        <select id="unit" name="unit" onchange="Calculate();">
                            <option value="mb"<?=(!empty($_POST['unit']) && $_POST['unit'] === 'mb' ? ' selected="selected"' : '') ?>>MiB</option>
                            <option value="gb"<?=(!empty($_POST['unit']) && $_POST['unit'] === 'gb' ? ' selected="selected"' : '') ?>>GiB</option>
                        </select>
                        <?= REQUEST_TAX > 0 ? "<strong><?= REQUEST_TAX * 100 ?>% of this is deducted as tax by the system.</strong>" : '' ?>
                        <p>Bounty must be greater than or equal to <?= REQUEST_MIN ?> MiB.</p>
                    </td>
                </tr>
                <tr>
                    <td class="label">Bounty information</td>
                    <td>
                        <input type="hidden" id="amount" name="amount" value="<?= !empty($Bounty) ? $Bounty : REQUEST_MIN * 1024 * 1024 ?>" />
                        <input type="hidden" id="current_uploaded" value="<?=$Viewer->uploadedSize()?>" />
                        <input type="hidden" id="current_downloaded" value="<?=$Viewer->downloadedSize()?>" />
                        <input type='hidden' id='request_tax' value="<?=REQUEST_TAX?>" />
                        <?= REQUEST_TAX > 0
                            ? 'Bounty after tax: <strong><span id="bounty_after_tax">' . sprintf("%0.2f", 100 * (1 - REQUEST_TAX)) . ' MiB</span></strong><br />'
                            : '<span id="bounty_after_tax" style="display: none;">' . sprintf("%0.2f", 100 * (1 - REQUEST_TAX)) . ' MiB</span>'
                        ?>
                        If you add the entered <strong><span id="new_bounty"><?= REQUEST_MIN ?>.00 MiB</span></strong> of bounty, your new stats will be: <br />
                        Uploaded: <span id="new_uploaded"><?= byte_format($Viewer->uploadedSize()) ?></span><br />
                        Ratio: <span id="new_ratio"><?= ratio_html($Viewer->uploadedSize(), $Viewer->downloadedSize()) ?></span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" class="center">
                        <input type="submit" id="button" value="Create request" disabled="disabled" />
                    </td>
                </tr>
<?php    } else { ?>
                <tr>
                    <td colspan="2" class="center">
                        <input type="submit" id="button" value="Edit request" />
                    </td>
                </tr>
<?php    } ?>
            </table>
        </form>
        <script type="text/javascript">ToggleLogCue();<?=$newRequest ? " Calculate();" : '' ?></script>
        <script type="text/javascript">Categories();</script>
    </div>
</div>
<?php
View::show_footer();
