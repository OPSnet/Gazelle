<?php

$ID = (int)$_GET['id'];
if (empty($_GET['type']) || !$ID) {
    error(404);
}

require_once('array.php');
if (!array_key_exists($_GET['type'], $Types)) {
    error(403);
}
$Short = $_GET['type'];
$Type = $Types[$Short];

switch ($Short) {
    case 'user':
        $Username = $DB->scalar("
            SELECT Username FROM users_main WHERE ID = ?
            ", $ID
        );
        if (!$Username) {
            error(404);
        }
        break;

    case 'request_update':
        $NoReason = true;
        [$Name, $Desc, $Filled, $CategoryID, $Year] = $DB->row("
            SELECT Title, Description, TorrentID, CategoryID, Year
            FROM requests
            WHERE ID = ?
            ", $ID
        );
        if (!$Name) {
            error(404);
        }
        if ($Filled || ($CategoryID != 0 && ($Categories[$CategoryID - 1] != 'Music' || $Year != 0))) {
            error(403);
        }
        break;

    case 'request':
        [$Name, $Desc, $Filled] = $DB->row("
            SELECT Title, Description, TorrentID
            FROM requests
            WHERE ID = ?
            ", $ID
        );
        if (!$Name) {
            error(404);
        }
        break;

    case 'collage':
        [$Name, $Desc] = $DB->row("
            SELECT Name, Description
            FROM collages
            WHERE ID = ?
            ", $ID
        );
        if (!$Name) {
            error(404);
        }
        break;

    case 'thread':
        [$Title, $ForumID, $Username] = $DB->row("
            SELECT ft.Title, ft.ForumID, um.Username
            FROM forums_topics AS ft
            INNER JOIN users_main AS um ON (um.ID = ft.AuthorID)
            WHERE ft.ID = ?
            ", $ID
        );
        if (!$Title) {
            error(404);
        }
        $MinClassRead = $DB->scalar("
            SELECT MinClassRead FROM forums WHERE ID = ?
            ", $ForumID
        );
        if ($Viewer->disableForums() || !$Viewer->forumAccess($ForumID, $MinClassRead)) {
            error(403);
        }
        break;

    case 'post':
        [$Body, $TopicID, $Username] = $DB->row("
            SELECT fp.Body, fp.TopicID, um.Username
            FROM forums_posts AS fp
            INNER JOIN users_main AS um ON (um.ID = fp.AuthorID)
            WHERE fp.ID = ?
            ", $ID
        );
        if (!$Body) {
            error(404);
        }
        [$ForumID, $MinClassRead] = $DB->row("
            SELECT ft.ForumID, f.MinClassRead
            FROM forums_topics ft
            INNER JOIN forums f ON (f.ID = ft.ForumID)
            WHERE ft.ID = ?
            ", $TopicID
        );
        if ($Viewer->disableForums() || !$Viewer->forumAccess($ForumID, $MinClassRead)) {
            error(403);
        }
        break;

    case 'comment':
        [$Body, $Username] = $DB->row("
            SELECT c.Body, um.Username
            FROM comments AS c
            INNER JOIN users_main AS um ON (um.ID = c.AuthorID)
            WHERE c.ID = ?
            ", $ID
        );
        if (!$Body) {
            error(404);
        }
        break;
}

View::show_header('Report a '.$Type['title'], 'bbcode,jquery.validate,form_validate');
?>
<div class="thin">
    <div class="header">
        <h2>Report <?=$Type['title']?></h2>
    </div>
    <h3>Reporting guidelines</h3>
    <div class="box pad">
        <p>Following these guidelines will help the moderators deal with your report in a timely fashion. </p>
        <ul>
<?php foreach ($Type['guidelines'] as $Guideline) { ?>
            <li><?=$Guideline?></li>
<?php } ?>
        </ul>
        <p>In short, please include as much detail as possible when reporting. Thank you. </p>
    </div>
<?php

switch ($Short) {
    case 'user':
?>
    <p>You are reporting the user <strong><?=display_str($Username)?></strong></p>
<?php
        break;
    case 'request_update':
?>
    <p>You are reporting the request:</p>
    <table>
        <tr class="colhead">
            <td>Title</td>
            <td>Description</td>
            <td>Filled?</td>
        </tr>
        <tr>
            <td><?=display_str($Name)?></td>
            <td><?=Text::full_format($Desc)?></td>
            <td><strong><?=($Filled == 0 ? 'No' : 'Yes')?></strong></td>
        </tr>
    </table>
    <br />

    <div class="box pad center">
        <p><strong>It will greatly increase the turnover rate of the updates if you can fill in as much of the following details as possible.</strong></p>
        <form class="create_form" id="report_form" name="report" action="" method="post">
            <input type="hidden" name="action" value="takereport" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="type" value="<?=$Short?>" />
            <table class="layout">
                <tr>
                    <td class="label">Year (required)</td>
                    <td>
                        <input type="text" size="4" name="year" class="required" />
                    </td>
                </tr>
                <tr>
                    <td class="label">Release type</td>
                    <td>
                        <select id="releasetype" name="releasetype">
                            <option value="0">---</option>
<?php
        $releaseTypes = (new Gazelle\ReleaseType)->list();
        foreach ($releaseTypes as $Key => $Val) {
?>
                            <option value="<?=$Key?>"<?=(!empty($ReleaseType) ? ($Key == $ReleaseType ? ' selected="selected"' : '') : '')?>><?=$Val?></option>
<?php   } ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td class="label">Comment</td>
                    <td>
                        <textarea rows="8" cols="80" name="comment" class="required"></textarea>
                    </td>
                </tr>
            </table>
            <br />
            <br />
            <input type="submit" value="Submit report" />
        </form>
    </div>
<?php
        break;
    case 'request':
?>
    <p>You are reporting the request:</p>
    <table>
        <tr class="colhead">
            <td>Title</td>
            <td>Description</td>
            <td>Filled?</td>
        </tr>
        <tr>
            <td><?=display_str($Name)?></td>
            <td><?=Text::full_format($Desc)?></td>
            <td><strong><?=($Filled == 0 ? 'No' : 'Yes')?></strong></td>
        </tr>
    </table>
<?php
        break;
    case 'collage':
?>
    <p>You are reporting the collage:</p>
    <table>
        <tr class="colhead">
            <td>Title</td>
            <td>Description</td>
        </tr>
        <tr>
            <td><?=display_str($Name)?></td>
            <td><?=Text::full_format($Desc)?></td>
        </tr>
    </table>
<?php
        break;
    case 'thread':
?>
    <p>You are reporting the thread:</p>
    <table>
        <tr class="colhead">
            <td>Username</td>
            <td>Title</td>
        </tr>
        <tr>
            <td><?=display_str($Username)?></td>
            <td><?=display_str($Title)?></td>
        </tr>
    </table>
<?php
        break;
    case 'post':
?>
    <p>You are reporting the post:</p>
    <table>
        <tr class="colhead">
            <td>Username</td>
            <td>Body</td>
        </tr>
        <tr>
            <td><?=display_str($Username)?></td>
            <td><?=Text::full_format($Body)?></td>
        </tr>
    </table>
<?php
        break;
    case 'comment':
?>
    <p>You are reporting the <?=$Types[$Short]['title']?>:</p>
    <table>
        <tr class="colhead">
            <td>Username</td>
            <td>Body</td>
        </tr>
        <tr>
            <td><?=display_str($Username)?></td>
            <td><?=Text::full_format($Body)?></td>
        </tr>
    </table>
<?php
    break;
}
if (empty($NoReason)) {
?>
    <h3>Reason</h3>
    <div class="box pad center">
        <form class="create_form" name="report" id="report_form" action="" method="post">
            <input type="hidden" name="action" value="takereport" />
            <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
            <input type="hidden" name="id" value="<?=$ID?>" />
            <input type="hidden" name="type" value="<?=$Short?>" />
            <textarea class="required" rows="10" cols="95" name="reason"></textarea><br /><br />
            <input type="submit" value="Submit report" />
        </form>
    </div>
<?php } ?>
</div>
<?php
View::show_footer();
