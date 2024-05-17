<?php

/* This file defines all the configuration required to run a Gazelle
 * installation. Much can be left as-is in development. For production, a
 * number of keys will need to be changed. This is done in the
 * override.config.php file which must reside in the same directory as this
 * file. (You can also also create an override.php file for development/staging
 * environments if required).
 *
 * The declarations in this file must all follow the format:
 *
 *     defined('FOO') or define('FOO', 'bar');
 *
 * The override file then only need declare:
 *
 *     define('FOO', 'rat');
 *
 * to change to a site-specific value. These gymnastics are necessary
 * because a redefinition of a constant causes PHP to complain.
 *
 * Pro-tip: the development.config.php and production.config.php provide
 * the list of constants that will most likely need to appear in a
 * override.config.php file. The docker installation takes care of this
 * automatically.
 *
 * When adding a new value, consider whether the setting is appropriate for
 * production directly, or whether it is a secret and therefore needs to be
 * overridden. The less things that have to be overridden, the easier things
 * become.
 *
 * You are expected to read this file in its entirety before setting out.
 */

if (file_exists(__DIR__ . '/override.config.php')) {
    require_once(__DIR__ . '/override.config.php');
}

// ------------------------------------------------------------------------
// General settings. It is more than likely that you will need to change
// these in production.

// The name of your site!
defined('SITE_NAME') or define('SITE_NAME', 'Gazelle');

// The DNS name (A record) of your site.
defined('SITE_HOST') or define('SITE_HOST', 'localhost');

// The URL of the site. There is no capability to offer both SSL and
// non-SSL versions of the site in production. In the development
// environment you will need to override this to http (unless you
// want to set up your own CA).
defined('SITE_URL') or define('SITE_URL', 'https://' . SITE_HOST);

// The year the site was launched. This will be generated automatically
// in the Docker environment. You only need to copy it to the production
// override afterwards.
defined('SITE_LAUNCH_YEAR') or define('SITE_LAUNCH_YEAR', 2018);

// Is this the beta version of the site?
defined('BETA') or define('BETA', false);

// Path for storing temporary files (should be moved out of the root partition)
defined('TMPDIR') or define('TMPDIR', '/tmp');

// Paths for storing uploaded assets outside the database. See bin/generate-storage-dirs
// Should also be moved out of the root partition.
defined('STORAGE_PATH_TORRENT')    or define('STORAGE_PATH_TORRENT',    '/var/lib/gazelle/torrent');
defined('STORAGE_PATH_RIPLOG')     or define('STORAGE_PATH_RIPLOG',     '/var/lib/gazelle/riplog');
defined('STORAGE_PATH_RIPLOGHTML') or define('STORAGE_PATH_RIPLOGHTML', '/var/lib/gazelle/riploghtml');

// Host static assets (images, css, js) on another server.
// In development it is just a folder
defined('STATIC_SERVER') or define('STATIC_SERVER', 'static');

// Where is the repository physically stored (and hence where the document
// root lives). This is needed so that Gazelle knows where static assets are,
// in order to stat them for cache-busting via modification timestamps.
defined('SERVER_ROOT') or define('SERVER_ROOT', '/var/www');

// If you are running a beta version and you really need to know where the
// production files are, set the path here. The only reason that this is
// still needed is if you are storing riplogs in the document root and
// serving them directly. On a new installation this may be left as-is.
defined('SERVER_ROOT') or define('SERVER_ROOT', '/var/www');
defined('SERVER_ROOT_LIVE') or define('SERVER_ROOT_LIVE', SERVER_ROOT);

// System account used to generate Editing Requests.
defined('SYSTEM_USER_ID') or define('SYSTEM_USER_ID', 17);

// User with no privileges to a show a user what their paranoia looks like.
defined('PARANOIA_PREVIEW_USER') or define('PARANOIA_PREVIEW_USER', SYSTEM_USER_ID);

// Alternative hostname for site, such as for beta deploy (e.g. beta.example.com)
// This is important for BB code rendering to recognize our own urls as local,
// regardless of which one is used (e.g. pasted in a forum).
defined('ALT_SITE_HOST') or define('ALT_SITE_HOST', SITE_HOST);
defined('ALT_SITE_URL') or define('ALT_SITE_URL', SITE_URL);

// User to name on donate.php to direct donation inquiries to
defined('DONATION_MANAGER_USER_ID') or define('DONATION_MANAGER_USER_ID', 0);

// Monero donation address, must be a primary account address starting with 4...
// The user will receive a payment id-bound address based on this address.
defined('MONERO_DONATION_ADDRESS') or define ('MONERO_DONATION_ADDRESS', '');

// x/y/zpub to derive bitcoin donation addresses from
// this should be the *pub of your receive wallet key only, not your wallet master key!
// also see the documentation on Gazelle\Donate\Bitcoin::__construct for more info.
defined('BITCOIN_DONATION_XYZPUB') or define('BITCOIN_DONATION_XYZPUB', '');

// Top 10 history date. Previous days and weeks of Top 10 torrents are
// recorded in a task. There is not point going back prior to the start
// of recorded history.
defined('TOP_TEN_HISTORY_BEGIN') or define('TOP_TEN_HISTORY_BEGIN', '2023-06-23');

// The heading used for the Top 10 pages.
defined('TOP_TEN_HEADING') or define('TOP_TEN_HEADING', 'Top 10');

// ------------------------------------------------------------------------
// Secrets
// ALL OF THESE MUST BE OVERRIDDEN

// Symmetric encryption and auth key for cookies. Changing this after
// the site is launched will invalidate everyone's sessions and tokens.
// This may be what you need in the event of a compromise.
defined('ENCKEY') or define('ENCKEY', 'changeme');
defined('AUTHKEY') or define('AUTHKEY', 'changeme');

// Extra salt added into RSS authentication
defined('RSS_HASH') or define('RSS_HASH', 'changeme');

// Seedbox ids are hashified with this salt.
defined('SEEDBOX_SALT') or define('SEEDBOX_SALT', 'changeme');

// User avatars are hashed with this salt.
defined('AVATAR_SALT') or define('AVATAR_SALT', 'changeme');

// ------------------------------------------------------------------------
// MySQL settings

// None of these values need to be adjusted for the local Docker
// environment. It is assumed that all will be changed in production.

// Hostname of the mysql instance.
defined('SQLHOST') or define('SQLHOST', 'mysql');

// The TCP port to use.
defined('SQLPORT') or define('SQLPORT', 3306);

// The socket to use. See the php documentation on mysqli::connect
// to understand how these fit together. If the database and PHP interpreter
// are running on the same host then you want to use a socket.
defined('SQLSOCK') or define('SQLSOCK', null);

// The name of the database schema.
defined('SQLDB') or define('SQLDB', 'gazelle');

// The username of the website account. See the docs/01-MysqlRoles.txt
// document for details on what roles need to be configured.
defined('SQLLOGIN') or define('SQLLOGIN', 'gazelle');

// The password of the above account.
defined('SQLPASS') or define('SQLPASS', 'password');

// The username of the Phinx account (used for schema modifications).
// In production, this account will have a different set of grants compared
// to the website account (so that if the website account is compromised, it
// cannot be used to drop tables or any other malicious activities).
defined('SQL_PHINX_USER') or define('SQL_PHINX_USER', SQLLOGIN);

// Password of the above.
defined('SQL_PHINX_PASS') or define('SQL_PHINX_PASS', SQLPASS);

// ------------------------------------------------------------------------
// Postgresql settings

// Hostname of the Postgresql backend
defined('GZPG_HOST') or define('GZPG_HOST', 'pg');

// Default port of the backend.
// No need to change unless you're running on a non-standard port.
defined('GZPG_PORT') or define('GZPG_PORT', 5432);

// Name of the default database
defined('GZPG_DB') or define('GZPG_DB', 'gz');

// Role name used by the website (with limited privileges)
defined('GZPG_USER') or define('GZPG_USER', 'nyala');

// Password of the above
defined('GZPG_PASSWORD') or define('GZPG_PASSWORD', 'nyalapw');

// The DSN of the connection
defined('GZPG_DSN') or define('GZPG_DSN',
    'pgsql:host='      . GZPG_HOST
        . ';port='     . GZPG_PORT
        . ';dbname='   . GZPG_DB
        . ';user='     . GZPG_USER
        . ';password=' . GZPG_PASSWORD
);

// ------------------------------------------------------------------------
// Sphinx settings

// Hostname of the Sphinx fulltext search engine. The hostnames
// will probably need to be changed in production but the default
// port numbers will be fine.
defined('SPHINX_HOST') or define('SPHINX_HOST', 'sphinxsearch');

// Default port of the above.
defined('SPHINX_PORT') or define('SPHINX_PORT', 9312);

// Hostname of the Sphinx query endpoint.
defined('SPHINXQL_HOST') or define('SPHINXQL_HOST', SPHINX_HOST);

// Default port of the above.
defined('SPHINXQL_PORT') or define('SPHINXQL_PORT', 9306);

// Socket path, if communicating over localhost.
defined('SPHINXQL_SOCK') or define('SPHINXQL_SOCK', false);

// The maximum match count must not exceed the Sphinx configuration
// (Default is 1000).
defined('SPHINX_MAX_MATCHES') or define('SPHINX_MAX_MATCHES', 1000);

// ------------------------------------------------------------------------
// Ocelot settings

// If you have set Ocelot up in the development environment then this
// should be overridden and set to true.
defined('DISABLE_TRACKER') or define('DISABLE_TRACKER', false);

// This is the name of the docker Ocelot container
// In production this will be the IP address of the Ocelot instance on your internal network
defined('TRACKER_HOST') or define('TRACKER_HOST', 'ocelot');

// TCP port of the Ocelot instance
// Must match the Ocelot configuration
defined('TRACKER_PORT') or define('TRACKER_PORT', 34000);

// Public-facing hostname of the Ocelot instance
defined('TRACKER_NAME') or define('TRACKER_NAME', '127.0.0.1' . ":" . TRACKER_PORT);

// Shared secret that is compiled into Ocelot (see config.cpp). Must
// be exactly 32 alphanumeric characters.
defined('TRACKER_SECRET') or define('TRACKER_SECRET', '00000000000000000000000000000000');

// Second shared secret that is compiled into Ocelot (see config.cpp). Must
// be exactly 32 alphanumeric characters.
defined('TRACKER_REPORTKEY') or define('TRACKER_REPORTKEY', '00000000000000000000000000000000');

// Announce URLs for users. Some clients cannot do HTTPS, and some people
// persist in using these clients, which is a great pity.
defined('ANNOUNCE_HTTP_URL') or define('ANNOUNCE_HTTP_URL', 'http://' . TRACKER_NAME);
defined('ANNOUNCE_HTTPS_URL') or define('ANNOUNCE_HTTPS_URL', 'https://' . TRACKER_NAME);

// Define this to be a file (writable by the web server user) to trace the
// expiry of freeleech tokens by Ocelot. Note that if this is set in
// lib/override.config.php, the default TMP symbol has not yet been defined.
defined('DEBUG_TRACKER_TOKEN_EXPIRE') or define('DEBUG_TRACKER_TOKEN_EXPIRE', false);

// ------------------------------------------------------------------------
// Memcached settings

// On production, you probably want the memcached instance to be sitting on
// the same host as the PHP interpreter. In which case you may leave this as-is.
defined('MEMCACHE_HOST_LIST') or define('MEMCACHE_HOST_LIST', [
    ['host' => 'unix:///var/run/memcached.sock', 'port' => 0, 'buckets' => 1],
]);

// Memcached prefix (if ever you need to run another namespace in the same
// memcached instance).
defined('CACHE_ID') or define('CACHE_ID', 'ops');

// ------------------------------------------------------------------------
// Executable (binaries) settings
//
// adjust paths as appropriate

defined('BIN_COMPOSER') or define('BIN_COMPOSER', '/usr/local/bin/composer');
defined('BIN_GIT')      or define('BIN_GIT', '/usr/bin/git');
defined('BIN_PHINX')    or define('BIN_PHINX', realpath(__DIR__ . '/../vendor/bin/phinx'));
defined('PHINX_MYSQL')  or define('PHINX_MYSQL', realpath(__DIR__ . '/../phinx.php'));

// ------------------------------------------------------------------------
// Local image caching settings

// Is local image caching enabled?
defined('IMAGE_CACHE_ENABLED') or define('IMAGE_CACHE_ENABLED', false);

// When local image caching is enabled, this is used to define the cache names of assets
defined('IMAGE_CACHE_SECRET') or define('IMAGE_CACHE_SECRET', 'changeme');

// When local image caching is enabled, this hostname is used.
defined('IMAGE_CACHE_HOST') or define('IMAGE_CACHE_HOST', SITE_URL);

// ------------------------------------------------------------------------
// Email settings

// The DNS name (MX record) of your email hostname.
defined('MAIL_HOST') or define('MAIL_HOST', 'mail.' . SITE_HOST);

// Set to true in a development environment. Instead of delivery, messages
// will be written to files in TMPDIR.
defined('DEBUG_EMAIL') or define('DEBUG_EMAIL', false);

// When you need to verify what arguments are in the request
// Comes in handy for dynamic urls and DDoS attacks
defined('DEBUG_URI') or define('DEBUG_URI', '/thisifine/');
defined('DEBUG_TRACE') or define('DEBUG_TRACE', '');

// ------------------------------------------------------------------------
// IRC settings

defined('DISABLE_IRC')             or define('DISABLE_IRC', false);
defined('IRC_HOSTNAME')            or define('IRC_HOSTNAME', 'irc.' . SITE_HOST);
defined('IRC_PORT')                or define('IRC_PORT', 7000);
defined('IRC_PORT_CLEARTEXT')      or define('IRC_PORT_CLEARTEXT', 6669);
defined('IRC_BOT')                 or define('IRC_BOT', 'Rippy');
defined('IRC_HTTP_SOCKET_ADDRESS') or define('IRC_HTTP_SOCKET_ADDRESS', 'http://localhost:51011/');

// Main channel for registered users
defined('IRC_CHAN') or define('IRC_CHAN', '#mygazelle');

// Channel announcing new uploads
defined('IRC_CHAN_ANNOUNCE') or define('IRC_CHAN_ANNOUNCE', '#announce');

// Channel announcing new requests
defined('IRC_CHAN_REQUEST') or define('IRC_CHAN_REQUEST', '#requests');

// Public channel for inactive users, login problems and similar
defined('IRC_CHAN_BLOCKED') or define('IRC_CHAN_BLOCKED', '#blocked');

// Channel for general messages to staff
defined('IRC_CHAN_STAFF') or define('IRC_CHAN_STAFF', '#staff');

// Channel for general messages to moderators (urgent reports)
defined('IRC_CHAN_MOD') or define('IRC_CHAN_MOD', '#staff');

// Channel for messages that require attention on the backend (low-traffic)
defined('IRC_CHAN_DEV') or define('IRC_CHAN_DEV', '#gazelle-lab');

// Channel for messages of unusual user activity (potentially high-traffic)
defined('IRC_CHAN_STATUS ') or define('IRC_CHAN_STATUS', '#gazelle-status');

// ------------------------------------------------------------------------
// Push server settings

defined('PUSH_SOCKET_LISTEN_ADDRESS') or define('PUSH_SOCKET_LISTEN_ADDRESS', false);
defined('PUSH_SOCKET_LISTEN_PORT') or define('PUSH_SOCKET_LISTEN_PORT', 6789);

// ------------------------------------------------------------------------
// Site settings

// Leaving these as is will work, but you will probably want to change them
// either in development or production, or both. Any defines that are likely
// to require a change will be listed in the example override files.
// When in doubt, read the source.

// Display the site logo on the public pages.
defined('SHOW_LOGO') or define('SHOW_LOGO', true);

// How many enabled users are allowed? (Set to 0 for unlimited).
defined('USER_LIMIT') or define('USER_LIMIT', 5000);

// How many days of inactivity (not logging in) before an account is deactivated?
// NB: If you do not want to deactivate stale accounts, disable the inactive user tasks
defined('INACTIVE_USER_DEACTIVATE_DAYS') or define('INACTIVE_USER_DEACTIVATE_DAYS', 120);

// How many days before sending an email to warn of pending deactivation?
defined('INACTIVE_USER_WARN_DAYS') or define('INACTIVE_USER_WARN_DAYS', INACTIVE_USER_DEACTIVATE_DAYS - 7);

// Set to false if you want to display the login form directly.
defined('SHOW_PUBLIC_INDEX') or define('SHOW_PUBLIC_INDEX', true);

// Can J. Random User create their own account?
defined('OPEN_REGISTRATION') or define('OPEN_REGISTRATION', false);

// Can inactive users enable themselves automatically?
defined('FEATURE_EMAIL_REENABLE') or define('FEATURE_EMAIL_REENABLE', false);

// Refuse connections from Tor exit nodes?
defined('BLOCK_TOR') or define('BLOCK_TOR', true);

// Connect to remote resources with curl via a proxy
defined('HTTP_PROXY') or define('HTTP_PROXY', false);

// Block Opera Mini proxy?
defined('BLOCK_OPERA_MINI') or define('BLOCK_OPERA_MINI', true);

// Should PHP errors be shown in the output?
defined('DEBUG_MODE') or define('DEBUG_MODE', false);

// Can developer+ see PHP warnings in the site footer?
defined('DEBUG_WARNINGS') or define('DEBUG_WARNINGS', true);

// Do upload notifications need to be traced? (Results written to TMPDIR)
defined('DEBUG_UPLOAD_NOTIFICATION') or define('DEBUG_UPLOAD_NOTIFICATION', false);

// Do contest payouts need to be tested?
// Results are always written to TMPDIR/payout-contest-<id>.txt
// If true, no PMs sent to users, no db updates performed.
defined('DEBUG_CONTEST_PAYOUT') or define('DEBUG_CONTEST_PAYOUT', false);

// if false, no attempt will be made to contact the last.fm website.
defined('LASTFM_API_KEY') or define('LASTFM_API_KEY', false);

// Fake useragent (to override default cURL useragent string).
defined('FAKE_USERAGENT') or define('FAKE_USERAGENT', 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.1 Safari/537.11');

// How much upload buffer to members start out with? (3 GiB)
defined('STARTING_UPLOAD') or define('STARTING_UPLOAD', 3 * 1024 * 1024 * 1024);

// Can freeleech (FL) tokens be stacked?
defined('STACKABLE_FREELEECH_TOKENS') or define('STACKABLE_FREELEECH_TOKENS', true);

// What size does a token represent? (512 MiB)
defined('BYTES_PER_FREELEECH_TOKEN') or define('BYTES_PER_FREELEECH_TOKEN', 512 * 1024 * 1024);

// How long does an activated token last before it is purged?
defined('FREELEECH_TOKEN_EXPIRY_DAYS') or define('FREELEECH_TOKEN_EXPIRY_DAYS', 30);

// When setting torrent groups or collages freeleech, "large" torrents
// can be set to neutral leech, to avoid tanking the economy.
// Scale of the neutral leech threshold (k, m, g for KiB, MiB and TiB, respectively)
defined('NEUTRAL_LEECH_UNIT') or define('NEUTRAL_LEECH_UNIT', 'm');

// Size of the the neutral leech threshold (in terms of the units specified above.
defined('NEUTRAL_LEECH_THRESHOLD') or define('NEUTRAL_LEECH_THRESHOLD', 1024);

// How much buffer does a request vote represent?
defined('REQUEST_MIN') or define('REQUEST_MIN', 100); // in MiB

// How much request buffer is removed as tax? (0 = none, 0.1 = 10%, 0.25 = 25% and so on).
defined('REQUEST_TAX') or define('REQUEST_TAX', 0.0);

// Retain this many days of hourly snapshots.
defined('DELETE_USER_STATS_DAILY_DAY') or define('DELETE_USER_STATS_DAILY_DAY', 15);

// Retain this many months of daily snapshots.
defined('DELETE_USER_STATS_MONTHLY_DAY') or define('DELETE_USER_STATS_MONTHLY_DAY', 120);

// How many invites do new users receive?
defined('STARTING_INVITES') or define('STARTING_INVITES', 0);

// How many invites does a Donor receive?
defined('DONOR_INVITES') or define('DONOR_INVITES', 2);

// How much fiat currency is required to acquire a donor point
defined('DONOR_RANK_PRICE') or define('DONOR_RANK_PRICE', 10);

// Minimum permission.Level allowed to purchase invites.
defined('MIN_INVITE_CLASS') or define('MIN_INVITE_CLASS', 150);

// Lowest permissions.Level of staff user classes
defined('STAFF_LEVEL') or define('STAFF_LEVEL', 820);

// Threshold for detecting duplicate IP addresses.
defined('IP_OVERLAPS') or define('IP_OVERLAPS', 5);

// Maximum length of a pathname in torrent files and zip archives.
defined('MAX_PATH_LEN') or define('MAX_PATH_LEN', 200);

// How many collages to list on a group page when it appears in many collages.
defined('COLLAGE_SAMPLE_THRESHOLD') or define('COLLAGE_SAMPLE_THRESHOLD', 4);

// How many personal collages to list on a group page when it appears in many collages.
defined('PERSONAL_COLLAGE_SAMPLE_THRESHOLD') or define('PERSONAL_COLLAGE_SAMPLE_THRESHOLD', 4);

// Number of groups an artist must have to be selected at random.
defined('RANDOM_ARTIST_MIN_ENTRIES') or define('RANDOM_ARTIST_MIN_ENTRIES', 3);

// Number of entries a collage must have to be selected at random.
defined('RANDOM_COLLAGE_MIN_ENTRIES') or define('RANDOM_COLLAGE_MIN_ENTRIES', 5);

// Number of seeds a torrent must have to be selected at random.
defined('RANDOM_TORRENT_MIN_SEEDS') or define('RANDOM_TORRENT_MIN_SEEDS', 3);

// How many torrents can a user delete before they must take a break?
defined('USER_TORRENT_DELETE_MAX') or define('USER_TORRENT_DELETE_MAX', 3);

// How long a break must a user take if they delete too many torrents?
defined('USER_TORRENT_DELETE_HOURS') or define('USER_TORRENT_DELETE_HOURS', 24);

// How many seconds should elapse before alerting that the scheduler is not running?
// Hint: check the crond service
defined('SCHEDULER_DELAY') or define('SCHEDULER_DELAY', 450);

// How long before a stale announce is drained and a user is not longer considered
// to be seeding a torrent
defined('UNSEEDED_DRAIN_INTERVAL') or define('UNSEEDED_DRAIN_INTERVAL', 3600 * 6);

// Which image hosts are acceptable and which aren't?
defined('IMAGE_HOST_BANNED') or define('IMAGE_HOST_BANNED', []);
defined('IMAGE_HOST_RECOMMENDED') or define('IMAGE_HOST_RECOMMENDED', []);

// What are the relative weights of user percentiles, in order to calculate
// the overall percentile rank.
defined('RANKING_WEIGHT') or define('RANKING_WEIGHT', [
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

// Successive login failures generate increasing delays.
defined('LOGIN_ATTEMPT_BACKOFF') or define('LOGIN_ATTEMPT_BACKOFF', [
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

// Set to TagID of 'test' to ignore uploads tagged with 'test' for the
// recent uploads widget.
defined('HOMEPAGE_TAG_IGNORE') or define('HOMEPAGE_TAG_IGNORE', [0]);

// Do not report these pages for excessive memory consumption
defined('IGNORE_PAGE_MAX_MEMORY') or define('IGNORE_PAGE_MAX_MEMORY', ['artist', 'bookmark']);

// Do not report these pages for being slow
defined('IGNORE_PAGE_MAX_TIME') or define('IGNORE_PAGE_MAX_TIME', ['top10']);

// How many days to wait until a never active torrent can be requested for reseed
defined('RESEED_NEVER_ACTIVE_TORRENT') or define('RESEED_NEVER_ACTIVE_TORRENT', 3);

// How many days to wait until a previously active torrent can be requested for reseed
defined('RESEED_TORRENT') or define('RESEED_TORRENT', 14);

// ------------------------------------------------------------------------
// Inactivity reaper settings
//
// There are two types of reaping: uploads that are never announced by
// the initial uploader (never seeded), and uploads that were seeded for
// for a while and since then the size of the swarm has dropped to zero
// (unseeded). To eliminate reaping completely, the corresponding
// scheduled tasks should be disabled.
// It is easier to specify the first and second unseeded notifications
// in terms of the interval remaining until reaping.

defined('MAX_NEVER_SEEDED_PER_RUN')         or define('MAX_NEVER_SEEDED_PER_RUN', 4000);
defined('REMOVE_NEVER_SEEDED_HOUR')         or define('REMOVE_NEVER_SEEDED_HOUR',   72);
defined('NOTIFY_NEVER_SEEDED_INITIAL_HOUR') or define('NOTIFY_NEVER_SEEDED_INITIAL_HOUR', 8); // 8 hours after upload
defined('NOTIFY_NEVER_SEEDED_FINAL_HOUR')   or define('NOTIFY_NEVER_SEEDED_FINAL_HOUR', REMOVE_NEVER_SEEDED_HOUR - 24);

defined('MAX_UNSEEDED_PER_RUN')         or define('MAX_UNSEEDED_PER_RUN', 4000);
defined('REMOVE_UNSEEDED_HOUR')         or define('REMOVE_UNSEEDED_HOUR', 24 * 30); // 30 days
defined('NOTIFY_UNSEEDED_INITIAL_HOUR') or define('NOTIFY_UNSEEDED_INITIAL_HOUR', REMOVE_UNSEEDED_HOUR - (24 * 10)); // 10 days before
defined('NOTIFY_UNSEEDED_FINAL_HOUR')   or define('NOTIFY_UNSEEDED_FINAL_HOUR',   REMOVE_UNSEEDED_HOUR - (24 *  3)); // 3 days before

// There is a single task that handles the various phases of reaping.
// In normal operations you want to perform all of the phases, but in certain
// circumstances you might wish to suspend one phase or another.
// If you think you need to suspend all the phases, disable the task in the
// scheduler instead.

// Award winners who have begun to seed unseeded uploads
defined('REAPER_TASK_CLAIM') or define('REAPER_TASK_CLAIM', true);

// Look for new never seeded or unseeded uploads to process
defined('REAPER_TASK_NOTIFY') or define('REAPER_TASK_NOTIFY', true);

// Reap any expired unseeded torrents
defined('REAPER_TASK_REMOVE_UNSEEDED') or define('REAPER_TASK_REMOVE_UNSEEDED', true);

// Reap any expired never seeded torrents
defined('REAPER_TASK_REMOVE_NEVER_SEEDED') or define('REAPER_TASK_REMOVE_NEVER_SEEDED', true);

// How many notifications can a user receive in a single message
defined('NOTIFY_REAPER_MAX_PER_USER') or define('NOTIFY_REAPER_MAX_PER_USER', 200);

// How many users will be notified in a single run?
defined('NOTIFY_REAPER_MAX_NOTIFICATION') or define('NOTIFY_REAPER_MAX_NOTIFICATION', 2500);

// How much is the BP reward scaled up for a reseed?
defined('REAPER_RESEED_REWARD_FACTOR') or define('REAPER_RESEED_REWARD_FACTOR', 1.25);

// ------------------------------------------------------------------------
// Source flag settings

// Source flag for torrent files. Should be unique across the wider tracker
// space to enable easy cross-seeding.
defined('SOURCE') or define('SOURCE', 'OPS');

// Acceptable source flag from a prior site.
defined('GRANDFATHER_SOURCE') or define('GRANDFATHER_SOURCE', '');

// Epoch time of cut-off for accepting grandfathered source flags.
defined('GRANDFATHER_OLD_SOURCE') or define('GRANDFATHER_OLD_SOURCE', 0);

// Epoch time of cut-off for accepting torrents with no source flags (useful if you
// introduce source flags after launch).
defined('GRANDFATHER_NO_SOURCE') or define('GRANDFATHER_NO_SOURCE', 0);

// ------------------------------------------------------------------------
// Bonus point settings

// Points awarded for uploads.
defined('BONUS_AWARD_FLAC_PERFECT') or define('BONUS_AWARD_FLAC_PERFECT', 400);
defined('BONUS_AWARD_FLAC')         or define('BONUS_AWARD_FLAC', 30);
defined('BONUS_AWARD_MP3')          or define('BONUS_AWARD_MP3', 30);
defined('BONUS_AWARD_OTHER')        or define('BONUS_AWARD_OTHER', 10);

// Tax when donating to bonus pools.
defined('BONUS_POOL_TAX_STD')   or define('BONUS_POOL_TAX_STD', 0.9);
defined('BONUS_POOL_TAX_ELITE') or define('BONUS_POOL_TAX_ELITE', 0.8);
defined('BONUS_POOL_TAX_TM')    or define('BONUS_POOL_TAX_TM', 0.7);
defined('BONUS_POOL_TAX_STAFF') or define('BONUS_POOL_TAX_STAFF', 0.5);

// ------------------------------------------------------------------------
// Pagination

defined('BOOKMARKS_PER_PAGE')        or define('BOOKMARKS_PER_PAGE', 20);
defined('COLLAGES_PER_PAGE')         or define('COLLAGES_PER_PAGE', 25);
defined('CONTEST_ENTRIES_PER_PAGE')  or define('CONTEST_ENTRIES_PER_PAGE', 50);
defined('FRIENDS_PER_PAGE')          or define('FRIENDS_PER_PAGE', 20);
defined('INVITES_PER_PAGE')          or define('INVITES_PER_PAGE', 50);
defined('ITEMS_PER_PAGE')            or define('ITEMS_PER_PAGE', 50);
defined('IPS_PER_PAGE')              or define('IPS_PER_PAGE', 50);
defined('LOG_ENTRIES_PER_PAGE')      or define('LOG_ENTRIES_PER_PAGE', 50);
defined('MESSAGES_PER_PAGE')         or define('MESSAGES_PER_PAGE', 25);
defined('PEERS_PER_PAGE')            or define('PEERS_PER_PAGE', 100);
defined('POSTS_PER_PAGE')            or define('POSTS_PER_PAGE', 25);
defined('REPORTS_PER_PAGE')          or define('REPORTS_PER_PAGE', 10);
defined('REQUESTS_PER_PAGE')         or define('REQUESTS_PER_PAGE', 25);
defined('TOPICS_PER_PAGE')           or define('TOPICS_PER_PAGE', 50);
defined('TORRENTS_PER_PAGE')         or define('TORRENTS_PER_PAGE', 50);
defined('TORRENT_COMMENTS_PER_PAGE') or define('TORRENT_COMMENTS_PER_PAGE', 10);
defined('USERS_PER_PAGE')            or define('USERS_PER_PAGE', 50);
defined('WIKI_ARTICLES_PER_PAGE')    or define('WIKI_ARTICLES_PER_PAGE', 25);

defined('AJAX_USERS_PER_PAGE') or define('AJAX_USERS_PER_PAGE', 30);

// ------------------------------------------------------------------------
// User Referral settings

// Can people from approved trackers create their own account?
defined('OPEN_EXTERNAL_REFERRALS') or define('OPEN_EXTERNAL_REFERRALS', false);

// Where to forward cURL queries for referrals (URL with trailiing slash).
defined('REFERRAL_BOUNCER') or define('REFERRAL_BOUNCER', '');

// Symmetric encryption key for bouncer communication.
defined('REFERRAL_KEY') or define('REFERRAL_KEY', hash('sha512', 'sekret'));

// Should referrals send invitation emails.
defined('REFERRAL_SEND_EMAIL') or define('REFERRAL_SEND_EMAIL', false);

// Code names of referral sites (must match the db).
defined('REFERRAL_SITES') or define('REFERRAL_SITES', ['ABC', 'DEF']);

// ------------------------------------------------------------------------
// Recovery settings

// Is recovery open?
defined('RECOVERY') or define('RECOVERY', false);

// Is buffer from the previous site restituted to recovered users?
defined('RECOVERY_BUFFER') or define('RECOVERY_BUFFER', false);

// Random salt for generating temporary filenames
defined('RECOVERY_SALT') or define('RECOVERY_SALT', 'changeme');

// Where are uploads for recovery proof stored?
defined('RECOVERY_PATH') or define('RECOVERY_PATH', '');

// Highest torrent id from a previous incarnation
defined('MAX_PREV_TORRENT_ID') or define('MAX_PREV_TORRENT_ID', 0);

// In which database schema are the users of the previous site stored?
defined('RECOVERY_DB') or define('RECOVERY_DB', '');

// Which table stores the old/new user mappings?
defined('RECOVERY_MAPPING_TABLE') or define('RECOVERY_MAPPING_TABLE', 'users_' . RECOVERY_DB . '_mapping');

// Which table stores proof about prior IRC users?
defined('RECOVERY_IRC_TABLE') or define('RECOVERY_IRC_TABLE', 'users_' . RECOVERY_DB . '_irc');

// Are recovery requests that validate correctly automatically accepted?
defined('RECOVERY_AUTOVALIDATE') or define('RECOVERY_AUTOVALIDATE', true);

// How many validations are performed per scheduled run?
defined('RECOVERY_AUTOVALIDATE_LIMIT') or define('RECOVERY_AUTOVALIDATE_LIMIT', 20);

// Which user issues recovery invites?
defined('RECOVERY_ADMIN_ID') or define('RECOVERY_ADMIN_ID', SYSTEM_USER_ID);

// What name is given to the recovery inviter?
defined('RECOVERY_ADMIN_NAME') or define('RECOVERY_ADMIN_NAME', 'RecoveryBot');

// How many pending people can be reassigned buffer in a single run?
defined('RECOVERY_BUFFER_REASSIGN_LIMIT') or define('RECOVERY_BUFFER_REASSIGN_LIMIT', 100);

// Security check to prevent <form> stuffing
defined('RECOVERY_PAIR_SALT') or define('RECOVERY_PAIR_SALT', 'changeme');

// ------------------------------------------------------------------------
// Permission.ID labels

// Permission ID of primary class.
defined('USER') or define('USER',                      2);
defined('MEMBER') or define('MEMBER',                  3);
defined('POWER') or define('POWER',                    4);
defined('ELITE') or define('ELITE',                    5);
defined('TORRENT_MASTER') or define('TORRENT_MASTER', 25);
defined('POWER_TM') or define('POWER_TM',             29);
defined('ELITE_TM') or define('ELITE_TM',             28);
defined('ULTIMATE_TM') or define('ULTIMATE_TM',       48);
defined('FORUM_MOD') or define('FORUM_MOD',           21);
defined('MOD') or define('MOD',                       11);
defined('SYSOP') or define('SYSOP',                   15);

// Permission ID of secondary class.
defined('DONOR') or define('DONOR',             42);
defined('FLS_TEAM') or define('FLS_TEAM',       23);
defined('INTERVIEWER') or define('INTERVIEWER', 30);
defined('RECRUITER') or define('RECRUITER',     41);
defined('VIP') or define('VIP',                 26);

// ------------------------------------------------------------------------
// Well-known forum settings

// The secret Donor forum.
defined('DONOR_FORUM') or define('DONOR_FORUM', 70);

// Where Announcements are sent.
defined('ANNOUNCEMENT_FORUM_ID') or define('ANNOUNCEMENT_FORUM_ID', 12);

// Where Edit Requests are sent.
defined('EDITING_FORUM_ID') or define('EDITING_FORUM_ID', 34);

// Staff forum (for polls that display the names of voters).
defined('STAFF_FORUM_ID') or define('STAFF_FORUM_ID', 5);

// Forums where voter names are revealed.
defined('FORUM_REVEAL_VOTE') or define('FORUM_REVEAL_VOTE', []);

// Where trashed threads go.
defined('TRASH_FORUM_ID') or define('TRASH_FORUM_ID', 4);

// The Album of the Month (AotM) forum. This is the Announcement forum
// by default, but you might want to have a dedicated forum for this.
defined('AOTM_FORUM_ID') or define('AOTM_FORUM_ID', 12);

// The Showcase (Vanity House) forum.
defined('SHOWCASE_FORUM_ID') or define('SHOWCASE_FORUM_ID', 18);

// The client whitelist suggestion forum.
defined('CLIENT_WHITELIST_FORUM_ID') or define('CLIENT_WHITELIST_FORUM_ID', 680);

// The invitations forum.
defined('INVITATION_FORUM_ID') or define('INVITATION_FORUM_ID', 39);

// Number of thread posts per cache key.
defined('THREAD_CATALOGUE') or define('THREAD_CATALOGUE', 500);

// ------------------------------------------------------------------------
// Well-known Wiki pages

// The index page (to prevent it from ever being deleted).
defined('INDEX_WIKI_PAGE_ID') or define('INDEX_WIKI_PAGE_ID', 1);

// The rules page.
defined('RULES_WIKI_PAGE_ID') or define('RULES_WIKI_PAGE_ID', 127);

// Information about Source Flags.
defined('SOURCE_FLAG_WIKI_PAGE_ID') or define('SOURCE_FLAG_WIKI_PAGE_ID', 113);

// ------------------------------------------------------------------------
// None of the settings below here should need to be changed. They are here
// simply to not have them in the code, either because they are magic
// numbers or they are used in more than place. Any changes here will most
// likely require a corresonding change in the code or stylesheets.
// ------------------------------------------------------------------------

// Maximum length of a custom user title.
defined('USER_TITLE_LENGTH') or define('USER_TITLE_LENGTH', 1024);

// Default image for user avatars.
defined('USER_DEFAULT_AVATAR') or define('USER_DEFAULT_AVATAR', STATIC_SERVER . '/common/avatars/default.png');

// Magic constant of locked accounts.
defined('STAFF_LOCKED') or define('STAFF_LOCKED', 1);

// Width of avatars (as displayed in forums and profile page).
defined('AVATAR_WIDTH') or define('AVATAR_WIDTH', 150);

// Width of similar artists map.
defined('SIMILAR_WIDTH') or define('SIMILAR_WIDTH', 720);

// Height of similar artists map.
defined('SIMILAR_HEIGHT') or define('SIMILAR_HEIGHT', 500);

// Number of columns in the official tags toolbox
defined('TAG_OFFICIAL_COLUMNS') or define('TAG_OFFICIAL_COLUMNS', 4);

// The files of a torrent are squished into a single field in the database,
// delimited by this character
defined('FILELIST_DELIM') or define('FILELIST_DELIM', "\xC3\xB7");

// ------------------------------------------------------------------------
// Upload configuration

// Upload categories.
defined('CATEGORY') or define('CATEGORY', [
    'Music',
    'Applications',
    'E-Books',
    'Audiobooks',
    'E-Learning Videos',
    'Comedy',
    'Comics'
]);
defined('CATEGORY_GROUPED') or define('CATEGORY_GROUPED', array_intersect(['Music'], CATEGORY));
defined('CATEGORY_EBOOK') or define('CATEGORY_EBOOK', 3);
defined('CATEGORY_MUSIC') or define('CATEGORY_MUSIC', 1);

// Icons of upload categories.
defined('CATEGORY_ICON') or define('CATEGORY_ICON', [
    'music.png',
    'apps.png',
    'ebook.png',
    'audiobook.png',
    'elearning.png',
    'comedy.png',
    'comics.png'
]);

// Allowed upload encodings.
defined('ENCODING') or define('ENCODING', [
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

// Allowed upload formats.
defined('FORMAT') or define('FORMAT', [
    'MP3',
    'FLAC',
    'Ogg Vorbis',
    'AAC',
    'AC3',
    'DTS'
]);

// Allowed upload media.
defined('MEDIA') or define('MEDIA', [
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

// For audio uploads, files with these names will not be decoded
defined('IGNORE_AUDIO_LOGFILE') or define('IGNORE_AUDIO_LOGFILE', ['audiochecker.log', 'sox.log']);

// ------------------------------------------------------------------------
// Paranoia

// Magic constants for paranoia.
defined('PARANOIA_HIDE')       or define('PARANOIA_HIDE', 0);
defined('PARANOIA_ALLOWED')    or define('PARANOIA_ALLOWED', 1);
defined('PARANOIA_OVERRIDDEN') or define('PARANOIA_OVERRIDDEN', 2);

// What permissions allow a viewer to override a user's paranoia?
defined('PARANOIA_OVERRIDE') or define('PARANOIA_OVERRIDE', [
    'downloaded'       => 'users_mod',
    'uploaded'         => 'users_mod',
    'lastseen'         => 'view_last_seen',
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

// ------------------------------------------------------------------------
// Artist configuration

defined('ARTIST_MAIN')      or define('ARTIST_MAIN',      1);
defined('ARTIST_GUEST')     or define('ARTIST_GUEST',     2);
defined('ARTIST_REMIXER')   or define('ARTIST_REMIXER',   3);
defined('ARTIST_COMPOSER')  or define('ARTIST_COMPOSER',  4);
defined('ARTIST_CONDUCTOR') or define('ARTIST_CONDUCTOR', 5);
defined('ARTIST_DJ')        or define('ARTIST_DJ',        6);
defined('ARTIST_PRODUCER')  or define('ARTIST_PRODUCER',  7);
defined('ARTIST_ARRANGER')  or define('ARTIST_ARRANGER',  8);
defined('ARTIST_TYPE')      or define('ARTIST_TYPE', [
    ARTIST_MAIN      => 'Main',
    ARTIST_GUEST     => 'Guest',
    ARTIST_REMIXER   => 'Remixer',
    ARTIST_COMPOSER  => 'Composer',
    ARTIST_CONDUCTOR => 'Conductor',
    ARTIST_DJ        => 'DJ/Compiler',
    ARTIST_PRODUCER  => 'Producer',
    ARTIST_ARRANGER  => 'Arranger',
]);
defined('ARTIST_SECTION_ARRANGER') or define('ARTIST_SECTION_ARRANGER', 1020);
defined('ARTIST_SECTION_PRODUCER') or define('ARTIST_SECTION_PRODUCER', 1021);
defined('ARTIST_SECTION_COMPOSER') or define('ARTIST_SECTION_COMPOSER', 1022);
defined('ARTIST_SECTION_REMIXER') or define('ARTIST_SECTION_REMIXER', 1023);
defined('ARTIST_SECTION_GUEST') or define('ARTIST_SECTION_GUEST', 1024);

// ------------------------------------------------------------------------
// Collage configuration

defined('COLLAGE') or define('COLLAGE', [
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
defined('COLLAGE_PERSONAL_ID') or define('COLLAGE_PERSONAL_ID', 0);
defined('COLLAGE_ARTISTS_ID') or define('COLLAGE_ARTISTS_ID', 7);

// ------------------------------------------------------------------------
// Donor configuration. Any changes here will need to be reflected in the
// wiki documentation.

defined('RANK_ONE_COST')    or define('RANK_ONE_COST', 5);
defined('RANK_TWO_COST')    or define('RANK_TWO_COST', 10);
defined('RANK_THREE_COST')  or define('RANK_THREE_COST', 15);
defined('RANK_FOUR_COST')   or define('RANK_FOUR_COST', 20);
defined('RANK_FIVE_COST')   or define('RANK_FIVE_COST', 30);
defined('MAX_RANK')         or define('MAX_RANK', 6);
defined('MAX_EXTRA_RANK')   or define('MAX_EXTRA_RANK', 8);
defined('DONOR_FORUM_RANK') or define('DONOR_FORUM_RANK', 6);
defined('MAX_SPECIAL_RANK') or define('MAX_SPECIAL_RANK', 3);

// ------------------------------------------------------------------------
// Cache settings

// Grant cache access to specific keys based on site permission
defined('CACHE_PERMISSION') or define('CACHE_PERMISSION', [
    'api_apps'  => 'site_debug',
    'catalogue' => 'site_debug',
]);

defined('CACHE_BULK_FLUSH') or define('CACHE_BULK_FLUSH', 500);

defined('CACHE_RESPONSE') or define('CACHE_RESPONSE', [
     0 => 'success',
     1 => 'failure/delete ok',
    16 => 'not found',
]);

defined('CACHE_DB') or define('CACHE_DB', [
    'artist'        => ['table' => 'artists_group',  'pk' => 'ArtistID'],
    'collage'       => ['table' => 'collages',       'pk' => 'ID'],
    'torrent'       => ['table' => 'torrents',       'pk' => 'ID'],
    'torrent-group' => ['table' => 'torrents_group', 'pk' => 'ID'],
    'user'          => ['table' => 'users_main',     'pk' => 'ID'],
]);

defined('CACHE_NAMESPACE') or define('CACHE_NAMESPACE', [
    'artist' => [
        'a1' => 'artist_%d',
        'a2' => 'artist_comments_%d',
        'a3' => 'artist_comments_%d_catalogue_0',
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
        'g5' => 'torrent_group_%d',
        'g6' => 'torrent_group_light_%d',
        'g7' => 'groups_artists_%d',
        'g8' => 'tg2_%d',
        'g9' => 'tlist_%d',
    ],
    'torrent' => [
        't1' => 't3_%d',
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

// ------------------------------------------------------------------------
// Common regexp patterns

defined('IP_REGEXP')       or define('IP_REGEXP',       '/\b(?:\d{1,3}\.){3}\d{1,3}\b/');
defined('URL_REGEXP_STEM') or define('URL_REGEXP_STEM', '((?:f|ht)tps?:\/\/(?:' . str_replace('/', '', IP_REGEXP) . '|localhost|(?:[\w-]+(?:\.[\w-]+)+))(?::\d{1,5})?(?:\/\S*))');
defined('URL_REGEXP')      or define('URL_REGEXP',      '/^' . URL_REGEXP_STEM . '$/i');
defined('CSS_REGEXP')      or define('CSS_REGEXP',      '/^' . URL_REGEXP_STEM . '\.css(?:\?\S*)?$/i');
defined('IMAGE_REGEXP')    or define('IMAGE_REGEXP',    '/\b(' . URL_REGEXP_STEM . '\.(?:gif|png|webm|jpe?g|tiff?)(\?\S*)?)\b/i');
defined('SITELINK_REGEXP') or define('SITELINK_REGEXP', '(?:' . preg_quote(SITE_URL, '/') .  '|' . preg_quote(ALT_SITE_URL, '/') . ')');
defined('ARTIST_REGEXP')   or define('ARTIST_REGEXP',   '/^' . SITELINK_REGEXP . '\/artist\.php\?.*?\bid=(?P<id>\d+)$/');
defined('COLLAGE_REGEXP')  or define('COLLAGE_REGEXP',  '/^' . SITELINK_REGEXP . '\/collages\.php\?.*?\bid=(?P<id>\d+)\b/');
defined('TGROUP_REGEXP')   or define('TGROUP_REGEXP',   '/^' . SITELINK_REGEXP . '\/torrents\.php\?.*?\bid=(?P<id>\d+)\b/');
defined('TORRENT_REGEXP')  or define('TORRENT_REGEXP',  '/^' . SITELINK_REGEXP . '\/torrents\.php\?.*?\btorrentid=(?P<id>\d+)\b/');
defined('EMAIL_REGEXP')    or define('EMAIL_REGEXP',    '/^[\w-]+(?:\.[\w-]+)*(?:\+[.\w-]+)?@[\w-]+(?:\.[\w-]+)+$/');
defined('USERNAME_REGEXP') or define('USERNAME_REGEXP', '/\b(?:[01]$(*PRUNE)(*FAIL)|(?P<username>[\w.]{1,20}))\b/');

// ------------------------------------------------------------------------
// Common icons (emoji)

defined('ICON_ALL')          or define('ICON_ALL',          "\xe2\x9c\x85");
defined('ICON_NONE')         or define('ICON_NONE',         "\xf0\x9f\x9a\xab");
defined('ICON_TOGGLE')       or define('ICON_TOGGLE',       "\xf0\x9f\x94\x81");
defined('ICON_PADLOCK')      or define('ICON_PADLOCK',      "\xF0\x9F\x94\x92");
defined('ICON_STAR')         or define('ICON_STAR',         "\xE2\x98\x85");

// ------------------------------------------------------------------------
// Donor forum descriptions

defined('DONOR_FORUM_DESCRIPTION') or define('DONOR_FORUM_DESCRIPTION', [
    "I want only two houses, rather than seven... I feel like letting go of things",
    "A billion here, a billion there, sooner or later it adds up to real money.",
    "I've cut back, because I'm buying a house in the West Village.",
    "Some girls are just born with glitter in their veins.",
    "I get half a million just to show up at parties. My life is, like, really, really fun.",
    "Some people change when they think they're a star or something",
    "I'd rather not talk about money. It’s kind of gross.",
    "I have not been to my house in Bermuda for two or three years, and the same goes for my house in Portofino. How long do I have to keep leading this life of sacrifice?",
    "When I see someone who is making anywhere from $300,000 to $750,000 a year, that's middle class.",
    "Money doesn't make you happy. I now have $50 million but I was just as happy when I had $48 million.",
    "I'd rather smoke crack than eat cheese from a tin.",
    "I am who I am. I can’t pretend to be somebody who makes $25,000 a year.",
    "A girl never knows when she might need a couple of diamonds at ten o'clock in the morning.",
    "I wouldn't run for president. I wouldn't want to move to a smaller house.",
    "I have the stardom glow.",
    "What's Walmart? Do they like, sell wall stuff?",
    "Whenever I watch TV and see those poor starving kids all over the world, I can't help but cry. I mean I'd love to be skinny like that, but not with all those flies and death and stuff.",
    "Too much money ain't enough money.",
    "What's a soup kitchen?",
    "I work very hard and I’m worth every cent!",
    "To all my Barbies out there who date Benjamin Franklin, George Washington, Abraham Lincoln, you'll be better off in life. Get that money.",
]);

// ------------------------------------------------------------------------
// Collector settings

defined('ZIP_GROUP') or define('ZIP_GROUP', [
    0 => 'MP3 (VBR) - High Quality',
    1 => 'MP3 (VBR) - Low Quality',
    2 => 'MP3 (CBR)',
    3 => 'FLAC - Lossless',
    4 => 'Others',
]);
defined('ZIP_OPTION') or define('ZIP_OPTION', [
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

// ------------------------------------------------------------------------
// Opera Turbo (may include Opera-owned IP addresses that aren't used for Turbo, but shouldn't run much risk of exploitation)
// Useful: http://www.robtex.com/cnet/

defined('ALLOWED_PROXY') or define('ALLOWED_PROXY', [
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
