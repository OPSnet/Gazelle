<?php

namespace Gazelle\Util;

class Arrays {
    /**
     * Given an array, for every string value, apply trim
     */
    public static function trim(array $array): array {
        foreach ($array as $key => $value) {
            if (is_string($value)) {
                $array[$key] = trim($value);
            }
        }
        return $array;
    }
}
