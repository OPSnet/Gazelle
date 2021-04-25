<?php

/********************************************************************************
 ************ Torrent form class *************** upload.php and torrents.php ****
 ********************************************************************************
 ** This class is used to create both the upload form, and the 'edit torrent'  **
 ** form. It is broken down into several functions - head(), foot(),           **
 ** music_form() [music], audiobook_form() [Audiobooks and comedy], and        **
 ** simple_form() [everything else].                                           **
 **                                                                            **
 ** When it is called from the edit page, the forms are shortened quite a bit. **
 **                                                                            **
 ********************************************************************************/

use OrpheusNET\Logchecker\Logchecker;

class TORRENT_FORM {
    var $UploadForm = '';
    var $Categories = [];
    var $Formats = [];
    var $Bitrates = [];
    var $Media = [];
    var $NewTorrent = false;
    var $Torrent = [];
    var $Error = false;
    var $TorrentID = false;
    var $Disabled = '';
    var $DisabledFlag = false;

    const TORRENT_INPUT_ACCEPT = ['application/x-bittorrent', '.torrent'];
    const JSON_INPUT_ACCEPT = ['application/json', '.json'];

    function __construct($Torrent = false, $Error = false, $NewTorrent = true) {

        $this->NewTorrent = $NewTorrent;
        $this->Torrent = $Torrent;
        $this->Error = $Error;

        global $UploadForm, $Categories, $Formats, $Bitrates, $Media, $TorrentID;

        $this->UploadForm = $UploadForm;
        $this->Categories = $Categories;
        $this->Formats = $Formats;
        $this->Bitrates = $Bitrates;
        $this->Media = $Media;
        $this->TorrentID = $TorrentID;

        if ($this->Torrent && $this->Torrent['GroupID']) {
            $this->Disabled = ' disabled="disabled"';
            $this->DisabledFlag = true;
        }
    }

    function head() {
?>

<div class="thin">
<?php   if ($this->NewTorrent) { ?>
    <div style="text-align: center;">
        Your personal announce URL is:<br />
        <div style="margin: 0 auto;"><input type="text" value="<?= (new \Gazelle\User(G::$LoggedUser['ID']))->announceUrl() ?>" size="71" onclick="this.select();" readonly="readonly" /></div>
    </div>
<?php
        }
        if ($this->Error) {
?>
    <p style="color: red; text-align: center;"><?= $this->Error ?></p>
<?php   } ?>
    <form class="create_form" name="torrent" action="" enctype="multipart/form-data" method="post" id="upload_table" onsubmit="$('#post').raw().disabled = 'disabled';">
        <div>
            <input type="hidden" id="torrent-new" name="torrent-new" value="<?= $this->NewTorrent ? 1 : 0 ?>" />
            <input type="hidden" name="submit" value="true" />
            <input type="hidden" name="auth" value="<?=G::$LoggedUser['AuthKey']?>" />
<?php       if (!$this->NewTorrent) { ?>
                <input type="hidden" name="action" value="takeedit" />
                <input type="hidden" name="torrentid" value="<?=display_str($this->TorrentID)?>" />
                <input type="hidden" name="type" id="edittype" value="<?=display_str($this->Torrent['CategoryID'])?>" />
<?php
            } else {
                if ($this->Torrent && $this->Torrent['GroupID']) {
?>
                <input type="hidden" name="groupid" value="<?=display_str($this->Torrent['GroupID'])?>" />
                <input type="hidden" name="type" value="<?=array_search($this->UploadForm, $this->Categories)?>" />
<?php
                }
                if ($this->Torrent && $this->Torrent['RequestID']) {
?>
                <input type="hidden" name="requestid" value="<?=display_str($this->Torrent['RequestID'])?>" />
<?php
                }
            }
?>
        </div>
<?php   if ($this->NewTorrent) { ?>
        <table cellpadding="3" cellspacing="1" border="0" class="layout border" width="100%">
            <tr>
                <td class="label">Torrent file:</td>
                <td>
                    <input id="file" type="file" name="file_input" size="50" accept="<?= implode(',', self::TORRENT_INPUT_ACCEPT); ?>" />
                </td>
            </tr>
            <tr>
                <td class="label">JSON file:</td>
                <td>
                    <input type="file" id="torrent-json-file" accept="<?= implode(',', self::JSON_INPUT_ACCEPT); ?>" />
                    <br />(If you are not cross-seeding from another site, you can safely leave this field empty).
                </td>
            </tr>
            <tr>
                <td class="label">Type:</td>
                <td>
                    <select id="categories" name="type" onchange="Categories()"<?=$this->Disabled?>>
<?php       foreach ($this->Categories as $Index => $Cat) { ?>
                        <option value="<?= $Index ?>"<?= $Cat == $this->Torrent['CategoryName'] ? ' selected="selected"' : '' ?>><?= display_str($Cat) ?></option>
<?php       } ?>
                    </select>
                </td>
            </tr>
        </table>
<?php   }/*if*/ ?>
        <div id="dynamic_form">
<?php
    } // function head

    function foot() {
        $Torrent = $this->Torrent;
?>
        </div>
        <table cellpadding="3" cellspacing="1" border="0" class="layout border slice" width="100%">
<?php
        if (!$this->NewTorrent) {
            if (check_perms('torrents_freeleech')) {
                $leech = ["Normal", "Free", "Neutral"];
                $reason = ["N/A", "Staff Pick", "Perma-FL", "Vanity House"];
?>
            <tr id="freetorrent">
                <td class="label">Leech Type</td>
                <td>
                    <select name="freeleechtype">
<?php           foreach ($leech as $Key => $Name) { ?>
                        <option value="<?= $Key ?>"<?=($Key == $Torrent['FreeTorrent'] ? ' selected="selected"' : '')?>><?= $Name ?></option>
<?php           } ?>
                    </select>
                    because
                    <select name="freeleechreason">
<?php           foreach ($reason as $Key => $Name) { ?>
                        <option value="<?= $Key ?>"<?=($Key == $Torrent['FreeLeechType'] ? ' selected="selected"' : '')?>><?= $Name ?></option>
<?php           } ?>
                    </select>
                </td>
            </tr>
<?php
            }
        }
?>
            <tr>
                <td colspan="2" style="text-align: center;">
                    <p>Be sure that your torrent is approved by the <a href="rules.php?p=upload" target="_blank">rules</a>. Not doing this will result in a <strong class="important_text">warning</strong> or <strong class="important_text">worse</strong>.</p>
<?php   if ($this->NewTorrent) { ?>
                    <p>After uploading the torrent, you will have a one hour grace period during which no one other than you can fill requests with this torrent.<br />Make use of this time wisely, and <a href="requests.php" target="_blank">search the list of requests</a>.</p>
<?php   } ?>
                    <div style="width: 60%; margin: 0 auto;">
                    <ul id="check" class="nobullet" style="display: none; padding: 12px; text-align: left; border: 2px solid orangered;"></ul>
                    </div>
                    <input id="post" type="submit"<?php if ($this->NewTorrent) { echo ' value="Upload torrent"'; } else { echo ' value="Edit torrent"';} ?> />
                </td>
            </tr>
        </table>
    </form>
</div>
<?php
    } //function foot

    function music_form($GenreTags) {
        $QueryID = G::$DB->get_query_id();
        $Torrent = $this->Torrent;
        $IsRemaster = !empty($Torrent['Remastered']);
        $UnknownRelease = !$this->NewTorrent && $IsRemaster && !$Torrent['RemasterYear'];

        if ($Torrent['GroupID']) {
            G::$DB->prepared_query("
                SELECT
                    ID,
                    RemasterYear,
                    RemasterTitle,
                    RemasterRecordLabel,
                    RemasterCatalogueNumber
                FROM torrents
                WHERE Remastered = '1'
                    AND RemasterYear != 0
                    AND GroupID = ?
                ORDER BY RemasterYear DESC,
                    RemasterTitle DESC,
                    RemasterRecordLabel DESC,
                    RemasterCatalogueNumber DESC
                ", $Torrent['GroupID']
            );
            if (G::$DB->has_results()) {
                $GroupRemasters = G::$DB->to_array(false, MYSQLI_BOTH, false);
            }
        }

        $HasLog = $Torrent['HasLog'];
        $HasCue = $Torrent['HasCue'];
        $BadTags = $Torrent['BadTags'];
        $BadFolders = $Torrent['BadFolders'];
        $BadFiles = $Torrent['BadFiles'];
        $MissingLineage = $Torrent['MissingLineage'];
        $CassetteApproved = $Torrent['CassetteApproved'];
        $LossymasterApproved = $Torrent['LossymasterApproved'];
        $LossywebApproved = $Torrent['LossywebApproved'];
        $releaseTypes = (new \Gazelle\ReleaseType)->list();
        $user = new \Gazelle\User(G::$LoggedUser['ID']);
?>
        <div id="musicbrainz_popup" style="display: none;">
            <a href="#null" id="popup_close">x</a>
            <h1 id="popup_title"></h1>
            <h2 id="popup_back"></h2>
            <div id="results1"></div>
            <div id="results2"></div>
        </div>
        <div id="popup_background"></div>

        <table cellpadding="3" cellspacing="1" border="0" class="layout border<?php if ($this->NewTorrent) { echo ' slice'; } ?>" width="100%">
<?php   if (!$this->NewTorrent) { ?>
            <tr><td colspan="2"><h3>Edit <?=
                Artists::display_artists(Artists::get_artist($Torrent['GroupID']))
                . '<a href="/torrents.php?id=' . $Torrent['GroupID'] . '">' . display_str($Torrent['Title']) . "</a>"
            ?></h3></td></tr>
<?php   } else { ?>
            <tr id="releasetype_tr">
                <td class="label">
                    <span id="releasetype_label">Release type:</span>
                </td>
                <td>
                    <select id="releasetype" name="releasetype"<?=$this->Disabled?>>
                        <option>---</option>
<?php       foreach ($releaseTypes as $Key => $Val) { ?>
                        <option value="<?= $Key ?>"<?= $Key == $Torrent['ReleaseType'] ? ' selected="selected"' : '' ?>><?= $Val ?></option>
<?php       } ?>
                    </select>
                    <br />Please take the time to fill this out correctly (especially when adding Compilations and Anthologies). Need help? Try reading <a href="wiki.php?action=article&amp;id=58" target="_blank">this wiki article</a> or searching <a href="https://musicbrainz.org/search" target="_blank">MusicBrainz</a>.
                </td>
            </tr>
            <tr>
                <td class="label">Image (recommended):</td>
                <td><input type="text" id="image" name="image" size="60" value="<?=display_str($Torrent['Image']) ?>"<?=$this->Disabled?> />
                    <img id="thumbnail" src="#" height="100" width="100" float="right" style="margin-left: 10px; vertical-align: top; display: none;" />
                <br />Artwork helps improve the quality of the catalog. Please try to find a decent sized image (500x500).
<?php       if (IMAGE_HOST_BANNED) { ?>
                <br /><b>Images hosted on <strong class="important_text"><?= implode(', ', IMAGE_HOST_BANNED)
                    ?> are not allowed</strong>, please rehost first on one of <?= implode(', ', IMAGE_HOST_RECOMMENDED) ?>.</b>
<?php       } ?>
                </td>
            </tr>

            <tr id="artist_tr">
            <td class="label">Artist(s):</td>
            <td id="artistfields">
                <p id="vawarning" class="hidden"><strong class="important_text">Please use the multiple artists feature rather than adding "Various Artists" as an artist; read <a href="wiki.php?action=article&amp;id=64" target="_blank">this</a> for more information.</strong></p>
<?php
            if (!empty($Torrent['Artists'])) {
                $FirstArtist = true;
                foreach ($Torrent['Artists'] as $Importance => $Artists) {
                    $n = 0;
                    foreach ($Artists as $Artist) {
?>
                    <input type="text" id="artist_<?= $n++ ?>" name="artists[]" size="45" value="<?= display_str($Artist['name']) ?>" onblur="CheckVA();"<?=
                        $user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?><?= $this->Disabled ?> />
                    <select id="importance" name="importance[]"<?=$this->Disabled?>>
                        <option value="1"<?=($Importance == '1' ? ' selected="selected"' : '')?>>Main</option>
                        <option value="2"<?=($Importance == '2' ? ' selected="selected"' : '')?>>Guest</option>
                        <option value="4"<?=($Importance == '4' ? ' selected="selected"' : '')?>>Composer</option>
                        <option value="5"<?=($Importance == '5' ? ' selected="selected"' : '')?>>Conductor</option>
                        <option value="6"<?=($Importance == '6' ? ' selected="selected"' : '')?>>DJ / Compiler</option>
                        <option value="3"<?=($Importance == '3' ? ' selected="selected"' : '')?>>Remixer</option>
                        <option value="7"<?=($Importance == '7' ? ' selected="selected"' : '')?>>Producer</option>
                        <option value="8"<?=($Importance == '8' ? ' selected="selected"' : '')?>>Arranger</option>
                    </select>
<?php
                        if ($FirstArtist) {
                            if (!$this->DisabledFlag) {
?>
                    <a href="javascript:AddArtistField()" class="brackets">+</a> <a href="javascript:RemoveArtistField()" class="brackets">&minus;</a>
<?php
                            }
                            $FirstArtist = false;
                        }
?>
                    <br />
<?php
                    }
                }
            } else {
?>
                    <input type="text" id="artist_0" name="artists[]" size="45" onblur="CheckVA();"<?=
                        $user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?><?= $this->Disabled ?> />
                    <select id="importance_0" name="importance[]"<?=$this->Disabled?>>
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
<?php       } ?>
                </td>
            </tr>

            <tr id="title_tr">
                <td class="label">Album title:</td>
                <td>
                    <input type="text" id="title" name="title" size="60" value="<?=display_str($Torrent['Title'])?>"<?=$this->Disabled?> />
                    <p class="min_padding">Do not include the words remaster, re-issue, MFSL Gold, limited edition, bonus tracks, bonus disc or country-specific information in this field. That belongs in the edition information fields below; see <a href="wiki.php?action=article&amp;id=18" target="_blank">this</a> for further information. Also remember to use the correct capitalization for your upload. See the <a href="wiki.php?action=article&id=42" target="_blank">Capitalization Guidelines</a> for more information.</p>
                </td>
            </tr>
            <tr id="musicbrainz_tr">
                <td class="label tooltip" title="Click the &quot;Find Info&quot; button to automatically fill out parts of the upload form by selecting an entry in MusicBrainz">MusicBrainz:</td>
                <td><input type="button" value="Find Info" id="musicbrainz_button" /></td>
            </tr>
            <tr id="year_tr">
                <td class="label">
                    <span id="year_label_not_remaster"<?php if ($IsRemaster) { echo ' class="hidden"';} ?>>Year:</span>
                    <span id="year_label_remaster"<?php if (!$IsRemaster) { echo ' class="hidden"';} ?>>Year of first release:</span>
                </td>
                <td>
                    <p id="yearwarning" class="hidden">You have entered a year for a release which predates the medium's availability. You will need to change the year and enter additional edition information. If this information cannot be provided, check the &quot;Unknown Release&quot; check box below.</p>
                    <input type="text" id="year" name="year" size="5" value="<?=display_str($Torrent['Year']) ?>"<?=$this->Disabled?> onblur="CheckYear();" />
                    <br />This is the year of the original release. You may be uploading a remaster or re-edition that was published more recently.
                    <br />If so, there is a place to add that date below (check Edition information).
                </td>
            </tr>
            <tr id="label_tr">
                <td class="label">Record label (optional):</td>
                <td><input type="text" id="record_label" name="record_label" size="40" value="<?=display_str($Torrent['RecordLabel']) ?>"<?=$this->Disabled?> /></td>
            </tr>
            <tr id="catalogue_tr">
                <td class="label">Catalogue number (optional):</td>
                <td>
                    <input type="text" id="catalogue_number" name="catalogue_number" size="40" value="<?=display_str($Torrent['CatalogueNumber']) ?>"<?=$this->Disabled?> />
                    <br />
                    Please double-check the record label and catalogue number when using MusicBrainz. See <a href="wiki.php?action=article&amp;id=18" target="_blank">this guide</a> for more details.
                </td>
            </tr>

<?php    } /* $this->NewTorrent */ ?>
            <tr>
                <td class="label">Edition information:</td>
                <td>
                    <input type="checkbox" id="remaster" name="remaster"<?php if ($IsRemaster) { echo ' checked="checked"'; } ?> onclick="Remaster();<?php if ($this->NewTorrent) { ?> CheckYear();<?php } ?>" />
                    <label for="remaster">Check this if this torrent is a different edition to the original, for example a remaster, country specific edition, or a release that includes additional bonus tracks or bonus discs.</label>
                    <div id="remaster_true"<?php if (!$IsRemaster) { echo ' class="hidden"';} ?>>
<?php    if (check_perms('edit_unknowns') || G::$LoggedUser['ID'] == $Torrent['UserID']) { ?>
                        <br />
                        <input type="checkbox" id="unknown" name="unknown"<?php if ($UnknownRelease) { echo ' checked="checked"'; } ?> onclick="<?php if ($this->NewTorrent) { ?>CheckYear(); <?php } ?>ToggleUnknown();" /> <label for="unknown">Unknown Release</label>
<?php    } ?>
                        <br /><br />
<?php    if (!empty($GroupRemasters)) { ?>
                        <input type="hidden" id="json_remasters" value="<?=display_str(json_encode($GroupRemasters))?>" />
                        <select id="groupremasters" name="groupremasters" onchange="GroupRemaster()"<?php if ($UnknownRelease) { echo ' disabled="disabled"'; } ?>>
                            <option value="">-------</option>
<?php
            $LastLine = '';

            foreach ($GroupRemasters as $Index => $Remaster) {
                $Line = $Remaster['RemasterYear'] . ' / ' . $Remaster['RemasterTitle']
                    . ' / ' . $Remaster['RemasterRecordLabel'] . ' / ' . $Remaster['RemasterCatalogueNumber'];
                if ($Line != $LastLine) {
                    $LastLine = $Line;
?>
                            <option value="<?=$Index?>"<?=(($Remaster['ID'] == $this->TorrentID) ? ' selected="selected"' : '')?>><?=$Line?></option>
<?php
                }
            }
?>
                        </select>
                        <br />
<?php   } ?>
                        <table id="edition_information" class="layout border" border="0" width="100%">
                            <tbody>
                                <tr id="edition_year">
                                    <td class="label">Year (required):</td>
                                    <td>
                                        <input type="text" id="remaster_year" name="remaster_year" size="5" value="<?php if ($Torrent['RemasterYear']) { echo display_str($Torrent['RemasterYear']); } ?>"<?php if ($UnknownRelease) { echo ' disabled="disabled"';} ?> />
                                    </td>
                                </tr>
                                <tr id="edition_title">
                                    <td class="label">Title:</td>
                                    <td>
                                        <input type="text" id="remaster_title" name="remaster_title" size="50" value="<?=display_str($Torrent['RemasterTitle']) ?>"<?php if ($UnknownRelease) { echo ' disabled="disabled"';} ?> />
                                        <p class="min_padding">Title of the edition (e.g. <span style="font-style: italic;">"Deluxe Edition" or "Remastered"</span>).</p>
                                    </td>
                                </tr>
                                <tr id="edition_record_label">
                                    <td class="label">Record label:</td>
                                    <td>
                                        <input type="text" id="remaster_record_label" name="remaster_record_label" size="50" value="<?=display_str($Torrent['RemasterRecordLabel']) ?>"<?php if ($UnknownRelease) { echo ' disabled="disabled"';} ?> />
                                        <p class="min_padding">This is for the record label of the <strong>edition</strong>. It may differ from the original.</p>
                                    </td>
                                </tr>
                                <tr id="edition_catalogue_number">
                                    <td class="label">Catalogue number:</td>
                                    <td><input type="text" id="remaster_catalogue_number" name="remaster_catalogue_number" size="50" value="<?=display_str($Torrent['RemasterCatalogueNumber']) ?>"<?php if ($UnknownRelease) { echo ' disabled="disabled"';} ?> />
                                        <p class="min_padding">This is for the catalogue number of the <strong>edition</strong>.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="label">Scene:</td>
                <td>
                    <input type="checkbox" id="scene" name="scene" <?php if ($Torrent['Scene']) { echo 'checked="checked" ';} ?>/>
                    <label for="scene">Select this only if this is a "scene release".<br />If you ripped it yourself, it is <strong>not</strong> a scene release. If you are not sure, <strong class="important_text">do not</strong> select it; you will be penalized. For information on the scene, visit <a href="https://en.wikipedia.org/wiki/Warez_scene" target="_blank">Wikipedia</a>.</label>
                </td>
            </tr>
<?php   if (check_perms('torrents_edit_vanityhouse') && $this->NewTorrent) { ?>
            <tr>
                <td class="label">Vanity House:</td>
                <td>
                    <label><input type="checkbox" id="vanity_house" name="vanity_house"<?php if ($Torrent['GroupID']) { echo ' disabled="disabled"'; } ?><?php if ($Torrent['VanityHouse']) { echo ' checked="checked"';} ?> />
                    Check this only if you are submitting your own work or submitting on behalf of the artist, and this is intended to be a Vanity House release.
                    </label>
                </td>
            </tr>
<?php   } ?>

            <tr>
                <td class="label">Media:</td>
                <td>
                    <select name="media" id="media">
                        <option>---</option>
<?php   foreach ($this->Media as $Media) { ?>
                        <option value="<?= $Media ?>"<?=
                            $Media == $Torrent['Media'] ? ' selected="selected"' : '' ?>><?= $Media ?></option>
<?php   } ?>
                    </select>
                </td>
            </tr>

            <tr>
                <td class="label">Format:</td>
                <td>
                    <select id="format" name="format">
                        <option>---</option>
<?php   foreach ($this->Formats as $Format) { ?>
                        <option value="<?= $Format ?>"<?=
                            $Format == $Torrent['Format'] ? ' selected="selected"' : '' ?>><?= $Format ?></option>
<?php   } ?>
                    </select>
                <span id="format_warning" class="important_text"></span>
                </td>
            </tr>
            <tr id="bitrate_row">
                <td class="label">Bitrate:</td>
                <td>
                    <select id="bitrate" name="bitrate">
                        <option value="">---</option>
<?php
        if ($Torrent['Bitrate'] && !in_array($Torrent['Bitrate'], $this->Bitrates)) {
            $OtherBitrate = true;
            if (substr($Torrent['Bitrate'], strlen($Torrent['Bitrate']) - strlen(' (VBR)')) == ' (VBR)') {
                $Torrent['Bitrate'] = substr($Torrent['Bitrate'], 0, strlen($Torrent['Bitrate']) - 6);
                $VBR = true;
            }
        } else {
            $OtherBitrate = false;
        }

        // See if they're the same bitrate
        // We have to do this screwery because '(' is a regex character.
        $SimpleBitrate = explode(' ', $Torrent['Bitrate']);
        $SimpleBitrate = $SimpleBitrate[0];
        foreach ($this->Bitrates as $Bitrate) {
?>
                        <option value="<?= $Bitrate ?>"<?=
            ($SimpleBitrate && preg_match('/^'.$SimpleBitrate.'.*/', $Bitrate)) || ($OtherBitrate && $Bitrate == 'Other')
                ? ' selected="selected"' : '' ?>
            ?><?= $Bitrate ?></option>

<?php   } ?>
                    </select>
                    <span id="other_bitrate_span"<?php if (!$OtherBitrate) { echo ' class="hidden"'; } ?>>
                        <input type="text" name="other_bitrate" size="5" id="other_bitrate"<?php if ($OtherBitrate) { echo ' value="'.display_str($Torrent['Bitrate']).'"';} ?> onchange="AltBitrate();" />
                        <input type="checkbox" id="vbr" name="vbr"<?php if (isset($VBR)) { echo ' checked="checked"'; } ?> /><label for="vbr"> (VBR)</label>
                    </span>
                </td>
            </tr>

            <tr id="upload_logs"<?= $this->NewTorrent ? ' class="hidden"' : '' ?>>
                <td class="label">
                    Log files:<br /><a href="javascript:;" onclick="AddLogField('<?=Logchecker::getAcceptValues()?>');" class="brackets">+</a> <a href="javascript:;" onclick="RemoveLogField();" class="brackets">&minus;</a>
                </td>
                <td id="logfields">
                    <a class="brackets" href="logchecker.php" target="_blank">Logchecker</a>
                    You may analyze your log files prior uploading to verify that they are perfect.<br />For multi-disc releases, click the "<span class="brackets">+</span>" button to add multiple log files.<br />
                    <input id="logfile_1" type="file" accept="<?=LogChecker::getAcceptValues()?>" multiple name="logfiles[]" size="50" />
                </td>
            </tr>

<?php   if ($this->NewTorrent) { ?>
            <tr>
                <td class="label">Multi-format uploader:</td>
                <td><input type="button" value="+" id="add_format" />&nbsp;<input type="button" style="display: none;" value="-" id="remove_format" /></td>
            </tr>
            <tr id="placeholder_row_top"></tr>
            <tr id="extra_format_placeholder"></tr>
<?php
        }
        if (!$this->NewTorrent && check_perms('users_mod')) {
?>
            <tr>
                <td class="label">Log/cue:</td>
                <td>
                    <input type="checkbox" id="flac_log" name="flac_log"<?php if ($HasLog) { echo ' checked="checked"';} ?> /> <label for="flac_log">Check this box if the torrent has, or should have, a log file.</label><br />
                    <input type="checkbox" id="flac_cue" name="flac_cue"<?php if ($HasCue) { echo ' checked="checked"';} ?> /> <label for="flac_cue">Check this box if the torrent has, or should have, a cue file.</label><br />
                </td>
            </tr>
            <tr>
                <td class="label">Bad tags:</td>
                <td><input type="checkbox" id="bad_tags" name="bad_tags"<?php if ($BadTags) { echo ' checked="checked"';} ?> /> <label for="bad_tags">Check this box if the torrent has bad tags.</label></td>
            </tr>
            <tr>
                <td class="label">Bad folder names:</td>
                <td><input type="checkbox" id="bad_folders" name="bad_folders"<?php if ($BadFolders) { echo ' checked="checked"';} ?> /> <label for="bad_folders">Check this box if the torrent has bad folder names.</label></td>
            </tr>
            <tr>
                <td class="label">Bad file names:</td>
                <td><input type="checkbox" id="bad_files" name="bad_files"<?php if ($BadFiles) {echo ' checked="checked"';} ?> /> <label for="bad_files">Check this box if the torrent has bad file names.</label></td>
            </tr>
            <tr>
                <td class="label">Missing lineage:</td>
                <td><input type="checkbox" id="missing_lineage" name="missing_lineage"<?php if ($MissingLineage) {echo ' checked="checked"';} ?> /> <label for="missing_lineage">Check this box if the torrent is missing lineage information.</label></td>
            </tr>
            <tr>
                <td class="label">Cassette approved:</td>
                <td><input type="checkbox" id="cassette_approved" name="cassette_approved"<?php if ($CassetteApproved) {echo ' checked="checked"';} ?> /> <label for="cassette_approved">Check this box if the torrent is an approved cassette rip.</label></td>
            </tr>
            <tr>
                <td class="label">Lossy master approved:</td>
                <td><input type="checkbox" id="lossymaster_approved" name="lossymaster_approved"<?php if ($LossymasterApproved) {echo ' checked="checked"';} ?> /> <label for="lossymaster_approved">Check this box if the torrent is an approved lossy master.</label></td>
            </tr>
            <tr>
                <td class="label">Lossy web approved:</td>
                <td><input type="checkbox" id="lossyweb_approved" name="lossyweb_approved"<?php if ($LossywebApproved) { echo ' checked="checked"';} ?> /> <label for="lossyweb_approved">Check this box if the torrent is an approved lossy WEB release.</label></td>
            </tr>
<?php
        }
        if ($this->NewTorrent) {
            global $Twig;
?>
            <tr>
                <td class="label">Tags:</td>
                <td>
<?php       if ($GenreTags) { ?>
                    <select id="genre_tags" name="genre_tags" onchange="add_tag(); return false;"<?=$this->Disabled?>>
                        <option>---</option>
<?php           foreach ($GenreTags as $Genre) { ?>
                        <option value="<?= $Genre ?>"><?= $Genre ?></option>
<?php           } ?>
                    </select>
<?php       } ?>
                    <input type="text" id="tags" name="tags" size="40" value="<?= display_str($Torrent['TagList']) ?>"<?=
                        $user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?><?= $this->Disabled ?> />
                    <br /><?= $Twig->render('rules/tag.twig', ['on_upload' => true]) ?>
                </td>
            </tr>
            <tr>
                <td class="label">Album description:</td>
                <td>
<?php new TEXTAREA_PREVIEW('album_desc', 'album_desc', display_str($Torrent['GroupDescription']), 60, 8, true, true, false, [$this->Disabled]); ?>
                    <p class="min_padding">Contains background information such as album history and maybe a review.</p>
                </td>
            </tr>
<?php   } /* if new torrent */ ?>
            <tr>
                <td class="label">Release description (optional):</td>
                <td>
<?php new TEXTAREA_PREVIEW('release_desc', 'release_desc', display_str($Torrent['TorrentDescription']), 60, 8); ?>
                    <p class="min_padding">Contains information like encoder settings or details of the ripping process. <strong class="important_text">Do not paste the ripping log here.</strong></p>
                </td>
            </tr>
        </table>
<?php
        //    For AJAX requests (e.g. when changing the type from Music to Applications),
        //    we don't need to include all scripts, but we do need to include the code
        //    that generates previews. It will have to be eval'd after an AJAX request.
        if ($_SERVER['SCRIPT_NAME'] === '/ajax.php') {
            TEXTAREA_PREVIEW::JavaScript(false);
        }
        G::$DB->set_query_id($QueryID);
    }

    function audiobook_form() {
        $Torrent = $this->Torrent;
        $user = new \Gazelle\User(G::$LoggedUser['ID']);
        global $Twig;
?>
        <table cellpadding="3" cellspacing="1" border="0" class="layout border slice" width="100%">
<?php   if ($this->NewTorrent) { ?>
            <tr id="title_tr">
                <td class="label">Author - Title:</td>
                <td>
                    <input type="text" id="title" name="title" size="60" value="<?=display_str($Torrent['Title']) ?>" />
                    <p class="min_padding">Should only include the author if applicable.</p>
                </td>
            </tr>
<?php   } ?>
            <tr id="year_tr">
                <td class="label">Year:</td>
                <td><input type="text" id="year" name="year" size="5" value="<?=display_str($Torrent['Year']) ?>" /></td>
            </tr>
            <tr>
                <td class="label">Format:</td>
                <td>
                    <select id="format" name="format" onchange="Format()">
                        <option value="">---</option>
<?php   foreach ($this->Formats as $Format) { ?>
                        <option value="<?= $Format ?>"<?=
                            $Format == $Torrent['Format'] ? ' selected="selected"' : '' ?><?= $Format ?></option>
<?php   } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label">Bitrate:</td>
                <td>
                    <select id="bitrate" name="bitrate" onchange="Bitrate()">
                        <option value="">---</option>
<?php
        if (!$Torrent['Bitrate'] || ($Torrent['Bitrate'] && !in_array($Torrent['Bitrate'], $this->Bitrates))) {
            $OtherBitrate = true;
            if (substr($Torrent['Bitrate'], strlen($Torrent['Bitrate']) - strlen(' (VBR)')) == ' (VBR)') {
                $Torrent['Bitrate'] = substr($Torrent['Bitrate'], 0, strlen($Torrent['Bitrate']) - 6);
                $VBR = true;
            }
        } else {
            $OtherBitrate = false;
        }
        $SimpleBitrate = explode(' ', $Torrent['Bitrate']);
        $SimpleBitrate = $SimpleBitrate[0];
        foreach ($this->Bitrates as $Bitrate) {
?>
                        <option value="<?= $Bitrate ?>"<?=
            ($SimpleBitrate && preg_match('/^'.$SimpleBitrate.'.*/', $Bitrate)) || ($OtherBitrate && $Bitrate == 'Other')
                ? ' selected="selected"' : '' ?>
            ?><?= $Bitrate ?></option>

<?php   } ?>
                    </select>
                    <span id="other_bitrate_span"<?php if (!$OtherBitrate) { echo ' class="hidden"'; } ?>>
                        <input type="text" name="other_bitrate" size="5" id="other_bitrate"<?php if ($OtherBitrate) { echo ' value="'.display_str($Torrent['Bitrate']).'"';} ?> onchange="AltBitrate()" />
                        <input type="checkbox" id="vbr" name="vbr"<?php if (isset($VBR)) { echo ' checked="checked"'; } ?> /><label for="vbr"> (VBR)</label>
                    </span>
                </td>
            </tr>
<?php   if ($this->NewTorrent) { ?>
            <tr>
                <td class="label">Tags:</td>
                <td>
                    <input type="text" id="tags" name="tags" size="60" value="<?= display_str($Torrent['TagList']) ?>"<?=
                        $user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />
                    <br /><?= $Twig->render('rules/tag.twig', ['on_upload' => true]) ?>
                </td>
            </tr>
            <tr>
                <td class="label">Description:</td>
                <td>
<?php new TEXTAREA_PREVIEW('album_desc', 'album_desc', display_str($Torrent['GroupDescription']), 60, 8); ?>
                    <p class="min_padding">Contains information like the track listing, a review, a link to Discogs or MusicBrainz, etc.</p>
                </td>
            </tr>
<?php   } ?>
            <tr>
                <td class="label">Release description (optional):</td>
                <td>
<?php new TEXTAREA_PREVIEW('release_desc', 'release_desc', display_str($Torrent['TorrentDescription']), 60, 8); ?>
                    <p class="min_padding">Contains information like encoder settings. For analog rips, this frequently contains lineage information.</p>
                </td>
            </tr>
        </table>
<?php
        TEXTAREA_PREVIEW::JavaScript(false);
    }

    function simple_form($CategoryID) {
        $Torrent = $this->Torrent;
        $user = new \Gazelle\User(G::$LoggedUser['ID']);
?>        <table cellpadding="3" cellspacing="1" border="0" class="layout border slice" width="100%">
            <tr id="name">
<?php
        if ($this->NewTorrent) {
            if ($this->Categories[$CategoryID] == 'E-Books') {
?>
                <td class="label">Author - Title:</td>
<?php       } else { ?>
                <td class="label">Title:</td>
<?php       } ?>
                <td><input type="text" id="title" name="title" size="60" value="<?=display_str($Torrent['Title']) ?>" /></td>
            </tr>
            <tr>
                <td class="label">Tags:</td>
                <td><input type="text" id="tags" name="tags" size="60" value="<?= display_str($Torrent['TagList']) ?>"<?=
                    $user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> /></td>
            </tr>
            <tr>
                <td class="label">Image (optional):</td>
                <td><input type="text" id="image" name="image" size="60" value="<?=display_str($Torrent['Image']) ?>"<?=$this->Disabled?> />
                <br />Artwork helps improve the quality of the catalog. Please try to find a decent sized image (500x500).
<?php       if (IMAGE_HOST_BANNED) { ?>
                <br />Images hosted on <?= implode(', ', IMAGE_HOST_BANNED) ?> are not allowed, please rehost first on one of <?= implode(', ', IMAGE_HOST_RECOMMENDED) ?>
<?php       } ?>
                </td>
            </tr>
            <tr>
                <td class="label">Description:</td>
                <td>
<?php
            new TEXTAREA_PREVIEW('desc', 'desc', display_str($Torrent['GroupDescription']), 60, 8);
            TEXTAREA_PREVIEW::JavaScript(false);
?>
                </td>
            </tr>
<?php   } ?>
        </table>
<?php
    }
}
