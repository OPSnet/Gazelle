<?php

if (!check_perms('site_debug')) {
    error(403);
}

if (!empty($_POST['query'])) {
    $_POST['query'] = trim($_POST['query']);
    if (strtolower(substr($_POST['query'], 0, 7)) !== 'select ' ||
        preg_match('/^select([^--]*)from/i', $_POST['query']) !== 1) {
        error(0);
    }
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
        <textarea style="width: 98%;" name="query" cols="90" rows="8"><?=$_POST['query']?></textarea><br />
        <input type="submit" value="Query" />
    </form>
</div>
<?php

if (!empty($_POST['query'])) {
    G::$DB->prepared_query($_POST['query']);
    $Record = G::$DB->fetch_record();
    $Headers = [];
    $Row = [];
    foreach ($Record as $Key => $Value) {
        if (!is_int($Key)) {
            $Headers[] = $Key;
            $Row[] = $Value;
        }
    }

?>
<div class="thin" style="overflow-x: scroll">
    <div>
        <h3 style="display:inline">Query Results</h3>
    </div>
    <table>
<?php
print_row($Headers, 'colhead');
print_row($Row, 'rowb');
$Cnt = 0;
while ($Record = G::$DB->fetch_record()) {
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
View::show_footer();
