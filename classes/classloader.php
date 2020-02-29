<?php

/**
 * Load classes automatically when they're needed
 *
 * @param string $ClassName class name
 */
spl_autoload_register(function ($ClassName) {
    $FilePath = __DIR__ . '/' . strtolower($ClassName) . '.class.php';
    if (!file_exists($FilePath)) {
        // TODO: Rename the following classes to conform with the code guidelines
        switch ($ClassName) {
            case 'DB_MYSQL':
                $FileName = 'mysql.class';
                break;
            case 'BENCODE_DICT':
            case 'BENCODE_LIST':
                $FileName = 'torrent.class';
                break;
            default:
                die("Couldn't import class $ClassName");
        }
        $FilePath = __DIR__ . "/$FileName.php";
    }
    require_once($FilePath);
});

require(__DIR__.'/../vendor/autoload.php');
