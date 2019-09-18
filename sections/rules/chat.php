<?
//Include the header
View::show_header('Chat Rules');
?>

<div class="thin">
<? include('jump.php'); ?>
    <div class="box pad" style="padding: 10px 10px 10px 20px;">
        <p>Anything not allowed on the forums is also not allowed on IRC and vice versa. They are separated for convenience only.</p>
    </div>
    <br />
<!-- Forum Rules -->
    <h2 id="forums">Forum Rules</h2>
    <div class="box pad rule_summary" style="padding: 10px 10px 10px 20px;">
<?        Rules::display_forum_rules() ?>
    </div>
<!-- END Forum Rules -->

<!-- IRC Rules -->
    <h2 id="irc">IRC Rules</h2>
    <div class="box pad rule_summary" style="padding: 10px 10px 10px 20px;">
<?        Rules::display_irc_chat_rules() ?>
    </div>
</div>
<?
View::show_footer();
?>
