<?php
// We need these to do our rankification
include(SERVER_ROOT.'/sections/torrents/ranking_funcs.php');

$userVotes = Votes::get_user_votes($LoggedUser['ID']);
$tagMan = new \Gazelle\Manager\Tag;

$where = [];
$params = [];

if (!empty($_GET['advanced']) && check_perms('site_advanced_top10')) {
    $details = 'all';
    $limit = 25;

    if (!empty($_GET['tags'])) {
        $tagsAny = isset($_GET['anyall']) && $_GET['anyall'] === 'any';
        $tags = explode(',', trim($_GET['tags']));
        foreach ($tags as $id => $tag) {
            $tags[$id] = $tagMan->sanitize($tag);
        }
        $operator = $tagsAny ? ' OR ' : ' AND ';
        $params = $tags;
        $where[] = sprintf('EXISTS (
            SELECT 1
            FROM torrents_tags tt
            INNER JOIN tags t ON (t.ID = tt.TagID)
            WHERE tt.GroupID = tg.ID
                AND (%s)
            )', implode($operator, array_fill(0, count($tags), 't.Name = ?'))
        );
    }
    $year1 = (int)$_GET['year1'];
    $year2 = (int)$_GET['year2'];
    if ($year1 > 0 && $year2 <= 0) {
        $where[] = 'tg.Year >= ?';
        $params[] = $year1;
    } elseif ($year1 > 0 && $year2 > 0) {
        $where[] = 'tg.Year BETWEEN ? AND ?';
        $params[] = $year1;
        $params[] = $year2;
    } elseif ($year2 > 0 && $year1 <= 0) {
        $where[] = 'tg.Year <= ?';
        $params[] = $year2;
    }
} else {
    $details = 'all';
    // defaults to 10 (duh)
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 25;
    $limit = in_array($limit, [25, 100, 250]) ? $limit : 25;
}
$filtered = !empty($where);

if ($filtered) {
    $where = implode(' AND ', $where);
}
$whereSum = !$filtered ? '' : md5($where);

// Unlike the other top 10s, this query just gets some raw stats
// We'll need to do some fancy-pants stuff to translate it into
// BPCI scores before getting the torrent data
$query = '
    SELECT v.GroupID, v.Ups, v.Total, v.Score
    FROM torrents_votes AS v';
if ($filtered) {
    $query .= "
    INNER JOIN torrents_group AS tg ON (tg.ID = v.GroupID)
    WHERE $where AND ";
} else {
    $query .= '
    WHERE ';
}
$query .= "
        Score > 0
    ORDER BY Score DESC
    LIMIT $limit";

$topVotes = $Cache->get_value('top10votes_'.$limit.$whereSum);
if ($topVotes === false) {
    if ($Cache->get_query_lock('top10votes')) {
        $DB->prepared_query($query, ...$params);

        $results = $DB->to_array('GroupID', MYSQLI_ASSOC, false);
        $ranks = Votes::calc_ranks($DB->to_pair('GroupID', 'Score', false));

        $groups = Torrents::get_groups(array_keys($results));

        $topVotes = [];
        foreach ($results as $groupID => $votes) {
            $topVotes[$groupID] = $groups[$groupID];
            $topVotes[$groupID]['Ups'] = $votes['Ups'];
            $topVotes[$groupID]['Total'] = $votes['Total'];
            $topVotes[$groupID]['Score'] = $votes['Score'];
            $topVotes[$groupID]['Rank'] = $ranks[$groupID];
        }

        $Cache->cache_value('top10votes_'.$limit.$whereSum, $topVotes, 60 * 30);
        $Cache->clear_query_lock('top10votes');
    } else {
        $topVotes = false;
    }
}
View::show_header("Top $limit Voted Groups",'browse,voting');
?>
<div class="thin">
    <div class="header">
        <h2>Top <?=$limit?> Voted Groups</h2>
        <?php \Gazelle\Top10::renderLinkbox("votes"); ?>
    </div>
<?php
$all = ($_GET['anyall'] ?? 'all') === 'all';
if (check_perms('site_advanced_top10')) { ?>
    <form class="search_form" name="votes" action="" method="get">
        <input type="hidden" name="advanced" value="1" />
        <input type="hidden" name="type" value="votes" />
        <table cellpadding="6" cellspacing="1" border="0" class="layout border" width="100%">
            <tr id="tagfilter">
                <td class="label">Tags (comma-separated):</td>
                <td class="ft_taglist">
                    <input type="text" name="tags" size="75" value="<?php if (!empty($_GET['tags'])) { echo display_str($_GET['tags']);} ?>" />&nbsp;
                    <input type="radio" id="rdoAll" name="anyall" value="all"<?=($all ? ' checked="checked"' : '')?> /><label for="rdoAll"> All</label>&nbsp;&nbsp;
                    <input type="radio" id="rdoAny" name="anyall" value="any"<?=(!$all ? ' checked="checked"' : '')?> /><label for="rdoAny"> Any</label>
                </td>
            </tr>
            <tr id="yearfilter">
                <td class="label">Year:</td>
                <td class="ft_year">
                    <input type="text" name="year1" size="4" value="<?php if (!empty($_GET['year1'])) { echo display_str($_GET['year1']);} ?>" />
                    to
                    <input type="text" name="year2" size="4" value="<?php if (!empty($_GET['year2'])) { echo display_str($_GET['year2']);} ?>" />
                </td>
            </tr>
            <tr>
                <td colspan="2" class="center">
                    <input type="submit" value="Filter torrents" />
                </td>
            </tr>
        </table>
    </form>
<?php
}

$bookmark = new \Gazelle\Bookmark;
?>
    <h3>Top <?=$limit?>
<?php
if (empty($_GET['advanced'])) { ?>
        <small class="top10_quantity_links">
<?php
    switch ($limit) {
        case 100: ?>
            - <a href="top10.php?type=votes" class="brackets">Top 25</a>
            - <span class="brackets">Top 100</span>
            - <a href="top10.php?type=votes&amp;limit=250" class="brackets">Top 250</a>
<?php            break;
        case 250: ?>
            - <a href="top10.php?type=votes" class="brackets">Top 25</a>
            - <a href="top10.php?type=votes&amp;limit=100" class="brackets">Top 100</a>
            - <span class="brackets">Top 250</span>
<?php            break;
        default: ?>
            - <span class="brackets">Top 25</span>
            - <a href="top10.php?type=votes&amp;limit=100" class="brackets">Top 100</a>
            - <a href="top10.php?type=votes&amp;limit=250" class="brackets">Top 250</a>
<?php    } ?>
        </small>
<?php
} ?>
    </h3>
<?php

$number = 0;
$torrentTable = '';
foreach ($topVotes as $groupID => $group) {
    $groupName = $group['Name'];
    $groupYear = $group['Year'];
    $groupCategoryID = $group['CategoryID'];
    $torrentTags = new Tags($group['TagList']);
    $wikiImage = $group['WikiImage'];
    $torrents = isset($group['Torrents']) ? $group['Torrents'] : [];
    $artists = $group['Artists'];
    $extendedArtists = $group['ExtendedArtists'];
    $upVotes = $group['Ups'];
    $totalVotes = $group['Total'];
    $score = $group['Score'];
    $downVotes = $totalVotes - $upVotes;

    $isBookmarked = $bookmark->isTorrentBookmarked($LoggedUser['ID'], $groupID);
    $userVote = isset($userVotes[$groupID]) ? $userVotes[$groupID]['Type'] : '';

    $displayName = $group['Rank'] . " - ";

    if (!empty($extendedArtists[1]) || !empty($extendedArtists[4]) || !empty($extendedArtists[5])|| !empty($extendedArtists[6])) {
        unset($extendedArtists[2]);
        unset($extendedArtists[3]);
        $displayName .= Artists::display_artists($extendedArtists);
    } elseif (count($artists) > 0) {
        $displayName .= Artists::display_artists(['1' => $artists]);
    }

    $displayName .= '<a href="torrents.php?id='.$groupID.'" class="tooltip" title="View torrent group" dir="ltr">'.$groupName.'</a>';
    if ($groupYear > 0) {
        $displayName = $displayName. " [$groupYear]";
    }
    if ($group['VanityHouse']) {
        $displayName .= ' [<abbr class="tooltip" title="This is a Vanity House release">VH</abbr>]';
    }
    // Start an output buffer, so we can store this output in $torrentTable
    ob_start();

    if (count($torrents) > 1 || $groupCategoryID == 1) {
        // Grouped torrents
        $groupSnatched = false;
        foreach ($torrents as &$torrent) {
            if (($torrent['IsSnatched'] = Torrents::has_snatched($torrent['ID'])) && !$groupSnatched) {
                $groupSnatched = true;
            }
        }
        unset($torrent);
        $snatchedGroupClass = $groupSnatched ? ' snatched_group' : '';
?>
                <tr class="group groupid_<?=$groupID?>_header discog<?=$snatchedGroupClass?>" id="group_<?=$groupID?>">
                    <td class="center">
                        <div id="showimg_<?=$groupID?>" class="show_torrents">
                            <a href="#" class="tooltip show_torrents_link" onclick="toggle_group(<?=$groupID?>, this, event);" title="Expand this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to expand all groups on this page."></a>
                        </div>
                    </td>
                    <td class="center cats_col">
                        <div title="<?=$torrentTags->title()?>" class="tooltip <?=Format::css_category($groupCategoryID)?> <?=$torrentTags->css_name()?>"></div>
                    </td>
                    <td class="big_info">
<?php        if ($LoggedUser['CoverArt']) { ?>
                        <div class="group_image float_left clear">
                            <?php ImageTools::cover_thumb($wikiImage, $groupCategoryID) ?>
                        </div>
<?php        } ?>
                        <div class="group_info clear">

                            <strong><?=$displayName?></strong> <!--<?php Votes::vote_link($groupID, $userVote); ?>-->
<?php        if ($isBookmarked) { ?>
                            <span class="remove_bookmark float_right">
                                <a href="#" class="bookmarklink_torrent_<?=$groupID?> brackets" onclick="Unbookmark('torrent', <?=$groupID?>, 'Bookmark'); return false;">Remove bookmark</a>
                            </span>
<?php        } else { ?>
                            <span class="add_bookmark float_right">
                                <a href="#" class="bookmarklink_torrent_<?=$groupID?> brackets" onclick="Bookmark('torrent', <?=$groupID?>, 'Remove bookmark'); return false;">Bookmark</a>
                            </span>
<?php        } ?>
                            <div class="tags"><?=$torrentTags->format()?></div>

                        </div>
                    </td>
                    <td colspan="4" class="votes_info_td">
                        <span style="white-space: nowrap;">
                            <span class="favoritecount_small tooltip" title="<?=$upVotes . ($upVotes == 1 ? ' upvote' : ' upvotes')?>"><span id="upvotes"><?=number_format($upVotes)?></span> <span class="vote_album_up">&and;</span></span>
                            &nbsp; &nbsp;
                            <span class="favoritecount_small tooltip" title="<?=$downVotes . ($downVotes == 1 ? ' downvote' : ' downvotes')?>"><span id="downvotes"><?=number_format($downVotes)?></span> <span class="vote_album_down">&or;</span></span>
                            &nbsp;
                            <span style="float: right;"><span class="favoritecount_small" id="totalvotes"><?=number_format($totalVotes)?></span> Total</span>
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
        $lastRemasterYear = '-';
        $lastRemasterTitle = '';
        $lastRemasterRecordLabel = '';
        $lastRemasterCatalogueNumber = '';
        $lastMedia = '';

        $editionID = 0;
        unset($firstUnknown);

        foreach ($torrents as $torrentID => $torrent) {
            //Get report info, use the cache if available, if not, add to it.
            $reported = false;
            $reports = Torrents::get_reports($torrentID);
            if (count($reports) > 0) {
                $reported = true;
            }
            if ($torrent['Remastered'] && !$torrent['RemasterYear']) {
                $firstUnknown = !isset($firstUnknown);
            }
            $snatchedTorrentClass = $torrent['IsSnatched'] ? ' snatched_torrent' : '';

            if ($torrent['RemasterTitle'] != $lastRemasterTitle
                || $torrent['RemasterYear'] != $lastRemasterYear
                || $torrent['RemasterRecordLabel'] != $lastRemasterRecordLabel
                || $torrent['RemasterCatalogueNumber'] != $lastRemasterCatalogueNumber
                || (isset($firstUnknown) && $firstUnknown)
                || $torrent['Media'] != $lastMedia
                ) {
                $editionID++;
?>
        <tr class="group_torrent groupid_<?=$groupID?> edition<?=$snatchedGroupClass?> hidden">
            <td colspan="7" class="edition_info"><strong><a href="#" onclick="toggle_edition(<?=$groupID?>, <?=$editionID?>, this, event);" class="tooltip" title="Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.">&minus;</a> <?=Torrents::edition_string($torrent, $group)?></strong></td>
        </tr>
<?php
            }
            $lastRemasterTitle = $torrent['RemasterTitle'];
            $lastRemasterYear = $torrent['RemasterYear'];
            $lastRemasterRecordLabel = $torrent['RemasterRecordLabel'];
            $lastRemasterCatalogueNumber = $torrent['RemasterCatalogueNumber'];
            $lastMedia = $torrent['Media'];
?>
        <tr class="group_torrent torrent_row groupid_<?=$groupID?> edition_<?=$editionID?><?=$snatchedTorrentClass . $snatchedGroupClass?> hidden">
            <td colspan="3">
                <span>
                    [ <a href="torrents.php?action=download&amp;id=<?=$torrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" class="tooltip" title="Download">DL</a>
<?php            if (Torrents::can_use_token($torrent)) { ?>
                    | <a href="torrents.php?action=download&amp;id=<?=$torrentID ?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>&amp;usetoken=1" class="tooltip" title="Use a FL Token" onclick="return confirm('<?=FL_confirmation_msg($torrent['Seeders'], $torrent['Size'])?>');">FL</a>
<?php            } ?>
                    | <a href="reportsv2.php?action=report&amp;id=<?=$torrentID?>" class="tooltip" title="Report">RP</a> ]
                </span>
                &nbsp;&nbsp;&raquo;&nbsp; <a href="torrents.php?id=<?=$groupID?>&amp;torrentid=<?=$torrentID?>"><?=Torrents::torrent_info($torrent)?><?php if ($reported) { ?> / <strong class="torrent_label tl_reported">Reported</strong><?php } ?></a>
            </td>
            <td class="number_column nobr"><?=Format::get_size($torrent['Size'])?></td>
            <td class="number_column"><?=number_format($torrent['Snatched'])?></td>
            <td class="number_column<?=($torrent['Seeders'] == 0) ? ' r00' : '' ?>"><?=number_format($torrent['Seeders'])?></td>
            <td class="number_column"><?=number_format($torrent['Leechers'])?></td>
        </tr>
<?php
        }
    } else { //if (count($torrents) > 1 || $groupCategoryID == 1)
        // Viewing a type that does not require grouping
        $torrentID = key($torrents);
        $torrent = current($torrents);
        $torrent['IsSnatched'] = Torrents::has_snatched($torrentID);

        $displayName = $number .' - <a href="torrents.php?id='.$groupID.'" class="tooltip" title="View torrent group" dir="ltr">'.$groupName.'</a>';
        if ($torrent['IsSnatched']) {
            $displayName .= ' ' . Format::torrent_label('Snatched!');
        }
        if ($torrent['FreeTorrent'] == '1') {
            $displayName .= ' ' . Format::torrent_label('Freeleech!');
        } elseif ($torrent['FreeTorrent'] == '2') {
            $displayName .= ' ' . Format::torrent_label('Neutral leech!');
        } elseif (Torrents::has_token($torrentID)) {
            $displayName .= ' ' . Format::torrent_label('Personal freeleech!');
        }
        $snatchedTorrentClass = $torrent['IsSnatched'] ? ' snatched_torrent' : '';
?>
        <tr class="torrent torrent_row<?=$snatchedTorrentClass . $snatchedGroupClass?>" id="group_<?=$groupID?>">
            <td></td>
            <td class="center cats_col">
                <div title="<?=$torrentTags->title()?>" class="tooltip <?=Format::css_category($groupCategoryID)?> <?=$torrentTags->css_name()?>">
                </div>
            </td>
            <td class="nobr big_info">
<?php        if ($LoggedUser['CoverArt']) { ?>
                <div class="group_image float_left clear">
                    <?php ImageTools::cover_thumb($wikiImage, $groupCategoryID) ?>
                </div>
<?php        } ?>
                <div class="group_info clear">
                    <span>
                        [ <a href="torrents.php?action=download&amp;id=<?=$torrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" class="tooltip" title="Download">DL</a>
<?php        if (Torrents::can_use_token($torrent)) { ?>
                        | <a href="torrents.php?action=download&amp;id=<?=$torrentID ?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>&amp;usetoken=1" class="tooltip" title="Use a FL Token" onclick="return confirm('<?=FL_confirmation_msg($torrent['Seeders'], $torrent['Size'])?>');">FL</a>
<?php        } ?>
                        | <a href="reportsv2.php?action=report&amp;id=<?=$torrentID?>" class="tooltip" title="Report">RP</a>
<?php        if ($isBookmarked) { ?>
                        | <a href="#" id="bookmarklink_torrent_<?=$groupID?>" class="remove_bookmark" onclick="Unbookmark('torrent', <?=$groupID?>, 'Bookmark'); return false;">Remove bookmark</a>
<?php        } else { ?>
                        | <a href="#" id="bookmarklink_torrent_<?=$groupID?>" class="add_bookmark" onclick="Bookmark('torrent', <?=$groupID?>, 'Remove bookmark'); return false;">Bookmark</a>
<?php        } ?>
                        ]
                    </span>
                    <strong><?=$displayName?></strong> <!--<?php Votes::vote_link($groupID, $userVote); ?>-->
                    <div class="tags"><?=$torrentTags->format()?></div>
                </div>
            </td>
            <td class="number_column nobr"><?=Format::get_size($torrent['Size'])?></td>
            <td class="number_column"><?=number_format($torrent['Snatched'])?></td>
            <td class="number_column<?=($torrent['Seeders'] == 0) ? ' r00' : '' ?>"><?=number_format($torrent['Seeders'])?></td>
            <td class="number_column"><?=number_format($torrent['Leechers'])?></td>
        </tr>
<?php
    } //if (count($torrents) > 1 || $groupCategoryID == 1)
    $torrentTable .= ob_get_clean();
}
?>
<table class="torrent_table grouping cats m_table" id="discog_table">
    <tr class="colhead_dark">
        <td><!-- expand/collapse --></td>
        <td class="cats_col"><!-- category --></td>
        <td class="m_th_left" width="70%">Torrents</td>
        <td>Size</td>
        <td class="sign snatches"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/snatched.png" alt="Snatches" title="Snatches" class="tooltip" /></td>
        <td class="sign seeders"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/seeders.png" alt="Seeders" title="Seeders" class="tooltip" /></td>
        <td class="sign leechers"><img src="static/styles/<?=$LoggedUser['StyleName'] ?>/images/leechers.png" alt="Leechers" title="Leechers" class="tooltip" /></td>
    </tr>
<?php
if ($topVotes === false) { ?>
    <tr>
        <td colspan="7" class="center">Server is busy processing another top list request. Please try again in a minute.</td>
    </tr>
<?php
} elseif (count($topVotes) === 0) { ?>
    <tr>
        <td colspan="7" class="center">No torrents were found that meet your criteria.</td>
    </tr>
<?php
} else {
    echo $torrentTable;
}
?>
</table>
</div>
<?php
View::show_footer();
