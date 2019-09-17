<?php

use OrpheusNET\Logchecker\Logchecker;

//ini_set('upload_max_filesize',1000000);
enforce_login();

$ValidateChecksum = true;
if (isset($_FILES['log']) && is_uploaded_file($_FILES['log']['tmp_name'])) {
    $File = $_FILES['log'];
} elseif (!empty($_POST["pastelog"])) {
    $ValidateChecksum = false;
    $TmpFile = tempnam('/tmp', 'log_');
    file_put_contents($TmpFile, $_POST["pastelog"]);
    $File = array('tmp_name' => $TmpFile, 'name' => $TmpFile);
} else {
    error('No log file uploaded or file is empty.');
}


View::show_header('Logchecker');

echo <<<HTML
<div class="linkbox">
	<a href="logchecker.php" class="brackets">Test Another Log File</a>
	<a href="logchecker.php?action=upload" class="brackets">Upload Missing Logs</a>
</div>
<div class="thin">
	<h2 class="center">Logchecker Test Results</h2>
HTML;

$Log = new Logchecker();
$Log->validateChecksum($ValidateChecksum);
$Log->new_file($File['tmp_name']);

list($Score, $Bad, $Checksum, $Text) = $Log->parse();

if($Score == 100) {
    $Color = '#418B00';
}
elseif($Score > 90) {
    $Color = '#74C42E';
}
elseif($Score > 75) {
    $Color = '#FFAA00';
}
elseif($Score > 50) {
    $Color = '#FF5E00';
}
else {
    $Color = '#FF0000';
}

if (!$Checksum) {
    echo <<<HTML
	<blockquote>
		<strong>Trumpable For:</strong>
		<br /><br />
		Bad/No Checksum(s)
	</blockquote>
HTML;
}

echo <<<HTML
	<blockquote>
		<strong>Score:</strong> <span style='color:{$Color}'>{$Score}</span> (out of 100)
	</blockquote>
HTML;

if($Bad){
    echo <<<HTML
	<blockquote>
		<h3>Log validation report:</h3>
		<ul>
HTML;
    foreach($Bad as $Property) {
        echo "\t\t\t<li>{$Property}</li>";
    }
echo <<<HTML
		</ul>
	</blockquote>
HTML;
}

echo <<<HTML
	<blockquote>
		<pre>{$Text}</pre>
	</blockquote>
</div>
HTML;
View::show_footer();

if (!empty($TmpFile) && is_file($TmpFile)) {
    unlink($TmpFile);
}
