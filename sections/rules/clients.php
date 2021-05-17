<?php

$whitelist = new \Gazelle\Manager\ClientWhitelist;
View::show_header('Client Rules');

?>
<div class="thin">
<?php
echo $Twig->render('rules/toc.twig');
echo $Twig->render('rules/client-whitelist.twig', [
    'list' => $whitelist->list(),
]);
?>
</div>
<?php
View::show_footer();
