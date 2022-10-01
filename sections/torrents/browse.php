<?php

use Gazelle\Util\SortableTableHeader;

$tagMan = new Gazelle\Manager\Tag;
$tgMan  = (new Gazelle\Manager\TGroup)->setViewer($Viewer);
$torMan = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$snatcher = new Gazelle\User\Snatch($Viewer);

if (!empty($_GET['searchstr']) || !empty($_GET['groupname'])) {
    $torrent = $torMan->findByInfohash($_GET['searchstr'] ?? $_GET['groupname']);
    if ($torrent) {
        header('Location: ' . $torrent->location());
        exit;
    }
}

$imgTag = '<img src="' . (new Gazelle\User\Stylesheet($Viewer))->imagePath() . '%s.png" class="tooltip" alt="%s" title="%s"/>';
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

if (isset($_GET['setdefault'])) {
    // Setting default search options, remove page and setdefault params
    $clear = '/(?:&|^)(?:page|setdefault)=.*?(?:&|$)/';
    $Viewer->modifyOption('DefaultSearch', preg_replace($clear, '', $_SERVER['QUERY_STRING']));
} elseif (isset($_GET['cleardefault'])) {
    // Clearing default search options
    $Viewer->modifyOption('DefaultSearch', null);
} elseif (empty($_SERVER['QUERY_STRING']) && $Viewer->option('DefaultSearch')) {
    // Use default search options
    $page = $_GET['page'] ?? false;
    parse_str($Viewer->option('DefaultSearch'), $_GET);
    if ($page !== false) {
        $_GET['page'] = $page;
    }
}

// Terms were not submitted via the search form
if (isset($_GET['searchsubmit'])) {
    $GroupResults = !empty($_GET['group_results']);
} else {
    $GroupResults = ($Viewer->option('DisableGrouping2') ?? 0) === 0;
}

$paginator = new Gazelle\Util\Paginator(TORRENTS_PER_PAGE, (int)($_GET['page'] ?? 1));
$Search = new Gazelle\Search\Torrent(
    $GroupResults,
    $header->getSortKey(),
    $header->getOrderDir(),
    $paginator->page(),
    TORRENTS_PER_PAGE,
    $Viewer->permitted('site_search_many')
);
$Results = $Search->query($_GET);
$RealNumResults = $NumResults = $Search->record_count();
if (!$Viewer->permitted('site_search_many')) {
    $NumResults = min($NumResults, SPHINX_MAX_MATCHES);
}
$paginator->setTotal($NumResults);

/* if the user has the privilege of advanced search, we prioritze the url param 'action'
 * if it is present, otherwise we fall back to their personal preference.
 */
$AdvancedSearch = $Viewer->permitted('site_advanced_search')
    && ($_GET['action'] ?? ['basic ', 'advanced'][$Viewer->option('SearchType') ?? 0]) == 'advanced';

if ($AdvancedSearch) {
    $hideAdvanced = '';
    $searchMode = 'advanced';
    $toggleSearchMode = 'basic';
} else {
    $hideAdvanced = ' hidden';
    $searchMode = 'basic';
    $toggleSearchMode = 'advanced';
}

echo $Twig->render('torrent/browse-header.twig', [
    'input'         => $_GET,
    'filtered'      => $Search->has_filters(),
    'grouped'       => $GroupResults,
    'hide_remaster' => ($_GET['remastertitle'] ?? $_GET['remasteryear'] ?? $_GET['remastercataloguenumber'] ?? '') != ''
        ? '' : ' hidden',
    'hide_advanced' => $hideAdvanced,
    'release_type'  => (new Gazelle\ReleaseType)->list(),
    'results_total' => $RealNumResults,
    'results_shown' => $NumResults,
    'search_plus'   => $AdvancedSearch,
    'search_toggle' => $toggleSearchMode,
    'show_search'   => $Viewer->option('ShowTorFilter') ?? true,
    'tag_default'   => $tagMan->genreList(),
    'tag_list'      => $Search->get_terms('taglist'),
    'viewer'        => $Viewer,
]);

if ($NumResults == 0) {
    echo $Twig->render('torrent/search-none.twig', [
        'list' => $tagMan->userTopTagList($Viewer->id()),
    ]);
    exit;
}

$releaseTypes = (new Gazelle\ReleaseType)->list();
$bookmark = new \Gazelle\User\Bookmark($Viewer);
$imgProxy = (new Gazelle\Util\ImageProxy)->setViewer($Viewer);

echo $paginator->linkbox()
?>

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
$groupsClosed = (bool)$Viewer->option('TorrentGrouping');
foreach ($Results as $GroupID) {
    $tgroup = $tgMan->findById($GroupID);
    if (is_null($tgroup)) {
        continue;
    }
    $Torrents = $tgroup->torrentList();
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
        $GroupTime = $MaxSize = 0;
        foreach ($Torrents as $T) {
            $GroupTime = max($GroupTime, strtotime($T['Time']));
            $MaxSize = max($MaxSize, $T['Size']);
        }
    }

    $TorrentTags = new Tags(implode(' ', (array_column($GroupInfo['tags'], 'name'))));

    $DisplayName = $tgroup->artistHtml() . ' - ';
    $SnatchedGroupClass = $tgroup->isSnatched() ? ' snatched_group' : '';

    if ($GroupResults && (count($Torrents) > 1 || isset(CATEGORY_GROUPED[$CategoryID - 1]))) {
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
        <td class="td_collapse center m_td_left">
            <div id="showimg_<?=$GroupID?>" class="<?= $groupsClosed ? 'show' : 'hide' ?>_torrents">
                <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?=$GroupID?>, this, event)" title="Collapse this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all groups on this page."></a>
            </div>
        </td>
        <td class="center cats_col">
            <div title="<?=$TorrentTags->title()?>" class="tooltip <?= $tgroup->categoryCss() ?> <?=$TorrentTags->css_name()?>">
            </div>
        </td>
        <td colspan="2" class="td_info big_info">
<?php    if ($Viewer->option('CoverArt')) { ?>
            <div class="group_image float_left clear">
                <?= $imgProxy->thumbnail($GroupInfo['Image'], $GroupInfo['CategoryID']) ?>
            </div>
<?php    } ?>
            <div class="group_info clear">
                <?=$DisplayName?>
<?php    if ($bookmark->isTorrentBookmarked($GroupID)) { ?>
                <span class="remove_bookmark float_right">
                    <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Unbookmark('torrent', <?=$GroupID?>, 'Bookmark'); return false;">Remove bookmark</a>
                </span>
<?php    } else { ?>
                <span class="add_bookmark float_right">
                    <a href="#" id="bookmarklink_torrent_<?=$GroupID?>" class="brackets" onclick="Bookmark('torrent', <?=$GroupID?>, 'Remove bookmark'); return false;">Bookmark</a>
                </span>
<?php    } ?>
                <br />
                <div class="tags"><?= $TorrentTags->format("torrents.php?action={$searchMode}&amp;taglist=") ?></div>
            </div>
        </td>
        <td class="td_time nobr"><?=time_diff($GroupTime, 1)?></td>
        <td class="td_size number_column nobr"><?=Format::get_size($MaxSize)?> (Max)</td>
        <td class="td_snatched number_column m_td_right"><?=number_format($tgroup->stats()->snatchTotal())?></td>
        <td class="td_seeders number_column<?= $tgroup->stats()->seedingTotal() == 0 ? ' r00' : '' ?> m_td_right"><?=number_format($tgroup->stats()->seedingTotal())?></td>
        <td class="td_leechers number_column m_td_right"><?=number_format($tgroup->stats()->leechTotal())?></td>
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
            $torrent = $torMan->findById($TorrentID);
            if (is_null($torrent)) {
                continue;
            }
            // All of the individual torrents in the group
            if ($Data['is_deleted']) {
                continue;
            }

            if ($Data['Remastered'] && !$Data['RemasterYear']) {
                $FirstUnknown = !isset($FirstUnknown);
            }
            $SnatchedTorrentClass = $snatcher->showSnatch($torrent->id()) ? ' snatched_torrent' : '';
            $Reported = $torMan->hasReport($Viewer, $TorrentID);

            if (isset(CATEGORY_GROUPED[$CategoryID - 1])
                    && ($Data['RemasterTitle'] != $LastRemasterTitle
                        || $Data['RemasterYear'] != $LastRemasterYear
                        || $Data['RemasterRecordLabel'] != $LastRemasterRecordLabel
                        || $Data['RemasterCatalogueNumber'] != $LastRemasterCatalogueNumber)
                    || (isset($FirstUnknown) && $FirstUnknown)
                    || $Data['Media'] != $LastMedia
            ) {
                $EditionID++;

?>
    <tr class="group_torrent groupid_<?=$GroupID?> edition<?=$SnatchedGroupClass . ($groupsClosed ? ' hidden' : '')?>">
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
    <tr class="group_torrent groupid_<?=$GroupID?> edition_<?=$EditionID?><?=$SnatchedTorrentClass . $SnatchedGroupClass . ($groupsClosed ? ' hidden' : '')?>">
        <td class="td_info" colspan="3">
            <?= $Twig->render('torrent/action-v2.twig', [
                'can_fl' => $Viewer->canSpendFLToken($torrent),
                'key'    => $Viewer->announceKey(),
                't'      => $torrent,
            ]) ?>
            &raquo; <a href="torrents.php?id=<?=$GroupID?>&amp;torrentid=<?=$TorrentID?>"><?=Torrents::torrent_info($Data)?>
<?php if ($Reported) { ?>
            / <strong class="torrent_label tl_reported">Reported</strong>
<?php } ?></a>
        </td>
        <td class="td_file_count"><?=$Data['FileCount']?></td>
        <td class="td_time nobr"><?=time_diff($torrent->uploadDate(), 1)?></td>
        <?= $Twig->render('torrent/stats.twig', ['torrent' => $torrent]) ?>
    </tr>
<?php
        }
    } else {
        // Viewing a type that does not require grouping

        $TorrentID = key($Torrents);
        $torrent = $torMan->findById($TorrentID);
        if (is_null($torrent)) {
            continue;
        }
        $Data = current($Torrents);
        $DisplayName .= "<a href=\"torrents.php?id=$GroupID&amp;torrentid=$TorrentID#torrent$TorrentID\" class=\"tooltip\" title=\"View torrent\" dir=\"ltr\">$GroupName</a>";
        if (isset(CATEGORY_GROUPED[$CategoryID - 1])) {
            if ($GroupYear) {
                $DisplayName .= " [$GroupYear]";
            }
            if ($CategoryID == 1 && $ReleaseType > 0) {
                $DisplayName .= ' [' . $releaseTypes[$ReleaseType] . ']';
            }
            $ExtraInfo = Torrents::torrent_info($Data, true, true);
        } elseif ($Data['IsSnatched']) {
            $ExtraInfo = "<strong class=\"torrent_label tooltip tl_snatched\" title=\"Snatched!\" style=\"white-space: nowrap;\">Snatched!</strong>";
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
            <div title="<?=$TorrentTags->title()?>" class="tooltip <?= $tgroup->categoryCss() ?> <?=$TorrentTags->css_name()?>"></div>
        </td>
        <td class="td_info big_info">
<?php   if ($Viewer->option('CoverArt')) { ?>
            <div class="group_image float_left clear">
                <?= $imgProxy->thumbnail($GroupInfo['Image'], $CategoryID) ?>
            </div>
<?php   } ?>
            <div class="group_info clear">
                <?= $Twig->render('torrent/action-v2.twig', [
                    'can_fl' => $Viewer->canSpendFLToken($torrent),
                    'key'    => $Viewer->announceKey(),
                    't'      => $torrent,
                ]) ?>
                <?=$DisplayName?>
                <div class="torrent_info"><?=$ExtraInfo?></div>
                <div class="tags"><?=$TorrentTags->format("torrents.php?action={$searchMode}&amp;taglist=")?></div>
            </div>
        </td>
        <td class="td_file_count"><?=$Data['FileCount']?></td>
        <td class="td_time nobr"><?=time_diff($torrent->uploadDate(), 1)?></td>
        <?= $Twig->render('torrent/stats.twig', ['torrent' => $torrent]) ?>
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
