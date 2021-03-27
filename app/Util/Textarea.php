<?php

namespace Gazelle\Util;

class Textarea extends \Gazelle\Base {

    /**
     * @static
     * @var Twig context
     */
    protected static $twig;

    /**
     * @static
     * @var array of textareas
     */
    protected static $list = [];

    /**
     * @var int Unique ID
     */
    protected $id;

    /**
     * @var string name and id of element
     */
    protected $name;

    /**
     * @var string initial textarea value
     */
    protected $value;

    /**
     * @var int rows
     */
    protected $rows;

    /**
     * @var int columns
     */
    protected $cols;

    public static function twig($twig) {
        self::$twig = $twig;
    }

    /**
     * This method must be called once to enable activation.
     */
    public static function activate(): string {
        if (!self::$list) {
            return '';
        }
        $html = '<script type="text/javascript" src="' . STATIC_SERVER . '/functions/textareapreview.class.js?v='
            . filemtime(SERVER_ROOT . '/public/static/functions/textareapreview.class.js')
            . '"></script>';
        $n = 0;
        foreach (self::$list as $name) {
            $html .= '<script type="text/javascript" class="preview_code">'
                . '$(document).ready(function () { TextareaPreview.factory([[' . $n++ . ', "' . $name . '"]]); });</script>';
        }
        self::$list = [];
        return $html;
    }

    /**
     * Create a textarea
     *
     * @param string name  name attribute
     * @param string value default text attribute
     * @param string cols  cols attribute
     * @param string rows  rows attribute
     */
    public function __construct(string $name, string $value, int $cols = 72, int $rows = 10) {
        parent::__construct();
        $this->id     = count(self::$list);
        self::$list[] = $name;
        $this->name   = $name;
        $this->value  = $value;
        $this->cols   = $cols;
        $this->rows   = $rows;
    }

    /**
     * emit the DOM elements for previewing the content
     */
    public function preview(): string {
        return '<div id="preview_wrap_' . $this->id . '" class="preview_wrap hidden"><div id="preview_'
            . $this->id . '" class="text_preview tooltip" title="Double-click to edit"></div></div>';
    }

    public function textarea(): string {
        return '<div id="textarea_wrap_' . $this->id . '" class="field_div textarea_wrap">'
            . '<textarea name="' . $this->name
            . '" id="' . $this->name . '" cols="' . $this->cols . '" rows="' . $this->rows . '">'
            . $this->value . '</textarea></div>';
    }

    /**
     * Emit the preview/edit button.
     */
    public function button(): string {
        return '<input type="button" class="hidden button_preview_'
            . $this->id . '" value="Preview" title="Preview text" />';
    }
}
