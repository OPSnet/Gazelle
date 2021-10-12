<?php

if (!$Viewer->permitted('admin_manage_forums')) {
    error(403);
}

View::show_header('IRC Channel Management');
$DB->prepared_query('SELECT ID, `Name`, Sort, MinLevel, Classes FROM irc_channels ORDER BY Sort');

?>
<div class="header">
    <script type="text/javacript">document.getElementByID('content').style.overflow = 'visible';</script>
    <h2>IRC Channel Manager</h2>
</div>
<div class="thin box pad">
    <p>
        You can manage IRC channels here. For a channel name, you must follow the
        <a href="https://tools.ietf.org/html/rfc2812#section-1.3" target="_blank">IRC RFC Section 1.3</a> on Channels,
        which minimally states that a channel must start with a '&', '#', '+' or '!' character and cannot contain any
        spaces, commas, or control G, and be a maximum of 50 characters. Channel
        names are additionally case insensitive.
    </p>
</div>
<table width="100%">
    <tr class="colhead">
        <td>Sort</td>
        <td>Name</td>
        <td>MinLevel</td>
        <td>Classes</td>
        <td>Options</td>
    </tr>
    <?php
    $Row = 'b';
    while (list($ID, $Name, $Sort, $MinLevel, $Classes) = $DB->fetch_record()) {
        $Row = $Row === 'a' ? 'b' : 'a';
        ?>
    <form class="manage_form" name="forums" action="" method="post">
        <input type="hidden" name="id" value="<?=$ID?>" />
        <input type="hidden" name="action" value="irc_alter" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <tr class="row<?=$Row?>">
            <td>
                <input type="text" size="3" name="sort" value="<?=$Sort?>" />
            </td>
            <td>
                <input type="text" size="50" name="name" value="<?=$Name?>" />
            </td>
            <td>
                <input type="text" size="6" name="min_level" value="<?=$MinLevel?>"/>
            </td>
            <td>
                <input type="text" size="15" name="classes" value="<?=$Classes?>"/>
            </td>
            <td>
                <input type="submit" name="submit" value="Edit" />
                <input type="submit" name="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this channel? This is an irreversible action!')"/>

            </td>
        </tr>
    </form>
        <?php
    }
    ?>
    <tr class="colhead">
        <td colspan="5">Create Channel</td>
    </tr>
    <form class="create_form" name="forum" action="" method="post">
        <input type="hidden" name="action" value="irc_alter" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <tr class="rowa">
            <td>
                <input type="text" size="3" name="sort" />
            </td>
            <td>
                <input type="text" size="50" name="name" />
            </td>
            <td>
                <input type="text" size="6" name="min_level" />
            </td>
            <td>
                <input type="text" size="15" name="classes" />
            </td>
            <td>
                <input type="submit" value="Create" />
            </td>
        </tr>
    </form>
</table>
<?php View::show_footer(); ?>
