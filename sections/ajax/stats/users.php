<?php

/*
Disabled until we fix Geographical Data
if (!list($Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements) = $Cache->get_value('geodistribution')) {
    require(__DIR__ . '/../../../classes/charts.class.php');
    $DB->prepared_query('
        SELECT Code, Users
        FROM users_geodistribution');
    $Data = $DB->to_array();
    $Count = $DB->record_count() - 1;

    if ($Count < 30) {
        $CountryMinThreshold = $Count;
    } else {
        $CountryMinThreshold = 30;
    }

    $CountryMax = ceil(log(Max(1, $Data[0][1])) / log(2)) + 1;
    $CountryMin = floor(log(Max(1, $Data[$CountryMinThreshold][1])) / log(2));

    $CountryRegions = array('RS' => array('RS-KM')); // Count Kosovo as Serbia as it doesn't have a TLD
    foreach ($Data as $Key => $Item) {
        list($Country, $UserCount) = $Item;
        $Countries[] = $Country;
        $CountryUsers[] = number_format((((log($UserCount) / log(2)) - $CountryMin) / ($CountryMax - $CountryMin)) * 100, 2);
        $Rank[] = round((1 - ($Key / $Count)) * 100);

        if (isset($CountryRegions[$Country])) {
            foreach ($CountryRegions[$Country] as $Region) {
                $Countries[] = $Region;
                $Rank[] = end($Rank);
            }
        }
    }
    reset($Rank);

    for ($i = $CountryMin; $i <= $CountryMax; $i++) {
        $LogIncrements[] = Format::human_format(pow(2, $i));
    }
    $Cache->cache_value('geodistribution', array($Countries, $Rank, $CountryUsers, $CountryMax, $CountryMin, $LogIncrements), 0);
}
*/

if (!$UserClasses = $Cache->get_value('stats_users_classes')) {
    $DB->prepared_query("
        SELECT p.Name, COUNT(m.ID) AS Users
        FROM users_main AS m
            JOIN permissions AS p ON m.PermissionID = p.ID
        WHERE m.Enabled = '1'
        GROUP BY p.Name
        ORDER BY Users DESC");
    $UserClasses = $DB->to_pair('Name', 'Users', false);
    $Cache->cache_value('stats_users_classes', $UserClasses, 3600 * 24 * 14);
}

if (!$Platforms = $Cache->get_value('stats_users_platforms')) {
    $DB->prepared_query("
        SELECT OperatingSystem, COUNT(UserID) AS Users
        FROM users_sessions
        GROUP BY OperatingSystem
        ORDER BY Users DESC");
    $Platforms = $DB->to_pair('OperatingSystem', 'Users');
    $Cache->cache_value('stats_users_platforms', $Platforms, 3600 * 24 * 14);
}

if (!$Browsers = $Cache->get_value('stats_users_browsers')) {
    $DB->prepared_query("
        SELECT Browser, COUNT(UserID) AS Users
        FROM users_sessions
        GROUP BY Browser
        ORDER BY Users DESC");

    $Browsers = $DB->to_pair('Browser', 'Users');
    $Cache->cache_value('stats_users_browsers', $Browsers, 3600 * 24 * 14);
}

//Timeline generation
if (!$Flow = $Cache->get_value('stats_users_flow')) {
    $Labels = [];
    $InFlow = [];
    $OutFlow = [];
    $Flow = [];
    $DB->prepared_query("
        SELECT DATE_FORMAT(JoinDate,'%b \'%y') AS Month, COUNT(UserID)
        FROM users_info
        GROUP BY Month
        ORDER BY JoinDate DESC
        LIMIT 1, 12");
    $TimelineIn = array_reverse($DB->to_array(false, MYSQLI_BOTH, false));
    $DB->prepared_query("
        SELECT DATE_FORMAT(BanDate,'%b \'%y') AS Month, COUNT(UserID)
        FROM users_info
        GROUP BY Month
        ORDER BY BanDate DESC
        LIMIT 1, 12");
    $TimelineOut = array_reverse($DB->to_array(false, MYSQLI_BOTH, false));

    foreach ($TimelineIn as $Month) {
        list($Label, $Amount) = $Month;
        $Labels[] = $Label;
        $InFlow[] = $Amount;
    }
    foreach ($TimelineOut as $Month) {
        list($Label, $Amount) = $Month;
        $OutFlow[] = $Amount;
    }
    for ($i = 0; $i < count($Labels); $i++) {
        $Flow[$Labels[$i]] = [
            'new' => isset($InFlow[$i]) ? $InFlow[$i] : 0,
            'disabled' => isset($OutFlow[$i]) ? $OutFlow[$i] : 0
        ];
    }

    // Tested: fine for Dec -> Jan
    $Cache->cache_value('stats_users_flow', $Flow, mktime(0, 0, 0, date('n') + 1, 2));
}

print(json_encode(
    [
        'status' => 'success',
        'response' => [
            'flow' => $Flow,
            'classes' => $UserClasses,
            'platforms' => $Platforms,
            'browsers' => $Browsers,
        ]
    ]
));
