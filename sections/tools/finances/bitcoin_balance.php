<?php

if (!$Viewer->permitted('admin_donor_log')) {
    error(403);
}

$Title = "Bitcoin Donation Balance";
$Balance = 0;
View::show_header($Title);

?>
<div class="header">
    <h2><?= $Title ?></h2>
    <h2>TODO</h2>
</div>
<?php
View::show_footer();
