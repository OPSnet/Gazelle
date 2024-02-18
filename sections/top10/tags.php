<?php

$details = $_GET['details'] ?? 'all';
$details = in_array($_GET['details'] ?? '', ['top_used', 'top_request', 'top_voted'])
    ? $details : 'all';

$limit = $_GET['limit'] ?? 10;
$limit = in_array($limit, [10, 100, 250]) ? $limit : 10;

$tag = new Gazelle\Manager\Tag();

View::show_header(TOP_TEN_HEADING . " – Tags");
?>
<div class="thin">
    <div class="header">
        <h2><?= TOP_TEN_HEADING ?> – Tags</h2>
        <?= $Twig->render('top10/linkbox.twig', ['selected' => 'tags']) ?>
    </div>

<?php

if (in_array($details, ['all', 'top_used'])) {
    generate_tag_table('Most Used Torrent Tags', 'top_used', $tag->topTGroupList($limit), $limit);
}

if (in_array($details, ['all', 'top_request'])) {
    generate_tag_table('Most Used Request Tags', 'top_request', $tag->topRequestList($limit), $limit, request: true);
}

if (in_array($details, ['all', 'top_voted'])) {
    generate_tag_table('Most Highly Voted Tags', 'top_voted', $tag->topVotedList($limit), $limit);
}

echo '</div>';
View::show_footer();

// generate a table based on data from most recent query
function generate_tag_table(string $caption, string $tag, array $details, int $limit, bool $request = false): void {
?>
    <h3>Top <?= $limit ?> <?= $caption ?>
        <small class="top10_quantity_links">
<?php if ($limit == 100) { ?>
            - <a href="top10.php?type=tags&amp;details=<?=$tag?>" class="brackets">Top 10</a>
            - <span class="brackets">Top 100</span>
            - <a href="top10.php?type=tags&amp;limit=250&amp;details=<?=$tag?>" class="brackets">Top 250</a>
<?php } elseif ($limit == 250) { ?>
            - <a href="top10.php?type=tags&amp;details=<?=$tag?>" class="brackets">Top 10</a>
            - <a href="top10.php?type=tags&amp;limit=100&amp;details=<?=$tag?>" class="brackets">Top 100</a>
            - <span class="brackets">Top 250</span>
<?php } else { ?>
            - <span class="brackets">Top 10</span>
            - <a href="top10.php?type=tags&amp;limit=100&amp;details=<?=$tag?>" class="brackets">Top 100</a>
            - <a href="top10.php?type=tags&amp;limit=250&amp;details=<?=$tag?>" class="brackets">Top 250</a>
<?php } ?>
        </small>
    </h3>
    <table class="border">
    <tr class="colhead">
        <td class="center">Rank</td>
        <td>Tag</td>
        <td style="text-align: right;">Uses</td>
<?php if (!$request) { ?>
        <td style="text-align: right;">Pos. votes</td>
        <td style="text-align: right;">Neg. votes</td>
<?php } ?>
    </tr>
<?php if (empty($details)) { ?>
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
        <td><a href="<?= $request ? 'requests.php?tags=' : 'torrents.php?taglist=' ?><?=$detail['name']?>"><?=$detail['name']?></a></td>
        <td class="number_column"><?=number_format($detail['uses'])?></td>
<?php   if (!$request) { ?>
        <td class="number_column"><?=number_format($detail['posVotes'])?></td>
        <td class="number_column"><?=number_format($detail['negVotes'])?></td>
<?php   } ?>
    </tr>
<?php
    }
    echo '</table><br />';
}
