<?php

match ($_GET['type'] ?? '') {
    'posts' => include_once 'post_history.php',
    default => json_error('bad type'),
};
