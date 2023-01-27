<?php

match ($_GET['type'] ?? '') {
    'posts' => require_once('post_history.php'),
    default => json_error('bad type'),
};
