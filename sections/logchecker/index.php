<?php

require_once(match ($_REQUEST['action'] ?? 'test') {
    'take_test'   => 'take_test.php',
    'take_upload' => 'take_upload.php',
    'update'      => 'update.php',
    'upload'      => 'upload.php',
    default       => 'test.php',
});
