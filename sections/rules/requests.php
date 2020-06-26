<?php
//Include the header
View::show_header('Request Rules');
?>
<div class="thin">
<?php
echo G::$Twig->render('rules/toc.twig');
echo G::$Twig->render('rules/request.twig');
?>
</div>
<?php
View::show_footer();
