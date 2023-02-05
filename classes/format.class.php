<?php
class Format {
    /**
     * Gets the CSS class corresponding to a ratio
     *
     * @param float $Ratio ratio to get the css class for
     * @return string the CSS class corresponding to the ratio range
     */
    public static function get_ratio_color($Ratio) {
        if ($Ratio < 0.1) { return 'r00'; }
        if ($Ratio < 0.2) { return 'r01'; }
        if ($Ratio < 0.3) { return 'r02'; }
        if ($Ratio < 0.4) { return 'r03'; }
        if ($Ratio < 0.5) { return 'r04'; }
        if ($Ratio < 0.6) { return 'r05'; }
        if ($Ratio < 0.7) { return 'r06'; }
        if ($Ratio < 0.8) { return 'r07'; }
        if ($Ratio < 0.9) { return 'r08'; }
        if ($Ratio < 1) { return 'r09'; }
        if ($Ratio < 2) { return 'r10'; }
        if ($Ratio < 5) { return 'r20'; }
        return 'r50';
    }

    /**
     * Calculates and formats a ratio.
     *
     * @param int $Dividend AKA numerator
     * @param int $Divisor
     * @param boolean $Color if true, ratio will be coloured.
     * @return string formatted ratio HTML
     */
    public static function get_ratio_html($Dividend, $Divisor, $Color = true) {
        $Ratio = self::get_ratio($Dividend, $Divisor);

        if ($Ratio === false) {
            return '--';
        }
        if ($Ratio === '∞') {
            return '<span class="tooltip r99" title="Infinite">∞</span>';
        }
        if ($Color) {
            $Ratio = sprintf('<span class="tooltip %s" title="%s">%s</span>',
                self::get_ratio_color($Ratio),
                self::get_ratio($Dividend, $Divisor, 5),
                $Ratio
            );
        }

        return $Ratio;
    }

    /**
     * Returns ratio
     * @param int $Dividend
     * @param int $Divisor
     * @param int $Decimal floor to n decimals (e.g. subtract .005 to floor to 2 decimals)
     * @return boolean|string
     */
    public static function get_ratio($Dividend, $Divisor, $Decimal = 2) {
        if ($Divisor == 0 && $Dividend == 0) {
            return false;
        }
        if ($Divisor == 0) {
            return '∞';
        }
        return number_format(max($Dividend / $Divisor - (0.5 / pow(10, $Decimal)), 0), $Decimal);
    }

    /**
     * Gets the query string of the current page, minus the parameters in $Exclude,
     * plus the parameters in $NewParams
     *
     * @param array $Exclude Query string parameters to leave out, or blank to include all parameters.
     * @param bool $Escape Whether to return a string prepared for HTML output
     * @param bool $Sort Whether to sort the parameters by key
     * @param array $NewParams New query items to insert into the URL
     * @return string A query string, optionally HTML-sanitized
     */
    public static function get_url(array $Exclude = [], $Escape = true, $Sort = false, array $NewParams = []) {
        $QueryItems = NULL;
        parse_str($_SERVER['QUERY_STRING'], $QueryItems);

        foreach ($Exclude as $Key) {
            unset($QueryItems[$Key]);
        }
        if ($Sort) {
            ksort($QueryItems);
        }

        $NewQuery = http_build_query(array_merge($QueryItems, $NewParams), '');
        return $Escape ? display_str($NewQuery) : $NewQuery;
    }

    /**
     * Format a size in bytes as a human readable string in KiB/MiB/...
     *        Note: KiB, MiB, etc. are the IEC units, which are in base 2.
     *            KB, MB are the SI units, which are in base 10.
     *
     * @param int $Size
     * @param int $Levels Number of decimal places. Defaults to 2, unless the size >= 1TB, in which case it defaults to 4.
     *                    or 0 in the case of bytes.
     * @return string formatted number.
     */
    public static function get_size($Size, $Levels = 2) {
        $Units = [' B', ' KiB', ' MiB', ' GiB', ' TiB', ' PiB', ' EiB', ' ZiB', ' YiB'];
        $Size = (double)$Size;
        for ($Steps = 0; abs($Size) >= 1024; $Size /= 1024, $Steps++) {
        }
        if (func_num_args() == 1 && $Steps >= 4) {
            $Levels++;
        }
        if ($Steps == 0) {
            $Levels = 0;
        }
        return number_format($Size, $Levels) . $Units[$Steps];
    }

    /**
     * Format a number as a multiple of its highest power of 1000 (e.g. 10035 -> '10.04k')
     *
     * @param int $Number
     * @return string formatted number.
     */
    public static function human_format($Number) {
        $Steps = 0;
        while ($Number >= 1000) {
            $Steps++;
            $Number = $Number / 1000;
        }
        switch ($Steps) {
            case 0: return (string)round($Number); break;
            case 1: return round($Number, 2).'k'; break;
            case 2: return round($Number, 2).'M'; break;
            case 3: return round($Number, 2).'G'; break;
            case 4: return round($Number, 2).'T'; break;
            case 5: return round($Number, 2).'P'; break;
            default:
                return round($Number, 2).'E + '.$Steps * 3;
        }
    }

    /**
     * Given a formatted string of a size, get the number of bytes it represents.
     *
     * @param string $Size formatted size string, e.g. 123.45k
     * @return Number of bytes it represents, e.g. (123.45 * 1024)
     */
    public static function get_bytes($Size) {
        list($Value, $Unit) = sscanf($Size, "%f%s");
        $Unit = ltrim($Unit);
        if (empty($Unit)) {
            return $Value ? round($Value) : 0;
        }
        switch (strtolower($Unit[0])) {
            case 'k': return round($Value * 1024);
            case 'm': return round($Value * 1048576);
            case 'g': return round($Value * 1073741824);
            case 't': return round($Value * 1099511627776);
            default: return 0;
        }
    }
}
