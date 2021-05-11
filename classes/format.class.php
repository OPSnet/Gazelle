<?php
class Format {
    /**
     * Torrent Labels
     * Map a common display string to a CSS class
     * Indexes are lower case
     * Note the "tl_" prefix for "torrent label"
     *
     * There are five basic types:
     * * tl_free (leech status)
     * * tl_snatched
     * * tl_reported
     * * tl_approved
     * * tl_notice (default)
     *
     * @var array Strings
     */
    private static $TorrentLabels = [
        'default'  => 'tl_notice',
        'snatched' => 'tl_snatched',

        'freeleech'          => 'tl_free',
        'neutral leech'      => 'tl_free tl_neutral',
        'personal freeleech' => 'tl_free tl_personal',

        'reported'              => 'tl_reported',
        'bad tags'              => 'tl_reported tl_bad_tags',
        'bad folders'           => 'tl_reported tl_bad_folders',
        'bad file names'        => 'tl_reported tl_bad_file_names',
        'missing lineage'       => 'tl_reported tl_missing_lineage',
        'cassette approved'     => 'tl_approved tl_cassete',
        'lossy master approved' => 'tl_approved tl_lossy_master',
        'lossy web approved'    => 'tl_approved tl_lossy_web'
    ];


    /**
     * Gets the CSS class corresponding to a ratio
     *
     * @param $Ratio ratio to get the css class for
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
     * Finds what page we're on and gives it to us, as well as the LIMIT clause for SQL
     * Takes in $_GET['page'] as an additional input
     *
     * @param int $PerPage Results to show per page
     * @param int $DefaultResult Optional, which result's page we want if no page is specified
     * If this parameter is not specified, we will default to page 1
     *
     * @return array<int, string> What page we are on, and what to use in the LIMIT section of a query
     * e.g. "SELECT [...] LIMIT $Limit;"
     */
    public static function page_limit($PerPage, $DefaultResult = 1) {
        if (!isset($_GET['page'])) {
            $Page = (int)ceil($DefaultResult / $PerPage);
            if ($Page == 0) {
                $Page = 1;
            }
            $Limit = $PerPage;
        } else {
            if (!is_number($_GET['page'])) {
                error(0);
            }
            $Page = $_GET['page'];
            if ($Page <= 0) {
                $Page = 1;
            }
            $Limit = $PerPage * $Page - $PerPage . ", $PerPage";
        }
        return [$Page, $Limit];
    }

    /* Get pages
     * Returns a page list, given certain information about the pages.
     *
     * @param int $StartPage: The current record the page you're on starts with.
     *        e.g. if you're on page 2 of a forum thread with 25 posts per page, $StartPage is 25.
     *        If you're on page 1, $StartPage is 0.
     *      FIXME: I don't think this is right and you want to pass in a number 1 - max page
     * @param int $TotalRecords: The total number of records in the result set.
     *        e.g. if you're on a forum thread with 152 posts, $TotalRecords is 152.
     * @param int $ItemsPerPage: Self-explanatory. The number of records shown on each page
     *        e.g. if there are 25 posts per forum page, $ItemsPerPage is 25.
     * @param int $ShowPages: The number of page links that are shown.
     *        e.g. If there are 20 pages that exist, but $ShowPages is only 11, only 11 links will be shown.
     * @param string $Anchor A URL fragment to attach to the links.
     *        e.g. '#comment12'
     * @return A sanitized HTML page listing.
     */
    public static function get_pages($StartPage, $TotalRecords, $ItemsPerPage, $ShowPages = 11, $Anchor = '') {
        global $Document, $Method;
        $Location = "$Document.php";
        $StartPage = ceil($StartPage);
        $TotalPages = 0;
        $Pages = '';

        if ($TotalRecords > 0) {
            $StartPage = min($StartPage, ceil($TotalRecords / $ItemsPerPage));

            $ShowPages--;
            $TotalPages = ceil($TotalRecords / $ItemsPerPage);

            if ($TotalPages > $ShowPages) {
                $StartPosition = $StartPage - round($ShowPages / 2);

                if ($StartPosition <= 0) {
                    $StartPosition = 1;
                } else {
                    if ($StartPosition >= ($TotalPages - $ShowPages)) {
                        $StartPosition = $TotalPages - $ShowPages;
                    }
                }

                $StopPage = $ShowPages + $StartPosition;

            } else {
                $StopPage = $TotalPages;
                $StartPosition = 1;
            }

            $StartPosition = max($StartPosition, 1);

            $QueryString = self::get_url(['page', 'post']);
            if ($QueryString != '') {
                $QueryString = "&amp;$QueryString";
            }

            if ($StartPage > 1) {
                $Pages .= "<a href=\"$Location?page=1$QueryString$Anchor\"><strong>&laquo; First</strong></a> ";
                $Pages .= "<a href=\"$Location?page=".($StartPage - 1).$QueryString.$Anchor.'" class="pager_prev"><strong>&lsaquo; Prev</strong></a> | ';
            }
            //End change

            for ($i = $StartPosition; $i <= $StopPage; $i++) {
                if ($i != $StartPage) {
                    $Pages .= "<a href=\"$Location?page=$i$QueryString$Anchor\">";
                }
                $Pages .= '<strong>';
                $PageStartPosition = (($i - 1) * $ItemsPerPage) + 1;
                if ($i * $ItemsPerPage > $TotalRecords) {
                    if ($PageStartPosition == $TotalRecords) {
                        $Pages .= $TotalRecords;
                    } else {
                        $Pages .= "$PageStartPosition-$TotalRecords";
                    }
                } else {
                    $Pages .= "$PageStartPosition-" . ($i * $ItemsPerPage);
                }

                $Pages .= '</strong>';
                if ($i != $StartPage) {
                    $Pages .= '</a>';
                }
                if ($i < $StopPage) {
                    $Pages .= ' | ';
                }
            }

            if ($StartPage && $StartPage < $TotalPages) {
                $Pages .= " | <a href=\"$Location?page=".($StartPage + 1).$QueryString.$Anchor.'" class="pager_next"><strong>Next &rsaquo;</strong></a> ';
                $Pages .= "<a href=\"$Location?page=$TotalPages$QueryString$Anchor\"><strong> Last &raquo;</strong></a>";
            }
        }
        if ($TotalPages > 1) {
            return $Pages;
        }
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
            case 0: return round($Number); break;
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

    /**
     * Echo data sent in a GET form field, useful for text areas.
     *
     * @param string $Index the name of the form field
     * @param boolean $Return if set to true, value is returned instead of echoed.
     * @return Sanitized value of field index if $Return == true
     */
    public static function form($Index, $Return = false) {
        if (!empty($_GET[$Index])) {
            if ($Return) {
                return display_str($_GET[$Index]);
            } else {
                echo display_str($_GET[$Index]);
            }
        }
    }


    /**
     * Convenience function to echo out selected="selected" and checked="checked" so you don't have to.
     *
     * @param string $Name the name of the option in the select (or field in $Array)
     * @param mixed $Value the value that the option must be for the option to be marked as selected or checked
     * @param string $Attribute The value returned/echoed is $Attribute="$Attribute" with a leading space
     * @param array $Array The array the option is in, defaults to GET.
     * @return void
     */
    public static function selected($Name, $Value, $Attribute = 'selected', $Array = []) {
        if (empty($Array)) {
            $Array = $_GET;
        }
        if (isset($Array[$Name]) && $Array[$Name] !== '') {
            if ($Array[$Name] == $Value) {
                echo " $Attribute=\"$Attribute\"";
            }
        }
    }

    /**
     * Return a CSS class name if certain conditions are met. Mainly useful to mark links as 'active'
     *
     * @param mixed $Target The variable to compare all values against
     * @param mixed $Tests The condition values. Type and dimension determines test type
     *                 Scalar: $Tests must be equal to $Target for a match
     *                 Vector: All elements in $Tests must correspond to equal values in $Target
     *                 2-dimensional array: At least one array must be identical to $Target
     * @param string $ClassName CSS class name to return
     * @param bool $AddAttribute Whether to include the "class" attribute in the output
     * @param string $UserIDKey Key in _REQUEST for a user ID parameter, which if given will be compared to $LoggedUser[ID]
     *
     * @return string class name on match, otherwise an empty string
     */
    public static function add_class($Target, $Tests, $ClassName, $AddAttribute, $UserIDKey = false) {
        global $LoggedUser;
        if ($UserIDKey && isset($_REQUEST[$UserIDKey]) && $LoggedUser['ID'] != $_REQUEST[$UserIDKey]) {
            return '';
        }
        $Pass = true;
        if (!is_array($Tests)) {
            // Scalars are nice and easy
            $Pass = $Tests === $Target;
        } elseif (!is_array($Tests[0])) {
            // Test all values in vectors
            foreach ($Tests as $Type => $Part) {
                if (!isset($Target[$Type]) || $Target[$Type] !== $Part) {
                    $Pass = false;
                    break;
                }
            }
        } else {
            // Loop to the end of the array or until we find a matching test
            foreach ($Tests as $Test) {
                $Pass = true;
                // If $Pass remains true after this test, it's a match
                foreach ($Test as $Type => $Part) {
                    if (!isset($Target[$Type]) || $Target[$Type] !== $Part) {
                        $Pass = false;
                        break;
                    }
                }
                if ($Pass) {
                    break;
                }
            }
        }
        if (!$Pass) {
            return '';
        }
        if ($AddAttribute) {
            return " class=\"$ClassName\"";
        }
        return " $ClassName";
    }


    /**
     * Modified accessor for the $TorrentLabels array
     *
     * Converts $Text to lowercase and strips non-word characters
     *
     * @param string $Text Search string
     * @return string CSS class(es)
     */
    public static function find_torrent_label_class($Text) {
        $Index = mb_eregi_replace('(?:[^\w\d\s]+)', '', strtolower($Text));
        if (isset(self::$TorrentLabels[$Index])) {
            return self::$TorrentLabels[$Index];
        } else {
            return self::$TorrentLabels['default'];
        }
    }

    /**
     * Creates a strong element that notes the torrent's state.
     * E.g.: snatched/freeleech/neutral leech/reported
     *
     * The CSS class is inferred using find_torrent_label_class($Text)
     *
     * @param string $Text Display text
     * @param string $Class Custom CSS class
     * @return string <strong> element
     */
    public static function torrent_label($Text, $Class = '') {
        if (empty($Class)) {
            $Class = self::find_torrent_label_class($Text);
        }
        return sprintf('<strong class="torrent_label tooltip %1$s" title="%2$s" style="white-space: nowrap;">%2$s</strong>',
                display_str($Class), display_str($Text));
    }

    /**
     * Formats a CSS class name from a Category ID
     * @global array $Categories
     * @param int|string $CategoryID This number will be subtracted by one
     * @return string
     */
    public static function css_category($CategoryID = 1) {
        global $Categories;
        return 'cats_' . strtolower(str_replace(['-', ' '], '',
                $Categories[$CategoryID - 1]));
    }
}
