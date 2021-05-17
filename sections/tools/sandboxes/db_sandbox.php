<?php

if (!check_perms('admin_site_debug')) {
    error(403);
}

if (empty($_POST['query'])) {
    $query = null;
    $textAreaRows = 8;
} else {
    $query = trim($_POST['query']);
    if (preg_match('@^(?:explain\s+)?select\b(?:[\s\w()/.,`\'"=*+-])+\bfrom@i', $query) !== 1) {
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
        $n = $DB->record_count();
?>
<div class="thin" style="overflow-x: scroll">
    <div>
        <h3 style="display:inline">Query Results (<?= number_format($n) ?> row<?= plural($n) ?>)</h3>
    </div>
    <table>
<?php

$Cnt = 0;
while ($Record = $DB->next_record(MYSQLI_ASSOC)) {
    $Row = [];
    if ($Cnt === 0) {
        print_row(array_keys($Record), 'colhead');
    }
    print_row(array_values($Record), ($Cnt++ % 2) ? 'rowa' : 'rowb');
}
?>
    </table>
</div>

<?php
    }
}
View::show_footer();
