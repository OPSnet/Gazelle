<?php

$pm = (new Gazelle\Manager\PM($Viewer))->findById((int)$_GET['id']);
if (is_null($pm)) {
    error(404);
}
$pm->markRead();
$postTotal = $pm->postTotal();

$inbox = new Gazelle\Inbox($Viewer);
$inbox->setFolder($_GET['section'] ?? 'inbox');

$paginator = new Gazelle\Util\Paginator(POSTS_PER_PAGE, (int)($_GET['page'] ?? ceil($postTotal / POSTS_PER_PAGE)));
$paginator->setTotal($postTotal);

$postList = $pm->postList($paginator->limit(), $paginator->offset());
$senderList = $pm->senderList();
$recipientList = $pm->recipientList();
$staffPMList = (new Gazelle\Manager\User)->staffPMList();

View::show_header("View conversation " . $pm->subject(), ['js' => 'comments,inbox,bbcode,jquery.validate,form_validate']);
?>
<div class="thin">
    <h2><?= $pm->subject() . ($pm->forwardedTo() ? (" (Forwarded to " . $pm->forwardedTo()->username() . ")") : '') ?></h2>
    <div class="linkbox">
        <a href="<?= $inbox->folderLink($inbox->folder(), $inbox->showUnreadFirst()) ?>" class="brackets">
            Return to <?= $inbox->folder() ?>
        </a>
    </div>
<?= $paginator->linkbox(); ?>

<?php foreach ($postList as $post) { ?>
    <div class="box vertical_space">
        <div class="head" style="overflow: hidden;">
            <div style="float: left;">
                <strong>
<?php if (!isset($senderList[$post['sender_id']])) { ?>
                System</strong> <?=time_diff($post['sent_date'])?>
<?php } else { ?>
                <?= $senderList[$post['sender_id']]->username() ?></strong> <?=time_diff($post['sent_date'])?>
                    - <a href="#quickpost" onclick="Quote('<?= $post['id'] ?>','<?= $senderList[$post['sender_id']]->username() ?>');" class="brackets">Quote</a>
<?php } ?>
            </div>
            <div style="float: right;"><a href="#">&uarr;</a> <a href="#messageform">&darr;</a></div>
        </div>
        <div class="body" id="message<?= $post['id'] ?>">
            <?= Text::full_format($post['body']) ?>
        </div>
    </div>
<?php
}
echo $paginator->linkbox();

if ($recipientList) {
?>
    <h3>Reply</h3>
    <form class="send_form" name="reply" action="inbox.php" method="post" id="messageform">
        <div class="box pad">
            <input type="hidden" name="action" value="takecompose" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="toid" value="<?=implode(',', $recipientList)?>" />
            <input type="hidden" name="convid" value="<?= $pm->id() ?>" />
            <textarea id="quickpost" class="required" name="body" cols="90" rows="10" onkeyup="resize('quickpost');"></textarea> <br />
            <div id="preview" class="box vertical_space body hidden"></div>
            <div id="buttons" class="center">
                <input type="button" value="Preview" onclick="Quick_Preview();" />
                <input type="submit" value="Send message" />
            </div>
        </div>
    </form>
<?php } ?>
    <h3>Manage conversation</h3>
    <form class="manage_form" name="messages" action="inbox.php" method="post">
        <div class="box pad">
            <input type="hidden" name="action" value="takeedit" />
            <input type="hidden" name="convid" value="<?= $pm->id() ?>" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />

            <table class="layout" width="100%">
                <tr>
                    <td class="label"><label for="pin">Pinned</label></td>
                    <td>
                        <input type="checkbox" id="pin" name="pin"<?php if ($pm->isPinned()) { echo ' checked="checked"'; } ?> />
                    </td>
                    <td class="label"><label for="mark_unread">Mark as unread</label></td>
                    <td>
                        <input type="checkbox" id="mark_unread" name="mark_unread" />
                    </td>
                    <td class="label"><label for="delete">Delete conversation</label></td>
                    <td>
                        <input type="checkbox" id="delete" name="delete" />
                    </td>
                </tr>
                <tr>
                    <td class="center" colspan="6"><input type="submit" value="Manage conversation" /></td>
                </tr>
            </table>
        </div>
    </form>
<?php if ($Viewer->isStaffPMReader() && (!$pm->forwardedTo() || $pm->forwardedTo()->id() == $Viewer->id())) { ?>
    <h3>Forward conversation</h3>
    <form class="send_form" name="forward" action="inbox.php" method="post">
        <div class="box pad">
            <input type="hidden" name="action" value="forward" />
            <input type="hidden" name="convid" value="<?= $pm->id() ?>" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <label for="receiverid">Forward to</label>
            <select id="receiverid" name="receiverid">
<?php
    foreach ($staffPMList as $StaffID => $StaffName) {
        if ($StaffID == $Viewer->id() || in_array($StaffID, $recipientList)) {
            continue;
        }
?>
                <option value="<?=$StaffID?>"><?=$StaffName?></option>
<?php } ?>
            </select>
            <input type="submit" value="Forward" />
        </div>
    </form>
<?php } ?>
</div>
<?php
View::show_footer();
