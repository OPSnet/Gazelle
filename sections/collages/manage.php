<?php

$collage = new Gazelle\Collage((int)($_GET['collageid'] ?? $_GET['id'] ?? 0));
if (is_null($collage) || $collage->isArtist()) {
    error(404);
}
if ($collage->isPersonal() && !$collage->isOwner($Viewer->id()) && !$Viewer->permitted('site_collages_delete')) {
    error(403);
}

$tgroupIds = $collage->groupIds();
$tgMan = new Gazelle\Manager\TGroup;
$userMan = new Gazelle\Manager\User;

View::show_header("Manage collage: " . $collage->name(), ['js' => 'jquery-ui,jquery.tablesorter,sort']);
?>
<div class="thin">
    <div class="header">
        <h2>Manage collage <?= $collage->link() ?></h2>
    </div>
    <table width="100%" class="layout">
        <tr class="colhead"><td id="sorting_head">Sorting</td></tr>
        <tr>
            <td id="drag_drop_textnote">
            <ul>
                <li>Click on the headings to organize columns automatically.</li>
                <li>Sort multiple columns simultaneously by holding down the shift key and clicking other column headers.</li>
                <li>Click and drag any row to change its order.</li>
                <li>Press "Save All Changes" when you are finished sorting.</li>
                <li>Press "Edit" or "Remove" to simply modify one entry.</li>
            </ul>
            </td>
        </tr>
    </table>

    <div class="drag_drop_save hidden">
        <input type="button" name="submit" value="Save All Changes" class="save_sortable_collage" />
    </div>
    <table id="manage_collage_table">
        <thead>
            <tr class="colhead">
                <th style="width: 7%;" data-sorter="false">Order</th>
                <th style="width: 1%;"><span><abbr class="tooltip" title="Current rank">#</abbr></span></th>
                <th style="width: 7%;"><span>Cat.&nbsp;#</span></th>
                <th style="width: 1%;"><span>Year</span></th>
                <th style="width: 15%;" data-sorter="ignoreArticles"><span>Artist</span></th>
                <th data-sorter="ignoreArticles"><span>Torrent</span></th>
                <th style="width: 1%;"><span>User</span></th>
                <th style="width: 1%; text-align: right;" class="nobr" data-sorter="false"><span><abbr class="tooltip" title="Modify an individual row">Tweak</abbr></span></th>
            </tr>
        </thead>
        <tbody>
<?php

    $Number = 0;
    foreach ($tgroupIds as $tgroupId) {
        $tgroup = $tgMan->findById($tgroupId);
        if (is_null($tgroup)) {
            continue;
        }
        $Number++;
?>
            <tr class="drag <?= ($Number % 2 === 0) ? 'rowa' : 'rowb' ?>" id="li_<?=$tgroup->id() ?>">
                <form class="manage_form" name="collage" action="collages.php" method="post">
                    <td>
                        <input class="sort_numbers" type="text" name="sort" value="<?=$collage->sequence($tgroup->id()) ?>" id="sort_<?=$tgroup->id() ?>" size="4" />
                    </td>
                    <td><?=$Number?></td>
                    <td><?=$tgroup->catalogueNumber() ?: '&nbsp;'?></td>
                    <td><?=$tgroup->year() ?: '&nbsp;'?></td>
                    <td><?=$tgroup->artistHtml() ?: '&nbsp;'?></td>
                    <td><a href="torrents.php?id= <?= $tgroup->id() ?>" class="tooltip" title="View torrent group"><?= $tgroup->name() ?></a></td>
                    <td class="nobr"><?= $userMan->findById($collage->entryUserId($tgroupId))->link() ?></td>
                    <td class="nobr">
                        <input type="hidden" name="action" value="manage_handle" />
                        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                        <input type="hidden" name="collageid" value="<?=$collage->id() ?>" />
                        <input type="hidden" name="groupid" value="<?=$tgroup->id()?>" />
                        <input type="submit" name="submit" value="Edit" />
                        <input type="submit" name="submit" value="Remove" />
                    </td>
                </form>
            </tr>
<?php
    } ?>
        </tbody>
    </table>
    <div class="drag_drop_save hidden">
        <input type="button" name="submit" value="Save All Changes" class="save_sortable_collage" />
    </div>
    <form class="dragdrop_form hidden" name="collage" action="collages.php" method="post" id="drag_drop_collage_form">
        <div>
            <input type="hidden" name="action" value="manage_handle" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="collageid" value="<?=$collage->id() ?>" />
            <input type="hidden" name="groupid" value="1" />
            <input type="hidden" name="drag_drop_collage_sort_order" id="drag_drop_collage_sort_order" readonly="readonly" value="" />
        </div>
    </form>
</div>
<?php
View::show_footer();
