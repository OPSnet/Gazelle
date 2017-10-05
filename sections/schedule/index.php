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

$LineEnd = check_perms('admin_schedule') ? "<br />" : "\n";
$RunEvery = false;
$RunHourly = false;
$RunDaily = false;
$RunWeekly = false;
$RunBiweekly = false;
$RunManual = false;
$RunTasks = null;

/**
 * Given a directory name for one of the sections in schedule/, load all of the PHP files
 * and execute them. We use extract($GLOBALS) to give them access to variables in
 * the global scope as all use $DB, most use $CACHE, and then some use $Hour, $Day, etc.
 * This should be rewritten to be properly injected, but oh well.
 *
 * @param string $Dir which dir to load all files from to run
 */
function run_tasks($Dir) {
	global $RunTasks, $LineEnd;
	$Tasks = array_diff(scandir(SERVER_ROOT.'/sections/schedule/'.$Dir, 1), array('.', '..'));
	extract($GLOBALS);
	foreach ($Tasks as $Task) {
		$TimeStart = microtime(true);
		$Task = str_replace('.php', '', $Task);
		if (!empty($RunTasks) && !in_array($Task, $RunTasks)) {
			continue;
		}
		print("Running {$Task}...");
		/** @noinspection PhpIncludeInspection */
		require_once SERVER_ROOT."/sections/schedule/{$Dir}/{$Task}.php";
		print("DONE! (".number_format(microtime(true) - $TimeStart, 3).")".$LineEnd);
	}
}

if (PHP_SAPI === 'cli') {
	if (!isset($argv[1]) || $argv[1] != SCHEDULE_KEY) {
		error(403);
	}
	for ($i = 2; $i < count($argv); $i++) {
		if ($argv[$i] === 'run_tasks') {
			if ($i < count($argv) - 1) {
				$RunTasks = array();
				for (++$i; $i < count($argv); $i++) {
					$RunTasks[] = $argv[$i];
				}
				foreach (array('RunEvery', 'RunHourly', 'RunDaily', 'RunWeekly', 'RunBiweekly', 'RunManual') as $Var) {
					$$Var = true;
				}
			}
		}
		elseif (substr($argv[$i], 0, 4) === 'run_') {
			$Var = str_replace('_', '', ucwords($argv[$i], '_'));
			${$Var} = true;
		}
	}
}
else {
	foreach($_GET as $Key => $Value) {
		if (substr($Key, 0, 4) === 'run_') {
			$Key = str_replace('_', '', ucwords($argv[$i]));
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

if (empty($RunTasks) && !$RunManual && !$RunEvery && !$RunHourly && !$RunDaily && !$RunWeekly && !$RunBiweekly) {
	// We set this true here just so we run the tasks as we're (trying) to run all sections and
	// not just an individual one (or some collection of tasks)
	$RunEvery = true;
	$DB->query("
	UPDATE schedule
	SET
		NextHour = $CurrentHour,
		NextDay = $CurrentDay,
		NextBiWeekly = $CurrentBiWeek");
}

$sqltime = sqltime();

echo "Current Time: $sqltime\n\n";

/*************************************************************************\
//--------------Run every time ------------------------------------------//

These functions are run every time the script is executed (every 15
minutes).

\*************************************************************************/

if ($RunEvery) {
	echo "Running every run tasks...\n";
	run_tasks('every');
	echo "\n";
}

/*************************************************************************\
//--------------Run every hour ------------------------------------------//

These functions are run every hour.

\*************************************************************************/

if ($Hour != $CurrentHour || $RunHourly) {
	echo "Running hourly tasks...\n";
	run_tasks('hourly');
	echo "\n";
}

/*************************************************************************\
//--------------Run every day -------------------------------------------//

These functions are run in the first 15 minutes of every day.

\*************************************************************************/

if ($Day != $CurrentDay || $RunDaily) {
	echo "Running daily tasks...\n";
	run_tasks('daily');
	echo "\n";
}

/*************************************************************************\
//--------------Run weekly ----------------------------------------------//

These functions are run in the first 15 minutes of the week (Sunday).

\*************************************************************************/

if (($Day != $CurrentDay || $RunDaily) && date('w') == 0) {
	echo "Running weekly tasks...\n";
	run_tasks('weekly');
	echo "\n";
}

/*************************************************************************\
//--------------Run twice per month -------------------------------------//

These functions are twice per month, on the 8th and the 22nd.

\*************************************************************************/

if ($BiWeek != $CurrentBiWeek || $RunBiweekly) {
	echo "Running bi-weekly tasks...\n";
	run_tasks('biweekly');
	echo "\n";
}

/*************************************************************************\
//--------------Run manually --------------------------------------------//

These functions are only run when manual is specified via GET 'runmanual'

\*************************************************************************/
if ($RunManual) {
	echo "Running manual tasks...\n";
	run_tasks('manually');
}

// Done

echo "-------------------------\n\n";
if (check_perms('admin_schedule')) {
	echo '<pre>';
	View::show_footer();
}