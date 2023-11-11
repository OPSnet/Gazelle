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

namespace Gazelle;

use Gazelle\Util\Textarea;
use OrpheusNET\Logchecker\Logchecker;

class Upload extends \Gazelle\Base {
    protected int    $categoryId = 0;
    protected string $Disabled = '';
    protected bool   $DisabledFlag = false;

    const TORRENT_INPUT_ACCEPT = ['application/x-bittorrent', '.torrent'];
    const JSON_INPUT_ACCEPT = ['application/json', '.json'];

    public function __construct(
        protected \Gazelle\User $user,
        protected array|false $Torrent = false,
        protected string|false $Error = false,
    ) {
        if (is_array($this->Torrent) && isset($this->Torrent['GroupID'])) {
            $this->Disabled = ' disabled="disabled"';
            $this->DisabledFlag = true;
        }
    }

    public function setCategoryId(int $categoryId): static {
        // FIXME: the upload form counts categories from zero
        $this->categoryId = $categoryId - 1;
        return $this;
    }

    /**
     * This is an awful hack until something better can be figured out.
     * We want to get rid eval()'ing Javascript code, and this produces
     * something that can be added to the DOM and the engine will run it.
     */
    public function albumReleaseJS(): string {
        $x = new Textarea('album_desc', '');
        $x = new Textarea('release_desc', '');
        unset($x);
        return Textarea::factory();
    }

    public function descriptionJS(): string {
        $x = new Textarea('desc', '');
        unset($x);
        return Textarea::factory();
    }

    public function head(): string {
        return self::$twig->render('upload/header.twig', [
            'category_id' => $this->categoryId,
            'error'       => $this->Error,
            'is_disabled' => $this->DisabledFlag,
            'is_upload'   => $this->Torrent === false || isset($this->Torrent['add-format']),
            'info'        => $this->Torrent,
            'user'        => $this->user,
        ]);
    }

    public function foot(bool $showFooter): string {
        $torMan = new \Gazelle\Manager\Torrent;
        return self::$twig->render('upload/footer.twig', [
            'is_upload'    => $this->Torrent === false || isset($this->Torrent['add-format']),
            'info'         => $this->Torrent,
            'leech_type'   => $torMan->leechTypeList(),
            'leech_reason' => $torMan->leechReasonList(),
            'show_footer'  => $showFooter,
            'viewer'       => $this->user,
        ]);
    }

    public function music_form(array $GenreTags, \Gazelle\Manager\TGroup $manager): string {
        $QueryID = self::$db->get_query_id();
        $Torrent = $this->Torrent;
        if ($Torrent === false) {
            $tgroup              = null;
            $GroupRemasters      = [];
            $IsRemaster          = false;
            $UnknownRelease      = false;
            $HasLog              = false;
            $HasCue              = false;
            $BadTags             = false;
            $BadFolders          = false;
            $BadFiles            = false;
            $MissingLineage      = false;
            $CassetteApproved    = false;
            $LossymasterApproved = false;
            $LossywebApproved    = false;
        } else {
            $tgroup              = $manager->findById((int)($Torrent['GroupID'] ?? 0));
            $GroupRemasters      = $tgroup?->remasterList() ?? [];
            $IsRemaster          = !empty($Torrent['Remastered']);
            $UnknownRelease      = $IsRemaster && !$Torrent['RemasterYear'];
            // not set on Upload => Add Format
            $HasLog              = $Torrent['HasLog'] ?? false;
            $HasCue              = $Torrent['HasCue'] ?? false;
            $BadTags             = $Torrent['BadTags'] ?? false;
            $BadFolders          = $Torrent['BadFolders'] ?? false;
            $BadFiles            = $Torrent['BadFiles'] ?? false;
            $MissingLineage      = $Torrent['MissingLineage'] ?? false;
            $CassetteApproved    = $Torrent['CassetteApproved'] ?? false;
            $LossymasterApproved = $Torrent['LossymasterApproved'] ?? false;
            $LossywebApproved    = $Torrent['LossywebApproved'] ?? false;
        }
        $releaseTypes = (new \Gazelle\ReleaseType)->list();

        ob_start();
?>
        <div id="musicbrainz_popup" style="display: none;">
            <a href="#null" id="popup_close">x</a>
            <h1 id="popup_title"></h1>
            <h2 id="popup_back"></h2>
            <div id="results1"></div>
            <div id="results2"></div>
        </div>
        <div id="popup_background"></div>

        <table id="form-music-upload" cellpadding="3" cellspacing="1" border="0" class="layout border<?php if ($Torrent === false) {
echo ' slice'; } ?>" width="100%">
<?php   if (is_array($Torrent) && isset($Torrent['GroupID']) && !isset($Torrent['add-format'])) { ?>
            <tr><td colspan="2"><h3>Edit <?= $tgroup->link() ?></h3></td></tr>
<?php   } else { ?>
            <tr id="releasetype_tr">
                <td class="label">
                    <span id="releasetype_label">Release type:</span>
                </td>
                <td>
                    <select id="releasetype" name="releasetype"<?=$this->Disabled?>>
                        <option>---</option>
<?php       foreach ($releaseTypes as $Key => $Val) { ?>
                        <option value="<?= $Key ?>"<?= is_array($Torrent) && $Key == $Torrent['ReleaseType'] ? ' selected="selected"' : '' ?>><?= $Val ?></option>
<?php       } ?>
                    </select>
                    <br />Please take the time to fill this out correctly (especially when adding Compilations and Anthologies). Need help? Try reading <a href="wiki.php?action=article&amp;id=58" target="_blank">this wiki article</a> or searching <a href="https://musicbrainz.org/search" target="_blank">MusicBrainz</a>.
                </td>
            </tr>
            <tr>
                <td class="label">Image (recommended):</td>
                <td><input type="text" id="image" name="image" size="60" value="<?=html_escape($Torrent['Image'] ?? '') ?>"<?=$this->Disabled?> />
                    <img id="thumbnail" src="#" height="100" width="100" float="right" style="margin-left: 10px; vertical-align: top; display: none;" />
                <br />Artwork helps improve the quality of the catalog. Please try to find a decent sized image (500x500).
<?php       if (IMAGE_HOST_BANNED) { /** @phpstan-ignore-line */ ?>
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
                    <input type="text" id="artist_<?= $n++ ?>" name="artists[]" size="45" value="<?= html_escape($Artist['name']) ?>" onblur="CheckVA();"<?=
                        $this->user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?><?= $this->Disabled ?> />
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
                        $this->user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?><?= $this->Disabled ?> />
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
                    <input type="text" id="title" name="title" size="60" value="<?=html_escape($Torrent['Title'] ?? '')?>"<?=$this->Disabled?> />
                    <p class="min_padding">Do not include the words remaster, re-issue, MFSL Gold, limited edition, bonus tracks, bonus disc or country-specific information in this field. That belongs in the edition information fields below; see <a href="wiki.php?action=article&amp;id=18" target="_blank">this</a> for further information. Also remember to use the correct capitalization for your upload. See the <a href="wiki.php?action=article&id=42" target="_blank">Capitalization Guidelines</a> for more information.</p>
                </td>
            </tr>
            <tr id="musicbrainz_tr">
                <td class="label tooltip" title="Click the &quot;Find Info&quot; button to automatically fill out parts of the upload form by selecting an entry in MusicBrainz">MusicBrainz:</td>
                <td><input type="button" value="Find Info" id="musicbrainz_button" /></td>
            </tr>
            <tr id="year_tr">
                <td class="label">
                    <span id="year_label_not_remaster"<?php if ($IsRemaster) {
echo ' class="hidden"';} ?>>Year:</span>
                    <span id="year_label_remaster"<?php if (!$IsRemaster) {
echo ' class="hidden"';} ?>>Year of first release:</span>
                </td>
                <td>
                    <p id="yearwarning" class="hidden">You have entered a year for a release which predates the medium's availability. You will need to change the year and enter additional edition information. If this information cannot be provided, check the &quot;Unknown Release&quot; check box below.</p>
                    <input type="text" id="year" name="year" size="5" value="<?=html_escape($Torrent['Year'] ?? '') ?>"<?=$this->Disabled?> onblur="CheckYear();" />
                    <br />This is the year of the original release. You may be uploading a remaster or re-edition that was published more recently.
                    <br />If so, there is a place to add that date below (check Edition information).
                </td>
            </tr>
            <tr id="label_tr">
                <td class="label">Record label (optional):</td>
                <td><input type="text" id="record_label" name="record_label" size="40" value="<?=html_escape($Torrent['RecordLabel'] ?? '') ?>"<?=$this->Disabled?> /></td>
            </tr>
            <tr id="catalogue_tr">
                <td class="label">Catalogue number (optional):</td>
                <td>
                    <input type="text" id="catalogue_number" name="catalogue_number" size="40" value="<?=html_escape($Torrent['CatalogueNumber'] ?? '') ?>"<?=$this->Disabled?> />
                    <br />
                    Please double-check the record label and catalogue number when using MusicBrainz. See <a href="wiki.php?action=article&amp;id=18" target="_blank">this guide</a> for more details.
                </td>
            </tr>

<?php    } /* new */ ?>
            <tr>
                <td class="label">Edition information:</td>
                <td>
                    <input type="checkbox" id="remaster" name="remaster"<?php if ($IsRemaster || $GroupRemasters) {
echo ' checked="checked"'; } ?> onclick="Remaster();<?php if ($Torrent === false) {
?> CheckYear();<?php } ?>" />
                    <label for="remaster">Check this if this torrent is a different edition to the original, for example a remaster, country specific edition, or a release that includes additional bonus tracks or bonus discs.</label>
                    <div id="remaster_true"<?php if (!($IsRemaster || $GroupRemasters)) {
echo ' class="hidden"';} ?>>
<?php    if ($this->user->permitted('edit_unknowns') || (is_array($Torrent) && isset($Torrent['UserID']) && $Torrent['UserID'] == $this->user->id())) { ?>
                        <br />
                        <input type="checkbox" id="unknown" name="unknown"<?php if ($UnknownRelease) {
echo ' checked="checked"'; } ?> onclick="<?php if ($Torrent === false) {
?>CheckYear(); <?php } ?>ToggleUnknown();" /> <label for="unknown">Unknown Release</label>
<?php    } ?>
                        <br /><br />
<?php    if ($GroupRemasters) { ?>
                        <input type="hidden" id="json_remasters" value="<?=
                            html_escape(json_encode($GroupRemasters))?>" />
                        <select id="groupremasters" name="groupremasters" onchange="GroupRemaster()"<?php
                                if ($UnknownRelease) {
echo ' disabled="disabled"'; } ?>>
                            <option value="">-------</option>
<?php
            $LastLine = '';
            foreach ($GroupRemasters as $Index => $Remaster) {
                $Line = implode(' / ', [$Remaster['year'], $Remaster['title'], $Remaster['record_label'], $Remaster['catalogue_number']]);
                if ($Line != $LastLine) {
                    $LastLine = $Line;
?>
                            <option value="<?=$Index?>"<?= is_array($Torrent) && isset($Torrent['ID']) && in_array($Torrent['ID'], $Remaster['id_list']) ? ' selected="selected"' : '' ?>><?=$Line?></option>
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
                                        <input type="text" id="remaster_year" name="remaster_year" size="5" value="<?php
                                            if (is_array($Torrent) && isset($Torrent['RemasterYear'])) {
                                                echo html_escape($Torrent['RemasterYear']);
                                            } ?>"<?php if ($UnknownRelease) {
echo ' disabled="disabled"';} ?> />
                                    </td>
                                </tr>
                                <tr id="edition_title">
                                    <td class="label">Title:</td>
                                    <td>
                                        <input type="text" id="remaster_title" name="remaster_title" size="50" value="<?=
                                            $Torrent === false ? '' : html_escape($Torrent['RemasterTitle'] ?? '')
                                            ?>"<?php if ($UnknownRelease) {
echo ' disabled="disabled"';} ?> />
                                        <p class="min_padding">Title of the edition (e.g. <span style="font-style: italic;">"Deluxe Edition" or "Remastered"</span>).</p>
                                    </td>
                                </tr>
                                <tr id="edition_record_label">
                                    <td class="label">Record label:</td>
                                    <td>
                                        <input type="text" id="remaster_record_label" name="remaster_record_label" size="50" value="<?=
                                            $Torrent === false ? '' : html_escape($Torrent['RemasterRecordLabel'] ?? '')
                                            ?>"<?php if ($UnknownRelease) {
echo ' disabled="disabled"';} ?> />
                                        <p class="min_padding">This is for the record label of the <strong>edition</strong>. It may differ from the original.</p>
                                    </td>
                                </tr>
                                <tr id="edition_catalogue_number">
                                    <td class="label">Catalogue number:</td>
                                    <td><input type="text" id="remaster_catalogue_number" name="remaster_catalogue_number" size="50" value="<?=
                                        $Torrent === false ? '' : html_escape($Torrent['RemasterCatalogueNumber'] ?? '')
                                        ?>"<?php if ($UnknownRelease) {
echo ' disabled="disabled"';} ?> />
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
                    <input type="checkbox" id="scene" name="scene" <?php if (is_array($Torrent) && (bool)($Torrent['Scene'] ?? false)) {
echo 'checked="checked" ';} ?>/>
                    <label for="scene">Select this only if this is a "scene release".<br />If you ripped it yourself, it is <strong>not</strong> a scene release. If you are not sure, <strong class="important_text">do not</strong> select it; you will be penalized. For information on the scene, visit <a href="https://en.wikipedia.org/wiki/Warez_scene" target="_blank">Wikipedia</a>.</label>
                </td>
            </tr>
<?php   if ($this->user->permitted('torrents_edit_vanityhouse')) { ?>
            <tr>
                <td class="label">Showcase:</td>
                <td>
                    <label><input type="checkbox" id="vanity_house" name="vanity_house" <?php
                        if (is_array($Torrent) && (bool)($Torrent['VanityHouse'] ?? false)) {
echo ' checked="checked"';} ?> />
                    Check this only if you are submitting your own work or submitting on behalf of the artist, and this is intended to be a Showcase release.
                    </label>
                </td>
            </tr>
<?php   } ?>

            <tr>
                <td class="label">Media:</td>
                <td>
                    <select name="media" id="media">
                        <option>---</option>
<?php   foreach (MEDIA as $Media) { ?>
                        <option value="<?= $Media ?>"<?=
                            is_array($Torrent) && isset($Torrent['Media']) && $Media == $Torrent['Media'] ? ' selected="selected"' : '' ?>><?= $Media ?></option>
<?php   } ?>
                    </select>
                </td>
            </tr>

            <tr>
                <td class="label">Format:</td>
                <td>
                    <select id="format" name="format">
                        <option>---</option>
<?php   foreach (FORMAT as $Format) { ?>
                        <option value="<?= $Format ?>"<?=
                            is_array($Torrent) && isset($Torrent['Format']) && $Format == $Torrent['Format'] ? ' selected="selected"' : '' ?>><?= $Format ?></option>
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
        $bitrate = $Torrent['Bitrate'] ?? '';
        if ($bitrate && !in_array($bitrate, ENCODING)) {
            $OtherBitrate = true;
            if (substr($bitrate, strlen($bitrate) - strlen(' (VBR)')) == ' (VBR)') {
                $bitrate = substr($bitrate, 0, strlen($bitrate) - 6);
                $VBR = true;
            }
        } else {
            $OtherBitrate = false;
        }

        // See if they're the same bitrate
        // We have to do this screwery because '(' is a regex character.
        $SimpleBitrate = explode(' ', $bitrate);
        $SimpleBitrate = $SimpleBitrate[0];
        foreach (ENCODING as $Bitrate) {
?>
                        <option value="<?= $Bitrate ?>"<?=
            ($SimpleBitrate && preg_match('/^' . $SimpleBitrate . '.*/', $Bitrate)) || ($OtherBitrate && $Bitrate == 'Other')
                ? ' selected="selected"' : '' ?> ><?= $Bitrate ?></option>
<?php   } ?>
                    </select>
                    <span id="other_bitrate_span"<?php if (!$OtherBitrate) {
echo ' class="hidden"'; } ?>>
                        <input type="text" name="other_bitrate" size="5" id="other_bitrate"<?php
                            if ($OtherBitrate) {
                                echo ' value="' . html_escape($bitrate) . '"';
                            } ?> onchange="AltBitrate();" />
                        <input type="checkbox" id="vbr" name="vbr"<?php if (isset($VBR)) {
echo ' checked="checked"'; } ?> /><label for="vbr"> (VBR)</label>
                    </span>
                </td>
            </tr>

            <tr id="upload_logs"<?= $Torrent === false ? ' class="hidden"' : '' ?>>
                <td class="label">
                    Log files:<br /><a href="javascript:;" onclick="AddLogField('<?=Logchecker::getAcceptValues()?>');" class="brackets">+</a> <a href="javascript:;" onclick="RemoveLogField();" class="brackets">&minus;</a>
                </td>
                <td id="logfields">
                    <a class="brackets" href="logchecker.php" target="_blank">Logchecker</a>
                    You may analyze your log files prior uploading to verify that they are perfect.<br />For multi-disc releases, click the "<span class="brackets">+</span>" button to add multiple log files.<br />
                    <input id="logfile_1" type="file" accept="<?=LogChecker::getAcceptValues()?>" multiple name="logfiles[]" size="50" />
                </td>
            </tr>

<?php   if ($Torrent === false) { ?>
            <tr>
                <td class="label">Multi-format uploader:</td>
                <td><input type="button" value="+" id="add_format" />&nbsp;<input type="button" style="display: none;" value="-" id="remove_format" /></td>
            </tr>
            <tr id="placeholder_row_top"></tr>
            <tr id="extra_format_placeholder"></tr>
<?php
        }
        if (is_array($Torrent) && $this->user->permitted('users_mod')) {
?>
            <tr>
                <td class="label">Log/cue:</td>
                <td>
                    <input type="checkbox" id="flac_log" name="flac_log"<?php if ($HasLog) {
echo ' checked="checked"';} ?> /> <label for="flac_log">Check this box if the torrent has, or should have, a log file.</label><br />
                    <input type="checkbox" id="flac_cue" name="flac_cue"<?php if ($HasCue) {
echo ' checked="checked"';} ?> /> <label for="flac_cue">Check this box if the torrent has, or should have, a cue file.</label><br />
                </td>
            </tr>
            <tr>
                <td class="label">Bad tags:</td>
                <td><input type="checkbox" id="bad_tags" name="bad_tags"<?php if ($BadTags) {
echo ' checked="checked"';} ?> /> <label for="bad_tags">Check this box if the torrent has bad tags.</label></td>
            </tr>
            <tr>
                <td class="label">Bad folder names:</td>
                <td><input type="checkbox" id="bad_folders" name="bad_folders"<?php if ($BadFolders) {
echo ' checked="checked"';} ?> /> <label for="bad_folders">Check this box if the torrent has bad folder names.</label></td>
            </tr>
            <tr>
                <td class="label">Bad file names:</td>
                <td><input type="checkbox" id="bad_files" name="bad_files"<?php if ($BadFiles) {
echo ' checked="checked"';} ?> /> <label for="bad_files">Check this box if the torrent has bad file names.</label></td>
            </tr>
            <tr>
                <td class="label">Missing lineage:</td>
                <td><input type="checkbox" id="missing_lineage" name="missing_lineage"<?php if ($MissingLineage) {
echo ' checked="checked"';} ?> /> <label for="missing_lineage">Check this box if the torrent is missing lineage information.</label></td>
            </tr>
            <tr>
                <td class="label">Cassette approved:</td>
                <td><input type="checkbox" id="cassette_approved" name="cassette_approved"<?php if ($CassetteApproved) {
echo ' checked="checked"';} ?> /> <label for="cassette_approved">Check this box if the torrent is an approved cassette rip.</label></td>
            </tr>
            <tr>
                <td class="label">Lossy master approved:</td>
                <td><input type="checkbox" id="lossymaster_approved" name="lossymaster_approved"<?php if ($LossymasterApproved) {
echo ' checked="checked"';} ?> /> <label for="lossymaster_approved">Check this box if the torrent is an approved lossy master.</label></td>
            </tr>
            <tr>
                <td class="label">Lossy web approved:</td>
                <td><input type="checkbox" id="lossyweb_approved" name="lossyweb_approved"<?php if ($LossywebApproved) {
echo ' checked="checked"';} ?> /> <label for="lossyweb_approved">Check this box if the torrent is an approved lossy WEB release.</label></td>
            </tr>
<?php
        }
        if ($Torrent === false) {
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
                    <input type="text" id="tags" name="tags" size="40" value=""<?=
                        $this->user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?><?= $this->Disabled ?> />
                    <br /><?= self::$twig->render('rules/tag.twig', ['on_upload' => true]) ?>
                </td>
            </tr>
            <tr>
                <td class="label">Album description:</td>
                <td>
<?php
            $groupDesc = new Textarea('album_desc', '', 60, 5);
            if ($this->DisabledFlag) {
                $groupDesc->setDisabled();
            }
?>
            <?= $groupDesc->emit() ?>
                    <p class="min_padding">Contains background information such as album history and maybe a review.</p>
                </td>
            </tr>
<?php   } /* if new torrent */ ?>
            <tr>
                <td class="label">Release description (optional):</td>
                <td>
                <?= (new Textarea('release_desc', is_array($Torrent) ? ($Torrent['TorrentDescription'] ?? '') : '', 60, 5))->emit() ?>
                    <p class="min_padding">Contains information like encoder settings or details of the ripping process. <strong class="important_text">Do not paste the ripping log here.</strong></p>
                </td>
            </tr>
        </table>
<?php
        self::$db->set_query_id($QueryID);
        return (string)ob_get_clean();
    }

    public function audiobook_form(): string {
        $Torrent = $this->Torrent;
        ob_start();
?>
        <table id="form-audiobook" cellpadding="3" cellspacing="1" border="0" class="layout border slice" width="100%">
<?php   if ($Torrent === false) { ?>
            <tr id="title_tr">
                <td class="label">Author - Title:</td>
                <td>
                    <input type="text" id="title" name="title" size="60" value="" />
                    <p class="min_padding">Should only include the author if applicable.</p>
                </td>
            </tr>
<?php   } ?>
            <tr id="year_tr">
                <td class="label">Year:</td>
                <td><input type="text" id="year" name="year" size="5" value="<?=
                    is_array($Torrent) ? html_escape($Torrent['Year']) : '' ?>" /></td>
            </tr>
            <tr>
                <td class="label">Format:</td>
                <td>
                    <select id="format" name="format">
                        <option value="">---</option>
<?php   foreach (FORMAT as $Format) { ?>
                        <option value="<?= $Format ?>"<?= is_array($Torrent) && $Format == $Torrent['Format'] ? ' selected="selected"' : '' ?>><?= $Format ?></option>
<?php   } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label">Bitrate:</td>
                <td>
                    <select id="bitrate" name="bitrate">
                        <option value="">---</option>
<?php
        if (is_array($Torrent) && (!isset($Torrent['Bitrate']) || ($Torrent['Bitrate'] && !in_array($Torrent['Bitrate'], ENCODING)))) {
            $OtherBitrate = true;
            if (substr($Torrent['Bitrate'], strlen($Torrent['Bitrate']) - strlen(' (VBR)')) == ' (VBR)') {
                $Torrent['Bitrate'] = substr($Torrent['Bitrate'], 0, strlen($Torrent['Bitrate']) - 6);
                $VBR = true;
            }
        } else {
            $OtherBitrate = false;
        }
        $SimpleBitrate = isset($Torrent['Bitrate']) ? (explode(' ', $Torrent['Bitrate']))[0] : null;
        foreach (ENCODING as $Bitrate) {
?>
                        <option value="<?= $Bitrate ?>"<?=
            ($SimpleBitrate && preg_match('/^' . $SimpleBitrate . '.*/', $Bitrate)) || ($OtherBitrate && $Bitrate == 'Other')
                ? ' selected="selected"' : '' ?>><?= $Bitrate ?></option>

<?php   } ?>
                    </select>
                    <span id="other_bitrate_span"<?php if (!$OtherBitrate) {
echo ' class="hidden"'; } ?>>
                        <input type="text" name="other_bitrate" size="5" id="other_bitrate"<?php
                            if ($OtherBitrate) {
                                echo ' value="' . html_escape($Torrent['Bitrate']) . '"';
                            } ?> onchange="AltBitrate()" />
                        <input type="checkbox" id="vbr" name="vbr"<?php if (isset($VBR)) {
echo ' checked="checked"'; } ?> /><label for="vbr"> (VBR)</label>
                    </span>
                </td>
            </tr>
<?php   if ($Torrent === false) { ?>
            <tr>
                <td class="label">Tags:</td>
                <td>
                    <input type="text" id="tags" name="tags" size="60" value=""<?=
                        $this->user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />
                    <br /><?= self::$twig->render('rules/tag.twig', ['on_upload' => true]) ?>
                </td>
            </tr>
            <tr>
                <td class="label">Description:</td>
                <td>
<?php
            $groupDesc = new Textarea('album_desc', '', 60, 5);
            if ($this->DisabledFlag) {
                $groupDesc->setDisabled();
            }
?>
            <?= $groupDesc->emit() ?>
                    <p class="min_padding">Contains information like the track listing, a review, a link to Discogs or MusicBrainz, etc.</p>
                </td>
            </tr>
<?php   } ?>
            <tr>
                <td class="label">Release description (optional):</td>
                <td>
                <?= (new Textarea('release_desc', is_array($Torrent) ? $Torrent['TorrentDescription'] : '' , 60, 5))->emit() ?>
                    <p class="min_padding">Contains information like encoder settings. For analog rips, this frequently contains lineage information.</p>
                </td>
            </tr>
        </table>
<?php
        return (string)ob_get_clean();
    }

    public function simple_form(): string {
        $Torrent = $this->Torrent;
        ob_start();
?>
        <table id="form-simple-upload" cellpadding="3" cellspacing="1" border="0" class="layout border slice" width="100%">
            <tr id="name">
<?php
        if ($Torrent === false) {
            if (CATEGORY[$this->categoryId] == 'E-Books') {
?>
                <td class="label">Author - Title:</td>
<?php       } else { ?>
                <td class="label">Title:</td>
<?php       } ?>
                <td><input type="text" id="title" name="title" size="60" value="" /></td>
            </tr>
            <tr>
                <td class="label">Tags:</td>
                <td><input type="text" id="tags" name="tags" size="60" value=""<?=
                    $this->user->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> /></td>
            </tr>
            <tr>
                <td class="label">Image (optional):</td>
                <td><input type="text" id="image" name="image" size="60" value=""<?=$this->Disabled?> />
                <br />Artwork helps improve the quality of the catalog. Please try to find a decent sized image (500x500).
<?php       if (IMAGE_HOST_BANNED) { /** @phpstan-ignore-line */ ?>
                <br />Images hosted on <?= implode(', ', IMAGE_HOST_BANNED) ?> are not allowed, please rehost first on one of <?= implode(', ', IMAGE_HOST_RECOMMENDED) ?>
<?php       } ?>
                </td>
            </tr>
            <tr>
                <td class="label">Description:</td>
                <td>
                    <?= (new Textarea('desc', '', 60, 5))->emit() ?>
                </td>
            </tr>
<?php   } ?>
        </table>
<?php
        return (string)ob_get_clean();
    }
}
