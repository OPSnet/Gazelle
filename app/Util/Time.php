<?php

namespace Gazelle\Util;

class Time {
    /**
     * Returns the number of seconds between now() and the given timestamp. If the timestamp
     * is an integer, we assume that's the nubmer of seconds you wish to subtract, otherwise
     * it's a string of a timestamp that we convert to a UNIX timestamp and then do a subtraction.
     * If the passed in $timestamp does not convert properly or is null, return false (error).
     */
    public static function timeAgo(string|int|float|\DateTimeImmutable $time): false|int {
        $epoch = match (true) {
            is_numeric($time)                   => (int)$time,
            $time instanceof \DateTimeImmutable => (int)(time() - $time->format('U')),
            default                             => strtotime($time),
        };
        return $epoch === false ? false : time() - $epoch;
    }

    public static function diff(
        int|false|string|\DateTimeImmutable|null $time,
        int                                      $levels    = 2,
        bool                                     $span      = true,
        string|false                             $starttime = false,
        bool                                     $hideAgo   = false,
    ): string {
        $starttime = ($starttime === false) ? time() : strtotime($starttime);
        if ($starttime === false || $time === 0 || $time === false || $time === '' || $time === null) {
            return 'Never';
        }
        $epoch = match (true) {
            is_numeric($time)                   => (int)$time,
            $time instanceof \DateTimeImmutable => (int)$time->format('U'),
            default                             => strtotime($time),
        };
        if ($epoch === false) {
            return 'Never';
        }
        $seconds = $starttime - $epoch;

        // If the time is negative, then it expires in the future.
        if ($seconds < 0) {
            $seconds = -$seconds;
            $hideAgo = true;
        }

        $years  = (int)floor($seconds / 31_556_926); // seconds in one year
        $remain = $seconds - $years * 31_556_926;

        $months = (int)floor($remain / 2_629_744); // seconds in one month
        $remain = $remain - $months * 2_629_744;

        $weeks  = (int)floor($remain / 604800); // seconds in one week
        $remain = $remain - $weeks * 604800;

        $days   = (int)floor($remain / 86400); // seconds in one day
        $remain = $remain - $days * 86400;

        $hours  = (int)floor($remain / 3600); // seconds in one hour
        $remain = $remain - $hours * 3600;

        $minutes = (int)floor($remain / 60);

        $timespec = '';

        if ($years > 0 && $levels > 0) {
            $timespec .= "$years year" . plural($years);
            $levels--;
        }

        if ($months > 0 && $levels > 0) {
            if ($timespec != '') {
                $timespec .= ', ';
            }
            $timespec .= "$months month" . plural($months);
            $levels--;
        }

        if ($weeks > 0 && $levels > 0) {
            if ($timespec != '') {
                $timespec .= ', ';
            }
            $timespec .= "$weeks week" . plural($weeks);
            $levels--;
        }

        if ($days > 0 && $levels > 0) {
            if ($timespec != '') {
                $timespec .= ', ';
            }
            $timespec .= "$days day" . plural($days);
            $levels--;
        }

        if ($hours > 0 && $levels > 0) {
            if ($timespec != '') {
                $timespec .= ', ';
            }
            $timespec .= "$hours hour" . plural($hours);
            $levels--;
        }

        if ($minutes > 0 && $levels > 0) {
            if ($timespec != '') {
                $timespec .= ' and ';
            }
            $timespec .= "$minutes minute" . plural($minutes);
        }

        if ($timespec == '') {
            $timespec = 'Just now';
        } elseif (!$hideAgo) {
            $timespec .= ' ago';
        }

        return $span
            ? ('<span class="time tooltip" title="' . date('Y-m-d H:i', $epoch) . "\">$timespec</span>")
            : $timespec;
    }

    /**
     * Converts a numeric amount of hours (though we round down via floor for all levels) into a more human readeable
     * string representing the number of years, months, weeks, days, and hours that make up that numeric amount. The
     * function then either surrounds the amount with a span or just returns the string. Giving a less than or equal
     * 0 hours to the function will return the string 'Never'.
     */
    public static function convertHours(int $hours, int $levels = 2, bool $span = true): string {
        if ($hours <= 0) {
            return 'Never';
        }

        $years = floor($hours / 8760); // hours in a year
        $remain = $hours - $years * 8760;

        $months = floor($remain / 730); // hours in a month
        $remain = $remain - $months * 730;

        $weeks = floor($remain / 168); // hours in a week
        $remain = $remain - $weeks * 168;

        $days = floor($remain / 24); // hours in a day
        $remain = $remain - $days * 24;

        $hours = floor($remain);

        $return = '';

        if ($years > 0 && $levels > 0) {
            $return .= $years . 'y';
            $levels--;
        }

        if ($months > 0 && $levels > 0) {
            $return .= $months . 'mo';
            $levels--;
        }

        if ($weeks > 0 && $levels > 0) {
            $return .= $weeks . 'w';
            $levels--;
        }

        if ($days > 0 && $levels > 0) {
            $return .= $days . 'd';
            $levels--;
        }

        if ($hours > 0 && $levels > 0) {
            $return .= $hours . 'h';
        }

        if ($span) {
            return '<span>' . $return . '</span>';
        } else {
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

        $interval = [];
        $remainder = $seconds % 60;
        if ($remainder) {
            $interval[] = "{$remainder}s";
        }
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
            if ($week < 52) {
                $interval[] = "{$week}w";
            } else {
                $year = (int)floor($week / 52);
                $week = $week % 52;
                if ($week) {
                    $interval[] = "{$week}w";
                }
                $interval[] = "{$year}y";
            }
        }
        return implode('', array_slice(array_reverse($interval), 0, 2));
    }

    /**
     * Utility function to generate a timestamp to insert into the database, given some offset
     */
    public static function offset(int $offset): false|string {
        return date('Y-m-d H:i:s', time() + $offset);
    }

    public static function isValidDateTime($dateTime, $format = 'Y-m-d H:i'): bool {
        $formatted = \DateTime::createFromFormat($format, $dateTime);
        return $formatted && $formatted->format($format) === $dateTime;
    }
}
