<?php

require_once(match ($_GET['type'] ?? 'inbox') {
    'viewconv' => 'viewconv.php',
    default    => 'inbox.php',
});
