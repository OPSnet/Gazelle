<?php
if (PHP_VERSION_ID < 50400) {
    die("Gazelle requires PHP 5.4 or later to function properly");
}
date_default_timezone_set('UTC');

// Main settings
define('SITE_NAME', 'Orpheus Dev'); //The name of your site
define('SITE_HOST', 'localhost');
define('SITE_URL', 'http://localhost:8080');
define('MAIL_HOST', 'mail.'.SITE_HOST); // The host to use for mail delivery (e.g. mail.orpheus.network)
define('SERVER_ROOT', '/var/www'); //The root of the server, used for includes, purpose is to shorten the path string
define('ANNOUNCE_URL', 'http://'.SITE_HOST.':34000'); //Announce URL
define('ANNOUNCE_HTTP_URL', 'http://localhost:34000');
define('ANNOUNCE_HTTPS_URL', 'https://localhost:34000');
define('REFERRAL_BOUNCER', 'http://127.0.0.1:8888'); // URL to referral bouncer.
define('REFERRAL_KEY', hash('sha512', '')); // Shared key to encrypt data flowing to bouncer.
define('REFERRAL_SEND_EMAIL', false); // Whether to send invite emails for referrals.
define('GRANDFATHER_NO_SOURCE', strtotime('1970-01-01')); // End date to ignore source flag
define('GRANDFATHER_OLD_SOURCE', strtotime('1970-01-01')); // End date to allow APL

// Allows you to run static content off another server. Default is usually what you want.
define('STATIC_SERVER', 'static/');

// Keys
define('ENCKEY', 'JRFb5tW89xBSjaJP'); //Random key. The key for encryption
define('SCHEDULE_KEY', 'OL9n0m2JxhBxYyMvXWJg'); // Random key. This key must be the argument to schedule.php for the schedule to work.
define('RSS_HASH', 'weFQmRVNrfcbhq0TNWZA'); //Random key. Used for generating unique RSS auth key.

// MySQL details
define('SQLHOST', 'localhost'); //The MySQL host ip/fqdn
define('SQLLOGIN', 'gazelle');//The MySQL login
define('SQLPASS', 'password'); //The MySQL password
define('SQLDB', 'gazelle'); //The MySQL database to use
define('SQLPORT', 3306); //The MySQL port to connect on
define('SQLSOCK', '/var/run/mysqld/mysqld.sock');

// Memcached details
$MemcachedServers = [
    // unix sockets are fast, and other people can't telnet into them
    ['host' => '/var/run/memcached.sock', 'port' => 0],
];

// Sphinx details
define('SPHINX_HOST', '127.0.0.1');
define('SPHINX_PORT', 9312);
define('SPHINXQL_HOST', '127.0.0.1');
define('SPHINXQL_PORT', 9306);
define('SPHINXQL_SOCK', false);
define('SPHINX_MAX_MATCHES', 1000); // Must be <= the server's max_matches variable (default 1000)
define('SPHINX_INDEX', 'torrents');

// Ocelot details
define('DISABLE_TRACKER', true);
define('TRACKER_HOST', 'localhost');
define('TRACKER_PORT', 34000);
define('TRACKER_SECRET', '1737853d77069dc24824916a8d0e501e'); // Must be 32 characters and match site_password in Ocelot's config.cpp
define('TRACKER_REPORTKEY', '1737853d77069dc24824916a8d0e501e'); // Must be 32 characters and match report_password in Ocelot's config.cpp

// Site settings
define('CRYPT_HASH_PREFIX', '$2y$07$');
define('DEBUG_MODE', true); //Set to false if you dont want everyone to see debug information, can be overriden with 'site_debug'
define('DEBUG_WARNINGS', true); //Set to true if you want to see PHP warnings in the footer
define('SHOW_PUBLIC_INDEX', true); // Show the public index.php landing page
define('OPEN_REGISTRATION', true); //Set to false to disable open regirstration, true to allow anyone to register
define('USER_LIMIT', 40000); //The maximum number of users the site can have, 0 for no limit
define('STARTING_INVITES', 0); //# of invites to give to newly registered users
define('STARTING_UPLOAD', 3221225472); //Upload given to newly registered users, in bytes using IEC standard (1024 bytes per KiB)
define('BYTES_PER_FREELEECH_TOKEN', 536870912); // Amount of bytes to use per token
define('STACKABLE_FREELEECH_TOKENS', true); // Allow stacking tokens
define('REQUEST_TAX', 0.0); //Percentage Tax (0 - 1) to charge users on making requests
define('BLOCK_TOR', false); //Set to true to block Tor users
define('BLOCK_OPERA_MINI', false); //Set to true to block Opera Mini proxy
define('DONOR_INVITES', 2);
define('SYSTEM_USER_ID', 1);
define('TRASH_FORUM_ID', 4);
define('EDITING_FORUM_ID', 34);

if (!defined('FEATURE_EMAIL_REENABLE')) {
    define('FEATURE_EMAIL_REENABLE', true);
}

// User class IDs needed for automatic promotions. Found in the 'permissions' table
// Name of class    Class ID (NOT level)
define('ADMIN',            '40');
define('USER',            '2');
define('MEMBER',        '3');
define('POWER',            '4');
define('ELITE',            '5');
define('VIP',            '26');
define('TORRENT_MASTER','25');
define('MOD',            '11');
define('LEAD_DEV',      '43');
define('SYSOP',            '15');
define('ARTIST',        '19');
define('DONOR',            '20');
define('FLS_TEAM',        '23');
define('POWER_TM',        '29');
define('ELITE_TM',        '28');
define('FORUM_MOD',        '21');
define('TORRENT_MOD',   '22');
define('INTERVIEWER',   '30');
define('SECURITY',      '33');
define('IRC',           '34');
define('SHADOW',        '35');
define('ALPHA',         '36');
define('BRAVO',         '37');
define('CHARLIE',       '38');
define('DELTA',         '39');
define('RECRUITER',     '41');
define('ULTIMATE_TM',   '48');

// Pagination
define('TORRENT_COMMENTS_PER_PAGE', 10);
define('POSTS_PER_PAGE', 25);
define('TOPICS_PER_PAGE', 50);
define('TORRENTS_PER_PAGE', 50);
define('REQUESTS_PER_PAGE', 25);
define('MESSAGES_PER_PAGE', 25);
define('LOG_ENTRIES_PER_PAGE', 50);

// Cache catalogues
define('THREAD_CATALOGUE', 500); // Limit to THREAD_CATALOGUE posts per cache key.

// IRC settings
define('DISABLE_IRC', true);
define('BOT_NICK', '');
define('BOT_SERVER', ''); // IRC server address. Used for onsite chat tool.
define('BOT_PORT', 6667);
define('BOT_CHAN', '#'.SITE_HOST);
define('BOT_DISABLED_CHAN', '#'); // Channel to refer disabled users to.
define('BOT_REPORT_CHAN', '#');
define('SOCKET_LISTEN_PORT', 51010);
define('SOCKET_LISTEN_ADDRESS', 'localhost');
define('ADMIN_CHAN', '#');
define('LAB_CHAN', '#');
define('STATUS_CHAN', '#');

// Miscellaneous values
define('RANK_ONE_COST', 5);
define('RANK_TWO_COST', 10);
define('RANK_THREE_COST', 15);
define('RANK_FOUR_COST', 20);
define('RANK_FIVE_COST', 30);
define('MAX_RANK', 6);
define('MAX_EXTRA_RANK', 8);
define('DONOR_FORUM_RANK', 6);
define('DONOR_FORUM', 70);
define('MAX_SPECIAL_RANK', 3);

define('FORUM_REVEAL_VOTER', []);

define('CATEGORY', ['Music', 'Applications', 'E-Books', 'Audiobooks', 'E-Learning Videos', 'Comedy', 'Comics']);
define('CATEGORY_GROUPED', array_intersect(['Music'], CATEGORY));
define('CATEGORY_ICON', ['music.png', 'apps.png', 'ebook.png', 'audiobook.png', 'elearning.png', 'comedy.png', 'comics.png']);

$Formats = ['MP3', 'FLAC', 'AAC', 'AC3', 'DTS'];
$Bitrates = ['192', 'APS (VBR)', 'V2 (VBR)', 'V1 (VBR)', '256', 'APX (VBR)', 'V0 (VBR)', 'q8.x (VBR)', '320', 'Lossless', '24bit Lossless', 'Other'];
$Media = ['CD', 'DVD', 'Vinyl', 'Soundboard', 'SACD', 'DAT', 'Cassette', 'WEB'];

$CollageCats = [0=>'Personal', 1=>'Theme', 2=>'Genre introduction', 3=>'Discography', 4=>'Label', 5=>'Staff picks', 6=>'Charts', 7=>'Artists'];

$ZIPGroups = [
    0 => 'MP3 (VBR) - High Quality',
    1 => 'MP3 (VBR) - Low Quality',
    2 => 'MP3 (CBR)',
    3 => 'FLAC - Lossless',
    4 => 'Others'
];

//3D array of attributes, OptionGroup, OptionNumber, Name
$ZIPOptions = [
    '00' => [0,0,'V0'],
    '01' => [0,1,'APX'],
    '02' => [0,2,'256'],
    '03' => [0,3,'V1'],
    '10' => [1,0,'224'],
    '11' => [1,1,'V2'],
    '12' => [1,2,'APS'],
    '13' => [1,3,'192'],
    '20' => [2,0,'320'],
    '21' => [2,1,'256'],
    '22' => [2,2,'224'],
    '23' => [2,3,'192'],
    '30' => [3,0,'FLAC / 24bit / Vinyl'],
    '31' => [3,1,'FLAC / 24bit / DVD'],
    '32' => [3,2,'FLAC / 24bit / SACD'],
    '33' => [3,3,'FLAC / Log (100) / Cue'],
    '34' => [3,4,'FLAC / Log (100)'],
    '35' => [3,5,'FLAC / Log'],
    '36' => [3,6,'FLAC'],
    '40' => [4,0,'DTS'],
    '41' => [4,1,'Ogg Vorbis'],
    '42' => [4,2,'AAC - 320'],
    '43' => [4,3,'AAC - 256'],
    '44' => [4,4,'AAC - q5.5'],
    '45' => [4,5,'AAC - q5'],
    '46' => [4,6,'AAC - 192']
];

//Captcha fonts are located in ./fonts
$CaptchaFonts = [
    'ARIBLK.TTF',
    'IMPACT.TTF',
    'TREBUC.TTF',
    'TREBUCBD.TTF',
    'TREBUCBI.TTF',
    'TREBUCIT.TTF',
    'VERDANA.TTF',
    'VERDANAB.TTF',
    'VERDANAI.TTF',
    'VERDANAZ.TTF'];
//Captcha images should be located in /captcha
$CaptchaBGs = [
    'captcha1.png',
    'captcha2.png',
    'captcha3.png',
    'captcha4.png',
    'captcha5.png',
    'captcha6.png',
    'captcha7.png',
    'captcha8.png',
    'captcha9.png'];

// Deny cache access to keys without specified permission
define('CACHE_PERMISSION', [
    'api_apps' => 'site_debug',
    'catalogue' => 'site_debug'
]);
