<?php

use Gazelle\Inbox;

$UserID = $Viewer->id();

try {
    $Inbox = new Inbox(
        $Viewer->id(),
        $Viewer->option('ListUnreadPMsFirst') ?? false
    );
} catch (Throwable $e) {
    error(404);
}
$paginator = new Gazelle\Util\Paginator(MESSAGES_PER_PAGE, (int)($_GET['page'] ?? 1));

[$NumResults, $Messages] = $Inbox->result($paginator->limit(), $paginator->offset());
$paginator->setTotal($NumResults);

View::show_header('Inbox');
?>
<div class="thin">
    <h2><?= $Inbox->title() ?></h2>
    <div class="linkbox">
<?php
foreach (array_keys(Inbox::SECTIONS) as $Section) {
    if ($Inbox->section() != $Section) {
?>
        <a href="<?= $Inbox->getLink($Section) ?>" class="brackets">
            <?= $Inbox->title($Section) ?>
        </a>
<?php
    }
}
?>
    </div>
    <?= $paginator->linkbox() ?>
    <div class="box pad">
<?php if ($NumResults == 0 && empty($_GET['search'])) { ?>
    <h2>Your <?= $Inbox->section() ?> is empty.</h2>
<?php } else { ?>
        <form class="search_form" name="<?= $Inbox->section() ?>" action="inbox.php" method="get" id="searchbox">
            <div>
                <input type="hidden" name="section" value="<?= $Inbox->section() ?>" />
                <label><input type="radio" name="searchtype" value="user"<?= (empty($_GET['searchtype']) || $_GET['searchtype'] === 'user') ? ' checked="checked"' : '' ?> /> User</label>
                <label><input type="radio" name="searchtype" value="subject"<?= Format::selected('searchtype', 'subject', 'checked') ?> /> Subject</label>
                <label><input type="radio" name="searchtype" value="message"<?= Format::selected('searchtype', 'message', 'checked') ?> /> Message</label>
                <input type="search" name="search" placeholder="<?=(!empty($_GET['search']) ? display_str($_GET['search']) : 'Search ' . $Inbox->section())?>" style="width: 98%;" />
            </div>
        </form><br />
        <form class="manage_form" name="messages" action="inbox.php" method="post" id="messageform">
            <input type="hidden" name="action" value="masschange" />
            <input type="hidden" name="section" value="<?= $Inbox->section() ?>" />
            <input type="hidden" name="sort" value="<?= (string) (int) $Inbox->getSort() ?>" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="submit" name="read" value="Mark as read" />&nbsp;
            <input type="submit" name="unread" value="Mark as unread" />&nbsp;
            <input type="submit" name="sticky" value="Toggle sticky" title="Unsticky message are stickied, sticky messages are unstickied" />&nbsp;
            <input type="submit" name="delete" value="Delete message(s)" />
            <span style="float: right;">
<?php            // provide a temporary toggle for sorting PMs
        $ToggleTitle = 'Temporary toggle switch for sorting PMs. To permanently change the sorting behavior, edit the setting in your profile.';
        $SortURL = $Inbox->getLink($Inbox->section(), Inbox::HTML, Inbox::ALT_SORT);
        $LinkText = (strpos($SortURL, 'unread') !== false) ? 'unread' : 'latest';
?>
                <a href="<?= $SortURL ?>" class="brackets tooltip" title="<?= $ToggleTitle ?>">List <?= $LinkText ?> first</a>
            </span>

            <table class="message_table checkboxes">
                <tr class="colhead">
                    <td width="10"><input type="checkbox" onclick="toggleChecks('messageform', this);" /></td>
                    <td width="50%">Subject</td>
                    <td><?= ($Inbox->section() === 'sentbox') ? 'Receiver' : 'Sender' ?></td>
                    <td>Date</td>
<?php        if ($Viewer->permitted('users_mod')) { ?>
                    <td>Forwarded to</td>
<?php        } ?>
                </tr>
<?php if ($NumResults == 0) { ?>
                <tr class="a">
                    <td colspan="5">No results.</td>
                </tr>
<?php } else {
        $Row = 'a';
        foreach ($Messages as [$ConvID, $Subject, $Unread, $Sticky, $ForwardedID, $SenderID, $Date]) {
            if ($Unread === '1') {
                $RowClass = 'unreadpm';
            } else {
                $Row = $Row === 'a' ? 'b' : 'a';
                $RowClass = "row$Row";
            }
?>
                <tr class="<?= $RowClass ?>">
                    <td class="center"><input type="checkbox" name="messages[]=" value="<?= $ConvID ?>" /></td>
                    <td>
<?php
            if ($Unread) {
                echo '<strong>';
            }
            if ($Sticky) {
                echo 'Sticky: ';
            }
            $Section = ($Inbox->section() != 'inbox') ? '&amp;section=' . $Inbox->section() : '';
            $Sort = ($Inbox->getSort() == Inbox::UNREAD_FIRST) ? '&amp;sort=unread' : '';
?>
                        <a href="inbox.php?action=viewconv&amp;id=<?= $ConvID . $Section . $Sort ?>"><?= $Subject ?></a>
<?php
            if ($Unread) {
                echo "</strong>";
            }
?>
                    </td>
                    <td><?= Users::format_username((int)$SenderID, true, true, true, true) ?></td>
                    <td><?= time_diff($Date) ?></td>
<?php            if ($Viewer->permitted('users_mod')) { ?>
                    <td><?= (($ForwardedID && $ForwardedID != $Viewer->id()) ? Users::format_username($ForwardedID, false, false, false) : '') ?></td>
<?php            } ?>
                </tr>
<?php
        }
    }
?>
            </table>
            <input type="submit" name="read" value="Mark as read" />&nbsp;
            <input type="submit" name="unread" value="Mark as unread" />&nbsp;
            <input type="submit" name="sticky" value="Toggle sticky" title="Unsticky message are stickied, sticky messages are unstickied" />&nbsp;
            <input type="submit" name="delete" value="Delete message(s)" />
        </form>
<?php } ?>
    </div>
    <?= $paginator->linkbox() ?>
</div>
<?php
View::show_footer();
