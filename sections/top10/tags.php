<?php

$details = $_GET['details'] ?? 'all';
$details = in_array($_GET['details'] ?? '', ['top_used', 'top_request', 'top_voted'])
    ? $details : 'all';

$limit = $_GET['limit'] ?? 10;
$limit = in_array($limit, [10, 100, 250]) ? $limit : 10;

$tag = new Gazelle\Top10\Tag;

View::show_header('Top 10 Tags');
?>
<div class="thin">
    <div class="header">
        <h2>Top 10 Tags</h2>
        <?= $Twig->render('top10/linkbox.twig', ['selected' => 'tags']) ?>
    </div>

<?php

if ($details == 'all' || $details == 'top_used') {
    $topUsedTags = $tag->getTopUsedTags($limit);

    generate_tag_table('Most Used Torrent Tags', 'top_used', $topUsedTags, $limit);
}

if ($details == 'all' || $details == 'top_request') {
    $topRequestTags = $tag->getTopRequestTags($limit);

    generate_tag_table('Most Used Request Tags', 'top_request', $topRequestTags, $limit, false, true);
}

if ($details == 'all' || $details == 'top_voted') {
    $topVotedTags = $tag->getTopVotedTags($limit);

    generate_tag_table('Most Highly Voted Tags', 'top_voted', $topVotedTags, $limit);
}

echo '</div>';
View::show_footer();

// generate a table based on data from most recent query
function generate_tag_table(string $caption, string $tag, array $details, int $limit, bool $showVotes = true, bool $requestTable = false): void {
    if ($requestTable) {
        $URLString = 'requests.php?tags=';
    } else {
        $URLString = 'torrents.php?taglist=';
    }
?>
    <h3>Top <?=$limit.' '.$caption?>
        <small class="top10_quantity_links">
<?php
    switch ($limit) {
        case 100: ?>
            - <a href="top10.php?type=tags&amp;details=<?=$tag?>" class="brackets">Top 10</a>
            - <span class="brackets">Top 100</span>
            - <a href="top10.php?type=tags&amp;limit=250&amp;details=<?=$tag?>" class="brackets">Top 250</a>
        <?php    break;
        case 250: ?>
            - <a href="top10.php?type=tags&amp;details=<?=$tag?>" class="brackets">Top 10</a>
            - <a href="top10.php?type=tags&amp;limit=100&amp;details=<?=$tag?>" class="brackets">Top 100</a>
            - <span class="brackets">Top 250</span>
        <?php    break;
        default: ?>
            - <span class="brackets">Top 10</span>
            - <a href="top10.php?type=tags&amp;limit=100&amp;details=<?=$tag?>" class="brackets">Top 100</a>
            - <a href="top10.php?type=tags&amp;limit=250&amp;details=<?=$tag?>" class="brackets">Top 250</a>
<?php
    } ?>
        </small>
    </h3>
    <table class="border">
    <tr class="colhead">
        <td class="center">Rank</td>
        <td>Tag</td>
        <td style="text-align: right;">Uses</td>
<?php
    if ($showVotes) {    ?>
        <td style="text-align: right;">Pos. votes</td>
        <td style="text-align: right;">Neg. votes</td>
<?php
    }    ?>
    </tr>
<?php
    if (empty($details)) { ?>
        <tr class="rowb">
            <td colspan="9" class="center">
                Found no tags matching the criteria
            </td>
        </tr>
        </table><br />
<?php
        return;
    }
    foreach ($details as $index => $detail) { ?>
    <tr class="row<?=$index % 2 ? 'a' : 'b'?>">
        <td class="center"><?= $index + 1 ?></td>
        <td><a href="<?=$URLString?><?=$detail['Name']?>"><?=$detail['Name']?></a></td>
        <td class="number_column"><?=number_format($detail['Uses'])?></td>
<?php   if ($showVotes) { ?>
        <td class="number_column"><?=number_format($detail['PositiveVotes'])?></td>
        <td class="number_column"><?=number_format($detail['NegativeVotes'])?></td>
<?php   } ?>
    </tr>
<?php
    }
    echo '</table><br />';
}
