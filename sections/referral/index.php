<?php
	if (isset($LoggedUser["ID"])) {
		header("Location: index.php");
		exit;
	}
	// redirect if referrals are currently closed
	if (!OPEN_EXTERNAL_REFERRALS) {
		View::show_header("Referrals are closed");
?>
<div class="thin">
	<strong clas="important_text">Sorry, the site is not accepting referral invites.</strong>
</div>
<?php
		View::show_footer();
		exit;
	}

	$ReferralManager = new Gazelle\Manager\Referral($DB, $Cache);
	$Accounts = $ReferralManager->getActiveAccounts();

	View::show_header('External Tracker Referrals');
?>
	<p style='max-width: 600px; font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;'>
		Welcome Home!
		<br /><br />
		If you had an account on Apollo at the time of the backup (June 2017), please use the recovery page to restore your account.
		<br /><br />
		If you are unsure if you are in the backup or not, use the recovery page.
		<br /><br />
		If you had an account on Apollo but you signed up after the backup date, you can use either the Referral page (if you are on PTP, BTN, MTV, EMP or 32P) or the Recovery page. To save us work and to ensure immediate registration, please use the Referral page if you can.
		<br /><br />
		If you did not have an account on Apollo but you would like to join Orpheus, and you are on PTP, BTN, MTV, EMP or 32P, feel free to use the referral page and join!
		<br /><br />
		See you on the other side.
	</p>
	<br />
	<div style="width: 50em; text-align: left">
		<h1>External Tracker Referrals</h1>
		<br/>
		<p>Here you are able to gain access to <?php echo SITE_NAME; ?> by verifying that you are a member of another private tracker that we trust.</p>
		<br/>
		<h4>The process is as follows:</h4>
		<br/>
		<ol>
			<li>Choose a tracker from the list that you're a member of.</li>
			<li><?php echo SITE_NAME; ?> will generate a string of characters that you will place in the body of your profile at the tracker of your choice.</li>
			<li>Paste the character string anywhere in the body of your profile and save it.</li>
			<li>Enter your username and <?php echo SITE_NAME; ?> will verify your membership and issue an invite code to you.</li>
			<li>Join <?php echo SITE_NAME; ?>!</li>
		</ol>

<?php 
	if (empty($_POST['action'])) {
		if (!empty($Accounts)) {
?>
		<br/>
		<h2>Choose a Tracker:</h2>
		<br/>
		<div class="center">
			<form name="referral_service" method="post" action="">
<?php
				foreach ($Accounts as $Account) {
					$ID = "site" . $Account["ID"];
?>
					<div>
						<input id="<?=$ID?>" type="radio" name="service" value="<?=$Account["ID"]?>"/>
						<label for="<?=$ID?>"><?=$Account["Site"]?></label>
					</div>
					<br/>
					<br/>
<?php } ?>
				<br/>
				<input type="hidden" name="action" value="account">
				<input type="submit" name="submit" value="Submit" class="submit" />
			</form>
		</div>
	<?php } else { ?>
		<br/>
		<h2>Sorry, we aren't accepting external tracker referrals at this time. Please try again later.</h2>
		<br/>
<?php
		}
	} else if ($_POST['action'] == 'account') {
		$Token = $ReferralManager->generateToken();
		$_SESSION['referral_token'] = $Token;
		$Account = $ReferralManager->getAccount($_POST['service']);
?>
		<br/>
		<h2>Step 2: Paste Your Code</h2>
		<br/>
		<p>Copy and paste the code below into the profile of your <?=$Account["Site"]?> account. It can go anywhere in your profile body (commonly known as "Profile info 1") as long as it is in one piece.</p>
		<br/>
		<p id="referral-code"><?=$Token?></p>
		<br/>
		<p>Enter the <?=$Account["UserIsId"] ? "user id" : "username"?> you use at <?=$Account["Site"]?> exactly as it appears on the site. This is critical in verifying your account.</p>
		<br/>
		<div class="center">
			<form name="referral_service" method="post" action="">
			<label for="username"><?=$Account["UserIsId"] ? "User Id" : "Username"?></label><input type="text" name="username" />
				<br/>
				<br/>
				<label for="Email">Email Address</label><input type="text" name="email" />
				<input type="hidden" name="token" value="<?=$Token?>" />
				<input type="hidden" name="service" value="<?=$Account["ID"]?>" />
				<input type="hidden" name="action" value="verify" />
				<br/>
				<br/>
				<input type="submit" name="submit" value="Verify" class="submit" />
			</form>
		</div>
	</div>
<?php
	} else if ($_POST['action'] == 'verify') {
		$Token = $_SESSION['referral_token'];
		if ($Token != $_POST['token']) {
			header("Location: referral.php");
		}

		$Email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
		$Error = false;
		$Invite = false;
		if (filter_var($Email, FILTER_VALIDATE_EMAIL)) {
			$Account = $ReferralManager->getFullAccount($_POST['service']);
			if ($Account["UserIsId"] && !preg_match('/^\d+$/', $_POST['username'])) {
				$Error = "You appear to have entered a username instead of your user id.";
			} else {
				$Verified = $ReferralManager->verifyAccount($Account, $_POST['username'], $Token);
				if ($Verified === true) {
					$Invite = $ReferralManager->generateInvite($Account, $_POST['username'], $Email);
					if ($Invite === false) {
						$Error = "Failed to generate invite.";
					}
				} else {
					$Error = $Verified;
				}
			}
		} else {
			$Error = "Invalid email address.";
		}
?>
		<br />
		<h2>Step 2: Join</h2>
		<br />
<?php 	if ($Error) { ?>
		<h3>There was an error verifying your account at <?=$Account["Site"]?>. Please refresh the page and try again.</h3>
		<br />
		<p><?=$Error?></p>
<?php 	} else {
			if (defined('REFERRAL_SEND_EMAIL') && REFERRAL_SEND_EMAIL) { ?>
				<h3>Congratulations, you have verified your account at <?=$Account["Site"]?>. We have sent you an email to the address you specified. Make sure to check your spam folder! Welcome to <?=SITE_NAME?>!</h3>
<?php		} else { ?>
				<h3>Congratulations, you have verified your account at <?=$Account["Site"]?>. <a href="https://<?=SITE_URL?>/register.php?invite=<?=$Invite?>">Click here</a> to register. Welcome to <?=SITE_NAME?></h3>
<?php		} ?>
	</div>
<?php
		}
	}
	View::show_footer();
?>
