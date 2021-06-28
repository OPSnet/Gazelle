<?php
if (isset($Viewer)) {
    header("Location: index.php");
    exit;
}
View::show_header();
?>
<style>
.container{margin:30px auto 140px;width:70%;}
form{margin:30px auto 140px;width:700px;padding:20px}
p{margin:10px 0;font-size:18px;line-height:1.6em}
h5{padding-top:30px}
#suggestions{margin-top:35px;color:#e0e0e0}
#suggestions a,p{color:#fff;font-weight:200}
#suggestions a{font-size:14px;margin:0 10px}
</style>

<div id="logo">
<a href="/" style="margin-left: 0;"><img src="<?= STATIC_SERVER ?>/styles/public/images/loginlogo.png" alt="Orpheus Network" title="Orpheus Network" /></a>
</div>

<div class="container">
<?php
if (defined('RECOVERY') && RECOVERY) {
    require_once('form.php');
}
else {
    require_once('closed.php');
}
?>
</div>
<?php
View::show_footer();
