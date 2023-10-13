<?php
/*-- TODO ---------------------------//
Add in support for form id checks
Complete the number and date validation
Finish the GenerateJS stuff
//-----------------------------------*/

/**
 * Validator class
 *
 * This class is used to validate the contents of an associated array (usually $_GET or $_POST).
 * Basic usage of the class is:
 *
 * $Val = new Gazelle\Util\Validator;
 * $Val->setField('username', true, 'string', 'You need to set a username')
 *     ->setField('password', true, 'string', 'You need to set a password');
 *
 * if (!$Val->validate($_POST)) {
 *     error($Val->errorMessage());
 * }
 *
 * You can also use this class to create some validation JS to be put on a page (given a form ID), however, it's
 * not really clear how well this feature is actually used throughout the codebase.
 *
 * TODO: investigate how much we can replace in this class with usage of filter_var and its validation flags,
 * such as email and link for sure.
 */

namespace Gazelle\Util;

class Validator {
    protected array $Fields = [];
    protected string $errorMessage;

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
     */
    public function setField(string $FieldName, bool $Required, string $FieldType, string $ErrorMessage, array $Options = []): static {
        $this->Fields[$FieldName] = [
            'Type' => strtolower($FieldType),
            'Required' => $Required,
            'ErrorMessage' => $ErrorMessage,
        ];
        foreach (['allowcomma', 'allowperiod', 'comparefield', 'inarray', 'maxlength', 'minlength', 'range', 'regex'] as $option) {
            if (!empty($Options[$option])) {
                $this->Fields[$FieldName][$option] = $Options[$option];
            }
        }
        return $this;
    }

    public function setFields(array $fields): static {
        foreach ($fields as $f) {
            [$name, $required, $type, $message] = $f;
            $options = count($f) === 5 ? $f[4] : [];
            $this->setField($name, $required, $type, $message, $options);
        }
        return $this;
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
     *      preg_match using the EMAIL_REGEXP constant against the field to check that it passes
     * - link: Makes sure the length of the link falls between MinLength and MaxLength and performs a preg_match
     *      using the URL_REGEXP constant against the field
     * - username: checks that the length of the username falls within MinLength and MaxLength and performs a preg_match
     *      using the USERNAME_REGEXP constant against the field
     * - checkbox: just checks if the field exists within the associate array, doesn't matter the value
     * - compare: compares the field against field specified in the CompareField option. Useful for stuff like password
     *      where you have to input it twice, check that the second password equals the first one
     * - inarray: checks that the value specified in InArray option is in the field (which we assume is an array)
     * - regex: performs a preg_match of the value of Regex option and the field
     *
     * TODO: date fields are not actually validated, need to figure out what the proper validation syntax should be.
     */
    public function validate(array $ValidateArray): bool {
        reset($this->Fields);
        foreach ($this->Fields as $FieldKey => $Field) {
            $ValidateVar = $ValidateArray[$FieldKey] ?? null;

            if ($ValidateVar != '' || !empty($Field['Required']) || $Field['Type'] == 'date') {
                if ($Field['Type'] == 'string') {
                    if (isset($Field['range'])) {
                        [$MinLength, $MaxLength] = $Field['range'];
                    } else {
                        $MaxLength = $Field['maxlength'] ?? 255;
                        $MinLength = $Field['minlength'] ?? 1;
                    }
                    if ($MaxLength !== -1 && strlen($ValidateVar) > $MaxLength) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    } elseif ($MinLength !== -1 && strlen($ValidateVar) < $MinLength) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    }

                } elseif ($Field['Type'] == 'number') {
                    if (isset($Field['range'])) {
                        [$MinLength, $MaxLength] = $Field['range'];
                    } else {
                        $MaxLength = $Field['maxlength'] ?? false;
                        $MinLength = $Field['minlength'] ?? 0;
                    }

                    $Match = '0-9';
                    if (isset($Field['allowperiod'])) {
                        $Match .= '.';
                    }
                    if (isset($Field['allowcomma'])) {
                        $Match .= ',';
                    }

                    if (preg_match('/[^' . $Match . ']/', $ValidateVar) || strlen($ValidateVar) < 1) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    } elseif ($MaxLength !== false && $ValidateVar > $MaxLength) {
                        $this->errorMessage = $Field['ErrorMessage'] . '!';
                        break;
                    } elseif ($ValidateVar < $MinLength) {
                        $this->errorMessage = $Field['ErrorMessage'] . "$MinLength";
                        break;
                    }

                } elseif ($Field['Type'] == 'email') {
                    $MaxLength = $Field['maxlength'] ?? 255;
                    $MinLength = $Field['minlength'] ?? 6;

                    if (!preg_match(EMAIL_REGEXP, $ValidateVar)) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    } elseif (strlen($ValidateVar) > $MaxLength) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    } elseif (strlen($ValidateVar) < $MinLength) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    }

                } elseif ($Field['Type'] == 'link') {
                    $MaxLength = $Field['maxlength'] ?? 255;
                    $MinLength = $Field['minlength'] ?? 10;

                    if (!preg_match(URL_REGEXP, $ValidateVar)) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    } elseif (strlen($ValidateVar) > $MaxLength) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    } elseif (strlen($ValidateVar) < $MinLength) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    }

                } elseif ($Field['Type'] == 'username') {
                    if (!preg_match(USERNAME_REGEXP, $ValidateVar)) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    }

                } elseif ($Field['Type'] == 'checkbox') {
                    if (!isset($ValidateArray[$FieldKey])) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    }

                } elseif ($Field['Type'] == 'compare') {
                    if ($ValidateArray[$Field['comparefield']] != $ValidateVar) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    }

                } elseif ($Field['Type'] == 'inarray') {
                    if (array_search($ValidateVar, $Field['inarray']) === false) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    }

                } elseif ($Field['Type'] == 'regex') {
                    if (!preg_match($Field['regex'], $ValidateVar)) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    } elseif (isset($Field['maxlength']) && strlen($ValidateVar) > $Field['maxlength']) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    } elseif (isset($Field['minlength']) && strlen($ValidateVar) < $Field['minlength']) {
                        $this->errorMessage = $Field['ErrorMessage'];
                        break;
                    }
                }
            }
        }
        return !isset($this->errorMessage);
    }

    public function errorMessage(): ?string {
        return $this->errorMessage ?? null;
    }

    public function generateJS(string $FormID): string {
        $ReturnJS = "<script type=\"text/javascript\" language=\"javascript\">\r\n"
            . "//<![CDATA[\r\nfunction formVal() {\r\n    clearErrors('$FormID');\r\n";

        reset($this->Fields);
        foreach ($this->Fields as $FieldKey => $Field) {
            $ValItem = '';
            if ($Field['Type'] == 'string') {
                $ValItem = '    if ($(\'#' . $FieldKey . '\').raw().value == ""';
                if (!empty($Field['maxlength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > ' . $Field['maxlength'];
                } else {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > 255';
                }
                if (!empty($Field['minlength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < ' . $Field['minlength'];
                }
                $ValItem .= ') { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";

            } elseif ($Field['Type'] == 'number') {
                $Match = '0-9';
                if (!empty($Field['allowperiod'])) {
                    $Match .= '.';
                }
                if (!empty($Field['allowcomma'])) {
                    $Match .= ',';
                }

                $ValItem = '    if ($(\'#' . $FieldKey . '\').raw().value.match(/[^' . $Match . ']/) || $(\'#' . $FieldKey . '\').raw().value.length < 1';
                if (!empty($Field['maxlength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value/1 > ' . $Field['maxlength'];
                }
                if (!empty($Field['minlength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value/1 < ' . $Field['minlength'];
                }
                $ValItem .= ') { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";

            } elseif ($Field['Type'] == 'email') {
                $ValItem = '    if (!validEmail($(\'#' . $FieldKey . '\').raw().value)';
                if (!empty($Field['maxlength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > ' . $Field['maxlength'];
                } else {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > 255';
                }
                if (!empty($Field['minlength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < ' . $Field['minlength'];
                } else {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < 6';
                }
                $ValItem .= ') { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";

            } elseif ($Field['Type'] == 'link') {
                $ValItem = '    if (!validLink($(\'#' . $FieldKey . '\').raw().value)';
                if (!empty($Field['maxlength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > ' . $Field['maxlength'];
                } else {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > 255';
                }
                if (!empty($Field['minlength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < ' . $Field['minlength'];
                } else {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < 10';
                }
                $ValItem .= ') { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";

            } elseif ($Field['Type'] == 'username') {
                $ValItem = '    if ($(\'#' . $FieldKey . '\').raw().value.match(/[^a-zA-Z0-9_\-]/)';
                if (!empty($Field['maxlength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length > ' . $Field['maxlength'];
                }
                if (!empty($Field['minlength'])) {
                    $ValItem .= ' || $(\'#' . $FieldKey . '\').raw().value.length < ' . $Field['minlength'];
                }
                $ValItem .= ') { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";

            } elseif ($Field['Type'] == 'regex') {
                $ValItem = '    if (!$(\'#' . $FieldKey . '\').raw().value.match(' . $Field['regex'] . ')) { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";

            } elseif ($Field['Type'] == 'date') {
                $DisplayError = $FieldKey . 'month';
                if (isset($Field['minlength']) && $Field['minlength'] == 3) {
                    $Day = '$(\'#' . $FieldKey . 'day\').raw().value';
                    $DisplayError .= ",{$FieldKey}day";
                } else {
                    $Day = '1';
                }
                $DisplayError .= ",{$FieldKey}year";
                $ValItemHold = '    if (!validDate($(\'#' . $FieldKey . 'month\').raw().value+\'/\'+' . $Day . '+\'/\'+$(\'#' . $FieldKey . 'year\').raw().value)) { return showError(\'' . $DisplayError . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";

                if (empty($Field['Required'])) {
                    $ValItem = '    if ($(\'#' . $FieldKey . 'month\').raw().value != ""';
                    if (isset($Field['minlength']) && $Field['minlength'] == 3) {
                        $ValItem .= ' || $(\'#' . $FieldKey . 'day\').raw().value != ""';
                    }
                    $ValItem .= ' || $(\'#' . $FieldKey . 'year\').raw().value != "") {' . "\r\n";
                    $ValItem .= $ValItemHold;
                    $ValItem .= "    }\r\n";
                } else {
                    $ValItem .= $ValItemHold;
                }

            } elseif ($Field['Type'] == 'checkbox') {
                $ValItem = '    if (!$(\'#' . $FieldKey . '\').checked) { return showError(\'' . $FieldKey . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";

            } elseif ($Field['Type'] == 'compare') {
                $ValItem = '    if ($(\'#' . $FieldKey . '\').raw().value!=$(\'#' . $Field['comparefield'] . '\').raw().value) { return showError(\'' . $FieldKey . ',' . $Field['comparefield'] . '\',\'' . $Field['ErrorMessage'] . '\'); }' . "\r\n";
            }

            if (empty($Field['Required']) && $Field['Type'] != 'date') {
                $ReturnJS .= '    if ($(\'#' . $FieldKey . '\').raw().value!="") {' . "\r\n    ";
                $ReturnJS .= $ValItem;
                $ReturnJS .= "    }\r\n";
            } else {
                $ReturnJS .= $ValItem;
            }
        }
        return $ReturnJS . "}\r\n//]]>\r\n</script>\r\n";
    }
}
