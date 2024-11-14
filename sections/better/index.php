<?php

require_once match ($_GET['method'] ?? '') {
    'transcode' => 'transcode.php',
    default     => 'better.php',
};
