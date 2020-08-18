<?php
$appMan = new Gazelle\Manager\Applicant;
if (isset($_POST['auth'])) {
    authorize();
    $Role = array_key_exists('role', $_POST) ? trim($_POST['role']) : '';
    $Body = array_key_exists('body', $_POST) ? trim($_POST['body']) : '';

    if (strlen($Role)) {
        if (strlen($Body) > 80) {
            $Applicant = $appMan->createApplicant($LoggedUser['ID'], $Role, $Body);
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
<?php
    if (check_perms('admin_manage_applicants') || $appMan->userIsApplicant($LoggedUser['ID'])) { ?>
        <div class="linkbox">
    <?php if (check_perms('admin_manage_applicants')) { ?>
            <a href="/apply.php?action=view" class="brackets">Current applications</a>
            <a href="/apply.php?action=view&status=resolved" class="brackets">Resolved applications</a>
            <a href="/apply.php?action=admin" class="brackets">Manage roles</a>
    <?php }
    if ($appMan->userIsApplicant($LoggedUser['ID'])) { ?>
            <a href="/apply.php?action=view" class="brackets">View your application</a>
    <?php } ?>
        </div>
<?php
    } ?>
    </div>

<?php
$appRoleMan = new Gazelle\Manager\ApplicantRole;
$Roles = $appRoleMan->list();
if ($Roles) { ?>
    <div class="box">
        <div class="head">Open Roles</div>
        <div class="pad">
            <table>
<?php
    foreach ($Roles as $title => $info) { ?>
                <tr>
                    <td><div class="head"><?= $title ?></div></td>
                </tr>
                <tr>
                    <td><div class="pad"><?= Text::full_format($info['description']) ?></div></td>
                </tr>
<?php
    } /* foreach */ ?>
            </table>
        </div>
    </div>
<?php
} ?>

<?php
if (count($Roles) == 0) { ?>
    <div class="box pad">
    <p>Thanks for your interest in helping Orpheus! There are
    no openings at the moment. Keep an eye on the front page
    or the Orpheus forum for announcements in the future.</p>
    </div>
<?php
} else {
    if ($Error) {
?>
    <div class="important"><?=$Error?></div>
<?php
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
<?php
    foreach (array_keys($Roles) as $title) { ?>
                        <option value="<?=$title?>"<?=$Role == $title ? ' selected' : ''?>><?=$title?></option>
<?php
    } ?>
                    </select>
                </div>
                <div class="head">Your cover letter</div>
                <div class="pad">At least 80 characters, now convince us!
<?php
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
<?php
} /* else */ ?>
</div>

<?php View::show_footer();
