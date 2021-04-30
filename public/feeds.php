<?php
/*-- Feed Start Class ----------------------------------*/
/*------------------------------------------------------*/
/* Simplified version of script_start, used for the     */
/* sitewide RSS system.                                 */
/*------------------------------------------------------*/
/********************************************************/

// Let's prevent people from clearing feeds
if (isset($_GET['clearcache'])) {
    unset($_GET['clearcache']);
}

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/../classes/util.php');

$Cache = new CACHE;
$DB    = new DB_MYSQL;
$Twig  = Gazelle\Util\Twig::factory();
Gazelle\Base::initialize($Cache, $DB, $Twig);

$Feed = new Feed;

header('Cache-Control: no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma:');
header('Expires: '.date('D, d M Y H:i:s', time() + (2 * 60 * 60)).' GMT');
header('Last-Modified: '.date('D, d M Y H:i:s').' GMT');

$Feed->UseSSL = (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
require(__DIR__ . '/../sections/feeds/index.php');
