<?php

use Gazelle\Util\SortableTableHeader;

$torMan    = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$reportMan = new Gazelle\Manager\Torrent\Report($torMan);
$tagMan    = new Gazelle\Manager\Tag;
$tgMan     = (new Gazelle\Manager\TGroup)->setViewer($Viewer);
$snatcher  = new Gazelle\User\Snatch($Viewer);

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
    $GroupResults = (bool)$Viewer->option('DisableGrouping2') === false;
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
$Results = array_unique($Search->query($_GET));
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
$imgProxy = new Gazelle\Util\ImageProxy($Viewer);

echo $paginator->linkbox();
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
$snatcher = new Gazelle\User\Snatch($Viewer);

$groupsClosed = (bool)$Viewer->option('TorrentGrouping');
foreach ($Results as $GroupID) {
    $tgroup = $tgMan->findById($GroupID);
    if (is_null($tgroup)) {
        continue;
    }
    $torrentList = $tgroup->torrentIdList();
    if (empty($torrentList)) {
        continue;
    }

    $SnatchedGroupClass = $tgroup->isSnatched($Viewer->id()) ? ' snatched_group' : '';

    if ($GroupResults && (count($torrentList) > 1 || $tgroup->categoryGrouped())) {
?>
    <tr class="group groupid_<?=$GroupID?>_header<?=$SnatchedGroupClass?>">
<?= $Twig->render('tgroup/collapse-tgroup.twig', [ 'closed' => $groupsClosed, 'id' => $tgroup->id() ]) ?>
        <td class="center cats_col">
            <div title="<?= $tgroup->primaryTag() ?>" class="tooltip <?= $tgroup->categoryCss() ?> <?= $tgroup->primaryTagCss() ?>">
            </div>
        </td>
        <td class="td_info big_info">
<?php    if ($Viewer->option('CoverArt')) { ?>
            <div class="group_image float_left clear">
                <?= $imgProxy->tgroupThumbnail($tgroup) ?>
            </div>
<?php    } ?>
            <div class="group_info clear">
                <?= $tgroup->link() ?>
                <span style="float: right">
                <?= $Twig->render('bookmark/action.twig', [
                    'class'         => 'torrent',
                    'id'            => $tgroup->id(),
                    'is_bookmarked' => $bookmark->isTorrentBookmarked($tgroup->id()),
                ]) ?>
                </span>
                <br />
                <div class="tags"><?= implode(', ',
                    array_map(fn($name) => "<a href=\"torrents.php?action={$searchMode}&amp;taglist=$name\">$name</a>", $tgroup->tagNameList())
                    ) ?></div>
            </div>
        </td>
        <td></td>
        <td class="td_time nobr"><?=time_diff($tgroup->mostRecentUpload(), 1)?></td>
        <td class="td_size number_column nobr"><?=Format::get_size($tgroup->maxTorrentSize())?> (Max)</td>
        <td class="td_snatched number_column m_td_right"><?=number_format($tgroup->stats()->snatchTotal())?></td>
        <td class="td_seeders number_column<?= $tgroup->stats()->seedingTotal() == 0 ? ' r00' : '' ?> m_td_right"><?=number_format($tgroup->stats()->seedingTotal())?></td>
        <td class="td_leechers number_column m_td_right"><?=number_format($tgroup->stats()->leechTotal())?></td>
    </tr>
<?php
        $prev = '';
        $EditionID = 0;
        $UnknownCounter = 0;

        foreach ($torrentList as $torrentId) {
            $torrent = $torMan->findById($torrentId);
            if (is_null($torrent)) {
                continue;
            }
            $current = $torrent->remasterTuple();
            if ($torrent->isRemasteredUnknown()) {
                $UnknownCounter++;
            }

            if ($prev != $current || $UnknownCounter === 1) {
                $EditionID++;

?>
    <tr class="group_torrent groupid_<?=$tgroup->id()?> edition<?=$SnatchedGroupClass . ($groupsClosed ? ' hidden' : '')?>">
        <td colspan="9" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=$tgroup->id()?>, <?=$EditionID?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?= $torrent->edition() ?></strong></td>
    </tr>
<?php
            }
            $prev = $current;
            $SnatchedTorrentClass = $snatcher->showSnatch($torrent->id()) ? ' snatched_torrent' : '';
?>
    <tr class="group_torrent groupid_<?=$tgroup->id()?> edition_<?=$EditionID?><?=$SnatchedTorrentClass . $SnatchedGroupClass . ($groupsClosed ? ' hidden' : '')?>">
        <td class="td_info" colspan="3">
            <?= $Twig->render('torrent/action-v2.twig', [
                'pl'      => true,
                'torrent' => $torrent,
                'viewer'  => $Viewer,
            ]) ?>
            &raquo; <?= $torrent->shortLabelLink() ?>
        </td>
        <td class="td_file_count"><?=$torrent->fileTotal()?></td>
        <td class="td_time nobr"><?=time_diff($torrent->created(), 1)?></td>
        <?= $Twig->render('torrent/stats.twig', ['torrent' => $torrent]) ?>
    </tr>
<?php
        }
    } else {
        // Viewing a type that does not require grouping

        foreach ($torrentList as $torrentId) {
            $torrent = $torMan->findById($torrentId);
            if (is_null($torrent)) {
                continue;
            }
            $SnatchedTorrentClass = $tgroup->isSnatched() ? ' snatched_torrent' : '';
?>
    <tr class="torrent<?=$SnatchedTorrentClass . $SnatchedGroupClass?>">
<?php       if ($GroupResults) { ?>
        <td></td>
<?php       } ?>
        <td class="center cats_col m_cats_col m_td_left">
            <div title="<?= $tgroup->primaryTag() ?>" class="tooltip <?= $tgroup->categoryCss() ?> <?= $tgroup->primaryTagCss() ?>"></div>
        </td>
        <td class="td_info big_info">
<?php       if ($Viewer->option('CoverArt')) { ?>
            <div class="group_image float_left clear">
                <?= $imgProxy->tgroupThumbnail($tgroup) ?>
            </div>
<?php       } ?>
            <div class="group_info clear">
                <?= $Twig->render('torrent/action-v2.twig', [
                    'torrent' => $torrent,
                    'viewer'  => $Viewer,
                ]) ?>
                <?= $torrent->fullLink() ?>
                <div class="tags"><?= implode(', ',
                    array_map(fn($name) => "<a href=\"torrents.php?action={$searchMode}&amp;taglist=$name\">$name</a>", $tgroup->tagNameList())
                    ) ?></div>
            </div>
        </td>
        <td class="td_file_count"><?= $torrent->fileTotal() ?></td>
        <td class="td_time nobr"><?=time_diff($torrent->created(), 1)?></td>
        <?= $Twig->render('torrent/stats.twig', ['torrent' => $torrent]) ?>
    </tr>
<?php
        }
    }
}
?>
</table>
<?= $paginator->linkbox() ?>
</div>
<?php
View::show_footer();
