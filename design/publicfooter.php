</div>
<div id="foot">
<span class="links">
<?php
$Y = date('Y');
if ($Y != SITE_LAUNCH_YEAR) {
    $Y = SITE_LAUNCH_YEAR . "-$Y";
}
?>
Site and design &copy; <?= $Y ?> <?=SITE_NAME?> | <a href='https://github.com/OPSnet/Gazelle'>Project Gazelle</a>
</span>
</div>
</body>
</html>
