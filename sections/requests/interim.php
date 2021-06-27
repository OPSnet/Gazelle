<?php
if (!isset($_GET['id']) || !intval($_GET['id'])) {
    error(404);
}

$Action = $_GET['action'];
if ($Action !== 'unfill' && $Action !== 'delete') {
    error(404);
}

$DB->prepared_query('
    SELECT UserID, FillerID
    FROM requests
    WHERE ID = ?',
    $_GET['id']);
list($RequestorID, $FillerID) = $DB->next_record();

if ($Action === 'unfill') {
    if (!in_array($Viewer->id(), [$RequestorID, $FillerID]) && !check_perms('site_moderate_requests')) {
        error(403);
    }
} elseif ($Action === 'delete') {
    if ($Viewer->id() != $RequestorID && !check_perms('site_moderate_requests')) {
        error(403);
    }
}

View::show_header(ucwords($Action) . ' Request');
?>
<div class="thin center">
    <div class="box" style="width: 600px; margin: 0px auto;">
        <div class="head colhead">
            <?=ucwords($Action)?> Request
        </div>
        <div class="pad">
            <form class="<?=(($Action === 'delete') ? 'delete_form' : 'edit_form')?>" name="request" action="requests.php" method="post">
                <input type="hidden" name="action" value="take<?=$Action?>" />
                <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
                <input type="hidden" name="id" value="<?=$_GET['id']?>" />
<?php
    if ($Action === 'delete') { ?>
                <div class="warning">You will <strong>not</strong> get your bounty back if you delete this request.</div>
<?php
    } ?>
                <strong>Reason:</strong>
                <input type="text" name="reason" size="30" />
                <input value="<?=ucwords($Action)?>" type="submit" />
            </form>
        </div>
    </div>
</div>
<?php
View::show_footer();
