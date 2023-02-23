<?php

if (!$Viewer->permitted('users_mod')) {
    error(403);
}

$db = Gazelle\DB::DB();
if (isset($_POST['newalias'])) {
    $db->prepared_query("
        INSERT INTO label_aliases
               (BadLabel, AliasLabel)
        VALUES (?,        ?)
        ", trim($_POST['BadLabel']), trim($_POST['AliasLabel'])
    );
} elseif (isset($_POST['changealias']) && isset($_POST['aliasid']) && (int)$_POST['aliasid'] > 0) {
    $aliasId = (int)$_POST['aliasid'];
    if (isset($_POST['save'])) {
        $db->prepared_query("
            UPDATE label_aliases SET
                BadLabel = ?,
                AliasLabel = ?
            WHERE ID = ?
            ", $badLabel, $aliasLabel, $aliasId
        );
    } elseif (isset($_POST['delete'])) {
        $db->prepared_query("
            DELETE FROM label_aliases
            WHERE ID = ?
            ", $aliasId
        );
    }
}

$orderBy = ($_GET['order'] ?? 'AliasLabel') === 'good' ? 3 : 2;
$db->prepared_query("
    SELECT ID, BadLabel, AliasLabel
    FROM label_aliases
    ORDER BY $orderBy
");

View::show_header('Label Aliases');
?>
<div class="header">
    <h2>Label Aliases</h2>
    <div class="linkbox">
        <a href="tools.php?action=label_aliases&amp;order=good" class="brackets">Sort by good labels</a>
        <a href="tools.php?action=label_aliases&amp;order=bad" class="brackets">Sort by bad labels</a>
    </div>
</div>
<table width="100%">
    <tr class="colhead">
        <td>Label</td>
        <td>Renamed from</td>
        <td>Submit</td>
    </tr>
    <tr />
    <tr>
        <form method="post" action="">
            <input type="hidden" name="newalias" value="1" />
            <td>
                <input type="text" name="AliasLabel" />
            </td>
            <td>
                <input type="text" name="BadLabel" />
            </td>
            <td>
                <input type="submit" value="Add alias" />
            </td>
        </form>
    </tr>
<?php while ([$id, $badLabel, $aliasLabel] = $db->next_record()) { ?>
    <tr>
        <form method="post" action="">
            <input type="hidden" name="changealias" value="1" />
            <input type="hidden" name="aliasid" value="<?=$id?>" />
            <td>
                <input type="text" name="AliasLabel" value="<?=$aliasLabel?>" />
            </td>
            <td>
                <input type="text" name="BadLabel" value="<?=$badLabel?>" />
            </td>
            <td>
                <input type="submit" name="save" value="Save alias" />
                <input type="submit" name="delete" value="Delete alias" />
            </td>
        </form>
    </tr>
<?php } ?>
</table>
<?php
View::show_footer();
