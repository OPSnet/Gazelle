<?php

/**
 * Load classes automatically when they're needed
 *
 * @param string $ClassName class name
 */
spl_autoload_register(function ($ClassName) {
    if (strpos($ClassName, 'Gazelle\\') === 0) {
        return;
    }

    $FilePath = __DIR__ . '/' . strtolower($ClassName) . '.class.php';
    if (!file_exists($FilePath)) {
        switch ($ClassName) {
            case 'DB_MYSQL':
                $FileName = 'mysql.class';
                break;
            case 'BENCODE_DICT':
            case 'BENCODE_LIST':
                $FileName = 'torrent.class';
                break;
            default:
                return;
        }
        $FilePath = __DIR__ . "/$FileName.php";
    }
    if (file_exists($FilePath)) {
        require_once($FilePath);
    }
});

require(__DIR__.'/../vendor/autoload.php');
