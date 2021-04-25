<?php

enforce_login();

if (isset($_FILES['log']) && is_uploaded_file($_FILES['log']['tmp_name'])) {
    $file = $_FILES['log'];
    $isPaste = false;
} elseif (!empty($_POST["pastelog"])) {
    $fileTmp = tempnam('/tmp', 'log_');
    file_put_contents($fileTmp, $_POST["pastelog"]);
    $file = ['tmp_name' => $fileTmp, 'name' => $fileTmp];
    $isPaste = true;
} else {
    error('No log file uploaded or file is empty.');
}
$logfile = new \Gazelle\Logfile($file['tmp_name'], $file['name']);
if (isset($fileTmp)) {
    unlink($fileTmp);
}

View::show_header('Logchecker');
?>

<div class="linkbox">
    <a href="logchecker.php" class="brackets">Test Another Log file</a>
    <a href="logchecker.php?action=upload" class="brackets">Upload Missing Logs</a>
</div>
<div class="thin">
    <h2 class="center">Logchecker Test Results</h2>
<?= $Twig->render('logchecker/report.twig', [
    'pasted'   => $isPaste,
    'logfile'  => $logfile,
]) ?>
</div>

<?php
View::show_footer();
