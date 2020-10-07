<?php
/*-- API Start Class -------------------------------*/
/*--------------------------------------------------*/
/* Simplified version of script_start, used for the    */
/* site API calls                                    */
/*--------------------------------------------------*/
/****************************************************/

use Twig\Loader\FilesystemLoader;
use Twig\Environment;

$ScriptStartTime = microtime(true); //To track how long a page takes to create

//Lets prevent people from clearing feeds
if (isset($_GET['clearcache'])) {
    unset($_GET['clearcache']);
}

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/../classes/time.class.php');
require_once(__DIR__.'/../classes/paranoia.class.php');
require_once(__DIR__.'/../classes/util.php');

$Cache = new CACHE;
$DB = new DB_MYSQL;
$Debug = new DEBUG;
$Twig = new Environment(
    new FilesystemLoader(__DIR__.'/../templates'),
    [
        'debug' => DEBUG_MODE,
        'cache' => __DIR__.'/../cache/twig'
    ]
);
$Debug->handle_errors();

G::initialize();

header('Expires: '.date('D, d M Y H:i:s', time() + (2 * 60 * 60)).' GMT');
header('Last-Modified: '.date('D, d M Y H:i:s').' GMT');
header('Content-type: application/json');
require_once(__DIR__.'/../sections/api/index.php');
