<?php

if (defined('RECOVERY_AUTOVALIDATE') && RECOVERY_AUTOVALIDATE) {
    \Gazelle\Recovery::validate_pending(G::$DB);
}

\Gazelle\Recovery::boost_upload(G::$DB, G::$Cache);
