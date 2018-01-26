<?php

class Bonus {
	public static $Items = array(
		'1_token' => array(
			'Title' => '1 Freeleech Token',
			'Price' => 1000,
			'Action' => 'tokens',
			'Options' => array(
				'Amount' => 1
			)
		),
		'10_tokens' => array(
			'Title' => '10 Freeleech Tokens',
			'Price' => 9500,
			'Action' => 'tokens',
			'Options' => array(
				'Amount' => 10
			)
		),
		'50_tokens' => array(
			'Title' => '50 Freeleech Tokens',
			'Price' => 45000,
			'Action' => 'tokens',
			'Options' => array(
				'Amount' => 50
			)
		),
		'1_token_other' => array(
			'Title' => '1 Freeleech Token to Other',
			'Price' => 2500,
			'Action' => 'tokens',
			'Options' => array(
				'Amount' => 1,
				'Other' => 'true'
			),
			'Onclick' => 'ConfirmOther'
		),
		'10_tokens_other' => array(
			'Title' => '10 Freeleech Tokens to Other',
			'Price' => 24000,
			'Action' => 'tokens',
			'Options' => array(
				'Amount' => 10,
				'Other' => 'true'
			),
			'Onclick' => 'ConfirmOther'
		),
		'50_tokens_other' => array(
			'Title' => '50 Freeleech Tokens to Other',
			'Price' => 115000,
			'Action' => 'tokens',
			'Options' => array(
				'Amount' => 50,
				'Other' => 'true'
			),
			'Onclick' => 'ConfirmOther'
		),
		'title_nobbcode' => array(
			'Title' => 'Custom Title (No BBCode)',
			'Price' => 50000,
			'Action' => 'title',
			'Options' => array(
				'BBCode' => 'false'
			),
			'Free' => array(
				'Level' => 400
			),
			'Confirm' => false,
		),
		'title_bbcode' => array(
			'Title' => 'Custom Title (BBCode Allowed)',
			'Price' => 150000,
			'Action' => 'title',
			'Options' => array(
				'BBCode' => 'true'
			),
			'Free' => array(
				'Level' => 400
			),
			'Confirm' => false,
		),
		'title_remove' => array(
			'Title' => 'Remove Custom Title',
			'Price' => 0,
			'Action' => 'title',
			'Options' => array(
				'Remove' => 'true'
			)
		)
	);

	public static function get_list_other($balance) {
		$list_other = [];
		foreach (self::$Items as $title => $data) {
			if (isset(self::$Items[$title]['Options']) && isset(self::$Items[$title]['Options']) && isset(self::$Items[$title]['Options']['Other']) && $balance >= $data['Price']) {
				$list_other[] = [
					'name' => $title,
					'price' => $data['Price'],
					'after' => $balance - $data['Price'],
					'label' => preg_replace('/ to Other$/', '', $data['Title'])
				];
			}
		}
		return $list_other;
	}

	public static function give_token($fromID, $toID, $label) {
		if ($fromID === $toID) {
			return false;
		}
		if (!array_key_exists($label, self::$Items)) {
			return false;
		}
		$amount = self::$Items[$label]['Options']['Amount'];
		$price = self::$Items[$label]['Price'];
		if (!isset($price) and !($price > 0)) {
			return false;
		}
		$From = Users::user_info($fromID);
		$To = Users::user_info($toID);
		if ($From['Enabled'] != 1 || $To['Enabled'] != 1) {
			return false;
		}
		// get the bonus points of the giver from the database
		// verify they could be legally spent, and then update the receiver
		$stats = Users::user_stats($fromID, true);
		if ($stats['BonusPoints'] < $price) {
			return false;
		}
		G::$DB->prepared_query('UPDATE users_main SET BonusPoints = BonusPoints - ? WHERE BonusPoints > 0 AND ID = ?', $price, $fromID);
		if (G::$DB->affected_rows() != 1) {
			return false;
		}
		$new_stats = Users::user_stats($fromID, true);
		if (!($new_stats['BonusPoints'] >= 0 && $new_stats['BonusPoints'] < $stats['BonusPoints'])) {
			return false;
		}
		G::$DB->prepared_query("UPDATE users_main SET FLTokens = FLTokens + ? WHERE ID=?", $amount, $toID);
		if (G::$DB->affected_rows() != 1) {
			// as they say, "this could never happen to us"
			return false;
		}
		G::$Cache->delete_value("user_info_heavy_{$fromID}");
		G::$Cache->delete_value("user_info_heavy_{$toID}");
		// the calling code may not know this has been invalidated, so we cheat
		G::$LoggedUser['BonusPoints'] = $new_stats['BonusPoints'];
		self::send_pm_to_other($From['Username'], $toID, $amount);

		return true;
	}

	public static function send_pm_to_other($from, $toID, $amount) {
		if ($amount > 1) {
			$is_are = 'are';
			$s = 's';
		}
		else {
			$is_are = 'is';
			$s = '';
		}
		$to = Users::user_info($toID);
		$Body = "Hello {$to[Username]},

{$from} has sent you {$amount} freeleech token{$s} for you to use! " .
"You can use them to download torrents without getting charged any download. " .
"More details about them can be found on " .
"[url=".site_url()."wiki.php?action=article&id=57]the wiki[/url].

Enjoy!";
		Misc::send_pm($toID, 0, "Here {$is_are} {$amount} freeleech token{$s}!", trim($Body));
	}

	public static function get_price($Item) {
		$Price = $Item['Price'];
		if (isset($Item['Free'])) {
			if (isset($Item['Free']['Level']) && $Item['Free']['Level'] <= G::$LoggedUser['EffectiveClass']) {
				$Price = 0;
			}
		}
		return $Price;
	}
}
