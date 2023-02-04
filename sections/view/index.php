<?php

if (!array_key_exists('type', $_GET) && !array_key_exists('id', $_GET)) {
    error(404);
}

switch($_GET['type']) {
    case 'riplog':
        if (preg_match('/^(\d+)\D(\d+)$/', $_GET['id'], $m)) {
            header('Content-type: text/plain');
            header('Content-Disposition: inline; filename="' . $m[1] . '_' . $m[2] . '.txt"');
            $file = new \Gazelle\File\RipLog;
            echo $file->get([$m[1], $m[2]]);
        }
        else {
            error(404);
        }
        break;
    default:
        error(404);
}
