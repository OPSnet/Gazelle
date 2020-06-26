<?php
View::show_header('Collages Rules');
?>
<div class="thin">
<?php
echo G::$Twig->render('rules/toc.twig');
echo G::$Twig->render('rules/collage.twig');
?>
</div>
<?php
View::show_footer();
