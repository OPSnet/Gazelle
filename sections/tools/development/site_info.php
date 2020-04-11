<?php

// Note: The shell execs are operating from the root of the gazelle repo

function composer_exec($CMD) {
    // Composer won't work well through shell_exec if xdebug is enabled
    // which we might expect if DEBUG_MODE is enabled (as neither
    // xdebug or DEBUG_MODE should happen on production)
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        $CMD = 'COMPOSER_ALLOW_XDEBUG=1 '.$CMD;
    }
    return shell_exec($CMD);
}

if ((!defined('DEBUG_MODE') || DEBUG_MODE !== true) && !check_perms('admin_site_debug')) {
    error(403);
}

$Debug->set_flag('Start Git');

$GitBranch = shell_exec('git rev-parse --abbrev-ref HEAD');
$GitHash = shell_exec('git rev-parse HEAD');
$RemoteHash = shell_exec("git rev-parse origin/{$GitBranch}");

$Debug->set_flag('Start Composer');

$ComposerVersion = substr(composer_exec('composer --version'), 16);

$Packages = [];

$Composer = json_decode(file_get_contents(SERVER_ROOT.'/composer.json'), true);
foreach ($Composer['require'] as $Package => $Version) {
    $Packages[$Package] = ['Name' => $Package, 'Version' => $Version];
}
$ComposerLock = json_decode(file_get_contents(SERVER_ROOT.'/composer.lock'), true);
foreach ($ComposerLock['packages'] as $Package) {
    if (isset($Packages[$Package['name']])) {
        $Packages[$Package['name']]['Locked'] = $Package['version'];
    }
}

$ComposerPackages = json_decode(composer_exec('composer info --format=json'), true);
foreach ($ComposerPackages['installed'] as $Package) {
    if (isset($Packages[$Package['name']])) {
        $Packages[$Package['name']]['Installed'] = $Package['version'];
    }
}

$Debug->set_flag('Start Phinx');

$PhinxVersion = shell_exec('vendor/bin/phinx --version');
$PhinxMigrations = array_filter(json_decode(shell_exec('vendor/bin/phinx status --format=json | tail -n 1'), true)['migrations'], function($value) { return count($value) > 0; });
$PHPTimeStamp = date('Y-m-d H:i:s');
$DB->query('SELECT NOW() as now;');
$DBTimeStamp = $DB->fetch_record()['now'];

$Debug->set_flag('Start phpinfo');
ob_start();
phpinfo();
$Data = ob_get_contents();
ob_end_clean();
$Data = substr($Data, strpos($Data, '<body>') + 6, strpos($Data, '</body>'));

function uid ($id) {
    return sprintf("%s(%d)", posix_getpwuid($id)['name'], $id);
}

function gid ($id) {
    return sprintf("%s(%d)", posix_getgrgid($id)['name'], $id);
}

View::show_header('Site Information');
?>
<style type="text/css">
div#phpinfo {color: #222; font-family: sans-serif; display: none;}
div#phpinfo pre {margin: 0; font-family: monospace;}
div#phpinfo a:link {color: #009; text-decoration: none; background-color: #fff;}
div#phpinfo a:hover {text-decoration: underline;}
div#phpinfo table {border-collapse: collapse; border: 0; width: 934px; box-shadow: 1px 2px 3px #ccc;}
div#phpinfo .center {text-align: center;}
div#phpinfo .center table {margin: 1em auto; text-align: left;}
div#phpinfo .center th {text-align: center !important;}
div#phpinfo td, th {border: 1px solid #666; font-size: 75%; vertical-align: baseline; padding: 4px 5px;}
div#phpinfo h1 {font-size: 150%;}
div#phpinfo h2 {font-size: 125%;}
div#phpinfo .p {text-align: left;}
div#phpinfo .e {background-color: #ccf; width: 300px; font-weight: bold;}
div#phpinfo .h {background-color: #99c; font-weight: bold;}
div#phpinfo .v {background-color: #ddd; max-width: 300px; overflow-x: auto; word-wrap: break-word;}
div#phpinfo .v i {color: #999;}
div#phpinfo img {float: right; border: 0;}
div#phpinfo hr {width: 934px; background-color: #ccc; border: 0; height: 1px;}
</style>
<div class="thin">
    <h3>OS</h3>
    <div class="box pad">
        <span style="width: 100px; display: inline-block">User:</span> <?= uid(posix_getuid()) ?><br />
        <span style="width: 100px; display: inline-block">Group:</span> <?= gid(posix_getgid()) ?><br />
        <span style="width: 100px; display: inline-block">Effective User:</span> <?= uid(posix_geteuid()) ?><br />
        <span style="width: 100px; display: inline-block">Effective Group:</span> <?= gid(posix_getegid()) ?>
    </div>
    <h3>Timestamps</h3>
    <div class="box pad">
        <span style="width: 50px; display: inline-block">PHP:</span> <?=$PHPTimeStamp?><br />
        <span style="width: 50px; display: inline-block">DB:</span> <?=$DBTimeStamp?>
    </div>

    <h3>PHP</h3>
    <div class="box pad">
        PHP Version: <?=phpversion();?><br />
        <a onclick="toggle_display('phpinfo')" href='javascript:void(0)'>Toggle PHP Info</a><br />
        <div id="phpinfo"><?=$Data?></div>
    </div>

    <h3>Git</h3>
    <div class="box pad">
        <span style="width: 150px; display: inline-block;">Branch:</span> <?=$GitBranch?><br />
        <span style="width: 150px; display: inline-block;">Local Hash:</span> <?=$GitHash?><br />
        <span style="width: 150px; display: inline-block;">Remote Hash:</span> <?=$RemoteHash?>
    </div>

    <h3>Composer</h3>
    <div class="box pad">
        Composer Version: <?=$ComposerVersion?><br />
        <table>
            <tr class="colhead">
                <td>Package</td>
                <td>Version</td>
                <td>Installed</td>
                <td>Locked</td>
            </tr>
        <?php
        foreach ($Packages as $Package) {
            $Installed = $Package['Installed'] ?? '';
            $Locked = $Package['Locked'] ?? '';
            ?>
            <tr>
                <td><?=$Package['Name']?></td>
                <td><?=$Package['Version']?></td>
                <td><?=$Installed?></td>
                <td><?=$Locked?></td>
            </tr>
            <?php
        }
        ?>
        </table>
    </div>

    <h3>Phinx</h3>
    <div class="box pad">
        <?=$PhinxVersion?><br />
        <table>
            <tr class='colhead'>
                <td>Status</td>
                <td>Migration ID</td>
                <td>Migration Name</td>
            </tr>
            <?php
            foreach($PhinxMigrations as $Migration) {
                ?>
            <tr>
                <td><?=$Migration['migration_status']?></td>
                <td><?=$Migration['migration_id']?></td>
                <td><?=$Migration['migration_name']?></td>
            </tr>
                <?php
            }
            ?>
        </table>
    </div>
</div>
<?php

View::show_footer();
