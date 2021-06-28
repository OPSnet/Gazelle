<?php

$ArticleID = (int)$_GET['id'];
if (!$ArticleID) {
    error(404);
}

$wikiMan = new Gazelle\Manager\Wiki;
[$Revision, $Title, $Body, $Read, $Edit, $Date, $AuthorID] = $wikiMan->article($ArticleID);
if ($Read > $Viewer->effectiveClass()) {
    error(404);
}

View::show_header("Revisions of ".$Title);
?>
<div class="thin">
    <div class="header">
        <h2><a href="wiki.php">Wiki</a> &rsaquo; <a href="wiki.php?action=article&amp;id=<?=$ArticleID?>"><?=$Title?></a> &rsaquo; Revision history</h2>
    </div>
    <form action="wiki.php" method="get">
        <input type="hidden" name="action" id="action" value="compare" />
        <input type="hidden" name="id" id="id" value="<?=$ArticleID?>" />
        <table>
            <tr class="colhead">
                <td>Revision</td>
                <td>Title</td>
                <td>Author</td>
                <td>Age</td>
                <td>Old</td>
                <td>New</td>
            </tr>
            <tr>
                <td><?=$Revision?></td>
                <td><?=$Title?></td>
                <td><?=Users::format_username($AuthorID, false, false, false)?></td>
                <td><?=time_diff($Date)?></td>
                <td><input type="radio" name="old" value="<?=$Revision?>" disabled="disabled" /></td>
                <td><input type="radio" name="new" value="<?=$Revision?>" checked="checked" /></td>
            </tr>
<?php
$link = $wikiMan->revisions($ArticleID);
while ([$Revision, $Title, $AuthorID, $Date] = $link->next_record()) { ?>
            <tr>
                <td><?=$Revision?></td>
                <td><?=$Title?></td>
                <td><?=Users::format_username($AuthorID, false, false, false)?></td>
                <td><?=time_diff($Date)?></td>
                <td><input type="radio" name="old" value="<?=$Revision?>" /></td>
                <td><input type="radio" name="new" value="<?=$Revision?>" /></td>
            </tr>
<?php } ?>
            <tr>
                <td class="center" colspan="6">
                    <input type="submit" value="Compare" />
                </td>
            </tr>
        </table>
    </form>
</div>
<?php
View::show_footer();
