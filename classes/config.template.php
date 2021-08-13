<?php

// NOTE: When editing this file, please make-sure to update the generate-config.sh file for docker!

// Main settings
define('SITE_NAME', ''); //The name of your site
define('SITE_HOST', ''); // The host for your site (e.g. localhost, orpheus.network)
define('SITE_URL', 'https://'.SITE_HOST); // The base URL to access the site (e.g. http://localhost:8080, https://orpheus.network)
//define('ALT_SITE_HOST', ''); // Alternative hostname for site, such as for beta deploy (e.g. beta.localhost)
//define('ALT_SITE_URL', 'https://' . ALT_SITE_HOST); // This should be uncommented out if you are providing a second way to access site code, e.g. beta site
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
define('RECOVERY_SALT', '');
define('RECOVERY_PATH', '');
define('RECOVERY_DB', '');
define('RECOVERY_MAPPING_TABLE', 'users_' . RECOVERY_DB . '_mapping');
define('RECOVERY_IRC_TABLE', 'users_' . RECOVERY_DB . '_irc');
define('RECOVERY_AUTOVALIDATE', true);
define('RECOVERY_AUTOVALIDATE_LIMIT', 20);
define('RECOVERY_ADMIN_ID', 17); // hermes
define('RECOVERY_ADMIN_NAME', 'RecoveryBot');
define('RECOVERY_BUFFER_REASSIGN_LIMIT', 100);
define('RECOVERY_PAIR_SALT', '');

define('SOURCE', ''); // source flag to use for torrent files. should be unique from other sites to enable easy cross seeding.
define('GRANDFATHER_SOURCE', ''); // flag to use for grandfathering torrents. useful if expecting torrents from a defunct site.
define('GRANDFATHER_OLD_SOURCE', strtotime('1970-01-01')); // End date to allow source flag from previous site.
define('GRANDFATHER_NO_SOURCE', strtotime('1970-01-01')); // End date to ignore source flag.

define('MAX_PATH_LEN', 200); // Maximum filename length for torrent files and in archives
define('MAX_PREV_TORRENT_ID', 0); // Lowest torrent ID of previous site incarnation.
define('FAKE_USERAGENT', 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.1 Safari/537.11');
define('SEEDBOX_SALT', '8EQKRWZqA5DMVwpAqQKRCiMm5xaucsPePseXwZhm88T8PyyuAkjVgTLrAMWWeB8W'); // change me
define('AVATAR_SALT', 'BEapyctddrananytAqnkqccFgEntBdgf'); // change me
define('AVATAR_WIDTH', 150);
define('SIMILAR_WIDTH', 720);
define('SIMILAR_HEIGHT', 500);

define('LOGIN_ATTEMPT_BACKOFF', [
    0,
    30,
    90,
    60 * 5, // 5 minutes
    60 * 15,
    3600 * 3, // 3 hours
    3600 * 6,
    86400,
    86400 * 3,
    86400 * 7,
]);

// Allows you to run static content off another server. Default is usually what you want.
define('STATIC_SERVER', 'static');

// Keys
define('ENCKEY', ''); //Random key. The key for encryption
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

// Site settings
define('CRYPT_HASH_PREFIX', '$2y$07$');
define('DEBUG_EMAIL', false); //Set to true to write messages to TMPDIR instead of delivering
define('DEBUG_MODE', false); //Set to false if you dont want everyone to see debug information, can be overriden with 'site_debug'
define('DEBUG_WARNINGS', true); //Set to true if you want to see PHP warnings in the footer
define('DEBUG_UPLOAD_NOTIFICATION', false); // Set to true to dump notification trigger results
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
define('IP_OVERLAPS', 5); // Threshold for duplicate IPs
define('MIN_INVITE_CLASS', 150); // Minimum class allowed to purchase invites
define('USER_TITLE_LENGTH', 1024); // The maximum length of a custom user title

define('SYSTEM_USER_ID', 17); // ID for user to create "system" threads under (e.g. Edit Requests)
define('STAFF_FORUM_ID', 5); // ID of principal Staff forum (for polls)
define('TRASH_FORUM_ID', 4); // ID of forum to send threads to when trash button is pressed
define('EDITING_FORUM_ID', 34); // ID of forum to send editing requests to
define('HELP_FORUM_ID', 3); // ID of help forum
define('BUGS_FORUM_ID', 6); // ID of bug reports forum
define('AOTM_FORUM_ID', 51); // ID of the Album of The Month forum
define('VANITY_HOUSE_FORUM_ID', 18); // Vanity House forum
define('CLIENT_WHITELIST_FORUM_ID', 680); // Client whitelist suggestion forum

define("PARANOIA_HIDE", 0);
define("PARANOIA_ALLOWED", 1);
define("PARANOIA_OVERRIDDEN", 2);
define("PARANOIA_PREVIEW_USER", 1); // change to an unprivileged staff account
define("PARANOIA_OVERRIDE", [
    'downloaded'       => 'users_mod',
    'uploaded'         => 'users_mod',
    'lastseen'         => 'users_mod',
    'ratio'            => 'users_mod',
    'requiredratio'    => 'users_mod',
    'hide_donor_heart' => 'users_mod',
    'bonuspoints'      => 'admin_bp_history',
    'torrentcomments'  => 'site_moderate_forums',
    'invitedcount'     => 'users_view_invites',
    'snatched'         => 'users_view_torrents_snatchlist',
    'snatched+'        => 'users_view_torrents_snatchlist',
    'leeching'         => 'users_view_seedleech',
    'leeching+'        => 'users_view_seedleech',
    'seeding'          => 'users_view_seedleech',
    'seeding+'         => 'users_view_seedleech',
    'uploads'          => 'users_view_seedleech',
    'uploads+'         => 'users_view_seedleech',
]);

define('USER_TORRENT_DELETE_MAX', 3);
define('USER_TORRENT_DELETE_HOURS', 24);

define('DELETE_USER_STATS_DAILY_DAY',    15); // retain how many days worth of hourly granularity
define('DELETE_USER_STATS_MONTHLY_DAY', 120); // retain how many days worth of daily granularity

if (!defined('FEATURE_EMAIL_REENABLE')) {
    define('FEATURE_EMAIL_REENABLE', true);
}

// Name of class        Class ID (NOT level)
define('USER',            2);
define('MEMBER',          3);
define('POWER',           4);
define('ELITE',           5);
define('TORRENT_MASTER',  7);
define('POWER_TM',       22);
define('ELITE_TM',       23);
define('ULTIMATE_TM',    48);
define('FORUM_MOD',      28);
define('MOD',            11);
define('SYSOP',          15);

define('DONOR',          20);
define('FLS_TEAM',       23);
define('INTERVIEWER',    30);
define('RECRUITER',      41);
define('VIP',             6);

// Locked account constant
define('STAFF_LOCKED', 1);
define('STAFF_LEVEL', 820); // least permissions.Level of staff

// Pagination
define('BOOKMARKS_PER_PAGE', 20);
define('COLLAGES_PER_PAGE', 25);
define('CONTEST_ENTRIES_PER_PAGE', 50);
define('FRIENDS_PER_PAGE', 20);
define('INVITES_PER_PAGE', 50);
define('ITEMS_PER_PAGE', 50);
define('IPS_PER_PAGE', 50);
define('LOG_ENTRIES_PER_PAGE', 50);
define('MESSAGES_PER_PAGE', 25);
define('PEERS_PER_PAGE', 100);
define('POSTS_PER_PAGE', 25);
define('REPORTS_PER_PAGE', '10');
define('REQUESTS_PER_PAGE', 25);
define('TOPICS_PER_PAGE', 50);
define('TORRENTS_PER_PAGE', 50);
define('TORRENT_COMMENTS_PER_PAGE', 10);
define('USERS_PER_PAGE', 50);
define('WIKI_ARTICLES_PER_PAGE', 25);

define('AJAX_USERS_PER_PAGE', 30);

// Cache catalogues
define('THREAD_CATALOGUE', 500); // Limit to THREAD_CATALOGUE posts per cache key.

// IRC settings
define('DISABLE_IRC', false);
define('BOT_NICK', '');
define('BOT_SERVER', ''); // IRC server address. Used for onsite chat tool.
define('BOT_PORT', 6667);
define('BOT_CHAN', '#mygazelle');
define('ADMIN_CHAN', '#admin');
define('LAB_CHAN', '#lab');
define('STATUS_CHAN', '#status');
define('MOD_CHAN', '#staff');
define('BOT_DISABLED_CHAN', '#disabled'); // Channel to refer disabled users to.
define('BOT_REPORT_CHAN', '#reports');
define('SOCKET_LISTEN_PORT', 51010);
define('SOCKET_LISTEN_ADDRESS', 'localhost');

// Miscellaneous values
define('RANK_ONE_COST', 5);
define('RANK_TWO_COST', 10);
define('RANK_THREE_COST', 15);
define('RANK_FOUR_COST', 20);
define('RANK_FIVE_COST', 30);
define('MAX_RANK', 6);
define('MAX_EXTRA_RANK', 8);
define('DONOR_FORUM_RANK', 6);
define('DONOR_FORUM', null); // donor forum id
define('MAX_SPECIAL_RANK', 3);

define('BONUS_AWARD_FLAC_PERFECT', 400);
define('BONUS_AWARD_FLAC', 30);
define('BONUS_AWARD_MP3', 30);
define('BONUS_AWARD_OTHER', 10);

define('BONUS_POOL_TAX_STD', 0.9);
define('BONUS_POOL_TAX_ELITE', 0.8);
define('BONUS_POOL_TAX_TM', 0.7);
define('BONUS_POOL_TAX_STAFF', 0.5);

define('TEST_CONTEST_PAYOUT', true); // set to true to test

define('INDEX_WIKI_PAGE_ID', 1);
define('RULES_WIKI_PAGE_ID', 127);
define('SOURCE_FLAG_WIKI_PAGE_ID', 113);

define('TMPDIR', '/tmp');

define('FORUM_REVEAL_VOTER', []);

define('STORAGE_PATH_TORRENT', '/var/lib/gazelle/torrent');
define('STORAGE_PATH_RIPLOG', '/var/lib/gazelle/riplog');
define('STORAGE_PATH_RIPLOGHTML', '/var/lib/gazelle/riploghtml');

//Useful: http://www.robtex.com/cnet/
define('ALLOWED_PROXY', [
    //Opera Turbo (may include Opera-owned IP addresses that aren't used for Turbo, but shouldn't run much risk of exploitation)
    '64.255.180.*', //Norway
    '64.255.164.*', //Norway
    '80.239.242.*', //Poland
    '80.239.243.*', //Poland
    '91.203.96.*', //Norway
    '94.246.126.*', //Norway
    '94.246.127.*', //Norway
    '195.189.142.*', //Norway
    '195.189.143.*', //Norway
]);

define('CATEGORY', ['Music', 'Applications', 'E-Books', 'Audiobooks', 'E-Learning Videos', 'Comedy', 'Comics']);
define('CATEGORY_GROUPED', array_intersect(['Music'], CATEGORY));
define('CATEGORY_ICON', ['music.png', 'apps.png', 'ebook.png', 'audiobook.png', 'elearning.png', 'comedy.png', 'comics.png']);

define('FORMAT', [
    'MP3',
    'FLAC',
    'Ogg Vorbis',
    'AAC',
    'AC3',
    'DTS'
]);
define('ENCODING', [
    'Lossless',
    '24bit Lossless',
    'V0 (VBR)',
    'V1 (VBR)',
    'V2 (VBR)',
    '320',
    '256',
    '192',
    '160',
    '128',
    '96',
    '64',
    'APS (VBR)',
    'APX (VBR)',
    'q8.x (VBR)',
    'Other'
]);
define('MEDIA', [
    'CD',
    'WEB',
    'Vinyl',
    'DVD',
    'BD',
    'Soundboard',
    'SACD',
    'DAT',
    'Cassette',
]);

define('HOMEPAGE_TAG_IGNORE', [0]); // set to 'test' tag to ignore staff test uploads

define('ICON_ALL',     "\xe2\x9c\x85");
define('ICON_NONE',    "\xf0\x9f\x9a\xab");
define('ICON_TOGGLE',  "\xf0\x9f\x94\x81");
define('ICON_PADLOCK', "\xF0\x9F\x94\x92");

define('COLLAGE', [
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
]);
define('COLLAGE_PERSONAL_ID', 0);
define('COLLAGE_ARTISTS_ID', 7);
define('COLLAGE_SAMPLE_THRESHOLD', 4);
define('PERSONAL_COLLAGE_SAMPLE_THRESHOLD', 4);

define('ZIP_GROUP', [
    0 => 'MP3 (VBR) - High Quality',
    1 => 'MP3 (VBR) - Low Quality',
    2 => 'MP3 (CBR)',
    3 => 'FLAC - Lossless',
    4 => 'Others',
]);
define('ZIP_OPTION', [
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
    '24' => [2, 4, '160'],
    '25' => [2, 5, '128'],
    '26' => [2, 6, '96'],
    '27' => [2, 7, '64'],
    '30' => [3, 0, 'FLAC / 24bit / Vinyl'],
    '31' => [3, 1, 'FLAC / 24bit / DVD'],
    '32' => [3, 2, 'FLAC / 24bit / SACD'],
    '33' => [3, 3, 'FLAC / 24bit / WEB'],
    '34' => [3, 4, 'FLAC / Log (100) / Cue'],
    '35' => [3, 5, 'FLAC / Log (100)'],
    '36' => [3, 6, 'FLAC / Log'],
    '37' => [3, 7, 'FLAC / WEB'],
    '38' => [3, 8, 'FLAC'],
    '40' => [4, 0, 'DTS'],
    '42' => [4, 2, 'AAC - 320'],
    '43' => [4, 3, 'AAC - 256'],
    '44' => [4, 4, 'AAC - q5.5'],
    '45' => [4, 5, 'AAC - q5'],
    '46' => [4, 6, 'AAC - 192'],
]);

define('CAPTCHA_FONT', [
    'ARIBLK.TTF',
    'IMPACT.TTF',
    'TREBUC.TTF',
    'TREBUCBD.TTF',
    'TREBUCBI.TTF',
    'TREBUCIT.TTF',
    'VERDANA.TTF',
    'VERDANAB.TTF',
    'VERDANAI.TTF',
    'VERDANAZ.TTF',
]);
define('CAPTCHA_BG', [
    'captcha1.png',
    'captcha2.png',
    'captcha3.png',
    'captcha4.png',
    'captcha5.png',
    'captcha6.png',
    'captcha7.png',
    'captcha8.png',
    'captcha9.png',
]);

// Memcached details
define('MEMCACHE_HOST_LIST', [
    // unix sockets are fast, and other people can't telnet into them
    ['host' => 'unix:///var/run/memcached.sock', 'port' => 0, 'buckets' => 1],
]);
define('CACHE_ID', 'ops');

// Deny cache access to keys without specified permission
define('CACHE_PERMISSION', [
    'api_apps' => 'site_debug',
    'catalogue' => 'site_debug'
]);

define('CACHE_BULK_FLUSH', 500);

define('CACHE_RESPONSE', [
     0 => 'success',
     1 => 'failure/delete ok',
    16 => 'not found',
]);

define('CACHE_DB', [
    'artist'        => ['table' => 'artists_group',  'pk' => 'ArtistID'],
    'collage'       => ['table' => 'collages',       'pk' => 'ID'],
    'torrent-group' => ['table' => 'torrents_group', 'pk' => 'ID'],
    'user'          => ['table' => 'users_main',     'pk' => 'ID'],
]);

define('CACHE_NAMESPACE', [
    'artist' => [
        'a1' => 'artist_%d',
        'a2' => 'artist_comments_%d',
        'a3' => 'artist_comments_%d_catalogue_0',
        'a4' => 'artist_groups_%d',
        'a5' => 'artists_collages_%d',
        'a6' => 'artists_requests_%d',
        'a7' => 'similar_positions_%d',
    ],
    'collage' => [
        'c1' => 'collage_%d',
        'c2' => 'collage_display_%d',
        'c3' => 'collage_subs_user_%d',
        'c4' => 'collage_subs_user_new_%d',
    ],
    'torrent-group' => [
        'g1' => 'torrents_collages_%d',
        'g2' => 'torrent_collages_personal_%d',
        'g3' => 'torrents_cover_art_%d',
        'g4' => 'torrents_details_%d',
        'g5' => 'torrent_group_%d',
        'g6' => 'torrent_group_light_%d',
        'g7' => 'groups_artists_%d',
        'g8' => 'tg_%d',
        'g9' => 'tlist_%d',
    ],
    'user' => [
        'u1' => 'bookmarks_group_ids_%d',
        'u2' => 'donor_info_%d',
        'u3' => 'inbox_new_%d',
        'u4' => 'u_%d',
        'u5' => 'user_info_%d',
        'u6' => 'user_info_heavy_%d',
        'u7' => 'user_stats_%d',
        'u8' => 'user_statgraphs_%d',
        'u9' => 'user_tokens_%d',
    ],
]);

define('SITE_LAUNCH_YEAR', 2018);

define('ARTIST_MAIN',      1);
define('ARTIST_GUEST',     2);
define('ARTIST_REMIXER',   3);
define('ARTIST_COMPOSER',  4);
define('ARTIST_CONDUCTOR', 5);
define('ARTIST_DJ',        6);
define('ARTIST_PRODUCER',  7);
define('ARTIST_ARRANGER',  8);
define('ARTIST_TYPE', [
    ARTIST_MAIN      => 'Main',
    ARTIST_GUEST     => 'Guest',
    ARTIST_REMIXER   => 'Remixer',
    ARTIST_COMPOSER  => 'Composer',
    ARTIST_CONDUCTOR => 'Conductor',
    ARTIST_DJ        => 'DJ/Compiler',
    ARTIST_PRODUCER  => 'Producer',
    ARTIST_ARRANGER  => 'Arranger',
]);

define('ARTIST_SECTION_ARRANGER', 1020);
define('ARTIST_SECTION_PRODUCER', 1021);
define('ARTIST_SECTION_COMPOSER', 1022);
define('ARTIST_SECTION_REMIXER', 1023);
define('ARTIST_SECTION_GUEST', 1024);

define('RANDOM_ARTIST_MIN_ENTRIES', 1);
define('RANDOM_COLLAGE_MIN_ENTRIES', 1);
define('RANDOM_TORRENT_MIN_SEEDS', 0);

define('IP_REGEXP',       '/\b(?:\d{1,3}\.){3}\d{1,3}\b/');
define('URL_REGEXP_STEM', '((?:f|ht)tps?:\/\/)(?:' . str_replace('/', '', IP_REGEXP) . '|[\w-]+(?:\.[\w-]+)+)(?::\d{1,5})?(?:\/\S*)');
define('URL_REGEXP',      '/^' . URL_REGEXP_STEM . '$/i');
define('CSS_REGEXP',      '/^' . URL_REGEXP_STEM . '\.css(?:\?\S*)?$/i');
define('IMAGE_REGEXP',    '/\b(' . URL_REGEXP_STEM . '\.(?:gif|png|webm|jpe?g|tiff?)(\?\S*)?)\b/i');
define('SITELINK_REGEXP', '(?:' . preg_quote(SITE_URL, '/') . (defined('ALT_SITE_URL') ? '|' . preg_quote(ALT_SITE_URL, '/') : '') . ')');
define('ARTIST_REGEXP',   '/^' . SITELINK_REGEXP . '\/artist\.php\?.*?\bid=(?P<id>\d+)$/');
define('TGROUP_REGEXP',   '/^' . SITELINK_REGEXP . '\/torrents\.php\?.*?\bid=(?P<id>\d+)\b/');
define('TORRENT_REGEXP',  '/^' . SITELINK_REGEXP . '\/torrents\.php\?.*?\btorrentid=(?P<id>\d+)\b/');
define('EMAIL_REGEXP',    '/^[\w-]+(?:\.[\w-]+)*(?:\+[.\w-]+)?@[\w-]+(?:\.[\w-]+)+$/');
define('USERNAME_REGEXP', '/\b(?:[01]$(*PRUNE)(*FAIL)|(?P<username>[\w.]{1,20}))\b/');

define('IMAGE_HOST_BANNED', ['badhost.example.com']);
define('IMAGE_HOST_RECOMMENDED', ['goodhost.example.com']);

define('DONOR_RANK_PRICE', 10);
define('DONOR_FIRST_INVITE_COUNT', 2);

define('TAG_OFFICIAL_COLUMNS', 4);

define('RANKING_WEIGHT', [
    'uploaded'   => [ 8, 'DataUpload'],
    'downloaded' => [20, 'DataDownload'],
    'uploads'    => [25, 'Uploads'],
    'requests'   => [10, 'RequestsFilled'],
    'posts'      => [ 3, 'ForumPosts'],
    'bounty'     => [ 4, 'BountySpent'],
    'artists'    => [ 1, 'ArtistsAdded'],
    'collage'    => [ 5, 'CollageContribution'],
    'votes'      => [ 5, 'ReleaseVotes'],
    'bonus'      => [ 6, 'BonusPoints'],
    'comment-t'  => [18, 'CommentTorrent'],
]);
