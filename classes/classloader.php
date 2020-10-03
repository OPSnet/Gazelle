<?php

/**
 * Load classes automatically when they're needed
 *
 * @param string $className class name
 */
spl_autoload_register(function (string $className) {
    $file = __DIR__ . '/' . strtolower($className) . '.class.php';
    if (!file_exists($file)) {
        switch ($className) {
            case 'BENCODE_DICT':
            case 'BENCODE_LIST':
                $file = __DIR__ . "/torrent.class.php";
                break;
            default:
                return;
        }
    }
    if (file_exists($file)) {
        require_once($file);
    }
});

require_once(__DIR__ . '/../vendor/autoload.php');
