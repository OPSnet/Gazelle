<?php

if (!check_perms('site_database_specifics')) {
    error(403);
}

// View table definition
if (!empty($_GET['table']) && preg_match('/([\w-]+)/', $_GET['table'], $match)) {
    $tableName = $match[1];

    View::show_header('Database Specifics - ' . $tableName);
    $siteInfo = new Gazelle\SiteInfo;
    echo $Twig->render('admin/db-table.twig', [
        'definition' => $DB->row('SHOW CREATE TABLE ' . $tableName)[1],
        'table_read' => $siteInfo->tableRowsRead($tableName),
        'index_read' => $siteInfo->indexRowsRead($tableName),
    ]);
    View::show_footer();
    exit;
}

$header = new \Gazelle\Util\SortableTableHeader('totalsize', [
    'datafree'  => ['dbColumn' => 'data_free',      'defaultSort' => 'desc', 'text' => 'Free Size',  'alt' => 'free space'],
    'datasize'  => ['dbColumn' => 'data_length',    'defaultSort' => 'desc', 'text' => 'Data Size',  'alt' => 'table size'],
    'freeratio' => ['dbColumn' => 'CASE WHEN data_length = 0 THEN 0 ELSE data_free / data_length END', 'defaultSort' => 'desc', 'text' => 'Bloat %', 'alt' => 'table bloat'],
    'indexsize' => ['dbColumn' => 'index_length',   'defaultSort' => 'desc', 'text' => 'Index Size', 'alt' => 'index size'],
    'name'      => ['dbColumn' => 'table_name',     'defaultSort' => 'asc',  'text' => 'Name',       'alt' => 'name'],
    'rows'      => ['dbColumn' => 'table_rows',     'defaultSort' => 'desc', 'text' => 'Rows',       'alt' => 'row counts'],
    'rowsize'   => ['dbColumn' => 'avg_row_length', 'defaultSort' => 'desc', 'text' => 'Row Size',   'alt' => 'mean row length'],
    'totalsize' => ['dbColumn' => 'total_length',   'defaultSort' => 'desc', 'text' => 'Total Size', 'alt' => 'total table size'],
]);
$orderBy = $header->getOrderBy();
$orderDir = $header->getOrderDir();

$mode = $_GET['mode'] ?? 'show';
switch($mode) {
    case 'show':
        $tableColumn = 'table_name';
        $where = '';
        break;
    case 'merge':
        $tableColumn = "replace(table_name, 'deleted_', '')";
        $where = '';
        break;
    case 'exclude':
        $tableColumn = 'table_name';
        $where = "AND table_name NOT LIKE 'deleted%'";
        break;
}

$DB->prepared_query("
    SELECT $tableColumn AS table_name,
        engine,
        sum(table_rows) AS table_rows,
        avg(avg_row_length) AS avg_row_length,
        sum(data_length) AS data_length,
        sum(index_length) AS index_length,
        sum(index_length + data_length) AS total_length,
        sum(data_free) AS data_free
    FROM information_schema.tables
    WHERE table_schema = ? $where
    GROUP BY $tableColumn, engine
    ORDER by $orderBy $orderDir
    ", SQLDB
);
$Tables = $DB->to_array('table_name', MYSQLI_ASSOC);

$data = [];
foreach ($Tables as $name => $info) {
    if ($header->getSortKey() === 'freeratio') {
        $data[$name] = round($info['data_length'] == 0 ? 0 : $info['data_free'] / $info['data_length'], 2);
    } else {
        $data[$name] = $info[$orderBy];
    }
}

View::show_header('Database Specifics');
?>
<div class="linkbox">
    <a href="tools.php?action=database_specifics&amp;mode=show" title="Tables of deleted data are shown separately" class="brackets">Show deleted data</a>
    <a href="tools.php?action=database_specifics&amp;mode=merge" title="Stats of tables of deleted data are merged with their source table" class="brackets">Merge deleted data</a>
    <a href="tools.php?action=database_specifics&amp;mode=exclude" title="Tables of deleted data are excluded" class="brackets">Exclude deleted data</a>
</div>
<script src="<?= STATIC_SERVER ?>/functions/highcharts.js"></script>
<script src="<?= STATIC_SERVER ?>/functions/highcharts_custom.js"></script>
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
        text: '<?= SITE_NAME ?> database breakdown by <?= $header->current()['alt'] ?>',
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
    <tr class="colhead" style="text-align:right">
        <td style="text-align:left" class="nobr"><?= $header->emit('name') ?></td>
        <td style="text-align:right" class="nobr"><?= $header->emit('rows') ?></td>
        <td style="text-align:right" class="nobr"><?= $header->emit('rowsize') ?></td>
        <td style="text-align:right" class="nobr"><?= $header->emit('datasize') ?></td>
        <td style="text-align:right" class="nobr"><?= $header->emit('indexsize') ?></td>
        <td style="text-align:right" class="nobr"><?= $header->emit('datafree') ?></td>
        <td style="text-align:right" class="nobr"><?= $header->emit('freeratio') ?></td>
        <td style="text-align:right" class="nobr"><?= $header->emit('totalsize') ?></td>
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
        <td><b>Grand totals</b></td>
        <td class="number_column"><b><?= number_format($TotalRows) ?></b></td>
        <td></td>
        <td class="number_column"><b><?= Format::get_size($TotalDataSize) ?></b></td>
        <td class="number_column"><b><?= Format::get_size($TotalIndexSize) ?></b></td>
        <td class="number_column"><b><?= Format::get_size($TotalFreeSize) ?></b></td>
        <td class="number_column"><b><?= sprintf('%0.2f', $TotalDataSize == 0 ? 0 : ($TotalFreeSize / $TotalDataSize) * 100) ?></b></td>
        <td class="number_column"><b><?= Format::get_size($TotalDataSize + $TotalIndexSize) ?></b></td>
    </tr>
</table>
<?php
View::show_footer();
