<?php

enforce_login();

if (isset($_FILES['log']) && is_uploaded_file($_FILES['log']['tmp_name'])) {
    $File = $_FILES['log'];
    $isPaste = false;
} elseif (!empty($_POST["pastelog"])) {
    $TmpFile = tempnam('/tmp', 'log_');
    file_put_contents($TmpFile, $_POST["pastelog"]);
    $File = ['tmp_name' => $TmpFile, 'name' => $TmpFile];
    $isPaste = true;
} else {
    error('No log file uploaded or file is empty.');
}
$logfile = new \Gazelle\Logfile($File['tmp_name'], $File['name']);

if (isset($TmpFile)) {
    unlink($TmpFile);
}

View::show_header('Logchecker');
?>

<div class="linkbox">
    <a href="logchecker.php" class="brackets">Test Another Log File</a>
    <a href="logchecker.php?action=upload" class="brackets">Upload Missing Logs</a>
</div>
<div class="thin">
    <h2 class="center">Logchecker Test Results</h2>
<?= G::$Twig->render('logchecker/report.twig', [
    'PASTED'   => $isPaste,
    'CHECKSUM' => $logfile->checksum(),
    'SCORE'    => $logfile->score(),
    'REPORT'   => [['details' => $logfile->details(), 'text' => $logfile->text()]],
]) ?>
</div>

<?php
View::show_footer();
