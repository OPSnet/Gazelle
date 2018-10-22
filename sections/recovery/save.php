<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<?
$SITENAME = "Orpheus";
?>
<head>
<title><?=$SITENAME?> :: Membership recovery</title>
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
<?

function email_check ($raw) {
    $raw = strtolower(trim($raw));
    $parts = explode('@', $raw);
    if (count($parts) != 2) {
        return null;
    }
    list($lhs, $rhs) = $parts;
    if ($rhs == 'gmail.com') {
        $lhs = str_replace('.', '', $lhs);
    }
    $lhs = preg_replace('/\+.*$/', '', $lhs);
    return [$raw, "$lhs@$rhs"];
}

function validate ($info) {
    $data = [];
    foreach (explode(' ', 'username email announce invite info') as $key) {
        if (!isset($info[$key])) {
            return [];
        }
        switch ($key) {
            case 'email':
                $email = email_check($_POST['email']);
                if (!$email) {
                    return [];
                }
                $data['email']       = $email[0];
                $data['email_clean'] = $email[1];
                break;

            default:
                $data[$key] = trim($info[$key]);
                break;
        }
    }
    return $data;
}

function save_screenshot($upload) {
    if (!isset($upload['screen'])) {
        return [false, "File form name missing"];
    }
    $file = $upload['screen'];
    if (!isset($file['error']) || is_array($file['error'])) {
        return [false, "Never received the uploaded file."];
    }
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return [true, null];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return [false, "File was too large, please make sure it is less than 10MB in size."];
        default:
            return [false, "There was a problem with the screenshot file."];
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        return [false, "File was too large, please make sure it is less than 10MB in size."];
    }
    $filename = sha1(RECOVERY_SALT . mt_rand(0, 10000000). sha1_file($file['tmp_name']));
    $destination = sprintf('%s/%s/%s/%s/%s',
        RECOVERY_PATH, substr($filename, 0, 1), substr($filename, 1, 1), substr($filename, 2, 1), $filename
    );
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return [false, "Unable to persist your upload ($filename = $destination)."];
    }
    return [true, $filename];
}

function persist($info) {
    G::$DB->prepared_query(
        "INSERT INTO recovery (token, ipaddr, username, passhash, email, email_clean, announce, screenshot, invite, info, state    )
                       VALUES (?,     ?,      ?,        ?,        ?,     ?,           ?,        ?,          ?,      ?,    'PENDING')",
        $info['token'],
        $info['ipaddr'],
        $info['username'],
        $info['passhash'],
        $info['email'],
        $info['email_clean'],
        $info['announce'],
        $info['screenshot'],
        $info['invite'],
        $info['info']
    );
    return G::$DB->affected_rows();
}

// ==== here we go ===============================

$ipaddr     = $_SERVER['REMOTE_ADDR'];
$key        = "apl-recovery.$ipaddr";
$rate_limit = 0;

if (G::$Cache->get_value($key)) {
    $msg = "Rate limiting in force.<br />You tried to save this page too rapidly after the previous save.";
}
else {
    $info = validate($_POST);
    if (count($info)) {
        $info['ipaddr']   = $ipaddr;
        $info['passhash'] = Users::make_password_hash($_POST['password']);

        list($ok, $filename) = save_screenshot($_FILES);
        if (!$ok) {
            $msg = $filename; // the reason we were unable to save the screenshot info
        }
        else {
            $info['screenshot'] = $filename;

            $token = '';
            for ($i = 0; $i < 16; ++$i) {
                $token .= chr(mt_rand(97, 97+25));
                if (($i+1) % 4 == 0 && $i < 15) {
                    $token .= '-';
                }
            }
            $info['token'] = $token;

            if (persist($info)) {
                $msg = 'ok';
            }
            else {
                $msg = "Unable to save, are you sure you haven't registered already?";
            }
        }
    }
    else {
        $msg = "Your upload was not accepted.";
    }
}
G::$Cache->cache_value($key, 1, 300);

if ($msg == 'ok') {
?>
<h3>Success!</h3>
<p>Your information has been uploaded and secured. It will be held for the next 30 days and removed afterwards.</p>

<p>Please save the following token away for future reference. If you need to get in touch with Staff, this is the only
way you will be able to associate yourself with what you have just uploaded.<p>

<center><b><tt style="font-size: 20pt;"><?= $info['token'] ?></tt></b></center>

<p>Keep an eye on your mailbox, and we hope to see you soon.</p>
<?
} else {
?>
<h3>There was a problem</h3>
<p>Your information was not saved for the following reason.

<center><b style="font-size: 20pt;"><?= $msg ?></b></center>

<p>Please wait five minutes and try again.</p>
<?
}
?>
</div>
</body>
</html>
