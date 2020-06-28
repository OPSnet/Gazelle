<?php
$PerPage = POSTS_PER_PAGE;
list($Page, $Limit) = Format::page_limit($PerPage);

$CanEdit = check_perms('users_mod');

if ($CanEdit && isset($_POST['perform'])) {
    authorize();
    if ($_POST['perform'] === 'add' && !empty($_POST['message'])) {
        $DB->prepared_query("
            INSERT INTO changelog
                   (Message, Author)
            VALUES (?,       ?)
            ", trim($_POST['message']), trim($_POST['author'])
        );
        $ID = $DB->inserted_id();
    }
    if ($_POST['perform'] === 'remove' && !empty($_POST['change_id'])) {
        $DB->prepared_query("
            DELETE FROM changelog WHERE ID = ?
            ", (int)$_POST['change_id']
        );
    }
}

$NumResults = $DB->scalar("
    SELECT count(*) FROM changelog
");
$DB->prepared_query("
    SELECT
        ID,
        Message,
        Author,
        Date(Time) as Time2
    FROM changelog
    ORDER BY Time DESC
    LIMIT $Limit
");
$ChangeLog = $DB->to_array();

View::show_header('Gazelle Change Log', 'datetime_picker', 'datetime_picker');
?>
<div class="thin">
    <h2>Gazelle Change Log</h2>
    <div class="linkbox">
<?php
    $Pages = Format::get_pages($Page, $NumResults, $PerPage, 11);
    echo "\t\t$Pages\n";
?>
    </div>
<?php
    if ($CanEdit) { ?>
    <div class="box box2 edit_changelog">
        <div class="head">
            <strong>Manually submit a new change to the change log</strong>
        </div>
        <div class="pad">
            <form method="post" action="">
                <input type="hidden" name="perform" value="add" />
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <div class="field_div" id="cl_message">
                    <span class="label">Commit message:</span>
                    <br />
                    <textarea name="message" rows="2"></textarea>
                </div>
                <!--
                <div class="field_div" id="cl_date">
                    <span class="label">Date:</span>
                    <br />
                    <input type="text" class="date_picker" name="date" />
                </div>
                -->
                <div class="field_div" id="cl_author">
                    <span class="label">Author:</span>
                    <br />
                    <input type="text" name="author" value="<?=$LoggedUser['Username']?>" />
                </div>
                <div class="submit_div" id="cl_submit">
                    <input type="submit" value="Submit" />
                </div>
            </form>
        </div>
    </div>
<?php
    }

    foreach ($ChangeLog as $Change) {
?>
    <div class="box box2 change_log_entry">
        <div class="head">
            <span><?=$Change['Time2']?> by <?=$Change['Author']?></span>
<?php   if ($CanEdit) { ?>
            <span style="float: right;">
                <form id="delete_<?=$Change['ID']?>" method="post" action="">
                    <input type="hidden" name="perform" value="remove" />
                    <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                    <input type="hidden" name="change_id" value="<?=$Change['ID']?>" />
                </form>
                <a href="#" onclick="$('#delete_<?=$Change['ID']?>').raw().submit(); return false;" class="brackets">Delete</a>
            </span>
<?php   } ?>
        </div>
        <div class="pad">
            <?=$Change['Message']?>
        </div>
    </div>
<?php
    } ?>
</div>
<?php
View::show_footer();
