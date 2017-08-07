<?
View::show_header('Disable Two-factor Authentication');
?>

<div class="box pad">
	<p>Please note that if you lose your 2FA key and all of your backup keys, the <?= SITE_NAME ?> staff cannot help you
		retrieve your account. Ensure you keep your backup keys in a safe place.</p>
</div>

<form method="post">
	<table cellpadding="6" cellspacing="1" border="0" width="100%" class="layout border">
		<thead>
		<tr class="colhead_dark">
			<td colspan="2">
				<strong>Please confirm your password to remove your 2FA.</strong>
			</td>
		</tr>
		</thead>

		<tbody>
		<tr>
			<td class="label">
				<label for="password"><strong>Password</strong></label>
			</td>

			<td>
				<input type="password" size="50" name="password" id="password"/>
				
				<? if (isset($_GET['invalid'])): ?>
					<p class="warning">Invalid password.</p>
				<? endif; ?>
			</td>
		</tr>

		<tr>
			<td colspan="2">
				<input type="submit">
			</td>
		</tr>
		</tbody>
	</table>
</form>

<? View::show_footer(); ?>
