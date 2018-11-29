<?
/*-- TODO ---------------------------//
Writeup how to use the VALIDATE class, add in support for form id checks
Complete the number and date validation
Finish the GenerateJS stuff
//-----------------------------------*/

/**
 * Validate class
 *
 * This class is used to validate the contents of an associated array (most likely $_POST).
 *
 * Basic usage of the class would be:
 * $Validate = new VALIDATE();
 * $Validate->SetFields('username', true, 'string', 'You need to set a username');
 * $Validate->SetFields('password', true, 'string', 'You need to set a password');
 *
 * $Err = $Validate->ValidateForm($_POST);
 *
 * Where $Err should be NULL if the form was successfully validated, else it'll be a string
 * which indicates the first found error when validating the fields of the form.
 *
 * You can also use this class to create some validation JS to be put on a page (given a form ID), however, it's
 * not really clear how well this feature is actually used throughout the codebase.
 *
 * TODO: investigate how much we can replace in this class with usage of filter_var and its validation flags,
 * such as email and link for sure.
 */
class VALIDATE {
	var $Fields = array();

	/**
	 * Add a new field to be validated (or used for JS form generation) from the associated array to be validated.
	 * For each field, you need to give it a name (its key in the array), whether the field is required or not (fields
	 * that are not required and blank and not a date won't be checked, else it'll always be validated), the type of
	 * field (see below), the error message to show users if validation fails, and any options (while you can set
	 * all options, certain ones will only affect certain types).
	 *
	 * Listed here are all the allowed field types, as well as then the options that are used for that particular type.
	 * See below for how exactly each type is checked.
	 * - string
	 *      - MaxLength (if not set, defaults to 255)
	 *      - MinLength (if not set, defaults to 1)
	 * - number
	 *      - MaxLength (if not set, defaults to no upper bound)
	 *      - MinLength (if not set, defaults to 0
	 *      - AllowPeriod (allow a period in the number)
	 *      - AllowComma (allow a comma in the number)
	 * - email
	 *      - MaxLength (if not set, defaults to 255)
	 *      - MinLength (if not set, defaults to 6)
	 * - link
	 *      - MaxLength (if not set, defaults to 255)
	 *      - MinLength (if not set, defaults to 10)
	 * - username
	 *      - MaxLength (if not set, defaults to 20)
	 *      - MinLength (if not set, defaults to 1)
	 * - checkbox
	 * - compare
	 *      - CompareField (required), what other field should this one be compared to in validation array
	 * - inarray
	 *      - InArray (required), what value to check for within the value of the field/key in the validation array
	 * - regex
	 *      - Regex (required), regular expression string to use within preg_match
	 *
	 * @param string $FieldName
	 * @param bool   $Required
	 * @param string $FieldType
	 * @param string $ErrorMessage
	 * @param array  $Options
	 */
	function SetFields($FieldName, $Required, $FieldType, $ErrorMessage, $Options = array()) {
		$this->Fields[$FieldName]['Type'] = strtolower($FieldType);
		$this->Fields[$FieldName]['Required'] = $Required;
		$this->Fields[$FieldName]['ErrorMessage'] = $ErrorMessage;
		if (!empty($Options['maxlength'])) {
			$this->Fields[$FieldName]['MaxLength'] = $Options['maxlength'];
		}
		if (!empty($Options['minlength'])) {
			$this->Fields[$FieldName]['MinLength'] = $Options['minlength'];
		}
		if (!empty($Options['comparefield'])) {
			$this->Fields[$FieldName]['CompareField'] = $Options['comparefield'];
		}
		if (!empty($Options['allowperiod'])) {
			$this->Fields[$FieldName]['AllowPeriod'] = $Options['allowperiod'];
		}
		if (!empty($Options['allowcomma'])) {
			$this->Fields[$FieldName]['AllowComma'] = $Options['allowcomma'];
		}
		if (!empty($Options['inarray'])) {
			$this->Fields[$FieldName]['InArray'] = $Options['inarray'];
		}
		if (!empty($Options['regex'])) {
			$this->Fields[$FieldName]['Regex'] = $Options['regex'];
		}
	}

	/**
	 * Given an associate array, iterate through each key checking to see if we've set the field to be validated. If
	 * the field is not blank or it's required or it's a date, then we must validate, else we can skip this field.
	 *
	 * Note: Regular expression constants can be found in classes/regex.php
	 * Note: All checks against length (value for number type) is inclusive of the Min/Max lengths
	 *
	 * Field types and how we validate them (see above for options for each field type):
	 * - string: make sure the string's length is within the set MinLength and MaxLength
	 * - number: perform regular expression for digits + periods (if set) + commas (if set), and check that the numeric
	 *      falls within MinLength and MaxLength (using weak type coercion as necessary). This field cannot be left
	 *      empty.
	 * - email: Checks to make sure the length of the email falls within MinLength and MaxLength and performs a
	 *      preg_match using the EMAIL_REGEX constant against the field to check that it passes
	 * - link: Makes sure the length of the link falls between MinLength and MaxLength and performs a preg_match
	 *      using the URL_REGEX constant against the field
	 * - username: checks that the length of the username falls within MinLength and MaxLength and performs a preg_match
	 *      using the USERNAME_REGEX constant against the field
	 * - checkbox: just checks if the field exists within the associate array, doesn't matter the value
	 * - compare: compares the field against field specified in the CompareField option. Useful for stuff like password
	 *      where you have to input it twice, check that the second password equals the first one
	 * - inarray: checks that the value specified in InArray option is in the field (which we assume is an array)
	 * - regex: performs a preg_match of the value of Regex option and the field
	 *
	 * TODO: date fields are not actually validated, need to figure out what the proper validation syntax should be.
	 *
	 * @param  array $ValidateArray
	 * @return string|null
	 */
	function ValidateForm($ValidateArray) {
		reset($this->Fields);
		foreach ($this->Fields as $FieldKey => $Field) {
			$ValidateVar = $ValidateArray[$FieldKey];

			if ($ValidateVar != '' || !empty($Field['Required']) || $Field['Type'] == 'date') {
				if ($Field['Type'] == 'string') {
					if (isset($Field['MaxLength'])) {
						$MaxLength = $Field['MaxLength'];
					} else {
						$MaxLength = 255;
					}
					if (isset($Field['MinLength'])) {
						$MinLength = $Field['MinLength'];
					} else {
						$MinLength = 1;
					}

					if ($MaxLength !== -1 && strlen($ValidateVar) > $MaxLength) {
						return $Field['ErrorMessage'];
					} elseif ($MinLength !== -1 && strlen($ValidateVar) < $MinLength) {
						return $Field['ErrorMessage'];
					}

				} elseif ($Field['Type'] == 'number') {
					if (isset($Field['MaxLength'])) {
						$MaxLength = $Field['MaxLength'];
					} else {
						$MaxLength = '';
					}
					if (isset($Field['MinLength'])) {
						$MinLength = $Field['MinLength'];
					} else {
						$MinLength = 0;
					}

					$Match = '0-9';
					if (isset($Field['AllowPeriod'])) {
						$Match .= '.';
					}
					if (isset($Field['AllowComma'])) {
						$Match .= ',';
					}

					if (preg_match('/[^'.$Match.']/', $ValidateVar) || strlen($ValidateVar) < 1) {
						return $Field['ErrorMessage'];
					} elseif ($MaxLength != '' && $ValidateVar > $MaxLength) {
						return $Field['ErrorMessage'].'!!';
					} elseif ($ValidateVar < $MinLength) {
						return $Field['ErrorMessage']."$MinLength";
					}

				} elseif ($Field['Type'] == 'email') {
					if (isset($Field['MaxLength'])) {
						$MaxLength = $Field['MaxLength'];
					} else {
						$MaxLength = 255;
					}
					if (isset($Field['MinLength'])) {
						$MinLength = $Field['MinLength'];
					} else {
						$MinLength = 6;
					}

					if (!preg_match("/^".EMAIL_REGEX."$/i", $ValidateVar)) {
						return $Field['ErrorMessage'];
					} elseif (strlen($ValidateVar) > $MaxLength) {
						return $Field['ErrorMessage'];
					} elseif (strlen($ValidateVar) < $MinLength) {
						return $Field['ErrorMessage'];
					}

				} elseif ($Field['Type'] == 'link') {
					if (isset($Field['MaxLength'])) {
						$MaxLength = $Field['MaxLength'];
					} else {
						$MaxLength = 255;
					}
					if (isset($Field['MinLength'])) {
						$MinLength = $Field['MinLength'];
					} else {
						$MinLength = 10;
					}

					if (!preg_match('/^'.URL_REGEX.'$/i', $ValidateVar)) {
						return $Field['ErrorMessage'];
					} elseif (strlen($ValidateVar) > $MaxLength) {
						return $Field['ErrorMessage'];
					} elseif (strlen($ValidateVar) < $MinLength) {
						return $Field['ErrorMessage'];
					}

				} elseif ($Field['Type'] == 'username') {
					if (isset($Field['MaxLength'])) {
						$MaxLength = $Field['MaxLength'];
					} else {
						$MaxLength = 20;
					}
					if (isset($Field['MinLength'])) {
						$MinLength = $Field['MinLength'];
					} else {
						$MinLength = 1;
					}

					if (!preg_match(USERNAME_REGEX, $ValidateVar)) {
						return $Field['ErrorMessage'];
					} elseif (strlen($ValidateVar) > $MaxLength) {
						return $Field['ErrorMessage'];
					} elseif (strlen($ValidateVar) < $MinLength) {
						return $Field['ErrorMessage'];
					}

				} elseif ($Field['Type'] == 'checkbox') {
					if (!isset($ValidateArray[$FieldKey])) {
						return $Field['ErrorMessage'];
					}

				} elseif ($Field['Type'] == 'compare') {
					if ($ValidateArray[$Field['CompareField']] != $ValidateVar) {
						return $Field['ErrorMessage'];
					}

				} elseif ($Field['Type'] == 'inarray') {
					if (array_search($ValidateVar, $Field['InArray']) === false) {
						return $Field['ErrorMessage'];
					}

				} elseif ($Field['Type'] == 'regex') {
					if (!preg_match($Field['Regex'], $ValidateVar)) {
						return $Field['ErrorMessage'];
					}
				}
			}
		} // while
	} // function

	function GenerateJS($FormID) {
		$ReturnJS = "<script type=\"text/javascript\" language=\"javascript\">\r\n";
		$ReturnJS .= "//<![CDATA[\r\n";
		$ReturnJS .= "function formVal() {\r\n";
		$ReturnJS .= "	clearErrors('$FormID');\r\n";

		reset($this->Fields);
		foreach ($this->Fields as $FieldKey => $Field) {
			if ($Field['Type'] == 'string') {
				$ValItem = '	if ($(\'#'.$FieldKey.'\').raw().value == ""';
				if (!empty($Field['MaxLength'])) {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length > '.$Field['MaxLength'];
				} else {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length > 255';
				}
				if (!empty($Field['MinLength'])) {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length < '.$Field['MinLength'];
				}
				$ValItem .= ') { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

			} elseif ($Field['Type'] == 'number') {
				$Match = '0-9';
				if (!empty($Field['AllowPeriod'])) {
					$Match .= '.';
				}
				if (!empty($Field['AllowComma'])) {
					$Match .= ',';
				}

				$ValItem = '	if ($(\'#'.$FieldKey.'\').raw().value.match(/[^'.$Match.']/) || $(\'#'.$FieldKey.'\').raw().value.length < 1';
				if (!empty($Field['MaxLength'])) {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value/1 > '.$Field['MaxLength'];
				}
				if (!empty($Field['MinLength'])) {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value/1 < '.$Field['MinLength'];
				}
				$ValItem .= ') { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

			} elseif ($Field['Type'] == 'email') {
				$ValItem = '	if (!validEmail($(\'#'.$FieldKey.'\').raw().value)';
				if (!empty($Field['MaxLength'])) {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length > '.$Field['MaxLength'];
				} else {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length > 255';
				}
				if (!empty($Field['MinLength'])) {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length < '.$Field['MinLength'];
				} else {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length < 6';
				}
				$ValItem .= ') { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

			} elseif ($Field['Type'] == 'link') {
				$ValItem = '	if (!validLink($(\'#'.$FieldKey.'\').raw().value)';
				if (!empty($Field['MaxLength'])) {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length > '.$Field['MaxLength'];
				} else {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length > 255';
				}
				if (!empty($Field['MinLength'])) {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length < '.$Field['MinLength'];
				} else {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length < 10';
				}
				$ValItem .= ') { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

			} elseif ($Field['Type'] == 'username') {
				$ValItem = '	if ($(\'#'.$FieldKey.'\').raw().value.match(/[^a-zA-Z0-9_\-]/)';
				if (!empty($Field['MaxLength'])) {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length > '.$Field['MaxLength'];
				}
				if (!empty($Field['MinLength'])) {
					$ValItem .= ' || $(\'#'.$FieldKey.'\').raw().value.length < '.$Field['MinLength'];
				}
				$ValItem .= ') { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

			} elseif ($Field['Type'] == 'regex') {
				$ValItem = '	if (!$(\'#'.$FieldKey.'\').raw().value.match('.$Field['Regex'].')) { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

			} elseif ($Field['Type'] == 'date') {
				$DisplayError = $FieldKey.'month';
				if (isset($Field['MinLength']) && $Field['MinLength'] == 3) {
					$Day = '$(\'#'.$FieldKey.'day\').raw().value';
					$DisplayError .= ",{$FieldKey}day";
				} else {
					$Day = '1';
				}
				$DisplayError .= ",{$FieldKey}year";
				$ValItemHold = '	if (!validDate($(\'#'.$FieldKey.'month\').raw().value+\'/\'+'.$Day.'+\'/\'+$(\'#'.$FieldKey.'year\').raw().value)) { return showError(\''.$DisplayError.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

				if (empty($Field['Required'])) {
					$ValItem = '	if ($(\'#'.$FieldKey.'month\').raw().value != ""';
					if (isset($Field['MinLength']) && $Field['MinLength'] == 3) {
						$ValItem .= ' || $(\'#'.$FieldKey.'day\').raw().value != ""';
					}
					$ValItem .= ' || $(\'#'.$FieldKey.'year\').raw().value != "") {'."\r\n";
					$ValItem .= $ValItemHold;
					$ValItem .= "	}\r\n";
				} else {
					$ValItem .= $ValItemHold;
				}

			} elseif ($Field['Type'] == 'checkbox') {
				$ValItem = '	if (!$(\'#'.$FieldKey.'\').checked) { return showError(\''.$FieldKey.'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";

			} elseif ($Field['Type'] == 'compare') {
				$ValItem = '	if ($(\'#'.$FieldKey.'\').raw().value!=$(\'#'.$Field['CompareField'].'\').raw().value) { return showError(\''.$FieldKey.','.$Field['CompareField'].'\',\''.$Field['ErrorMessage'].'\'); }'."\r\n";
			}

			if (empty($Field['Required']) && $Field['Type'] != 'date') {
				$ReturnJS .= '	if ($(\'#'.$FieldKey.'\').raw().value!="") {'."\r\n	";
				$ReturnJS .= $ValItem;
				$ReturnJS .= "	}\r\n";
			} else {
				$ReturnJS .= $ValItem;
			}
			$ValItem = '';
		}

		$ReturnJS .= "}\r\n";
		$ReturnJS .= "//]]>\r\n";
		$ReturnJS .= "</script>\r\n";
		return $ReturnJS;
	}
}
?>
