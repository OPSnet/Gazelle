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
}
