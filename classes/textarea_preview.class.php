<?php
/**
 * This super class is used to manage the ammount of textareas there are and to
 * generate the required JavaScript that enables the previews to work.
 */
class TEXTAREA_PREVIEW_SUPER
{
    /**
     * @static
     * @var int $Textareas Total number of textareas created
     */
    protected static $Textareas = 0;

    /**
     * @static
     * @var array $_ID Array of textarea IDs
     */
    protected static $_ID = [];

    /**
     * @static
     * @var bool For use in JavaScript method
     */
    private static $Executed = false;

    /**
     * This method should only run once with $all as true and should be placed
     * in the header or footer.
     *
     * If $all is true, it includes TextareaPreview and jQuery
     *
     * jQuery is required for this to work, include it in the headers.
     *
     * @static
     * @param bool $all Output all required scripts, otherwise just do iterator()
     * @example <pre><?php TEXT_PREVIEW::JavaScript(); ?></pre>
     * @return void
     */
    public static function JavaScript($all = true)
    {
        if (self::$Textareas === 0) {
            return;
        }
        if (self::$Executed === false && $all) {
            View::parse('generic/textarea/script.phtml');
        }

        self::$Executed = true;
        self::iterator();
    }

    /**
     * This iterator generates JavaScript to initialize each JavaScript
     * TextareaPreview object.
     *
     * It will generate a numeric or custom ID related to the textarea.
     * @static
     * @return void
     */
    private static function iterator()
    {
        $script = [];
        for ($i = 0; $i < self::$Textareas; $i++) {
            if (isset(self::$_ID[$i]) && is_string(self::$_ID[$i])) {
                $a = sprintf('%d, "%s"', $i, self::$_ID[$i]);
            } else {
                $a = $i;
            }
            $script[] = sprintf('[%s]', $a);
        }
        if (!empty($script)) {
            View::parse('generic/textarea/script_factory.phtml', ['script' => join(', ', $script)]);
        }
    }
}
