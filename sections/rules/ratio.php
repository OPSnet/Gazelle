<?php
View::show_header('Ratio Requirements');
?>
<div class="thin">
<?php
echo $Twig->render('rules/toc.twig');
$b   = $Viewer->downloadedSize();
$GiB = 1024 * 1024 * 1024;
echo $Twig->render('rules/ratio.twig', [
    'level_1'  => ($b <    5 * $GiB) ? 'a' : 'b',
    'level_2'  => ($b >=   5 * $GiB && $b <  10 * $GiB) ? 'a' : 'b',
    'level_3'  => ($b >=  10 * $GiB && $b <  20 * $GiB) ? 'a' : 'b',
    'level_4'  => ($b >=  20 * $GiB && $b <  30 * $GiB) ? 'a' : 'b',
    'level_5'  => ($b >=  30 * $GiB && $b <  40 * $GiB) ? 'a' : 'b',
    'level_6'  => ($b >=  40 * $GiB && $b <  50 * $GiB) ? 'a' : 'b',
    'level_7'  => ($b >=  50 * $GiB && $b <  60 * $GiB) ? 'a' : 'b',
    'level_8'  => ($b >=  60 * $GiB && $b <  80 * $GiB) ? 'a' : 'b',
    'level_9'  => ($b >=  80 * $GiB && $b < 100 * $GiB) ? 'a' : 'b',
    'level_10' => ($b >= 100 * $GiB) ? 'a' : 'b',
]);
?>
</div>
<?php
View::show_footer();
