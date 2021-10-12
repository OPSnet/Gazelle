<?php

if (!$Viewer->permitted('admin_manage_stylesheets')) {
    error(403);
}

$header = new Gazelle\Util\SortableTableHeader('id', [
    'id'      => ['dbColumn' => 's.ID',     'defaultSort' => 'asc'],
    'name'    => ['dbColumn' => 's.Name',   'defaultSort' => 'asc',  'text' => 'Name'],
    'enabled' => ['dbColumn' => 'ui_count', 'defaultSort' => 'desc', 'text' => 'Enabled Users'],
    'total'   => ['dbColumn' => 'ud_count', 'defaultSort' => 'desc', 'text' => 'Total Users'],
]);
$OrderBy = $header->getOrderBy();
$OrderDir = $header->getOrderDir();

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
    ORDER BY $OrderBy $OrderDir");

    if ($DB->has_results()) { ?>
        <table width="100%">
            <tr class="colhead">
                <td><?= $header->emit('name') ?></td>
                <td>Description</td>
                <td>Default</td>
                <td><?= $header->emit('enabled') ?></td>
                <td><?= $header->emit('total') ?></td>
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
