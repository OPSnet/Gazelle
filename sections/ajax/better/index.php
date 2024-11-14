<?php

match ($_GET['method'] ?? '') {
    'transcode' => include_once 'transcode.php',
    'single'    => include_once 'single.php',
    default     => json_error('bad method'),
};
