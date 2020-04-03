<?php

use Gazelle\Util\SortableTableHeader;

if (!check_perms('admin_manage_stylesheets')) {
    error(403);
}

$SortOrderMap = [
    'id'      => ['s.ID', 'asc'],
    'name'    => ['s.Name', 'asc'],
    'enabled' => ['ui_count', 'desc'],
    'total'   => ['ud_count', 'desc'],
];
$SortOrder = (!empty($_GET['order']) && isset($SortOrderMap[$_GET['order']])) ? $_GET['order'] : 'id';
$OrderBy = $SortOrderMap[$SortOrder][0];
$OrderWay = (empty($_GET['sort']) || $_GET['sort'] == $SortOrderMap[$SortOrder][1])
    ? $SortOrderMap[$SortOrder][1]
    : SortableTableHeader::SORT_DIRS[$SortOrderMap[$SortOrder][1]];

View::show_header('Manage Stylesheets');
?>
<div class="thin">
    <div class="header">
        <div class="linkbox">
            <a href="tools.php" class="brackets">Back to tools</a>
        </div>
    </div>
    <?php
    $DB->prepared_query("
    SELECT
        s.ID,
        s.Name,
        s.Description,
        s.`Default`,
        IFNULL(ui.`Count`, 0) AS ui_count,
        IFNULL(ud.`Count`, 0) AS ud_count
    FROM stylesheets AS s
    LEFT JOIN (
        SELECT StyleID, COUNT(*) AS Count FROM users_info AS ui JOIN users_main AS um ON ui.UserID = um.ID WHERE um.Enabled='1' GROUP BY StyleID
    ) AS ui ON s.ID=ui.StyleID
    LEFT JOIN (
        SELECT StyleID, COUNT(*) AS Count FROM users_info AS ui JOIN users_main AS um ON ui.UserID = um.ID GROUP BY StyleID
    ) AS ud ON s.ID = ud.StyleID
    ORDER BY $OrderBy $OrderWay");

    if ($DB->has_results()) {
        $header = new SortableTableHeader([
            'name'    => 'Name',
            'enabled' => 'Enabled Users',
            'total'   => 'Total Users',
        ], $SortOrder, $OrderWay);
        ?>
        <table width="100%">
            <tr class="colhead">
                <td><?= $header->emit('name', $SortOrderMap['name'][1]) ?></td>
                <td>Description</td>
                <td>Default</td>
                <td><?= $header->emit('enabled', $SortOrderMap['enabled'][1]) ?></td>
                <td><?= $header->emit('total', $SortOrderMap['total'][1]) ?></td>
            </tr>
            <?php
            while (list($ID, $Name, $Description, $Default, $EnabledCount, $TotalCount) = $DB->next_record(MYSQLI_NUM, [1, 2])) { ?>
                <tr>
                    <td><?= $Name ?></td>
                    <td><?= $Description ?></td>
                    <td><?= ($Default == '1') ? 'Default' : '' ?></td>
                    <td><?= number_format($EnabledCount) ?></td>
                    <td><?= number_format($TotalCount) ?></td>
                </tr>
            <?php } ?>
        </table>
        <?php
    } else { ?>
        <h2 align="center">There are no stylesheets.</h2>
        <?php
    } ?>
</div>
<?php
View::show_footer();
