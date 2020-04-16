<?php

use Gazelle\Util\SortableTableHeader;

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

$SortOrderMap = [
    'datafree' => ['data_free', 'desc', 'free space'],
    'datasize' => ['data_length', 'desc', 'table size'],
    'freeratio' => ['CASE WHEN data_length = 0 THEN 0 ELSE data_free / data_length END', 'desc', 'table bloat'],
    'indexsize' => ['index_length', 'desc', 'index size'],
    'name' => ['table_name', 'desc', 'name'],
    'rows' => ['table_rows', 'desc', 'row counts'],
    'rowsize' => ['avg_row_length', 'desc', 'mean row length'],
    'totalsize' => ['data_length + index_length', 'desc', 'total table size'],
];
$SortOrder = (!empty($_GET['order']) && isset($SortOrderMap[$_GET['order']])) ? $_GET['order'] : 'totalsize';
$orderBy = $SortOrderMap[$SortOrder][0];
$orderWay = (empty($_GET['sort']) || $_GET['sort'] == $SortOrderMap[$SortOrder][1])
    ? $SortOrderMap[$SortOrder][1]
    : SortableTableHeader::SORT_DIRS[$SortOrderMap[$SortOrder][1]];

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
    switch ($SortOrder) {
        case 'freeratio':
            $data[$name] = round($info['data_length'] == 0 ? 0 : $info['data_free'] / $info['data_length'], 2);
            break;
        case 'totalsize':
            $data[$name] = $info['data_length'] + $info['index_length'];
            break;
        default:
            $data[$name] = $info[$SortOrderMap[$SortOrder][0]];
    }
}

View::show_header('Database Specifics');
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
        text: '<?= SITE_NAME ?> database breakdown by <?= $SortOrderMap[$SortOrder][2] ?>',
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
<?php
$header = new SortableTableHeader([
    'datafree' => 'Free Size',
    'datasize' => 'Data Size',
    'freeratio' => 'Bloat %',
    'indexsize' => 'Index Size',
    'name' => 'Name',
    'rows' => 'Rows',
    'rowsize' => 'Row Size',
    'totalsize' => 'Total Size',
], $SortOrder, $orderWay);
?>
<table>
    <tr class="colhead" style="text-align:right">
        <td style="text-align:left" class="nobr"><?= $header->emit('name', $SortOrderMap['name'][1]) ?></td>
        <td class="nobr"><?= $header->emit('rows', $SortOrderMap['rows'][1]) ?></td>
        <td class="nobr"><?= $header->emit('rowsize', $SortOrderMap['rowsize'][1]) ?></td>
        <td class="nobr"><?= $header->emit('datasize', $SortOrderMap['datasize'][1]) ?></td>
        <td class="nobr"><?= $header->emit('indexsize', $SortOrderMap['indexsize'][1]) ?></td>
        <td class="nobr"><?= $header->emit('datafree', $SortOrderMap['datafree'][1]) ?></td>
        <td class="nobr"><?= $header->emit('freeratio', $SortOrderMap['freeratio'][1]) ?></td>
        <td class="nobr"><?= $header->emit('totalsize', $SortOrderMap['totalsize'][1]) ?></td>
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
            <a href="?<?= Format::get_url(['order', 'sort'], true, false, ['table' => display_str($t['table_name'])]) ?>" title="engine: <?= $t['engine'] ?>">
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
        <td class="number_column"><?= sprintf('%0.2f', $TotalDataSize == 0 ? 0 : ($TotalFreeSize / $TotalDataSize) * 100) ?></td>
        <td class="number_column"><?= Format::get_size($TotalDataSize + $TotalIndexSize) ?></td>
    </tr>
</table>
<?php
View::show_footer();
