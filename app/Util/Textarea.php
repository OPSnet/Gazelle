<?php

namespace Gazelle\Util;

class Textarea extends \Gazelle\Base {

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

    /**
     * @var bool the id (preview_wrap_<n>) is handled manually in the markup
     */
    protected $previewManual = false;

    /**
     * @var int extra attributes on the textarea field
     */
    protected $extra = [];

    /**
     * This method must be called once to enable activation.
     */
    public static function activate(): string {
        if (!self::$list) {
            return '';
        }
        return '<script type="text/javascript" src="' . STATIC_SERVER . '/functions/textareapreview.class.js?v='
            . filemtime(SERVER_ROOT . '/public/static/functions/textareapreview.class.js')
            . '"></script><script type="text/javascript">console.log("foo");$(document).ready(function () {' . self::factory() . '});</script>';
    }

    /**
     * Emit the javascript required to activate the textareas dynamically (see the upload form)
     */
    public static function factory(): string {
        $html = 'console.log("bar");TextareaPreview.factory([' . implode(',', self::$list) . ']);';
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
        $this->name   = $name;
        $this->value  = $value;
        $this->cols   = $cols;
        $this->rows   = $rows;
        self::$list[] = "[{$this->id}, '$name']";
    }

    public function id(): int {
        return $this->id;
    }

    public function previewId(): string {
        return "preview_wrap_" . $this->id;
    }

    public function setAutoResize() {
        $this->extra[] = "onkeyup=\"resize('{$this->name}')\"";
        return $this;
    }

    public function setDisabled() {
        $this->extra[] = "disabled=\"disabled\"";
        return $this;
    }

    public function setPreviewManual(bool $previewManual) {
        $this->previewManual = $previewManual;
        return $this;
    }

    /**
     * emit the DOM elements for previewing the content
     */
    public function preview(): string {
        if ($this->previewManual) {
            $attr = [
                'class="preview_wrap"',
            ];
        } else {
            $attr = [
                'id="' . $this->previewId() . '"',
                'class="preview_wrap hidden"',
            ];
        }
        return '<div ' . implode(' ', $attr) . '><div id="preview_' . $this->id
            . '" class="text_preview tooltip" title="Double-click to edit"></div></div>';
    }

    public function field(): string {
        $attr = array_merge($this->extra, [
            'name="' . $this->name . '"',
            'id="' . $this->name . '"',
            'cols="' . $this->cols . '"',
            'rows="' . $this->rows . '"',
        ]);
        return '<div id="textarea_wrap_' . $this->id . '" class="field_div textarea_wrap">'
            . '<textarea ' . implode(' ', $attr ) . '>' . $this->value . '</textarea></div>';
    }

    /**
     * Emit the preview/edit button.
     */
    public function button(): string {
        return '<input type="button" class="hidden button_preview_'
            . $this->id . '" value="Preview" title="Preview text" />';
    }

    /**
     * Emit everything
     */
    public function emit(): string {
        return $this->preview()
            . $this->field()
            . $this->button();
    }
}
