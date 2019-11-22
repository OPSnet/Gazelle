<?php
if (!check_perms('site_database_specifics')) {
    error(403);
}

// View table definition
if (!empty($_GET['table'])) {
    if (preg_match('/([\w-]+)/', $_GET['table'], $match)) {
        $DB->prepared_query('SHOW CREATE TABLE ' . $match[1]);
        list(,$definition) = $DB->next_record(MYSQLI_NUM, false);
        header('Content-type: text/plain');
        die($definition);
    }
}

$orderArg = empty($_GET['order_by']) ? '' : trim($_GET['order_by']);
switch ($orderArg) {
    case 'datafree':
        $orderBy = 'data_free';
        $label = 'free space';
        break;
    case 'datasize':
        $orderBy = 'data_length';
        $label = 'table size';
        break;
    case 'freeratio':
        $orderBy = 'CASE WHEN data_length = 0 THEN = data_free / data_length END';
        $label = 'table bloat';
        break;
    case 'indexsize':
        $orderBy = 'index_length';
        $label = 'index size';
        break;
    case 'name':
        $orderBy = 'table_name';
        $label = 'name';
        break;
    case 'rows':
        $orderBy = 'table_rows';
        $label = 'row counts';
        break;
    case 'rowsize':
        $orderBy = 'avg_row_length';
        $label = 'mean row length';
        break;
    case 'totalsize':
    default:
        $orderArg = 'totalsize';
        $orderBy = 'data_length + index_length';
        $label = 'total table size';
        break;
}

$orderWay = (!empty ($_GET['order_way']) && $_GET['order_way'] == 'asc')
    ? 'ASC' : 'DESC';

$DB->prepared_query("
    SELECT table_name, engine, table_rows, avg_row_length, data_length, index_length, data_free
    FROM information_schema.tables
    WHERE table_schema = ?
    ORDER by $orderBy $orderWay
    ", SQLDB
);
$Tables = $DB->to_array('table_name', MYSQLI_ASSOC);

$data = [];
foreach ($Tables as $name => $info) {
    switch ($orderArg) {
        case 'datafree':
            $data[$name] = $info['data_free'];
            break;
        case 'datasize':
            $data[$name] = $info['data_length'];
            break;
        case 'freeratio':
            $data[$name] = round($info['data_length'] == 0 ? 0 : $info['data_free'] / $info['data_length'], 2);
            break;
        case 'indexsize':
            $data[$name] = $info['index_length'];
            break;
        case 'rows':
            $data[$name] = $info['table_rows'];
            break;
        case 'rowsize':
            $data[$name] = $info['avg_row_length'];
            break;
        case 'totalsize':
        default:
            $data[$name] = $info['data_length'] + $info['index_length'];
            break;
        }
}

View::show_header('Database Specifics');
$urlStem = 'tools.php?action=database_specifics&amp;';
function urlSort ($isThisColumn, $way) {
    return ($isThisColumn && $way == 'DESC') ? 'ASC' : 'DESC';
}
?>

<script src="<?= STATIC_SERVER ?>functions/highcharts.js"></script>
<script src="<?= STATIC_SERVER ?>functions/highcharts_custom.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
Highcharts.chart('statistics', {
    chart: {
        type: 'pie',
        plotBackgroundColor: '#051401',
        backgroundColor: '#000000',
        plotBorderWidth: null,
        plotShadow: true,
    },
    title: {
        text: '<?= SITE_NAME ?> database breakdown by <?= $label ?>',
        style: {
            color: '#c0c0c0',
        },
    },
    credits: { enabled: false },
    tooltip: { pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>' },
    plotOptions: {
        pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
                enabled: true,
                format: '<b>{point.name}</b>: {point.percentage:.1f} %',
                color: 'white',
            }
        }
    },
    series: [{
        name: 'Tables',
        data: [
<?php foreach ($data as $table => $value) { ?>
            { name: '<?= $table ?>', y: <?= $value ?> },
<?php } ?>
        ]
    }]
})});
</script>

<div class="box pad center">
<figure class="highcharts-figure">
    <div id="statistics"></div>
</figure>
</div>
<br />
<table>
    <tr class="colhead">
        <td><a href="<?= $urlStem ?>order_by=name&amp;order_way=<?= urlSort($orderArg == 'name', $orderWay) ?>">Name</a></td>
        <td><a href="<?= $urlStem ?>order_by=rows&amp;order_way=<?= urlSort($orderArg == 'rows', $orderWay) ?>">Rows</td>
        <td><a href="<?= $urlStem ?>order_by=rowsize&amp;order_way=<?= urlSort($orderArg == 'rowsize', $orderWay) ?>">Row Size</a></td>
        <td><a href="<?= $urlStem ?>order_by=datasize&amp;order_way=<?= urlSort($orderArg == 'datasize', $orderWay) ?>">Data Size</a></td>
        <td><a href="<?= $urlStem ?>order_by=indexsize&amp;order_way=<?= urlSort($orderArg == 'indexsize', $orderWay) ?>">Index Size</a></td>
        <td><a href="<?= $urlStem ?>order_by=datafree&amp;order_way=<?= urlSort($orderArg == 'datafree', $orderWay) ?>">Free Size</td>
        <td><a href="<?= $urlStem ?>order_by=dataratio&amp;order_way=<?= urlSort($orderArg == 'dataratio', $orderWay) ?>">Bloat %</td>
        <td><a href="<?= $urlStem ?>order_by=totalsize&amp;order_way=<?= urlSort($orderArg == 'totalsize', $orderWay) ?>">Total Size</td>
    </tr>
<?php
$TotalRows = 0;
$TotalDataSize = 0;
$TotalFreeSize = 0;
$TotalIndexSize = 0;
$Row = 'a';
foreach ($Tables as $t) {
    $Row = $Row === 'a' ? 'b' : 'a';

    // table_name, engine, table_rows, avg_row_length, data_length, index_length, data_free
    $TotalRows += $t['table_rows'];
    $TotalDataSize += $t['data_length'];
    $TotalIndexSize += $t['index_length'];
    $TotalFreeSize += $t['data_free'];
?>
    <tr class="row<?= $Row ?>">
        <td>
            <a href="<?= $urlStem ?>table=<?= display_str($t['table_name']) ?>" title="engine: <?= $t['engine'] ?>">
            <?= $t['engine'] != 'InnoDB' ? '<span style="color: tomato;">' : '' ?>
            <?= display_str($t['table_name']) ?>
            <?= $t['table_name'] == 'email' ? '</span>' : '' ?>
            </a>
        </td>
        <td class="number_column"><?= number_format($t['table_rows']) ?></td>
        <td class="number_column"><?= Format::get_size($t['avg_row_length']) ?></td>
        <td class="number_column"><?= Format::get_size($t['data_length']) ?></td>
        <td class="number_column"><?= Format::get_size($t['index_length']) ?></td>
        <td class="number_column"><?= Format::get_size($t['data_free']) ?></td>
        <td class="number_column"><?= round($t['data_length'] == 0 ? 0 : ($t['data_free'] / $t['data_length']) * 100, 2) ?></td>
        <td class="number_column"><?= Format::get_size($t['data_length'] + $t['index_length']) ?></td>
    </tr>
<?php
}
?>
    <tr>
        <td></td>
        <td class="number_column"><?= number_format($TotalRows) ?></td>
        <td></td>
        <td class="number_column"><?= Format::get_size($TotalDataSize) ?></td>
        <td class="number_column"><?= Format::get_size($TotalIndexSize) ?></td>
        <td class="number_column"><?= Format::get_size($TotalFreeSize) ?></td>
        <td class="number_column"><?= round($TotalDataSize == 0 ? 0 : ($TotalFreeSize / $tTotalDataSize) * 100, 2) ?></td>
        <td class="number_column"><?= Format::get_size($TotalDataSize + $TotalIndexSize) ?></td>
    </tr>
</table>
<?php
View::show_footer();
