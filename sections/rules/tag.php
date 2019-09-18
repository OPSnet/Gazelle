<?
//Include the header
View::show_header('Tagging rules');
?>
<!-- General Rules -->
<div class="thin">
<? include('jump.php'); ?>
    <div class="header">
        <h2 id="general">Tagging rules</h2>
    </div>
    <div class="box pad rule_summary" style="padding: 10px 10px 10px 20px;">
<?        Rules::display_site_tag_rules(false) ?>
    </div>
    <!-- END General Rules -->
</div>
<?
View::show_footer();
?>
