s/'SITE_NAME', *'/&Gazelle Dev/
s/'SITE_HOST', *'/&localhost/

s|'(SITE_URL)', *'https://'.SITE_HOST|'\1', 'http://'.SITE_HOST.':8080'|

s|'(SERVER_ROOT(_LIVE)?)', *'/path|'\1', '/var/www|

s|'ANNOUNCE_HTTP_URL', *'|&http://localhost:34000|
s|'ANNOUNCE_HTTPS_URL', *'|&https://localhost:3400|

s/('SQLHOST', *')localhost/\1mysql/
s/('SPHINX(QL)?_HOST', *')(localhost|127\.0\.0\.1)/\1sphinxsearch/

s|('host' *=>) *'unix:///var/run/memcached.sock'(, *'port' *=>) *0|\1 'memcached'\2 11211|

s/('(DEBUG_MODE|DISABLE_IRC)',) *false/\1 true/

s|'SOURCE', *'|&DEV|

s|'TRACKER_SECRET', *'|&00000000000000000000000000000000|
s|'TRACKER_REPORTKEY', *'|&00000000000000000000000000000000|
s/('TRACKER_HOST', *')localhost/\1ocelot/
