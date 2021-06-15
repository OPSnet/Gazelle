<?php

use Gazelle\Util\SortableTableHeader;

if (!empty($_GET['searchstr']) || !empty($_GET['groupname'])) {
    $t = (new Gazelle\Manager\Torrent)->findByInfohash($_GET['searchstr'] ?? $_GET['groupname']);
    if ($t) {
        header("Location: torrents.php?id=" . $t->groupId() . "&torrentid=" . $t->id());
        exit;
    }
}

$Viewer = new Gazelle\User($LoggedUser['ID']);
$torMan = new Gazelle\Manager\Torrent;

$iconUri = STATIC_SERVER . '/styles/' . $LoggedUser['StyleName'] . '/images';
$imgTag = '<img src="' . $iconUri . '/%s.png" class="tooltip" alt="%s" title="%s"/>';
$headerMap = [
    'year'     => ['defaultSort' => 'desc', 'text' => 'Year'],
    'time'     => ['defaultSort' => 'desc', 'text' => 'Time'],
    'size'     => ['defaultSort' => 'desc', 'text' => 'Size'],
    'snatched' => ['defaultSort' => 'desc', 'text' => sprintf($imgTag, 'snatched', 'Snatches', 'Snatches')],
    'seeders'  => ['defaultSort' => 'desc', 'text' => sprintf($imgTag, 'seeders', 'Seeders', 'Seeders')],
    'leechers' => ['defaultSort' => 'desc', 'text' => sprintf($imgTag, 'leechers', 'Leechers', 'Leechers')],
];
$header = new SortableTableHeader('time', $headerMap);
$headerIcons = new SortableTableHeader('time', $headerMap, ['asc' => '', 'desc' => '']);

// Setting default search options
if (!empty($_GET['setdefault'])) {
    $UnsetList = ['page', 'setdefault'];
    $UnsetRegexp = '/(&|^)('.implode('|', $UnsetList).')=.*?(&|$)/i';

    $SiteOptions = unserialize_array($DB->scalar("
        SELECT SiteOptions FROM users_info WHERE UserID = ?
        ", $LoggedUser['ID']
    ));
    if (!isset($SiteOptions['HttpsTracker'])) {
        $SiteOptions['HttpsTracker'] = true;
    }

    $SiteOptions['DefaultSearch'] = preg_replace($UnsetRegexp, '', $_SERVER['QUERY_STRING']);
    $DB->prepared_query("
        UPDATE users_info SET
            SiteOptions = ?
        WHERE UserID = ?
        ", serialize($SiteOptions), $LoggedUser['ID']
    );
    $Cache->begin_transaction('user_info_heavy_'.$LoggedUser['ID']);
    $Cache->update_row(false, ['DefaultSearch' => $SiteOptions['DefaultSearch']]);
    $Cache->commit_transaction(0);

// Clearing default search options
} elseif (!empty($_GET['cleardefault'])) {
    $SiteOptions = unserialize_array($DB->scalar("
        SELECT SiteOptions FROM users_info WHERE UserID = ?
        ", $LoggedUser['ID']
    ));
    $SiteOptions['DefaultSearch'] = '';

    $DB->prepared_query("
        UPDATE users_info SET
            SiteOptions = ?
        WHERE UserID = ?
        ", serialize($SiteOptions), $LoggedUser['ID']
    );
    $Cache->begin_transaction('user_info_heavy_'.$LoggedUser['ID']);
    $Cache->update_row(false, ['DefaultSearch' => '']);
    $Cache->commit_transaction(0);

// Use default search options
} elseif (empty($_SERVER['QUERY_STRING']) || (count($_GET) === 1 && isset($_GET['page']))) {
    if (!empty($LoggedUser['DefaultSearch'])) {
        if (!empty($_GET['page'])) {
            $Page = $_GET['page'];
            parse_str($LoggedUser['DefaultSearch'], $_GET);
            $_GET['page'] = $Page;
        } else {
            parse_str($LoggedUser['DefaultSearch'], $_GET);
        }
    }
}
// Terms were not submitted via the search form
if (isset($_GET['searchsubmit'])) {
    $GroupResults = !empty($_GET['group_results']);
} else {
    $GroupResults = !isset($LoggedUser['DisableGrouping2']) || $LoggedUser['DisableGrouping2'] == 0;
}

$paginator = new Gazelle\Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$Search = new Gazelle\Search\Torrent($GroupResults, $header->getSortKey(), $header->getOrderDir(), $paginator->page(), TORRENTS_PER_PAGE);
$Results = $Search->query($_GET);
$RealNumResults = $NumResults = $Search->record_count();
if (!check_perms('site_search_many')) {
    $NumResults = min($NumResults, SPHINX_MAX_MATCHES);
}
$paginator->setTotal($NumResults);

$HideFilter = isset($LoggedUser['ShowTorFilter']) && $LoggedUser['ShowTorFilter'] == 0;
// This is kinda ugly, but the enormous if paragraph was really hard to read
$AdvancedSearch = !empty($_GET['action']) && $_GET['action'] == 'advanced';
$AdvancedSearch |= !empty($LoggedUser['SearchType']) && (empty($_GET['action']) || $_GET['action'] == 'advanced');
$AdvancedSearch &= check_perms('site_advanced_search');
if ($AdvancedSearch) {
    $Action = 'action=advanced';
    $HideBasic = ' hidden';
    $HideAdvanced = '';
} else {
    $Action = 'action=basic';
    $HideBasic = '';
    $HideAdvanced = ' hidden';
}

if (Format::form('remastertitle', true) == ''
    && Format::form('remasteryear', true) == ''
    && Format::form('remasterrecordlabel', true) == ''
    && Format::form('remastercataloguenumber', true) == ''
) {
    $Hidden = ' hidden';
} else {
    $Hidden = '';
}

$releaseTypes = (new Gazelle\ReleaseType)->list();

View::show_header('Browse Torrents', 'browse');
?>
<div class="thin widethin">
<div class="header">
    <h2>Torrents</h2>
</div>
<form class="search_form" name="torrents" method="get" action="" onsubmit="$(this).disableUnset();">
<div class="box filter_torrents">
    <div class="head">
        <strong>
            <span id="ft_basic_text" class="<?=$HideBasic?>">Basic /</span>
            <span id="ft_basic_link" class="<?=$HideAdvanced?>"><a href="#" onclick="return toggleTorrentSearch('basic');">Basic</a> /</span>
            <span id="ft_advanced_text" class="<?=$HideAdvanced?>">Advanced</span>
            <span id="ft_advanced_link" class="<?=$HideBasic?>"><a href="#" onclick="return toggleTorrentSearch('advanced');">Advanced</a></span>
            Search
        </strong>
        <span style="float: right;">
            <a href="#" onclick="return toggleTorrentSearch(0);" id="ft_toggle" class="brackets"><?=$HideFilter ? 'Show' : 'Hide'?></a>
        </span>
    </div>
    <div id="ft_container" class="pad<?=$HideFilter ? ' hidden' : ''?>">
        <table class="layout">
            <tr id="artist_name" class="ftr_advanced<?=$HideAdvanced?>">
                <td class="label">Artist name:</td>
                <td colspan="3" class="ft_artistname">
                    <input type="search" spellcheck="false" size="40" name="artistname" class="inputtext smaller fti_advanced" value="<?php Format::form('artistname'); ?>" />
                </td>
            </tr>
            <tr id="album_torrent_name" class="ftr_advanced<?=$HideAdvanced?>">
                <td class="label">Album/Torrent name:</td>
                <td colspan="3" class="ft_groupname">
                    <input type="search" spellcheck="false" size="40" name="groupname" class="inputtext smaller fti_advanced" value="<?php Format::form('groupname'); ?>" />
                </td>
            </tr>
            <tr id="record_label" class="ftr_advanced<?=$HideAdvanced?>">
                <td class="label">Record label:</td>
                <td colspan="3" class="ft_recordlabel">
                    <input type="search" spellcheck="false" size="40" name="recordlabel" class="inputtext smaller fti_advanced" value="<?php Format::form('recordlabel'); ?>" />
                </td>
            </tr>
            <tr id="catalogue_number_year" class="ftr_advanced<?=$HideAdvanced?>">
                <td class="label">Catalogue number:</td>
                <td class="ft_cataloguenumber">
                    <input type="search" size="40" name="cataloguenumber" class="inputtext smallest fti_advanced" value="<?php Format::form('cataloguenumber'); ?>" />
                </td>
                <td class="label">Year:</td>
                <td class="ft_year">
                    <input type="search" name="year" class="inputtext smallest fti_advanced" value="<?php Format::form('year'); ?>" size="4" />
                </td>
            </tr>
            <tr id="edition_expand" class="ftr_advanced<?=$HideAdvanced?>">
                <td colspan="4" class="center ft_edition_expand"><a href="#" class="brackets" onclick="ToggleEditionRows(); return false;">Click here to toggle searching for specific remaster information</a></td>
            </tr>
            <tr id="edition_title" class="ftr_advanced<?=$HideAdvanced . $Hidden?>">
                <td class="label">Edition title:</td>
                <td class="ft_remastertitle">
                    <input type="search" spellcheck="false" size="40" name="remastertitle" class="inputtext smaller fti_advanced" value="<?php Format::form('remastertitle'); ?>" />
                </td>
                <td class="label">Edition year:</td>
                <td class="ft_remasteryear">
                    <input type="search" name="remasteryear" class="inputtext smallest fti_advanced" value="<?php Format::form('remasteryear'); ?>" size="4" />
                </td>
            </tr>
            <tr id="edition_label" class="ftr_advanced<?=$HideAdvanced . $Hidden?>">
                <td class="label">Edition release label:</td>
                <td colspan="3" class="ft_remasterrecordlabel">
                    <input type="search" spellcheck="false" size="40" name="remasterrecordlabel" class="inputtext smaller fti_advanced" value="<?php Format::form('remasterrecordlabel'); ?>" />
                </td>
            </tr>
            <tr id="edition_catalogue" class="ftr_advanced<?=$HideAdvanced . $Hidden?>">
                <td class="label">Edition catalogue number:</td>
                <td colspan="3" class="ft_remastercataloguenumber">
                    <input type="search" size="40" name="remastercataloguenumber" class="inputtext smallest fti_advanced" value="<?php Format::form('remastercataloguenumber'); ?>" />
                </td>
            </tr>
            <tr id="file_list" class="ftr_advanced<?=$HideAdvanced?>">
                <td class="label">File list:</td>
                <td colspan="3" class="ft_filelist">
                    <input type="search" spellcheck="false" size="40" name="filelist" class="inputtext fti_advanced" value="<?php Format::form('filelist'); ?>" />
                </td>
            </tr>
            <tr id="torrent_description" class="ftr_advanced<?=$HideAdvanced?>">
                <td class="label"><span title="Search torrent descriptions (not group information)" class="tooltip">Torrent description:</span></td>
                <td colspan="3" class="ft_description">
                    <input type="search" spellcheck="false" size="40" name="description" class="inputtext fti_advanced" value="<?php Format::form('description'); ?>" />
                </td>
            </tr>
            <tr id="rip_specifics" class="ftr_advanced<?=$HideAdvanced?>">
                <td class="label">Rip specifics:</td>
                <td class="nobr ft_ripspecifics" colspan="3">
                    <select id="bitrate" name="encoding" class="ft_bitrate fti_advanced">
                        <option value="">Bitrate</option>
<?php    foreach ($Bitrates as $BitrateName) { ?>
                        <option value="<?=display_str($BitrateName); ?>"<?php Format::selected('encoding', $BitrateName); ?>><?=display_str($BitrateName); ?></option>
<?php    } ?>            </select>

                    <select name="format" class="ft_format fti_advanced">
                        <option value="">Format</option>
<?php    foreach ($Formats as $FormatName) { ?>
                        <option value="<?=display_str($FormatName); ?>"<?php Format::selected('format', $FormatName); ?>><?=display_str($FormatName); ?></option>
<?php    } ?>            </select>
                    <select name="media" class="ft_media fti_advanced">
                        <option value="">Media</option>
<?php    foreach ($Media as $MediaName) { ?>
                        <option value="<?=display_str($MediaName); ?>"<?php Format::selected('media', $MediaName); ?>><?=display_str($MediaName); ?></option>
<?php    } ?>
                    </select>
                    <select name="releasetype" class="ft_releasetype fti_advanced">
                        <option value="">Release type</option>
<?php    foreach ($releaseTypes as $ID=>$Type) { ?>
                        <option value="<?=display_str($ID); ?>"<?php Format::selected('releasetype', $ID); ?>><?=display_str($Type); ?></option>
<?php    } ?>
                    </select>
                </td>
            </tr>
            <tr id="misc" class="ftr_advanced<?=$HideAdvanced?>">
                <td class="label">Miscellaneous:</td>
                <td class="nobr ft_misc" colspan="3">
                    <select name="haslog" class="ft_haslog fti_advanced">
                        <option value="">Rip Log File</option>
                        <option value="1"<?php Format::selected('haslog', '1'); ?>>Has log file</option>
                        <option value="0"<?php Format::selected('haslog', '0'); ?>>No log file</option>
                        <option value="99"<?php Format::selected('haslog', '99'); ?>>Scores 99%</option>
                        <option value="100"<?php Format::selected('haslog', '100'); ?>>Scores 100%</option>
                        <option value="-1"<?php Format::selected('haslog', '-1'); ?>>&lt;100%/Unscored</option>
                    </select>
                    <select name="hascue" class="ft_hascue fti_advanced">
                        <option value="">Cue File</option>
                        <option value="1"<?php Format::selected('hascue', 1); ?>>Has Cue</option>
                        <option value="0"<?php Format::selected('hascue', 0); ?>>No Cue</option>
                    </select>
                    <select name="scene" class="ft_scene fti_advanced">
                        <option value="">Scene</option>
                        <option value="1"<?php Format::selected('scene', 1); ?>>Is Scene</option>
                        <option value="0"<?php Format::selected('scene', 0); ?>>Not Scene</option>
                    </select>
                    <select name="vanityhouse" class="ft_vanityhouse fti_advanced">
                        <option value="">Vanity House</option>
                        <option value="1"<?php Format::selected('vanityhouse', 1); ?>>Is Vanity</option>
                        <option value="0"<?php Format::selected('vanityhouse', 0); ?>>Not Vanity</option>
                    </select>
                    <select name="freetorrent" class="ft_freetorrent fti_advanced">
                        <option value="">Leech Status</option>
                        <option value="1"<?php Format::selected('freetorrent', 1); ?>>Freeleech</option>
                        <option value="2"<?php Format::selected('freetorrent', 2); ?>>Neutral Leech</option>
                        <option value="3"<?php Format::selected('freetorrent', 3); ?>>Either</option>
                        <option value="0"<?php Format::selected('freetorrent', 0); ?>>Normal</option>
                    </select>
                </td>
            </tr>
            <tr id="search_terms" class="ftr_basic<?=$HideBasic?>">
                <td class="label">Search terms:</td>
                <td colspan="3" class="ftb_searchstr">
                    <input type="search" spellcheck="false" size="40" name="searchstr" class="inputtext fti_basic" value="<?php Format::form('searchstr'); ?>" />
                </td>
            </tr>
            <tr id="tagfilter">
                <td class="label"><span title="Use !tag to exclude tag" class="tooltip">Tags (comma-separated):</span></td>
                <td colspan="3" class="ft_taglist">
                    <input type="search" size="40" id="tags" name="taglist" class="inputtext smaller" value="<?= display_str($Search->get_terms('taglist')) ?>"<?=
                            $Viewer->hasAutocomplete('other') ? ' data-gazelle-autocomplete="true"' : '' ?> />&nbsp;
                    <input type="radio" name="tags_type" id="tags_type0" value="0"<?php Format::selected('tags_type', 0, 'checked'); ?> /><label for="tags_type0"> Any</label>&nbsp;&nbsp;
                    <input type="radio" name="tags_type" id="tags_type1" value="1"<?php Format::selected('tags_type', 1, 'checked'); ?> /><label for="tags_type1"> All</label>
                </td>
            </tr>
            <tr id="order">
                <td class="label">Order by:</td>
                <td colspan="3" class="ft_order">
                    <select name="order" style="width: auto;" class="ft_order_by">
                        <option value="time"<?php Format::selected('order', 'time'); ?>>Time added</option>
                        <option value="year"<?php Format::selected('order', 'year'); ?>>Year</option>
                        <option value="size"<?php Format::selected('order', 'size'); ?>>Size</option>
                        <option value="snatched"<?php Format::selected('order', 'snatched'); ?>>Snatched</option>
                        <option value="seeders"<?php Format::selected('order', 'seeders'); ?>>Seeders</option>
                        <option value="leechers"<?php Format::selected('order', 'leechers'); ?>>Leechers</option>
                        <option value="random"<?php Format::selected('order', 'random'); ?>>Random</option>
                    </select>
                    <select name="sort" class="ft_order_way">
                        <option value="desc"<?php Format::selected('sort', 'desc'); ?>>Descending</option>
                        <option value="asc"<?php Format::selected('sort', 'asc'); ?>>Ascending</option>
                    </select>
                </td>
            </tr>
            <tr id="search_group_results">
                <td class="label">
                    <label for="group_results">Group by release:</label>
                </td>
                <td colspan="3" class="ft_group_results">
                    <input type="checkbox" value="1" name="group_results" id="group_results"<?=$GroupResults ? ' checked="checked"' : ''?> />
                </td>
            </tr>
        </table>
        <table class="layout cat_list ft_cat_list">
<?php
$x = 0;
reset($Categories);
foreach ($Categories as $CatKey => $CatName) {
    if ($x % 7 == 0) {
        if ($x > 0) {
?>
            </tr>
<?php        } ?>
            <tr>
<?php
    }
    $x++;
?>
                <td>
                    <input type="checkbox" name="filter_cat[<?=($CatKey + 1)?>]" id="cat_<?=($CatKey + 1)?>" value="1"<?php if (isset($_GET['filter_cat'][$CatKey + 1])) { ?> checked="checked"<?php } ?> />
                    <label for="cat_<?=($CatKey + 1)?>"><?=$CatName?></label>
                </td>
<?php
}
?>
            </tr>
        </table>
        <table class="layout cat_list<?php if (empty($LoggedUser['ShowTags'])) { ?> hidden<?php } ?>" id="taglist">
            <tr>
<?php
$tagMan = new Gazelle\Manager\Tag;
$GenreTags = $tagMan->genreList();
$x = 0;
foreach ($GenreTags as $Tag) {
?>
                <td width="12.5%"><a href="#" onclick="add_tag('<?=$Tag?>'); return false;"><?=$Tag?></a></td>
<?php
    $x++;
    if ($x % 7 == 0) {
?>
            </tr>
            <tr>
<?php
    }
}
if ($x % 7 != 0) { // Padding
?>
                <td colspan="<?=(7 - ($x % 7))?>"> </td>
<?php } ?>
            </tr>
        </table>
        <table class="layout cat_list" width="100%">
            <tr>
                <td>
                    <a class="brackets" href="random.php?action=torrent">Random Torrent</a>
                    <a class="brackets" href="random.php?action=artist">Random Artist</a>
                </td>
                <td class="label">
                    <a class="brackets" href="#" onclick="$('#taglist').gtoggle(); if (this.innerHTML == 'View tags') { this.innerHTML = 'Hide tags'; } else { this.innerHTML = 'View tags'; }; return false;"><?=(empty($LoggedUser['ShowTags']) ? 'View tags' : 'Hide tags')?></a>
                </td>
            </tr>
        </table>
        <div class="submit ft_submit">
            <span style="float: left;"><!--
                --><?=number_format($RealNumResults)?> Results
                <?=!check_perms('site_search_many') ? "(Showing first $NumResults matches)" : ""?>
            </span>
            <input type="submit" value="Filter torrents" />
            <input type="hidden" name="action" id="ft_type" value="<?=($AdvancedSearch ? 'advanced' : 'basic')?>" />
            <input type="hidden" name="searchsubmit" value="1" />
            <input type="button" value="Reset" onclick="location.href = 'torrents.php<?php if (isset($_GET['action']) && $_GET['action'] === 'advanced') { ?>?action=advanced<?php } ?>'" />
            &nbsp;&nbsp;
<?php    if ($Search->has_filters()) { ?>
            <input type="submit" name="setdefault" value="Make default" />
<?php
    }

    if (!empty($LoggedUser['DefaultSearch'])) {
?>
            <input type="submit" name="cleardefault" value="Clear default" />
<?php    } ?>
        </div>
    </div>
</div>
</form>
<?php if ($NumResults == 0) { ?>
<div class="box pad" align="center">
    <h2>Your search did not match anything.</h2>
    <p>Make sure all names are spelled correctly, or try making your search less specific.</p>
<?php
    $DB->prepared_query("
        SELECT tags.Name
        FROM xbt_snatched AS s
        INNER JOIN torrents AS t ON (t.ID = s.fid)
        INNER JOIN torrents_group AS g ON (t.GroupID = g.ID)
        INNER JOIN torrents_tags AS tt ON (tt.GroupID = g.ID)
        INNER JOIN tags ON (tags.ID = tt.TagID)
        WHERE g.CategoryID = 1
            AND tags.Uses > 10
            AND s.uid = ?
        GROUP BY tt.TagID
        ORDER BY ((count(tags.Name) - 2) * (sum(tt.PositiveVotes) - sum(tt.NegativeVotes))) / (tags.Uses * 0.8) DESC
        LIMIT 8
        ", $LoggedUser['ID']
    );
    $list = $DB->collect(0);
    $link = [];
    foreach ($list as $tag) {
        $link[] = "<a href='torrents.php?taglist={$tag}'>{$tag}</a>";
    }
    if ($link) {
?>
    <p>You might like: <?= implode(" &mdash; ", $link) ?></p>
<?php } ?>
</div>
</div>
<?php
    View::show_footer();
    exit;
}

$bookmark = new \Gazelle\Bookmark;
?>

<?= $paginator->linkbox() ?>

<table class="torrent_table cats <?=$GroupResults ? 'grouping' : 'no_grouping'?> m_table" id="torrent_table">
    <tr class="colhead">
<?php    if ($GroupResults) { ?>
        <td class="small"></td>
<?php    } ?>
        <td class="small cats_col"></td>
        <td class="m_th_left m_th_left_collapsable nobr" width="100%">Name / <?= $header->emit('year') ?></td>
        <td>Files</td>
        <td class="nobr"><?= $header->emit('time') ?></td>
        <td class="nobr"><?= $header->emit('size') ?></td>
        <td class="sign nobr snatches"><?= $headerIcons->emit('snatched') ?></td>
        <td class="sign nobr seeders"><?= $headerIcons->emit('seeders') ?></td>
        <td class="sign nobr leechers"><?= $headerIcons->emit('leechers') ?></td>
    </tr>
<?php

// Start printing torrent list
$tgroupMan = new Gazelle\Manager\TGroup;
foreach ($Results as $GroupID) {
    $tgroup = $tgroupMan->findById($GroupID);
    if (is_null($tgroup)) {
        continue;
    }
    $Torrents = $tgroup->setViewer($Viewer)->torrentList();
    if (empty($Torrents)) {
        continue;
    }
    $GroupInfo = $tgroup->info();

    $CategoryID = $GroupInfo['CategoryID'];
    $GroupYear = $GroupInfo['Year'];
    $GroupCatalogueNumber = $GroupInfo['CatalogueNumber'];
    $GroupName = $GroupInfo['Name'];
    $GroupRecordLabel = $GroupInfo['RecordLabel'];
    $ReleaseType = $GroupInfo['ReleaseType'];
    if ($GroupResults) {
        $GroupTime = $MaxSize = $TotalLeechers = $TotalSeeders = $TotalSnatched = 0;
        foreach ($Torrents as $T) {
            $GroupTime = max($GroupTime, strtotime($T['Time']));
            $MaxSize = max($MaxSize, $T['Size']);
            $TotalLeechers += $T['Leechers'];
            $TotalSeeders += $T['Seeders'];
            $TotalSnatched += $T['Snatched'];
        }
    }

    $TorrentTags = new Tags(implode(' ', (array_column($GroupInfo['tags'], 'name'))));

    $DisplayName = $tgroup->artistHtml() . ' - ';
    $SnatchedGroupClass = $GroupInfo['Flags']['IsSnatched'] ? ' snatched_group' : '';

    if ($GroupResults && (count($Torrents) > 1 || isset($GroupedCategories[$CategoryID - 1]))) {
        // These torrents are in a group
        $DisplayName .= "<a href=\"torrents.php?id=$GroupID\" class=\"tooltip\" title=\"View torrent group\" dir=\"ltr\">$GroupName</a>";
        if ($GroupYear > 0) {
            $DisplayName .= " [$GroupYear]";
        }
        if ($GroupInfo['VanityHouse']) {
            $DisplayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
        }
        $DisplayName .= ' [' . $releaseTypes[$ReleaseType] . ']';
?>
    <tr class="group groupid_<?=$GroupID?>_header<?=$SnatchedGroupClass?>">
<?php
$ShowGroups = !(!empty($LoggedUser['TorrentGrouping']) && $LoggedUser['TorrentGrouping'] == 1);
?>
        <td class="td_collapse center m_td_left">
            <div id="showimg_<?=$GroupID?>" class="<?=($ShowGroups ? 'hide' : 'show')?>_torrents">
                <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?=$GroupID?>, this, event)" title="Collapse this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all groups on this page."></a>
            </div>
        </td>
        <td class="center cats_col">
            <div title="<?=$TorrentTags->title()?>" class="tooltip <?=Format::css_category($CategoryID)?> <?=$TorrentTags->css_name()?>">
            </div>
        </td>
        <td colspan="2" class="td_info big_info">
<?php    if ($LoggedUser['CoverArt']) { ?>
            <div class="group_image float_left clear">
                <?php ImageTools::cover_thumb($GroupInfo['WikiImage'], $GroupInfo['CategoryID']) ?>
            </div>
<?php    } ?>
            <div class="group_info clear">
                <?=$DisplayName?>
<?php    if ($bookmark->isTorrentBookmarked($LoggedUser['ID'], $GroupID)) { ?>
                <span class="remove_bookmark float_right">
                    <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Unbookmark('torrent', <?=$GroupID?>, 'Bookmark'); return false;">Remove bookmark</a>
                </span>
<?php    } else { ?>
                <span class="add_bookmark float_right">
                    <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Bookmark('torrent', <?=$GroupID?>, 'Remove bookmark'); return false;">Bookmark</a>
                </span>
<?php    } ?>
                <br />
                <div class="tags"><?=$TorrentTags->format('torrents.php?'.$Action.'&amp;taglist=')?></div>
            </div>
        </td>
        <td class="td_time nobr"><?=time_diff($GroupTime, 1)?></td>
        <td class="td_size number_column nobr"><?=Format::get_size($MaxSize)?> (Max)</td>
        <td class="td_snatched number_column m_td_right"><?=number_format($TotalSnatched)?></td>
        <td class="td_seeders number_column<?=($TotalSeeders == 0 ? ' r00' : '')?> m_td_right"><?=number_format($TotalSeeders)?></td>
        <td class="td_leechers number_column m_td_right"><?=number_format($TotalLeechers)?></td>
    </tr>
<?php
        $LastRemasterYear = '-';
        $LastRemasterTitle = '';
        $LastRemasterRecordLabel = '';
        $LastRemasterCatalogueNumber = '';
        $LastMedia = '';

        $EditionID = 0;
        $FirstUnknown = null;

        foreach ($Torrents as $TorrentID => $Data) {
            // All of the individual torrents in the group
            if ($Data['is_deleted']) {
                continue;
            }

            if ($Data['Remastered'] && !$Data['RemasterYear']) {
                $FirstUnknown = !isset($FirstUnknown);
            }
            $SnatchedTorrentClass = $Data['IsSnatched'] ? ' snatched_torrent' : '';
            $Reported = $torMan->hasReport($TorrentID);

            if (isset($GroupedCategories[$CategoryID - 1])
                    && ($Data['RemasterTitle'] != $LastRemasterTitle
                        || $Data['RemasterYear'] != $LastRemasterYear
                        || $Data['RemasterRecordLabel'] != $LastRemasterRecordLabel
                        || $Data['RemasterCatalogueNumber'] != $LastRemasterCatalogueNumber)
                    || (isset($FirstUnknown) && $FirstUnknown)
                    || $Data['Media'] != $LastMedia
            ) {
                $EditionID++;

?>
    <tr class="group_torrent groupid_<?=$GroupID?> edition<?=$SnatchedGroupClass . (!empty($LoggedUser['TorrentGrouping']) && $LoggedUser['TorrentGrouping'] == 1 ? ' hidden' : '')?>">
        <td colspan="9" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=$GroupID?>, <?=$EditionID?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?=Torrents::edition_string($Data, $GroupInfo)?></strong></td>
    </tr>
<?php
            }
            $LastRemasterTitle = $Data['RemasterTitle'];
            $LastRemasterYear = $Data['RemasterYear'];
            $LastRemasterRecordLabel = $Data['RemasterRecordLabel'];
            $LastRemasterCatalogueNumber = $Data['RemasterCatalogueNumber'];
            $LastMedia = $Data['Media'];
?>
    <tr class="group_torrent groupid_<?=$GroupID?> edition_<?=$EditionID?><?=$SnatchedTorrentClass . $SnatchedGroupClass . (!empty($LoggedUser['TorrentGrouping']) && $LoggedUser['TorrentGrouping'] == 1 ? ' hidden' : '')?>">
        <td class="td_info" colspan="3">
            <?= $Twig->render('torrent/action.twig', [
                'can_fl' => Torrents::can_use_token($Data),
                'key'    => $LoggedUser['torrent_pass'],
                't'      => $Data,
            ]) ?>
            &raquo; <a href="torrents.php?id=<?=$GroupID?>&amp;torrentid=<?=$TorrentID?>"><?=Torrents::torrent_info($Data)?>
<?php if ($Reported) { ?>
            / <strong class="torrent_label tl_reported">Reported</strong>
<?php } ?></a>
        </td>
        <td class="td_file_count"><?=$Data['FileCount']?></td>
        <td class="td_time nobr"><?=time_diff($Data['Time'], 1)?></td>
        <td class="td_size number_column nobr"><?=Format::get_size($Data['Size'])?></td>
        <td class="td_snatched number_column m_td_right"><?=number_format($Data['Snatched'])?></td>
        <td class="td_seeders number_column<?=($Data['Seeders'] == 0) ? ' r00' : ''?> m_td_right"><?=number_format($Data['Seeders'])?></td>
        <td class="td_leechers number_column m_td_right"><?=number_format($Data['Leechers'])?></td>
    </tr>
<?php
        }
    } else {
        // Viewing a type that does not require grouping

        $TorrentID = key($Torrents);
        $Data = current($Torrents);
        $DisplayName .= "<a href=\"torrents.php?id=$GroupID&amp;torrentid=$TorrentID#torrent$TorrentID\" class=\"tooltip\" title=\"View torrent\" dir=\"ltr\">$GroupName</a>";
        if (isset($GroupedCategories[$CategoryID - 1])) {
            if ($GroupYear) {
                $DisplayName .= " [$GroupYear]";
            }
            if ($CategoryID == 1 && $ReleaseType > 0) {
                $DisplayName .= ' [' . $releaseTypes[$ReleaseType] . ']';
            }
            $ExtraInfo = Torrents::torrent_info($Data, true, true);
        } elseif ($Data['IsSnatched']) {
            $ExtraInfo = Format::torrent_label('Snatched!');
        } else {
            $ExtraInfo = '';
        }
        $SnatchedTorrentClass = $Data['IsSnatched'] ? ' snatched_torrent' : '';
?>
    <tr class="torrent<?=$SnatchedTorrentClass . $SnatchedGroupClass?>">
<?php   if ($GroupResults) { ?>
        <td></td>
<?php   } ?>
        <td class="center cats_col m_cats_col m_td_left">
            <div title="<?=$TorrentTags->title()?>" class="tooltip <?=Format::css_category($CategoryID)?> <?=$TorrentTags->css_name()?>"></div>
        </td>
        <td class="td_info big_info">
<?php   if ($LoggedUser['CoverArt']) { ?>
            <div class="group_image float_left clear">
                <?=ImageTools::cover_thumb($GroupInfo['WikiImage'], $CategoryID) ?>
            </div>
<?php   } ?>
            <div class="group_info clear">
                <?= $Twig->render('torrent/action.twig', [
                    'can_fl' => Torrents::can_use_token($Data),
                    'key'    => $LoggedUser['torrent_pass'],
                    't'      => $Data,
                ]) ?>
                <?=$DisplayName?>
                <div class="torrent_info"><?=$ExtraInfo?></div>
                <div class="tags"><?=$TorrentTags->format("torrents.php?$Action&amp;taglist=")?></div>
            </div>
        </td>
        <td class="td_file_count"><?=$Data['FileCount']?></td>
        <td class="td_time nobr"><?=time_diff($Data['Time'], 1)?></td>
        <td class="td_size number_column nobr"><?=Format::get_size($Data['Size'])?></td>
        <td class="td_snatched m_td_right number_column"><?=number_format($Data['Snatched'])?></td>
        <td class="td_seeders m_td_right number_column<?=($Data['Seeders'] == 0) ? ' r00' : ''?>"><?=number_format($Data['Seeders'])?></td>
        <td class="td_leechers m_td_right number_column"><?=number_format($Data['Leechers'])?></td>
    </tr>
<?php
    }
}
?>
</table>
<?= $paginator->linkbox() ?>
</div>
<?php
View::show_footer();
