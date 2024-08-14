<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title><?= SITE_NAME ?> :: Membership recovery</title>
<meta http-equiv="X-UA-Compatible" content="chrome=1; IE=edge" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" href="/apple-touch-icon.png" />
<link rel="stylesheet" href="<?= STATIC_SERVER ?>/styles/apollostage/style.css" />

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
<?php
/** @phpstan-var \Gazelle\Cache $Cache */

$ipaddr     = $_SERVER['REMOTE_ADDR'];
$key        = "apl-recovery.$ipaddr";
$rate_limit = 0;

if ($Cache->get_value($key)) {
    $msg = "Rate limiting in force.<br />You tried to save this page too rapidly following the previous save.";
} else {
    $info = $recovery->validate($_POST);
    if (count($info)) {
        $info['ipaddr']   = $ipaddr;
        $info['password_ok'] = $recovery->checkPassword($info['username'], $_POST['password']);

        [$ok, $filename] = $recovery->saveScreenshot($_FILES);
        if (!$ok) {
            $msg = $filename; // the reason we were unable to save the screenshot info
        } else {
            $info['screenshot'] = $filename;

            $token = '';
            for ($i = 0; $i < 16; ++$i) {
                $token .= chr(random_int(97, 97 + 25));
                if (($i + 1) % 4 == 0 && $i < 15) {
                    $token .= '-';
                }
            }
            $info['token'] = $token;

            if ($recovery->persist($info)) {
                $msg = 'ok';
            } else {
                $msg = "Unable to save, are you sure you haven't registered already?";
            }
        }
    } else {
        $msg = "Your upload was not accepted.";
    }
}
$Cache->cache_value($key, 1, 300);

if ($msg == 'ok') {
?>
<h3>Success!</h3>
<p>Your information has been uploaded and secured. It will be held for the next 30 days and then removed.</p>

<p>Please save the following token away for future reference. If you need to get in touch with Staff, this is the only
way you will be able to associate yourself with what you have just uploaded.<p>

<center><b><tt style="font-size: 20pt;"><?= $info['token'] ?></tt></b></center>

<p>Keep an eye on your mailbox, and we hope to see you soon. If you don't receive anything in an hour (check and
recheck your spam folder), join the <tt>#recovery</tt> channel on IRC.</p>

<blockquote>
<h4>IRC details</h4>
<p>Server: <tt><?= IRC_HOSTNAME ?></tt><br />
Port: 6667 or +7000 for SSL</p>
</blockquote>

<?php
} else {
?>
<h3>There was a problem</h3>
<p>Your information was not saved for the following reason.</p>

<center><b style="font-size: 20pt;"><?= $msg ?></b></center>

<p>Please wait five minutes and try again.</p>
<?php
}
?>
</div>
</body>
</html>
