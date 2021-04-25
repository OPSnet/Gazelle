<?php
//Include the header
View::show_header('Request Rules');
?>
<div class="thin">
<?php
echo $Twig->render('rules/toc.twig');
echo $Twig->render('rules/request.twig');
?>
</div>
<?php
View::show_footer();
