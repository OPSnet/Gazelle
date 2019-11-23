<?php
if (!check_perms('admin_manage_permissions') && !check_perms('users_mod')) {
    error(403);
}

if (!check_perms('admin_manage_permissions')) {
    View::show_header('Site Options');
    $DB->query("SELECT Name, Value, Comment FROM site_options");
?>
    <div class="header">
        <h1>Site Options</h1>
    </div>
    <table width="100%">
        <tr class="colhead">
            <td>Name</td>
            <td>Value</td>
            <td>Comment</td>
        </tr>
<?php
    $Row = 'a';
    while (list($Name, $Value, $Comment) = $DB->next_record()) {
    $Row = $Row === 'a' ? 'b' : 'a';
?>
        <tr class="row<?=$Row?>">
            <td><?=$Name?></td>
            <td><?=$Value?></td>
            <td><?=$Comment?></td>
        </tr>
<?php
    }
?>
    </table>
<?php
    View::show_footer();
    die();
}

if (isset($_POST['submit'])) {
    authorize();

    $Name = $_POST['name'];
    $Value = $_POST['value'];
    $Comment = $_POST['comment'];

    if ($_POST['submit'] == 'Delete') {
        $DB->prepared_query('DELETE FROM site_options WHERE Name = ?', $Name);
        $Cache->delete_value('site_option_' . $Name);
    } else {
        $Val->SetFields('name', '1', 'regex', 'The name must be alphanumeric and may contain dashes or underscores. No spaces are allowed.', array('regex' => '/^[a-z][-_a-z0-9]{0,63}$/i'));
        $Val->SetFields('value', '1', 'string', 'You must specify a value for the option.');
        $Val->SetFields('comment', '1', 'string', 'You must specify a comment for the option.');

        $Error = $Val->ValidateForm($_POST);
        if ($Error) {
            error($Error);
        }

        if ($_POST['submit'] == 'Edit') {
            $DB->prepared_query('SELECT Name FROM site_options WHERE ID = ?', $_POST['id']);
            list($OldName) = $DB->next_record();
            $DB->prepared_query('
                UPDATE site_options
                SET
                    Name = ?, Value = ?, Comment = ?
                WHERE ID = ?
                ', $Name, $Value, $Comment, $_POST['id']
            );
            $Cache->delete_value('site_option_' . $OldName);
            $Cache->cache_value('site_option_' . $Name, $Value);
        } else {
            $DB->prepared_query('
                INSERT INTO site_options (Name, Value, Comment)
                VALUES (?, ?, ?)
                ', $Name, $Value, $Comment
            );
            $Cache->cache_value('site_option_' . $Name, $Value);
        }
    }
}

$DB->query('
    SELECT
        ID,
        Name,
        Value,
        Comment
    FROM site_options
    ORDER BY LOWER(Name)
');

View::show_header('Site Options');
?>

<div class="header">
    <h2>Site Options</h2>
</div>
<table width="100%">
    <tr class="colhead">
        <td>
            <span class="tooltip" title="Words must be separated by dashes or underscores">Name</span>
        </td>
        <td>Value</td>
        <td>Comment</td>
        <td>Submit</td>
    </tr>
    <tr class="rowa">
        <form class="create_form" name="site_option" action="" method="post">
            <input type="hidden" name="action" value="site_options" />
            <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
            <td>
                <input type="text" size="40" name="name" />
            </td>
            <td>
                <input type="text" size="20" name="value" />
            </td>
            <td>
                <input type="text" size="75" name="comment" />
            </td>
            <td>
                <input type="submit" name="submit" value="Create" />
            </td>
        </form>
    </tr>
<?php
$Row = 'a';
while (list($ID, $Name, $Value, $Comment) = $DB->next_record()) {
    $Row = $Row === 'a' ? 'b' : 'a';
?>
<tr class="row<?=$Row?>">
    <form class="manage_form" name="site_option" action="" method="post">
        <input type="hidden" name="id" value="<?=$ID?>" />
        <input type="hidden" name="action" value="site_options" />
        <input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
        <td>
            <input type="text" size="40" name="name" value="<?=$Name?>" />
        </td>
        <td>
            <input type="text" size="20" name="value" value="<?=$Value?>" />
        </td>
        <td>
            <input type="text" size="75" name="comment" value="<?=$Comment?>" />
        </td>
        <td>
            <input type="submit" name="submit" value="Edit" />
            <input type="submit" name="submit" value="Delete" />
        </td>
    </form>
</tr>
<?php
}
?>
</table>
<?php
View::show_footer();
