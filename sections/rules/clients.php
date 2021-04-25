<?php

$whitelist = new \Gazelle\Manager\ClientWhitelist;
View::show_header('Client Rules');

?>
<div class="thin">
<?php
echo $Twig->render('rules/toc.twig');
echo $Twig->render('rules/client-whitelist.twig', [
    'forum_thread' => CLIENT_WHITELIST_FORUM_ID,
    'list'         => $whitelist->list(),
    'site_url'     => SITE_URL,
]);
?>
</div>
<?php

View::show_footer();
