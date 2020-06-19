<?php

$whitelist = new \Gazelle\Manager\ClientWhitelist;
View::show_header('Client Rules');

?>
<div class="thin">
<?php

require('jump.php');
echo G::$Twig->render('rules/client-whitelist.twig', [
    'forum_thread' => CLIENT_WHITELIST_FORUM_ID,
    'list'         => $whitelist->list(),
    'site_url'     => site_url(),
]);

?>
</div>
<?php

View::show_footer();
