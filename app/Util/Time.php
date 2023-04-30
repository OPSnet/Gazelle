<?php

namespace Gazelle\Util;

class Time {
    /**
     * Returns the number of seconds between now() and the given timestamp. If the timestamp
     * is an integer, we assume that's the nubmer of seconds you wish to subtract, otherwise
     * it's a string of a timestamp that we convert to a UNIX timestamp and then do a subtraction.
     * If the passed in $timestamp does not convert properly or is null, return false (error).
     */
    public static function timeAgo(string|int|null $timestamp): false|int {
        if ($timestamp === null) {
            return false;
        }

        if (($filter = filter_var($timestamp, FILTER_VALIDATE_INT)) !== false) {
            return $filter;
        } else {
            if ($timestamp == '' || is_null($timestamp)) {
                return false;
            }
            if (is_string($timestamp)) {
                $timestamp = strtotime($timestamp);
                if ($timestamp === false) {
                    return false;
                }
            }
            return time() - $timestamp;
        }
    }

    public static function diff(int|string|null $timestamp, int $levels = 2, bool $span = true, string|false $starttime = false, bool $hideAgo = false): string {
        $starttime = ($starttime === false) ? time() : strtotime($starttime);

        if ($timestamp === 0 || $timestamp === '' || is_null($timestamp)) {
            return 'Never';
        }
        if (is_string($timestamp)) {
            $timestamp = strtotime($timestamp);
            if ($timestamp === false) {
                return 'Never';
            }
        }
        $time = $starttime - $timestamp;

        // If the time is negative, then it expires in the future.
        if ($time < 0) {
            $time = -$time;
            $hideAgo = true;
        }

        $years = floor($time / 31_556_926); // seconds in one year
        $remain = $time - $years * 31_556_926;

        $months = floor($remain / 2_629_744); // seconds in one month
        $remain = $remain - $months * 2_629_744;

        $weeks = floor($remain / 604800); // seconds in one week
        $remain = $remain - $weeks * 604800;

        $days = floor($remain / 86400); // seconds in one day
        $remain = $remain - $days * 86400;

        $hours=floor($remain / 3600); // seconds in one hour
        $remain = $remain - $hours * 3600;

        $minutes = floor($remain / 60);

        $return = '';

        if ($years > 0 && $levels > 0) {
            if ($years > 1) {
                $return .= "$years years";
            } else {
                $return .= "$years year";
            }
            $levels--;
        }

        if ($months > 0 && $levels > 0) {
            if ($return != '') {
                $return .= ', ';
            }
            if ($months > 1) {
                $return .= "$months months";
            } else {
                $return .= "$months month";
            }
            $levels--;
        }

        if ($weeks > 0 && $levels > 0) {
            if ($return != '') {
                $return .= ', ';
            }
            if ($weeks > 1) {
                $return .= "$weeks weeks";
            } else {
                $return .= "$weeks week";
            }
            $levels--;
        }

        if ($days > 0 && $levels > 0) {
            if ($return != '') {
                $return .= ', ';
            }
            if ($days > 1) {
                $return .= "$days days";
            } else {
                $return .= "$days day";
            }
            $levels--;
        }

        if ($hours > 0 && $levels > 0) {
            if ($return != '') {
                $return .= ', ';
            }
            if ($hours > 1) {
                $return .= "$hours hours";
            } else {
                $return .= "$hours hour";
            }
            $levels--;
        }

        if ($minutes > 0 && $levels > 0) {
            if ($return != '') {
                $return .= ' and ';
            }
            if ($minutes > 1) {
                $return .= "$minutes mins";
            } else {
                $return .= "$minutes min";
            }
        }

        if ($return == '') {
            $return = 'Just now';
        } elseif (!$hideAgo) {
            $return .= ' ago';
        }

        if ($span) {
            return '<span class="time tooltip" title="'.date('M d Y, H:i', $timestamp).'">'.$return.'</span>';
        } else {
            return $return;
        }
    }

    /**
     * Converts a numeric amount of hours (though we round down via floor for all levels) into a more human readeable
     * string representing the number of years, months, weeks, days, and hours that make up that numeric amount. The
     * function then either surrounds the amount with a span or just returns the string. Giving a less than or equal
     * 0 hours to the function will return the string 'Never'.
     *
     * @param int $hours
     * @param int $levels
     * @param bool $span
     * @return string
     */
    public static function convertHours($hours, $levels = 2, $span = true) {
        if ($hours <= 0) {
            return 'Never';
        }

        $years = floor($hours/8760); // hours in a year
        $remain = $hours - $years*8760;

        $months = floor($remain/730); // hours in a month
        $remain = $remain - $months*730;

        $weeks = floor($remain/168); // hours in a week
        $remain = $remain - $weeks*168;

        $days = floor($remain/24); // hours in a day
        $remain = $remain - $days*24;

        $hours = floor($remain);

        $return = '';

        if ($years > 0 && $levels > 0) {
            $return .= $years.'y';
            $levels--;
        }

        if ($months > 0 && $levels > 0) {
            $return .= $months.'mo';
            $levels--;
        }

        if ($weeks > 0 && $levels > 0) {
            $return .= $weeks.'w';
            $levels--;
        }

        if ($days > 0 && $levels > 0) {
            $return .= $days.'d';
            $levels--;
        }

        if ($hours > 0 && $levels > 0) {
            $return .= $hours.'h';
        }

        if ($span) {
            return '<span>'.$return.'</span>';
        }
        else {
            return $return;
        }
    }


    /**
     * Converts a numeric amount of seconds (though we round down via floor for all levels) into a more human readeable
     * string representing the number of weeks, days, hours, minutes, seconds.
     */
    public static function convertSeconds(int $seconds): string {
        if ($seconds <= 0) {
            return '0s';
        }

        $interval = [($seconds % 60) .  's'];
        $minutes = (int)floor($seconds / 60);

        if ($minutes >= 60) {
            $minute = $minutes % 60;
            $hours  = (int)floor($minutes / 60);
        } else {
            $minute = $minutes;
            $hours  = 0;
        }
        if ($minute) {
            $interval[] = "{$minute}m";
        }

        if ($hours >= 24) {
            $hour = $hours % 24;
            $days = (int)floor($hours / 24);
        } else {
            $hour = $hours;
            $days  = 0;
        }
        if ($hour) {
            $interval[] = "{$hour}h";
        }

        if ($days >= 7) {
            $day = $days % 7;
            $week = (int)floor($days / 7);
        } else {
            $day = $days;
            $week  = 0;
        }
        if ($day) {
            $interval[] = "{$day}d";
        }
        if ($week) {
            $interval[] = "{$week}w";
        }
        return implode('', array_slice(array_reverse($interval), 0, 2));
    }

    /**
     * Utility function to generate a timestamp to insert into the database, given some offset
     */
    public static function offset(int $offset): false|string {
        return date('Y-m-d H:i:s', time() + $offset);
    }

    public static function sqlTime($timestamp = false) {
        if ($timestamp === false) {
            $timestamp = time();
        }
        return date('Y-m-d H:i:s', (int)$timestamp);
    }

    public static function validDate($date_string) {
        $date_time = explode(' ', $date_string);
        if (count($date_time) != 2) {
            return false;
        }
        [$date, $time] = $date_time;
        $split_time = explode(':', $time);
        if (count($split_time) != 3) {
            return false;
        }
        [$hour, $minute, $second] = $split_time;
        if ($hour != 0 && !(is_number($hour) && $hour < 24 && $hour >= 0)) {
            return false;
        }
        if ($minute != 0 && !(is_number($minute) && $minute < 60 && $minute >= 0)) {
            return false;
        }
        if ($second != 0 && !(is_number($second) && $second < 60 && $second >= 0)) {
            return false;
        }
        $split_date = explode('-', $date);
        if (count($split_date) != 3) {
            return false;
        }
        [$year, $month, $day] = $split_date;
        return checkdate($month, $day, $year);
    }

    public static function isValidDate($date) {
        return static::isValidDateTime($date, 'Y-m-d');
    }

    public static function isValidDateTime($date_time, $format = 'Y-m-d H:i') {
        $formatted_date_time = \DateTime::createFromFormat($format, $date_time);
        return $formatted_date_time && $formatted_date_time->format($format) == $date_time;
    }
}
