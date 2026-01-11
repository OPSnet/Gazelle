<?php
/** @phpstan-var \Gazelle\User $Viewer */
/** @phpstan-var \Twig\Environment $Twig */
// phpcs:disable Generic.WhiteSpace.ScopeIndent.IncorrectExact
// phpcs:disable Generic.WhiteSpace.ScopeIndent.Incorrect

declare(strict_types=1);

namespace Gazelle;

$tgMan         = new Manager\TGroup();
$torMan        = new Manager\Torrent();
$bookmark      = new User\Bookmark($Viewer);
$collMan       = new Manager\Collage()->setImageProxy(new \Gazelle\Util\ImageProxy($Viewer));
$urlStem       = new User\Stylesheet($Viewer)->imagePath();
$vote          = new User\Vote($Viewer);

/** @var Collage $collage required from collage.php */

$collage->setViewer($Viewer);
$paginator    = new Util\Paginator((int)($Viewer->option('CollageEntries') ?? 25),
                                   (int)($_GET['page'] ?? 1))->setTotal($collage->total());
$snatcher     = $Viewer->snatch();
$entryList    = array_slice($collage->entryList(), $paginator->offset(), $paginator->limit());
$groupsClosed = (bool)$Viewer->option('TorrentGrouping');

echo $Twig->render('collage/header.twig', [
    'bookmarked' => $bookmark->isBookmarked($collage),
    'collage'    => $collage,
    'viewer'     => $Viewer,
]);

echo$Twig->render('collage/sidebar.twig', [
    'collage'      => $collage,
    'comments'     => new Manager\Comment()->collageSummary($collage),
    'contributors' => array_slice($collage->contributors(), 0, 5, true),
    'viewer'       => $Viewer,
]);

if ($Viewer->permitted('zip_downloader')) {
    if ($Viewer->option('Collector')) {
        [$ZIPList, $ZIPPrefs] = $Viewer->option('Collector');
        if (is_null($ZIPList)) {
            $ZIPList = ['00', '11'];
            $ZIPPrefs = 1;
        } else {
            $ZIPList = explode(':', $ZIPList);
        }
    } else {
        $ZIPList = ['00', '11'];
        $ZIPPrefs = 1;
    }
?>
        <div class="box box_zipdownload">
            <div class="head colhead_dark"><strong>Collector</strong></div>
            <div class="pad">
                <form class="download_form" name="zip" action="collages.php" method="post">
                <input type="hidden" name="action" value="download">
                <input type="hidden" name="auth" value="<?=$Viewer->auth()?>">
                <input type="hidden" name="collageid" value="<?= $collage->id ?>">
                <ul id="list" class="nobullet">
<?php foreach ($ZIPList as $ListItem) { ?>
                    <li id="list<?=$ListItem?>">
                        <input type="hidden" name="list[]" value="<?=$ListItem?>">
                        <span class="float_left"><?=ZIP_OPTION[$ListItem]['2']?></span>
                        <span class="remove remove_collector">
                            <a href="#" onclick="remove_selection('<?=$ListItem?>'); return false;"
                               class="float_right brackets">X</a></span>
                        <br style="clear: both;">
                    </li>
<?php } ?>
                </ul>
                <select id="formats" style="width: 180px;">
<?php
    $OpenGroup = false;
    $LastGroupID = -1;

    foreach (ZIP_OPTION as $Option) {
        [$GroupID, $OptionID, $OptName] = $Option;
        if ($GroupID != $LastGroupID) {
            $LastGroupID = $GroupID;
            if ($OpenGroup) { ?>
                    </optgroup>
<?php       } ?>
                    <optgroup label="<?=ZIP_GROUP[$GroupID]?>">
<?php
            $OpenGroup = true;
        } ?>
                        <option id="opt<?=$GroupID . $OptionID?>" value="<?=$GroupID . $OptionID?>"<?php
        if (in_array($GroupID . $OptionID, $ZIPList)) {
            echo ' disabled="disabled"';
        }?>><?=$OptName?></option>
<?php } /* foreach */ ?>
                    </optgroup>
                </select>
                <button type="button" onclick="add_selection();">+</button>
                <select name="preference" style="width: 210px;">
                    <option value="0"<?php if ($ZIPPrefs == 0) {
echo ' selected="selected"'; } ?>>Prefer Original</option>
                    <option value="1"<?php if ($ZIPPrefs == 1) {
echo ' selected="selected"'; } ?>>Prefer Best Seeded</option>
                    <option value="2"<?php if ($ZIPPrefs == 2) {
echo ' selected="selected"'; } ?>>Prefer Bonus Tracks</option>
                </select>
                <input type="submit" style="width: 210px;" value="Download" />
                </form>
            </div>
        </div>
<?php } /* zip collector */ ?>
    </div>
    <div class="main_column">
        <div id="coverart" class="box">
            <div class="head" id="coverhead"><strong>Cover Art</strong></div>
            <ul class="collage_images" id="collage_page0">
<?php
foreach ($entryList as $tgroupId) {
    $tgroup = $tgMan->findById($tgroupId);
    if ($tgroup) {
        echo $collMan->tgroupCover($tgroup);
    }
}
?>
            </ul>
        </div>
        <?= $paginator->linkbox() ?>
        <table class="torrent_table grouping cats m_table" id="discog_table">
            <tr class="colhead_dark">
                <td><!-- expand/collapse --></td>
                <td><!-- Category --></td>
                <td class="m_th_left" width="70%"><strong>Torrents</strong></td>
<?php if ($Viewer->ordinal()->value('file-count-display')) { ?>
                <td class="number_column nobr">Files</td>
<?php } ?>
                <td class="number_column nobr">Size</td>
                <td class="sign snatches"><img src="<?= $urlStem ?>snatched.svg" class="tooltip" alt="Snatches" title="Snatches"></td>
                <td class="sign seeders"><img src="<?= $urlStem ?>seeders.svg" class="tooltip" alt="Seeders" title="Seeders"></td>
                <td class="sign leechers"><img src="<?= $urlStem ?>leechers.svg" class="tooltip" alt="Leechers" title="Leechers"></td>
            </tr>
<?php
$tgroupIndex = $paginator->offset();
foreach ($entryList as $tgroupId) {
    $tgroup = $tgMan->findById($tgroupId);
    if (is_null($tgroup)) {
        continue;
    }
    $torrentIdList = $tgroup->torrentIdList();
    $SnatchedGroupClass = $tgroup->isSnatched() ? ' snatched_group' : '';
    $prev = '';
    $EditionID = 0;
    unset($FirstUnknown);
    $tgroupIndex++;

    if (count($torrentIdList) > 1 || $tgroup->categoryGrouped()) {
        // Tgroup header
?>
            <tr class="group groupid_<?=$tgroupId?>_header discog<?= $SnatchedGroupClass ?>" id="group_<?= $tgroupId ?>">
                <?= $Twig->render('tgroup/collapse-tgroup.twig', [ 'closed' => $groupsClosed, 'id' => $tgroupId ]) ?>
                <td class="center">
                    <div title="<?= $tgroup->primaryTag() ?>" class="tooltip <?= $tgroup->categoryCss() ?> <?= $tgroup->primaryTagCss() ?>"></div>
                </td>
                <td colspan="<?= $Viewer->ordinal()->value('file-count-display') ? 6 : 5 ?>">
                    <strong><?= $tgroupIndex ?> – <?= $tgroup->link() ?></strong>
                    <span class="float_right">
<?php
echo $Twig->render('bookmark/action.twig', [
    'class'         => 'torrent',
    'id'            => $tgroupId,
    'is_bookmarked' => $bookmark->isBookmarked($tgroup),
]);
if (!$Viewer->option('NoVoteLinks') && $Viewer->permitted('site_album_votes')) { ?>
                            <br><?= $vote->links($tgroup) ?>
<?php } ?>
                        </span>
                    <div class="tags"><?= implode(', ', array_map(
                                fn ($name) => "<a href=\"collages.php?action=search&tags=$name\">$name</a>", $tgroup->tagNameList()
                        )) ?></div>
                </td>
            </tr>
<?php
    }

    foreach ($torrentIdList as $torrentId) {
        $torrent = $torMan->findById($torrentId);
        if (is_null($torrent)) {
            continue;
        }

        if (count($torrentIdList) > 1 || $tgroup->categoryGrouped()) {
            if ($torrent->isRemasteredUnknown()) {
                $FirstUnknown = !isset($FirstUnknown);
            }
            $current = $torrent->remasterTuple();
            if ($prev != $current || (isset($FirstUnknown) && $FirstUnknown)) {
                $EditionID++;
?>
                <tr class="group_torrent groupid_<?= $tgroupId ?> edition<?= $SnatchedGroupClass . ($groupsClosed ? ' hidden' : '') ?>">
                    <td colspan="<?= $Viewer->ordinal()->value('file-count-display') ? 8 : 7 ?>" class="edition_info">
                        <?= $Twig->render('torrent/edition-header.twig', [
                            'edition_id' => $EditionID,
                            'tgroup'     => $tgroup,
                            'torrent'    => $torrent,
                        ]) ?>
                    </td>
                </tr>
<?php
            }
            $prev = $current;
?>
            <tr class="group_torrent torrent_row groupid_<?= $tgroupId ?> edition_<?= $EditionID ?><?=
                    ($snatcher->showSnatch($torrent) ? ' snatched_torrent' : '') . $SnatchedGroupClass
                    . ($groupsClosed ? ' hidden' : '') ?>">
                <td class="td_info" colspan="3">
                    <?= $Twig->render('torrent/action-v2.twig', [
                        'pl'      => true,
                        'torrent' => $torrent,
                        'viewer'  => $Viewer,
                    ]) ?>
                    &nbsp;&nbsp;»&nbsp;<?= $torrent->shortLabelLink() ?>
<?php   } else { ?>
            <tr class="torrent torrent_row<?= ($snatcher->showSnatch($torrent) ? ' snatched_torrent' : '')
                . $SnatchedGroupClass ?>" id="group_<?= $tgroupId ?>">
                <td></td>
                <td class="td_collage_category center">
                    <div title="<?= $tgroup->primaryTag() ?>" class="tooltip <?= $tgroup->categoryCss() ?> <?=
                        $tgroup->primaryTagCss() ?>"></div>
                </td>
                <td class="td_info">
                    <?= $Twig->render('torrent/action-v2.twig', [
                        'pl'      => true,
                        'torrent' => $torrent,
                        'viewer'  => $Viewer,
                    ]) ?>
                    <?= $tgroupIndex ?> – <strong><?= $tgroup->link() ?></strong>
<?php       if (!$Viewer->option('NoVoteLinks') && $Viewer->permitted('site_album_votes')) { ?>
                    <?= $vote->links($tgroup) ?>
<?php       } ?>
                    <div class="tags">
                        <?= implode(', ', array_map(fn ($name) =>
                            "<a href=\"collages.php?action=search&amp;tags=$name\">$name</a>", $tgroup->tagNameList())) ?>
                    </div>
<?php   } ?>
                </td>
                <?= $Twig->render('torrent/stats.twig', ['torrent' => $torrent, 'viewer' => $Viewer]) ?>
            </tr>
<?php
    }
}
?>
        </table>
        <?= $paginator->linkbox() ?>
    </div>
</div>
<?php
View::show_footer();
