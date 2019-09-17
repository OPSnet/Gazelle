<?
if (isset($_POST['auth'])) {
    authorize();
    $Role = array_key_exists('role', $_POST) ? trim($_POST['role']) : '';
    $Body = array_key_exists('body', $_POST) ? trim($_POST['body']) : '';

    if (strlen($Role)) {
        if (strlen($Body) > 80) {
            $Applicant = new Applicant($LoggedUser['ID'], $Role, $Body);
            header('Location: /apply.php?action=view&id=' . $Applicant->id());
            exit;
        }
        else {
            $Error = "You need to explain things a bit more.";
        }
    }
    else {
        $Error = "You need to choose which role interests you.";
    }
}
else {
    $Role = '';
    $Body = '';
}
View::show_header('Apply', 'apply');
?>

<div class="thin">
    <div class="header">
        <h3>Apply for a role at <?=SITE_NAME?></h3>
<? if (check_perms('admin_manage_applicants') || Applicant::user_is_applicant($LoggedUser['ID'])) { ?>
        <div class="linkbox">
    <? if (check_perms('admin_manage_applicants')) { ?>
            <a href="/apply.php?action=view" class="brackets">Current applications</a>
            <a href="/apply.php?action=view&status=resolved" class="brackets">Resolved applications</a>
            <a href="/apply.php?action=admin" class="brackets">Manage roles</a>
    <? }
    if (Applicant::user_is_applicant($LoggedUser['ID'])) { ?>
            <a href="/apply.php?action=view" class="brackets">View your application</a>
    <? } ?>
        </div>
<? } ?>
    </div>

<?php
$Roles = ApplicantRole::get_list();
if (count($Roles)) { ?>
    <div class="box">
        <div class="head">Open Roles</div>
        <div class="pad">
            <table>
<?    foreach ($Roles as $title => $info) { ?>
                <tr>
                    <td><div class="head"><?= $title ?></div></td>
                </tr>
                <tr>
                    <td><div class="pad"><?= Text::full_format($info['description']) ?></div></td>
                </tr>
<?    } /* foreach */ ?>
            </table>
        </div>
    </div>
<? } ?>

<? if (count($Roles) == 0) { ?>
    <div class="box pad">
    <p>Thanks for your interest in helping Orpheus! There are
    no openings at the moment. Keep an eye on the front page
    or the Orpheus forum for announcements in the future.</p>
    </div>
<?
} else {
    if ($Error) {
?>
    <div class="important"><?=$Error?></div>
<?
    }
?>
    <form class="send_form" id="applicationform" name="apply" action="/apply.php?action=save" method="post">
        <div class="box">
            <div id="quickpost">
                <div class="head">Your Role at <?= SITE_NAME ?></div>
                <div class="pad">
                    <div>Choose a role from the following list:</div>
                    <select name="role">
                        <option value="">---</option>
<?    foreach (array_keys($Roles) as $title) { ?>
                        <option value="<?=$title?>"<?=$Role == $title ? ' selected' : ''?>><?=$title?></option>
<?    } ?>
                    </select>
                </div>
                <div class="head">Your cover letter</div>
                <div class="pad">At least 80 characters, now convince us!
<?
                $text = new TEXTAREA_PREVIEW('body', 'body', $Body, 95, 20, false, false);
                echo $text->preview();
?>
                </div>
            </div>

            <div id="buttons" class="center">
                <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
                <input type="button" value="Preview" class="hidden button_preview_<?= $text->getId() ?>" />
                <input type="submit" value="Send Application" />
            </div>
        </div>
    </form>
<? } /* else */ ?>
</div>

<? View::show_footer();
