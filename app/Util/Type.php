<?php

namespace Gazelle\Util;

class Type {
    /**
     * Given a variable, which could be a string or numeric, test if that variable
     * is an integer (string or otherwise).
     *
     * TODO: replace current method with filter_var($variable, FILTER_VALIDATE_INT) !== false;
     * @param mixed $variable variable to test
     * @return bool              does the variable represent an integer
     */
    public static function isInteger($variable) {
        return $variable == strval(intval($variable));
    }

    /**
     * Given some value, test to see if it represents a boolean true/false value. This
     * covers booleans, strings that represent booleans (using the php.ini classification
     * of booleans with allowing string '0' and '1'), or the 0/1 integers. If the given
     * value does not fit any of the above criteria, then return null.
     *
     * @param  mixed $value
     * @return bool|null
     */
    public static function isBoolValue($value) {
        if (is_bool($value)) {
            return $value;
        }
        elseif (is_string($value)) {
            switch (strtolower($value)) {
                case 'true':
                case 'yes':
                case 'on':
                case '1':
                    return true;
                case 'false':
                case 'no':
                case 'off':
                case '0':
                    return false;
            }
        }
        elseif (is_numeric($value)) {
            if ($value == 1) {
                return true;
            }
            elseif ($value == 0) {
                return false;
            }
        }
        return null;
    }
}