<?
ini_set('memory_limit', '5G');
set_time_limit(0);

// Data is published on the first Tuesday of every month
$HaveData = false;
$FileNameLocation = '/tmp/GeoLiteCity-latest/GeoLiteCity_'.date('Ymd', strtotime('first tuesday '.date('Y-m'))).'/GeoLiteCity-Location.csv';
$FileNameBlocks = '/tmp/GeoLiteCity-latest/GeoLiteCity_'.date('Ymd', strtotime('first tuesday '.date('Y-m'))).'/GeoLiteCity-Blocks.csv';
if (file_exists('/tmp/GeoLiteCity-latest')) {
	if (file_exists($FileNameLocation) && file_exists($FileNameBlocks)) {
		$HaveData = true;
	}
}

if (!$HaveData) {
	//requires wget, unzip commands to be installed
	shell_exec('rm -r /tmp/GeoLiteCity-latest*');
	shell_exec('wget http://geolite.maxmind.com/download/geoip/database/GeoLiteCity_CSV/GeoLiteCity-latest.zip -O /tmp/GeoLiteCity-latest.zip');
	shell_exec('unzip /tmp/GeoLiteCity-latest.zip -d /tmp/GeoLiteCity-latest');
	shell_exec('rm /tmp/GeoLiteCity-latest.zip');
}

if (!file_exists($FileNameLocation) || !file_exists($FileNameBlocks)) {
	error('Download or extraction of maxmind database failed');
}

View::show_header();

$DB->query("TRUNCATE TABLE geoip_country");

$DB->prepared_query("
CREATE TEMPORARY TABLE temp_geoip_locations (
	`ID` int(10) NOT NULL PRIMARY KEY,
	`Country` varchar(2) NOT NULL
)");

// Note: you cannot use a prepared query here for this
$DB->query("
LOAD DATA INFILE '{$FileNameLocation}' INTO TABLE temp_geoip_locations
FIELDS TERMINATED BY ',' 
OPTIONALLY ENCLOSED BY '\"' 
LINES TERMINATED BY '\n'
IGNORE 2 LINES
(@ID, @Country, @dummy, @dummy, @dummy, @dummy, @dummy, @dummy, @dummy)
SET `ID`=@ID, `Country`=@Country;");


$DB->prepared_query("
CREATE TEMPORARY TABLE temp_geoip_blocks (
	`StartIP` INT(11) UNSIGNED NOT NULL,
	`EndIP` INT(11) UNSIGNED NOT NULL,
	`LocID` INT(10) NOT NULL
)");

// Note: you cannot use a prepared query here for this
$DB->query("
LOAD DATA INFILE '{$FileNameBlocks}' INTO TABLE temp_geoip_blocks
FIELDS TERMINATED BY ',' 
OPTIONALLY ENCLOSED BY '\"' 
LINES TERMINATED BY '\n'
IGNORE 2 LINES
(`StartIP`,`EndIP`, `LocID`);");

$DB->prepared_query("
INSERT INTO geoip_country (StartIP, EndIP, Code) 
	SELECT StartIP, EndIP, Country 
	FROM temp_geoip_blocks AS tgb
	LEFT JOIN temp_geoip_locations AS tgl ON tgb.LocID = tgl.ID
");

print "{$DB->affected_rows()} locations inserted";

$DB->query("INSERT INTO users_geodistribution
	(Code, Users)
SELECT g.Code, COUNT(u.ID) AS Users
FROM geoip_country AS g
	JOIN users_main AS u ON INET_ATON(u.IP) BETWEEN g.StartIP AND g.EndIP
WHERE u.Enabled = '1'
GROUP BY g.Code
ORDER BY Users DESC");

print "{$DB->affected_rows()} users updated";

View::show_footer();
