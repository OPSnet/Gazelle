<?php
/*
 * This page creates previews of all supported stylesheets
 * SERVER_ROOT . '/' . STATIC_SERVER . 'stylespreview' must exist and be writable
 * Dependencies are PhantomJS (http://phantomjs.org/) and
 * ImageMagick (http://www.imagemagick.org/script/index.php)
 */
View::show_header('Rerender stylesheet gallery images');
$DB->query('
    SELECT
        ID,
        LOWER(REPLACE(Name," ","_")) AS Name,
        Name AS ProperName
    FROM stylesheets');
$Styles = $DB->to_array('ID', MYSQLI_BOTH);
$ImagePath = SERVER_ROOT . '/' . STATIC_SERVER . 'stylespreview';
?>
<div class="thin">
    <h2>Rerender stylesheet gallery images</h2>
    <div class="sidebar">
        <div class="box box_info">
            <div class="head colhead_dark">Rendering parameters</div>
            <ul class="stats nobullet">
                <li>Server root: <?=SERVER_ROOT; ?></li>
                <li>Static server: <?=STATIC_SERVER; ?></li>
                <li>Whoami: <?php echo(shell_exec('whoami')); ?></li>
                <li>Path: <?php echo dirname(__FILE__); ?></li>
                <li>NodeJS: <?php echo (shell_exec('node -v;')); ?></li>
                <li>Puppeteer: <?php echo (shell_exec('npm view -g puppeteer version')); ?></li>
            </ul>
        </div>
    </div>
    <div class="main_column">
        <div class="box">
            <div class="head">About rendering</div>
            <div class="pad">
                <p>You are now rendering stylesheet gallery images.</p>
                <p>The used parameters can be seen on the right, returned statuses are displayed below.</p>
            </div>
        </div>
        <div class="box">
            <div class="head">Rendering status</div>
            <div class="pad">
<?php
//set_time_limit(0);
foreach ($Styles as $Style) {
?>
                <div class="box">
                    <h6><?= $Style['Name'] ?></h6>
                    <p>Build preview:
<?php
    $CmdLine = '/usr/bin/node "' . dirname(__FILE__) . '/render_build_preview.js" "' . SERVER_ROOT . '" "' . STATIC_SERVER . '" "' . $Style['Name'] . '" "' . dirname(__FILE__) . '"';
    // echo $CmdLine . '<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    $BuildResult = trim(shell_exec(escapeshellcmd($CmdLine)));
    echo (empty($BuildResult)) ? 'Success.' : "Error occured: {$BuildResult}";
?>
                    </p>
<?php
    //If build was successful, snap a preview.
    if (empty($BuildResult)) {
?>
                    <p>Converting Image:
<?php
        $CmdLine = '/usr/bin/convert "' . $ImagePath . '/full_' . $Style['Name'] . '.png" -filter Box -resize 40% -quality 94 "' . $ImagePath . '/thumb_' . $Style['Name'] . '.png"';
        // echo $CmdLine . '<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        $ConvertResult = shell_exec(escapeshellcmd($CmdLine));
        echo (empty($ConvertResult)) ? 'Success.' : "Error occured: {$ConvertResult}";
?>
                    </p>
<?php
    } ?>
                </div>
<?php
} ?>
            </div>
        </div>
    </div>
</div>
<?php
View::show_footer();
