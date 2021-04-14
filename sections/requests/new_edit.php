<?php

/*
 * Yeah, that's right, edit and new are the same place.
 * It makes the page uglier to read but ultimately better as the alternative
 * means maintaining 2 copies of almost identical files.
 */

$NewRequest = $_GET['action'] === 'new';
if (!$NewRequest) {
    $RequestID = $_GET['id'];
    if (!intval($RequestID)) {
        error(404);
    }
}

if ($NewRequest && ($LoggedUser['BytesUploaded'] < 250 * 1024 * 1024 || !check_perms('site_submit_requests'))) {
    error('You do not have enough uploaded to make a request.');
}

$RequestTaxPercent = ($RequestTax * 100);
$user = new Gazelle\User($LoggedUser['ID']);

if (!$NewRequest) {
    if (empty($ReturnEdit)) {

        $Request = Requests::get_request($RequestID);
        if ($Request === false) {
            error(404);
        }

        // Define these variables to simplify _GET['groupid'] requests later on
        $CategoryID = $Request['CategoryID'];
        $Title = $Request['Title'];
        $Year = $Request['Year'];
        $Image = $Request['Image'];
        $ReleaseType = $Request['ReleaseType'];
        $GroupID = $Request['GroupID'];

        $VoteArray = Requests::get_votes_array($RequestID);
        $VoteCount = count($VoteArray['Voters']);

        $LogCue = $Request['LogCue'];
        $NeedCue = (strpos($LogCue, 'Cue') !== false);
        $NeedLog = (strpos($LogCue, 'Log') !== false);
        if ($NeedLog) {
            if (strpos($LogCue, '%') !== false) {
                preg_match('/\d+/', $LogCue, $Matches);
                $MinLogScore = (int)$Matches[0];
            }
        }
        $Checksum = $Request['Checksum'] ? 1 : 0;

        $IsFilled = !empty($Request['TorrentID']);
        $CategoryName = $Categories[$CategoryID - 1];

        $CanEdit = ((!$IsFilled && $LoggedUser['ID'] == $Request['UserID'] && $VoteCount < 2) || check_perms('site_moderate_requests'));
        if (!$CanEdit) {
            error(403);
        }

        if ($CategoryName === 'Music') {
            $ArtistForm = Requests::get_artists($RequestID);

            $BitrateArray = [];
            if ($Request['BitrateList'] == 'Any') {
                $BitrateArray = array_keys($Bitrates);
            } else {
                $BitrateArray = array_keys(array_intersect($Bitrates, explode('|', $Request['BitrateList'])));
            }

            $FormatArray = [];
            if ($Request['FormatList'] == 'Any') {
                $FormatArray = array_keys($Formats);
            } else {
                foreach ($Formats as $Key => $Val) {
                    if (strpos($Request['FormatList'], $Val) !== false) {
                        $FormatArray[] = $Key;
                    }
                }
            }

            $MediaArray = [];
            if ($Request['MediaList'] == 'Any') {
                $MediaArray = array_keys($Media);
            } else {
                $MediaTemp = explode('|', $Request['MediaList']);
                foreach ($Media as $Key => $Val) {
                    if (in_array($Val, $MediaTemp)) {
                        $MediaArray[] = $Key;
                    }
                }
            }
        }

        $Tags = implode(', ', $Request['Tags']);
    }
}

if ($NewRequest && !empty($_GET['artistid']) && intval($_GET['artistid'])) {
    $DB->prepared_query('
        SELECT Name
        FROM artists_group
        WHERE artistid = ?
        LIMIT 1',
        $_GET['artistid']
    );
    list($ArtistName) = $DB->next_record();
    $ArtistForm = [
        1 => [['name' => trim($ArtistName)]],
        2 => [],
        3 => []
    ];
} elseif ($NewRequest && !empty($_GET['groupid']) && intval($_GET['groupid'])) {
    $ArtistForm = Artists::get_artist($_GET['groupid']);
    $DB->prepared_query("
        SELECT
            tg.Name,
            tg.Year,
            tg.ReleaseType,
            tg.WikiImage,
            GROUP_CONCAT(t.Name SEPARATOR ', '),
            tg.CategoryID
        FROM torrents_group AS tg
        INNER JOIN torrents_tags AS tt ON (tt.GroupID = tg.ID)
        INNER JOIN tags AS t ON (t.ID = tt.TagID)
        WHERE tg.ID = ?",
        $_GET['groupid']
    );
    if (list($Title, $Year, $ReleaseType, $Image, $Tags, $CategoryID) = $DB->next_record()) {
        $GroupID = trim($_REQUEST['groupid']);
    }
}

$tagMan = new Gazelle\Manager\Tag;
$GenreTags = $tagMan->genreList();

View::show_header(($NewRequest ? 'Create a request' : 'Edit a request'), 'requests,form_validate');
?>
<div class="thin">
    <div class="header">
        <h2><?=($NewRequest ? 'Create a request' : 'Edit a request')?></h2>
    </div>

    <div class="box pad">
        <form action="" method="post" id="request_form" onsubmit="Calculate();">
            <div>
<?php    if (!$NewRequest) { ?>
                <input type="hidden" name="requestid" value="<?=$RequestID?>" />
<?php    } ?>
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="hidden" name="action" value="<?=($NewRequest ? 'takenew' : 'takeedit')?>" />
            </div>

            <table class="layout">
                <tr>
                    <td colspan="2" class="center">Please make sure your request follows <a href="rules.php?p=requests">the request rules</a>!</td>
                </tr>
<?php    if ($NewRequest || $CanEdit) { ?>
                <tr>
                    <td class="label">
                        Type
                    </td>
                    <td>
                        <select id="categories" name="type" onchange="Categories();">
<?php        foreach ($Categories as $Cat) { ?>
                            <option value="<?=$Cat?>"<?=(!empty($CategoryName) && ($CategoryName === $Cat) ? ' selected="selected"' : '')?>><?=$Cat?></option>
<?php        } ?>
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
                            $user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> size="45" value="<?=display_str($Artist['name']) ?>" />
                        <select id="importance" name="importance[]">
                            <option value="1"<?=($Importance == '1' ? ' selected="selected"' : '')?>>Main</option>
                            <option value="2"<?=($Importance == '2' ? ' selected="selected"' : '')?>>Guest</option>
                            <option value="4"<?=($Importance == '4' ? ' selected="selected"' : '')?>>Composer</option>
                            <option value="5"<?=($Importance == '5' ? ' selected="selected"' : '')?>>Conductor</option>
                            <option value="6"<?=($Importance == '6' ? ' selected="selected"' : '')?>>DJ / Compiler</option>
                            <option value="3"<?=($Importance == '3' ? ' selected="selected"' : '')?>>Remixer</option>
                            <option value="7"<?=($Importance == '7' ? ' selected="selected"' : '')?>>Producer</option>
                            <option value="8"<?=($Importance == '8' ? ' selected="selected"' : '')?>>Arranger</option>
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
                            $user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> size="45" onblur="CheckVA();" />
                        <select id="importance" name="importance[]">
                            <option value="1">Main</option>
                            <option value="2">Guest</option>
                            <option value="4">Composer</option>
                            <option value="5">Conductor</option>
                            <option value="6">DJ / Compiler</option>
                            <option value="3">Remixer</option>
                            <option value="7">Producer</option>
                            <option value="8">Arranger</option>
                        </select>
                        <a href="#" onclick="AddArtistField(); return false;" class="brackets">+</a> <a href="#" onclick="RemoveArtistField(); return false;" class="brackets">&minus;</a>
<?php
        }
?>
                    </td>
                </tr>
                <tr>
                    <td class="label">Title</td>
                    <td>
                        <input type="text" name="title" size="45" value="<?=(!empty($Title) ? $Title : '')?>" />
                    </td>
                </tr>
                <tr id="recordlabel_tr">
                    <td class="label">Record label</td>
                    <td>
                        <input type="text" name="recordlabel" size="45" value="<?=(!empty($Request['RecordLabel']) ? $Request['RecordLabel'] : '')?>" />
                    </td>
                </tr>
                <tr id="cataloguenumber_tr">
                    <td class="label">Catalogue number</td>
                    <td>
                        <input type="text" name="cataloguenumber" size="15" value="<?=(!empty($Request['CatalogueNumber']) ? $Request['CatalogueNumber'] : '')?>" />
                    </td>
                </tr>
                <tr id="oclc_tr">
                    <td class="label">WorldCat (OCLC) ID</td>
                    <td>
                        <input type="text" name="oclc" size="15" value="<?=(!empty($Request['OCLC']) ? $Request['OCLC'] : '')?>" />
                    </td>
                </tr>
<?php    } ?>
                <tr id="year_tr">
                    <td class="label">Year</td>
                    <td>
                        <input type="text" name="year" size="5" value="<?=(!empty($Year) ? $Year : '')?>" />
                    </td>
                </tr>
<?php    if ($NewRequest || $CanEdit) { ?>
                <tr id="image_tr">
                    <td class="label">Image</td>
                    <td>
                        <input type="text" name="image" size="45" value="<?=(!empty($Image) ? $Image : '')?>" />
<?php if (IMAGE_HOST_BANNED) { ?>
                        <br /><b>Images hosted on <strong class="important_text"><?= implode(', ', IMAGE_HOST_BANNED)
                            ?> are not allowed</strong>, please rehost first on one of <?= implode(', ', IMAGE_HOST_RECOMMENDED) ?>.</b>
<?php } ?>
                    </td>
                </tr>
<?php    } ?>
                <tr>
                    <td class="label">Tags</td>
                    <td>
                        <select id="genre_tags" name="genre_tags" onchange="add_tag(); return false;">
                            <option>---</option>
<?php    foreach ($GenreTags as $Genre) { ?>
                            <option value="<?= display_str($Genre) ?>"><?= display_str($Genre) ?></option>
<?php    } ?>
                        </select>
                        <input type="text" id="tags" name="tags" size="45" value="<?= empty($Tags) ? '' : display_str($Tags) ?>"<?=
                            $user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />
                        <br />
                        Tags should be comma-separated, and you should use a period (".") to separate words inside a tag&#8202;&mdash;&#8202;e.g. "<strong class="important_text_alt">hip.hop</strong>".
                        <br /><br />
                        There is a list of official tags to the left of the text box. Please use these tags instead of "unofficial" tags (e.g. use the official "<strong class="important_text_alt">drum.and.bass</strong>" tag, instead of an unofficial "<strong class="important_text">dnb</strong>" tag.).
                    </td>
                </tr>
<?php    if ($NewRequest || $CanEdit) { ?>
                <tr id="releasetypes_tr">
                    <td class="label">Release type</td>
                    <td>
                        <select id="releasetype" name="releasetype">
                            <option value="0">---</option>
<?php
        $releaseTypes = (new Gazelle\ReleaseType)->list();
        foreach ($releaseTypes as $Key => $Val) {
?>                            <option value="<?=$Key?>"<?=!empty($ReleaseType) ? ($Key == $ReleaseType ? ' selected="selected"' : '') : '' ?>><?=$Val?></option>
<?php   } ?>
                        </select>
                    </td>
                </tr>
                <tr id="formats_tr">
                    <td class="label">Allowed formats</td>
                    <td>
                        <input type="checkbox" name="all_formats" id="toggle_formats" onchange="Toggle('formats', <?=($NewRequest ? 1 : 0)?>);"<?=!empty($FormatArray) && (count($FormatArray) === count($Formats)) ? ' checked="checked"' : ''; ?> /><label for="toggle_formats"> All</label>
                        <span style="float: right;"><strong>NB: You cannot require a log or cue unless FLAC is an allowed format</strong></span>
<?php        foreach ($Formats as $Key => $Val) {
            if ($Key % 8 === 0) {
                echo '<br />';
            } ?>
                        <input type="checkbox" name="formats[]" value="<?=$Key?>" onchange="ToggleLogCue(); if (!this.checked) { $('#toggle_formats').raw().checked = false; }" id="format_<?=$Key?>"
                            <?=(!empty($FormatArray) && in_array($Key, $FormatArray) ? ' checked="checked"' : '')?> /><label for="format_<?=$Key?>"> <?=$Val?></label>
<?php        } ?>
                    </td>
                </tr>
                <tr id="bitrates_tr">
                    <td class="label">Allowed bitrates</td>
                    <td>
                        <input type="checkbox" name="all_bitrates" id="toggle_bitrates" onchange="Toggle('bitrates', <?=($NewRequest ? 1 : 0)?>);"<?=(!empty($BitrateArray) && (count($BitrateArray) === count($Bitrates)) ? ' checked="checked"' : '')?> /><label for="toggle_bitrates"> All</label>
<?php        foreach ($Bitrates as $Key => $Val) {
            if ($Key % 8 === 0) {
                echo '<br />';
            } ?>
                        <input type="checkbox" name="bitrates[]" value="<?=$Key?>" id="bitrate_<?=$Key?>"
                            <?=(!empty($BitrateArray) && in_array($Key, $BitrateArray) ? ' checked="checked" ' : '')?>
                        onchange="if (!this.checked) { $('#toggle_bitrates').raw().checked = false; }" /><label for="bitrate_<?=$Key?>"> <?=$Val?></label>
<?php        } ?>
                    </td>
                </tr>
                <tr id="media_tr">
                    <td class="label">Allowed media</td>
                    <td>
                        <input type="checkbox" name="all_media" id="toggle_media" onchange="Toggle('media', <?=($NewRequest ? 1 : 0)?>);"<?=(!empty($MediaArray) && (count($MediaArray) === count($Media)) ? ' checked="checked"' : '')?> /><label for="toggle_media"> All</label>
<?php        foreach ($Media as $Key => $Val) {
            if ($Key % 8 === 0) {
                echo '<br />';
            } ?>
                        <input type="checkbox" name="media[]" value="<?=$Key?>" id="media_<?=$Key?>"
                            <?=(!empty($MediaArray) && in_array($Key, $MediaArray) ? ' checked="checked" ' : '')?>
                        onchange="ToggleLogCue(); if (!this.checked) { $('#toggle_media').raw().checked = false; }" /><label for="media_<?=$Key?>"> <?=$Val?></label>
<?php        } ?>
                    </td>
                </tr>
                <tr id="logcue_tr" class="hidden">
                    <td class="label">Log / Checksum / Cue<br />(CD FLAC only)</td>
                    <td>
                        <input type="checkbox" id="needlog" name="needlog" onchange="ToggleLogScore()" <?=(!empty($NeedLog) ? 'checked="checked" ' : '')?>/><label for="needlog"> Require log</label>
                        <span id="minlogscore_span" class="hidden">&nbsp;<input type="text" name="minlogscore" id="minlogscore" size="4" value="<?=(!empty($MinLogScore) ? $MinLogScore : '')?>" /> Minimum log score</span>
                        <br />
                        <input type="checkbox" id="needcksum" name="needcksum"<?=$Checksum ? ' checked="checked" ' : ''?>/><label for="needcksum"> Require checksum</label>
                        <br />
                        <input type="checkbox" id="needcue" name="needcue" <?=(!empty($NeedCue) ? 'checked="checked" ' : '')?>/><label for="needcue"> Require cue file</label>
                    </td>
                </tr>
<?php    } ?>
                <tr>
                    <td class="label">Description</td>
                    <td>
                        <textarea name="description" cols="70" rows="7"><?=(!empty($Request['Description']) ? $Request['Description'] : '')?></textarea> <br />
                    </td>
                </tr>
<?php    if (check_perms('site_moderate_requests')) { ?>
                <tr>
                    <td class="label">Torrent group</td>
                    <td>
                        <?=SITE_URL?>/torrents.php?id=<input type="text" name="groupid" value="<?=$GroupID?>" size="15" /><br />
                        If this request matches a torrent group <span style="font-weight: bold;">already existing</span> on the site, please indicate that here.
                    </td>
                </tr>
<?php    } elseif ($GroupID && ($CategoryID == 1)) { ?>
                <tr>
                    <td class="label">Torrent group</td>
                    <td>
                        <a href="torrents.php?id=<?=$GroupID?>"><?=SITE_URL?>/torrents.php?id=<?=$GroupID?></a><br />
                        This request <?=($NewRequest ? 'will be' : 'is')?> associated with the above torrent group.
<?php        if (!$NewRequest) {    ?>
                        If this is incorrect, please <a href="reports.php?action=report&amp;type=request&amp;id=<?=$RequestID?>">report this request</a> so that staff can fix it.
<?php         }    ?>
                        <input type="hidden" name="groupid" value="<?=$GroupID?>" />
                    </td>
                </tr>
<?php    }
    if ($NewRequest) { ?>
                <tr id="voting">
                    <td class="label">Bounty (MiB)</td>
                    <td>
                        <input type="text" id="amount_box" size="8" value="<?=(!empty($Bounty) ? $Bounty : '100')?>" />
                        <select id="unit" name="unit" onchange="Calculate();">
                            <option value="mb"<?=(!empty($_POST['unit']) && $_POST['unit'] === 'mb' ? ' selected="selected"' : '') ?>>MiB</option>
                            <option value="gb"<?=(!empty($_POST['unit']) && $_POST['unit'] === 'gb' ? ' selected="selected"' : '') ?>>GiB</option>
                        </select>
                        <?= $RequestTax > 0 ? "<strong>{$RequestTaxPercent}% of this is deducted as tax by the system.</strong>" : '' ?>
                        <p>Bounty must be greater than or equal to 100 MiB.</p>
                    </td>
                </tr>
                <tr>
                    <td class="label">Bounty information</td>
                    <td>
                        <input type="hidden" id="amount" name="amount" value="<?=(!empty($Bounty) ? $Bounty : '100')?>" />
                        <input type="hidden" id="current_uploaded" value="<?=$LoggedUser['BytesUploaded']?>" />
                        <input type="hidden" id="current_downloaded" value="<?=$LoggedUser['BytesDownloaded']?>" />
                        <input type='hidden' id='request_tax' value="<?=$RequestTax?>" />
                        <?= $RequestTax > 0
                            ? 'Bounty after tax: <strong><span id="bounty_after_tax"><?=sprintf("%0.2f", 100 * (1 - $RequestTax))?> MiB</span></strong><br />'
                            : '<span id="bounty_after_tax" style="display: none;"><?=sprintf("%0.2f", 100 * (1 - $RequestTax))?> MiB</span>'
                        ?>
                        If you add the entered <strong><span id="new_bounty">100.00 MiB</span></strong> of bounty, your new stats will be: <br />
                        Uploaded: <span id="new_uploaded"><?=Format::get_size($LoggedUser['BytesUploaded'])?></span><br />
                        Ratio: <span id="new_ratio"><?=Format::get_ratio_html($LoggedUser['BytesUploaded'], $LoggedUser['BytesDownloaded'])?></span>
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
        <script type="text/javascript">ToggleLogCue();<?=$NewRequest ? " Calculate();" : '' ?></script>
        <script type="text/javascript">Categories();</script>
    </div>
</div>
<?php
View::show_footer();
