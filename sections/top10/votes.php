<?php

$vote = new Gazelle\User\Vote($Viewer);
$tagMan = new Gazelle\Manager\Tag;

$all = ($_GET['anyall'] ?? 'all') === 'all';

if (empty($_GET['advanced']) || !$Viewer->permitted('site_advanced_top10')) {
    $limit = (int)($_GET['limit'] ?? 25);
} else {
    $limit = 25;
    $vote->setTopYearInterval((int)$_GET['year1'], (int)$_GET['year2']);
    if (isset($_GET['tags'])) {
        $list = explode(',', trim($_GET['tags']));
        $tags = [];
        foreach ($list as $tag) {
            $t = $tagMan->sanitize($tag);
            if (!empty($t)) {
                $tags[] = $t;
            }
        }
        if ($tags) {
            $vote->setTopTagList($tags, $all);
        }
    }
}
$vote->setTopLimit($limit);

$bookmark     = new Gazelle\User\Bookmark($Viewer);
$snatcher     = new Gazelle\User\Snatch($Viewer);
$imgProxy     = new Gazelle\Util\ImageProxy($Viewer);
$tgMan        = (new Gazelle\Manager\TGroup)->setViewer($Viewer);
$torMan       = (new Gazelle\Manager\Torrent)->setViewer($Viewer);
$urlStem      = (new Gazelle\User\Stylesheet($Viewer))->imagePath();
$topVotes     = $vote->topVotes();
$number       = 0;

View::show_header(TOP_TEN_HEADING . " – Voted Groups", ['js' => 'browse,voting']);
?>
<div class="thin">
    <div class="header">
        <h2><?= TOP_TEN_HEADING ?> – Voted Groups</h2>
        <?= $Twig->render('top10/linkbox.twig', ['selected' => 'votes']) ?>
    </div>
<?php if ($Viewer->permitted('site_advanced_top10')) { ?>
    <form class="search_form" name="votes" action="" method="get">
        <input type="hidden" name="advanced" value="1" />
        <input type="hidden" name="type" value="votes" />
        <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
            <tr id="tagfilter">
                <td class="label">Tags (comma-separated):</td>
                <td class="ft_taglist">
                    <input type="text" name="tags" size="75" value="<?php if (!empty($_GET['tags'])) {
echo display_str($_GET['tags']);} ?>" />&nbsp;
                    <input type="radio" id="rdoAll" name="anyall" value="all"<?=($all ? ' checked="checked"' : '')?> /><label for="rdoAll"> All</label>&nbsp;&nbsp;
                    <input type="radio" id="rdoAny" name="anyall" value="any"<?=(!$all ? ' checked="checked"' : '')?> /><label for="rdoAny"> Any</label>
                </td>
            </tr>
            <tr id="yearfilter">
                <td class="label">Year:</td>
                <td class="ft_year">
                    <input type="text" name="year1" size="4" value="<?php if (!empty($_GET['year1'])) {
echo display_str($_GET['year1']);} ?>" />
                    to
                    <input type="text" name="year2" size="4" value="<?php if (!empty($_GET['year2'])) {
echo display_str($_GET['year2']);} ?>" />
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Filter torrents" />
                </td>
            </tr>
        </table>
    </form>
<?php } ?>
    <h3>Top <?=$limit?>
<?php if (empty($_GET['advanced'])) { ?>
        <small class="top10_quantity_links">
<?php
    switch ($limit) {
        case 100:
            ?>
            - <a href="top10.php?type=votes" class="brackets">Top 25</a>
            - <span class="brackets">Top 100</span>
            - <a href="top10.php?type=votes&amp;limit=250" class="brackets">Top 250</a>
<?php
            break;
        case 250:
            ?>
            - <a href="top10.php?type=votes" class="brackets">Top 25</a>
            - <a href="top10.php?type=votes&amp;limit=100" class="brackets">Top 100</a>
            - <span class="brackets">Top 250</span>
<?php
            break;
        default:
            ?>
            - <span class="brackets">Top 25</span>
            - <a href="top10.php?type=votes&amp;limit=100" class="brackets">Top 100</a>
            - <a href="top10.php?type=votes&amp;limit=250" class="brackets">Top 250</a>
<?php    } ?>
        </small>
<?php } ?>
    </h3>
<table class="torrent_table grouping cats m_table" id="discog_table">
    <tr class="colhead_dark">
        <td><!-- expand/collapse --></td>
        <td class="cats_col"><!-- category --></td>
        <td class="m_th_left" width="70%">Torrents</td>
        <td>Size</td>
        <td class="sign snatches"><img src="<?= $urlStem ?>snatched.png" alt="Snatches" title="Snatches" class="tooltip" /></td>
        <td class="sign seeders"><img src="<?= $urlStem ?>seeders.png" alt="Seeders" title="Seeders" class="tooltip" /></td>
        <td class="sign leechers"><img src="<?= $urlStem ?>leechers.png" alt="Leechers" title="Leechers" class="tooltip" /></td>
    </tr>
<?php
if (count($topVotes) === 0) { ?>
    <tr>
        <td colspan="7" class="center">No torrents were found that meet your criteria.</td>
    </tr>
<?php
} else {
    foreach ($topVotes as $tgroupId => $result) {
        $tgroup = $tgMan->findById($tgroupId);
        if (is_null($tgroup)) {
            continue;
        }
        $torrentIdList = $tgroup->torrentIdList();
        if (count($torrentIdList) === 0) {
            continue;
        }
        ++$number;
        $sequence   = $result['sequence'];
        $upVotes    = $result['Ups'];
        $totalVotes = $result['Total'];
        $score      = $result['Score'];
        $downVotes  = $totalVotes - $upVotes;
        $snatchedGroupClass = $tgroup->isSnatched() ? ' snatched_group' : '';

        if (count($torrentIdList) > 1 || $tgroup->categoryGrouped()) {
            // Grouped torrents
?>
    <tr class="group groupid_<?=$tgroupId?>_header discog<?=$snatchedGroupClass?>" id="group_<?=$tgroupId?>">
        <td class="center">
            <div id="showimg_<?=$tgroupId?>" class="show_torrents">
                <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?=$tgroupId?>, this, event);" title="Expand this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to expand all groups on this page."></a>
            </div>
        </td>
        <td class="center cats_col">
            <div title="<?= $tgroup->primaryTag() ?>" class="tooltip <?= $tgroup->categoryCss() ?> <?= $tgroup->primaryTagCss() ?>"></div>
        </td>

        <td class="big_info">
<?php       if ($Viewer->option('CoverArt')) { ?>
            <div class="group_image float_left clear">
                <?= $imgProxy->tgroupThumbnail($tgroup) ?>
            </div>
<?php       } ?>
            <div class="group_info clear">
                <strong><?= $result['sequence'] ?> - <?= $tgroup->link() ?><?php if ($tgroup->year()) {
echo ' [' . $tgroup->year() . ']'; } ?></strong>
                <div class="tags"><?= implode(', ', array_map(
                    fn($name) => "<a href=\"collages.php?action=search&amp;tags=$name\">$name</a>", $tgroup->tagNameList()
                    )) ?></div>
            </div>
        </td>
        <td colspan="4" class="votes_info_td">
            <?= $Twig->render('bookmark/action.twig', [
                'class'         => 'torrent',
                'id'            => $tgroupId,
                'is_bookmarked' => $bookmark->isTorrentBookmarked($tgroupId),
            ]) ?><br />
            <span style="white-space: nowrap;">
                <span class="favoritecount_small tooltip" title="<?=$upVotes . ($upVotes == 1 ? ' upvote' : ' upvotes')?>"><span id="upvotes"><?=number_format($upVotes)?></span> <span class="vote_album_up">&#x25b2;</span></span>
                &nbsp; &nbsp;
                <span class="favoritecount_small tooltip" title="<?=$downVotes . ($downVotes == 1 ? ' downvote' : ' downvotes')?>"><span id="downvotes"><?=number_format($downVotes)?></span> <span class="vote_album_down">&#x25bc;</span></span>
                &nbsp;
                <span class="favoritecount_small" id="totalvotes"><?=number_format($totalVotes)?></span> Total
            </span>
            <br />
            <span style="white-space: nowrap;">
                <span class="tooltip_interactive" title="&lt;span style=&quot;font-weight: bold;&quot;&gt;Score: <?=number_format($score * 100, 4)?>&lt;/span&gt;&lt;br /&gt;&lt;br /&gt;This is the lower bound of the binomial confidence interval &lt;a href=&quot;wiki.php?action=article&amp;id=108&quot;&gt;described here&lt;/a&gt;, multiplied by 100." data-title-plain="Score: <?=number_format($score * 100, 4)?>. This is the lower bound of the binomial confidence interval described in the Favorite Album Votes wiki article, multiplied by 100.">Score: <span class="favoritecount_small"><?=number_format($score * 100, 1)?></span></span>
                &nbsp; | &nbsp;
                <span class="favoritecount_small"><?=number_format($upVotes / $totalVotes * 100, 1)?>%</span> positive
            </span>
        </td>
    </tr>
<?php
            $prev = '';
            $editionID = 0;
            unset($firstUnknown);

            foreach ($torrentIdList as $torrentId) {
                $torrent = $torMan->findById($torrentId);
                if (is_null($torrent)) {
                    continue;
                }

                $current = $torrent->remasterTuple();
                if ($torrent->isRemasteredUnknown()) {
                    $FirstUnknown = !isset($firstUnknown);
                }
                if ($prev != $current || isset($firstUnknown) && $firstUnknown) {
                    $editionID++;
?>
    <tr class="group_torrent groupid_<?= $tgroupId ?> hidden edition_<?= $editionID ?><?= $snatchedGroupClass ?>">
        <td colspan="7" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?= $tgroupId ?>, <?= $editionID ?>, this, event)"
            class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?= $torrent->edition() ?>
            </strong></td>
    </tr>
<?php
                }
                $prev = $current;
?>
    <tr class="group_torrent torrent_row groupid_<?= $tgroupId ?> hidden edition_<?= $editionID ?><?= ($snatcher->showSnatch($torrentId) ? ' snatched_torrent' : '') . $snatchedGroupClass ?>">
        <td colspan="3">
            <?= $Twig->render('torrent/action-v2.twig', [
                'torrent' => $torrent,
                'viewer'  => $Viewer,
            ]) ?>
            &nbsp;&nbsp;&raquo;&nbsp;<?= $torrent->shortLabelLink() ?>
        </td>
        <?= $Twig->render('torrent/stats.twig', ['torrent' => $torrent]) ?>
    </tr>
<?php
            }
        } else {
            // Viewing a type that does not require grouping
            $torrentId = $torrentIdList[0];
            $torrent = $torMan->findById($torrentId);
?>
    <tr class="torrent torrent_row<?= ($snatcher->showSnatch($torrentId) ? ' snatched_torrent' : '')
        . $SnatchedGroupClass ?>" id="group_<?= $tgroupId ?>">
        <td></td>
        <td class="td_collage_category center">
            <div title="<?= ucfirst($tgroup->primaryTag()) ?>"
                 class="tooltip <?= $tgroup->categoryCss() ?> <?= $tgroup->primaryTagCss() ?>"></div>
        </td>
        <td class="td_info">
            <?= $Twig->render('torrent/action-v2.twig', [
                'torrent' => $torrent,
                'viewer'  => $Viewer,
            ]) ?>
            <strong><?= $tgroup->link() ?></strong>
<?php   if (!$Viewer->option('NoVoteLinks') && $Viewer->permitted('site_album_votes')) { ?>
            <?= $vote->links($tgroupId) ?>
<?php   } ?>
            <div class="tags"><?= implode(', ', array_map(
                fn($name) => "<a href=\"collages.php?action=search&tags=$name\">$name</a>", $tgroup->tagNameList()
                )) ?></div>
        </td>
        <?= $Twig->render('torrent/stats.twig', ['torrent' => $torrent]) ?>
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
