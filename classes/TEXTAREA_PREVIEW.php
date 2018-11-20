<?php
/**
 * Textarea Preview Class
 *
 * This class generates a textarea that works with the JS preview script.
 *
 * Templates found in design/views/generic/textarea
 *
 * @example <pre><?php
 *  // Create a textarea with a name of content.
 *  // Buttons and preview divs are generated automatically near the textarea.
 *  new TEXTAREA_PREVIEW('content');
 *
 *  // Create a textarea with name and id body_text with default text and
 *  // no buttons or wrap preview divs.
 *  // Buttons and preview divs are generated manually
 *  $text = new TEXTAREA_PREVIEW('body_text', 'body_text', 'default text',
 *                  50, 20, false, false, ['disabled="disabled"', 'class="text"']);
 *
 *  $text->buttons(); // output buttons
 *
 *  $text->preview(); // output preview div
 *
 * // Create a textarea with custom preview wrapper around a table
 * // the table will be (in)visible depending on the toggle
 * $text = new TEXTAREA_PREVIEW('body', '', '', 30, 10, false, false);
 * $id = $text->getID();
 *
 * // some template
 * <div id="preview_wrap_<?=$id?>">
 *      <table>
 *          <tr>
 *              <td>
 *                  <div id="preview_<?=$id?>"></div>
 *              </td>
 *          </tr>
 *      </table>
 * </div>
 * </pre>
 */
class TEXTAREA_PREVIEW extends TEXTAREA_PREVIEW_SUPER
{
    /**
     * @var int Unique ID
     */
    private $id;

    /**
     * Flag for preview output
     * @var bool $preview
     */
    private $preview = false;

    /**
     * String table
     * @var string Buffer
     */
    private $buffer = null;

    /**
     * This method creates a textarea
     *
     * @param string $Name      name attribute
     * @param string $ID        id attribute
     * @param string $Value     default text attribute
     * @param string $Cols      cols attribute
     * @param string $Rows      rows attribute
     * @param bool   $Preview   add the preview divs near the textarea
     * @param bool   $Buttons   add the edit/preview buttons near the textarea
     * @param bool   $Buffer    doesn't output the textarea, use getBuffer()
     * @param array  $ExtraAttributes   array of attribute="value"
     * @param bool   $Required  is this a required textarea
     *
     * If false for $Preview, $Buttons, or $Buffer, use the appropriate
     * methods to add the those elements manually. Alternatively, use getID
     * to create your own.
     *
     * It's important to have the right IDs as they make the JS function properly.
     */
    public function __construct(
        $Name,
        $ID = '',
        $Value = '',
        $Cols = 50,
        $Rows = 10,
        $Preview = true,
        $Buttons = true,
        $Buffer = false,
        array $ExtraAttributes = [],
        $Required = false
    ) {
        $this->id = parent::$Textareas;
        parent::$Textareas += 1;
        array_push(parent::$_ID, $ID);

        if (empty($ID)) {
            $ID = 'quickpost_' . $this->id;
        }

        if (!empty($ExtraAttributes)) {
            $Attributes = ' ' . implode(' ', $ExtraAttributes);
        } else {
            $Attributes = '';
        }

        if ($Preview === true) {
            $this->preview();
        }

        $this->buffer = View::parse('generic/textarea/textarea.phtml', [
            'ID' => $ID,
            'NID' => $this->id,
            'Name' => &$Name,
            'Value' => &$Value,
            'Cols' => &$Cols,
            'Rows' => &$Rows,
            'Attributes' => &$Attributes,
            'Required' => ($Required === true) ? 'required' : ''
        ],
            $Buffer);

        if ($Buttons === true) {
            $this->buttons();
        }
    }

    /**
     * Outputs the divs required for previewing the AJAX content
     * Will only output once
     */
    public function preview()
    {
        if (!$this->preview) {
            View::parse('generic/textarea/preview.phtml', ['ID' => $this->id]);
        }
        $this->preview = true;
    }

    /**
     * Outputs the preview and edit buttons
     * Can be called many times to place buttons in different areas
     */
    public function buttons()
    {
        View::parse('generic/textarea/buttons.phtml', ['ID' => $this->id]);
    }

    /**
     * Returns the textarea's numeric ID.
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Returns textarea string when buffer is enabled in the constructor
     * @return string
     */
    public function getBuffer()
    {
        return $this->buffer;
    }
}
