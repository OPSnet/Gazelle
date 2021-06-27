<?php
if (!check_perms('users_mod')) {
    error(403);
}

$Tokens = (int)$_REQUEST['numtokens'];

if (isset($_REQUEST['addtokens'])) {
    authorize();

    if ($Tokens < 1) {
        error('Please enter a valid number of tokens.');
    }
    $sql = "
        UPDATE users_main um
        INNER JOIN user_flt uf ON (uf.user_id = um.ID) SET
            uf.tokens = uf.tokens + ?
        WHERE um.Enabled = '1'";
    if (!isset($_REQUEST['leechdisabled'])) {
        $sql .= "
            AND um.can_leech = 1";
    }
    $DB->prepared_query($sql, $Tokens);

    $DB->prepared_query("
        SELECT concat('user_info_heavy_', ID) as cacheKey FROM users_main
        WHERE Enabled = '1'
        " . (isset($_REQUEST['leechdisabled']) ? '' : ' AND can_leech = 1')
    );
    $ck = $DB->collect('cacheKey');
    $Cache->deleteMulti($ck);

    $message = '<div class="box pad">'
        . '<strong>' . number_format($Tokens) . ' freeleech tokens added to '
        . number_format(count($ck)) . ' enabled users'
        . (!isset($_REQUEST['leechdisabled']) ? ' with leeching privileges enabled' : '')
        . '.</strong></div>';

} elseif (isset($_REQUEST['cleartokens'])) {
    authorize();

    if ($Tokens < 1) {
        error('Please enter a valid number of tokens.');
    }

    if (isset($_REQUEST['onlydrop'])) {
        $where = "WHERE uf.tokens > ?";
    } elseif (!isset($_REQUEST['leechdisabled'])) {
        $where = "WHERE (um.Enabled = '1' AND um.can_leech = 1) OR uf.tokens > ?";
    } else {
        $where = "WHERE um.Enabled = '1' OR uf.tokens > ?";
    }

    $DB->prepared_query("SELECT concat('user_info_heavy_', ID) as cacheKey FROM users_main $where", $Tokens);
    $ck = $DB->collect('cacheKey');
    $Cache->deleteMulti($ck);

    $DB->prepared_query("
        UPDATE users_main um
        INNER JOIN user_flt uf ON (uf.user_id = um.ID) SET
            uf.tokens = ?
       $where
       ", $Tokens);

    $message = '<div class="box pad">'
        . '<strong>Freeleech tokens reduced to ' . number_format($Tokens)
        . ' for ' . number_format(count($ck)) . ' enabled users'
        . (!isset($_REQUEST['leechdisabled']) ? ' with leeching privileges enabled' : '')
        . '.</strong></div>';
}

View::show_header('Add sitewide tokens');
?>
<div class="header">
<h2>Manage freeleech token amounts</h2>
</div>

<?= $message ?>

<table><tr><td style="vertical-align:top;" width="50%">
<div class="box pad">
    <form class="add_form" name="fltokens" action="" method="post">
        <input type="checkbox" id="leechdisabled" name="leechdisabled" value="1" />
        <label for="leechdisabled">Grant tokens to users whose leeching privileges are suspended?</label><br />

        Tokens to add: <input type="text" name="numtokens" size="5" /><br /><br />

        <input type="hidden" name="action" value="tokens" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <input type="submit" name="addtokens" value="Add tokens" />
    </form>
</div>
</td><td style="vertical-align:top;" width="50%">
<div class="box pad">
    <form class="manage_form" name="fltokens" action="" method="post">
        <span id="droptokens">
        <input type="checkbox" id="onlydrop" name="onlydrop" value="1" onchange="$('#disabled').gtoggle(); return true;" />
        <label for="onlydrop">Include disabled users?</label></span><br />

        <span id="disabled">
        <input type="checkbox" id="leechdisabled" name="leechdisabled" value="1" onchange="$('#droptokens').gtoggle(); return true;" />
        <label for="leechdisabled">Include users whose leeching privileges are disabled?</label></span><br /><br />

        Maximum token limit: <input type="text" name="numtokens" size="5" /><br />
        Members with more tokens will have their total reduced to this limit.<br /><br />

        <input type="hidden" name="action" value="tokens" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <input type="submit" name="cleartokens" value="Set maximum token limit" />
    </form>
</div>
</td></tr></table>
<?php
View::show_footer();
