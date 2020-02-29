<?php

define('MEMORY_EXCEPTION', true);
define('TIME_EXCEPTION', true);
define('ERROR_EXCEPTION', true);

require_once(__DIR__.'/../classes/config.php');
require_once(__DIR__.'/../classes/classloader.php');

use Gazelle\Util\Crypto;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

require_once(__DIR__.'/../classes/time.class.php'); //Require the time class
require_once(__DIR__.'/../classes/paranoia.class.php'); //Require the paranoia check_paranoia function
require_once(__DIR__.'/../classes/regex.php');
require_once(__DIR__.'/../classes/util.php');
require_once(__DIR__.'/../classes/image.class.php');

$Debug = new DEBUG;
$Debug->handle_errors();

$DB = new DB_MYSQL;
$Cache = new CACHE($MemcachedServers);

G::$Cache = $Cache;
G::$DB = $DB;
G::$Twig = new Environment(
    new FilesystemLoader(__DIR__.'/templates'),
    ['cache' => __DIR__.'/cache/twig']
);

define('WIDTH', 585);
define('HEIGHT', 400);

global $Img;
$Img = new IMAGE;

$DB->query('
	select ArtistID, Name from artists_group
	WHERE artistid in (SELECT distinct s1.artistid FROM artists_similar AS s1 inner JOIN artists_similar AS s2 ON s1.SimilarID = s2.SimilarID AND s1.ArtistID != s2.ArtistID)
');
while ($row = $DB->next_record()) {
	$save = $DB->get_query_id();
	echo "$row[0]\t$row[1]\n";
        $Img->create(WIDTH, HEIGHT);
        $Img->color(255, 255, 255, 127);

        $Similar = new ARTISTS_SIMILAR($row[0], $row[1]);
        $Similar->set_up();
        $Similar->set_positions();
        $Similar->background_image();

	$DB->set_query_id($save);
}
