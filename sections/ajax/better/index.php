<?php

match ($_GET['method'] ?? '') {
    'transcode' => require_once('transcode.php'),
    'single'    => require_once('single.php'),
    default     => json_error('bad method'),
};
