<?php
/**
 * New transcode module:
 * $_GET['filter'] determines which torrents should be shown and can be empty/all (default), uploaded, snatched or seeding
 * $_GET['target'] further filters which transcodes one would like to do and can be empty/any (default), v0, 320 or all
 *  Here, 'any' means that at least one of the formats V0 and/or 320 is missing and 'all' means that all of them are missing.
 *  'v0', etc. mean that this specific format is missing (but others might be present).
 *
 * Furthermore, there's $_GET['userid'] which allows to see the page as a different user would see it (specifically relevant for uploaded/snatched/seeding).
 */

if (!empty($_GET['userid']) && is_number($_GET['userid'])) {
    if ($Viewer->permitted('users_override_paranoia')) {
        $userId = $_GET['userid'];
    } else {
        error(403);
    }
} else {
    $userId = $Viewer->id();
}

if (empty($_GET['filter']) || !in_array($_GET['filter'], ['uploaded', 'seeding', 'snatched'])) {
    $_GET['filter'] = 'all';
}
if (empty($_GET['target']) || !in_array($_GET['target'], ['v0', '320', 'all'])) {
    $_GET['target'] = 'any';
}
$encodings = ['v0' => 'V0 (VBR)', '320' => '320'];

function transcode_init_sphql() {
    // Initializes a basic SphinxqlQuery object
    $sqlQL = new SphinxqlQuery();
    $sqlQL->select('groupid')
        ->from('better_transcode')
        ->where('logscore', 100)
        ->where_match('FLAC', 'format')
        ->order_by('RAND()')
        ->limit(0, TORRENTS_PER_PAGE, TORRENTS_PER_PAGE);
    if (in_array($_GET['target'], ['v0', '320'])) {
        // V0/320 is missing
        $sqlQL->where_match('!'.$_GET['target'], 'encoding', false);
    } elseif ($_GET['target'] === 'all') {
        // all transcodes are missing
        $sqlQL->where_match('!(v0 | 320)', 'encoding', false);
    } else {
        // any transcode is missing
        $sqlQL->where_match('!(v0 320)', 'encoding', false);
    }
    if (!empty($_GET['search'])) {
        $sqlQL->where_match($_GET['search'], '(groupname,artistname,year,taglist)');
    }
    return $sqlQL;
}

function transcode_parse_groups($groups) {
    $torrentGroups = [];
    foreach ($groups as $groupID => $group) {
        if (empty($group['Torrents'])) {
            continue;
        }
        foreach ($group['Torrents'] as $torrentID => $torrent) {
            $remIdent = "{$torrent['Media']} {$torrent['RemasterYear']} {$torrent['RemasterTitle']} {$torrent['RemasterRecordLabel']} {$torrent['RemasterCatalogueNumber']}";
            if (!isset($torrentGroups[$groupID])) {
                $torrentGroups[$groupID] = [
                    'Year' => $group['Year'],
                    'ExtendedArtists' => $group['ExtendedArtists'],
                    'Name' => $group['Name'],
                    'ReleaseType' => $group['ReleaseType'],
                    'TagList' => $group['TagList'],
                    'Editions' => []
                ];
            }
            if (!isset($torrentGroups[$groupID]['Editions'][$remIdent])) {
                if ($torrent['Remastered'] && $torrent['RemasterYear'] != 0) {
                    $editionName = $torrent['RemasterYear'];
                    $addExtra = ' - ';
                    if ($torrent['RemasterRecordLabel']) {
                        $editionName .= $addExtra.display_str($torrent['RemasterRecordLabel']);
                        $addExtra = ' / ';
                    }
                    if ($torrent['RemasterCatalogueNumber']) {
                        $editionName .= $addExtra.display_str($torrent['RemasterCatalogueNumber']);
                        $addExtra = ' / ';
                    }
                    if ($torrent['RemasterTitle']) {
                        $editionName .= $addExtra.display_str($torrent['RemasterTitle']);
                        $addExtra = ' / ';
                    }
                    $editionName .= $addExtra.display_str($torrent['Media']);
                } else {
                    $addExtra = ' / ';
                    if (!$torrent['Remastered']) {
                        $editionName = 'Original Release';
                        if ($group['RecordLabel']) {
                            $editionName .= $addExtra.$group['RecordLabel'];
                            $addExtra = ' / ';
                        }
                        if ($group['CatalogueNumber']) {
                            $editionName .= $addExtra.$group['CatalogueNumber'];
                            $addExtra = ' / ';
                        }
                    } else {
                        $editionName = 'Unknown Release(s)';
                    }
                    $editionName .= $addExtra.display_str($torrent['Media']);
                }
                $torrentGroups[$groupID]['Editions'][$remIdent] = [
                    'FlacIDs' => [],
                    'MP3s' => [],
                    'Media' => $torrent['Media'],
                    'EditionName' => $editionName,
                    'FLACIsSnatched' => false
                ];
            }

            if ($torrent['Format'] == 'MP3') {
                $torrentGroups[$groupID]['Editions'][$remIdent]['MP3s'][$torrent['Encoding']] = true;
            } elseif ($torrent['Format'] == 'FLAC' && ($torrent['LogScore'] == 100 || $torrent['Media'] != 'CD')
                    && !isset($torrentGroups[$groupID]['Editions'][$remIdent]['FlacIDs'][$torrentID])) {
                $torrentGroups[$groupID]['Editions'][$remIdent]['FlacIDs'][$torrentID] = true;
                $torrentGroups[$groupID]['Editions'][$remIdent]['FLACIsSnatched'] = $torrentGroups[$groupID]['Editions'][$remIdent]['FLACIsSnatched'] || $torrent['IsSnatched'];
            }
        }
    }
    return $torrentGroups;
}

$groups = [];
$resultCount = 0;
if (in_array($_GET['filter'], ['all', 'uploaded'])) {
    $sqlQL = transcode_init_sphql();
    if ($_GET['filter'] === 'uploaded') {
        $sqlQL->where('uploader', $userId);
    }

    $sqlQLResult = $sqlQL->sphinxquery();
    $resultCount = $sqlQLResult->get_meta('total');
    if ($resultCount != 0) {
        $results = $sqlQLResult->collect('groupid');
        $groups = Torrents::get_groups(array_values($results));
        $groups = transcode_parse_groups($groups);
    }
    unset($sqlQL, $sqlQLResult, $results);
} elseif (in_array($_GET['filter'], ['snatched', 'seeding'])) {
    // Read all snatched/seeding torrents
    $table = $_GET['filter'] === 'seeding' ? 'xbt_files_users' : 'xbt_snatched';
    $extraFilter = $_GET['filter'] === 'seeding' ? 'AND x.active = 1 AND x.Remaining = 0' : '';
    $DB->prepared_query(sprintf("
        SELECT t.GroupID, x.fid
        FROM %s AS x
            JOIN torrents AS t ON t.ID=x.fid
            JOIN torrents_group AS tg ON tg.ID = t.GroupID
        WHERE t.Format = 'FLAC'
            AND (t.LogScore = '100' OR t.Media != 'CD')
            AND tg.CategoryID = 1
            AND x.uid = ?
            %s", $table, $extraFilter), $userId);
    $snatched = $DB->to_array();
    shuffle($snatched); // randomize results
    while ($resultCount < TORRENTS_PER_PAGE && count($snatched) > 0) {
        // we throw TORRENTS_PER_PAGE results into Sphinx until we have at least TORRENTS_PER_PAGE results (or no snatches left)
        $snatchedTmp = array_slice($snatched, 0, TORRENTS_PER_PAGE);
        $snatched = array_slice($snatched, TORRENTS_PER_PAGE);

        $sqlQL = transcode_init_sphql();
        $sqlQL->where('groupid', array_map(fn($row) => $row['GroupID'], $snatchedTmp));

        $sqlQLResult = $sqlQL->sphinxquery();
        $resultsTmp = $sqlQLResult->collect('groupid');
        $groupsTmp = Torrents::get_groups(array_values($resultsTmp));
        $groupsTmp = transcode_parse_groups($groupsTmp);
        // Since we're asking Sphinxql about groups and remidents, the result can/will contain different editions that are transcodable but weren't snatched, so let's filter them out
        foreach ($groupsTmp as $groupID => $group) {
            foreach ($group['Editions'] as $remIdent => $edition) {
                $editionSnatched = false;
                foreach ($snatchedTmp as $snatchedTmpE) {
                    if (isset($edition['FlacIDs'][$snatchedTmpE['fid']])) {
                        $editionSnatched = true;
                        break;
                    }
                }
                if (!$editionSnatched || count($edition['MP3s']) === 3) {
                    unset($groupsTmp[$groupID]['Editions'][$remIdent]);
                }
            }
            $resultCount += count($groupsTmp[$groupID]['Editions']);
            if (count($groupsTmp[$groupID]['Editions']) === 0) {
                unset($groupsTmp[$groupID]);
            }
        }
        $groups = $groupsTmp + $groups;
        unset($snatchedTmp, $sqlQL, $sqlQLResult, $resultsTmp, $groupsTmp);
    }
}

$counter = [
    'total' => 0, //how many FLAC torrents can be transcoded?
    'miss_total' => 0, //how many transcodes are missing?
    'miss_V0 (VBR)' => 0, //how many V0 transcodes are missing?
    'miss_320' => 0, //how many 320 transcodes are missing?
    'ids' => []
];
foreach ($groups as $groupID => $group) {
    foreach ($group['Editions'] as $remIdent => $edition) {
        if (count($edition['FlacIDs']) === 0 //no FLAC in this group
                || (!empty($edition['MP3s']) && $_GET['target'] === 'all') //at least one transcode present when we only wanted groups containing no transcodes at all
                || (isset($encodings[$_GET['target']]) && isset($edition['MP3s'][$encodings[$_GET['target']]])) //the transcode we asked for is already there
                || count($edition['MP3s']) === 3) //all 3 transcodes are there already (this can happen due to the caching of Sphinx's better_transcode table)
        {
            unset($groups[$groupID]['Editions'][$remIdent]);
            continue;
        }
        $edition_miss = 0; //number of transcodes missing in this edition
        foreach ($encodings as $Encoding) {
            if (!isset($edition['MP3s'][$Encoding])) {
                ++$edition_miss;
                ++$counter['miss_'.$Encoding];
            }
        }
        $counter['miss_total'] += $edition_miss;
        $counter['total'] += (bool)$edition_miss;
        if ($edition_miss) {
            foreach ($edition['FlacIDs'] as $id => $_) {
                $counter['ids'][] = $id;
            }
        }
    }
}

View::show_header('Transcode Search');

function selected($val) {
    return $val ? ' selected="selected"' : '';
}
?>
<br />
<div class="thin">
    <h2>Transcodes</h2>
    <div class="linkbox">
        <a class="brackets" href="better.php?method=transcode">Transcodes</a>
        <a class="brackets" href="better.php?method=missing">Missing</a>
        <a class="brackets" href="better.php?method=single">Single Seeded</a>
    </div>
    <form class="search_form" name="transcodes" action="" method="get">
        <input type="hidden" name="method" value="transcode" />
        <table cellpadding="6" cellspacing="1" border="0" class="border" width="100%">
            <tr>
                <td class="label"><strong>Filter</strong></td>
                <td>
                    <select name="filter">
                        <option value="all"<?=selected($_GET['filter'] == 'all')?>>Show all torrents</option>
                        <option value="snatched"<?=selected($_GET['filter'] == 'snatched')?>>Snatched only</option>
                        <option value="seeding"<?=selected($_GET['filter'] == 'seeding')?>>Seeding only</option>
                        <option value="uploaded"<?=selected($_GET['filter'] == 'uploaded')?>>Uploaded only</option>
                    </select>
                    <select name="target">
                        <option value="any"<?=selected($_GET['target'] == 'any')?>>Any transcode missing</option>
                        <option value="v0"<?=selected($_GET['target'] == 'v0')?>>V0 missing</option>
                        <option value="320"<?=selected($_GET['target'] == '320')?>>320 missing</option>
                        <option value="all"<?=selected($_GET['target'] == 'all')?>>All transcodes missing</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="label"><strong>Search</strong></td>
                <td>
                    <input type="search" name="search" size="60" value="<?=(!empty($_GET['search']) ? display_str($_GET['search']) : '')?>" />
                </td>
            </tr>
            <tr><td>&nbsp;</td><td><input type="submit" value="Search" /></td></tr>
        </table>
    </form>
    <h3>About</h3>
    <div class="box pad" style="padding: 10px 10px 10px 20px;">
        <p>
            This page aims at listing <?=TORRENTS_PER_PAGE?> random transcodable perfect FLACs matching the options you selected above, but there can be more or less matches on this page. The following numbers tell you something about the torrents currently listed below and can change if you reload.<br /><br />

            Number of perfect FLACs you can transcode: <?=number_format($counter['total'])?><br />
            Number of missing transcodes: <?=number_format($counter['miss_total'])?><br />
            Number of missing V0 / 320 transcodes: <?=number_format($counter['miss_V0 (VBR)'])?> / <?=number_format($counter['miss_320'])?>
<?php
if ($Viewer->permitted('zip_downloader') && count($counter['ids']) > 1) {
    $idList = implode(',', $counter['ids']);
?>
            <br />
            <br />
            <a class="brackets" href="torrents.php?action=collector&amp;title=better&amp;ids=<?=$idList?>" onclick="return confirm('If you do not have the content, your ratio WILL be affected; be sure to check the size of all torrents before downloading.');">Download All</a>
<?php
} ?>
        </p>
    </div>
    <h3>List</h3>
    <table width="100%" class="torrent_table">
        <tr class="colhead">
            <td>Torrent</td>
            <td>V0</td>
            <td>320</td>
        </tr>
<?php
$releaseMan = new Gazelle\ReleaseType;
if ($resultCount == 0) { ?>
        <tr><td colspan="4">No results found!</td></tr>
<?php
} else {
    foreach ($groups as $groupID => $group) {
        $groupYear = $group['Year'];
        $extendedArtists = $group['ExtendedArtists'];
        $groupName = $group['Name'];
        $releaseType = $group['ReleaseType'];

        if (!empty($extendedArtists[1]) || !empty($extendedArtists[4]) || !empty($extendedArtists[5]) || !empty($extendedArtists[6])) {
            unset($extendedArtists[2]);
            unset($extendedArtists[3]);
            $artistNames = Artists::display_artists($extendedArtists);
        } else {
            $artistNames = '';
        }

        $torrentTags = new Tags($group['TagList']);

        foreach ($group['Editions'] as $remIdent => $edition) {
            // TODO: point to the correct FLAC (?)
            $flacID = array_search(true, $edition['FlacIDs']);
            $displayName = $artistNames . "<a href=\"torrents.php?id=$groupID&amp;torrentid=$flacID#torrent$flacID\" class=\"tooltip\" title=\"View torrent\" dir=\"ltr\">$groupName</a>";
            if ($groupYear > 0) {
                $displayName .= " [$groupYear]";
            }
            if ($releaseType > 0) {
                $displayName .= ' [' . $releaseMan->findNameById($releaseType) . ']';
            }
            if ($edition['FLACIsSnatched']) {
                $displayName .= ' ' . Format::torrent_label('Snatched!');
            }
?>
        <tr<?=($edition['FLACIsSnatched'] ? ' class="snatched_torrent"' : '')?>>
            <td>
                <span class="torrent_links_block">
                    <a href="torrents.php?action=download&amp;id=<?=$flacID?>&amp;torrent_pass=<?= $Viewer->announceKey() ?>" title="Download" class="brackets tooltip">DL</a>
                </span>
                <?=$displayName?>
                <div class="torrent_info"><?=$edition['EditionName']?></div>
                <div class="tags"><?=$torrentTags->format('better.php?action=transcode&tags=')?></div>
            </td>
            <td><?=(isset($edition['MP3s']['V0 (VBR)']) ? '<strong class="important_text_alt">YES</strong>' : '<strong class="important_text">NO</strong>')?></td>
            <td><?=(isset($edition['MP3s']['320']) ? '<strong class="important_text_alt">YES</strong>' : '<strong class="important_text">NO</strong>')?></td>
        </tr>
<?php
        }
    }
}
?>
    </table>
</div>
<?php
View::show_footer();
