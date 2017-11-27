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
			)
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
			)
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