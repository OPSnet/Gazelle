<?php

if (!$Viewer->permitted('admin_manage_referrals')) {
    error(403);
}

$referralManager = new Gazelle\Manager\Referral;
$referralAccounts = $referralManager->getFullAccounts();

$cookie = [];
$params = [];
$hasResult = false;

if (isset($_POST['url'])) {
    authorize();
    $url = $_POST['url'];
    $proxy = new Gazelle\Util\Proxy(REFERRAL_KEY, REFERRAL_BOUNCER);
    $hasResult = true;
    $failedLogin = false;

    if (isset($_POST['account'])) {
        $account = $referralManager->getFullAccount($_POST['account']);
        $failedLogin = !$referralManager->loginAccount($account);
        $cookie = $account['Cookie'];
    } else {
        $cookie = $_POST['cookie'];
        if (strlen($cookie) < 2) {
            $cookie = [];
        }
        $cookie = json_decode($cookie);
        if (json_last_error() != JSON_ERROR_NONE) {
            $cookie = [];
        }
    }

    $params = $_POST['params'];
    $params = json_decode($params);
    if (json_last_error() != JSON_ERROR_NONE) {
        $params = [];
    }

    $post = isset($_POST['post']);

    $request = ['Url' => $url, 'Params' => $params, 'Cookie' => $cookie, 'Post' => $post];
    if ($failedLogin) {
        $response = ['status' => 500, 'cookies' => [], 'response' => 'Login failed.'];
    } else {
        $response = $proxy->fetch($url, $params, $cookie, $post);
    }
}

View::show_header("Referral Sandbox");
?>
<style type="text/css">
div#preview {display: none;}
</style>

<div class="header">
    <h2>Referral Sandbox</h2>
</div>
<?php if ($hasResult) { ?>
<div class="thin box pad">
    <div class="thin">
        <h3>Request</h3>
        <div class="box pad">
<?php var_dump($request); ?>
        </div>
    </div>
    <div class="thin">
        <h3>Response</h3>
        <div class="box pad">
            <a onclick="toggle_display('preview')" href="javascript:void(0)">Toggle Preview</a><br />
            <div id="preview">
                <iframe style="width: 100%; height: 600px;" srcdoc="<?=str_replace('"', '&quot;', str_replace('&', '&amp;', $response['response']))?>"></iframe>
            </div><br />
            <div>
<?php
if (str_contains($response['response'], '<html')) {
    $response['response'] = 'HTML body';
}
var_dump($response)
?>
            </div>
        </div>
    </div>
</div>
<?php } ?>
<div class="thin box pad">
    <h3>Manual</h3>
    <form class="send_form" action="" method="post">
        <input type="hidden" name="action" value="referral_sandbox" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <table class="layout">
            <tbody>
                <tr>
                    <td class="label">
                        <label for="url">URL</label>
                    </td>
                    <td>
                        <input style="width: 98%;" type="text" name="url" value="<?=$url?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="cookie">Cookies</label>
                    </td>
                    <td>
                        <textarea style="width: 98%;" name="cookie" cols="90" rows="8"><?=json_encode($cookie)?></textarea>
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="params">Parameters</label>
                    </td>
                    <td>
                        <textarea style="width: 98%;" name="params" cols="90" rows="8"><?=json_encode($params)?></textarea>
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="post">POST</label>
                    </td>
                    <td>
                        <input type="checkbox" name="post"<?=$post ? ' checked="checked"' : ""?> />
                    </td>
                </tr>
            </tbody>
        </table>
        <input type="submit" value="Submit" />
    </form>
</div>
<div class="thin box pad">
<?php if ($referralManager->readOnly) { ?>
    <p>
        <strong class="important_text">DB key not loaded - accounts suspended</strong>
    </p>
<?php } elseif (empty($referralAccounts)) { ?>
    <h3>Auto</h3>
    <p>No referral accounts found.</p>
<?php } else { ?>
    <h3>Auto</h3>
    <form class="send_form" action="" method="post">
        <input type="hidden" name="action" value="referral_sandbox" />
        <input type="hidden" name="auth" value="<?= $Viewer->auth() ?>" />
        <table class="layout">
            <tbody>
                <tr>
                    <td class="label">
                        <label for="url">URL</label>
                    </td>
                    <td>
                        <input style="width: 98%;" type="text" name="url" value="<?=$url?>" />
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="account">Account</label>
                    </td>
                    <td>
<?php
    foreach ($referralAccounts as $account) {
        $id = $account["ID"];
?>
                        <label for="<?=$id?>"><?=$account["Site"]?></label>
                        <input id="<?=$id?>" type="radio" name="account" value="<?=$id?>" />
<?php } ?>
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="params">Parameters</label>
                    </td>
                    <td>
                        <textarea style="width: 98%;" name="params" cols="90" rows="8"><?=json_encode($params)?></textarea>
                    </td>
                </tr>
                <tr>
                    <td class="label">
                        <label for="post">POST</label>
                    </td>
                    <td>
                        <input type="checkbox" name="post"<?=$post ? ' checked="checked"' : ""?> />
                    </td>
                </tr>
            </tbody>
        </table>
        <input type="submit" value="Submit" />
    </form>
<?php } ?>
</div>
<?php
View::show_footer();
?>
