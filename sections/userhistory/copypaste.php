<?php
//Copy-paste generation page. Designed for easy Ctrl+A & Ctrl+C use. Code is mostly a copy-paste from the pages for IP and Email history.

//Set UserID and check perms
$UserID = $_GET['userid'];
if (!is_number($UserID)) {
    error(404);
}
$UserInfo = Users::user_info($UserID);
if (!check_perms('users_view_ips', $UserInfo['Class'])) {
    error(403);
}

//Display username
echo "Username: ". $UserInfo['Username'] . "</br>";

//Display Join Date
$Joined = $DB->scalar("
    SELECT JoinDate
    FROM users_info
    WHERE UserID = ?
    ", $UserID
);
echo "Joined: " . $Joined . "</br>";

//Get Emails from the DB.
$DB->prepared_query("
    SELECT
        u.Email,
        ? AS Time,
        u.IP,
        c.Code
    FROM users_main AS u
        LEFT JOIN geoip_country AS c ON INET_ATON(u.IP) BETWEEN c.StartIP AND c.EndIP
    WHERE u.ID = ?
    UNION
    SELECT
        h.Email,
        h.Time,
        h.IP,
        c.Code
    FROM users_history_emails AS h
        LEFT JOIN geoip_country AS c ON INET_ATON(h.IP) BETWEEN c.StartIP AND c.EndIP
    WHERE UserID = ? "
        /*AND Time != '0000-00-00 00:00:00'*/."
    ORDER BY Time DESC", sqltime(), $UserID, $UserID);
$History = $DB->to_array();

//Display the emails.
echo "</br>Emails:</br>";
foreach ($History as $Key => $Values) {
    if ($Values['Time'] != "0000-00-00 00:00:00"){
        echo $Values['Email'] . " | Set from IP: " . $Values['IP'] . "</br>";
    }
}

//Get IPs from the DB and do some stuff to them so we can use it.
$DB->prepared_query("
    SELECT
        SQL_CALC_FOUND_ROWS
        IP,
        StartTime,
        EndTime
    FROM users_history_ips
    WHERE UserID = ?
    ORDER BY StartTime DESC", $UserID);


if ($DB->has_results()) {
    $Results = $DB->to_array(false, MYSQLI_ASSOC);
} else {
    $Results = [];
}
//Display the results.
echo "</br>Site IPs:</br>";
foreach ($Results as $Index => $Result) {
    $IP = $Result['IP'];
    $StartTime = $Result['StartTime'];
    $EndTime = $Result['EndTime'];
    if (!$Result['EndTime']) {
        $EndTime = sqltime();
    }
    echo $IP . " | Start: " . $StartTime . " | End: " . $EndTime . "</br>";
}

//Get Tracker IPs from the DB
$TrackerIps = $DB->prepared_query("
    SELECT IP, fid, tstamp
    FROM xbt_snatched
    WHERE uid = ?
        AND IP != ''
    ORDER BY tstamp DESC", $UserID);

//Display Tracker IPs
echo "</br>Tracker IPs:</br>";
$Results = $DB->to_array();
foreach ($Results as $Index => $Result) {
    $IP = $Result['IP'];
    $TorrentID = $Result['fid'];
    $Time = $Result['tstamp'];
    echo $IP . " | TorrentID: " . $TorrentID . " | Time: " . date('Y-m-d g:i:s', $Time) . "</br>";
}
