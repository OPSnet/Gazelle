<?php
/**********************************************************************
 *>>>>>>>>>>>>>>>>>>>>>>>>>>> User search <<<<<<<<<<<<<<<<<<<<<<<<<<<<*
 **********************************************************************/

if (empty($_GET['search'])) {
    json_die("failure", "no search terms");
} else {
    $_GET['username'] = $_GET['search'];
}

define('USERS_PER_PAGE', 30);

if (isset($_GET['username'])) {
    $_GET['username'] = trim($_GET['username']);

    list($Page, $Limit) = Format::page_limit(USERS_PER_PAGE);
    $DB->prepared_query("
        SELECT
            SQL_CALC_FOUND_ROWS
            um.ID,
            um.Username,
            um.Enabled,
            um.PermissionID,
            (donor.UserID IS NOT NULL) AS Donor,
            ui.Warned,
            ui.Avatar
        FROM users_main AS um
        INNER JOIN users_info AS ui ON (ui.UserID = um.ID)
        LEFT JOIN users_levels AS donor ON (donor.UserID = um.ID
            AND donor.PermissionID = (SELECT ID FROM permissions WHERE Name = 'Donor' LIMIT 1)
        )
        WHERE Username LIKE concat('%', ?, '%')
        ORDER BY Username
        LIMIT ?
        ", trim($_GET['username']), $Limit
    );
    $Results = $DB->to_array(MYSQLI_NUM);
    $DB->query('SELECT FOUND_ROWS();');
    list($NumResults) = $DB->next_record();
}

$JsonUsers = [];
foreach ($Results as $Result) {
    list($UserID, $Username, $Enabled, $PermissionID, $Donor, $Warned, $Avatar) = $Result;
    $JsonUsers[] = [
        'userId' => (int)$UserID,
        'username' => $Username,
        'donor' => $Donor == 1,
        'warned' => !is_null($Warned),
        'enabled' => ($Enabled == 2 ? false : true),
        'class' => Users::make_class_string($PermissionID),
        'avatar' => $Avatar
    ];
}

json_print("success", [
    'currentPage' => (int)$Page,
    'pages' => ceil($NumResults / USERS_PER_PAGE),
    'results' => $JsonUsers
]);
