<?php

namespace Gazelle\Util;

class Textarea extends \Gazelle\Base {
    protected static array $list = [];

    protected int $id;
    protected bool $previewManual = false;
    protected array $extra = [];

    /**
     * This method must be called once to enable activation.
     */
    public static function activate(): string {
        if (!self::$list) {
            return '';
        }
        return '<script type="text/javascript" src="' . STATIC_SERVER . '/functions/bbcode.js?v='
            . filemtime(SERVER_ROOT . '/public/static/functions/bbcode.js') . '"></script>'
            . '<script type="text/javascript" src="' . STATIC_SERVER . '/functions/textareapreview.class.js?v='
            . filemtime(SERVER_ROOT . '/public/static/functions/textareapreview.class.js')
            . '"></script><script type="text/javascript">document.addEventListener(\'DOMContentLoaded\', function () {' . self::factory() . '});</script>';
    }

    /**
     * Emit the javascript required to activate the textareas dynamically (see the upload form)
     */
    public static function factory(): string {
        $html = 'TextareaPreview.factory([' . implode(',', self::$list) . ']);';
        self::$list = [];
        return $html;
    }

    /**
     * Create a textarea
     *
     * @param string $name  name attribute
     * @param string $value default text attribute
     * @param int $cols  cols attribute
     * @param int $rows  rows attribute
     */
    public function __construct(
        protected readonly string $name,
        protected readonly string $value,
        protected readonly int $cols = 72,
        protected readonly int $rows = 10
    ) {
        $this->id     = count(self::$list);
        self::$list[] = "[{$this->id}, '$name']";
    }

    public function id(): int {
        return $this->id;
    }

    public function previewId(): string {
        return "preview_wrap_" . $this->id;
    }

    public function setDisabled(): static {
        $this->extra[] = "disabled=\"disabled\"";
        return $this;
    }

    public function setPreviewManual(bool $previewManual): static {
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
        $name = html_escape($this->name);
        $attr = array_merge($this->extra, [
            'name="' . $name . '"',
            'id="' . $name . '"',
            'cols="' . $this->cols . '"',
            'rows="' . $this->rows . '"',
            'onkeyup="resize(\'' . $name . '\')"',
        ]);
        return '<div id="textarea_wrap_' . $this->id . '" class="field_div textarea_wrap">'
            . '<textarea ' . implode(' ', $attr ) . '>' . html_escape($this->value) . '</textarea></div>';
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
