<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<?
require('classes/config.php');
?>
<head>
<title><?= SITE_NAME ?> :: Membership recovery</title>
<meta http-equiv="X-UA-Compatible" content="chrome=1; IE=edge" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="shortcut icon" href="favicon.ico" />
<link rel="apple-touch-icon" href="apple-touch-icon.png" />
<link rel="stylesheet" href="/static/styles/apollostage/style.css" />

<style type="text/css" media="screen">
body{background-color:#212328;margin:0;font-size:0.9em;font-family:"Helvetica Neue",Helvetica,Arial,sans-serif}
.container{margin:30px auto 140px;width:900px;}
form{margin:30px auto 140px;width:700px;padding:20px}
a{color:#fff;text-decoration:none}
a:hover{text-decoration:underline}
p{margin:10px 0;font-size:18px;line-height:1.6em}
h5{padding-top:30px}
#suggestions{margin-top:35px;color:#fff}
#suggestions a,p{color:#fff;font-weight:200}
#suggestions a{font-size:14px;margin:0 10px}
    </style>
</head>

<body>
<div class="container">
<p style="text-align:center">
</p></div>

<div class="container">
<?
if (defined('RECOVERY') && RECOVERY) {
    include('sections/recovery/form.php');
}
else {
    include('sections/recovery/closed.php');
}
?>
</div>

</body>
</html>
