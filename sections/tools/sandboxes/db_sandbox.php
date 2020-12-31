<?php

if (!check_perms('admin_site_debug')) {
    error(403);
}

if (empty($_POST['query'])) {
    $query = null;
    $textAreaRows = 8;
} else {
    $query = trim($_POST['query']);
    if (preg_match('@^(?:explain\s+)?select\b(?:[\s\w().,`\'"/*+-])+\bfrom@i', $query) !== 1) {
        error('Invalid query');
    }
    $textAreaRows = max(8, substr_count($query, "\n") + 2);
}

function print_row($Row, $Class) {
    echo "<tr class='{$Class}'>".implode("\n", array_map(function ($Value) { return "<td>".(($Value === null) ? "NULL" : $Value)."</td>"; }, $Row))."</tr>";
}

$Title = 'DB Sandbox';
View::show_header($Title);

?>
<div class="header">
    <h2><?=$Title?></h2>
</div>
<div class="thin pad box">
    <form action="tools.php?action=db_sandbox" method='POST'>
        <textarea style="width: 98%;" name="query" cols="90" rows="<?= $textAreaRows ?>"><?= $query ?></textarea><br /><br />
        <input type="submit" value="Query" />
    </form>
</div>
<?php

if (!empty($query)) {
    try {
        $success = true;
        $DB->prepared_query($query);
    }
    catch (DB_MYSQL_Exception $e) {
        $success = false;
?>
    <div class="thin box pad">
        <h3 style="display:inline">Query error</h3>
        <div>Mysql error: <?= display_str($e->getMessage()) ?></div>
    </div>
<?php
    }
    if ($success) {
?>
<div class="thin" style="overflow-x: scroll">
    <div>
        <h3 style="display:inline">Query Results</h3>
    </div>
    <table>
<?php

$Record = $DB->fetch_record();
$Headers = [];
$Row = [];
foreach ($Record as $Key => $Value) {
    if (!is_int($Key)) {
        $Headers[] = $Key;
        $Row[] = $Value;
    }
}

print_row($Headers, 'colhead');
print_row($Row, 'rowb');
$Cnt = 0;
while ($Record = $DB->fetch_record()) {
    $Row = [];
    foreach ($Record as $Key => $Value) {
        if (!is_int($Key)) {
            $Row[] = $Value;
        }
    }
    print_row($Row, ($Cnt++ % 2) ? 'rowa' : 'rowb');
}
?>
    </table>
</div>

<?php
    }
}
View::show_footer();
