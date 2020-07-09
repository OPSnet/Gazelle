<?php

class CalendarView {
    private static $Days = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    private static $Headings = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    private static $Events;

    public static function render_title($Month, $Year) {
        if (!is_numeric($Month) || !is_numeric($Year)) {
            error(404);
        }

        $NextMonth = $Month % 12 == 0 ? 1 : $Month + 1;
        $PreviousMonth = $Month == 1 ? 12 : $Month - 1;
        $NextYear = $Year;
        if ($NextMonth == 1) {
            $NextYear++;
        }
        $PreviousYear = $Year;
        if ($PreviousMonth == 12) {
            $PreviousYear--;
        }
?>
        <h1 class="center">
            <a href="tools.php?action=calendar&amp;month=<?=$PreviousMonth?>&amp;year=<?=$PreviousYear?>">&lsaquo;</a>
            <?=date("F", mktime(0, 0, 0, $Month, 10)) . " $Year"?>
            <a href="tools.php?action=calendar&amp;month=<?=$NextMonth?>&amp;year=<?=$NextYear?>">&rsaquo;</a>
        </h1>
        <input type="hidden" id="month" value="<?=$Month?>" />
        <input type="hidden" id="year" value="<?=$Year?>" />
<?php
    }

    private static function get_events_on($Day, $Events) {
        // Linear search, Lol.
        $Results = [];
        foreach ($Events as $Event) {
            if ($Event['StartDay'] == $Day || ($Event['StartDay'] <= $Day && $Event['EndDay'] >= $Day)) {
                $Results[] = $Event;
            }
        }
        return $Results;
    }


    private static function render_events_day($Day, $Events) {
        $Events = self::get_events_on($Day, $Events);
        foreach ($Events as $Event) {
            $Color = Calendar::$Colors[Calendar::$Importances[$Event['Importance']]];
            $Category = Calendar::$Categories[$Event['Category']];
            $Tooltip = $Event['Title'] . " - " . Calendar::$Categories[$Event['Category']] . " - " . Calendar::$Importances[$Event['Importance']];
?>
            <p><a href="#" class="event_day tooltip" title="<?=$Tooltip?>" data-gazelle-id="<?=$Event['ID']?>" style="color: <?=$Color?>;"><?=Format::cut_string($Event['Title'], 8, true)?></a></p>
<?php
        }
    }

    public static function render_calendar($Month, $Year, $Events) {
        $RunningDay = date('w', mktime(0, 0, 0, $Month, 1, $Year));
        $DaysInMonth = date('t', mktime(0 ,0 ,0, $Month, 1, $Year));
        $DaysThisWeek = 1;
        $DayCounter = 0;
        $DatesArray = [];
?>

        <table class="calendar">
            <tr>
<?php        foreach (self::$Headings as $Heading) { ?>
                <td class="calendar-row calendar-heading">
                    <strong><?=$Heading?></strong>
                </td>
<?php        } ?>
            </tr>
            <tr class="calendar-row">

<?php        for ($x = 0; $x < $RunningDay; $x++) { ?>
                <td class="calendar-day-np"></td>
<?php
            $DaysThisWeek++;
        }

        for ($i = 1; $i <= $DaysInMonth; $i++) {
?>
                <td class="calendar-day">
                    <div class="day-events">
<?php                   self::render_events_day($i, $Events); ?>
                    </div>
                    <div class="day-number">
                        <?=$i?>
                    </div>
                </td>
<?php       if ($RunningDay == 6) { ?>
            </tr>
<?php           if (($DayCounter + 1) != $DaysInMonth) { ?>
            <tr class="calendar-row">
<?php
                }
                $RunningDay = -1;
                $DaysThisWeek = 0;
            }
            $DaysThisWeek++;
            $RunningDay++;
            $DayCounter++;
        }

        if ($DaysThisWeek < 8) {
            for ($x = 1; $x <= (8 - $DaysThisWeek); $x++) {
?>
                <td class="calendar-day-np"></td>
<?php
            }
        }
?>
            </tr>

        </table>
<?php
        echo $Calendar;
    }
}
