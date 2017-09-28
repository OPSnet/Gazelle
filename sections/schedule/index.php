<?php
/*************************************************************************\
//--------------Schedule page -------------------------------------------//

This page is run every 15 minutes, by cron.

\*************************************************************************/

set_time_limit(50000);
ob_end_flush();
gc_enable();

/*
 * Use this if your version of pgrep does not support the '-c' option.
 * The '-c' option requires procps-ng.
 *
 * $PCount = chop(shell_exec("/usr/bin/pgrep -f schedule.php | wc -l"));
 */
$PCount = chop(shell_exec("/usr/bin/pgrep -cf schedule.php"));
if ($PCount > 3) {
	// 3 because the cron job starts two processes and pgrep finds itself
	die("schedule.php is already running. Exiting ($PCount)\n");
}

/**
 * Given a directory name for one of the sections in schedule/, load all of the PHP files
 * and execute them. We use extract($GLOBALS) to give them access to variables in
 * the global scope as all use $DB, most use $CACHE, and then some use $Hour, $Day, etc.
 * This should be rewritten to be properly injected, but oh well.
 *
 * @param string $Dir which dir to load all files from to run
 */
function run_tasks($Dir) {
	$Tasks = array_diff(scandir(SERVER_ROOT.'/sections/schedule/'.$Dir, 1), array('.', '..'));
	extract($GLOBALS);
	foreach ($Tasks as $Task) {
		print('Running '.str_replace('.php', '', $Task).'...');
		/** @noinspection PhpIncludeInspection */
		require_once SERVER_ROOT."/sections/schedule/{$Dir}/{$Task}";
		print("DONE!\n");
	}
}

$run_hourly = false;
$run_daily = false;
$run_weekly = false;
$run_hourly = false;
$run_biweekly = false;
$run_manual = false;

if (PHP_SAPI === 'cli') {
	if (!isset($argv[1]) || $argv[1] != SCHEDULE_KEY) {
		error(403);
	}
	for ($i = 2; $i < count($argv); $i++) {
		if (substr($argv[$i], 0, 4) === 'run_') {
			${$argv[$i]} = true;
		}
	}
}
else {
	foreach($_GET as $Key => $Value) {
		if (substr($Key, 0, 4) === 'run_') {
			$$Key = true;
		}
	}
	if (!check_perms('admin_schedule')) {
		error(403);
	}
}

if (check_perms('admin_schedule')) {
	authorize();
	View::show_header();
	echo '<pre>';
}

$DB->query("
	SELECT NextHour, NextDay, NextBiWeekly
	FROM schedule");
list($Hour, $Day, $BiWeek) = $DB->next_record();
$CurrentHour = date('H');
$CurrentDay = date('d');
$CurrentBiWeek = ($CurrentDay < 22 && $CurrentDay >= 8) ? 22 : 8;


$DB->query("
	UPDATE schedule
	SET
		NextHour = $CurrentHour,
		NextDay = $CurrentDay,
		NextBiWeekly = $CurrentBiWeek");

$sqltime = sqltime();

echo "Current Time: $sqltime\n\n";

/*************************************************************************\
//--------------Run every time ------------------------------------------//

These functions are run every time the script is executed (every 15
minutes).

\*************************************************************************/

echo "Running every run tasks...\n";
run_tasks('every');
echo "\n";

/*************************************************************************\
//--------------Run every hour ------------------------------------------//

These functions are run every hour.

\*************************************************************************/

if ($Hour != $CurrentHour || $run_hourly) {
	echo "Running hourly tasks...\n";
	run_tasks('hourly');
	echo "\n";
}

/*************************************************************************\
//--------------Run every day -------------------------------------------//

These functions are run in the first 15 minutes of every day.

\*************************************************************************/

if ($Day != $CurrentDay || $run_daily) {
	echo "Running daily tasks...\n";
	run_tasks('daily');
	echo "\n";
}

/*************************************************************************\
//--------------Run weekly ----------------------------------------------//

These functions are run in the first 15 minutes of the week (Sunday).

\*************************************************************************/

if (($Day != $CurrentDay || $run_daily) && date('w') == 0) {
	echo "Running weekly tasks...\n";
	run_tasks('weekly');
	echo "\n";
}

/*************************************************************************\
//--------------Run twice per month -------------------------------------//

These functions are twice per month, on the 8th and the 22nd.

\*************************************************************************/

if ($BiWeek != $CurrentBiWeek || $run_biweekly) {
	echo "Running bi-weekly tasks...\n";
	run_tasks('biweekly');
	echo "\n";
}

/*************************************************************************\
//--------------Run manually --------------------------------------------//

These functions are only run when manual is specified via GET 'runmanual'

\*************************************************************************/
if ($run_manual) {
	echo "Running manual tasks...\n";
	run_tasks('manually');
}

// Done

echo "-------------------------\n\n";
if (check_perms('admin_schedule')) {
	echo '<pre>';
	View::show_footer();
}