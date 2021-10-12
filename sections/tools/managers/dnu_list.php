<?php

if (!$Viewer->permitted('admin_dnu')) {
    error(403);
}

$DB->prepared_query("
    SELECT
        d.ID,
        d.Name,
        d.Comment,
        d.UserID,
        d.Time
    FROM do_not_upload AS d
    ORDER BY d.Sequence
");

$Title = 'Manage the "Do Not Upload" list';
View::show_header($Title, ['js' => 'jquery-ui,dnu_list']);
?>
    <div class="header">
        <h2><?=($Title)?></h2>
        <center><p>Drag and drop table rows to reorder.</p></center>
    </div>
    <table id="dnu">
        <tr class="colhead">
            <td colspan="4">Add an entry to the "Do Not Upload" list</td>
        </tr>
        <tr class="rowa">
            <form class="add_form" name="dnu" action="tools.php" method="post">
                <input type="hidden" name="action" value="dnu_alter" />
                <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                <td>
                    <input type="text" name="name" size="40" />
                </td>
                <td colspan="2">
                    <input type="text" name="comment" size="60" />
                </td>
                <td>
                    <input type="submit" value="Create" />
                </td>
            </form>
        </tr>
        <tr class="colhead">
            <td>Name</td>
            <td>Comment</td>
            <td>Added</td>
            <td>Submit</td>
        </tr>
        <tbody>
<?php while (list($ID, $Name, $Comment, $UserID, $DNUTime) = $DB->next_record()) { ?>
            <tr id="item_<?=$ID?>">
                <form class="manage_form dnu" action="tools.php" method="post">
                    <td>
                        <input type="hidden" name="action" value="dnu_alter" />
                        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                        <input type="hidden" name="id" value="<?=$ID?>" />
                        <input type="text" name="name" value="<?=display_str($Name)?>" size="40" />
                    </td>
                    <td>
                        <input type="text" name="comment" value="<?=display_str($Comment)?>" size="60" />
                    </td>
                    <td>
                        <?=Users::format_username($UserID, false, false, false)?><br />
                        <?php    echo time_diff($DNUTime, 1) . "\n"; ?>
                    </td>
                    <td>
                        <input type="submit" name="submit" value="Edit" />
                        <input type="submit" name="submit" value="Delete" />
                    </td>
                </form>
            </tr>
<?php } ?>
        </tbody>
    </table>
<?php

View::show_footer();
