<?php

// NOTE: When editing this file, please make-sure to update the generate-config.sh file for docker!

// Main settings
define('SITE_NAME', ''); //The name of your site
define('NONSSL_SITE_URL', ''); //The FQDN of your site
define('SSL_SITE_URL', ''); //The FQDN of your site, make this different if you are using a subdomain for ssl
define('SITE_IP', ''); //The IP address by which your site can be publicly accessed
define('SITE_HOST', ''); // The host for your site (e.g. localhost, orpheus.network)
define('SITE_URL', 'https://'.SITE_HOST); // The base URL to access the site (e.g. http://localhost:8080, https://orpheus.network)
define('MAIL_HOST', 'mail.'.SITE_HOST); // The host to use for mail delivery (e.g. mail.orpheus.network)
define('SERVER_ROOT', '/path'); //The root of the server, used for includes, purpose is to shorten the path string
define('SERVER_ROOT_LIVE', '/path'); //Only needed on the beta site when accessing unmocked resources, otherwise == SERVER_ROOT
define('BETA', false); //Only needed on the beta site when different code paths are necessary
define('ANNOUNCE_HTTP_URL', '');
define('ANNOUNCE_HTTPS_URL', '');
define('REFERRAL_BOUNCER', ''); // URL to the bouncer including trailing /.
define('REFERRAL_KEY', hash('sha512', '')); //Random key. Used for encrypting traffic to/from the boucner.
define('REFERRAL_SEND_EMAIL', false); // Whether to send invite emails for referrals.
define('REFERRAL_SITES', ['ABC', 'DEF']);
define('RECOVERY', false);
define('RECOVERY_BUFFER', false); // is buffer from previous site restituted
define('GRANDFATHER_NO_SOURCE', strtotime('1970-01-01')); // End date to ignore source flag.
define('GRANDFATHER_OLD_SOURCE', strtotime('1970-01-01')); // End date to allow source flag from previous site.
define('MAX_PREV_TORRENT_ID', 0); // Lowest torrent ID of previous site incarnation.

// Allows you to run static content off another server. Default is usually what you want.
define('NONSSL_STATIC_SERVER', 'static/');
define('SSL_STATIC_SERVER', 'static/');

// Keys
define('ENCKEY', ''); //Random key. The key for encryption
define('SITE_SALT', ''); //Random key. Default site wide salt for passwords, DO NOT LEAVE THIS BLANK/CHANGE AFTER LAUNCH!
define('SCHEDULE_KEY', ''); // Random key. This key must be the argument to schedule.php for the schedule to work.
define('RSS_HASH', ''); //Random key. Used for generating unique RSS auth key.

// MySQL details
define('SQLHOST', 'localhost'); //The MySQL host ip/fqdn
define('SQLLOGIN', '');//The MySQL login
define('SQLPASS', ''); //The MySQL password
define('SQL_PHINX_USER', ''); // User to use for Phinx migrations
define('SQL_PHINX_PASS', ''); // Pass to use for Phinx migrations
define('SQLDB', 'gazelle'); //The MySQL database to use
define('SQLPORT', 3306); //The MySQL port to connect on
define('SQLSOCK', false); // Socket mysql is listening on, usually /var/run/mysqld/mysqld.sock

// Memcached details
$MemcachedServers = [
    // unix sockets are fast, and other people can't telnet into them
    ['host' => 'unix:///var/run/memcached.sock', 'port' => 0, 'buckets' => 1],
];

// Sphinx details
define('SPHINX_HOST', 'localhost');
define('SPHINX_PORT', 9312);
define('SPHINXQL_HOST', '127.0.0.1');
define('SPHINXQL_PORT', 9306);
define('SPHINXQL_SOCK', false);
define('SPHINX_MAX_MATCHES', 1000); // Must be <= the server's max_matches variable (default 1000)
define('SPHINX_INDEX', 'torrents');

// Ocelot details
define('DISABLE_TRACKER', false);
define('TRACKER_HOST', 'localhost');
define('TRACKER_PORT', 2710);
define('TRACKER_SECRET', ''); // Must be 32 characters and match site_password in Ocelot's config.cpp
define('TRACKER_REPORTKEY', ''); // Must be 32 characters and match report_password in Ocelot's config.cpp

define('STATIC_SERVER', SSL_STATIC_SERVER);

/*
if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 80) {
    define('SITE_URL', NONSSL_SITE_URL);
    define('STATIC_SERVER', NONSSL_STATIC_SERVER);
} else {
    define('SITE_URL', SSL_SITE_URL);
    define('STATIC_SERVER', SSL_STATIC_SERVER);
}
*/

// Site settings
define('CRYPT_HASH_PREFIX', '$2y$07$');
define('DEBUG_MODE', false); //Set to false if you dont want everyone to see debug information, can be overriden with 'site_debug'
define('DEBUG_WARNINGS', true); //Set to true if you want to see PHP warnings in the footer
define('SHOW_PUBLIC_INDEX', true); // Show the public index.php landing page
define('OPEN_REGISTRATION', true); //Set to false to disable open registration, true to allow anyone to register
define('OPEN_EXTERNAL_REFERRALS', false); //Set to false to disable external tracker referrals, true to allow them
define('USER_LIMIT', 5000); //The maximum number of users the site can have, 0 for no limit
define('STARTING_UPLOAD', 3221225472); //Upload given to newly registered users, in bytes using IEC standard (1024 bytes per KiB)
define('BYTES_PER_FREELEECH_TOKEN', 536870912); // Amount of bytes to use per token
define('STACKABLE_FREELEECH_TOKENS', true); // Allow stacking tokens
define('FREELEECH_TOKEN_EXPIRY_DAYS', 30); // Number of days before an activated token expires
define('REQUEST_TAX', 0.0); //Percentage Tax (0 - 1) to charge users on making requests
define('STARTING_INVITES', 0); //# of invites to give to newly registered users
define('BLOCK_TOR', false); //Set to true to block Tor users
define('BLOCK_OPERA_MINI', false); //Set to true to block Opera Mini proxy
define('DONOR_INVITES', 2);
define('SYSTEM_USER_ID', 17); // ID for user to create "system" threads under (e.g. Edit Requests)
define('TRASH_FORUM_ID', 4); // ID of forum to send threads to when trash button is pressed
define('EDITING_FORUM_ID', 34); // ID of forum to send editing requests to
define('EDITING_TRASH_FORUM_ID', 48); // ID of forum to send editing threads to when trash button is pressed in EDITING_FORUM_ID
define('HELP_FORUM_ID', 3); // ID of help forum
define('HELP_RESOLVED_FORUM_ID', 44); // ID of forum to send resolved help threads when resolve button is pressed in HELP_FORUM_ID
define('BUGS_FORUM_ID', 6); // ID of bug reports forum
define('BUGS_RESOLVED_FORUM_ID', 14); // ID of forum to send resolved bug reports when resolve button is pressed in BUGS_FORUM_ID
define('AOTM_FORUM_ID', 51); // ID of the Album of The Month forum
define('VANITY_HOUSE_FORUM_ID', 18); // Vanity House forum
define('CLIENT_WHITELIST_FORUM_ID', 680); // Client whitelist suggestion forum

if (!defined('FEATURE_EMAIL_REENABLE')) {
    define('FEATURE_EMAIL_REENABLE', true);
}

// User class IDs needed for automatic promotions. Found in the 'permissions' table
// Name of class        Class ID (NOT level)
define('ADMIN',         '1');
define('USER',          '2');
define('MEMBER',        '3');
define('POWER',         '4');
define('ELITE',         '5');
define('VIP',           '6');
define('TORRENT_MASTER','7');
define('LEGEND',        '8');
define('CELEB',         '9');
define('MOD',           '11');
define('DESIGNER',      '13');
define('CODER',         '14');
define('SYSOP',         '15');
define('ARTIST',        '19');
define('DONOR',         '20');
define('FLS_TEAM',      '21');
define('POWER_TM',      '22');
define('ELITE_TM',      '23');
define('FORUM_MOD',     '28');
define('ULTIMATE_TM',   '48');

// Locked account constant
define('STAFF_LOCKED', 1);

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
// define('DISABLE_IRC', false);
define('BOT_NICK', '');
define('BOT_SERVER', ''); // IRC server address. Used for onsite chat tool.
define('BOT_PORT', 6667);
define('BOT_CHAN', '#'.NONSSL_SITE_URL);
define('BOT_ANNOUNCE_CHAN', '#');
define('BOT_STAFF_CHAN', '#');
define('BOT_DISABLED_CHAN', '#'); // Channel to refer disabled users to.
define('BOT_HELP_CHAN', '#');
define('BOT_DEBUG_CHAN', '#');
define('BOT_REPORT_CHAN', '#');
define('BOT_NICKSERV_PASS', '');
define('BOT_INVITE_CHAN', BOT_CHAN.'-invites'); // Channel for non-members seeking an interview
define('BOT_INTERVIEW_CHAN', BOT_CHAN.'-interview'); // Channel for the interviews
define('BOT_INTERVIEW_NUM', 5);
define('BOT_INTERVIEW_STAFF', BOT_CHAN.'-interviewers'); // Channel for the interviewers
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

define('BONUS_AWARD_FLAC_PERFECT', 400);
define('BONUS_AWARD_FLAC', 30);
define('BONUS_AWARD_MP3', 30);
define('BONUS_AWARD_OTHER', 10);

define('BONUS_POOL_TAX_STD', 0.9);
define('BONUS_POOL_TAX_ELITE', 0.8);
define('BONUS_POOL_TAX_TM', 0.7);
define('BONUS_POOL_TAX_STAFF', 0.5);

define('SOURCE_FLAG_WIKI_PAGE_ID', 113);

define('TMPDIR', '/tmp');

$ForumsRevealVoters = [];
$ForumsDoublePost = [];

define('STORAGE_PATH_TORRENT', '/var/lib/gazelle/torrent');
define('STORAGE_PATH_RIPLOG', '/var/lib/gazelle/riplog');
define('STORAGE_PATH_RIPLOGHTML', '/var/lib/gazelle/riploghtml');

$Categories = ['Music', 'Applications', 'E-Books', 'Audiobooks', 'E-Learning Videos', 'Comedy', 'Comics'];
$GroupedCategories = array_intersect(['Music'], $Categories);
$CategoryIcons = ['music.png', 'apps.png', 'ebook.png', 'audiobook.png', 'elearning.png', 'comedy.png', 'comics.png'];
$CategoriesV2 = ['Music'];
$CategoryV2Icons = ['music.png'];

$Formats = ['MP3', 'FLAC', 'Ogg Vorbis', 'AAC', 'AC3', 'DTS'];
$Bitrates = ['192', 'APS (VBR)', 'V2 (VBR)', 'V1 (VBR)', '256', 'APX (VBR)', 'V0 (VBR)', 'q8.x (VBR)', '320', 'Lossless', '24bit Lossless', 'Other'];
$Media = ['CD', 'DVD', 'Vinyl', 'Blu-ray', 'Soundboard', 'SACD', 'DAT', 'Cassette', 'WEB'];

define('ICON_ALL',    "\xe2\x9c\x85");
define('ICON_NONE',   "\xf0\x9f\x9a\xab");
define('ICON_TOGGLE', "\xf0\x9f\x94\x81");

$CollageCats = [
    0 => 'Personal',
    1 => 'Theme',
    2 => 'Genre Introduction',
    3 => 'Discography',
    4 => 'Label',
    5 => 'Staff picks',
    6 => 'Charts',
    7 => 'Artists',
    8 => 'Awards',
    9 => 'Series',
];

$ReleaseTypes = [
    1  => 'Album',
    3  => 'Soundtrack',
    5  => 'EP',
    6  => 'Anthology',
    7  => 'Compilation',
    8  => 'Sampler',
    9  => 'Single',
    10 => 'Demo',
    11 => 'Live album',
    12 => 'Split',
    13 => 'Remix',
    14 => 'Bootleg',
    15 => 'Interview',
    16 => 'Mixtape',
    17 => 'DJ Mix',
    18 => 'Concert recording',
    21 => 'Unknown',
];

$ZIPGroups = [
    0 => 'MP3 (VBR) - High Quality',
    1 => 'MP3 (VBR) - Low Quality',
    2 => 'MP3 (CBR)',
    3 => 'FLAC - Lossless',
    4 => 'Others',
];

//3D array of attributes, OptionGroup, OptionNumber, Name
$ZIPOptions = [
    '00' => [0, 0, 'V0'],
    '01' => [0, 1, 'APX'],
    '02' => [0, 2, '256'],
    '03' => [0, 3, 'V1'],
    '10' => [1, 0, '224'],
    '11' => [1, 1, 'V2'],
    '12' => [1, 2, 'APS'],
    '13' => [1, 3, '192'],
    '20' => [2, 0, '320'],
    '21' => [2, 1, '256'],
    '22' => [2, 2, '224'],
    '23' => [2, 3, '192'],
    '30' => [3, 0, 'FLAC / 24bit / Vinyl'],
    '31' => [3, 1, 'FLAC / 24bit / DVD'],
    '32' => [3, 2, 'FLAC / 24bit / SACD'],
    '33' => [3, 3, 'FLAC / 24bit / WEB'],
    '34' => [3, 4, 'FLAC / Log (100) / Cue'],
    '35' => [3, 5, 'FLAC / Log (100)'],
    '36' => [3, 6, 'FLAC / Log'],
    '37' => [3, 7, 'FLAC'],
    '40' => [4, 0, 'DTS'],
    '41' => [4, 1, 'Ogg Vorbis'],
    '42' => [4, 2, 'AAC - 320'],
    '43' => [4, 3, 'AAC - 256'],
    '44' => [4, 4, 'AAC - q5.5'],
    '45' => [4, 5, 'AAC - q5'],
    '46' => [4, 6, 'AAC - 192'],
];

// Ratio requirements, in descending order
// Columns: Download amount, required ratio, grace period
$RatioRequirements = [
    [50 * 1024 * 1024 * 1024, 0.60, date('Y-m-d H:i:s')],
    [40 * 1024 * 1024 * 1024, 0.50, date('Y-m-d H:i:s')],
    [30 * 1024 * 1024 * 1024, 0.40, date('Y-m-d H:i:s')],
    [20 * 1024 * 1024 * 1024, 0.30, date('Y-m-d H:i:s')],
    [10 * 1024 * 1024 * 1024, 0.20, date('Y-m-d H:i:s')],
    [5 * 1024 * 1024 * 1024,  0.15, date('Y-m-d H:i:s', time() - (60 * 60 * 24 * 14))]
];

//Captcha fonts should be located in /classes/fonts
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
    'VERDANAZ.TTF'
];

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
    'captcha9.png'
];

// Special characters, and what they should be converted to
// Used for torrent searching
$SpecialChars = [
    '&' => 'and'
];

// Deny cache access to keys without specified permission
$CachePermissions = [
    'api_apps' => 'site_debug',
    'catalogue' => 'site_debug'
];

// array to store external site credentials and API URIs, stored in cache to keep user sessions alive
$ExternalServicesConfig = [
    "Orpheus" => [
        'type' => 'gazelle',
        'inviter_id' => 1,
        'base_url' => 'https://orpheus.network/',
        'api_path' => 'ajax.php?action=',
        'login_path' => 'login.php',
        'username' => 'foo',
        'password' => 'bar',
        'cookie' => '',
        'cookie_expiry' => 0,
        'status' => TRUE
    ],
    "VagrantGazelle" => [
        'type' => 'gazelle',
        'inviter_id' => 1,
        'base_url' => 'http://localhost:80/',
        'api_path' => 'ajax.php?action=',
        'login_path' => 'login.php',
        'username' => 'foo',
        'password' => 'bar',
        'cookie' => '',
        'cookie_expiry' => 0,
        'status' => TRUE
    ],
    "PassThePopcorn" => [
        'type' => 'gazelle',
        'inviter_id' => 1,
        'base_url' => 'https://passthepopcorn.me/',
        'api_path' => 'ajax.php?action=',
        'login_path' => 'login.php',
        'username' => 'foo',
        'password' => 'bar',
        'cookie' => '',
        'cookie_expiry' => 0,
        'status' => TRUE
    ]];

define('TOP10_ALL_TIME_THRESHOLD', 150);
define('TOP10_YEAR_THRESHOLD', 80);
define('TOP10_DATA_THRESHOLD', 50);

define('SITE_LAUNCH_YEAR', 2018);

define('ARTIST_MAIN', 1);
define('ARTIST_GUEST', 2);
define('ARTIST_REMIXER', 3);
define('ARTIST_COMPOSER', 4);
define('ARTIST_CONDUCTOR', 5);
define('ARTIST_DJ', 6);
define('ARTIST_PRODUCER', 7);

define('RANDOM_ARTIST_MIN_ENTRIES', 1);
define('RANDOM_COLLAGE_MIN_ENTRIES', 1);
define('RANDOM_TORRENT_MIN_SEEDS', 0);

//resource_type://username:password@domain:port/path?query_string#anchor
define('RESOURCE_REGEX', '(https?|ftps?):\/\/');
define('IP_REGEX', '(\d{1,3}\.){3}\d{1,3}');
define('DOMAIN_REGEX', '([a-z0-9\-\_]+\.)*[a-z0-9\-\_]+');
define('PORT_REGEX', ':\d{1,5}');
define('URL_REGEX', '('.RESOURCE_REGEX.')('.IP_REGEX.'|'.DOMAIN_REGEX.')('.PORT_REGEX.')?(\/\S*)*');
define('USERNAME_REGEX_SHORT', '[a-z0-9_?\.]{1,20}');
define('USERNAME_REGEX', '/^'.USERNAME_REGEX_SHORT.'$/iD');
define('EMAIL_REGEX','[_a-z0-9-]+([.+][_a-z0-9-]+)*@'.DOMAIN_REGEX);
define('IMAGE_REGEX', URL_REGEX.'\/\S+\.(jpg|jpeg|tif|tiff|png|gif|bmp)(\?\S*)?');
define('CSS_REGEX', URL_REGEX.'\/\S+\.css(\?\S*)?');
define('SITELINK_REGEX', RESOURCE_REGEX.'(ssl.)?'.preg_quote(NONSSL_SITE_URL, '/'));
define('TORRENT_REGEX', SITELINK_REGEX.'\/torrents\.php\?(.*&)?torrentid=(\d+)'); // torrentid = group 4
define('TORRENT_GROUP_REGEX', SITELINK_REGEX.'\/torrents\.php\?(.*&)?id=(\d+)'); // id = group 4
define('ARTIST_REGEX', SITELINK_REGEX.'\/artist\.php\?(.*&)?id=(\d+)'); // id = group 4

define('DONOR_RANK_PRICE', 10);
define('DONOR_FIRST_INVITE_COUNT', 2);

define('TAG_OFFICIAL_COLUMNS', 4);
