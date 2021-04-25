<?php
View::show_header('Collages Rules');
?>
<div class="thin">
<?php
echo $Twig->render('rules/toc.twig');
echo $Twig->render('rules/collage.twig');
?>
</div>
<?php
View::show_footer();
